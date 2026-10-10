<?php

declare(strict_types=1);

namespace WEM\PressFsyncBotBundle\Controller\Backend;

use Contao\Message;
use WEM\PressFsyncBotBundle\Service\BackendActionGuard;
use WEM\PressFsyncBotBundle\Service\TaskService;

class TaskController
{
    public function __construct(
        private readonly BackendActionGuard $guard,
        private readonly TaskService $tasker,
    ) {
    }

    public function executeTasks(): void
    {
        $this->guard->assertValidRequestToken();

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

        $this->guard->redirectToReferer();
    }

    public function executeTasksDryRun(): void
    {
        $this->guard->assertValidRequestToken();

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

        $this->guard->redirectToReferer();
    }

    public function cleanTasks(): void
    {
        $this->guard->assertValidRequestToken();

        $this->tasker->cleanTasks();
        Message::addConfirmation("Tâches nettoyées");

        $this->guard->redirectToReferer();
    }
}