<?php

namespace WEM\PressFsyncBotBundle\Event;

use Exception;

use Contao\Config;
use Contao\BackendUser;
use Contao\Environment;
use Contao\File;
use Contao\FilesModel;
use Contao\Image;
use Contao\StringUtil;
use Contao\System;

use Haste\Input\Input;
use Haste\Http\Response\JsonResponse;

use Inn42\Rawg;

use WEM\PressFsyncBotBundle\Model\DiscordEvent;
use WEM\PressFsyncBotBundle\Model\DiscordMessage;
use WEM\PressFsyncBotBundle\Model\Task;
use WEM\PressFsyncBotBundle\Model\TwitchEvent;
use WEM\PressFsyncBotBundle\Model\UserConfig;

/**
 * TODO
 *
 * Ajouter à la config :
 * - Fréquence de synchronisation des events
 * - Fréquence de synchronisation des messages
 *
 * Planning Web
 * - Les 4 prochains events dans un tableau
 *
 * Alertes live
 * - Message Discord (et channel)
 * - Tweet ?
 * - Mastodon ?
 *
 * Twitch ID & Secret dans les settings Contao
 *
 * Alerte VOD
 * - Message Discord (et channel)
 * - Depuis Youtube
 *
 * Alerte Podcast
 * - Message Discord (et channel)
 * - Depuis Spotify
 * - Depuis Deezer
 * - Depuis Bandcamp
 * - Depuis Soundcloud
 *
 * Alerte Article
 * - Message Discord (et channel)
 * - Depuis Wordpress
 * - Depuis Contao
 */
class GeneratePageListener extends \Controller
{
    protected $strTwitchClientId = null;
    protected $strTwitchClientSecret = null;
    protected $strDiscordToken = null;
    protected $strTwitchToken = null;
    protected $strTwitchTokenType = null;
    protected $arrTwitchCache = ['categories' => []];
    protected $arrDiscordCache = ['server_events' => []];

    public function getTwitchChannels()
    {
        $objConfigs = UserConfig::findByTwitchSyncSchedulePlanned();

        if (!$objConfigs || 0 === $objConfigs->count()) {
            return [];
        }

        $encryptionService = System::getContainer()->get('plenta.encryption');

        $arrConfigs = [];
        while ($objConfigs->next()) {
            $username = $encryptionService->decrypt($objConfigs->twitchUsername);

            $arrConfigs[$username] = $this->parseConfig($objConfigs->current());
        }

        return $arrConfigs;
    }

    protected function parseConfig($objConfig)
    {
        $encryptionService = System::getContainer()->get('plenta.encryption');

        $arrConfig = [
            'id' => $objConfig->id,
            'label' => $objConfig->username,
            'url' => 'https://www.twitch.tv/' . $username,
            'intro' => $objConfig->syncTwitchScheduleWithDiscordMessagesFormat,
            'color' => $objConfig->syncTwitchScheduleWithDiscordMessagesColor,
            'events_servers' => deserialize($objConfig->syncTwitchScheduleWithDiscordEventsServers),
            'events_messages' => deserialize($objConfig->syncTwitchScheduleWithDiscordMessagesRecipients),
            'broadcaster_id' => $encryptionService->decrypt($objConfig->twitchBroadcasterId)
        ];

        if ($objConfig->syncTwitchScheduleWithDiscordEventsFallbackPicture && $objFile = FilesModel::findByUuid($objConfig->syncTwitchScheduleWithDiscordEventsFallbackPicture)) {
            $arrConfig['default_picture'] = $objFile->path;
        }

        if ($objConfig->syncTwitchScheduleWithDiscordMessagesThumbnail && $objFile = FilesModel::findByUuid($objConfig->syncTwitchScheduleWithDiscordMessagesThumbnail)) {
            $arrConfig['avatar'] = $objFile->path;
        }

        return $arrConfig;
    }

    public function getYtChannels()
    {
        return [];
    }

