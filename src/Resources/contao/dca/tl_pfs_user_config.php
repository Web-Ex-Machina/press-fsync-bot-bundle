<?php

$GLOBALS['TL_DCA']['tl_pfs_user_config'] = array(
    // Config
    'config' => array(
        'dataContainer'               => Contao\DC_Table::class,
        'enableVersioning'            => true,
        'sql' => array(
            'keys' => array(
                'id' => 'primary'
            )
        )
    ),

    // List
    'list' => array(
        'sorting' => array(
            'mode'                    => 1,
            'fields'                  => array('username'),
            'flag'                    => 1,
            'panelLayout'             => 'filter;search,limit',
        ),
        'label' => array(
            'fields'                  => array('username'),
            'format'                  => '%s'
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
        '__selector__' => array('syncTwitchScheduleWithDiscordEvents', 'syncTwitchScheduleWithDiscordMessages', 'sendDiscordAlertWhenLiveOnTwitch'),
        'default' => '
			{global_legend},username;
			{twitch_legend},twitchUsername,twitchBroadcasterId;
			{youtube_legend},youtubeChannel;
			{syncTwitchScheduleWithDiscordEvents_legend},syncTwitchScheduleWithDiscordEvents;
			{syncTwitchScheduleWithDiscordMessages_legend},syncTwitchScheduleWithDiscordMessages;
			{sendDiscordAlertWhenLiveOnTwitch_legend},sendDiscordAlertWhenLiveOnTwitch
		'
    ),

    // Subpalettes
    'subpalettes' => [
        'syncTwitchScheduleWithDiscordEvents' => 'syncTwitchScheduleWithDiscordEventsServers,syncTwitchScheduleWithDiscordEventsFallbackPicture',
        'syncTwitchScheduleWithDiscordMessages' => 'syncTwitchScheduleWithDiscordMessagesFormat,syncTwitchScheduleWithDiscordMessagesColor,syncTwitchScheduleWithDiscordMessagesThumbnail,syncTwitchScheduleWithDiscordMessagesRecipients',
        'sendDiscordAlertWhenLiveOnTwitch' => '',
    ],

    // Fields
    'fields' => array(
        'id' => array(
            'label'                   => array('ID'),
            'search'                  => true,
            'sql'                     => "int(10) unsigned NOT NULL auto_increment"
        ),
        'tstamp' => array(
            'sql'                     => "int(10) unsigned NOT NULL default '0'"
        ),
        'username' => array(
            'exclude'                 => true,
            'search'                  => true,
            'inputType'               => 'text',
            'eval'                    => array('mandatory'=>true, 'rgxp'=>'extnd', 'unique'=>true, 'maxlength'=>255, 'tl_class'=>'w50'),
            'sql'                     => "varchar(255) NOT NULL default ''"
        ),
        'twitchUsername' => array(
            'exclude'                 => true,
            'search'                  => true,
            'inputType'               => 'text',
            'load_callback' => [
                ['plenta.encryption', 'decrypt']
            ],
            'save_callback' => [
                ['plenta.encryption', 'encrypt']
            ],
            'eval'                    => array('mandatory'=>true, 'rgxp'=>'extnd', 'unique'=>true, 'maxlength'=>255, 'tl_class'=>'w50'),
            'sql'                     => "varchar(255) NOT NULL default ''"
        ),
        'twitchBroadcasterId' => array(
            'exclude'                 => true,
            'search'                  => true,
            'inputType'               => 'text',
            'load_callback' => [
                ['plenta.encryption', 'decrypt']
            ],
            'save_callback' => [
                ['plenta.encryption', 'encrypt']
            ],
            'eval'                    => array('mandatory'=>true, 'rgxp'=>'extnd', 'unique'=>true, 'maxlength'=>255, 'tl_class'=>'w50'),
            'sql'                     => "varchar(255) NOT NULL default ''"
        ),
        'youtubeChannel' => array(
            'exclude'                 => true,
            'search'                  => true,
            'inputType'               => 'text',
            'load_callback' => [
                ['plenta.encryption', 'decrypt']
            ],
            'save_callback' => [
                ['plenta.encryption', 'encrypt']
            ],
            'eval'                    => array('mandatory'=>true, 'rgxp'=>'extnd', 'unique'=>true, 'maxlength'=>255, 'tl_class'=>'w50'),
            'sql'                     => "varchar(255) NOT NULL default ''"
        ),
        'syncTwitchScheduleWithDiscordEvents' => array(
            'exclude'                 => true,
            'inputType'               => 'checkbox',
            'eval'                    => array('submitOnChange'=>true),
            'sql'                     => "char(1) NOT NULL default ''"
        ),
        'syncTwitchScheduleWithDiscordEventsServers' => array(
            'exclude'                 => true,
            'inputType'               => 'listWizard',
            'eval'                    => array('tl_class'=>'clr'),
            'sql'                     => "blob NULL"
        ),
        'syncTwitchScheduleWithDiscordEventsFallbackPicture' => array(
            'exclude'                 => true,
            'inputType'               => 'fileTree',
            'eval'                    => array('filesOnly'=>true, 'fieldType'=>'radio'),
            'sql'                     => "binary(16) NULL"
        ),
        'syncTwitchScheduleWithDiscordMessages' => array(
            'exclude'                 => true,
            'inputType'               => 'checkbox',
            'eval'                    => array('submitOnChange'=>true),
            'sql'                     => "char(1) NOT NULL default ''"
        ),
        'syncTwitchScheduleWithDiscordMessagesFormat' => array(
            'exclude'                 => true,
            'search'                  => true,
            'inputType'               => 'text',
            'eval'                    => array('mandatory'=>true, 'maxlength'=>255, 'tl_class'=>'clr'),
            'sql'                     => "varchar(255) NOT NULL default ''"
        ),
        'syncTwitchScheduleWithDiscordMessagesColor' => array(
            'exclude'                 => true,
            'search'                  => true,
            'inputType'               => 'text',
            'eval'                    => array('mandatory'=>true, 'maxlength'=>255, 'tl_class'=>'clr'),
            'sql'                     => "varchar(255) NOT NULL default ''"
        ),
        'syncTwitchScheduleWithDiscordMessagesThumbnail' => array(
            'exclude'                 => true,
            'inputType'               => 'fileTree',
            'eval'                    => array('filesOnly'=>true, 'fieldType'=>'radio'),
            'sql'                     => "binary(16) NULL"
        ),
        'syncTwitchScheduleWithDiscordMessagesRecipients' => array(
            'inputType'               => 'keyValueWizard',
            'exclude'                 => true,
            'sql'                     => "text NULL"
        ),
        'sendDiscordAlertWhenLiveOnTwitch' => array(
            'exclude'                 => true,
            'inputType'               => 'checkbox',
            'eval'                    => array('submitOnChange'=>true),
            'sql'                     => "char(1) NOT NULL default ''"
        ),
    )
);
