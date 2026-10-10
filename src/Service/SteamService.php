<?php

declare(strict_types=1);

namespace WEM\PressFsyncBotBundle\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class SteamService
{
    /**
     * SteamID64 of Ruvon.
     */
    public const RUVON_STEAM_ID = '76561198084359321';

    private const WISHLIST_COUNT_URL = 'https://api.steampowered.com/IWishlistService/GetWishlistItemCount/v1/';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * A SteamID64 is 17 digits.
     */
    public function isValidSteamId(string $steamId): bool
    {
        return 1 === preg_match('/^\d{17}$/', $steamId);
    }

    /**
     * Number of games in the wishlist, or null if the SteamID is invalid or Steam does not answer properly.
     */
    public function getWishlistCount(string $steamId): ?int
    {
        if (!$this->isValidSteamId($steamId)) {
            return null;
        }

        try {
            $data = $this->httpClient->request('GET', self::WISHLIST_COUNT_URL, [
                'query' => ['steamid' => $steamId],
                'timeout' => 5,
                'max_duration' => 10,
            ])->toArray();
        } catch (\Throwable) {
            return null;
        }

        $count = $data['response']['count'] ?? null;

        return is_int($count) ? $count : null;
    }
}
