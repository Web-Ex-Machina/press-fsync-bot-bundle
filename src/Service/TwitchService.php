<?php

declare(strict_types=1);

namespace WEM\PressFsyncBotBundle\Service;

use Contao\CoreBundle\Framework\ContaoFramework;
use DateTime;
use DateTimeZone;
use WEM\PressFsyncBotBundle\Model\Task;
use WEM\PressFsyncBotBundle\Model\TwitchEvent;
use WEM\PressFsyncBotBundle\Model\UserConfig;

class TwitchService
{
    protected const PARALLEL_REQUESTS = 10;

    protected array $arrCache = ['categories' => []];
    protected array $arrResults = [];

    public function __construct(
        private readonly ContaoFramework $framework, 
        private readonly ConfigService $config,
        private readonly TwitchClient $client,
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

    public function getChannels(): array
    {
        $objConfigs = UserConfig::findByTwitchSyncSchedulePlanned();

        if (!$objConfigs || 0 === $objConfigs->count()) {
            return [];
        }

        $arrConfigs = [];
        while ($objConfigs->next()) {
            $arrConfigs[] = $this->config->parse($objConfigs->current());
        }

        return $arrConfigs;
    }

    public function syncEvents(): void
    {
        $arrChannels = $this->getChannels();
        $arrEvents = [];
        // Users for which we could not get a reliable answer from Twitch, their events must not be removed
        $arrFailedUsers = [];
        $this->arrResults = [
            'created' => 0,
            'updated' => 0,
            'deleted' => 0,
        ];

        // Load the stored events once, instead of one query per Twitch event
        $arrStoredEvents = [];
        $arrStoredByTwitch = [];
        $objStoredEvents = TwitchEvent::findAll();

        if ($objStoredEvents) {
            foreach ($objStoredEvents->getModels() as $objStoredEvent) {
                $arrStoredEvents[] = $objStoredEvent;

                if ($objStoredEvent->twitch_event && !isset($arrStoredByTwitch[$objStoredEvent->twitch_event])) {
                    $arrStoredByTwitch[$objStoredEvent->twitch_event] = $objStoredEvent;
                }
            }
        }

        $strStartTime = date('Y-m-d\TH:i:sP');

        // Get the next events from the Twitch schedules, requests are made in parallel
        foreach (array_chunk($arrChannels, self::PARALLEL_REQUESTS, true) as $arrChunk) {
            $arrRequests = [];

            foreach ($arrChunk as $i => $t) {
                $arrRequests[$i] = [
                    'endpoint' => 'helix/schedule',
                    'data' => [
                        'broadcaster_id' => $t['broadcaster_id'],
                        'start_time' => $strStartTime,
                        'utc_offset' => 60
                    ],
                ];
            }

            $arrResponses = $this->client->requestMultiple($arrRequests);

            foreach ($arrChunk as $i => $t) {
                $r = $arrResponses[$i] ?? null;

                // Network error, or error from the API (a 404 only means the channel has no schedule)
                if (null === $r || (isset($r['error']) && 404 !== (int) ($r['status'] ?? 0))) {
                    $arrFailedUsers[$t['id']] = true;

                    continue;
                }

                if (!array_key_exists('data', $r) || null === $r['data'] || null === $r['data']['segments']) {
                    continue;
                }

                // Loop on the events
                foreach ($r['data']['segments'] as $event) {
                    // Skip if event is in one month or after
                    if ($this->getTimestampFromDate($event['start_time']) >= strtotime("+1 month")) {
                        continue;
                    }

                    // Sync the event in the database
                    $objEvent = $this->syncEvent($event, $t, $arrStoredByTwitch[$event['id']] ?? null);
                    $arrStoredByTwitch[$event['id']] = $objEvent;
                    $arrEvents[$objEvent->id] = true;
                }
            }
        }

        // Finally, remove events that have been removed from Twitch
        foreach ($arrStoredEvents as $objStoredEvent) {
            if (isset($arrEvents[$objStoredEvent->id]) || isset($arrFailedUsers[$objStoredEvent->user])) {
                continue;
            }

            $objStoredEvent->delete();
            $this->arrResults['deleted']++;
        }
    }

    /**
     * Sync a Twitch event in the Database, only writes when something changed
     * @param  array             $event       Twitch event from API
     * @param  array             $t           User config from database
     * @param  TwitchEvent|null  $objExisting Event already in database, if any
     * @return TwitchEvent
     */
    protected function syncEvent(array $event, array $t, ?TwitchEvent $objExisting = null): TwitchEvent
    {
        $arrValues = [
            'twitch_event' => $event['id'],
            'user' => $t['id'],
            'start_time' => $this->getTimestampFromDate($event['start_time']),
            'end_time' => $this->getTimestampFromDate($event['end_time']),
            'title' => addslashes($event['title']),
            'is_recurring' => $event['is_recurring'] ? 1 : '',
            'canceled_until' => $this->getTimestampFromDate($event['canceled_until']),
        ];

        if ($event['category']) {
            $arrValues['category_id'] = $event['category']['id'] ?: 0;
            $arrValues['category_name'] = $event['category']['name'] ?: '';
        }

        if (!$objExisting) {
            $objEvent = new TwitchEvent();

            foreach ($arrValues as $k => $v) {
                $objEvent->{$k} = $v;
            }

            $objEvent->tstamp = time();
            $objEvent->save();

            $this->arrResults['created']++;

            return $objEvent;
        }

        // Values from the database are strings, compare them as such
        $blnChanged = false;

        foreach ($arrValues as $k => $v) {
            if ((string) $objExisting->{$k} !== (string) $v) {
                $objExisting->{$k} = $v;
                $blnChanged = true;
            }
        }

        if ($blnChanged) {
            $objExisting->tstamp = time();
            $objExisting->save();

            $this->arrResults['updated']++;
        }

        return $objExisting;
    }

    /**
     * Parse a Twitch event in an useful array
     *
     * @param  array   $event    Data from Twitch
     * @param  integer $width    Picture width wanted
     * @param  integer $height   Picture height wanted
     *
     * @return array
     */
    public function parseEvent(array $event, int $width = 800, int $height = 320): array
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

        // Return data
        return $data;
    }

    /**
     * Convert a DATE_ATOM format into a timestamp at the right timezone
     * @param  string $date
     * @return int
     */
    protected function getTimestampFromDate(string|null $date): ?int
    {
        if (!$date) {
            return null;
        }

        $objDate = DateTime::createFromFormat(DATE_ATOM, $date, new DateTimeZone('UTC'));
        $objDate->setTimezone(new DateTimeZone('Europe/Paris'));
        return $objDate->getTimestamp();
    }
}