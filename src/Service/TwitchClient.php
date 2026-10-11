<?php

declare(strict_types=1);

namespace WEM\PressFsyncBotBundle\Service;

use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use WEM\UtilsBundle\Classes\Encryption;

class TwitchClient
{
    protected ?string $clientId = null;
    protected ?string $clientSecret = null;
    protected string $token = "";
    protected string $tokenType = "";

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Encryption $encryption,
    ) {
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
            sprintf('Client-Id: %s', $this->getClientId())
        ]);

        $request = curl_exec($ch);
        curl_close($ch);

        return json_decode($request, true);
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

    protected function getToken(): array
    {
        if (!$this->token || !$this->tokenType) {
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
}
