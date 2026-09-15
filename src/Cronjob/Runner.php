<?php

declare(strict_types=1);

namespace WEM\PressFsyncBotBundle\Cronjob;

use Contao\CoreBundle\Framework\FrameworkAwareInterface;
use Contao\CoreBundle\Framework\FrameworkAwareTrait;
use Contao\CoreBundle\Monolog\ContaoContext;

class Runner implements FrameworkAwareInterface
{
    use FrameworkAwareTrait;

    /**
     * SynchronizationRequestListener constructor.
     */
    public function __construct()
    {
    }

    /**
     * Entry point for background execution by the Contao cron.
     */
    public function __invoke(): void
    {
    }

    public function log(string $message): void
    {
        $this->logger->info(
            $message, 
            ['contao' => new ContaoContext(__METHOD__, ContaoContext::CRON)]
        );
    }
}