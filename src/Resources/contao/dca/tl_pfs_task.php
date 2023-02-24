<?php

$GLOBALS['TL_DCA']['tl_pfs_task'] = array(
    // Config
    'config' => array('dataContainer' => \Contao\DC_Table::class, 'sql' => array('keys' => array('id' => 'primary'))),
    // List
    'list' => array(
        'sorting' => array('mode' => 1, 'fields' => array('type'), 'flag' => 1, 'panelLayout' => 'filter;search,limit'), 'label' => array('fields' => array('type', 'task', 'endpoint', 'method'), 'format' => '[%s] %s / %s / %s', 'showColumns' => \true),
        'global_operations' => array(
            'all' => array(
                'href' => 'act=select', 'class' => 'header_edit_all', 'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"'
            )
        ),
        'operations' => array(
            'edit' => array('href' => 'act=edit', 'icon' => 'edit.svg'), 'delete' => array(
                'href' => 'act=delete', 'icon' => 'delete.svg', 'attributes' => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\'))return false;Backend.getScrollOffset()"'
            ),
            'show' => array('href' => 'act=show', 'icon' => 'show.svg')
        )
    ),
    'palettes' => array('default' => '
            {global_legend},created_at,type,task,endpoint,data,method
        '),
    // Fields
    'fields' => array(
        'id' => array('sql' => "int(10) unsigned NOT NULL auto_increment"), 'created_at' => array('inputType' => 'text', 'sql' => "double(13,3) unsigned"),
        'type' => array('filter' => \true, 'inputType' => 'text', 'sql' => "text NULL"), 'task' => array('filter' => \true, 'inputType' => 'text', 'sql' => "text NULL"),
        'endpoint' => array('search' => \true, 'inputType' => 'text', 'sql' => "text NULL"), 'data' => array('search' => \true, 'inputType' => 'text', 'sql' => "text NULL"),
        'method' => array('filter' => \true, 'inputType' => 'text', 'sql' => "text NULL")
    ),
);
