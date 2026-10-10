<?php

declare(strict_types=1);

namespace WEM\PressFsyncBotBundle\Controller\Backend;

use Contao\Message;
use Symfony\Component\HttpFoundation\RequestStack;
use WEM\PressFsyncBotBundle\Service\DiscordService;

class DiscordMessageController
{
    use RedirectsToRefererTrait;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly DiscordService $discord,
    ) {
    }

    public function syncMessages(): void
    {
        $this->discord->syncMessages();
        $results = $this->discord->getResults();

        Message::addConfirmation(\sprintf('%s tâches de création créées', $results['created']));
        Message::addInfo(\sprintf('%s tâches de mise à jour créées', $results['updated']));
        Message::addInfo(\sprintf('%s tâches de suppression créées', $results['deleted']));

        $this->redirectToReferer();
    }
}