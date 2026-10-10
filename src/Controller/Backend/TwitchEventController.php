<?php

declare(strict_types=1);

namespace WEM\PressFsyncBotBundle\Controller\Backend;

use Contao\Message;
use WEM\PressFsyncBotBundle\Service\BackendActionGuard;
use WEM\PressFsyncBotBundle\Service\TwitchService;

class TwitchEventController
{
    public function __construct(
        private readonly BackendActionGuard $guard,
        private readonly TwitchService $twitch,
    ) {
    }

    public function syncEvents(): void
    {
        $this->guard->assertValidRequestToken();

        $this->twitch->syncEvents();
        $results = $this->twitch->getResults();

        Message::addConfirmation(\sprintf('%s événements créés', $results['created']));
        Message::addInfo(\sprintf('%s événements mis à jour', $results['updated']));
        Message::addInfo(\sprintf('%s événements supprimés', $results['deleted']));

        $this->guard->redirectToReferer();
    }
}