<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Controller;

use Contao\Input;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Credentials;
use Alnv\ProSearchIndexerContaoAdapterBundle\Adapter\Elasticsearch;

/**
 *
 * @Route("/ps", defaults={"_scope" = "frontend", "_token_check" = false})
 */
class ProSearchController extends \Contao\CoreBundle\Controller\AbstractController {

    /**
     *
     * @Route("/search/results", methods={"POST", "GET"}, name="get-search-results")
     */
    public function getSearchResults() {

        $this->container->get('contao.framework')->initialize();

        $arrCategories = Input::post('categories') ?? [];
        $strModuleId = Input::post('module') ?? [];
        $query = Input::get('query') ?? '';

        $objKeyword = new \Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Keyword();
        $arrKeywords = $objKeyword->setKeywords($query, ['categories' => $arrCategories]);

        $objCredentials = new Credentials();
        $arrCredentials = $objCredentials->getCredentials();

        $arrResults = [
            'keywords' => $arrKeywords,
            'results' => []
        ];

        switch ($arrCredentials['type']) {
            case 'elasticsearch':
            case 'elasticsearch_cloud':
                $objElasticsearchAdapter = new Elasticsearch($strModuleId);
                $objElasticsearchAdapter->connect();
                if ($objElasticsearchAdapter->getClient()) {
                    $arrResults['results'] = $objElasticsearchAdapter->search($arrKeywords);
                }
                break;
            case 'licence':
                // todo
                break;
        }

        // parse template
        foreach (($arrResults['results']['hits']??[]) as $index => $arrResult) {
            $objTemplate = new \FrontendTemplate('ps_search_result');
            $objTemplate->setData($arrResult);
            $arrResults['results']['hits'][$index]['template'] = \Controller::replaceInsertTags($objTemplate->parse());
        }

        return new JsonResponse($arrResults);
    }

    /**
     *
     * @Route("/search/autocompletion", methods={"POST", "GET"}, name="get-search-autocompletion")
     */
    public function getAutoCompletion() {

        $this->container->get('contao.framework')->initialize();

        $arrCategories = Input::post('categories') ?? [];
        $strModuleId = Input::post('module') ?? [];
        $query = Input::get('query') ?? '';

        $objCredentials = new Credentials();
        $arrCredentials = $objCredentials->getCredentials();

        $objKeyword = new \Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Keyword();
        $arrKeywords = $objKeyword->setKeywords($query, ['categories' => $arrCategories]);

        $arrResults = [
            'keywords' => $arrKeywords,
            'results' => []
        ];

        switch ($arrCredentials['type']) {
            case 'elasticsearch':
            case 'elasticsearch_cloud':
                $objElasticsearchAdapter = new Elasticsearch($strModuleId);
                $objElasticsearchAdapter->connect();
                if ($objElasticsearchAdapter->getClient()) {
                    $arrResults['results'] = $objElasticsearchAdapter->autocompltion($arrKeywords);
                }
                break;
            case 'licence':
                // todo
                break;
        }

        return new JsonResponse($arrResults);
    }
}