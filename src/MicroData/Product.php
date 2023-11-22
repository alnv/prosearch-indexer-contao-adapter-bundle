<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\MicroData;

use Contao\FrontendTemplate;

/**
 *
 */
class Product extends MicroData
{

    /**
     * @var string
     */
    protected string $type = 'Product';

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
                case 'sku':
                case 'description':
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

        $objTemplate = new FrontendTemplate('md_product');
        $objTemplate->setData($arrData);

        return $objTemplate->parse();
    }
}