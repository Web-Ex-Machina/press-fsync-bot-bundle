<?php

declare(strict_types=1);

namespace WEM\PressFsyncBotBundle\Service;

use Contao\FilesModel;
use WEM\PressFsyncBotBundle\Model\UserConfig;
use WEM\UtilsBundle\Classes\Encryption;
use WEM\UtilsBundle\Classes\StringUtil;

class ConfigService
{
    public function __construct(
        private readonly Encryption $encryption,
    ) {
    }

    public function parse(UserConfig $objConfig): array
    {
        $arrConfig = [
            'id' => $objConfig->id,
            'label' => $objConfig->username,
            'url' => 'https://www.twitch.tv/' . $this->encryption->decrypt_b64($objConfig->twitchUsername),
            'intro' => $objConfig->syncTwitchScheduleWithDiscordMessagesFormat,
            'color' => $objConfig->syncTwitchScheduleWithDiscordMessagesColor,
            'events_servers' => StringUtil::deserialize($objConfig->syncTwitchScheduleWithDiscordEventsServers),
            'events_messages' => StringUtil::deserialize($objConfig->syncTwitchScheduleWithDiscordMessagesRecipients),
            'broadcaster_id' => $this->encryption->decrypt_b64($objConfig->twitchBroadcasterId)
        ];

        if ($objConfig->syncTwitchScheduleWithDiscordEventsFallbackPicture && $objFile = FilesModel::findByUuid($objConfig->syncTwitchScheduleWithDiscordEventsFallbackPicture)) {
            $arrConfig['default_picture'] = $objFile->path;
        }

        if ($objConfig->syncTwitchScheduleWithDiscordMessagesThumbnail && $objFile = FilesModel::findByUuid($objConfig->syncTwitchScheduleWithDiscordMessagesThumbnail)) {
            $arrConfig['avatar'] = $objFile->path;
        }

        return $arrConfig;
    }
}