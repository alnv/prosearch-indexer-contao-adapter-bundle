<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Helpers;

use Contao\StringUtil;

class Keyword
{

    protected Synonyms $objSynonyms;

    protected array $arrKeywordData = [];

    public function __construct(?Synonyms $synonyms = null)
    {
        $this->objSynonyms = $synonyms ?? new Synonyms();
    }

    public function setKeywords($strKeywords, $arrOptions = []): array
    {

        $strKeywords = \str_replace('/', '\/', (string) $strKeywords);
        $strToken = Text::tokenize($strKeywords);
        $strToken = \mb_strtolower($strToken);
        $arrTypes = $arrOptions['categories'] ?? [];
        $arrSynonyms = $this->objSynonyms->expand($strToken);

        // normalized category keys (the former filter callback did not change the values)
        $arrTypes = \array_map(static fn ($strType): string => \is_scalar($strType) ? Categories::normalize((string) $strType) : '', (array) $arrTypes);
        $arrTypes = \array_values(\array_unique(\array_filter($arrTypes, static fn (string $strType): bool => '' !== $strType)));

        return [
            'keyword' => $strKeywords,
            'token' => $strToken,
            'query' => $strToken,
            'words' => \array_values(\array_filter(StringUtil::trimsplit(' |,', $strToken))),
            'types' => $arrTypes,
            'synonym' => $arrSynonyms[0] ?? $strToken,
            'synonyms' => $arrSynonyms
        ];
    }

    public function convertSynonyms($arrWords): array
    {
        $arrReturn = [];

        foreach ($arrWords as $strWord) {
            if (!$strWord) {
                continue;
            }

            $strWord = \mb_strtolower($strWord);
            $arrReturn[] = $this->objSynonyms->predict($strWord);
        }

        return $arrReturn;
    }

    public function getKeywords(): array
    {
        return $this->arrKeywordData;
    }
}