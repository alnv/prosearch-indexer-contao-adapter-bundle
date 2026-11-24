<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Controller;

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Categories;
use Alnv\ProSearchIndexerContaoAdapterBundle\Search\FrontendRequestToken;
use Alnv\ProSearchIndexerContaoAdapterBundle\Search\IndexNameResolver;
use Alnv\ProSearchIndexerContaoAdapterBundle\Search\SearchPanelRenderer;
use Alnv\ProSearchIndexerContaoAdapterBundle\Search\SearchResultProvider;
use Alnv\ProSearchIndexerContaoAdapterBundle\Search\SearchService;
use Contao\CoreBundle\Controller\AbstractController;
use Contao\Input;
use Contao\CoreBundle\Util\LocaleUtil;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * JSON endpoints of the front end search (POST only, CSRF protected).
 *
 * Contao's RequestTokenListener skips XHR and JSON requests, therefore the token is checked here for every
 * request (_token_check is false only to answer with a JSON 403 and a fresh token instead of an HTML page).
 *
 * Parameters are read from the request body (form-encoded or JSON). "query" and "search_after" in the query
 * string are still accepted for templates of ProSearch < 4 but should be sent in the body, so that search
 * terms do not end up in URLs and server logs.
 */
#[Route(path: 'elastic', name: 'elastic-controller', defaults: ['_scope' => 'frontend', '_token_check' => false])]
class ElasticsearchController extends AbstractController
{
    public function __construct(
        private readonly SearchService $searchService,
        private readonly IndexNameResolver $indexNameResolver,
        private readonly ?SearchResultProvider $resultProvider = null,
        private readonly ?SearchPanelRenderer $panelRenderer = null,
        private readonly ?FrontendRequestToken $requestToken = null,
    ) {
    }

    #[Route(path: '/search/results', name: '.search_results', methods: ['POST'])]
    public function getSearchResults(Request $request): JsonResponse
    {
        $this->container->get('contao.framework')->initialize();

        if (!$this->requestToken()->isValid($request)) {
            return $this->requestToken()->createInvalidTokenResponse();
        }

        $this->closeSession($request);
        $params = $this->getParams($request);

        $result = $this->resultProvider()->search([
            'query' => $params['query'],
            'module' => $params['module'],
            'root' => $params['root'],
            'categories' => $params['categories'],
            'group' => $params['group'],
            'search_after' => $params['search_after'],
            'source' => $params['source'],
        ], $this);

        // rendered results area for prosearch-search.js (same template as the server-side rendering)
        if ($params['view']) {
            $this->applyLanguage($request, $params['root']);
            $module = '' !== $params['module'] ? ModuleModel::findByPk($params['module']) : null;

            $result['view'] = $this->panelRenderer()->render($result, [
                'formId' => $this->sanitizeId($params['form']),
                'idPrefix' => $this->sanitizeId($params['id_prefix']),
                'grouped' => $params['group'],
                'categories' => $params['group'],
                'activeCategories' => \is_array($params['categories']) ? array_values(array_filter($params['categories'], 'is_string')) : [],
                'categoryOptions' => $params['group'] ? (new Categories())->getTranslatedCategories($params['root']) : [],
                'onlyCategories' => StringUtil::deserialize($module?->psSearchCategories, true),
                'headingLevel' => (int) $params['heading'],
                'loaded' => (int) $params['loaded'],
            ]);
        }

        return $this->requestToken()->createResponse($result);
    }

    #[Route(path: '/search/autocompletion', name: '.search_autocompletion', methods: ['POST'])]
    public function getAutoCompletion(Request $request): JsonResponse
    {
        $this->container->get('contao.framework')->initialize();

        if (!$this->requestToken()->isValid($request)) {
            return $this->requestToken()->createInvalidTokenResponse();
        }

        $this->closeSession($request);
        $params = $this->getParams($request);

        return $this->requestToken()->createResponse($this->resultProvider()->autocomplete([
            'query' => $params['query'],
            'module' => $params['module'],
            'root' => $params['root'],
            'categories' => $params['categories'],
        ]));
    }

