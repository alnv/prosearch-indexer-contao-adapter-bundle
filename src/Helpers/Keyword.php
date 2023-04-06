<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Helpers;

use Contao\StringUtil;

class Keyword {

    protected $objSynonyms;

    protected array $arrKeywordData = [];

    public function __construct() {

        $this->objSynonyms = new Synonyms();
    }

    public function setKeywords($strKeywords, $arrOptions=[]) {

        $strToken = Text::tokenize($strKeywords);
        $strToken = strtolower($strToken);

        $arrTypes = $arrOptions['categories'] ?? [];
        $arrTypes = $this->convertSynonyms($arrTypes);

        $strSynonym = $this->objSynonyms->predict($strToken);
        $strQuery = $strSynonym;

        $arrTypes = array_unique($arrTypes);
        $arrTypes = array_filter($arrTypes);

        return [
            'keyword' => $strKeywords,
            'token' => $strToken,
            'query' => $strQuery,
            'words' => array_filter(StringUtil::trimsplit(' |,', $strQuery)),
            'types' => $arrTypes,
            'synonym' => $strSynonym
        ];
    }

    public function convertSynonyms($arrWords) {

        $arrReturn = [];

        foreach ($arrWords as $strWord) {
            $strWord = strtolower($strWord);
            $arrReturn[] = $this->objSynonyms->predict($strWord);
        }

        return $arrReturn;
    }

    public function getKeywords() {

        return $this->arrKeywordData;
    }
}