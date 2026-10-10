<?php

declare(strict_types=1);

namespace WEM\PressFsyncBotBundle\Cronjob;

use Contao\CoreBundle\Monolog\ContaoContext;
use Psr\Log\LoggerInterface;

class Runner
{
    public function __construct(
        protected readonly LoggerInterface|null $logger = null,
    ) {
    }

    /**
     * Entry point for background execution by the Contao cron.
     */
    public function __invoke(): void
    {
    }

    public function log(string $message): void
    {
        $this->logger?->info(
            $message,
            ['contao' => new ContaoContext(__METHOD__, ContaoContext::CRON)]
        );
    }
}
