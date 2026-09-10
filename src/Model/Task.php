<?php

declare(strict_types=1);

namespace WEM\PressFsyncBotBundle\Model;

use WEM\UtilsBundle\Model\Model;

/**
 * Reads and writes items.
 */
class Task extends Model
{
    /**
     * Table name.
     *
     * @var string
     */
    protected static $strTable = 'tl_pfs_task';
}
