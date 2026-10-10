<?php

declare(strict_types=1);

use Contao\DataContainer;
use Contao\DC_Table;

$GLOBALS['TL_DCA']['tl_pfs_discord_event'] = [
    // Config
    'config' => [
        'dataContainer' => DC_Table::class,
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'user' => 'index',
                'twitch_event' => 'index',
            ],
        ],
    ],

    // List
    'list' => [
        'sorting' => [
            'mode' => DataContainer::MODE_SORTED,
            'fields' => ['user'],
            'flag' => DataContainer::SORT_INITIAL_LETTER_ASC,
            'panelLayout' => 'filter;search,limit',
        ],
        'label' => [
            'fields' => ['user', 'discord_event', 'twitch_event'],
            'format' => '%s [%s] - %s',
            'showColumns' => true,
        ],
        'global_operations' => [
            'all',
            'syncEvents' => [
                'href' => 'key=syncEvents',
                'icon' => 'alert',
            ],
            'syncMessages' => [
                'href' => 'key=syncMessages',
                'icon' => 'alert',
            ],
        ],
        'operations' => ['edit', 'delete', 'show'],
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
