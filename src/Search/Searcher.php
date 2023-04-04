<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Search;

use Symfony\Component\DomCrawler\Crawler;

abstract class Searcher
{

    /**
     * @var int
     */
    protected int $indicesId = 0;

    /**
     * @var Crawler
     */
    protected Crawler $objCrawler;

    /**
     * @return int
     */
    public function getIndicesId(): int
    {
        return $this->indicesId;
    }

    /**
     * @param $strContent
     * @return array|string|string[]
     */
    public function parseContent($strContent)
    {

        $strContent = str_replace(["\n", "\r", "\t", '&#160;', '&nbsp;', '&shy;'], [' ', ' ', ' ', ' ', ' ', ''], $strContent);

        while (($intStart = strpos($strContent, '<script')) !== false) {
            if (($intEnd = strpos($strContent, '</script>', $intStart)) !== false) {
                $strContent = substr($strContent, 0, $intStart) . substr($strContent, $intEnd + 9);
            } else {
                break;
            }
        }

        while (($intStart = strpos($strContent, '<style')) !== false) {
            if (($intEnd = strpos($strContent, '</style>', $intStart)) !== false) {
                $strContent = substr($strContent, 0, $intStart) . substr($strContent, $intEnd + 8);
            } else {
                break;
            }
        }

        while (($intStart = strpos($strContent, '<!-- indexer::stop -->')) !== false) {
            if (($intEnd = strpos($strContent, '<!-- indexer::continue -->', $intStart)) !== false) {
                $intCurrent = $intStart;

                while (($intNested = strpos($strContent, '<!-- indexer::stop -->', $intCurrent + 22)) !== false && $intNested < $intEnd) {
                    if (($intNewEnd = strpos($strContent, '<!-- indexer::continue -->', $intEnd + 26)) !== false) {
                        $intEnd = $intNewEnd;
                        $intCurrent = $intNested;
                    } else {
                        break;
                    }
                }

                $strContent = substr($strContent, 0, $intStart) . substr($strContent, $intEnd + 26);
            } else {
                break;
            }
        }

        $arrMatches = [];
        preg_match('/<\/head>/', $strContent, $arrMatches, PREG_OFFSET_CAPTURE);
        $intOffset = \strlen($arrMatches[0][0]) + $arrMatches[0][1];
        $strBody = substr($strContent, $intOffset);

        return strip_tags($strBody);
    }
}