<?php

declare(strict_types=1);

namespace WEM\PressFsyncBotBundle\Controller\Api;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FrontendTemplate;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use WEM\UtilsBundle\Classes\Encryption;
use WEM\UtilsBundle\Classes\StringUtil;

#[Route(
    '/api/psync',
    name: 'wem_api_psync',
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

    #[Route("/modal/generate-widget")]
    public function displayModalGenerateWidget(Request $request): Response
    {
        $objTemplate = new FrontendTemplate('mod_pfs_modal_generate_widget');
        return new Response($objTemplate->parse());
    }
}