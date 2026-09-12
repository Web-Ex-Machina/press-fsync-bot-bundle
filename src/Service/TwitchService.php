<?php

declare(strict_types=1);

namespace WEM\PressFsyncBotBundle\Service;

use Contao\Config;
use DateTime;
use DateTimeZone;
use WEM\PressFsyncBotBundle\Model\Task;
use WEM\PressFsyncBotBundle\Model\TwitchEvent;
use WEM\PressFsyncBotBundle\Model\UserConfig;
use WEM\UtilsBundle\Classes\Encryption;

class TwitchService
{
    protected string $clientId = "";
    protected string $clientSecret = "";
    protected string $token = "";
    protected string $tokenType = "";
    protected array $arrCache = ['categories' => []];
    protected array $arrResults = [];

    public function __construct(
        private readonly ConfigService $config,
        private readonly Encryption $encryption,
    ) {
        $this->clientId = $this->encryption->decrypt_b64(Config::get('pfsTwitchClientId'));
        $this->clientSecret = $this->encryption->decrypt_b64(Config::get('pfsTwitchClientSecret'));
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
        $this->arrResults = [
            'created' => 0,
            'updated' => 0,
            'deleted' => 0,
        ];

        foreach ($arrChannels as $t) {
            // First, get the next events from the Twitch schedule
            $r = $this->request(
                'helix/schedule',
                [
                    'broadcaster_id' => $t['broadcaster_id'],
                    'start_time' => date('Y-m-d\TH:i:sP'),
                    'utc_offset' => 60
                ]
            );

            if (null === $r || !array_key_exists('data', $r) || null === $r['data'] || null === $r['data']['segments']) {
                continue;
            }

            // Loop on the events
            foreach ($r['data']['segments'] as $event) {
                // Skip if event is in one month or after
                if ($this->getTimestampFromDate($event['start_time']) >= strtotime("+1 month")) {
                    continue;
                }

                if (null !== $event['canceled_until']) {
                    // continue;
                }

                // Sync the event in the database
                $objEvent = $this->syncEvent($event, $t);
                $arrEvents[] = $objEvent->id;
            }
        }

        // Finally, remove events that have been removed from Twitch
        if (!empty($arrEvents)) {
            $strSql = 'id NOT IN(' . implode(',', $arrEvents) . ')';
            $objDatabaseEvents = TwitchEvent::findBy([$strSql], null);
        } else {
            $objDatabaseEvents = TwitchEvent::findAll();
        }

        if ($objDatabaseEvents && 0 < $objDatabaseEvents->count()) {
            while ($objDatabaseEvents->next()) {
                $objDatabaseEvents->delete();
                $this->arrResults['deleted']++;
            }
        }
    }

    /**
     * Sync a Twitch event in the Database
     * @param  array $event Twitch event from API
     * @param  array $t     User config from database
     * @return TwitchEvent
     */
    protected function syncEvent(array $event, array $t): TwitchEvent
    {
        try {
            $objEvent = TwitchEvent::findItems(['twitch_event' => $event['id']], 1);

            if (!$objEvent) {
                $objEvent = new TwitchEvent();

                $this->arrResults['created']++;
            } else {
                $objEvent = $objEvent->current();

                $this->arrResults['updated']++;
            }

            $objEvent->tstamp = time();
            $objEvent->twitch_event = $event['id'];
            $objEvent->user = $t['id'];
            $objEvent->start_time = $this->getTimestampFromDate($event['start_time']);
            $objEvent->end_time = $this->getTimestampFromDate($event['end_time']);
            $objEvent->title = addslashes($event['title']);
            $objEvent->is_recurring = $event['is_recurring'] ? 1 : '';
            $objEvent->canceled_until = $this->getTimestampFromDate($event['canceled_until']);

            if ($event['category']) {
                $objEvent->category_id = $event['category']['id'] ?: 0;
                $objEvent->category_name = $event['category']['name'] ?: '';
            }

            $objEvent->save();

            return $objEvent;
        } catch (\Exception $e) {
            throw $e;
        }
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
    protected function parseEvent(array $event, int $width = 800, int $height = 320): array
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

    protected function getToken(): array
    {
        if (!$this->tokenType && !$this->tokenType) {
            $data = [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
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

            $this->tokenType = ucfirst($arrResult['token_type']);
            $this->token = $arrResult['access_token'];
        }

        return [
            'access_token' => $this->token,
            'token_type' => $this->tokenType,
        ];
    }

    public function request(string $endpoint, array $data = [], string $method = 'GET'): array
    {
        $token = $this->getToken();
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
            sprintf('Client-Id: %s', $this->clientId)
        ]);

        $request = curl_exec($ch);
        curl_close($ch);

        return json_decode($request, true);
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