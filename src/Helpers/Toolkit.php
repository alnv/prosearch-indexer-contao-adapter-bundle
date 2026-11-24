<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Helpers;

use Contao\StringUtil;

class Toolkit
{
    public static function parseDidYouMeanArray($strSearchQuery, $arrDidYouMean): array
    {
        if (empty($arrDidYouMean)) {
            return [];
        }

        if (($intIndex = \array_search(\strtolower($strSearchQuery), \array_map('strtolower', $arrDidYouMean))) !== false) {
            unset($arrDidYouMean[$intIndex]);

            $arrDidYouMean = \array_filter($arrDidYouMean);
        }

        return $arrDidYouMean;
    }
}