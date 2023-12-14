<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Helpers;

use Contao\Database;
use Contao\StringUtil;

class Synonyms
{

    protected array $arrSynonyms = [];

    public function __construct()
    {

        $objSynonyms = Database::getInstance()->prepare('SELECT * FROM tl_synonyms WHERE disable!=?')->execute('1');

        while ($objSynonyms->next()) {

            $strKeyword = Text::tokenize($objSynonyms->keyword);

            if (!$strKeyword) {
                continue;
            }

            $arrSynonyms = [];
            foreach (StringUtil::deserialize($objSynonyms->synonyms, true) as $strSynonym) {

                if (!$strSynonym) {
                    continue;
                }

                $arrSynonyms[] = Text::tokenize($strSynonym);
            }

            if (empty($arrSynonyms)) {
                continue;
            }

            $this->arrSynonyms[$strKeyword] = $arrSynonyms;
        }
    }

    public function predict($strQuery)
    {

        foreach ($this->arrSynonyms as $strKeyWord => $arrSynonyms) {

            foreach ($arrSynonyms as $strSynonym) {

                similar_text($strQuery, $strSynonym, $intPercent);

                if ($intPercent >= 75) {
                    return $strKeyWord;
                }
            }
        }

        return $strQuery;
    }
}