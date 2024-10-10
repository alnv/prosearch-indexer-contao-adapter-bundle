<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Controller;

use Alnv\ProSearchIndexerContaoAdapterBundle\Adapter\Elasticsearch;
use Alnv\ProSearchIndexerContaoAdapterBundle\Adapter\Options;
use Alnv\ProSearchIndexerContaoAdapterBundle\Adapter\Proxy;
use Alnv\ProSearchIndexerContaoAdapterBundle\AI\AiElasticsearch;
use Alnv\ProSearchIndexerContaoAdapterBundle\Entity\Result;
use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Categories;
use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Credentials;
use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Keyword;
use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Stats;
use Contao\CoreBundle\Controller\AbstractController;
use Contao\FrontendTemplate;
use Contao\Input;
use Contao\ModuleModel;
use Contao\System;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;


#[Route(path: 'elastic', name: 'elastic-controller', defaults: ['_scope' => 'frontend', '_token_check' => false])]
class ElasticsearchController extends AbstractController
{

    #[Route(path: '/search/results', methods: ["POST", "GET"])]
    public function getSearchResults(): JsonResponse
    {

        $this->container->get('contao.framework')->initialize();

        $arrJsonData = \json_decode(file_get_contents('php://input'), true);
        if (!empty($arrJsonData) && is_array($arrJsonData)) {
            Input::setPost('root', ($arrJsonData['root'] ?? 0));
            Input::setPost('module', ($arrJsonData['module'] ?? 0));
            Input::setPost('categories', ($arrJsonData['categories'] ?? []));
            Input::setPost('source', ($arrJsonData['source'] ?? ''));
        }

        $arrCategories = Input::post('categories') ?: (Input::get('categories') ?? []);
        $strModuleId = Input::post('module') ?: (Input::get('module') ?? '');
        $strRootPageId = Input::post('root') ?: (Input::get('root') ?? '');
        $strSource = Input::post('source') ?: (Input::get('source') ?? '');
        $blnGroup = (bool)(Input::post('group') ?: (Input::get('group') ?? false));
        $strQuery = Input::get('query') ?? '';
        $strSearchAfter = Input::get('search_after') ?? '';

        $objKeyword = new Keyword();
        $arrKeywords = $objKeyword->setKeywords($strQuery, ['categories' => $arrCategories]);

        $objCredentials = new Credentials();
        $arrCredentials = $objCredentials->getCredentials();

        $arrResults = [
            'keywords' => $arrKeywords,
            'globalRichSnippets' => [],
            'results' => []
        ];

        $arrElasticOptions = $this->getOptionsByModuleAndRootId($strModuleId, $strRootPageId);
        $arrElasticOptions['search_after'] = $strSearchAfter;

        switch ($arrCredentials['type']) {
            case 'elasticsearch':
            case 'elasticsearch_cloud':
                $objElasticsearchAdapter = new Elasticsearch($arrElasticOptions);
                $objElasticsearchAdapter->connect();

                if ($objElasticsearchAdapter->getClient()) {
                    $arrResults['results'] = $objElasticsearchAdapter->search($arrKeywords);
                }
                break;

            case 'licence':
                $objElasticsearchAdapter = new Elasticsearch($arrElasticOptions);
                $objElasticsearchAdapter->connect();

                $objProxy = new Proxy($objElasticsearchAdapter->getLicense());
                $arrResults['results'] = $objProxy->search($arrKeywords, $objElasticsearchAdapter->getIndexName($strRootPageId), $arrElasticOptions);
                break;
        }

        $arrHits = $arrResults['results']['hits'];

        if ($arrElasticOptions['useOpenAi']) {

            $intFirstScore = (floatval(($arrHits[0]['_score'] ?? 0)) * 10);
            if (empty($arrHits) || ($arrElasticOptions['openAiRelevanceScore'] > 0 || $arrElasticOptions['openAiRelevanceScore'] >= $intFirstScore)) {
                $arrHits = (new AiElasticsearch($arrElasticOptions['openAiAssistant'], []))->getHits($arrKeywords['keyword']);
                $arrResults['results']['didYouMean'] = [];
                $arrResults['results']['max_score'] = 0;
            }
        }

        $arrResults['results']['hits'] = [];

        $objModule = ModuleModel::findByPk($strModuleId);
        $strSearchResultsTemplate = $objModule ? ($objModule->psResultsTemplate ?? 'elasticsearch_result') : 'elasticsearch_result';

        if ($blnGroup === true) {

            $arrGrouped = [];
            $arrGlobalRichSnippets = [];
            $arrCategoriesLabels = (new Categories())->getTranslatedCategories();

            foreach ($arrHits as $arrHit) {

                $arrTypes = empty($arrHit['_source']['types']) ? [''] : $arrHit['_source']['types'];
                foreach ($arrTypes as $strType) {

                    $strLabel = $arrCategoriesLabels[$strType]['label'] ?? '';
                    if (!isset($arrGrouped[$strLabel])) {
                        $arrGrouped[$strLabel] = [
                            'hits' => [],
                            'label' => $strLabel,
                            'value' => $strType
                        ];
                    }

                    $arrElasticOptions['usedKeyWord'] = $strType;
                    $arrParsedHit = $this->parseHit($arrHit, $arrKeywords, $arrElasticOptions);

                    if (empty($arrParsedHit)) {
                        continue;
                    }

                    $arrGrouped[$strLabel]['hits'][] = $this->addTemplate($strSearchResultsTemplate, $arrParsedHit, $arrGlobalRichSnippets);
                }
            }

            ksort($arrGrouped);
            $arrResults['globalRichSnippets'] = $arrGlobalRichSnippets;
            $arrResults['results']['hits'] = $arrGrouped;

        } else {

            foreach ($arrHits as $arrHit) {
                $arrParsedHit = $this->parseHit($arrHit, $arrKeywords, $arrElasticOptions);

                if (empty($arrParsedHit)) {
                    continue;
                }

                $arrResults['results']['hits'][] = $this->addTemplate($strSearchResultsTemplate, $arrParsedHit);
            }
        }

        if (!empty($arrResults['results']['didYouMean'])) {
            if (($intIndex = array_search($arrKeywords['keyword'], $arrResults['results']['didYouMean'])) !== false) {
                unset($arrResults['results']['didYouMean'][$intIndex]);
            }
            $arrResults['results']['didYouMean'] = array_filter($arrResults['results']['didYouMean']);
        }

        Stats::setKeyword($arrKeywords, count(($arrResults['results']['hits'] ?? [])), $strSource);

        return new JsonResponse($arrResults);
    }

