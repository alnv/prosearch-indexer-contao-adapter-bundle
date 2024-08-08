<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Helpers;

use Contao\System;
use Contao\Database;
use Contao\StringUtil;
use Contao\BackendUser;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;

class Stats
{

    public static function setKeyword($arrKeywords, $intHits, $strSource = ''): void
    {

        $varStat = static::findStat($arrKeywords['keyword'], $arrKeywords['types']);
        if (!$varStat) {
            $varStat = static::newStat($arrKeywords['keyword'], $arrKeywords['types'], $intHits);
        } else {
            static::updateStat($varStat, $intHits);
        }

        static::setSource($varStat, $strSource);
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

    protected static function setSource($arrStat, $strSource): void
    {

        $blnNew = true;
        $strSource = strtok($strSource, '?');
        $strKeyword = $arrStat['keywords'] ?? '';

        if (!$strKeyword || !$strSource) {
            return;
        }

        $arrSources = StringUtil::deserialize($arrStat['source'], true);
        foreach ($arrSources as $intIndex => $arrSource) {
            if ($strSource == $arrSource['source']) {
                $arrSources[$intIndex]['click'] = (int)$arrSource['click'] + 1;
                $blnNew = false;
            }
        }

        if ($blnNew) {
            $arrSources[] = [
                'source' => $strSource,
                'click' => 1
            ];
        }

        Database::getInstance()->prepare('UPDATE tl_search_stats %s WHERE id=?')->set([
            'tstamp' => time(),
            'source' => $arrSources,
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

    protected static function newStat($strKeyWord, $arrTypes, $intHits): array
    {

        $arrSet = [
            'types' => serialize($arrTypes),
            'keywords' => $strKeyWord,
            'urls' => serialize([]),
            'hits' => $intHits,
            'tstamp' => time(),
            'clicks' => 0,
            'count' => 1
        ];

        $objInsert = Database::getInstance()->prepare('INSERT INTO tl_search_stats %s')->set($arrSet)->execute();

        $arrSet['id'] = $objInsert->insertId;

        return $arrSet;
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

    public static function reset(): void
    {
        Database::getInstance()->prepare('DELETE FROM tl_search_stats')->execute();
    }

    public static function export(): void
    {

        System::loadLanguageFile('tl_search_stats');

        $objSpreadsheet = new Spreadsheet();
        $objSpreadsheet->getProperties()
            ->setTitle('ProSearch Statistik')
            ->setCreator('Contao CMS')
            ->setLastModifiedBy(BackendUser::getInstance()->email);
        $objSheet = $objSpreadsheet->getActiveSheet();

        $numRows = 1;
        $objStats = Database::getInstance()->prepare('SELECT * FROM tl_search_stats ORDER BY count DESC')->execute();

        $arrStats = [];
        while ($objStats->next()) {

            if (!$objStats->keywords) {
                continue;
            }

            $arrUrls = StringUtil::deserialize($objStats->urls, true);
            $arrTypes = StringUtil::deserialize($objStats->types, true);
            $arrSources = [];
            foreach (StringUtil::deserialize($objStats->source, true) as $arrSource) {
                $arrSources[] = $arrSource['source'] . ' : ' . (int) $arrSource['click'];
            }

            $arrStat = [];
            $arrStat[$GLOBALS['TL_LANG']['tl_search_stats']['keywords'][0] ?? ''] = $objStats->keywords;
            $arrStat[$GLOBALS['TL_LANG']['tl_search_stats']['types'][0] ?? ''] = implode(',', $arrTypes);
            $arrStat[$GLOBALS['TL_LANG']['tl_search_stats']['count'][0] ?? ''] = (int) $objStats->count;
            $arrStat[$GLOBALS['TL_LANG']['tl_search_stats']['hits'][0] ?? ''] = (int) $objStats->hits;
            $arrStat[$GLOBALS['TL_LANG']['tl_search_stats']['clicks'][0] ?? ''] = (int) $objStats->clicks;
            $arrStat[$GLOBALS['TL_LANG']['tl_search_stats']['urls'][0] ?? ''] = implode(PHP_EOL, $arrUrls);
            $arrStat[$GLOBALS['TL_LANG']['tl_search_stats']['source'][0] ?? ''] = implode(PHP_EOL, $arrSources);

            $arrStats[] = $arrStat;
        }

        $arrFields = array_keys(($arrStats[0] ?? []));

        foreach ($arrFields as $numCols => $strField) {
            $objSheet->setCellValue([$numCols + 1, $numRows], $strField);
        }

        $numRows++;

        foreach ($arrStats as $arrMember) {
            $numCols = 1;
            foreach ($arrMember as $strValue) {
                $objSheet->setCellValue([$numCols, $numRows], $strValue);
                $numCols++;
            }
            $numRows++;
        }

        $objXls = new Csv($objSpreadsheet);

        $objXls->setDelimiter(';');
        $objXls->setEnclosure('"');

        header('Content-Disposition: attachment;filename="export-' . uniqid() . '.csv"');
        header('Cache-Control: max-age=0');
        header('Content-Type: application/vnd.ms-excel');
        $objXls->save('php://output');
        exit;
    }
}