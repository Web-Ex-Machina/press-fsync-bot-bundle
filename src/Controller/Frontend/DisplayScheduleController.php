<?php

declare(strict_types=1);

namespace WEM\PressFsyncBotBundle\Controller\Frontend;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Routing\ContentUrlGenerator;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\Config;
use Contao\Environment;
use Contao\FilesModel;
use Contao\FrontendTemplate;
use Contao\Input;
use Contao\Model\Collection;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\Pagination;
use Contao\System;
use DatePeriod;
use DateTime;
use DateInterval;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use WEM\PressFsyncBotBundle\Model\TwitchEvent;
use WEM\UtilsBundle\Classes\Encryption;
use WEM\UtilsBundle\Classes\StringUtil;

#[AsFrontendModule(
    DisplayScheduleController::TYPE, 
    category: 'press_fsync',
    template: 'mod_pfs_display_schedule'
)]
class DisplayScheduleController extends AbstractFrontendModuleController
{
    /**
     * Module name
     */
    public const TYPE = 'press_fsync_display_schedule';

    /**
     * List config.
     */
    protected array $config = [];

    /**
     * List limit.
     */
    protected int $limit = 0;

    /**
     * List offset.
     */
    protected int $offset = 0;

    /**
     * List options.
     */
    protected array $options = [];

    /**
     * List filters.
     */
    protected array $filters = [];

    /**
     * Current ModuleModel
     */
    protected ModuleModel $model;

    public function __construct(
        private readonly ContentUrlGenerator $contentUrlGenerator,
        private readonly Encryption $encryption,
    ) {
    }

    /**
     * Generate module response
     */
    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        $this->model = $model;
        $this->model->pids = StringUtil::deserialize($this->model->pfs_configs);

        // Return if there are no archives
        if (empty($this->model->pids) || !\is_array($this->model->pids)) {
            return new Response();
        }

        $template->items = [];
        $template->empty = $GLOBALS['TL_LANG']['PFS']['SCHEDULE']['empty'];

        // Add pids
        $this->config = [];

        if ($this->model->pids) {
            $this->config['users'] = $this->model->pids;
        }

        // Retrieve filters
        if ($this->model->pfs_filters) {
            $this->buildFilters();
        }

        if ('obs' === Input::get('view')) {
            $this->generateWidget();
        }

        if (!Input::get('nofilters')) {
            $template->filters = $this->filters;
        }

        // Get the total number of items
        $intTotal = TwitchEvent::countItems($this->config);

        if ($intTotal < 1) {
            return new Response();
        }

        $template->module_id = $this->model->id;

        global $objPage;
        $template->generateWidgetUrl = $this->contentUrlGenerator->generate($objPage, ['/generateWidgetUrlModal'], UrlGeneratorInterface::ABSOLUTE_URL);
        
        if ('list' === $this->model->pfs_schedule_mode) {
            $this->limit = 0;
            $this->offset = (int) $this->model->skipFirst;

            // Maximum number of items
            if (Input::get('nbitems')) {
                $this->limit = (int) Input::get('nbitems');
            } else if ($this->model->numberOfItems > 0) {
                $this->limit = $this->model->numberOfItems;
            }

            $nbGroupBy = $this->model->pfs_schedule_nbGroupsBy ?: 12;

            switch ($this->model->pfs_schedule_groupBy) {
                case 'year':
                    $template->period = new DatePeriod(new DateTime(), new DateInterval('P1Y'), $nbGroupBy);
                break;
                case 'month':
                    $template->period = new DatePeriod(new DateTime(), new DateInterval('P1M'), $nbGroupBy);
                break;
                case 'week':
                    $template->period = new DatePeriod(new DateTime(), new DateInterval('P1W'), $nbGroupBy);
                break;
                case 'day':
                    $template->period = new DatePeriod(new DateTime(), new DateInterval('P1D'), $nbGroupBy);
                break;
                default:
                    $template->period = null;
            }

            $template->groupBy = $this->model->pfs_schedule_groupBy;
            $template->pagination = $this->buildPagination($intTotal);
            $template->items = $this->buildList();
        } else if('calendar' === $this->model->pfs_schedule_mode) {
            $this->buildCalendar();
        }

