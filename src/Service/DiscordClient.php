<?php

declare(strict_types=1);

namespace WEM\PressFsyncBotBundle\Service;

use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use WEM\UtilsBundle\Classes\Encryption;

class DiscordClient
{
    protected const CONNECT_TIMEOUT = 5;
    protected const TIMEOUT = 15;
    protected const MAX_RATE_LIMIT_WAIT = 10.0;

    protected ?string $token = null;
    protected float $rateLimitWait = 0.0;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Encryption $encryption,
    ) {
    }

    /**
     * Returns the number of seconds to wait before the next request to respect Discord's rate limit
     * (0 if the last response said we still have requests left), then resets it.
     */
    public function consumeRateLimitWait(): float
    {
        $wait = $this->rateLimitWait;
        $this->rateLimitWait = 0.0;

        return $wait;
    }

    /**
     * Returns null if the request failed (network error, timeout, invalid response).
     */
    public function request(string $endpoint, array $data = [], string $method = 'GET'): ?array
    {
        # Set endpoint
        $url = "https://discord.com/api/" . $endpoint;

        # Initialize new curl request
        $ch = curl_init();

        # Set headers, data etc..
        curl_setopt_array($ch, array(
            CURLOPT_URL            => $url,
            CURLOPT_HEADER         => 1,
            CURLOPT_HTTPHEADER     => array(
                'Authorization: Bot ' . $this->getToken(),
                "Content-Type: application/json",
                "Accept: application/json"
            ),
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
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

        // Network error or timeout
        if (false === $request) {
            return null;
        }

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
                $middle[1] = "";
            }

            $headers[strtolower(trim($middle[0]))] = trim($middle[1]);
        }

        // Remember if we must wait before the next request (bucket exhausted)
        if (isset($headers['x-ratelimit-remaining'], $headers['x-ratelimit-reset-after'])
            && (int) $headers['x-ratelimit-remaining'] <= 0
        ) {
            $this->rateLimitWait = min((float) $headers['x-ratelimit-reset-after'], self::MAX_RATE_LIMIT_WAIT);
        }

        $response = json_decode($body, true);

        // If we hit the API limit, sleep for a while and relaunch the request
        if (is_array($response) && array_key_exists('retry_after', $response)) {
            sleep((int) ceil($response['retry_after']));
            $this->rateLimitWait = 0.0;

            return $this->request($endpoint, $data, $method);
        }

        return is_array($response) ? $response : null;
    }

    protected function getToken(): string
    {
        if (null === $this->token) {
            $this->framework->initialize();

            $this->token = $this->encryption->decrypt_b64($this->framework->getAdapter(Config::class)->get('pfsDiscordToken'));
        }

        return $this->token;
    }
}
