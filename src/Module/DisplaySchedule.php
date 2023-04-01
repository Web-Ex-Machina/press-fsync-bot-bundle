<?php

declare(strict_types=1);

namespace WEM\PressFsyncBotBundle\Module;

use Contao\Config;
use Contao\Module;
use Contao\Input;
use Contao\PageModel;
use Contao\System;
use ContaoInput;
use Patchwork\Utf8;
use WEM\UtilsBundle\Classes\StringUtil;
use WEM\PressFsyncBotBundle\Model\TwitchEvent;
use WEM\PressFsyncBotBundle\Model\UserConfig;

class DisplaySchedule extends Module
{
    /**
     * List config.
     */
    protected $config = [];

    /**
     * List limit.
     */
    protected $limit = 0;

    /**
     * List offset.
     */
    protected $offset = 0;

    /**
     * List options.
     */
    protected $options = [];

    /**
     * List filters.
     */
    protected $filters = [];

    /**
     * Template.
     *
     * @var string
     */
    protected $strTemplate = 'mod_pfs_display_schedule';

    /**
     * Display a wildcard in the back end
     * @return string
     */
    public function generate()
    {
        if (TL_MODE === 'BE') {
            $objTemplate = new \BackendTemplate('be_wildcard');
            $objTemplate->wildcard = '### '.Utf8::strtoupper($GLOBALS['TL_LANG']['FMD']['press_fsync_display_schedule'][0]).' ###';
            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id='.$this->id;

            return $objTemplate->parse();
        }

        $this->pids = \StringUtil::deserialize($this->pfs_configs);

        // Return if there are no archives
        if (empty($this->pids) || !\is_array($this->pids)) {
            return '';
        }

        return parent::generate();
    }

    /**
     * Compile list.
     */
    protected function compile()
    {
        if ('generateWidgetUrlModal' === Input::get('auto_item')) {
            $objTemplate = new \FrontendTemplate('mod_pfs_modal_generate_widget');
            echo $objTemplate->parse();
            die;
        }

        global $objPage;
        $this->limit = null;
        $this->offset = (int) $this->skipFirst;

        // Maximum number of items
        if ($this->numberOfItems > 0) {
            $this->limit = $this->numberOfItems;
        }

        if (Input::get('nbitems')) {
            $this->limit = (int) Input::get('nbitems');
        }

        $this->Template->articles = [];
        $this->Template->empty = $GLOBALS['TL_LANG']['PFS']['SCHEDULE']['empty'];

        // Add pids
        $this->config = [];
        $this->config['start_time_after'] = time();

        // Retrieve filters
        $this->buildFilters();

        if ('obs' === Input::get('view')) {
            $this->generateWidget();
        }

        if (!Input::get('nofilters')) {
            $this->Template->filters = $this->filters;
        }

        // Get the total number of items
        $intTotal = TwitchEvent::countItems($this->config);

        if ($intTotal < 1) {
            return;
        }

        $total = $intTotal - $offset;

        // Split the results
        if ($this->perPage > 0 && (!isset($this->limit) || $this->numberOfItems > $this->perPage)) {
            // Adjust the overall limit
            if (isset($this->limit)) {
                $total = min($this->limit, $total);
            }

            // Get the current page
            $id = 'page_n'.$this->id;
            $page = Input::get($id) ?? 1;

            // Do not index or cache the page if the page number is outside the range
            if ($page < 1 || $page > max(ceil($total / $this->perPage), 1)) {
                throw new PageNotFoundException('Page not found: '.\Environment::get('uri'));
            }

            // Set limit and offset
            $this->limit = $this->perPage;
            $this->offset += (max($page, 1) - 1) * $this->perPage;
            $skip = (int) $this->skipFirst;

            // Overall limit
            if ($this->offset + $this->limit > $total + $skip) {
                $this->limit = $total + $skip - $this->offset;
            }

            // Add the pagination menu
            $objPagination = new \Pagination($total, $this->perPage, \Config::get('maxPaginationLinks'), $id);
            $this->Template->pagination = $objPagination->generate("\n  ");
        }

        $objItems = TwitchEvent::findItems($this->config, ($this->limit ?: 0), ($this->offset ?: 0), $this->options);

        // Add the articles
        if (null !== $objItems) {
            $this->Template->items = $this->parseItems($objItems);
        }

        $this->Template->module_id = $this->id;
        $this->Template->generateWidgetUrl = PageModel::findByPk($objPage->id)->getFrontendUrl('/generateWidgetUrlModal');
    }

    protected function generateWidget()
    {
        $intTotal = TwitchEvent::countItems($this->config);

        if ($intTotal < 1) {
            die;
        }

        $objItems = TwitchEvent::findItems($this->config, ($this->limit ?: 0));

        if (null === $objItems) {
            die;
        }

        $objTemplate = new \FrontendTemplate('mod_pfs_display_schedule_obs_widget');
        $this->pfs_schedule_item_template = 'pfs_schedule_item_widget';
        $objTemplate->items = $this->parseItems($objItems);
        echo $objTemplate->parse();
        die;
    }

