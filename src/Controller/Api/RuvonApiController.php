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
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.steampowered.com/IWishlistService/GetWishlistItemCount/v1/?steamid=76561198084359321');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $r = curl_exec($ch);
        $r = json_decode($r);
        curl_close($ch);

        $objTemplate = new FrontendTemplate('wishlist_ruvon');
        $objTemplate->number = $r->response->count;
        
        return new Response($objTemplate->parse());
    }
}