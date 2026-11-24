<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Modules;

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Categories;
use Contao\BackendTemplate;
use Contao\Module;
use Contao\StringUtil;
use Contao\System;

class ElasticsearchModule extends Module
{
    use SearchModuleTrait;

    protected $strTemplate = 'mod_elasticsearch';

    public function generate(): string
    {
        $request = System::getContainer()->get('request_stack')->getCurrentRequest();

        if ($request && System::getContainer()->get('contao.routing.scope_matcher')->isBackendRequest($request)) {
            $objTemplate = new BackendTemplate('be_wildcard');
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->title = $this->headline;
            $objTemplate->href = 'contao?do=themes&amp;table=tl_module&amp;act=edit&amp;id='.$this->id;
            $objTemplate->wildcard = '### '.strtoupper($GLOBALS['TL_LANG']['FMD']['elasticsearch'][0] ?? '').' ###';

            return $objTemplate->parse();
        }

        return parent::generate();
    }

    protected function compile()
    {
        global $objPage;

        $search = $this->getSearchRequest();

        // template variables of ProSearch < 4 (also used by custom templates)
        $this->Template->uniqueId = $this->id;
        $this->Template->rootPageId = $objPage->rootId;
        $this->Template->elementId = $this->getElementId();
        $this->Template->redirect = $this->getRedirectUrl();
        $this->Template->isResultPage = $this->isResultsPage();
        $this->Template->categoryOptions = (new Categories())->getTranslatedCategories();
        $this->Template->categories = StringUtil::deserialize($this->psSearchCategories, true);
        $this->Template->keywordLabel = $GLOBALS['TL_LANG']['MSC']['keywords'] ?? '';
        $this->Template->search = StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['searchLabel'] ?? '');
        $this->Template->keywords = StringUtil::specialchars($search['keywords']);
        $this->Template->inputCategories = $search['categories'];
        $this->Template->requestToken = System::getContainer()->get('contao.csrf.token_manager')->getDefaultTokenValue();

        if ($this->isLegacyTemplate()) {
            $this->loadLegacyAssets([
                'elasticsearch-default' => ['default.scss', 'autoComplete.scss'],
                'elasticsearch-custom' => ['elasticsearch.scss'],
            ], 'j_elasticsearch');

            return;
        }

        // on the results page itself this module is a plain search form (another module shows the results)
        $showResults = !$this->isResultsPage();
        $panel = $showResults && $search['submitted'] ? $this->renderResults($search, true) : null;

        $this->Template->action = $this->getActionUrl();
        $this->Template->showResults = $showResults;
        $this->Template->panel = $panel['html'] ?? '';
        $this->Template->panelOpen = null !== $panel;

        $this->addFrontendData($this->Template, [
            'type' => 'panel',
            'group' => true,
            'live' => $showResults,
            // search in place unless the results are shown on another page
            'nativeSubmit' => !$showResults || '' !== $this->getRedirectUrl(),
            'suggest' => 'none',
            'categories' => $search['categories'],
            'searchAfter' => $panel['searchAfter'] ?? '',
            'loaded' => $panel['count'] ?? 0,
            'total' => $panel['total'] ?? 0,
        ], $search['keywords']);

        $this->loadFrontendAssets([
            'elasticsearch-default' => ['default.scss'],
            'elasticsearch-custom' => ['elasticsearch.scss'],
        ]);
    }
}
