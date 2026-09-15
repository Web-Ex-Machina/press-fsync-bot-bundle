<?php

namespace WEM\PressFsyncBotBundle\Cronjob\Job;

use Contao\Config;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Contao\CoreBundle\Exception\CronExecutionSkippedException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Psr\Log\LoggerInterface;
use WEM\PressFsyncBotBundle\Cronjob\Runner;
use WEM\PressFsyncBotBundle\Service\TaskService;

#[AsCronJob('5,15,25,35,45,55 * * * *')]
class ExecuteTasksJob extends Runner
{
    public function __construct(
        protected LoggerInterface|null $logger,
        private readonly TaskService $tasker,
    ) {
        parent::__construct();
    }

    public function __invoke(): void
    {
        parent::__invoke();
        
        if ('prod' !== Config::get('pfsExecuteTasksJob')) {
            return;
        }
        
        $this->log('Start Executing Tasks');

        $this->tasker->executeTasks();
        $results = $this->tasker->getResults();

        $this->log(\sprintf(
            'Tasker - %s/%s/%s success/failed/pending', 
            count($results['success']), 
            count($results['errors']), 
            $results['pending'],
        ));

        if (!empty($results['errors_details'])) {
            foreach ($results['errors_details'] as $e) {
                $this->log("Tasker error: " . $e);
            }
        }

        $this->log('End Executing Tasks');
    }
}