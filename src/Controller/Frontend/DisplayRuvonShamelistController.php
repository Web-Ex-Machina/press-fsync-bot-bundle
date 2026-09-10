<?php

declare(strict_types=1);

namespace WEM\PressFsyncBotBundle\Controller\Frontend;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\ModuleModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[AsFrontendModule(
    DisplayRuvonShamelistController::TYPE, 
    category: 'press_fsync',
    template: 'mod_pfs_shamelist_ruvon'
)]
class DisplayRuvonShamelistController extends AbstractFrontendModuleController
{
    /**
     * Module name
     */
    public const TYPE = 'press_shamelist_ruvon';

    /**
     * Generate module response
     */
    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        return $template->getResponse();
    }
}