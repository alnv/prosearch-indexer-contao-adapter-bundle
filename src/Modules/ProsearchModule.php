<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Modules;

use Contao\Input;
use Contao\Module;
use Contao\Combiner;
use Contao\StringUtil;

class ProsearchModule extends Module
{

    protected $strTemplate = 'mod_prosearch';

    public function generate()
    {

        if (\System::getContainer()->get('request_stack')->getCurrentRequest()->get('_scope') == 'backend') {

            $objTemplate = new \BackendTemplate('be_wildcard');
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->title = $this->headline;
            $objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id=' . $this->id;
            $objTemplate->wildcard = '### ' . strtoupper($GLOBALS['TL_LANG']['FMD']['prosearch'][0]) . ' ###';

            return $objTemplate->parse();
        }

        return parent::generate();
    }

    protected function compile()
    {

        $strKeywords = trim(Input::get('keywords'));

        $this->Template->uniqueId = $this->id;
        $this->Template->keywordLabel = $GLOBALS['TL_LANG']['MSC']['keywords'];
        $this->Template->search = StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['searchLabel']);
        $this->Template->didYouMeanLabel = StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['didYouMeanLabel']);

        $this->Template->keyword = StringUtil::specialchars($strKeywords);
        $this->Template->action = $this->getActionUrl();
        $this->Template->searchButtonAttributes = $this->getSearchButtonAttributes();

        $this->Template->categories = \StringUtil::deserialize($this->psSearchCategories, true);
        $this->Template->elementId = $this->getElementId();

        $this->getJsScript();
    }

    protected function getActionUrl() {

        if ($objJump = \PageModel::findByPk($this->jumpTo)) {
            return $objJump->getFrontendUrl();
        }

        global $objPage;
        return $objPage->getFrontendUrl();
    }

    protected function getSearchButtonAttributes() {

        $strAttributes = '';

        if (!$this->jumpTo) {
            $strAttributes = '@click.stop.prevent="search()"';
        }

        return $strAttributes;
    }

    protected function getJsScript() {

        $this->loadAssets();

        $objTemplate = new \FrontendTemplate('js_prosearch');
        $objTemplate->setData($this->Template->getData());

        $this->Template->script = $objTemplate->parse();
    }

    private function getElementId() {

        return 'id_search_' . $this->id;
    }

    protected function loadAssets() {

        $objCombiner = new Combiner();
        $objCombiner->add('/bundles/alnvprosearchindexercontaoadapter/vue.min.js');
        $objCombiner->add('/bundles/alnvprosearchindexercontaoadapter/vue-resource.min.js');

        $GLOBALS['TL_HEAD']['jsProsearch'] = '<script src="'.$objCombiner->getCombinedFile().'"></script>';

        $objCombiner = new Combiner();
        $objCombiner->add('/bundles/alnvprosearchindexercontaoadapter/default.scss');
        $GLOBALS['TL_CSS']['cssProsearch'] = $objCombiner->getCombinedFile();
    }
}