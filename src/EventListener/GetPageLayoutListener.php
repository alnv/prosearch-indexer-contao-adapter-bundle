<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\EventListener;

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Categories;
use Contao\LayoutModel;
use Contao\PageModel;
use Contao\PageRegular;
use Contao\StringUtil;

class GetPageLayoutListener
{

    public function getPageLayout(PageModel $pageModel, LayoutModel $layout, PageRegular $pageRegular): void
    {
        $categories = Categories::normalizeList((string) $pageModel->psSearchCategory);

        if ([] !== $categories) {
            $GLOBALS['TL_HEAD']['search:type'] = '<meta name="search:type" content="' . StringUtil::specialchars(implode(',', $categories)) . '">';
        }
    }
}
