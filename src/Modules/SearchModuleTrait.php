<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Modules;

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Categories;
use Alnv\ProSearchIndexerContaoAdapterBundle\Search\FrontendRequestToken;
use Alnv\ProSearchIndexerContaoAdapterBundle\Search\SearchPanelRenderer;
use Alnv\ProSearchIndexerContaoAdapterBundle\Search\SearchResultProvider;
use Contao\Combiner;
use Contao\Controller;
use Contao\FrontendTemplate;
use Contao\Input;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Contao\Template;

trait SearchModuleTrait
{
    public const string FORM_SUBMIT = 'prosearch_search';

    private const string ASSET_PATH = 'bundles/alnvprosearchindexercontaoadapter/';

    private ?bool $legacyTemplate = null;

    protected function getSearchRequest(): array
    {
        if (self::FORM_SUBMIT === Input::post('FORM_SUBMIT')) {
            $keywords = $this->stringValue(Input::post('didyoumean')) ?: $this->stringValue(Input::post('keywords'));
            $categories = $this->listValue(Input::post('categories'));

            // category filter buttons toggle one category (without JavaScript)
            if ('' !== ($toggle = $this->stringValue(Input::post('ps_toggle_category')))) {
                $categories = \in_array($toggle, $categories, true) ? array_values(array_diff($categories, [$toggle])) : [...$categories, $toggle];
            }

            return [
                'keywords' => $keywords,
                'categories' => $categories,
                'searchAfter' => $this->stringValue(Input::post('search_after')),
                'submitted' => '' !== $keywords || [] !== $categories,
                'method' => 'POST',
            ];
        }

        $keywords = $this->stringValue(Input::get('keywords')) ?: $this->stringValue(Input::get('q'));
        $categories = $this->listValue(Input::get('categories'));

        return [
            'keywords' => $keywords,
            'categories' => $categories,
            'searchAfter' => '',
            'submitted' => '' !== $keywords || [] !== $categories,
            'method' => 'GET',
        ];
    }

    /**
     * Until 4.0 the module types were "elasticsearch" and "elasticsearch_type_ahead" (see FrontendModuleTypeMigration),
     * Contao renders the type as CSS class "mod_<type>". The old class is added so that theme styles keep working.
     *
     * @deprecated since 4.0, the classes mod_elasticsearch* will be removed in 5.0, use mod_prosearch*
     */
    protected function addLegacyCssClass(string $class): void
    {
        $cssID = \is_array($this->cssID) ? $this->cssID : StringUtil::deserialize($this->cssID, true);
        $cssID[1] = trim(($cssID[1] ?? '').' '.$class);
        $this->cssID = $cssID;
    }

    /**
     * Templates without data-prosearch were written for the Vue front end of ProSearch < 4.
     */
    protected function isLegacyTemplate(): bool
    {
        if (null !== $this->legacyTemplate) {
            return $this->legacyTemplate;
        }

        try {
            $path = Controller::getTemplate($this->customTpl ?: $this->strTemplate);
            $source = is_file($path) ? (string) file_get_contents($path) : '';
        } catch (\Throwable) {
            $source = '';
        }

        return $this->legacyTemplate = !str_contains($source, 'data-prosearch');
    }

    /**
     * Results area rendered on the server (first page load after a search, also without JavaScript).
     *
     * @param array{keywords: string, categories: list<string>, searchAfter: string} $search
     *
     * @return array{html: string, status: string, total: int, count: int, hasMore: bool, searchAfter: string, error: bool}|null
     */
    protected function renderResults(array $search, bool $grouped): ?array
    {
        global $objPage;

        if ('' === $search['keywords']) {
            return null;
        }

        $container = System::getContainer();

        $result = $container->get(SearchResultProvider::class)->search([
            'query' => $search['keywords'],
            'module' => (string) $this->id,
            'root' => (string) $objPage->rootId,
            'categories' => $search['categories'],
            'group' => $grouped,
            'search_after' => $search['searchAfter'],
            'source' => $container->get('request_stack')->getCurrentRequest()?->getUri() ?? '',
        ], $this);

        return $container->get(SearchPanelRenderer::class)->render($result, [
            'formId' => $this->getFormId(),
            'idPrefix' => $this->getElementId(),
            'grouped' => $grouped,
            'categories' => $grouped,
            'activeCategories' => $search['categories'],
            'categoryOptions' => $grouped ? (new Categories())->getTranslatedCategories((string) $objPage->rootId) : [],
            'onlyCategories' => StringUtil::deserialize($this->psSearchCategories, true),
            'headingLevel' => $this->getGroupHeadingLevel(),
        ]);
    }

