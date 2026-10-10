<?php

declare(strict_types=1);

namespace WEM\PressFsyncBotBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\Controller;
use Doctrine\DBAL\Connection;

class ModuleContainer
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

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
        return $this->connection->fetchAllKeyValue('SELECT id, username FROM tl_pfs_user_config ORDER BY username');
    }
}
