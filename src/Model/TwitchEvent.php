<?php

declare(strict_types=1);

namespace WEM\PressFsyncBotBundle\Model;

/**
 * Reads and writes items.
 */
class TwitchEvent extends \WEM\UtilsBundle\Model\Model
{
    /**
     * Table name.
     *
     * @var string
     */
    protected static $strTable = 'tl_pfs_twitch_event';

    /**
     * Default order column
     *
     * @var string
     */
    protected static $strOrderColumn = "start_time ASC";

    /**
     * Generic statements format.
     *
     * @param string $strField    [Column to format]
     * @param mixed  $varValue    [Value to use]
     * @param string $strOperator [Operator to use, default "="]
     *
     * @return array
     */
    public static function formatStatement($strField, $varValue, $strOperator = '=')
    {
        try {
            $arrColumns = [];
            $t = static::$strTable;

            switch ($strField) {
                case 'users':
                    if (!\is_array($varValue)) {
                        $varValue = [$varValue];
                    }

                    $arrColumns[] = sprintf("$t.user IN(%s)", implode(",", $varValue));
                break;

                case 'start_time_after':
                    $arrColumns[] = sprintf("$t.start_time >= %s", $varValue);
                break;

                case 'start_time_before':
                    $arrColumns[] = sprintf("$t.start_time <= %s", $varValue);
                break;

                case 'notcanceled':
                    if (true === $varValue) {
                        $arrColumns[] = sprintf("$t.canceled_until IS NULL");
                    }
                break;

                case 'search':
                    $strKeywords = implode('|', $varValue);
                    $arrColumns[] = "($t.title REGEXP '$strKeywords' OR $t.category_name REGEXP '$strKeywords')";
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
