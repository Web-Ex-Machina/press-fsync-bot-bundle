<?php

declare(strict_types=1);

namespace WEM\PressFsyncBotBundle\Controller\Frontend;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\Input;
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
        if (Input::post('steamid')) {
            $nbGamesPlayer = $this->getNumberOfGames(Input::post('steamid'));
            $nbGamesRuvon = $this->getNumberOfGames('76561198084359321');

            $template->steamid = Input::post('steamid');
            $template->score = $nbGamesPlayer;
            $template->scoreRuvon = $nbGamesRuvon;
            $template->diff = $nbGamesPlayer / $nbGamesRuvon * 100;
        }

        return $template->getResponse();
    }

    protected function getNumberOfGames(string $steamid): int 
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.steampowered.com/IWishlistService/GetWishlistItemCount/v1/?steamid=' . $steamid);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $r = curl_exec($ch);
        $r = json_decode($r);
        curl_close($ch);

        return $r->response->count;
    }
}