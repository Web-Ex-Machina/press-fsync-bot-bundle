<?php

declare(strict_types=1);

namespace WEM\PressFsyncBotBundle\Controller\Backend;

use Contao\CoreBundle\Exception\RedirectResponseException;

/**
 * Requires a `RequestStack $requestStack` property on the using class.
 */
trait RedirectsToRefererTrait
{
    /**
     * Redirect to the current back end URL, without the "key" parameter that triggered the action.
     */
    private function redirectToReferer(): never
    {
        $uri = $this->requestStack->getCurrentRequest()?->getRequestUri() ?? '';

        throw new RedirectResponseException(preg_replace('/&(amp;)?(key)=[^&]*/', '', $uri));
    }
}
