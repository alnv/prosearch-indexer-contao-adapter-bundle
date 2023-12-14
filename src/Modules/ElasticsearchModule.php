<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Modules;

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Categories;
use Contao\Combiner;
use Contao\Module;
use Contao\StringUtil;
use Contao\System;
use Contao\FrontendTemplate;
use Contao\BackendTemplate;

class ElasticsearchModule extends Module
{

    protected $strTemplate = 'mod_elasticsearch';

    public function generate(): string
    {

        if (System::getContainer()->get('request_stack')->getCurrentRequest()->get('_scope') == 'backend') {

            $objTemplate = new BackendTemplate('be_wildcard');
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->title = $this->headline;
            $objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id=' . $this->id;
            $objTemplate->wildcard = '### ' . strtoupper($GLOBALS['TL_LANG']['FMD']['elasticsearch'][0]) . ' ###';

            return $objTemplate->parse();
        }

        return parent::generate();
    }

    protected function compile()
    {

        global $objPage;

        $this->loadAssets();

        $this->Template->uniqueId = $this->id;
        $this->Template->rootPageId = $objPage->rootId;
        $this->Template->elementId = $this->getElementId();
        $this->Template->categoryOptions = (new Categories())->getTranslatedCategories();
        $this->Template->categories = StringUtil::deserialize($this->psSearchCategories, true);
        $this->Template->keywordLabel = $GLOBALS['TL_LANG']['MSC']['keywords'];
        $this->Template->search = StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['searchLabel']);
        $this->Template->didYouMeanLabel = StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['didYouMeanLabel']);

        $objTemplate = new FrontendTemplate('js_elasticsearch');
        $objTemplate->setData($this->Template->getData());
        $this->Template->script = $objTemplate->parse();
    }

    protected function getElementId(): string
    {

        return 'id_search_' . uniqid() . $this->id;
    }

    protected function loadAssets(): void
    {

        $objCombiner = new Combiner();
        $objCombiner->add('/bundles/alnvprosearchindexercontaoadapter/vue.min.js');
        $objCombiner->add('/bundles/alnvprosearchindexercontaoadapter/vue-resource.min.js');
        $GLOBALS['TL_HEAD']['vue'] = '<script src="' . $objCombiner->getCombinedFile() . '"></script>';

        $objCombiner = new Combiner();
        $objCombiner->add('/bundles/alnvprosearchindexercontaoadapter/autoComplete.min.js');
        $GLOBALS['TL_HEAD']['autoComplete'] = '<script src="' . $objCombiner->getCombinedFile() . '"></script>';

        $objCombiner = new Combiner();
        $objCombiner->add('/bundles/alnvprosearchindexercontaoadapter/default.scss');
        $objCombiner->add('/bundles/alnvprosearchindexercontaoadapter/autoComplete.scss');
        $GLOBALS['TL_HEAD']['elasticsearch-default'] = '<link href="' . $objCombiner->getCombinedFile() . '" rel="stylesheet">';

        $objCombiner = new Combiner();
        $objCombiner->add('/bundles/alnvprosearchindexercontaoadapter/elasticsearch.scss');
        $GLOBALS['TL_HEAD']['elasticsearch-custom'] = '<link href="' . $objCombiner->getCombinedFile() . '" rel="stylesheet">';
    }
}