<?php

declare(strict_types=1);

use Contao\DataContainer;
use Contao\DC_Table;

$GLOBALS['TL_DCA']['tl_pfs_twitch_event'] = [
    // Config
    'config' => [
        'dataContainer' => DC_Table::class,
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'user' => 'index',
            ],
        ],
    ],

    // List
    'list' => [
        'sorting' => [
            'mode' => DataContainer::MODE_SORTED,
            'fields' => ['user', 'start_time'],
            'flag' => DataContainer::SORT_INITIAL_LETTER_ASC,
            'panelLayout' => 'filter;search,limit',
        ],
        'label' => [
            'fields' => ['title', 'user', 'category_name', 'start_time', 'end_time', 'is_recurring', 'canceled_until'],
            'format' => '%s [%s] - %s',
            'showColumns' => true,
        ],
        'global_operations' => [
            'all',
            'syncEvents' => [
                'href' => 'key=syncEvents',
                'icon' => 'alert',
            ],
        ],
        'operations' => ['edit', 'delete', 'show'],
    ],

    // Palettes
    'palettes' => [
        'default' => '
            {global_legend},user,twitch_event,title,start_time,end_time,category_id,category_name,is_recurring,canceled_until
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
            'filter' => true,
            'eval' => ['tl_class' => 'w50'],
            'foreignKey' => 'tl_pfs_user_config.username',
            'sql' => "int(10) unsigned NOT NULL default '0'",
            'relation' => ['type' => 'belongsTo', 'load' => 'lazy'],
        ],
        'twitch_event' => [
            'inputType' => 'text',
            'search' => true,
            'eval' => ['tl_class' => 'w50'],
            'sql' => 'text NULL',
        ],
        'start_time' => [
            'inputType' => 'text',
            'filter' => true,
            'flag' => 8,
            'eval' => ['rgxp' => 'datim', 'tl_class' => 'w50'],
            'sql' => 'text NULL',
        ],
        'end_time' => [
            'inputType' => 'text',
            'flag' => 8,
            'eval' => ['rgxp' => 'datim', 'tl_class' => 'w50'],
            'sql' => 'text NULL',
        ],
        'title' => [
            'search' => true,
            'inputType' => 'text',
            'eval' => ['tl_class' => 'clr'],
            'sql' => 'text NULL',
        ],
        'canceled_until' => [
            'inputType' => 'text',
            'eval' => ['tl_class' => 'w50'],
            'sql' => 'text NULL',
        ],
        'category_id' => [
            'inputType' => 'text',
            'search' => true,
            'eval' => ['tl_class' => 'w50'],
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        'category_name' => [
            'inputType' => 'text',
            'search' => true,
            'eval' => ['tl_class' => 'w50'],
            'sql' => 'text NULL',
        ],
        'is_recurring' => [
            'inputType' => 'checkbox',
            'filter' => true,
            'eval' => ['submitOnChange' => true, 'tl_class' => 'w50 m12'],
            'sql' => "char(1) NOT NULL default ''",
        ],
    ],
];
