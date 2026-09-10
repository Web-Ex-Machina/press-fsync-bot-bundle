<?php

declare(strict_types=1);

use Contao\ArrayUtil;
use WEM\PressFsyncBotBundle\Model\DiscordEvent;
use WEM\PressFsyncBotBundle\Model\Task;
use WEM\PressFsyncBotBundle\Model\TwitchEvent;
use WEM\PressFsyncBotBundle\Model\UserConfig;

/*
 * Back end modules
 */
ArrayUtil::arrayInsert(
    $GLOBALS['BE_MOD'], 
    1, 
    [
        'press_fsync' => [
            'press_fsync_user_configs' => [
                'tables' => ['tl_pfs_user_config'],
            ],
            'press_fsync_twitch_events' => [
                'tables' => ['tl_pfs_twitch_event'],
            ],
            'press_fsync_discord_events' => [
                'tables' => ['tl_pfs_discord_event'],
            ],
            'press_fsync_tasks' => [
                'tables' => ['tl_pfs_task'],
            ],
        ],
    ]
);

/**
 * Frontend modules
 */
/**array_insert($GLOBALS['FE_MOD'], 2, [
    'press_fsync' => [
        'press_fsync_display_schedule' => WEM\PressFsyncBotBundle\Module\DisplaySchedule::class,
        'press_shamelist_ruvon' => WEM\PressFsyncBotBundle\Module\ShamelistRuvon::class,
    ],
]);**/

/*
 * Hooks
 */
// $GLOBALS['TL_HOOKS']['generatePage'][] = [WEM\PressFsyncBotBundle\Event\GeneratePageListener::class, 'catchApiRequest'];

/*
 * Models
 */
$GLOBALS['TL_MODELS'][DiscordEvent::getTable()] = DiscordEvent::class;
$GLOBALS['TL_MODELS'][Task::getTable()] = Task::class;
$GLOBALS['TL_MODELS'][TwitchEvent::getTable()] = TwitchEvent::class;
$GLOBALS['TL_MODELS'][UserConfig::getTable()] = UserConfig::class;
