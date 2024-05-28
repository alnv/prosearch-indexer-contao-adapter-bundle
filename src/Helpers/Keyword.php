<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Helpers;

use Contao\StringUtil;

class Keyword
{

    protected Synonyms $objSynonyms;

    protected array $arrKeywordData = [];

    public function __construct()
    {
        $this->objSynonyms = new Synonyms();
    }

    public function setKeywords($strKeywords, $arrOptions = []): array
    {

        $strKeywords = str_replace('/', '\/', $strKeywords);
        $strToken = Text::tokenize($strKeywords);
        $strToken = strtolower($strToken);

        $arrTypes = $arrOptions['categories'] ?? [];

        $strSynonym = $this->objSynonyms->predict($strToken);
        $strQuery = $strSynonym;

        $arrTypes = array_unique($arrTypes);
        $arrTypes = array_filter($arrTypes);
        $arrTypes = array_filter($arrTypes, function ($strType) {
            return strtolower($strType);
        });

        return [
            'keyword' => $strKeywords,
            'token' => $strToken,
            'query' => $strQuery,
            'words' => array_filter(StringUtil::trimsplit(' |,', $strQuery)),
            'types' => $arrTypes,
            'synonym' => $strSynonym
        ];
    }

    public function convertSynonyms($arrWords): array
    {

        $arrReturn = [];

        foreach ($arrWords as $strWord) {

            if (!$strWord) {
                continue;
            }

            $strWord = strtolower($strWord);
            $arrReturn[] = $this->objSynonyms->predict($strWord);
        }

        return $arrReturn;
    }

    public function getKeywords(): array
    {
        return $this->arrKeywordData;
    }
}