    protected function parseHit($arrHit, $arrKeywords, $arrElasticOptions): array
    {

        $objEntity = new Result();
        $objEntity->addHit($arrHit['_source']['id'], ($arrHit['highlight'] ?? []), [
            'types' => $arrHit['_source']['types'],
            'score' => $arrHit['_score'],
            'sort' => $arrHit['sort'] ?? [],
            'keywords' => $arrKeywords,
            'elasticOptions' => $arrElasticOptions,
        ]);

        if ($arrResult = $objEntity->getResult()) {
            return $arrResult;
        }

        return [];
    }

    protected function addTemplate($strTemplate, $arrHit, &$arrGlobalRichSnippets = []): array
    {

        $objTemplate = new FrontendTemplate($strTemplate);
        $objTemplate->setData($arrHit);
        $arrMicroData = [];

        foreach ($arrHit['microdata'] as $strType => $arrEntities) {

            $arrMicroData[$strType] = [];

            foreach ($arrEntities as $objEntity) {

                $arrJsonLdScriptsData = $objEntity->getJsonLdScriptsData();

                if ($objEntity->globalRichSnippet) {

                    if (!isset($arrGlobalRichSnippets[$strType])) {
                        $arrGlobalRichSnippets[$strType] = [];
                    }

                    $arrGlobalRichSnippets[$strType][] = $arrJsonLdScriptsData;
                }

                $arrMicroData[$strType][] = $arrJsonLdScriptsData;
            }
        }

        $objParser = System::getContainer()->get('contao.insert_tag.parser');

        $arrHit['microdata'] = $arrMicroData;
        $arrHit['template'] = $objParser->replaceInline($objTemplate->parse());

        return $arrHit;
    }

