<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\MicroData;

use Contao\FrontendTemplate;

/**
 *
 */
class Person extends MicroData
{

    /**
     * @var string
     */
    protected string $type = 'Person';

    public bool $richSnippet = true;

    public function match($arrKeyWords): void
    {

        $blnMatch = false;
        $strQuery = $arrKeyWords['keyword'] ?? '';

        if (!$strQuery) {
            return;
        }

        foreach ($this->jsonLdScriptsData as $strKey => $varValue) {

            switch ($strKey) {
                case 'address':
                    if (isset($varValue['addressLocality']) && is_string($varValue['addressLocality']) && strpos($varValue['addressLocality'], $strQuery) !== false) {
                        $blnMatch = true;
                    }
                    if (isset($varValue['postalCode']) && is_string($varValue['postalCode']) && strpos($varValue['postalCode'], $strQuery) !== false) {
                        $blnMatch = true;
                    }
                    if (isset($varValue['streetAddress']) && is_string($varValue['streetAddress']) && strpos($varValue['streetAddress'], $strQuery) !== false) {
                        $blnMatch = true;
                    }
                    break;
                case 'email':
                case 'jobTitle':
                case 'telephone':
                case 'name':
                    if ($varValue && is_string($varValue) && strpos($varValue, $strQuery) !== false) {
                        $blnMatch = true;
                    }
                    break;
            }
        }

        $this->jsonLdScriptsData['_matched'] = $blnMatch;
    }

    public function generate(array $arrParentData = []): string
    {

        $arrData = $this->jsonLdScriptsData;
        $arrData['image'] = $this->getImage($arrData['image']);
        $arrData['parent'] = $arrParentData;

        $objTemplate = new FrontendTemplate('md_person');
        $objTemplate->setData($arrData);

        return $objTemplate->parse();
    }
}