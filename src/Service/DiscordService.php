<?php

declare(strict_types=1);

namespace WEM\PressFsyncBotBundle\Service;

use Contao\CoreBundle\Framework\ContaoFramework;
use WEM\PressFsyncBotBundle\Model\DiscordEvent;
use WEM\PressFsyncBotBundle\Model\DiscordMessage;
use WEM\PressFsyncBotBundle\Model\Task;
use WEM\PressFsyncBotBundle\Model\TwitchEvent;
use Symfony\Component\Routing\RequestContext;
use WEM\UtilsBundle\Classes\Encryption;

class DiscordService
{
    protected array $cache = [];
    protected array $arrResults = [];

    public function __construct(
        private readonly ConfigService $config,
        private readonly ContaoFramework $framework, 
        private readonly Encryption $encryption,
        private readonly DiscordClient $client,
        private readonly TaskService $tasker,
        private readonly TwitchService $twitch,
        private readonly RequestContext $requestContext,
    ) {
        $this->framework->initialize();
    }

    public function __set(mixed $name, mixed $value): void
    {
        $this->{$name} = $value;
    }

    public function __get(mixed $name): mixed
    {
        return $this->{$name};
    }

    public function getResults(): array
    {
        return $this->arrResults;
    }

    // Sync Database according to Twitch :
    // Add task to create Discord Event if it exists in Database but not in Discord
    // Add task to update Discord Event if it exists in Database and in Discord and it should be updated
    // Add task to delete Discord Event if it exists in Discord but not in Database
    public function syncEvents(): void
    {
        $this->arrResults = [
            'created' => 0,
            'updated' => 0,
            'deleted' => 0,
        ];
        $this->cache = [
            'events_servers' => [],
        ];

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

                    $this->arrResults['created']++;
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

                    $this->arrResults['updated']++;
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
                if (!array_key_exists($s, $this->cache['events_servers'])) {
                    $objDiscordEvents = $this->client->request(
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

                            $this->arrResults['deleted']++;

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
                            
                            $this->arrResults['deleted']++;
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

                        $this->arrResults['deleted']++;
                    }
                }
            }
        }
    }

    // Format the Discord message, according to the next streams
    // Add Task to create/update Discord message
    public function syncMessages(): void
    {
        $this->arrResults = [
            'created' => 0,
            'updated' => 0,
            'deleted' => 0,
        ];

        // Retrieve the events
        $objEvents = TwitchEvent::findItems(['start_time_after' => time(), 'notcanceled' => true]);

        // Nothing to do, skip
        if (!$objEvents || 0 === $objEvents->count()) {
            return;
        }

        $arrConfigs = $this->twitch->getChannels();
        $arrEventsForMsg = [];
        $arrEventsIds = [];

        // Store config & parse events
        while ($objEvents->next()) {
            $username = $this->encryption->decrypt_b64($objEvents->getRelated('user')->twitchUsername);

            // Store the config for later
            $arrEventsForMsg[$username][] = date("d/m (H\hi)", (int) $objEvents->start_time) . " - " . $objEvents->title;
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
                    'url' => $this->getBaseUrl() . $arrConfig['avatar']
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

    /**
     * Absolute URL of the website root, with a trailing slash. Works without a current request (cron, command).
     */
    protected function getBaseUrl(): string
    {
        $scheme = $this->requestContext->getScheme();
        $port = 'https' === $scheme ? $this->requestContext->getHttpsPort() : $this->requestContext->getHttpPort();
        $defaultPort = 'https' === $scheme ? 443 : 80;

        return $scheme.'://'.$this->requestContext->getHost().($port !== $defaultPort ? ':'.$port : '').'/';
    }

    protected function shouldEventBeUpdated(array $event, DiscordEvent $objDiscordEvent): bool
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
}
