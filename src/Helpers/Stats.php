<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Helpers;

use Alnv\ProSearchIndexerContaoAdapterBundle\Stats\SearchStatsLogger;
use Contao\System;

/**
 * Static entry points of the front end search statistics (used by ElasticsearchController and StatController).
 * Everything is stored in tl_search_stats_log, see Stats\SearchStatsLogger.
 */
class Stats
{
    /**
     * @param array{keyword?: string, types?: array<mixed>} $arrKeywords result of Helpers\Keyword::setKeywords()
     */
    public static function setKeyword($arrKeywords, $intHits, $strSource = ''): void
    {
        static::logger()->log((string) ($arrKeywords['keyword'] ?? ''), (int) $intHits, (array) ($arrKeywords['types'] ?? []), (string) $strSource);
    }

    /**
     * @param array{keyword?: string} $arrKeywords result of Helpers\Keyword::setKeywords()
     */
    public static function setClick($arrKeywords, $strUrl): void
    {
        static::logger()->logClick((string) ($arrKeywords['keyword'] ?? ''), (string) $strUrl);
    }

    private static function logger(): SearchStatsLogger
    {
        return System::getContainer()->get(SearchStatsLogger::class);
    }
}
