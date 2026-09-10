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

use Contao\CoreBundle\DataContainer\PaletteManipulator;

PaletteManipulator::create()
    ->addLegend('press_fsync_bot_legend', 'global_legend', PaletteManipulator::POSITION_AFTER)
    ->addField('pfsTwitchClientId', 'press_fsync_bot_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('pfsTwitchClientSecret', 'press_fsync_bot_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('pfsDiscordToken', 'press_fsync_bot_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('pfsRawgApiSecret', 'press_fsync_bot_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('default', 'tl_settings')
;

$GLOBALS['TL_DCA']['tl_settings']['fields']['pfsTwitchClientId'] = [
    'inputType' => 'text',
    'eval' => ['tl_class' => 'w50'],
    'load_callback' => [
        ['plenta.encryption', 'decrypt'],
    ],
    'save_callback' => [
        ['plenta.encryption', 'encrypt'],
    ],
];
$GLOBALS['TL_DCA']['tl_settings']['fields']['pfsTwitchClientSecret'] = [
    'inputType' => 'text',
    'eval' => ['tl_class' => 'w50'],
    'load_callback' => [
        ['plenta.encryption', 'decrypt'],
    ],
    'save_callback' => [
        ['plenta.encryption', 'encrypt'],
    ],
];
$GLOBALS['TL_DCA']['tl_settings']['fields']['pfsDiscordToken'] = [
    'inputType' => 'text',
    'eval' => ['tl_class' => 'w50'],
    'load_callback' => [
        ['plenta.encryption', 'decrypt'],
    ],
    'save_callback' => [
        ['plenta.encryption', 'encrypt'],
    ],
];
$GLOBALS['TL_DCA']['tl_settings']['fields']['pfsRawgApiSecret'] = [
    'inputType' => 'text',
    'eval' => ['tl_class' => 'w50'],
    'load_callback' => [
        ['plenta.encryption', 'decrypt'],
    ],
    'save_callback' => [
        ['plenta.encryption', 'encrypt'],
    ],
];
