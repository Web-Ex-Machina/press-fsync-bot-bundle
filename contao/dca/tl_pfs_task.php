<?php

declare(strict_types=1);

use Contao\DataContainer;
use Contao\DC_Table;

$GLOBALS['TL_DCA']['tl_pfs_task'] = [
    // Config
    'config' => [
        'dataContainer' => DC_Table::class,
        'sql' => [
            'keys' => [
                'id' => 'primary',
            ],
        ],
    ],

    // List
    'list' => [
        'sorting' => [
            'mode' => DataContainer::MODE_SORTED,
            'fields' => ['type'],
            'flag' => DataContainer::SORT_INITIAL_LETTER_ASC,
            'panelLayout' => 'filter;search,limit',
        ],
        'label' => [
            'fields' => ['type', 'task', 'endpoint', 'method'],
            'format' => '[%s] %s / %s / %s',
            'showColumns' => true,
        ],
        'global_operations' => ['all'],
        'operations' => ['edit', 'delete', 'show'],
    ],
    'palettes' => [
        'default' => '{global_legend},created_at,type,task,endpoint,data,method',
    ],
    // Fields
    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'created_at' => [
            'inputType' => 'text',
            'sql' => 'double(13,3) unsigned',
        ],
        'type' => [
            'filter' => true,
            'inputType' => 'text',
            'sql' => 'text NULL',
        ],
        'task' => [
            'filter' => true,
            'inputType' => 'text',
            'sql' => 'text NULL',
        ],
        'endpoint' => [
            'search' => true,
            'inputType' => 'text',
            'sql' => 'text NULL',
        ],
        'data' => [
            'search' => true,
            'inputType' => 'text',
            'sql' => 'text NULL',
        ],
        'method' => [
            'filter' => true,
            'inputType' => 'text',
            'sql' => 'text NULL',
        ],
    ],
];
