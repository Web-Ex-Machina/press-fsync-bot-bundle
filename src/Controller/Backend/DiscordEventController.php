<?php

declare(strict_types=1);

namespace WEM\PressFsyncBotBundle\Controller\Backend;

use Contao\Controller;
use Contao\Environment;
use Contao\Message;
use WEM\PressFsyncBotBundle\Service\DiscordService;

class DiscordEventController
{
     public function __construct(
        private readonly DiscordService $discord,
    ) {
    }

    public function syncEvents(): void
    {
        $this->discord->syncEvents();
        $results = $this->discord->getResults();

        Message::addConfirmation(\sprintf('%s tâches de création créées', $results['created']));
        Message::addInfo(\sprintf('%s tâches de mise à jour créées', $results['updated']));
        Message::addInfo(\sprintf('%s tâches de suppression créées', $results['deleted']));

        $referer = preg_replace('/&(amp;)?(key)=[^&]*/', '', Environment::get('requestUri'));
        Controller::redirect($referer);
    }
}