    #[Route(path: '/search/autocompletion', methods: ["POST", "GET"])]
    public function getAutoCompletion(): JsonResponse
    {

        $this->container->get('contao.framework')->initialize();

        $arrJsonData = \json_decode(file_get_contents('php://input'), true);

        if (!empty($arrJsonData) && is_array($arrJsonData)) {
            Input::setPost('root', $arrJsonData['root']);
            Input::setPost('module', $arrJsonData['module']);
            Input::setPost('categories', $arrJsonData['categories']);
        }

        $arrCategories = Input::post('categories') ?? [];
        $strModuleId = Input::post('module') ?: (Input::get('module') ?? '');
        $strRootPageId = Input::post('root') ?: (Input::get('root') ?? '');
        $query = Input::get('query') ?? '';

        $objCredentials = new Credentials();
        $arrCredentials = $objCredentials->getCredentials();

        $objKeyword = new Keyword();
        $arrKeywords = $objKeyword->setKeywords($query, ['categories' => $arrCategories]);

        $arrResults = [
            'keywords' => $arrKeywords,
            'results' => []
        ];

        switch ($arrCredentials['type']) {
            case 'elasticsearch':
            case 'elasticsearch_cloud':
                $objElasticsearchAdapter = new Elasticsearch($this->getOptionsByModuleAndRootId($strModuleId, $strRootPageId));
                $objElasticsearchAdapter->connect();
                if ($objElasticsearchAdapter->getClient()) {
                    $arrResults['results'] = $objElasticsearchAdapter->autoCompilation($arrKeywords);
                }
                break;
            case 'licence':
                $arrOptions = $this->getOptionsByModuleAndRootId($strModuleId, $strRootPageId);
                $objElasticsearchAdapter = new Elasticsearch($arrOptions);
                $objElasticsearchAdapter->connect();
                $objProxy = new Proxy($objElasticsearchAdapter->getLicense());
                $arrResults['results'] = $objProxy->autocompletion($arrKeywords, $objElasticsearchAdapter->getIndexName($strRootPageId), $arrOptions);
                break;
        }

        return new JsonResponse($arrResults);
    }

    protected function getOptionsByModuleAndRootId($strModuleId, $strRootPageId = null): array
    {

        $objModule = ModuleModel::findByPk($strModuleId);

        $strAnalyzer = $objModule?->psAnalyzer ? $objModule->psAnalyzer : '';
        $strLanguage = $objModule?->psLanguage ? $objModule->psLanguage : '';
        $strDomains = $objModule?->psDomains ? $objModule->psDomains : '';

        $strOpenAssistant = $objModule?->psOpenAssistant ? $objModule->psOpenAssistant : '';
        $blnUseOpenAi = (bool)$objModule?->psUseOpenAi;

        $objElasticOptions = new Options();
        $objElasticOptions->setLanguage($strLanguage);
        $objElasticOptions->setOpenAiAssistant($strOpenAssistant);
        $objElasticOptions->setUseOpenAi($blnUseOpenAi);
        $objElasticOptions->setRootPageId($strRootPageId);
        $objElasticOptions->setPerPage($objModule?->perPage ?: 50);
        $objElasticOptions->setAnalyzer($strAnalyzer);
        $objElasticOptions->setFuzzy((bool)$objModule?->fuzzy);
        $objElasticOptions->setUseRichSnippets((bool)$objModule?->psUseRichSnippets);
        $objElasticOptions->setOpenDocumentsInBrowser(((bool)$objModule?->psOpenDocumentInBrowser));
        $objElasticOptions->setMinKeywordLength(($objModule?->minKeywordLength ?: 3));
        $objElasticOptions->setOpenAiRelevanceScore((int)($objModule?->psOpenAiRelevance ?: 0));
        $objElasticOptions->setDomain($strDomains);

        return $objElasticOptions->getOptions();
    }
}