    /**
     * @deprecated since 4.0, use SearchResultProvider::getOptions()
     */
    protected function getOptionsByModuleAndRootId($strModuleId, $strRootPageId = null): array
    {
        return $this->resultProvider()->getOptions((string) $strModuleId, (string) $strRootPageId);
    }

    /**
     * @return array{query: string, search_after: string, module: string, root: string, categories: mixed, group: bool, source: string, view: bool, form: string, id_prefix: string, heading: string, loaded: string}
     */
    private function getParams(Request $request): array
    {
        // JSON bodies (fetch() in custom templates of ProSearch < 4) are mapped to POST values
        if (!$request->request->count() && str_contains((string) $request->headers->get('Content-Type'), 'json')) {
            $json = json_decode($request->getContent(), true);

            if (\is_array($json)) {
                foreach ($json as $key => $value) {
                    if (\is_string($key)) {
                        Input::setPost($key, $value);
                    }
                }
            }
        }

        // Contao\Input encodes special characters as before (the statistics store the keywords this way)
        $value = static function (string $key, bool $fromQueryString = false): mixed {
            $post = Input::post($key);

            if ((null === $post || '' === $post) && $fromQueryString) {
                return Input::get($key);
            }

            return $post;
        };

        return [
            'query' => trim($this->scalar($value('query', true))),
            'search_after' => $this->scalar($value('search_after', true)),
            'module' => $this->scalar($value('module')),
            'root' => $this->scalar($value('root')),
            'categories' => $value('categories') ?: [],
            'group' => filter_var($this->scalar($value('group')), FILTER_VALIDATE_BOOLEAN),
            'source' => $this->scalar($value('source')),
            'view' => filter_var($this->scalar($value('view')), FILTER_VALIDATE_BOOLEAN),
            'form' => $this->scalar($value('form')),
            'id_prefix' => $this->scalar($value('id_prefix')),
            'heading' => $this->scalar($value('heading')),
            'loaded' => $this->scalar($value('loaded')),
        ];
    }

    /**
     * The endpoint has no page context: use the language of the website root for the labels.
     */
    private function applyLanguage(Request $request, string $rootId): void
    {
        $root = '' !== $rootId ? PageModel::findByPk($rootId) : null;

        if (!$root || !$root->language) {
            return;
        }

        $locale = LocaleUtil::formatAsLocale($root->language);
        $request->setLocale($locale);
        $request->attributes->set('_locale', $locale);
        $GLOBALS['TL_LANGUAGE'] = LocaleUtil::formatAsLanguageTag($root->language);
        System::loadLanguageFile('default', $GLOBALS['TL_LANGUAGE'], true);
    }

    private function scalar(mixed $value): string
    {
        return \is_scalar($value) ? (string) $value : '';
    }

    private function sanitizeId(string $id): string
    {
        return preg_replace('/[^A-Za-z0-9_-]/', '', $id) ?: 'ps_search';
    }

    /**
     * Release the session lock: the live search fires several requests in a row.
     */
    private function closeSession(Request $request): void
    {
        if ($request->hasSession() && $request->getSession()->isStarted()) {
            $request->getSession()->save();
        }
    }

    private function resultProvider(): SearchResultProvider
    {
        return $this->resultProvider ?? new SearchResultProvider($this->searchService, $this->indexNameResolver);
    }

    private function panelRenderer(): SearchPanelRenderer
    {
        return $this->panelRenderer ?? new SearchPanelRenderer();
    }

    private function requestToken(): FrontendRequestToken
    {
        return $this->requestToken ?? new FrontendRequestToken($this->container->get('contao.csrf.token_manager'), $this->getParameter('contao.csrf_token_name'));
    }
}
