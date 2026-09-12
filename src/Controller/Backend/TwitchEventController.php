<?php

declare(strict_types=1);

namespace WEM\PressFsyncBotBundle\Controller\Backend;

use Contao\Controller;
use Contao\Environment;
use Contao\Message;
use WEM\PressFsyncBotBundle\Service\TwitchService;

class TwitchEventController
{
     public function __construct(
        private readonly TwitchService $twitch,
    ) {
    }

    public function syncEvents(): void
    {
        $this->twitch->syncEvents();
        $results = $this->twitch->getResults();

        Message::addConfirmation(\sprintf('%s événements créés', $results['created']));
        Message::addInfo(\sprintf('%s événements mis à jour', $results['updated']));
        Message::addInfo(\sprintf('%s événements supprimés', $results['deleted']));

        $referer = preg_replace('/&(amp;)?(key)=[^&]*/', '', Environment::get('requestUri'));
        Controller::redirect($referer);
    }
}