    public function catchApiRequest($objPage, $objLayout, $objPageRegular)
    {
        global $objPage;

        if ($objPage->alias != "api") {
            return;
        }

        if (!Input::get('auto_item')) {
            return;
        }

        $encryptionService = System::getContainer()->get('plenta.encryption');
        $this->strTwitchClientId = $encryptionService->decrypt(Config::get('pfsTwitchClientId'));
        $this->strTwitchClientSecret = $encryptionService->decrypt(Config::get('pfsTwitchClientSecret'));
        $this->strDiscordToken = $encryptionService->decrypt(Config::get('pfsDiscordToken'));

        $arrTwitchChannels = $this->getTwitchChannels();
        $arrYtChannels = $this->getYtChannels();

        switch (Input::get('auto_item')) {
            case 'checklive':
                echo 'checklive';
            break;

            case 'checkyoutube':
                echo 'checkyoutube';
            break;

            case 'checkspotify':
                echo 'checkspotify';
            break;

            case 'checkinn42articles':
                echo 'checkinn42articles';
            break;

            case 'checkdystopeekarticles':
                echo 'checkdystopeekarticles';
            break;

            case 'executetasks':
                // Hardlock the timeout
                set_time_limit(60);

                // Retrieve sync tasks to do and execute them
                $objTasks = Task::findAll(['limit' => 30, 'order' => 'created_at ASC']);

                if (!$objTasks) {
                    return;
                }

                while ($objTasks->next()) {
                    // If the task did well, delete from table
                    if ($this->executeTask($objTasks->current())) {
                        $objTasks->delete();

                        // pause
                        sleep(1);
                    }
                }

            break;

            case 'syncschedule':
                // Story board of the tasks to plan
                // Retrieve all Twitch events
                // For each Twitch channel, retrieve all the streams planned within the next month
                // Check if the Twitch event is in the database
                foreach ($arrTwitchChannels as $t) {
                    // First, get the next events from the Twitch schedule
                    $r = $this->makeTwitchRequest(
                        'helix/schedule',
                        [
                            'broadcaster_id' => $t['broadcaster_id'],
                            'start_time' => date('Y-m-d\TH:i:sP'),
                            'utc_offset' => 60
                        ]
                    );

                    $arrTwitchEventsIds = [];
                    $hasChanges = false;

                    // Skip if there is nothing scheduled
                    if (empty($r['data']['segments'])) {
                        $strSql = 'user = "'.$t['broadcaster_id'].'"';

                        $objDatabaseEvents = DiscordEvent::findBy([$strSql], null);
                        if ($objDatabaseEvents && 0 < $objDatabaseEvents->count()) {
                            while ($objDatabaseEvents->next()) {
                                if (!$objDatabaseEvents->discord_event) {
                                    continue;
                                }

                                foreach ($t['events_servers'] as $s) {
                                    $this->addTask(
                                        "discord",
                                        "delete_event",
                                        sprintf('guilds/%s/scheduled-events/%s', $s, $objDatabaseEvents->discord_event),
                                        [],
                                        'DELETE'
                                    );
                                }

                                $hasChanges = true;
                            }
                        }

                        continue;
                    }

                    // Loop on the events

                    foreach ($r['data']['segments'] as $event) {
                        // Skip if event is in one month or after
                        if ($this->getTimestampFromTwitchDate($event['start_time']) >= strtotime("+1 month")) {
                            continue;
                        }

                        if (null !== $event['canceled_until']) {
                            continue;
                        }

                        // Sync the event in the database
                        $objTwitchEvent = $this->syncTwitchEvent($event, $t);
                    }
                }
            break;

            // Sync Database according to Twitch :
            // Add task to create Discord Event if it exists in Database but not in Discord
            // Add task to update Discord Event if it exists in Database and in Discord and it should be updated
            // Add task to delete Discord Event if it exists in Discord but not in Database
            case 'syncdiscordevents':
                // Retrieve the events
                $objEvents = TwitchEvent::findItems(['start_time_after' => time(), 'start_time_before' => strtotime("+1 week")]);

                // Nothing to do, skip
                if (!$objEvents || 0 === $objEvents) {
                    return;
                }

                // Store the events we add
                $arrServers = [];

                // Check if we must add/update items inside Discord
                while ($objEvents->next()) {
                    // Retrieve event config
                    $arrConfig = $this->parseConfig($objEvents->getRelated('user'));

                    // Retrieve the Discord event
                    $objDiscordEvent = DiscordEvent::findOneBy(['twitch_event="'.$objEvents->id.'"'], null);
                    $data['event'] = $objEvents->id;
                    $data['config'] = $arrConfig;

                    // Create task if it does not exists in Database
                    foreach ($arrConfig['events_servers'] as $s) {
                        if (!$objDiscordEvent) {
                            $this->addTask(
                                "discord",
                                "add_event",
                                sprintf('guilds/%s/scheduled-events', $s),
                                $data,
                                'POST'
                            );
                        }
                        // Create task if it does exists in Database but it should be updated
                        elseif ($this->shouldEventBeUpdatedOnDiscord($data, $objDiscordEvent)) {
                            $this->addTask(
                                "discord",
                                "update_event",
                                sprintf('guilds/%s/scheduled-events/%s', $s, $objDiscordEvent->discord_event),
                                $data,
                                'PATCH'
                            );
                        }

                        $arrServers[$s][] = $objEvents->id;
                    }
                }

                // Then delete events from Discord who:
                // 1/ are in Discord servers but not in the database anymore
                // 2/ are in Discord servers but are marked as canceled in the database
                // 3/ are in the database but are not in the listed events added by the loop above (we add a task if they have a Discord Event ID)
                if (!empty($arrServers)) {
                    foreach ($arrServers as $s => $events) {
                        // Retrieve Discord events for this server
                        // Litle cache system so we do not repeat unecessary requests
                        if (!array_key_exists($s, $this->arrDiscordCache['events_servers'])) {
                            $objDiscordEvents = $this->makeDiscordRequest(
                                sprintf('guilds/%s/scheduled-events', $s),
                                [],
                                'GET'
                            );

                            $this->arrDiscordCache['events_servers'][$s] = $objDiscordEvents;
                        } else {
                            $objDiscordEvents = $this->arrDiscordCache['events_servers'][$s];
                        }

                        // 1 & 2
                        foreach ($t['events_servers'] as $s) {
                            if ($objDiscordEvents) {
                                foreach ($objDiscordEvents as $e) {
                                    // 1
                                    if (0 === DiscordEvent::countItems(['discord_event' => $e['id']])) {
                                        $this->addTask(
                                            "discord",
                                            "delete_event",
                                            sprintf('guilds/%s/scheduled-events/%s', $s, $e['id']),
                                            [],
                                            'DELETE'
                                        );
                                    } else {
                                        $objEvent = DiscordEvent::findItems(['discord_event' => $e['id']], 1);

                                        // 2
                                        if (null !== $objEvent->getRelated('twitch_event')->canceled_until) {
                                            $this->addTask(
                                                "discord",
                                                "delete_event",
                                                sprintf('guilds/%s/scheduled-events/%s', $s, $e['id']),
                                                [],
                                                'DELETE'
                                            );
                                        }
                                    }
                                }
                            }
                        }

                        // 3
                        $strSql = 'twitch_event NOT IN(' . implode(',', $events) . ')';
                        $objDatabaseEvents = DiscordEvent::findItems([$strSql], null);
                        if ($objDatabaseEvents && 0 < $objDatabaseEvents->count()) {
                            while ($objDatabaseEvents->next()) {
                                if (!$objDatabaseEvents->discord_event) {
                                    continue;
                                }

                                foreach ($t['events_servers'] as $s) {
                                    $this->addTask(
                                        "discord",
                                        "delete_event",
                                        sprintf('guilds/%s/scheduled-events/%s', $s, $objDatabaseEvents->discord_event),
                                        [],
                                        'DELETE'
                                    );
                                }
                            }
                        }
                    }
                }
            break;

            // Format the Discord message, according to the next streams
            // Add Task to create/update Discord message
            case 'syncdiscordmessages':
                // Retrieve the events
                $objEvents = TwitchEvent::findItems(['start_time_after' => time(), 'start_time_before' => strtotime("+1 week")]);

                // Nothing to do, skip
                if (!$objEvents || 0 === $objEvents) {
                    return;
                }

                $arrConfigs = [];
                $arrEventsForMsg = [];
                $arrEventsIds = [];

                // Store config & parse events
                while ($objEvents->next()) {
                    // Store the config for later
                    $arrConfigs[$objEvents->user] = $this->parseConfig($objEvents->getRelated('user'));
                    $arrEventsForMsg[$objEvents->user][] = "Le " . date(Config::get('datimFormat'), $objEvents->start_time) . " - " . $objEvents->title;
                    $arrEventsIds[$objEvents->user][] = $objEvents->id;
                }

                // For each config, prepare embed
                foreach ($arrConfigs as $user => $arrConfig) {
                    // Prepare message
                    $arrEmbeds = [];
                    $arrEmbeds[] = [
                        'type' => 'rich',
                        'title' => $arrConfig['intro'],
                        'description' => !empty($arrEventsForMsg[$user]) ? implode("\n", $arrEventsForMsg[$user]) : 'Pas de streams prévus',
                        'url' => $arrConfig['url'],
                        'author' => [
                            'name' => $arrConfig['label'],
                            "url" => $arrConfig['url'],
                        ],
                        'timestamp' => date("c"),
                        'color' => hexdec($arrConfig['color'] ?: "FFFFFF"),
                        'thumbnail' => [
                            'url' => \Environment::get('base') . $arrConfig['avatar']
                        ]
                    ];

                    // If we detect changes between Twitch schedule & Database, add a task to also update the Discord messages
                    if (!empty($arrConfig['events_messages'])) {
                        foreach ($arrConfig['events_messages'] as $arrChannel) {
                            // Try to find a message id in database
                            $objMessage = DiscordMessage::findOneBy(['server='.$arrChannel['key'].' AND channel='.$arrChannel['value'].' AND user='.$arrConfig['broadcaster_id']], null);

                            if ($objMessage && $objMessage->events !== serialize($arrEventsIds[$user])) {
                                $this->addTask(
                                    "discord",
                                    "update_message",
                                    sprintf('channels/%s/messages/%s', $arrChannel['value'], $objMessage->message),
                                    ['embeds' => $arrEmbeds, 'flags' => 2, 'user' => $arrConfig['broadcaster_id'], 'server' => $arrChannel['key'], 'events' => $arrEventsIds[$user]],
                                    'PATCH'
                                );
                            } elseif (!$objMessage || !$objMessage->message) {
                                $this->addTask(
                                    "discord",
                                    "add_message",
                                    sprintf('channels/%s/messages', $arrChannel['value']),
                                    ['embeds' => $arrEmbeds, 'flags' => 2, 'user' => $arrConfig['broadcaster_id'], 'server' => $arrChannel['key'], 'events' => $arrEventsIds[$user]],
                                    'POST'
                                );
                            }
                        }
                    }
                }

            break;
        }
    }

