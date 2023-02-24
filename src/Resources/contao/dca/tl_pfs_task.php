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

$GLOBALS['TL_DCA']['tl_pfs_task'] = [
    // Config
    'config' => [
        'dataContainer' => \Contao\DC_Table::class,
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
            'fields' => ['type'],
            'flag' => 1,
            'panelLayout' => 'filter;search,limit',
        ],
        'label' => [
            'fields' => ['type', 'task', 'endpoint', 'method'],
            'format' => '[%s] %s / %s / %s',
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
    'palettes' => [
        'default' => '{global_legend},created_at,type,task,endpoint,data,method',
    ],
    // Fields
    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
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
