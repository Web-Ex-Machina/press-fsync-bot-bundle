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

$GLOBALS['TL_DCA']['tl_pfs_twitch_event'] = [
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
            'fields' => ['user', 'start_time'],
            'flag' => 1,
            'panelLayout' => 'filter;search,limit',
        ],
        'label' => [
            'fields' => ['title', 'user', 'category_name', 'start_time', 'end_time', 'is_recurring', 'canceled_until'],
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
