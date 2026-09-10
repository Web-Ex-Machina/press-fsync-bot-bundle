<?php

declare(strict_types=1);

namespace WEM\PressFsyncBotBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\Controller;
use Contao\Database;

class ModuleContainer
{
    /**
     * Return all templates as array.
     *
     * @return array
     */
    #[AsCallback(table: 'tl_module', target: 'fields.pfs_schedule_item_template.options')]
    public function getTemplates(): array
    {
        return Controller::getTemplateGroup('pfs_schedule_item_');
    }

    /**
     * Return all categories as array.
     *
     * @return array
     */
    #[AsCallback(table: 'tl_module', target: 'fields.pfs_configs.options')]
    public function getConfigs(): array
    {
        $arrItems = [];
        $objItems = Database::getInstance()->execute('SELECT id, username FROM tl_pfs_user_config ORDER BY username');

        if (!$objItems || 0 === $objItems->count()) {
            return $arrItems;
        }

        while ($objItems->next()) {
            $arrItems[$objItems->id] = $objItems->username;
        }

        return $arrItems;
    }
}
