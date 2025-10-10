<?php

declare(strict_types=1);

/**
 * Press Fsync Bot Bundle for Contao Open Source CMS
 * Copyright (c) 2023 Web ex Machina
 *
 * @category ContaoBundle
 * @package  Web-Ex-Machina/press-fsync-bot-bundle
 * @author   Web ex Machina <contact@webexmachina.fr>
 * @link     https://github.com/Web-Ex-Machina/press-fsync-bot-bundle/
 */

use WEM\PressFsyncBotBundle\Backend\RawgCallback;

/*
 * Back end modules
 */
array_insert($GLOBALS['BE_MOD'], 1, [
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
        'press_fsync_discord_messages' => [
            'tables' => ['tl_pfs_discord_message'],
        ],
        'press_fsync_tasks' => [
            'tables' => ['tl_pfs_task'],
            'debugRawgApi' => array(RawgCallback::class, 'debugRawgApi'),
        ],
    ],
]);

/**
 * Frontend modules
 */
array_insert($GLOBALS['FE_MOD'], 2, [
    'press_fsync' => [
        'press_fsync_display_schedule' => WEM\PressFsyncBotBundle\Module\DisplaySchedule::class,
        'press_shamelist_ruvon' => WEM\PressFsyncBotBundle\Module\ShamelistRuvon::class,
    ],
]);

/*
 * Hooks
 */
$GLOBALS['TL_HOOKS']['generatePage'][] = [WEM\PressFsyncBotBundle\Event\GeneratePageListener::class, 'catchApiRequest'];

/*
 * Models
 */
$GLOBALS['TL_MODELS'][WEM\PressFsyncBotBundle\Model\DiscordMessage::getTable()] = WEM\PressFsyncBotBundle\Model\DiscordMessage::class;
$GLOBALS['TL_MODELS'][WEM\PressFsyncBotBundle\Model\DiscordEvent::getTable()] = WEM\PressFsyncBotBundle\Model\DiscordEvent::class;
$GLOBALS['TL_MODELS'][WEM\PressFsyncBotBundle\Model\Task::getTable()] = WEM\PressFsyncBotBundle\Model\Task::class;
$GLOBALS['TL_MODELS'][WEM\PressFsyncBotBundle\Model\TwitchEvent::getTable()] = WEM\PressFsyncBotBundle\Model\TwitchEvent::class;
$GLOBALS['TL_MODELS'][WEM\PressFsyncBotBundle\Model\UserConfig::getTable()] = WEM\PressFsyncBotBundle\Model\UserConfig::class;
