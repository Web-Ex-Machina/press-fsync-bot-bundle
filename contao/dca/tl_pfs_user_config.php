<?php

declare(strict_types=1);

use Contao\DataContainer;
use Contao\DC_Table;

$GLOBALS['TL_DCA']['tl_pfs_user_config'] = [
    // Config
    'config' => [
        'dataContainer' => DC_Table::class,
        'enableVersioning' => true,
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
            'fields' => ['username'],
            'flag' => DataContainer::SORT_INITIAL_LETTER_ASC,
            'panelLayout' => 'filter;search,limit',
        ],
        'label' => [
            'fields' => ['username'],
            'format' => '%s',
        ],
        'global_operations' => ['all'],
        'operations' => ['edit', 'delete', 'show'],
    ],

    // Palettes
    'palettes' => [
        '__selector__' => [
            'syncTwitchScheduleWithDiscordEvents', 
            'syncTwitchScheduleWithDiscordMessages', 
            'sendDiscordAlertWhenLiveOnTwitch'
        ],
        'default' => '
            {global_legend},username;
            {twitch_legend},twitchUsername,twitchBroadcasterId;
            {syncTwitchScheduleWithDiscordEvents_legend},syncTwitchScheduleWithDiscordEvents;
            {syncTwitchScheduleWithDiscordMessages_legend},syncTwitchScheduleWithDiscordMessages;
            {sendDiscordAlertWhenLiveOnTwitch_legend},sendDiscordAlertWhenLiveOnTwitch
        ',
    ],

    // Subpalettes
    'subpalettes' => [
        'syncTwitchScheduleWithDiscordEvents' => 'syncTwitchScheduleWithDiscordEventsServers,syncTwitchScheduleWithDiscordEventsFallbackPicture',
        'syncTwitchScheduleWithDiscordMessages' => 'syncTwitchScheduleWithDiscordMessagesFormat,syncTwitchScheduleWithDiscordMessagesColor,syncTwitchScheduleWithDiscordMessagesThumbnail,syncTwitchScheduleWithDiscordMessagesRecipients',
        'sendDiscordAlertWhenLiveOnTwitch' => '',
    ],

    // Fields
    'fields' => [
        'id' => [
            'search' => true,
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'username' => [
            'exclude' => true,
            'search' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'rgxp' => 'extnd', 'unique' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'twitchUsername' => [
            'exclude' => true,
            'search' => true,
            'inputType' => 'text',
            'load_callback' => [
                ['wem.encryption_util', 'decrypt_b64'],
            ],
            'save_callback' => [
                ['wem.encryption_util', 'encrypt_b64'],
            ],
            'eval' => ['mandatory' => true, 'rgxp' => 'extnd', 'unique' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'twitchBroadcasterId' => [
            'exclude' => true,
            'search' => true,
            'inputType' => 'text',
            'load_callback' => [
                ['wem.encryption_util', 'decrypt_b64'],
            ],
            'save_callback' => [
                ['wem.encryption_util', 'encrypt_b64'],
            ],
            'eval' => ['mandatory' => true, 'rgxp' => 'extnd', 'unique' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'syncTwitchScheduleWithDiscordEvents' => [
            'exclude' => true,
            'inputType' => 'checkbox',
            'eval' => ['submitOnChange' => true],
            'sql' => "char(1) NOT NULL default ''",
        ],
        'syncTwitchScheduleWithDiscordEventsServers' => [
            'exclude' => true,
            'inputType' => 'listWizard',
            'eval' => ['tl_class' => 'clr'],
            'sql' => 'blob NULL',
        ],
        'syncTwitchScheduleWithDiscordEventsFallbackPicture' => [
            'exclude' => true,
            'inputType' => 'fileTree',
            'eval' => ['filesOnly' => true, 'fieldType' => 'radio'],
            'sql' => 'binary(16) NULL',
        ],
        'syncTwitchScheduleWithDiscordMessages' => [
            'exclude' => true,
            'inputType' => 'checkbox',
            'eval' => ['submitOnChange' => true],
            'sql' => "char(1) NOT NULL default ''",
        ],
        'syncTwitchScheduleWithDiscordMessagesFormat' => [
            'exclude' => true,
            'search' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'clr'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'syncTwitchScheduleWithDiscordMessagesColor' => [
            'exclude' => true,
            'search' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'clr'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'syncTwitchScheduleWithDiscordMessagesThumbnail' => [
            'exclude' => true,
            'inputType' => 'fileTree',
            'eval' => ['filesOnly' => true, 'fieldType' => 'radio'],
            'sql' => 'binary(16) NULL',
        ],
        'syncTwitchScheduleWithDiscordMessagesRecipients' => [
            'inputType' => 'keyValueWizard',
            'exclude' => true,
            'sql' => 'text NULL',
        ],
        'sendDiscordAlertWhenLiveOnTwitch' => [
            'exclude' => true,
            'inputType' => 'checkbox',
            'eval' => ['submitOnChange' => true],
            'sql' => "char(1) NOT NULL default ''",
        ],
    ],
];
