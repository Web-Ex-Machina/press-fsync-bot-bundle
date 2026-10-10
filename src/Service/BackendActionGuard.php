<?php

declare(strict_types=1);

namespace WEM\PressFsyncBotBundle\Service;

use Contao\CoreBundle\Exception\RedirectResponseException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Helper for the back end actions triggered by a link (`key=...`).
 */
class BackendActionGuard
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly UrlGeneratorInterface $urlGenerator,
        #[Autowire(service: 'contao.csrf.token_manager')]
        private readonly CsrfTokenManagerInterface $tokenManager,
        #[Autowire('%contao.csrf_token_name%')]
        private readonly string $tokenName,
    ) {
    }

    /**
     * Check the request token of the link, like Contao does for its own operations.
     * Without a valid one, the user is sent to the back end confirmation page.
     * (POST requests are already checked by the Contao request token listener.)
     */
    public function assertValidRequestToken(): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request || !$request->isMethodSafe()) {
            return;
        }

        $token = $request->query->getString('rt');

        if ('' !== $token && $this->tokenManager->isTokenValid(new CsrfToken($this->tokenName, $token))) {
            return;
        }

        $request->getSession()->set('INVALID_TOKEN_URL', $request->getRequestUri());

        throw new RedirectResponseException($this->urlGenerator->generate('contao_backend_confirm'));
    }

    /**
     * Redirect to the current back end URL, without the "key" parameter that triggered the action.
     */
    public function redirectToReferer(): never
    {
        $uri = $this->requestStack->getCurrentRequest()?->getRequestUri() ?? '';

        throw new RedirectResponseException(preg_replace('/&(amp;)?(key)=[^&]*/', '', $uri));
    }
}
