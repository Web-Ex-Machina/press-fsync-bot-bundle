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

$GLOBALS['TL_DCA']['tl_module']['palettes']['press_fsync_display_schedule'] = '
    {title_legend},name,headline,type;
    {config_legend},pfs_configs;
    {list_legend},numberOfItems,skipFirst,perPage,pfs_schedule_groupBy;
    {template_legend:hide},pfs_schedule_item_template,customTpl;
    {expert_legend:hide},guests,cssID
';

$GLOBALS['TL_DCA']['tl_module']['fields']['pfs_configs'] = [
    'exclude' => true,
    'inputType' => 'checkbox',
    'options_callback' => [WEM\PressFsyncBotBundle\DataContainer\ModuleContainer::class, 'getConfigs'],
    'eval' => ['multiple' => true, 'mandatory' => true],
    'sql' => 'blob NULL',
];
$GLOBALS['TL_DCA']['tl_module']['fields']['pfs_schedule_item_template'] = [
    'default' => 'job_default',
    'exclude' => true,
    'inputType' => 'select',
    'options_callback' => [WEM\PressFsyncBotBundle\DataContainer\ModuleContainer::class, 'getTemplates'],
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