    /**
     * Sync a Twitch event in the Database
     * @param  array $event Twitch event from API
     * @param  array $t     User config from database
     * @return TwitchEvent
     */
    protected function syncTwitchEvent($event, $t)
    {
        try {
            $objEvent = TwitchEvent::findItems(['twitch_event' => $event['id']], 1);

            if (!$objEvent) {
                $objEvent = new TwitchEvent();
            }

            $objEvent->tstamp = time();
            $objEvent->twitch_event = $event['id'];
            $objEvent->user = $t['id'];
            $objEvent->start_time = $this->getTimestampFromTwitchDate($event['start_time']);
            $objEvent->end_time = $this->getTimestampFromTwitchDate($event['end_time']);
            $objEvent->title = $event['title'];
            $objEvent->is_recurring = $event['is_recurring'] ? 1 : '';
            $objEvent->canceled_until = $this->getTimestampFromTwitchDate($event['canceled_until']);
            $objEvent->category_id = $event['category']['id'];
            $objEvent->category_name = $event['category']['name'];
            $objEvent->save();

            return $objEvent;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    protected function shouldEventBeUpdatedOnDiscord($event, $objEvent)
    {
        // If the title has changed, go
        $title = $event['title'] ?: $event['category']['name'];
        if (stripcslashes($title) !== $objEvent->title) {
            return true;
        }

        // If the date has changed, go
        if ($event['start_time'] !== $objEvent->start_time) {
            return true;
        }

        // Else, let it that way
        return false;
    }

    protected function shouldTaskBeAdded($strType, $strTask, $strEndpoint, $arrData, $strMethod)
    {
        return 0 === Task::countBy([sprintf("type='%s' AND task='%s' AND endpoint='%s' AND data=? AND method='%s'", $strType, $strTask, $strEndpoint, $strMethod)], serialize($arrData));
    }

    protected function addTask($strType, $strTask, $strEndpoint, $arrData, $strMethod)
    {
        // Add a way to skip the task system for debug purposes
        if ("1" === Input::get('debug')) {
            echo sprintf(
                'Add Task %s - %s - %s - %s - %s',
                $strType,
                $strTask,
                $strEndpoint,
                implode(' | ', $arrData),
                $strMethod
            );
            echo '<hr />';

            return;
        }

        if ($this->shouldTaskBeAdded($strType, $strTask, $strEndpoint, $arrData, $strMethod)) {
            $objTask = new Task();
            $objTask->created_at = microtime(true);
            $objTask->type = $strType;
            $objTask->task = $strTask;
            $objTask->endpoint = $strEndpoint;
            $objTask->data = serialize($arrData);
            $objTask->method = $strMethod;
            $objTask->save();
        }
    }

    protected function executeTask($objTask)
    {
        $data = $objTask->data ? deserialize($objTask->data) : [];
        if (array_key_exists('title', $data)) {
            $data['title'] = stripcslashes($data['title']);
        }
        $method = $objTask->method ?: "GET";
        $blnSuccess = true;

        switch ($objTask->type) {
            case 'discord':
                switch ($objTask->task) {
                    case 'add_event':
                    case 'update_event':
                        $objEvent = TwitchEvent::findByPk($data['event']);

                        $data['title'] = addslashes($objEvent->title);
                        $data['url'] = $data['config']['url'];
                        $data['user'] = $data['config']['broadcaster_id'];

                        if ($data['config']['default_picture']) {
                            $data['image'] = $data['config']['default_picture'];
                        }

                        unset($data['event']);
                        unset($data['config']);

                        $objResult = $this->makeDiscordRequest(
                            $objTask->endpoint,
                            $this->parseTwitchEvent($data),
                            $method
                        );

                        if (10070 === $objResult['code']) {
                            break;
                        }

                        // If we do not have an ID, it is because the request failed
                        // so we must break the switch
                        if (!$objResult['id']) {
                            $blnSuccess = false;
                            break;
                        }

                        $objEvent = DiscordEvent::findOneBy('discord_event', $objResult['id']);

                        if (!$objEvent) {
                            $objEvent = new DiscordEvent();
                        }

                        $objEvent->discord_event = $objResult['id'];
                        $objEvent->tstamp = time();
                        $objEvent->twitch_event = $data['id'];
                        $objEvent->start_time = $data['start_time'];
                        $objEvent->end_time = $data['end_time'];
                        $objEvent->title = $data['title'];
                        $objEvent->category_id = $data['category']['id'] ?: 0;
                        $objEvent->category_name = $data['category']['name'] ?: '';
                        $objEvent->user = $data['user'];
                        $objEvent->save();
                    break;

                    break;
                    case 'delete_event':
                        $objResult = $this->makeDiscordRequest(
                            $objTask->endpoint,
                            $data,
                            $method
                        );

                        // If we do not have an ID, it is because the request failed
                        // so we must break the switch
                        // We can have a 10070 code, which means the event was not found on the Discord server
                        // Not a biggie, just keep rollin', it should not be in our database either
                        if (null === $objResult) {
                            $blnSuccess = false;
                            break;
                        }

                        $eventId = explode("/", $objTask->endpoint);
                        $objEvent = DiscordEvent::findOneBy('discord_event', $eventId[3]);

                        if ($objEvent) {
                            $objEvent->delete();
                        }
                    break;
                    case 'add_message':
                    case 'update_message':
                        foreach ($data['embeds'] as &$e) {
                            $e['title'] = html_entity_decode($e['title']);
                            $e['description'] = html_entity_decode($e['description']);
                        }

                        $objResult = $this->makeDiscordRequest(
                            $objTask->endpoint,
                            $data,
                            $method
                        );

                        // If we do not have an ID, it is because the request failed
                        // so we must break the switch
                        if (!$objResult['id']) {
                            $blnSuccess = false;
                            break;
                        }

                        $objMessage = DiscordMessage::findOneBy(['server='.$data['server'].' AND channel='.$objResult['channel_id'].' AND user='.$data['user'].' AND message='.$objResult['id']], null);

                        if (!$objMessage) {
                            $objMessage = new DiscordMessage();
                        }

                        $objMessage->tstamp = time();
                        $objMessage->channel = $objResult['channel_id'];
                        $objMessage->message = $objResult['id'];
                        $objMessage->user = $data['user'];
                        $objMessage->events = serialize($data['events']);
                        $objMessage->server = $data['server'];
                        $objMessage->save();
                    break;

                    case 'delete_message':

                    break;
                }

            break;
            case 'twitch':
                $objResult = $this->makeTwitchRequest($objTask->endpoint, $data, $method);
            break;
            default:
                throw new Exception("Unkown task type");
        }

        return $blnSuccess;
    }

    /**
     * Parse a Twitch event in an useful array
     *
     * @param  array   $event    Data from Twitch
     * @param  array   $channel  Twitch channel
     * @param  integer $width    Picture width wanted
     * @param  integer $height   Picture height wanted
     *
     * @return array
     */
    protected function parseTwitchEvent($event, $width = 800, $height = 320)
    {
        $title = $event['title'] ?: $event['category']['name'];

        $data = [
            'name' => $title,
            'privacy_level' => 2,
            'scheduled_start_time' => $event['start_time'],
            'scheduled_end_time' => $event['end_time'],
            'entity_type' => 3,
            'entity_metadata' => [
                'location' => $event['url']
            ]
        ];

        // Retrieve game picture
        try {
            if ($event['category']['name']) {
                $objPicture = $this->getGamePicture($event['category']['name']);

                if (null !== $objPicture) {
                    $event['image'] = $objPicture->path;
                }
            }

            if (!$event['image']) {
                throw new Exception('No image found for this category and no fallback image to use');
            }

            // Format the picture
            $picture = Image::get($objPicture->path ?: $event['image'], $width, $height, 'crop');
            $objThumbnail = new File($picture);

            // If everything is ok, add the picture
            if ($objThumbnail) {
                $data['image'] = 'data:image/jpg;base64,'.base64_encode($objThumbnail->getContent());
            }
        } catch (Exception $e) {
            unset($data['image']);
        }

        // Return data
        return $data;
    }

    protected function getTwitchToken()
    {
        if (!$this->strTwitchTokenType && !$this->strTwitchTokenType) {
            $data = [
                'client_id' => $this->strTwitchClientId,
                'client_secret' => $this->strTwitchClientSecret,
                'grant_type' => 'client_credentials'
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://id.twitch.tv/oauth2/token');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/x-www-form-urlencoded",
            ]);

            $request = curl_exec($ch);
            curl_close($ch);

            $arrResult = json_decode($request, true);

            $this->strTwitchTokenType = ucfirst($arrResult['token_type']);
            $this->strTwitchToken = $arrResult['access_token'];
        }

        return [
            'access_token' => $this->strTwitchToken,
            'token_type' => $this->strTwitchTokenType,
        ];
    }

    protected function makeTwitchRequest($endpoint, $data = [], $method = 'GET')
    {
        $token = $this->getTwitchToken();
        $url = 'https://api.twitch.tv/' . $endpoint;

        $ch = curl_init();

        switch ($method) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);

                if (!empty($data)) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                }
            break;
            case 'GET':
                if (!empty($data)) {
                    $url .= '?' . http_build_query($data);
                }
            break;
            default:
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            sprintf('Authorization: %s %s', $token['token_type'], $token['access_token']),
            sprintf('Client-Id: %s', $this->strTwitchClientId)
        ]);

        $request = curl_exec($ch);
        curl_close($ch);

        return json_decode($request, true);
    }

    protected function makeDiscordRequest($endpoint, $data = [], $method = 'GET')
    {
        # Set endpoint
        $url = "https://discord.com/api/".$endpoint."";

        # Initialize new curl request
        $ch = curl_init();
        $f = fopen('request.txt', 'w');

        # Set headers, data etc..
        curl_setopt_array($ch, array(
            CURLOPT_URL            => $url,
            CURLOPT_HEADER         => 1,
            CURLOPT_HTTPHEADER     => array(
                'Authorization: Bot ' . $this->strDiscordToken,
                "Content-Type: application/json",
                "Accept: application/json"
            ),
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_STDERR         => $f,
        ));

        switch ($method) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
            break;
            case 'GET':
                // nothing
            break;
            default:
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }

        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }

        $request = curl_exec($ch);
        curl_close($ch);

        $headers = [];
        $request = rtrim($request);
        $response = explode("\n", $request);

        $headers['status'] = $response[0];
        array_shift($response);

        $body = end($response);
        array_pop($response);

        foreach ($response as $part) {
            $middle = explode(":", $part, 2);

            if (!$middle[0]) {
                continue;
            }

            if (!isset($middle[1])) {
                $middle[1] = null;
            }
            $headers[trim($middle[0])] = trim($middle[1]);
        }

        // dump($headers);
        // dump(json_decode($body, true));

        $response = json_decode($body, true);

        // If we hit the API limit, sleep for a while and relaunch the request
        if (array_key_exists('retry_after', $response)) {
            sleep(round($response['retry_after']));

            $response = $this->makeDiscordRequest($endpoint, $data, $method);
        }

        return $response;
    }

    protected function getGamePicture($name)
    {
        // Slugify the game name
        $strSlug = str_replace('.', '', $name);
        $strSlug = StringUtil::generateAlias($strSlug);
        $strPicturePath = 'files/rawg/'.substr($strSlug, 0, 1).'/'.$strSlug.'.jpg';

        $objFile = new File($strPicturePath);

        // Try to find a local picture for this game
        if ($objFile->exists()) {
            return $objFile;
        }

        // Else call Rawg API
        $arrGame = Rawg::getGame($name);

        // If background image
        if (!$arrGame['background_image']) {
            throw new Exception("No picture found for this game");
        }

        // Then call curl function to retrieve the picture
        $ch = curl_init($arrGame['background_image']);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
        $data = curl_exec($ch);
        curl_close($ch);

        $objFile->write($data);
        $objFile->close();

        return $objFile;
    }

    /**
     * Convert a DATE_ATOM format into a timestamp at the right timezone
     * @param  string $date
     * @return int
     */
    protected function getTimestampFromTwitchDate($date)
    {
        if (!$date) {
            return null;
        }

        $objDate = \DateTime::createFromFormat(DATE_ATOM, $date, new \DateTimeZone('UTC'));
        $objDate->setTimezone(new \DateTimeZone('Europe/Paris'));
        return $objDate->getTimestamp();
    }
}
