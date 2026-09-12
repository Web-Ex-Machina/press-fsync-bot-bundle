<?php

declare(strict_types=1);

namespace WEM\PressFsyncBotBundle\Controller\Api;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Environment;
use Contao\FilesModel;
use Contao\FrontendTemplate;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use WEM\PressFsyncBotBundle\Model\TwitchEvent;
use WEM\UtilsBundle\Classes\Encryption;
use WEM\UtilsBundle\Classes\StringUtil;

#[Route(
    '/api/psync/schedule',
    name: 'wem_api_psync_schedule',
    defaults: ['_scope' => 'frontend', '_token_check' => false]
)]
#[AsController]
class ScheduleApiController
{
    public function __construct(
        private readonly ContaoFramework $framework, 
        private readonly Encryption $encryption,
    ) {
        $this->framework->initialize();
    }

    #[Route("/")]
    public function view(Request $request): Response
    {
        return new Response('Hello World!');
    }

    #[Route("/get")]
    public function getSchedule(Request $request): Response
    {
        $c = ['notcanceled' => true];

        if ($request->query->has('keepCurrent')) {
            $c['start_time_after'] = time();
        }

        $limit = $request->query->has('limit') ? (int) $request->query->get('limit') : 3;
        $template =  $request->query->has('template') ? $request->query->get('template') : 'default';

        $objItems = TwitchEvent::findItems($c, $limit);
        $arrEvents = [];

        if (!$objItems) {
           return new Response();
        }

        $objTemplate = new FrontendTemplate('schedule_' . $template);

        while ($objItems->next()) {
            $u = $objItems->getRelated('user');
            $logo = FilesModel::findByUuid($u->syncTwitchScheduleWithDiscordMessagesThumbnail);

            $e = $objItems->row();
            $e['logo'] = $logo ? Environment::get('base') . '/' . $logo->path : null;
            $e['username'] = $this->encryption->decrypt_b64($u->twitchUsername);
            $e['url'] = 'https://www.twitch.tv/' . $this->encryption->decrypt_b64($u->twitchUsername);
            $e['datetime'] = date('d/m/Y à H:i', (int) $objItems->start_time);
            $e['date'] = date('d/m/Y', (int) $objItems->start_time);
            $e['date_simple'] = date('d/m', (int) $objItems->start_time);
            $e['time'] = date('H\hi', (int) $objItems->start_time);

            $arrEvents[] = $e;
        }

        $objTemplate->items = $arrEvents;
        return new Response($objTemplate->parse());
    }

    #[Route("/modal/generate-widget")]
    public function displayModalGenerateWidget(Request $request): Response
    {
        $objTemplate = new FrontendTemplate('mod_pfs_modal_generate_widget');
        return new Response($objTemplate->parse());
    }
}