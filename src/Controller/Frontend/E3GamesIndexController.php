<?php

declare(strict_types=1);

namespace WEM\PressFsyncBotBundle\Controller\Frontend;

use Contao\ContentModel;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Index ("Sommaire") of the E3 games: lists the headlines of the "rsce_e3gameblock" elements
 * located between the parent start and this element, with a link to their anchor.
 */
#[AsContentElement(
    E3GamesIndexController::TYPE,
    category: 'press_fsync',
    template: 'content_element/e3_games_index',
)]
class E3GamesIndexController extends AbstractContentElementController
{
    /**
     * Element type, stored in tl_content.type (do not change: existing elements use it).
     */
    public const TYPE = 'e3GamesIndex';

    /**
     * Type of the elements to list.
     */
    public const GAME_BLOCK_TYPE = 'rsce_e3gameblock';

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        if ($this->isBackendScope($request)) {
            return new Response('Sommaire');
        }

        // Get all the content elements of the parent, from this one
        $elements = $this->connection->fetchAllAssociative(
            'SELECT type, headline FROM tl_content WHERE pid = :pid AND ptable = :ptable AND sorting > :sorting ORDER BY sorting',
            [
                'pid' => (int) $model->pid,
                'ptable' => $model->ptable ?: 'tl_article',
                'sorting' => (int) $model->sorting,
            ],
        );

        $items = [];

        foreach ($elements as $element) {
            // The next index ends this one
            if (self::TYPE === $element['type']) {
                break;
            }

            if (self::GAME_BLOCK_TYPE !== $element['type']) {
                continue;
            }

            $headline = StringUtil::deserialize($element['headline']);
            $label = \is_array($headline) ? (string) ($headline['value'] ?? '') : '';

            if ('' === $label) {
                continue;
            }

            $items[] = [
                'label' => $label,
                'anchor' => StringUtil::generateAlias($label),
            ];
        }

        // Nothing to display
        if ([] === $items) {
            return new Response();
        }

        $template->set('items', $items);
        $template->set('url', $request->getRequestUri());

        return $template->getResponse();
    }
}