        return $template->getResponse();
    }

    protected function buildCalendar()
    {
        $objDate = new Date();
        $start = new DateTime(Input::get('start') ?: date('Y-m-d', $objDate->monthBegin));
        $end = new DateTime(Input::get('end') ?: date('Y-m-d', $objDate->monthEnd));
        $arrDays = new DatePeriod($start, new DateInterval('P1D'), (int) $start->diff($end)->format("%r%a"));

        foreach ($arrDays as $day) {
            // Retrieve all events of the day

            // Parse them and store them

        }

        // Send all "cells" to template
    }

    protected function buildPagination(int $total): string
    {
        $total = $total - $this->offset;

        // Split the results
        if ($this->model->perPage > 0 && (!isset($this->limit) || $this->model->numberOfItems > $this->model->perPage)) {
			// Adjust the overall limit
            if (isset($this->limit)) {
                $total = min($this->limit, $total);
            }

			$param = 'page_n' . $this->model->id;

			try {
				$pagination = System::getContainer()
                    ->get('contao.pagination.factory')
                    ->create(new PaginationConfig($param, $total, $this->model->perPage));
			} catch (PageOutOfRangeException $e) {
				throw new PageNotFoundException('Page not found: ' . Environment::get('uri'), previous: $e);
			}

			// Set limit and offset
			$limit = $pagination->getPerPage();
			$offset += $pagination->getOffset();
			$skip = $this->model->skipFirst;

			// Overall limit
			if ($offset + $limit > $total + $skip) {
				$limit = $total + $skip - $offset;
			}

			// Add the pagination menu
			return new LegacyTemplatePaginationProxy(System::getContainer()->get('twig'), $pagination);
		}

        return '';
    }

    protected function buildList(): array
    {
        $objItems = TwitchEvent::findItems($this->config, ($this->limit ?: 0), ($this->offset ?: 0), $this->options);

        return null !== $objItems ? $this->parseItems($objItems) : [];
    }
    
    // @todo: move this to API
    protected function generateWidget(): void
    {
        $intTotal = TwitchEvent::countItems($this->config);

        if ($intTotal < 1) {
            die;
        }

        $objItems = TwitchEvent::findItems($this->config, ($this->limit ?: 0));

        if (null === $objItems) {
            die;
        }

        $objTemplate = new FrontendTemplate('mod_pfs_display_schedule_obs_widget');
        $this->model->pfs_schedule_item_template = 'pfs_schedule_item_widget';
        $this->model->pfs_schedule_groupBy = '';
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
        $arrFilters = StringUtil::deserialize($this->model->pfs_filters);

        if (in_array('pid', $arrFilters)) {
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
        }
        
        if (in_array('category', $arrFilters)) {
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
    }

    /**
     * Parse one or more items and return them as array.
     *
     * @param Model\Collection $objItems
     *
     * @return array
     */
    protected function parseItems(Collection $objItems): array
    {
        $limit = $objItems->count();

        if ($limit < 1) {
            return [];
        }

        $count = 0;
        $arrItems = [];

        while ($objItems->next()) {
            $strBuffer = $this->parseItem(
                $objItems->current(), 
                ((1 === ++$count) ? ' first' : '') . (($count === $limit) ? ' last' : '') . ((0 === ($count % 2)) ? ' odd' : ' even'), 
                $count
            );

            switch ($this->model->pfs_schedule_groupBy) {
                case 'year':
                    $arrItems[date('Y', (int) $objItems->start_time)][] = $strBuffer;
                break;
                case 'month':
                    $arrItems[date('Y-m', (int) $objItems->start_time)][] = $strBuffer;
                break;
                case 'week':
                    $arrItems[date('Y-m-W', (int) $objItems->start_time)][] = $strBuffer;
                break;
                case 'day':
                    $arrItems[date('Y-m-d', (int) $objItems->start_time)][] = $strBuffer;
                break;
                default:
                    $arrItems[] = $strBuffer;
            }
        }

        return $arrItems;
    }

    /**
     * Parse an item and return it as string.
     *
     * @param TwitchEvent $objItem
     * @param string    $strClass
     * @param int       $intCount
     *
     * @return string
     */
    protected function parseItem(TwitchEvent $objItem, string $strClass = '', int $intCount = 0): string
    {
        $objTemplate = new FrontendTemplate($this->model->pfs_schedule_item_template);
        $objTemplate->setData($objItem->row());

        if ('' !== $objItem->cssClass) {
            $strClass = ' ' . $objItem->cssClass.$strClass;
        }

        $objTemplate->class = $strClass;
        $objTemplate->count = $intCount;

        // Parse event date
        if ($objItem->start_time) {
            $objStartAt = new DateTime('@' . $objItem->start_time);
            $objTemplate->start_time = date('d/m (H\hi)', (int) $objItem->start_time);
        }

        if ($objItem->end_time) {
            $objEndAt = new DateTime('@' . $objItem->end_time);
        }

        if ($objStartAt && $objEndAt) {
            $objDuration = $objStartAt->diff($objEndAt);
            // @todo : handle event with duration less than one hour and more than 24 hours
            $objTemplate->duration = $objDuration->format('%hh%I');
        }

        // Retrieve user config
        $objConfig = $objItem->getRelated('user');

        $objTemplate->username = $objConfig->username;
        $objTemplate->url = sprintf(
            'https://www.twitch.tv/%s/',
            $this->encryption->decrypt_b64($objConfig->twitchUsername)
        );

        // Retrieve and parse the syncTwitchScheduleWithDiscordMessagesThumbnail
        if ($objConfig->syncTwitchScheduleWithDiscordMessagesThumbnail && $objFile = FilesModel::findByUuid($objConfig->syncTwitchScheduleWithDiscordMessagesThumbnail)) {
            $objTemplate->avatar = $objFile->path;
        }

        return $objTemplate->parse();
    }
}