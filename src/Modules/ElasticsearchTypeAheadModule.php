<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Modules;

use Contao\BackendTemplate;
use Contao\Module;
use Contao\StringUtil;
use Contao\System;

class ElasticsearchTypeAheadModule extends Module
{
    use SearchModuleTrait;

    protected $strTemplate = 'mod_elasticsearch_type_ahead';

    public function generate(): string
    {
        $request = System::getContainer()->get('request_stack')->getCurrentRequest();

        if ($request && System::getContainer()->get('contao.routing.scope_matcher')->isBackendRequest($request)) {
            $objTemplate = new BackendTemplate('be_wildcard');
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->title = $this->headline;
            $objTemplate->href = 'contao?do=themes&amp;table=tl_module&amp;act=edit&amp;id='.$this->id;
            $objTemplate->wildcard = '### '.strtoupper($GLOBALS['TL_LANG']['FMD']['elasticsearch_type_ahead'][0] ?? '').' ###';

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
        $this->Template->redirect = $this->getRedirectUrl();
        $this->Template->isResultPage = $this->isResultsPage();
        $this->Template->keywordLabel = $GLOBALS['TL_LANG']['MSC']['keywords'] ?? '';
        $this->Template->search = StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['searchLabel'] ?? '');
        $this->Template->didYouMeanLabel = StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['didYouMeanLabel'] ?? '');
        $this->Template->keyword = StringUtil::specialchars($search['keywords']);
        $this->Template->action = $this->getActionUrl();
        $this->Template->categories = StringUtil::deserialize($this->psSearchCategories, true);
        $this->Template->elementId = $this->getElementId();
        $this->Template->requestToken = System::getContainer()->get('contao.csrf.token_manager')->getDefaultTokenValue();

        if ($this->isLegacyTemplate()) {
            $this->loadLegacyAssets([
                'elasticsearch-default' => ['autoComplete.scss', 'default.scss'],
                'elasticsearch_type_ahead' => ['elasticsearch_type_ahead.scss'],
            ], 'j_elasticsearch_type_ahead');

            return;
        }

        // results are listed here on the results page or if there is no results page
        $showResults = $this->isResultsPage() || '' === $this->getRedirectUrl();
        $panel = $showResults && $search['submitted'] ? $this->renderResults($search, false) : null;

        $this->Template->showResults = $showResults;
        $this->Template->panel = $panel['html'] ?? '';

        $this->addFrontendData($this->Template, [
            'type' => 'list',
            'group' => false,
            'live' => false,
            'nativeSubmit' => !$showResults,
            'suggest' => 'advanced' === $this->psAutoCompletionType ? 'hits' : 'terms',
            'categories' => $search['categories'],
            'searchAfter' => $panel['searchAfter'] ?? '',
            'loaded' => $panel['count'] ?? 0,
            'total' => $panel['total'] ?? 0,
        ], $search['keywords']);

        $this->loadFrontendAssets([
            'elasticsearch-default' => ['default.scss'],
            'elasticsearch_type_ahead' => ['elasticsearch_type_ahead.scss'],
        ]);
    }
}
