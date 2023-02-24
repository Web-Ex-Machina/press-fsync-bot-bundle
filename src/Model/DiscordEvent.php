<?php

declare(strict_types=1);

namespace WEM\PressFsyncBotBundle\Model;

/**
 * Reads and writes items.
 */
class DiscordEvent extends \WEM\UtilsBundle\Model\Model
{
    /**
     * Table name.
     *
     * @var string
     */
    protected static $strTable = 'tl_pfs_discord_event';
}
