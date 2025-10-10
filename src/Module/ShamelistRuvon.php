<?php

declare(strict_types=1);

namespace WEM\PressFsyncBotBundle\Module;

use Contao\Config;
use Contao\Date;
use Contao\Module;
use Contao\Input;
use Contao\PageModel;
use Contao\System;
use ContaoInput;
use DateInterval;
use DatePeriod;
use DateTime;
use WEM\UtilsBundle\Classes\StringUtil;
use WEM\PressFsyncBotBundle\Model\TwitchEvent;
use WEM\PressFsyncBotBundle\Model\UserConfig;

class ShamelistRuvon extends Module
{
    /**
     * Template.
     *
     * @var string
     */
    protected $strTemplate = 'mod_pfs_shamelist_ruvon';

    /**
     * Display a wildcard in the back end
     * @return string
     */
    public function generate()
    {
        if (TL_MODE === 'BE') {
            $objTemplate = new \BackendTemplate('be_wildcard');
            $objTemplate->wildcard = '### Shamelist Ruvon ###';
            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id='.$this->id;

            return $objTemplate->parse();
        }

        return parent::generate();
    }

    /**
     * Compile list.
     */
    protected function compile()
    {
        if (Input::post('steamid')) {
            $nbGamesPlayer = $this->getNumberOfGames(Input::post('steamid'));
            $nbGamesRuvon = $this->getNumberOfGames('76561198084359321');

            $this->Template->steamid = Input::post('steamid');
            $this->Template->score = $nbGamesPlayer;
            $this->Template->scoreRuvon = $nbGamesRuvon;
            $this->Template->diff = $nbGamesPlayer / $nbGamesRuvon * 100;
        }
    }

    protected function getNumberOfGames($steamid) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.steampowered.com/IWishlistService/GetWishlistItemCount/v1/?steamid=' . $steamid);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $r = curl_exec($ch);
        $r = json_decode($r);
        curl_close($ch);

        return $r->response->count;
    }
}
