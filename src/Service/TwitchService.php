<?php

declare(strict_types=1);

namespace WEM\PressFsyncBotBundle\Service;

use Contao\CoreBundle\Framework\ContaoFramework;
use DateTime;
use DateTimeZone;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
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

        // Cover, as a data URI. Left out when there is none, so an existing cover is not removed on update
        if (!empty($event['image'])) {
            $data['image'] = $event['image'];
        }

        // Return data
        return $data;
    }

    /**
     * Get the cover of a Discord event as a data URI (what Discord expects):
     * the key art of the Twitch category, or the fallback picture of the user config.
     *
     * @param  integer     $categoryId   Twitch category id
     * @param  string|null $strFallback  Path of the fallback picture, relative to the project dir
     *
     * @return string|null Null if there is no picture, the cover is then left untouched on Discord
     */
    public function getEventPicture(int $categoryId, ?string $strFallback = null): ?string
    {
        $strUrl = $this->getCategoryPicture($categoryId);

        if ($strUrl) {
            $strData = $this->download($strUrl);

            if (null !== $strData) {
                return 'data:image/jpeg;base64,' . base64_encode($strData);
            }
        }

        if ($strFallback) {
            $strPath = $this->projectDir . '/' . ltrim($strFallback, '/');
            $arrInfo = is_file($strPath) ? @getimagesize($strPath) : false;

            if (false !== $arrInfo && in_array($arrInfo['mime'], ['image/jpeg', 'image/png', 'image/gif'], true)) {
                return sprintf('data:%s;base64,%s', $arrInfo['mime'], base64_encode((string) file_get_contents($strPath)));
            }
        }

        return null;
    }

    protected function download(string $strUrl): ?string
    {
        $ch = curl_init($strUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $strData = curl_exec($ch);
        $intStatus = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return (200 === $intStatus && is_string($strData) && '' !== $strData) ? $strData : null;
    }

    /**
     * Get a landscape picture (key art) of a Twitch category.
     * Twitch only gives a portrait box art, so we go through IGDB (owned by Twitch, same credentials):
     * helix/games gives the IGDB id of the category, then IGDB gives its artworks, or its screenshots as a fallback.
     *
     * @param  integer $categoryId Twitch category id
     * @param  string  $strSize    IGDB image size (t_1080p: 1920x1080, t_720p: 1280x720, t_screenshot_big: 889x500)
     *
     * @return string|null URL of the picture, null if no landscape picture was found or the APIs could not answer
     */
    public function getCategoryPicture(int $categoryId, string $strSize = 't_1080p'): ?string
    {
        if ($categoryId <= 0) {
            return null;
        }

        if (!array_key_exists($categoryId, $this->arrCache['categories'])) {
            $r = $this->client->request('helix/games', ['id' => $categoryId]);

            // Do not cache failures, a later call can retry
            if (null === $r || isset($r['error'])) {
                return null;
            }

            $intIgdb = (int) ($r['data'][0]['igdb_id'] ?? 0);
            $strImageId = null;

            if ($intIgdb > 0) {
                foreach (['artworks', 'screenshots'] as $strEndpoint) {
                    $arrImages = $this->client->request(
                        TwitchClient::IGDB_URL . $strEndpoint,
                        sprintf('fields image_id,width,height; where game = %d; limit 50;', $intIgdb),
                        'POST'
                    );

                    // Do not cache failures, a later call can retry
                    if (null === $arrImages || isset($arrImages['status']) || isset($arrImages[0]['status'])) {
                        return null;
                    }

                    $strImageId = $this->pickLandscapeImage($arrImages);

                    if (null !== $strImageId) {
                        break;
                    }
                }
            }

            $this->arrCache['categories'][$categoryId] = $strImageId;
        }

        $strImageId = $this->arrCache['categories'][$categoryId];

        if (!$strImageId) {
            return null;
        }

        return sprintf('https://images.igdb.com/igdb/image/upload/%s/%s.jpg', $strSize, $strImageId);
    }

    /**
     * Pick the image with the widest landscape ratio, up to the 16:9 expected for key arts
     *
     * @param  array $arrImages IGDB images (image_id, width, height)
     *
     * @return string|null
     */
    protected function pickLandscapeImage(array $arrImages): ?string
    {
        $strBest = null;
        $fltBest = 0.0;

        foreach ($arrImages as $arrImage) {
            if (empty($arrImage['image_id']) || empty($arrImage['width']) || empty($arrImage['height'])) {
                continue;
            }

            $fltRatio = $arrImage['width'] / $arrImage['height'];

            // Portrait or square pictures are not what we are looking for
            if ($fltRatio < 1.3) {
                continue;
            }

            // Closest to 16:9, bigger pictures first
            $fltScore = -abs($fltRatio - 16 / 9) + $arrImage['width'] / 1000000;

            if (null === $strBest || $fltScore > $fltBest) {
                $strBest = $arrImage['image_id'];
                $fltBest = $fltScore;
            }
        }

        return $strBest;
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