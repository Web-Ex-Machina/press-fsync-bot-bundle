<?php

declare(strict_types=1);

namespace WEM\PressFsyncBotBundle\Service;

use Contao\Environment;
use Contao\FilesModel;
use Contao\FrontendTemplate;
use Contao\Input;
use WEM\PressFsyncBotBundle\Model\Task;
use WEM\PressFsyncBotBundle\Model\TwitchEvent;
use WEM\UtilsBundle\Classes\Encryption;

class ScheduleService
{
    public function __construct(
        private readonly DiscordService $discord,
        private readonly Encryption $encryption,
        private readonly TwitchService $twitch,
    ) {
    }

    public function syncSchedules(): void
    {
        $this->twitch->syncEvents();
        $this->discord->syncMessages();
        $this->discord->syncEvents();
    }
}