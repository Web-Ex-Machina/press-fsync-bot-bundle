<?php

declare(strict_types=1);

namespace WEM\PressFsyncBotBundle\Controller\Backend;

use Contao\Controller;
use Contao\Environment;
use Contao\Message;
use WEM\PressFsyncBotBundle\Service\TaskService;

class TaskController
{
     public function __construct(
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

        $referer = preg_replace('/&(amp;)?(key)=[^&]*/', '', Environment::get('requestUri'));
        Controller::redirect($referer);
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

        $referer = preg_replace('/&(amp;)?(key)=[^&]*/', '', Environment::get('requestUri'));
        Controller::redirect($referer);
    }

    public function cleanTasks(): void
    {
        $this->tasker->cleanTasks();
        Message::addConfirmation("Tâches nettoyées");

        $referer = preg_replace('/&(amp;)?(key)=[^&]*/', '', Environment::get('requestUri'));
        Controller::redirect($referer);
    }
}