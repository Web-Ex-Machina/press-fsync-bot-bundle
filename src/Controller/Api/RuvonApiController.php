<?php

declare(strict_types=1);

namespace WEM\PressFsyncBotBundle\Controller\Api;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FrontendTemplate;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use WEM\PressFsyncBotBundle\Service\SteamService;

#[Route(
    '/api/psync/ruvon',
    name: 'wem_api_psync/ruvon',
    defaults: ['_scope' => 'frontend', '_token_check' => false]
)]
#[AsController]
class RuvonApiController
{
    public function __construct(
        private readonly ContaoFramework $framework, 
        private readonly SteamService $steam,
    ) {
        $this->framework->initialize();
    }

    #[Route("/")]
    public function view(Request $request): Response
    {
        return new Response('Hello World!');
    }

    #[Route("/wishlistcount")]
    public function displayWishlistCount(Request $request): Response
    {
        $count = $this->steam->getWishlistCount(SteamService::RUVON_STEAM_ID);

        if (null === $count) {
            return new Response('', Response::HTTP_BAD_GATEWAY);
        }

        $objTemplate = new FrontendTemplate('wishlist_ruvon');
        $objTemplate->number = $count;
        
        return new Response($objTemplate->parse());
    }
}