<?php

declare(strict_types=1);

namespace WEM\PressFsyncBotBundle\Service;

use Contao\Environment;
use Contao\FilesModel;
use Contao\FrontendTemplate;
use Contao\Input;
use WEM\PressFsyncBotBundle\Model\Task;
use WEM\PressFsyncBotBundle\Model\TwitchEvent;

class ScheduleService
{
    public function __construct(
        private readonly DiscordService $discord,
        private readonly Encryption $encryption,
        private readonly TwitchService $twitch,
    ) {
    }

    public function getSchedule(): string
    {
        $c = ['notcanceled' => true];

        if(1 !== (int) Input::get('keepCurrent')) {
            $c['start_time_after'] = time();
        }

        $limit = Input::get('limit') ?: 3;
        $template = Input::get('template') ?: 'default';

        $objItems = TwitchEvent::findItems($c, $limit);
        $arrEvents = [];

        if (!$objItems) {
            echo '';
            die;
        }

        $objTemplate = new FrontendTemplate('schedule_' . $template);

        while ($objItems->next()) {
            $u = $objItems->getRelated('user');
            $logo = FilesModel::findByUuid($u->syncTwitchScheduleWithDiscordMessagesThumbnail);

            $e = $objItems->row();
            $e['logo'] = $logo ? Environment::get('base') . '/' . $logo->path : null;
            $e['username'] = $this->encryption->decrypt($u->twitchUsername);
            $e['url'] = 'https://www.twitch.tv/' . $this->encryption->decrypt($u->twitchUsername);
            $e['datetime'] = date('d/m/Y à H:i', $objItems->start_time);
            $e['date'] = date('d/m/Y', $objItems->start_time);
            $e['date_simple'] = date('d/m', $objItems->start_time);
            $e['time'] = date('H\hi', $objItems->start_time);

            $arrEvents[] = $e;
        }

        $objTemplate->items = $arrEvents;
        echo $objTemplate->parse();
        die;
    }

    public function syncSchedules(): void
    {
        $this->twitch->syncEvents();
        $this->discord->syncMessages();
        $this->discord->syncEvents();
    }
}