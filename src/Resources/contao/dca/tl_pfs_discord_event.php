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

$GLOBALS['TL_DCA']['tl_pfs_discord_event'] = [
    // Config
    'config' => [
        'dataContainer' => Contao\DC_Table::class,
        'sql' => [
            'keys' => [
                'id' => 'primary',
            ],
        ],
    ],

    // List
    'list' => [
        'sorting' => [
            'mode' => 1,
            'fields' => ['user'],
            'flag' => 1,
            'panelLayout' => 'filter;search,limit',
        ],
        'label' => [
            'fields' => ['user', 'discord_event', 'twitch_event'],
            'format' => '%s [%s] - %s',
            'showColumns' => true,
        ],
        'global_operations' => [
            'all' => [
                'href' => 'act=select',
                'class' => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
            ],
        ],
        'operations' => [
            'edit' => [
                'href' => 'act=edit',
                'icon' => 'edit.svg',
            ],
            'delete' => [
                'href' => 'act=delete',
                'icon' => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\''.$GLOBALS['TL_LANG']['MSC']['deleteConfirm'].'\'))return false;Backend.getScrollOffset()"',
            ],
            'show' => [
                'href' => 'act=show',
                'icon' => 'show.svg',
            ],
        ],
    ],

    // Palettes
    'palettes' => [
        'default' => '
            {global_legend},user,twitch_event,discord_event,discord_event_name,discord_event_scheduled_start_time,discord_event_scheduled_end_time,discord_event_entity_metadata_location,discord_event_category_name
        ',
    ],

    // Fields
    'fields' => [
        'id' => [
            'inputType' => 'text',
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'user' => [
            'inputType' => 'select',
            'foreignKey' => 'tl_pfs_user_config.username',
            'sql' => "int(10) unsigned NOT NULL default '0'",
            'eval' => ['tl_class' => 'w50'],
            'relation' => ['type' => 'belongsTo', 'load' => 'lazy'],
        ],
        'twitch_event' => [
            'inputType' => 'select',
            'foreignKey' => 'tl_pfs_twitch_event.title',
            'sql' => "int(10) unsigned NOT NULL default '0'",
            'eval' => ['tl_class' => 'w50'],
            'relation' => ['type' => 'belongsTo', 'load' => 'lazy'],
        ],
        'discord_event' => [
            'inputType' => 'text',
            'search' => true,
            'eval' => ['tl_class' => 'w50'],
            'sql' => 'text NULL',
        ],
        'discord_event_name' => [
            'inputType' => 'text',
            'search' => true,
            'eval' => ['tl_class' => 'w50'],
            'sql' => 'text NULL',
        ],
        'discord_event_scheduled_start_time' => [
            'inputType' => 'text',
            'search' => true,
            'eval' => ['tl_class' => 'w50'],
            'sql' => 'text NULL',
        ],
        'discord_event_scheduled_end_time' => [
            'inputType' => 'text',
            'search' => true,
            'eval' => ['tl_class' => 'w50'],
            'sql' => 'text NULL',
        ],
        'discord_event_entity_metadata_location' => [
            'inputType' => 'text',
            'search' => true,
            'eval' => ['tl_class' => 'w50'],
            'sql' => 'text NULL',
        ],
        'discord_event_category_name' => [
            'inputType' => 'text',
            'search' => true,
            'eval' => ['tl_class' => 'w50'],
            'sql' => 'text NULL',
        ],
    ],
];
