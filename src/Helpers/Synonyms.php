<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Helpers;

use Contao\Database;

class Synonyms {

    protected $naive_bayes;

    public function __construct() {

        $objSynonyms = Database::getInstance()->prepare('SELECT * FROM tl_synonyms WHERE disable!=?')->execute('1');
        $this->naive_bayes = naive_bayes();

        while ($objSynonyms->next()) {

            $strKeyword = Text::tokenize($objSynonyms->keyword);

            if (!$strKeyword) {
                continue;
            }

            $arrSynonyms = [];
            foreach (\StringUtil::deserialize($objSynonyms->synonyms, true) as $strSynonym) {
                $arrSynonyms[] = Text::tokenize($strSynonym);
            }

            if (empty($arrSynonyms)) {
                continue;
            }

            $this->naive_bayes->train($strKeyword, tokenize(implode(' ', $arrSynonyms)));
        }
    }

    public function predict($strKeyword) {

        $arrPredicts = $this->naive_bayes->predict(tokenize($strKeyword));

        if (empty($arrPredicts)) {
            return $strKeyword;
        }

        foreach ($arrPredicts as $strSynonym => $intPercent) {

            if (($intPercent*100) > 70) {
                return $strSynonym;
            }
        }

        return $strKeyword;
    }
}