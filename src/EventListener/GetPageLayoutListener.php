<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\EventListener;

use Contao\LayoutModel;
use Contao\PageModel;
use Contao\PageRegular;


class GetPageLayoutListener
{

    public function getPageLayout(PageModel $pageModel, LayoutModel $layout, PageRegular $pageRegular): void
    {
        if ($pageModel->psSearchCategory) {
            $strCategories = str_replace(' ', '', $pageModel->psSearchCategory);
            $GLOBALS['TL_HEAD']['search:type'] = '<meta name="search:type" content="'. $strCategories .'"/>';
        }
    }
}