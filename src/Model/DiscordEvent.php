<?php

declare(strict_types=1);

namespace WEM\PressFsyncBotBundle\Model;

use WEM\UtilsBundle\Model\Model;

/**
 * Reads and writes items.
 */
class DiscordEvent extends Model
{
    /**
     * Table name.
     *
     * @var string
     */
    protected static $strTable = 'tl_pfs_discord_event';

    /**
     * Default order column
     *
     * @var string
     */
    protected static $strOrderColumn = "tstamp ASC";

    /**
     * Generic statements format.
     *
     * @param string $strField    [Column to format]
     * @param mixed  $varValue    [Value to use]
     * @param string $strOperator [Operator to use, default "="]
     *
     * @return array
     */
    public static function formatStatement(string $strField, $varValue, string $strOperator = '='): array
    {
        try {
            $arrColumns = [];
            $t = static::$strTable;

            switch ($strField) {
                case 'twitch_events':
                    if (!\is_array($varValue)) {
                        $varValue = [$varValue];
                    }

                    $arrColumns[] = sprintf("$t.twitch_event IN(%s)", implode(",", $varValue));
                break;

                // Load parent
                default:
                    $arrColumns = array_merge($arrColumns, parent::formatStatement($strField, $varValue, $strOperator));
            }

            return $arrColumns;
        } catch (Exception $e) {
            throw $e;
        }
    }
}
