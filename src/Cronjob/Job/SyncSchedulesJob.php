<?php

namespace WEM\PressFsyncBotBundle\Cronjob\Job;

use Contao\Config;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Contao\CoreBundle\Exception\CronExecutionSkippedException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Psr\Log\LoggerInterface;
use WEM\PressFsyncBotBundle\Cronjob\Runner;
use WEM\PressFsyncBotBundle\Service\DiscordService;
use WEM\PressFsyncBotBundle\Service\TwitchService;

#[AsCronJob('0,10,20,30,40,50 * * * *')]
class SyncSchedulesJob extends Runner
{
    public function __construct(
        protected LoggerInterface|null $logger,
        private readonly DiscordService $discord,
        private readonly TwitchService $twitch,
    ) {
        parent::__construct();
    }

    public function __invoke(): void
    {
        parent::__invoke();

        if ('prod' !== Config::get('pfsSyncSchedulesJob')) {
            return;
        }
        
        $this->log('Start Sync Schedules');
        $this->twitch->syncEvents();
        $results = $this->twitch->getResults();
        $this->log(\sprintf('Twitch - %s/%s/%s created/updated/deleted', $results['created'], $results['updated'], $results['deleted']));

        $this->discord->syncEvents();
        $results = $this->discord->getResults();
        $this->log(\sprintf('Discord Events - %s/%s/%s created/updated/deleted', $results['created'], $results['updated'], $results['deleted']));

        // $this->discord->syncMessages();
        // $results = $this->discord->getResults();
        // $this->log(\sprintf('Discord Messages - %s/%s/%s created/updated/deleted', $results['created'], $results['updated'], $results['deleted']));

        $this->log('End Sync Schedules');
    }
}