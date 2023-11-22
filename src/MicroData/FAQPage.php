<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\MicroData;

use Contao\StringUtil;

/**
 *
 */
class FAQPage extends MicroData
{

    /**
     * @var string
     */
    protected string $type = 'FAQPage';

    public bool $globalRichSnippet = true;

    public function match($arrKeyWords): void
    {

        $blnGlobalMatch = false;
        $strQuery = $arrKeyWords['keyword'] ?? '';

        if (isset($this->jsonLdScriptsData['mainEntity']) && is_array($this->jsonLdScriptsData['mainEntity'])) {

            foreach ($this->jsonLdScriptsData['mainEntity'] as $index => $arrEntity) {

                $blnMatch = false;

                foreach ($arrEntity as $strKey => $varValue) {

                    switch ($strKey) {
                        case 'name':
                            if ($varValue && is_string($varValue) && strpos($varValue, $strQuery) !== false) {
                                $blnMatch = true;
                            }
                            break;
                        case 'acceptedAnswer':
                            if (isset($varValue['text']) && is_string($varValue['text']) && strpos($varValue['text'], $strQuery) !== false) {
                                $blnMatch = true;
                            }
                            break;
                    }
                }

                if (!$blnGlobalMatch && $blnMatch) {
                    $blnGlobalMatch = true;
                }

                $this->jsonLdScriptsData['mainEntity'][$index]['acceptedAnswer']['text'] = StringUtil::decodeEntities($this->jsonLdScriptsData['mainEntity'][$index]['acceptedAnswer']['text']);
                $this->jsonLdScriptsData['mainEntity'][$index]['_matched'] = $blnMatch;
            }
        }

        $this->jsonLdScriptsData['_matched'] = $blnGlobalMatch;
    }
}