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

            $arrConfigs[$username] = [
                'label' => $objConfigs->username,
                'url' => 'https://www.twitch.tv/' . $username,
                'intro' => $objConfigs->syncTwitchScheduleWithDiscordMessagesFormat,
                'color' => $objConfigs->syncTwitchScheduleWithDiscordMessagesColor,
                'events_servers' => deserialize($objConfigs->syncTwitchScheduleWithDiscordEventsServers),
                'events_messages' => deserialize($objConfigs->syncTwitchScheduleWithDiscordMessagesRecipients),
                'broadcaster_id' => $encryptionService->decrypt($objConfigs->twitchBroadcasterId)
            ];

            if ($objConfigs->syncTwitchScheduleWithDiscordEventsFallbackPicture && $objFile = FilesModel::findByUuid($objConfigs->syncTwitchScheduleWithDiscordEventsFallbackPicture)) {
                $arrConfigs[$username]['default_picture'] = $objFile->path;
            }

            if ($objConfigs->syncTwitchScheduleWithDiscordMessagesThumbnail && $objFile = FilesModel::findByUuid($objConfigs->syncTwitchScheduleWithDiscordMessagesThumbnail)) {
                $arrConfigs[$username]['avatar'] = $objFile->path;
            }
        }

        return $arrConfigs;
    }

    public function getYtChannels()
    {
        return [];
    }

    public function catchApiRequest($objPage, $objLayout, $objPageRegular)
    {
        global $objPage;

        $encryptionService = System::getContainer()->get('plenta.encryption');
        $this->strTwitchClientId = $encryptionService->decrypt(Config::get('pfsTwitchClientId'));
        $this->strTwitchClientSecret = $encryptionService->decrypt(Config::get('pfsTwitchClientSecret'));
        $this->strDiscordToken = $encryptionService->decrypt(Config::get('pfsDiscordToken'));

        if ($objPage->alias != "api") {
            return;
        }

        if (!Input::get('auto_item')) {
            return;
        }

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
                // Retrieve all Discord events
                // For each Twitch channel, retrieve the 3 next streams planned
                // Check if the Twitch event is in the database and in the Discord events
                // --
                // Sync Database according to Twitch :
                // - Add new events
                // - Update existing events
                // - Delete existing events who are not in the database anymore
                // --
                // Add task to create Discord Event if it exists in Database but not in Discord
                // Add task to update Discord Event if it exists in Database and in Discord and it should be updated
                // Add task to delete Discord Event if it exists in Discord but not in Database
                // --
                // Format the Discord message, according to the next streams
                // Add Task to create/update Discord message

                foreach ($arrTwitchChannels as $t) {
                    // First, get the next events from the Twitch schedule
                    $r = $this->makeTwitchRequest('helix/schedule', ['broadcaster_id' => $t['broadcaster_id'], 'start_time' => date('Y-m-d\TH:i:sP'), 'first' => 3, 'utc_offset' => 60]);

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
                    $arrEventsForMsg = [];
                    foreach ($r['data']['segments'] as $event) {
                        if (null !== $event['canceled_until']) {
                            continue;
                        }

                        // Retrieve the event
                        $objEvent = DiscordEvent::findOneBy(['twitch_event="'.$event['id'].'"'], null);
                        $data = $event;
                        $data['title'] = addslashes($event['title']);
                        $data['url'] = $t['url'];
                        $data['user'] = $t['broadcaster_id'];

                        if ($t['default_picture']) {
                            $data['image'] = $t['default_picture'];
                        }

                        // Create task if it does not exists in Database
                        foreach ($t['events_servers'] as $s) {
                            if (!$objEvent) {
                                $this->addTask(
                                    "discord",
                                    "add_event",
                                    sprintf('guilds/%s/scheduled-events', $s),
                                    $data,
                                    'POST'
                                );

                                $hasChanges = true;
                            }
                            // Create task if it does exists in Database but it should be updated
                            elseif ($this->shouldEventBeUpdatedOnDiscord($data, $objEvent)) {
                                $this->addTask(
                                    "discord",
                                    "update_event",
                                    sprintf('guilds/%s/scheduled-events/%s', $s, $objEvent->discord_event),
                                    $data,
                                    'PATCH'
                                );

                                $hasChanges = true;
                            }
                        }

                        // Store Twitch events IDs for later
                        $arrTwitchEventsIds[] = $event['id'];

                        $objStartAt = \DateTime::createFromFormat(DATE_ATOM, $event['start_time'], new \DateTimeZone('UTC'));
                        $objStartAt->setTimezone(new \DateTimeZone('Europe/Paris'));
                        // $objEndAt = \DateTime::createFromFormat(DATE_ATOM, $event['end_time']);
                        $arrEventsForMsg[] = "Le " . $objStartAt->format('d/m/Y à H:i') . " - " . $event['title'];
                    }

                    // Prepare message
                    $arrEmbeds = [];
                    $arrEmbeds[] = [
                        'type' => 'rich',
                        'title' => $t['intro'],
                        'description' => !empty($arrEventsForMsg) ? implode("\n", $arrEventsForMsg) : 'Pas de streams prévus',
                        'url' => $t['url'],
                        'author' => [
                            'name' => $t['label'],
                            "url" => $t['url'],
                        ],
                        'timestamp' => date("c"),
                        'color' => hexdec($t['color'] ?: "FFFFFF"),
                        'thumbnail' => [
                            'url' => \Environment::get('base') . $t['avatar']
                        ]
                    ];

                    // Find events to delete
                    $strSql = 'user = "'.$t['broadcaster_id'].'"';
                    if (!empty($arrTwitchEventsIds)) {
                        $strSql .= ' AND twitch_event NOT IN("' . implode('","', $arrTwitchEventsIds) . '")';
                    }

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

                    // Check if there is Discord events to delete because there was an issue before
                    foreach ($t['events_servers'] as $s) {
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

                        if ($objDiscordEvents) {
                            foreach ($objDiscordEvents as $e) {
                                $objDiscordEvent = DiscordEvent::findOneBy(['discord_event = '.$e['id']], null);
                                if (!$objDiscordEvent) {
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


                    // If we detect changes between Twitch schedule & Database, add a task to also update the Discord messages
                    if (!empty($t['events_messages'])) {
                        foreach ($t['events_messages'] as $c) {
                            // Try to find a message id in database
                            $objMessage = DiscordMessage::findOneBy(['server='.$c['key'].' AND channel='.$c['value'].' AND user='.$t['broadcaster_id']], null);

                            if ($objMessage && $objMessage->events !== serialize($arrTwitchEventsIds)) {
                                $this->addTask(
                                    "discord",
                                    "update_message",
                                    sprintf('channels/%s/messages/%s', $c['value'], $objMessage->message),
                                    ['embeds' => $arrEmbeds, 'flags' => 2, 'user' => $t['broadcaster_id'], 'server' => $c['key'], 'events' => $arrTwitchEventsIds],
                                    'PATCH'
                                );
                            } elseif (!$objMessage || !$objMessage->message) {
                                $this->addTask(
                                    "discord",
                                    "add_message",
                                    sprintf('channels/%s/messages', $c['value']),
                                    ['embeds' => $arrEmbeds, 'flags' => 2, 'user' => $t['broadcaster_id'], 'server' => $c['key'], 'events' => $arrTwitchEventsIds],
                                    'POST'
                                );
                            }
                        }
                    }
                }
            break;
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
}
