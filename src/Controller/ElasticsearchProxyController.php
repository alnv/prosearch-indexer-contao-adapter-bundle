<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Controller;

use Alnv\ProSearchIndexerContaoAdapterBundle\Adapter\Elasticsearch;
use Alnv\ProSearchIndexerContaoAdapterBundle\Adapter\Options;
use Contao\CoreBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;

/**
 *
 * @Route("/proxy", defaults={"_scope" = "frontend", "_token_check" = false})
 */
class ElasticsearchProxyController extends AbstractController
{


    /**
     *
     * @Route("/search/results", methods={"POST"}, name="proxy-search-results")
     */
    public function search()
    {
        $this->container->get('contao.framework')->initialize();

        $arrBody = \json_decode(file_get_contents('php://input'), true);

        $objElasticsearch = new Elasticsearch($arrBody['options']);
        $objElasticsearch->connect();

        return new JsonResponse($objElasticsearch->search($arrBody['keywords'], $arrBody['index']));
    }


    /**
     *
     * @Route("/search/autocompletion", methods={"POST"}, name="proxy-search-autocompletion")
     */
    public function autocompletion()
    {

        $this->container->get('contao.framework')->initialize();

        $arrBody = \json_decode(file_get_contents('php://input'), true);

        $objElasticsearch = new Elasticsearch($arrBody['options']);
        $objElasticsearch->connect();

        return new JsonResponse($objElasticsearch->autocompltion($arrBody['keywords'], $arrBody['index']));
    }


    /**
     *
     * @Route("/search/index", methods={"POST"}, name="proxy-search-index")
     */
    public function index()
    {

        $this->container->get('contao.framework')->initialize();

        $arrBody = \json_decode(file_get_contents('php://input'), true);

        $strLicence = \Input::post('licence') ?: \Input::get('licence');

        if (!in_array($strLicence, ['ck-23-kiel', 'alpha-test'])) {
            throw new \CoreBundle\Exception\AccessDeniedException('Page access denied:  ' . \Environment::get('uri'));
        }

        $objElasticsearch = new Elasticsearch((new Options())->getOptions());
        $objElasticsearch->connect();
        $objElasticsearch->clientIndex($arrBody['body'] ?? []);

        return new JsonResponse([]);
    }


    /**
     *
     * @Route("/search/delete", methods={"POST"}, name="proxy-search-delete")
     */
    public function delete()
    {

        $this->container->get('contao.framework')->initialize();

        $arrBody = \json_decode(file_get_contents('php://input'), true);

        $objElasticsearch = new Elasticsearch((new Options())->getOptions());
        $objElasticsearch->connect();
        $objElasticsearch->clientDelete($arrBody['body']['index'], $arrBody['body']['id']);

        return new JsonResponse([]);
    }


    /**
     *
     * @Route("/search/mapping", methods={"POST"}, name="proxy-search-mapping")
     */
    public function mapping()
    {
        $this->container->get('contao.framework')->initialize();

        $arrBody = \json_decode(file_get_contents('php://input'), true);

        $objElasticsearch = new Elasticsearch((new Options())->getOptions());
        $objElasticsearch->connect();
        $objElasticsearch->clientMapping($arrBody['body'] ?? []);

        return new JsonResponse([]);
    }
}