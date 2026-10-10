<?php

declare(strict_types=1);

namespace WEM\PressFsyncBotBundle\Controller\Frontend;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\ModuleModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use WEM\PressFsyncBotBundle\Service\SteamService;

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

    public function __construct(
        private readonly SteamService $steam,
    ) {
    }

    /**
     * Generate module response
     */
    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        $steamid = trim((string) $request->request->get('steamid', ''));

        if ('' !== $steamid) {
            $nbGamesPlayer = $this->steam->getWishlistCount($steamid);
            $nbGamesRuvon = $this->steam->getWishlistCount(SteamService::RUVON_STEAM_ID);

            if (null === $nbGamesPlayer || !$nbGamesRuvon) {
                // Invalid SteamID, private wishlist or Steam not answering
                $template->error = true;
            } else {
                $template->steamid = $steamid;
                $template->score = $nbGamesPlayer;
                $template->scoreRuvon = $nbGamesRuvon;
                $template->diff = $nbGamesPlayer / $nbGamesRuvon * 100;
            }
        }

        return $template->getResponse();
    }
}
