<?php

declare(strict_types=1);

namespace WEM\PressFsyncBotBundle\Service;

use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Psr\Cache\CacheItemPoolInterface;
use WEM\UtilsBundle\Classes\Encryption;

class TwitchClient
{
    protected const CONNECT_TIMEOUT = 5;
    protected const TIMEOUT = 15;
    protected const TOKEN_CACHE_KEY = 'pfs_twitch_app_token';
    // Seconds before the real expiration where we consider the token as expired
    protected const TOKEN_EXPIRATION_MARGIN = 300;

    protected ?string $clientId = null;
    protected ?string $clientSecret = null;
    protected ?array $token = null;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Encryption $encryption,
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * Returns null if the request failed (network error, timeout, invalid response).
     */
    public function request(string $endpoint, array $data = [], string $method = 'GET'): ?array
    {
        for ($attempt = 0; $attempt < 2; ++$attempt) {
            $token = $this->getToken();

            if (null === $token) {
                return null;
            }

            $ch = $this->buildHandle($endpoint, $data, $method, $token);
            $request = curl_exec($ch);
            $intStatus = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);

            // The token has been revoked or has expired, get a new one and retry once
            if (401 === $intStatus && 0 === $attempt) {
                $this->invalidateToken();

                continue;
            }

            return $this->decode($request);
        }

        return null;
    }

    /**
     * Run several requests in parallel.
     *
     * @param array<int|string, array{endpoint: string, data?: array, method?: string}> $arrRequests
     *
     * @return array<int|string, array|null> Responses, with the same keys as the requests (null if a request failed)
     */
    public function requestMultiple(array $arrRequests): array
    {
        $arrResults = [];

        if (empty($arrRequests)) {
            return $arrResults;
        }

        $token = $this->getToken();

        if (null === $token) {
            return array_fill_keys(array_keys($arrRequests), null);
        }

        $mh = curl_multi_init();
        $arrHandles = [];

        foreach ($arrRequests as $key => $arrRequest) {
            $ch = $this->buildHandle(
                $arrRequest['endpoint'],
                $arrRequest['data'] ?? [],
                $arrRequest['method'] ?? 'GET',
                $token
            );

            curl_multi_add_handle($mh, $ch);
            $arrHandles[$key] = $ch;
        }

        do {
            $intStatus = curl_multi_exec($mh, $blnActive);

            if ($blnActive) {
                curl_multi_select($mh, 1.0);
            }
        } while ($blnActive && CURLM_OK === $intStatus);

        $arrRetry = [];

        foreach ($arrHandles as $key => $ch) {
            $intStatus = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

            if (401 === $intStatus) {
                $arrRetry[] = $key;
            } elseif (0 !== curl_errno($ch)) {
                $arrResults[$key] = null;
            } else {
                $arrResults[$key] = $this->decode(curl_multi_getcontent($ch));
            }

            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }

        curl_multi_close($mh);

        // Token revoked or expired: request a new one, then replay the rejected requests
        if (!empty($arrRetry)) {
            $this->invalidateToken();

            foreach ($arrRetry as $key) {
                $arrRequest = $arrRequests[$key];
                $arrResults[$key] = $this->request(
                    $arrRequest['endpoint'],
                    $arrRequest['data'] ?? [],
                    $arrRequest['method'] ?? 'GET'
                );
            }
        }

        // Keep the order of the requests
        return array_replace(array_fill_keys(array_keys($arrRequests), null), $arrResults);
    }

    protected function buildHandle(string $endpoint, array $data, string $method, array $token): \CurlHandle
    {
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
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::CONNECT_TIMEOUT);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            sprintf('Authorization: %s %s', $token['token_type'], $token['access_token']),
            sprintf('Client-Id: %s', $this->getClientId())
        ]);

        return $ch;
    }

    protected function decode(string|bool|null $request): ?array
    {
        if (!is_string($request) || '' === $request) {
            return null;
        }

        $response = json_decode($request, true);

        return is_array($response) ? $response : null;
    }

    protected function getClientId(): string
    {
        if (null === $this->clientId) {
            $this->framework->initialize();

            $this->clientId = $this->encryption->decrypt_b64($this->framework->getAdapter(Config::class)->get('pfsTwitchClientId'));
        }

        return $this->clientId;
    }

    protected function getClientSecret(): string
    {
        if (null === $this->clientSecret) {
            $this->framework->initialize();

            $this->clientSecret = $this->encryption->decrypt_b64($this->framework->getAdapter(Config::class)->get('pfsTwitchClientSecret'));
        }

        return $this->clientSecret;
    }

    protected function invalidateToken(): void
    {
        $this->token = null;
        $this->cache->deleteItem(self::TOKEN_CACHE_KEY);
    }

    /**
     * App access tokens are valid for a long time, so we keep them between runs.
     */
    protected function getToken(): ?array
    {
        if (null !== $this->token) {
            return $this->token;
        }

        $objItem = $this->cache->getItem(self::TOKEN_CACHE_KEY);

        if ($objItem->isHit()) {
            $this->token = $objItem->get();

            return $this->token;
        }

        $data = [
            'client_id' => $this->getClientId(),
            'client_secret' => $this->getClientSecret(),
            'grant_type' => 'client_credentials'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://id.twitch.tv/oauth2/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::CONNECT_TIMEOUT);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/x-www-form-urlencoded",
        ]);

        $request = curl_exec($ch);
        curl_close($ch);

        $arrResult = $this->decode($request);

        if (null === $arrResult || !isset($arrResult['access_token'], $arrResult['token_type'])) {
            return null;
        }

        $this->token = [
            'access_token' => $arrResult['access_token'],
            'token_type' => ucfirst($arrResult['token_type']),
        ];

        $intExpiresIn = (int) ($arrResult['expires_in'] ?? 0);

        // Without a known lifetime, do not keep it between runs
        if ($intExpiresIn > self::TOKEN_EXPIRATION_MARGIN) {
            $objItem->set($this->token);
            $objItem->expiresAfter($intExpiresIn - self::TOKEN_EXPIRATION_MARGIN);
            $this->cache->save($objItem);
        }

        return $this->token;
    }
}
