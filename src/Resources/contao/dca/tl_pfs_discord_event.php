<?php

$GLOBALS['TL_DCA']['tl_pfs_discord_event'] = array(
    // Config
    'config' => array(
        'dataContainer' => Contao\DC_Table::class,
        'sql' => array(
            'keys' => array(
                'id' => 'primary',
            )
        )
    ),

    // List
    'list' => array(
        'sorting' => array(
            'mode'                    => 1,
            'fields'                  => array('user'),
            'flag'                    => 1,
            'panelLayout'             => 'filter;search,limit',
        ),
        'label' => array(
            'fields'                  => array('title', 'category_name', 'start_time'),
            'format'                  => '%s [%s] - %s',
            'showColumns' => true
        ),
        'global_operations' => array(
            'all' => array(
                'href'                => 'act=select',
                'class'               => 'header_edit_all',
                'attributes'          => 'onclick="Backend.getScrollOffset()" accesskey="e"'
            )
        ),
        'operations' => array(
            'edit' => array(
                'href'                => 'act=edit',
                'icon'                => 'edit.svg'
            ),
            'delete' => array(
                'href'                => 'act=delete',
                'icon'                => 'delete.svg',
                'attributes'          => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\'))return false;Backend.getScrollOffset()"'
            ),
            'show' => array(
                'href'                => 'act=show',
                'icon'                => 'show.svg'
            )
        )
    ),

    // Palettes
    'palettes' => array(
        'default' => '
			{global_legend},user,discord_event,twitch_event,start_time,end_time,title,category_id,category_name
		'
    ),

    // Fields
    'fields' => array(
        'id' => array(
            'inputType'               => 'text',
            'sql'                     => "int(10) unsigned NOT NULL auto_increment"
        ),
        'user' => array(
            'search'                  => true,
            'inputType'               => 'text',
            'sql'                     => "text NULL"
        ),
        'discord_event' => array(
            'search'                  => true,
            'inputType'               => 'text',
            'sql'                     => "text NULL"
        ),
        'twitch_event' => array(
            'search'                  => true,
            'inputType'               => 'text',
            'sql'                     => "text NULL"
        ),
        'start_time' => array(
            'inputType'               => 'text',
            'sql'                     => "text NULL"
        ),
        'end_time' => array(
            'inputType'               => 'text',
            'sql'                     => "text NULL"
        ),
        'title' => array(
            'search'                  => true,
            'inputType'               => 'text',
            'sql'                     => "text NULL"
        ),
        'category_id' => array(
            'inputType'               => 'text',
            'sql'                     => "int(10) unsigned NOT NULL default 0"
        ),
        'category_name' => array(
            'search'                  => true,
            'inputType'               => 'text',
            'sql'                     => "text NULL"
        ),
    )
);
