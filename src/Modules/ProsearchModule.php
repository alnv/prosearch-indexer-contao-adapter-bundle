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

        global $objPage;

        $strKeywords = trim(Input::get('keywords'));

        $this->Template->uniqueId = $this->id;
        $this->Template->keywordLabel = $GLOBALS['TL_LANG']['MSC']['keywords'];
        $this->Template->search = StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['searchLabel']);

        $this->Template->keyword = StringUtil::specialchars($strKeywords);
        $this->Template->action = $objPage->getFrontendUrl();

        $this->Template->categories = $this->getCategories();
        $this->Template->checkedCategories = \Input::get('categories') ?? [];
        $this->Template->elementId = $this->getElementId();

        $this->getJsScript();
    }

    protected function getCategories() {

        $arrCategories = [];

        if (!$this->psEnableSearchCategories) {
            return $arrCategories;
        }

        $arrOptions = \StringUtil::deserialize($this->psSearchCategoriesOptions, true);

        foreach ($arrOptions as $arrOption) {

            $arrCategories[] = [
                'label' => $arrOption['name'],
                'value' => implode(',', $arrOption['types']),
                'asArray' => $arrOption['types']
            ];
        }

        return $arrCategories;
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
    }
}