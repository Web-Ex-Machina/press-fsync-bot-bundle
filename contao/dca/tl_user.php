<?php

declare(strict_types=1);

use Contao\CoreBundle\DataContainer\PaletteManipulator;

/**
 * Author information, displayed by the "author" front end module.
 */
foreach (['login', 'admin', 'default', 'group', 'extend', 'custom'] as $palette) {
    PaletteManipulator::create()
        ->addLegend('author_legend', 'backend_legend', PaletteManipulator::POSITION_BEFORE)
        ->addField(['authorPicture', 'authorDescription', 'authorPage', 'authorLinks'], 'author_legend', PaletteManipulator::POSITION_APPEND)
        ->applyToPalette($palette, 'tl_user')
    ;
}

$GLOBALS['TL_DCA']['tl_user']['fields']['authorPicture'] = [
    'exclude' => true,
    'inputType' => 'fileTree',
    'eval' => ['filesOnly' => true, 'fieldType' => 'radio', 'extensions' => '%contao.image.valid_extensions%', 'tl_class' => 'clr'],
    'sql' => 'varbinary(255) NULL',
];
$GLOBALS['TL_DCA']['tl_user']['fields']['authorDescription'] = [
    'exclude' => true,
    'inputType' => 'textarea',
    'eval' => ['style' => 'height:80px', 'tl_class' => 'clr'],
    'sql' => 'longtext NULL',
];
$GLOBALS['TL_DCA']['tl_user']['fields']['authorPage'] = [
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['rgxp' => 'url', 'decodeEntities' => true, 'dcaPicker' => true, 'maxlength' => 255, 'tl_class' => 'clr'],
    'sql' => "varchar(255) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_user']['fields']['authorLinks'] = [
    'exclude' => true,
    'inputType' => 'multiColumnWizard',
    'eval' => [
        'tl_class' => 'clr',
        'columnFields' => [
            'name' => [
                'label' => &$GLOBALS['TL_LANG']['tl_user']['authorLinks_name'],
                'inputType' => 'text',
                'eval' => ['style' => 'width:200px'],
            ],
            'link' => [
                'label' => &$GLOBALS['TL_LANG']['tl_user']['authorLinks_link'],
                'inputType' => 'text',
                'eval' => ['rgxp' => 'url', 'decodeEntities' => true, 'style' => 'width:400px'],
            ],
        ],
    ],
    'sql' => 'longblob NULL',
];
