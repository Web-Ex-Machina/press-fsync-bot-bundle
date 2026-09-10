<?php

declare(strict_types=1);

namespace WEM\PressFsyncBotBundle\Service;

use WEM\PressFsyncBotBundle\Model\Task;
use WEM\PressFsyncBotBundle\Model\TwitchEvent;

class DiscordService
{
    protected array $cache = ['server_events' => []];

    public function __construct(
        private readonly ConfigService $config,
        private readonly TaskService $tasker,
    ) {
    }

    // Sync Database according to Twitch :
    // Add task to create Discord Event if it exists in Database but not in Discord
    // Add task to update Discord Event if it exists in Database and in Discord and it should be updated
    // Add task to delete Discord Event if it exists in Discord but not in Database
    protected function syncEvents(): void
    {
        // Retrieve the events
        $objEvents = TwitchEvent::findItems(['start_time_before' => strtotime("+1 week"), 'notcanceled' => true]);

        // Nothing to do, skip
        if (!$objEvents || 0 === $objEvents) {
            return;
        }

        // Store the events we add
        $arrServers = [];

        // Check if we must add/update items inside Discord
        while ($objEvents->next()) {
            // Retrieve event config
            $arrConfig = $this->config->parse($objEvents->getRelated('user'));

            // Retrieve the Discord event
            $objDiscordEvent = DiscordEvent::findOneBy(['twitch_event="'.$objEvents->id.'"'], null);
            $data['event'] = $objEvents->id;
            $data['config'] = $arrConfig;

            // Create task if it does not exists in Database
            foreach ($arrConfig['events_servers'] as $s) {
                if (!$objDiscordEvent) {
                    $this->tasker->create(
                        "discord",
                        "add_event",
                        sprintf('guilds/%s/scheduled-events', $s),
                        $data,
                        'POST'
                    );
                }
                // Create task if it does exists in Database but it should be updated
                elseif ($this->shouldEventBeUpdated($data, $objDiscordEvent)) {
                    $this->tasker->create(
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
                if (is_array($this->cache['events_servers']) && !array_key_exists($s, $this->cache['events_servers'])) {
                    $objDiscordEvents = $this->request(
                        sprintf('guilds/%s/scheduled-events', $s),
                        [],
                        'GET'
                    );

                    $this->cache['events_servers'][$s] = $objDiscordEvents;
                } else {
                    $objDiscordEvents = $this->cache['events_servers'][$s];
                }

                // 1 & 2
                if ($objDiscordEvents) {
                    foreach ($objDiscordEvents as $e) {
                        // 1
                        if (0 === DiscordEvent::countItems(['discord_event' => $e['id']])) {
                            $this->tasker->create(
                                "discord",
                                "delete_event",
                                sprintf('guilds/%s/scheduled-events/%s', $s, $e['id']),
                                [],
                                'DELETE'
                            );

                            continue;
                        }

                        // 2
                        $objEvent = DiscordEvent::findItems(['discord_event' => $e['id']], 1);
                        $objTwitchEvent = $objEvent->getRelated('twitch_event');

                        if (!$objTwitchEvent || null !== $objTwitchEvent->canceled_until) {
                            $this->tasker->create(
                                "discord",
                                "delete_event",
                                sprintf('guilds/%s/scheduled-events/%s', $s, $e['id']),
                                [],
                                'DELETE'
                            );
                        }
                    }
                }

                // 3
                $strSql = 'twitch_event NOT IN(' . implode(',', $events) . ')';
                $objDatabaseEvents = DiscordEvent::findBy([$strSql], null);
                if ($objDatabaseEvents && 0 < $objDatabaseEvents->count()) {
                    while ($objDatabaseEvents->next()) {
                        if (!$objDatabaseEvents->discord_event) {
                            continue;
                        }

                        $this->tasker->create(
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

    // Format the Discord message, according to the next streams
    // Add Task to create/update Discord message
    protected function syncMessages()
    {
        // Retrieve the events
        $objEvents = TwitchEvent::findItems(['start_time_after' => time(), 'notcanceled' => true]);

        // Nothing to do, skip
        if (!$objEvents || 0 === $objEvents->count()) {
            return;
        }

        $encryptionService = System::getContainer()->get('plenta.encryption');
        $arrConfigs = $this->getTwitchChannels();
        $arrEventsForMsg = [];
        $arrEventsIds = [];


        // Store config & parse events
        while ($objEvents->next()) {
            $username = $encryptionService->decrypt($objEvents->getRelated('user')->twitchUsername);

            // Store the config for later
            $arrEventsForMsg[$username][] = date("d/m (H\hi)", $objEvents->start_time) . " - " . $objEvents->title;
            $arrEventsIds[$username][] = $objEvents->id;
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
                //'timestamp' => date("c"),
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
                        $this->tasker->create(
                            "discord",
                            "update_message",
                            sprintf('channels/%s/messages/%s', $arrChannel['value'], $objMessage->message),
                            ['embeds' => $arrEmbeds, 'flags' => 2, 'user' => $arrConfig['broadcaster_id'], 'server' => $arrChannel['key'], 'events' => $arrEventsIds[$user]],
                            'PATCH'
                        );
                    } elseif (!$objMessage || !$objMessage->message) {
                        $this->tasker->create(
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
    }

    protected function shouldEventBeUpdated($event, $objDiscordEvent)
    {
        $objTwitchEvent = TwitchEvent::findByPk($event['event']);

        // If the title has changed, go
        $title = $objTwitchEvent->title ?: $objTwitchEvent->category_name;
        if ($objTwitchEvent->title !== $objDiscordEvent->discord_event_name) {
            return true;
        }

        if (date('c', $objTwitchEvent->start_time) !== $objDiscordEvent->discord_event_scheduled_start_time) {
            return true;
        }

        if (date('c', $objTwitchEvent->end_time) !== $objDiscordEvent->discord_event_scheduled_end_time) {
            return true;
        }

        // retrieve config and format url
        $arrConfig = $this->config->parse($objTwitchEvent->getRelated('user'));
        if ($arrConfig['url'] !== $objDiscordEvent->discord_event_entity_metadata_location) {
            return true;
        }

        if ($objTwitchEvent->category_name !== $objDiscordEvent->discord_event_category_name) {
            return true;
        }

        // Else, let it that way
        return false;
    }

    protected function request($endpoint, $data = [], $method = 'GET')
    {
        # Set endpoint
        $url = "https://discord.com/api/" . $endpoint;

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
        if (is_array($response) && array_key_exists('retry_after', $response)) {
            sleep(round($response['retry_after']));

            $response = $this->request($endpoint, $data, $method);
        }

        return $response;
    }
}