<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Helpers;

use Contao\StringUtil;

class Toolkit
{

    public static function parseDocumentIndex($varDocumentIndex): array
    {

        $arrDocumentsData = StringUtil::deserialize($varDocumentIndex, true);

        if (empty($arrDocumentsData)) {
            $arrDocumentsData = [
                'text' => [],
                'strong' => [],
                'h1' => [],
                'h2' => [],
                'h3' => [],
                'h4' => [],
                'h5' => [],
                'h6' => [],
                'document' => []
            ];
        }

        return $arrDocumentsData;
    }

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