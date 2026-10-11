<?php

declare(strict_types=1);

namespace WEM\PressFsyncBotBundle\Controller\Backend;

use Contao\Message;
use WEM\PressFsyncBotBundle\Service\BackendActionGuard;
use WEM\PressFsyncBotBundle\Service\DiscordService;

class DiscordEventController
{
    public function __construct(
        private readonly BackendActionGuard $guard,
        private readonly DiscordService $discord,
    ) {
    }

    public function syncEvents(): void
    {
        $this->sync(false);
    }

    public function forceSyncEvents(): void
    {
        $this->sync(true);
    }

    protected function sync(bool $blnForce): void
    {
        $this->guard->assertValidRequestToken();

        $this->discord->syncEvents($blnForce);
        $results = $this->discord->getResults();

        Message::addConfirmation(\sprintf('%s tâches de création créées', $results['created']));
        Message::addInfo(\sprintf('%s tâches de mise à jour créées', $results['updated']));
        Message::addInfo(\sprintf('%s tâches de suppression créées', $results['deleted']));

        foreach ($results['errors'] ?? [] as $error) {
            Message::addError($error);
        }

        $this->guard->redirectToReferer();
    }
}