<?php

declare(strict_types=1);

namespace WEM\PressFsyncBotBundle\DataContainer;

class ModuleContainer extends \Backend
{
    /**
     * Return all templates as array.
     *
     * @return array
     */
    public function getTemplates()
    {
        return $this->getTemplateGroup('pfs_schedule_item_');
    }

    /**
     * Return all categories as array.
     *
     * @return array
     */
    public function getConfigs()
    {
        $arrItems = [];
        $objItems = $this->Database->execute('SELECT id, username FROM tl_pfs_user_config ORDER BY username');

        if (!$objItems || 0 === $objItems->count()) {
            return $arrItems;
        }

        while ($objItems->next()) {
            $arrItems[$objItems->id] = $objItems->username;
        }

        return $arrItems;
    }
}
