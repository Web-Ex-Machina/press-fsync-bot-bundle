<?php

$GLOBALS['TL_DCA']['tl_pfs_discord_message'] = array(
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
            'fields'                  => array('server', 'channel', 'message', 'user'),
            'format'                  => '%s - %s - %s - %s',
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
			{global_legend},server,channel,message,events,user
		'
    ),

    // Fields
    'fields' => array(
        'id' => array(
            'sql'                     => "int(10) unsigned NOT NULL auto_increment"
        ),
        'server' => array(
            'search'                  => true,
            'inputType'               => 'text',
            'sql'                     => "varchar(255) NOT NULL default ''"
        ),
        'channel' => array(
            'search'                  => true,
            'inputType'               => 'text',
            'sql'                     => "varchar(255) NOT NULL default ''"
        ),
        'message' => array(
            'search'                  => true,
            'inputType'               => 'text',
            'sql'                     => "varchar(255) NOT NULL default ''"
        ),
        'events' => array(
            'search'                  => true,
            'inputType'               => 'text',
            'sql'                     => "text NULL"
        ),
        'user' => array(
            'search'                  => true,
            'inputType'               => 'text',
            'sql'                     => "varchar(255) NOT NULL default ''"
        )
    )
);
