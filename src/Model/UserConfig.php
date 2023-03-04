<?php

declare(strict_types=1);

namespace WEM\PressFsyncBotBundle\Model;

/**
 * Reads and writes items.
 */
class UserConfig extends \WEM\UtilsBundle\Model\Model
{
    /**
     * Table name.
     *
     * @var string
     */
    protected static $strTable = 'tl_pfs_user_config';

    /**
     * Default order column
     *
     * @var string
     */
    protected static $strOrderColumn = "username ASC";

    public static function findByTwitchSyncSchedulePlanned($options = [])
    {
        $t = static::$strTable;
        $sql = "$t.syncTwitchScheduleWithDiscordEvents = 1 OR $t.syncTwitchScheduleWithDiscordMessages = 1";
        return static::findBy([$sql], null, $options);
    }
}