    /**
     * Template variables of the accessible front end.
     *
     * @param array<string, mixed> $config options for prosearch-search.js
     */
    protected function addFrontendData(Template $template, array $config, string $keywords): void
    {
        global $objPage;

        System::loadLanguageFile('default');
        $lang = $GLOBALS['TL_LANG']['MSC'];
        $container = System::getContainer();
        $router = $container->get('router');
        $requestToken = $container->get(FrontendRequestToken::class)->getValue();

        $inputLabel = trim((string) ($lang['searchInputLabel'] ?? ''));
        $buttonLabel = trim((string) ($lang['searchButtonLabel'] ?? ''));
        $minLength = (int) ($this->minKeywordLength ?: 3);

        $template->formId = $this->getFormId();
        $template->inputId = 'ctrl_keywords_'.$this->id;
        $template->hintId = $this->getElementId().'_hint';
        $template->statusId = $this->getElementId().'_status';
        $template->panelId = $this->getElementId().'_panel';
        $template->listboxId = $this->getElementId().'_listbox';
        $template->formLabel = StringUtil::specialchars($lang['searchFormLabel'] ?? 'Site search');
        $template->inputLabel = '' !== $inputLabel ? $inputLabel : ($lang['searchInputLabelDefault'] ?? 'Search term');
        $template->inputLabelVisible = '' !== $inputLabel;
        $template->buttonLabel = '' !== $buttonLabel ? $buttonLabel : ($lang['searchSubmitLabel'] ?? 'Search');
        $template->buttonLabelVisible = '' !== $buttonLabel;
        $template->hint = \sprintf($lang['searchHint'] ?? 'Enter at least %s characters.', $minLength);
        $template->closeLabel = $lang['searchResultsClose'] ?? 'Close search results';
        $template->resultsLabel = StringUtil::specialchars($lang['searchResultsHeadline'] ?? 'Search results');
        $template->suggestionsLabel = StringUtil::specialchars($lang['searchSuggestionsLabel'] ?? 'Search suggestions');
        $template->searchIcon = $lang['searchButtonIcon'] ?? '';
        $template->loadingIcon = $lang['searchButtonLoadingIcon'] ?? '';
        $template->keywordsValue = StringUtil::specialchars($keywords);
        $template->requestToken = $requestToken;

        $config += [
            'module' => (int) $this->id,
            'root' => (int) $objPage->rootId,
            'minLength' => $minLength,
            'formId' => $this->getFormId(),
            'idPrefix' => $this->getElementId(),
            'headingLevel' => $this->getGroupHeadingLevel(),
            'requestToken' => $requestToken,
            'endpoints' => [
                'results' => $router->generate('prosearch-controller.search_results'),
                'autocomplete' => $router->generate('prosearch-controller.search_autocompletion'),
                'click' => $router->generate('prosearch-stat-controller.click'),
            ],
            'labels' => [
                'loading' => $lang['searchResultsLoading'] ?? 'Searching …',
                'error' => $lang['searchResultsError'] ?? 'The search is currently not available.',
                'moreLoaded' => $lang['searchResultsMoreLoaded'] ?? '%s more results loaded.',
                'noMore' => $lang['searchResultsNoMore'] ?? 'No further results.',
                'suggestions' => $lang['searchSuggestionsCount'] ?? '%s suggestions available. Use the arrow keys to select one.',
                'suggestionsOne' => $lang['searchSuggestionsCountOne'] ?? '1 suggestion available. Use the arrow keys to select it.',
                'noSuggestions' => $lang['searchNoSuggestions'] ?? 'No suggestions.',
                'minLength' => \sprintf($lang['searchHint'] ?? 'Enter at least %s characters.', $minLength),
                'closed' => $lang['searchResultsClosed'] ?? 'Search results closed.',
            ],
        ];

        $template->config = StringUtil::specialchars(json_encode($config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Assets of the accessible front end: one external script without inline code (CSP: script-src 'self').
     */
    protected function loadFrontendAssets(array $styles): void
    {
        $nonce = $this->Template?->nonce('script-src');
        $src = self::ASSET_PATH.'prosearch-search.js';
        $version = @filemtime(\dirname(__DIR__).'/Resources/public/prosearch-search.js') ?: '';

        $GLOBALS['TL_BODY']['prosearch-search'] = \sprintf('<script src="%s%s?v=%s" defer%s></script>', $this->getAssetBase(), $src, $version, $nonce ? ' nonce="'.$nonce.'"' : '');

        $this->loadStyles($styles);
    }

    /**
     * Assets and $this->script of custom templates of ProSearch < 4.
     *
     * @param list<string> $styles
     */
    protected function loadLegacyAssets(array $styles, string $scriptTemplate): void
    {
        $nonce = $this->Template?->nonce('script-src');
        $nonceAttribute = $nonce ? ' nonce="'.$nonce.'"' : '';

        // Vue 2 compiles the in-DOM templates with new Function(): only works with 'unsafe-eval'
        $this->Template?->addCspSource('script-src', "'unsafe-eval'");

        $combiner = new Combiner();
        $combiner->add(self::ASSET_PATH.'vue.min.js');
        $combiner->add(self::ASSET_PATH.'vue-resource.min.js');
        $GLOBALS['TL_HEAD']['vue'] = '<script src="'.$combiner->getCombinedFile().'"'.$nonceAttribute.'></script>';

        $combiner = new Combiner();
        $combiner->add(self::ASSET_PATH.'autoComplete.min.js');
        $GLOBALS['TL_HEAD']['autoComplete'] = '<script src="'.$combiner->getCombinedFile().'"'.$nonceAttribute.'></script>';

        // prosearch-legacy.js (request token for the old requests) is added by LegacySearchScriptListener

        // rendered only if the template outputs $this->script
        $this->Template->script = function () use ($scriptTemplate, $nonce): string {
            $template = new FrontendTemplate($scriptTemplate);
            $template->setData($this->Template->getData());
            $template->nonce = $nonce;

            return $template->parse();
        };

        $this->loadStyles($styles);
    }

    /**
     * @param list<string> $styles groups of SCSS files, one <link> per group
     */
    private function loadStyles(array $styles): void
    {
        if ($this->psPreventCssLoading) {
            return;
        }

        foreach ($styles as $key => $files) {
            $combiner = new Combiner();

            foreach ((array) $files as $file) {
                $combiner->add(self::ASSET_PATH.$file);
            }

            $GLOBALS['TL_HEAD'][$key] = '<link href="'.$combiner->getCombinedFile().'" rel="stylesheet">';
        }
    }

    protected function getRedirectUrl(): string
    {
        if ($this->jumpTo && ($page = PageModel::findByPk($this->jumpTo))) {
            return $page->getFrontendUrl();
        }

        return '';
    }

    protected function isResultsPage(): bool
    {
        global $objPage;

        return (int) $objPage->id === (int) $this->jumpTo;
    }

    /**
     * Form target: the results page or the current page.
     */
    protected function getActionUrl(): string
    {
        global $objPage;

        if ($redirect = $this->getRedirectUrl()) {
            return $redirect;
        }

        if (System::getContainer()->get('contao.routing.page_registry')->isRoutable($objPage)) {
            return $objPage->getFrontendUrl();
        }

        return System::getContainer()->get('request_stack')->getCurrentRequest()?->getPathInfo() ?? '/';
    }

    protected function getElementId(): string
    {
        return 'id_search_'.$this->id;
    }

    protected function getFormId(): string
    {
        return 'ps_search_form_'.$this->id;
    }

    /**
     * Group headings are one level below the module headline (h2 without headline).
     */
    protected function getGroupHeadingLevel(): int
    {
        $headline = StringUtil::deserialize($this->headline, true);

        if (!empty($headline['value']) && preg_match('/^h([1-6])$/', (string) ($headline['unit'] ?? ''), $match)) {
            return min(6, (int) $match[1] + 1);
        }

        return 2;
    }

    private function getAssetBase(): string
    {
        $request = System::getContainer()->get('request_stack')->getCurrentRequest();

        return ($request?->getBasePath() ?? '').'/';
    }

    private function stringValue(mixed $value): string
    {
        return \is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * @return list<string>
     */
    private function listValue(mixed $value): array
    {
        if (\is_string($value)) {
            $value = '' === $value ? [] : [$value];
        }

        if (!\is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(fn ($item): string => $this->stringValue($item), $value), static fn (string $item): bool => '' !== $item)));
    }
}
