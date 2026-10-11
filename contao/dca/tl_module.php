<?php

declare(strict_types=1);

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\DataContainer;

$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'pfs_schedule_mode';
$GLOBALS['TL_DCA']['tl_module']['palettes']['press_fsync_display_schedule'] = '
    {title_legend},name,headline,type;
    {config_legend},pfs_configs,pfs_filters;
    {list_legend},pfs_schedule_mode;
    {template_legend:hide},pfs_schedule_item_template,customTpl;
    {expert_legend:hide},guests,cssID
';

$GLOBALS['TL_DCA']['tl_module']['subpalettes']['pfs_schedule_mode_list'] = 'numberOfItems,skipFirst,perPage,pfs_schedule_groupBy,pfs_schedule_nbGroupsBy';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['pfs_schedule_mode_calendar'] = '';

$GLOBALS['TL_DCA']['tl_module']['fields']['pfs_configs'] = [
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['multiple' => true, 'mandatory' => true],
    'sql' => 'blob NULL',
];
$GLOBALS['TL_DCA']['tl_module']['fields']['pfs_filters'] = [
    'default' => '',
    'exclude' => true,
    'inputType' => 'select',
    'options' => ['pid', 'category'],
    'eval' => ['multiple' => true, 'chosen' => true, 'tl_class' => 'w50'],
    'sql' => "blob NULL",
];
$GLOBALS['TL_DCA']['tl_module']['fields']['pfs_schedule_mode'] = [
    'exclude' => true,
    'inputType' => 'select',
    'options' => ['list', 'calendar'],
    'eval' => ['includeBlankOption' => true, 'submitOnChange' => true],
    'sql' => "varchar(16) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_module']['fields']['pfs_schedule_item_template'] = [
    'default' => 'pfs_schedule_item_default',
    'exclude' => true,
    'inputType' => 'select',
    'eval' => ['tl_class' => 'w50'],
    'sql' => "varchar(64) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_module']['fields']['pfs_schedule_groupBy'] = [
    'default' => '',
    'exclude' => true,
    'inputType' => 'select',
    'options' => ['day', 'week', 'month', 'year'],
    'eval' => ['includeBlankOption' => true, 'tl_class' => 'w50'],
    'sql' => "varchar(16) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_module']['fields']['pfs_schedule_nbGroupsBy'] = [
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['rgxp' => 'digit', 'tl_class' => 'w50'],
    'sql' => "int(10) unsigned NOT NULL default '0'",
];

/**
 * Filter the news list by author (see NewsListAuthorsListener)
 */
PaletteManipulator::create()
    ->addField('pressf5_be_members', 'config_legend', PaletteManipulator::POSITION_PREPEND)
    ->applyToPalette('newslist', 'tl_module')
;

$GLOBALS['TL_DCA']['tl_module']['fields']['pressf5_be_members'] = [
    'exclude' => true,
    'flag' => DataContainer::SORT_ASC,
    'inputType' => 'checkbox',
    'foreignKey' => 'tl_user.name',
    'eval' => ['doNotCopy' => true, 'chosen' => true, 'multiple' => true, 'mandatory' => true, 'includeBlankOption' => true, 'tl_class' => 'clr'],
    'sql' => 'blob NULL',
];

/**
 * Author block (see AuthorController)
 */
$GLOBALS['TL_DCA']['tl_module']['palettes']['author'] = '
    {title_legend},name,headline,type;
    {config_legend},authorArchiveType,imgSize;
    {template_legend:hide},customTpl;
    {expert_legend:hide},guests,cssID
';

$GLOBALS['TL_DCA']['tl_module']['fields']['authorArchiveType'] = [
    'default' => 'author_news',
    'exclude' => true,
    'inputType' => 'select',
    'options' => ['author_news'],
    'reference' => &$GLOBALS['TL_LANG']['tl_module']['authorArchiveTypes'],
    'eval' => ['tl_class' => 'w50'],
    'sql' => "varchar(255) NOT NULL default ''",
];