    /**
     * Retrieve list filters.
     *
     * @return array [Array of available filters, parsed]
     */
    protected function buildFilters()
    {
        // User filter
        $arrOptions = [];
        foreach ($this->pids as $c) {
            $objConfig = UserConfig::findByPk($c);

            $arrOptions[] = [
                'value' => $c,
                'label' => $objConfig->username,
                'selected' => is_array(Input::get('users')) && in_array($c, Input::get('users')) ? true : false
            ];
        }

        $this->filters[] = [
            'type' => "select",
            'name' => "users[]",
            'label' => $GLOBALS['TL_LANG']['PFS']['SCHEDULE']['FILTERS']['users'],
            'placeholder' => $GGLOBALS['TL_LANG']['PFS']['SCHEDULE']['FILTERS']['usersPlaceholder'],
            'value' => Input::get('users') ?: '',
            'options' => $arrOptions,
            'multiple' => true,
        ];

        if ('' !== Input::get('users') && null !== Input::get('users')) {
            $this->config['users'] = Input::get('users');
        }

        // Category filter
        $arrOptions = [];
        $objOptions = TwitchEvent::findItemsGroupByOneField('category_name');
        if ($objOptions) {
            while ($objOptions->next()) {
                if (!$objOptions->category_name) {
                    continue;
                }

                $arrOptions[] = [
                    'value' => $objOptions->category_name,
                    'label' => $objOptions->category_name,
                    'selected' => Input::get('category') === $objOptions->category_name,
                ];
            }
        }

        $this->filters[] = [
            'type' => "select",
            'name' => "category",
            'label' => $GLOBALS['TL_LANG']['PFS']['SCHEDULE']['FILTERS']['category'],
            'placeholder' => $GLOBALS['TL_LANG']['PFS']['SCHEDULE']['FILTERS']['categoryPlaceholder'],
            'value' => Input::get('category') ?: '',
            'options' => $arrOptions
        ];

        if ('' !== Input::get('category') && null !== Input::get('category')) {
            $this->config['category_name'] = Input::get('category');
        }
    }

    /**
     * Parse one or more items and return them as array.
     *
     * @param Model\Collection $objItems
     * @param bool             $blnAddArchive
     *
     * @return array
     */
    protected function parseItems($objItems, $blnAddArchive = false)
    {
        $limit = $objItems->count();

        if ($limit < 1) {
            return [];
        }

        $count = 0;
        $arrArticles = [];

        while ($objItems->next()) {
            /** @var NewsModel $objArticle */
            $objArticle = $objItems->current();

            $arrArticles[] = $this->parseItem($objArticle, $blnAddArchive, ((1 === ++$count) ? ' first' : '').(($count === $limit) ? ' last' : '').((0 === ($count % 2)) ? ' odd' : ' even'), $count);
        }

        return $arrArticles;
    }

    /**
     * Parse an item and return it as string.
     *
     * @param NewsModel $objItem
     * @param bool      $blnAddArchive
     * @param string    $strClass
     * @param int       $intCount
     *
     * @return string
     */
    protected function parseItem($objItem, $blnAddArchive = false, $strClass = '', $intCount = 0)
    {
        $objTemplate = new \FrontendTemplate($this->pfs_schedule_item_template);
        $objTemplate->setData($objItem->row());

        if ('' !== $objItem->cssClass) {
            $strClass = ' '.$objItem->cssClass.$strClass;
        }

        $objTemplate->class = $strClass;
        $objTemplate->count = $intCount; // see #5708

        // Parse event date
        if ($objItem->start_time) {
            $objStartAt = new \DateTime('@' . $objItem->start_time);
            $objTemplate->start_time = date('d/m (H\hi)', (int) $objItem->start_time);
        }

        if ($objItem->end_time) {
            $objEndAt = new \DateTime('@' . $objItem->end_time);
        }

        if ($objStartAt && $objEndAt) {
            $objDuration = $objStartAt->diff($objEndAt);
            // @todo : handle event with duration less than one hour and more than 24 hours
            $objTemplate->duration = $objDuration->format('%hh%I');
        }

        // Retrieve user config
        $encryptionService = System::getContainer()->get('plenta.encryption');
        $objConfig = $objItem->getRelated('user');

        $objTemplate->username = $objConfig->username;
        $objTemplate->url = sprintf(
            'https://www.twitch.tv/%s/',
            $encryptionService->decrypt($objConfig->twitchUsername)
        );

        // Retrieve and parse the syncTwitchScheduleWithDiscordMessagesThumbnail
        if ($objConfig->syncTwitchScheduleWithDiscordMessagesThumbnail && $objFile = \FilesModel::findByUuid($objConfig->syncTwitchScheduleWithDiscordMessagesThumbnail)) {
            $objTemplate->avatar = $objFile->path;
        }

        return $objTemplate->parse();
    }
}
