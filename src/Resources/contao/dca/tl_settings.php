<?php

declare(strict_types=1);

use Contao\CoreBundle\DataContainer\PaletteManipulator;

PaletteManipulator::create()
    ->addLegend('press_fsync_bot_legend', 'global_legend', PaletteManipulator::POSITION_AFTER)
    ->addField('pfsTwitchClientId', 'press_fsync_bot_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('pfsTwitchClientSecret', 'press_fsync_bot_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('pfsDiscordToken', 'press_fsync_bot_legend', PaletteManipulator::POSITION_APPEND)
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
