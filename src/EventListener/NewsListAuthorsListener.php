<?php

declare(strict_types=1);

namespace WEM\PressFsyncBotBundle\EventListener;

use Contao\Module;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\Model\Collection;
use Contao\StringUtil;
use WEM\PressFsyncBotBundle\Model\News;

/**
 * Filter the news list module by author, according to the "pressf5_be_members" field of the module.
 */
class NewsListAuthorsListener
{
    #[AsHook('newsListCountItems')]
    public function onCountItems(array $newsArchives, bool|null $featuredOnly, Module $module): int|false
    {
        $authors = $this->getAuthors($module);

        if (null === $authors) {
            return false;
        }

        return News::countPublishedByPidsAndAuthors($newsArchives, $authors, $featuredOnly);
    }

    #[AsHook('newsListFetchItems')]
    public function onFetchItems(array $newsArchives, bool|null $featuredOnly, int $limit, int $offset, Module $module): Collection|false|null
    {
        $authors = $this->getAuthors($module);

        if (null === $authors) {
            return false;
        }

        $t = News::getTable();
        $order = '';

        if ('featured_first' === $module->news_featured) {
            $order .= "$t.featured DESC, ";
        }

        switch ($module->news_order) {
            case 'order_headline_asc':
                $order .= "$t.headline";
                break;

            case 'order_headline_desc':
                $order .= "$t.headline DESC";
                break;

            case 'order_random':
                $order .= 'RAND()';
                break;

            case 'order_date_asc':
                $order .= "$t.date";
                break;

            default:
                $order .= "$t.date DESC";
        }

        return News::findPublishedByPidsAndAuthors($newsArchives, $authors, $featuredOnly, $limit, $offset, ['order' => $order]);
    }

    /**
     * @return array<int>|null The selected authors, null if the module is not filtered by author
     */
    private function getAuthors(Module $module): array|null
    {
        $authors = StringUtil::deserialize($module->pressf5_be_members);

        if (!\is_array($authors) || [] === $authors) {
            return null;
        }

        return array_map('\intval', $authors);
    }
}
