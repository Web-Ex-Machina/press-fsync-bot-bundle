<?php

declare(strict_types=1);

namespace WEM\PressFsyncBotBundle\Controller\Backend;

use Contao\Message;
use Symfony\Component\HttpFoundation\RequestStack;
use WEM\PressFsyncBotBundle\Service\TaskService;

class TaskController
{
    use RedirectsToRefererTrait;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly TaskService $tasker,
    ) {
    }

    public function executeTasks(): void
    {
        $this->tasker->setDebug(false);
        $this->tasker->executeTasks();
        $results = $this->tasker->getResults();

        Message::addConfirmation(\sprintf('%s tâches executées avec succès', count($results['success'])));
        Message::addInfo(\sprintf('%s tâches échouées', count($results['errors'])));
        Message::addInfo(\sprintf('%s tâches en attente', $results['pending']));

        if (!empty($results['errors_details'])) {
            foreach ($results['errors_details'] as $e) {
                Message::addError($e);
            }
        }

        $this->redirectToReferer();
    }

    public function executeTasksDryRun(): void
    {
        $this->tasker->setDebug(true);
        $this->tasker->executeTasks();
        $results = $this->tasker->getResults();

        Message::addConfirmation(\sprintf('%s tâches executées avec succès', count($results['success'])));
        Message::addInfo(\sprintf('%s tâches échouées', count($results['errors'])));
        Message::addInfo(\sprintf('%s tâches en attente', $results['pending']));

        if (!empty($results['errors_details'])) {
            foreach ($results['errors_details'] as $e) {
                Message::addError($e);
            }
        }

        if (!empty($results['debug'])) {
            foreach ($results['debug'] as $e) {
                Message::addInfo($e);
            }
        }

        $this->redirectToReferer();
    }

    public function cleanTasks(): void
    {
        $this->tasker->cleanTasks();
        Message::addConfirmation("Tâches nettoyées");

        $this->redirectToReferer();
    }
}