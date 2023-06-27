<?php

namespace WEM\PressFsyncBotBundle\Backend;

use Contao\Backend;
use Contao\Environment;
use Contao\Message;
use Contao\Input;
use Contao\StringUtil;
use WEM\PressFsyncBotBundle\Classes\Rawg;

class RawgCallback extends Backend
{
    public function debugRawgApi()
    {
        if (Input::get('key') != 'debugRawgApi') {
            return '';
        }

        // Catch API call
        if (Input::post('FORM_SUBMIT') == 'tl_pfs_rawg_api_debug') {
            $arrGame = Rawg::getGame(\Input::post('gameAlias'));

            dump($arrGame);
        }

        // Return form
        return '
<div id="tl_buttons">
<a href="' . StringUtil::ampersand(str_replace('&key=import', '', Environment::get('request'))) . '" class="header_back" title="' . StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['backBTTitle']) . '" accesskey="b">' . $GLOBALS['TL_LANG']['MSC']['backBT'] . '</a>
</div>
' . Message::generate() . '
<form id="tl_pfs_rawg_api_debug" class="tl_form tl_edit_form" method="post">
<div class="tl_formbody_edit">
<input type="hidden" name="FORM_SUBMIT" value="tl_pfs_rawg_api_debug">
<input type="hidden" name="REQUEST_TOKEN" value="' . REQUEST_TOKEN . '">

<fieldset class="tl_tbox nolegend">
  <div class="widget w50">
    <h3><label for="gameAlias">Game alias</label></h3>
    <input type="text" id="gameAlias" name="gameAlias" />
    <p class="tl_help tl_tip">Enter the RAWG Game Alias</p>
  </div>
</fieldset>

</div>

<div class="tl_formbody_submit">

<div class="tl_submit_container">
  <button type="submit" name="save" id="save" class="tl_submit" accesskey="s">Try API</button>
</div>

</div>
</form>';
    }
}
