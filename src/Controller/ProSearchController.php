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

#[Route(defaults: ['_scope' => 'frontend', '_token_check' => false])]
class ProSearchController extends AbstractController
{
    private const string LEGACY_ROUTE_PREFIX = 'elastic-controller.';

    public function __construct(
        private readonly SearchService $searchService,
        private readonly IndexNameResolver $indexNameResolver,
        private readonly ?SearchResultProvider $resultProvider = null,
        private readonly ?SearchPanelRenderer $panelRenderer = null,
        private readonly ?FrontendRequestToken $requestToken = null,
    ) {
    }

    #[Route(path: '/prosearch/search/results', name: 'prosearch-controller.search_results', methods: ['POST'])]
    #[Route(path: '/elastic/search/results', name: 'elastic-controller.search_results', methods: ['POST'])]
    public function getSearchResults(Request $request): JsonResponse
    {
        $this->deprecateLegacyRoute($request, '/prosearch/search/results');
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

    #[Route(path: '/prosearch/search/autocompletion', name: 'prosearch-controller.search_autocompletion', methods: ['POST'])]
    #[Route(path: '/elastic/search/autocompletion', name: 'elastic-controller.search_autocompletion', methods: ['POST'])]
    public function getAutoCompletion(Request $request): JsonResponse
    {
        $this->deprecateLegacyRoute($request, '/prosearch/search/autocompletion');
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

    private function deprecateLegacyRoute(Request $request, string $path): void
    {
        if (str_starts_with((string) $request->attributes->get('_route'), self::LEGACY_ROUTE_PREFIX)) {
            \trigger_deprecation('alnv/prosearch-indexer-contao-adapter-bundle', '4.0', 'The route "%s" is deprecated and will be removed in 5.0, use "%s" instead.', $request->getPathInfo(), $path);
        }
    }

    private function scalar(mixed $value): string
    {
        return \is_scalar($value) ? (string) $value : '';
    }

    private function sanitizeId(string $id): string
    {
        return preg_replace('/[^A-Za-z0-9_-]/', '', $id) ?: 'ps_search';
    }

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
