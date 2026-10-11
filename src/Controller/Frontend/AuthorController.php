<?php

declare(strict_types=1);

namespace WEM\PressFsyncBotBundle\Controller\Frontend;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Image\Studio\Studio;
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Contao\CoreBundle\Security\Authentication\Token\TokenChecker;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\Input;
use Contao\ModuleModel;
use Contao\NewsModel;
use Contao\StringUtil;
use Contao\UserModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Author block: displays the back end user who wrote the news being read
 * (picture, name, description and links, see the "author*" fields of tl_user).
 */
#[AsFrontendModule(
    AuthorController::TYPE,
    category: 'press_fsync',
    template: 'frontend_module/author',
)]
class AuthorController extends AbstractFrontendModuleController
{
    /**
     * Module type, stored in tl_module.type (do not change: existing modules use it).
     */
    public const TYPE = 'author';

    /**
     * Author of the news item being read (tl_module.authorArchiveType).
     */
    public const ARCHIVE_TYPE_NEWS = 'author_news';

    public function __construct(
        private readonly Studio $studio,
        private readonly InsertTagParser $insertTagParser,
        private readonly TokenChecker $tokenChecker,
    ) {
    }

    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        $user = $this->findUser();

        // Nothing to display
        if (null === $user) {
            return new Response();
        }

        $page = $this->parseUrl((string) $user->authorPage);

        $figure = null;

        if ($user->authorPicture) {
            $figure = $this->studio
                ->createFigureBuilder()
                ->from($this->toUuid($user->authorPicture))
                ->setSize(StringUtil::deserialize($model->imgSize))
                ->setLinkHref('' !== $page ? $page : null)
                ->setLinkAttribute('rel', 'nofollow')
                ->setLinkAttribute('title', (string) $user->name)
                ->buildIfResourceExists()
            ;
        }

        $template->set('author', [
            'name' => $user->name,
            'page' => $page,
            'description' => $user->authorDescription,
            'links' => $this->getLinks($user),
            'figure' => $figure,
        ]);
        $template->set('searchable', false);

        return $template->getResponse();
    }

    /**
     * Find the author according to the module configuration.
     */
    private function findUser(): UserModel|null
    {
        $alias = $this->getContaoAdapter(Input::class)->get('auto_item');

        if (!$alias) {
            return null;
        }

        $news = $this->getContaoAdapter(NewsModel::class)->findByIdOrAlias($alias);

        if (null === $news || !$news->author) {
            return null;
        }

        if (!$this->tokenChecker->isPreviewMode() && !$this->isPublished($news)) {
            return null;
        }

        return $this->getContaoAdapter(UserModel::class)->findById((int) $news->author);
    }

    private function isPublished(NewsModel $news): bool
    {
        $time = time();

        return $news->published
            && ('' === (string) $news->start || (int) $news->start <= $time)
            && ('' === (string) $news->stop || (int) $news->stop > $time);
    }

    /**
     * @return array<int, array{name: string, link: string, class: string}>
     */
    private function getLinks(UserModel $user): array
    {
        $links = StringUtil::deserialize($user->authorLinks);

        if (!\is_array($links)) {
            return [];
        }

        $items = [];

        foreach ($links as $link) {
            $url = $this->parseUrl((string) ($link['link'] ?? ''));
            $name = (string) ($link['name'] ?? '');

            if ('' === $url) {
                continue;
            }

            $items[] = [
                'name' => '' !== $name ? $name : $url,
                'link' => $url,
                'class' => StringUtil::generateAlias($name),
            ];
        }

        return $items;
    }

    /**
     * Resolve the insert tags of an URL (e.g. "{{link_url::12}}").
     */
    private function parseUrl(string $url): string
    {
        return trim($this->insertTagParser->replaceInline($url));
    }

    /**
     * Convert a binary UUID (as stored in the database) to its string form.
     */
    private function toUuid(string $value): string
    {
        return 16 === \strlen($value) ? StringUtil::binToUuid($value) : $value;
    }
}
