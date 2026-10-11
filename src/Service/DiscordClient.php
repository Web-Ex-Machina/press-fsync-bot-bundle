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
    protected const MAX_RETRIES = 3;

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
     * Returns an empty array for successful responses without a body (204 on delete for example).
     */
    public function request(string $endpoint, array $data = [], string $method = 'GET', int $intRetries = 0): ?array
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
        $intHeaderSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $intStatus = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        // Network error or timeout
        if (!is_string($request)) {
            return null;
        }

        // Split headers and body, based on the size given by cURL (the body can be empty or on multiple lines)
        $headers = [];

        foreach (explode("\n", substr($request, 0, $intHeaderSize)) as $part) {
            $middle = explode(":", $part, 2);

            if (!isset($middle[1]) || !trim($middle[0])) {
                continue;
            }

            $headers[strtolower(trim($middle[0]))] = trim($middle[1]);
        }

        $body = trim(substr($request, $intHeaderSize));

        // Remember if we must wait before the next request (bucket exhausted)
        if (isset($headers['x-ratelimit-remaining'], $headers['x-ratelimit-reset-after'])
            && (int) $headers['x-ratelimit-remaining'] <= 0
        ) {
            $this->rateLimitWait = min((float) $headers['x-ratelimit-reset-after'], self::MAX_RATE_LIMIT_WAIT);
        }

        // Successful response without a body
        if ('' === $body) {
            return $intStatus >= 200 && $intStatus < 300 ? [] : null;
        }

        $response = json_decode($body, true);

        if (!is_array($response)) {
            return null;
        }

        // If we hit the API limit, sleep for a while and relaunch the request
        if (array_key_exists('retry_after', $response) && $intRetries < self::MAX_RETRIES) {
            sleep((int) ceil((float) $response['retry_after']));
            $this->rateLimitWait = 0.0;

            return $this->request($endpoint, $data, $method, $intRetries + 1);
        }

        return $response;
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
