<?php

declare(strict_types=1);

namespace WEM\PressFsyncBotBundle\Model;

use Contao\Date;
use Contao\Model\Collection;
use Contao\NewsModel;

/**
 * News queries filtered by author (back end users).
 */
class News extends NewsModel
{
    /**
     * Find published news items by their parent ID and authors.
     *
     * @param array     $arrPids    An array of news archive IDs
     * @param array     $arrAuthors An array of back end users IDs
     * @param bool|null $blnFeatured If true, return only featured news, if false, return only unfeatured news
     *
     * @return Collection|NewsModel[]|NewsModel|null
     */
    public static function findPublishedByPidsAndAuthors($arrPids, $arrAuthors, $blnFeatured = null, $intLimit = 0, $intOffset = 0, array $arrOptions = [])
    {
        if (empty($arrPids) || !\is_array($arrPids) || empty($arrAuthors) || !\is_array($arrAuthors)) {
            return null;
        }

        $arrColumns = static::buildColumns($arrPids, $arrAuthors, $blnFeatured, $arrOptions);
        $t = static::$strTable;

        if (!isset($arrOptions['order'])) {
            $arrOptions['order'] = "$t.date DESC";
        }

        $arrOptions['limit'] = $intLimit;
        $arrOptions['offset'] = $intOffset;

        return static::findBy($arrColumns, null, $arrOptions);
    }

    /**
     * Count published news items by their parent ID and authors.
     */
    public static function countPublishedByPidsAndAuthors($arrPids, $arrAuthors, $blnFeatured = null, array $arrOptions = []): int
    {
        if (empty($arrPids) || !\is_array($arrPids) || empty($arrAuthors) || !\is_array($arrAuthors)) {
            return 0;
        }

        return static::countBy(static::buildColumns($arrPids, $arrAuthors, $blnFeatured, $arrOptions), null, $arrOptions);
    }

    private static function buildColumns(array $arrPids, array $arrAuthors, bool|null $blnFeatured, array $arrOptions): array
    {
        $t = static::$strTable;

        $arrColumns = [
            "$t.pid IN(".implode(',', array_map('\intval', $arrPids)).')',
            "$t.author IN(".implode(',', array_map('\intval', $arrAuthors)).')',
        ];

        if (true === $blnFeatured) {
            $arrColumns[] = "$t.featured=1";
        } elseif (false === $blnFeatured) {
            $arrColumns[] = "$t.featured=0";
        }

        if (!static::isPreviewMode($arrOptions)) {
            $time = Date::floorToMinute();
            $arrColumns[] = "$t.published=1 AND ($t.start='' OR $t.start<=$time) AND ($t.stop='' OR $t.stop>$time)";
        }

        return $arrColumns;
    }
}
