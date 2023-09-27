<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Modules;

use Contao\Combiner;
use Contao\Input;
use Contao\Module;
use Contao\StringUtil;

class ElasticsearchTypeAheadModule extends Module
{

    protected $strTemplate = 'mod_elasticsearch_type_ahead';

    public function generate()
    {

        if (\System::getContainer()->get('request_stack')->getCurrentRequest()->get('_scope') == 'backend') {

            $objTemplate = new \BackendTemplate('be_wildcard');
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->title = $this->headline;
            $objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id=' . $this->id;
            $objTemplate->wildcard = '### ' . strtoupper($GLOBALS['TL_LANG']['FMD']['elasticsearch_type_ahead'][0]) . ' ###';

            return $objTemplate->parse();
        }

        return parent::generate();
    }

    protected function compile()
    {

        global $objPage;

        $strKeywords = trim(Input::get('keywords'));

        $this->Template->uniqueId = $this->id;
        $this->Template->rootPageId = $objPage->rootId;
        $this->Template->redirect = $this->getRedirectUrl();
        $this->Template->isResultPage = $this->isResultsPage();
        $this->Template->keywordLabel = $GLOBALS['TL_LANG']['MSC']['keywords'];
        $this->Template->search = StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['searchLabel']);
        $this->Template->didYouMeanLabel = StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['didYouMeanLabel']);

        $this->Template->keyword = StringUtil::specialchars($strKeywords);
        $this->Template->action = $this->getActionUrl();

        $this->Template->categories = \StringUtil::deserialize($this->psSearchCategories, true);
        $this->Template->elementId = $this->getElementId();

        $this->getJsScript();
    }

    protected function isResultsPage()
    {

        global $objPage;

        return $objPage->id === $this->jumpTo;
    }

    protected function getRedirectUrl()
    {

        $strRedirect = '';

        if ($objPage = \PageModel::findByPk($this->jumpTo)) {
            $strRedirect = $objPage->getFrontendUrl();
        }

        return $strRedirect;
    }

    protected function getActionUrl()
    {

        if ($objJump = \PageModel::findByPk($this->jumpTo)) {
            return $objJump->getFrontendUrl();
        }

        global $objPage;
        return $objPage->getFrontendUrl();
    }

    protected function getJsScript()
    {

        $this->loadAssets();

        $objTemplate = new \FrontendTemplate('js_elasticsearch_type_ahead');
        $objTemplate->setData($this->Template->getData());

        $this->Template->script = $objTemplate->parse();
    }

    private function getElementId()
    {

        return 'id_search_' . uniqid() . $this->id;
    }

    protected function loadAssets()
    {

        $objCombiner = new Combiner();
        $objCombiner->add('/bundles/alnvprosearchindexercontaoadapter/vue.min.js');
        $objCombiner->add('/bundles/alnvprosearchindexercontaoadapter/vue-resource.min.js');
        $GLOBALS['TL_HEAD']['vue'] = '<script src="' . $objCombiner->getCombinedFile() . '"></script>';

        $objCombiner = new Combiner();
        $objCombiner->add('/bundles/alnvprosearchindexercontaoadapter/autoComplete.min.js');
        $GLOBALS['TL_HEAD']['autoComplete'] = '<script src="' . $objCombiner->getCombinedFile() . '"></script>';

        $objCombiner = new Combiner();
        $objCombiner->add('/bundles/alnvprosearchindexercontaoadapter/autoComplete.scss');
        $objCombiner->add('/bundles/alnvprosearchindexercontaoadapter/default.scss');
        $GLOBALS['TL_HEAD']['elasticsearch-default'] = '<link href="' . $objCombiner->getCombinedFile() . '" rel="stylesheet">';

        $objCombiner = new Combiner();
        $objCombiner->add('/bundles/alnvprosearchindexercontaoadapter/elasticsearch_type_ahead.scss');
        $GLOBALS['TL_HEAD']['elasticsearch_type_ahead'] = '<link href="' . $objCombiner->getCombinedFile() . '" rel="stylesheet">';
    }
}