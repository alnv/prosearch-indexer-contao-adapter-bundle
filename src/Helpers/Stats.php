<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Helpers;

use Contao\Database;
use Contao\StringUtil;

class Stats
{

    public static function setKeyword($arrKeywords, $intHits): void
    {

        $varStat = static::findStat($arrKeywords['keyword'], $arrKeywords['types']);

        if (!$varStat) {
            static::newStat($arrKeywords['keyword'], $arrKeywords['types'], $intHits);
            return;
        }

        static::updateStat($varStat, $intHits);
    }

    public static function setClick($arrKeywords, $strUrl): void
    {

        $arrStat = static::findStat($arrKeywords['keyword'], $arrKeywords['types']);

        if (!$arrStat) {
            return;
        }

        $intClicks = (int)$arrStat['clicks'] + 1;
        $arrUrls = StringUtil::deserialize($arrStat['urls'], true);
        if ($strUrl && !in_array($strUrl, $arrUrls)) {
            $arrUrls[] = $strUrl;
        }

        Database::getInstance()->prepare('UPDATE tl_search_stats %s WHERE id=?')->set([
            'tstamp' => time(),
            'clicks' => $intClicks,
            'urls' => serialize($arrUrls)
        ])->execute($arrStat['id']);
    }

    protected static function updateStat($arrState, $intHits): void
    {
        $intNewHits = (int)$arrState['hits'] + $intHits;
        $intCount = (int)$arrState['count'] + 1;

        Database::getInstance()->prepare('UPDATE tl_search_stats %s WHERE id=?')->set([
            'hits' => $intNewHits,
            'count' => $intCount,
            'tstamp' => time(),
        ])->execute($arrState['id']);
    }

    protected static function newStat($strKeyWord, $arrTypes, $intHits): void
    {
        Database::getInstance()->prepare('INSERT INTO tl_search_stats %s')->set([
            'types' => serialize($arrTypes),
            'keywords' => $strKeyWord,
            'urls' => serialize([]),
            'hits' => $intHits,
            'tstamp' => time(),
            'clicks' => 0,
            'count' => 1
        ])->execute();
    }

    protected static function findStat($strKeyWord, $arrTypes = []): bool|array
    {

        $objStat = Database::getInstance()->prepare('SELECT * FROM tl_search_stats WHERE `keywords`=?')->execute($strKeyWord);

        if (!$objStat->numRows) {

            return false;
        }

        while ($objStat->next()) {

            $arrStatTypes = StringUtil::deserialize($objStat->types, true);

            if (empty(array_diff($arrTypes, $arrStatTypes))) {

                return $objStat->row();
            }
        }

        return false;
    }
}