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
}