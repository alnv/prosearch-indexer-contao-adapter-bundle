<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Controller;

use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\Input;
use Alnv\ProSearchIndexerContaoAdapterBundle\Adapter\Elasticsearch;
use Alnv\ProSearchIndexerContaoAdapterBundle\Adapter\Options;
use Contao\CoreBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Alnv\ProSearchIndexerContaoAdapterBundle\Events\LicenceCheckEvent;
use Symfony\Component\Routing\Annotation\Route;
use Contao\Environment;

#[Route(
    path: 'proxy',
    name: 'elastic-proxy-controller',
    defaults: ['_scope' => 'frontend']
)]
class ElasticsearchProxyController extends AbstractController
{

    #[Route(
        path: '/search/results',
        methods: ["POST"]
    )]
    public function search(): JsonResponse
    {
        $this->container->get('contao.framework')->initialize();

        $arrBody = \json_decode(file_get_contents('php://input'), true);

        $objElasticsearch = new Elasticsearch($arrBody['options']);
        $objElasticsearch->connect();

        return new JsonResponse($objElasticsearch->search($arrBody['keywords'], $arrBody['index']));
    }

    #[Route(
        path: '/search/autocompletion',
        methods: ["POST"]
    )]
    public function autocompletion(): JsonResponse
    {

        $this->container->get('contao.framework')->initialize();

        $arrBody = \json_decode(file_get_contents('php://input'), true);

        $objElasticsearch = new Elasticsearch($arrBody['options']);
        $objElasticsearch->connect();

        return new JsonResponse($objElasticsearch->autocompltion($arrBody['keywords'], $arrBody['index']));
    }

    #[Route(
        path: '/search/index',
        methods: ["POST"]
    )]
    public function index(): JsonResponse
    {

        $this->container->get('contao.framework')->initialize();

        $arrBody = \json_decode(file_get_contents('php://input'), true);
        $strLicence = Input::post('licence') ?: Input::get('licence');

        if (!(new LicenceCheckEvent())->isValidLicence($strLicence)) {
            throw new AccessDeniedException('Page access denied:  ' . Environment::get('uri'));
        }

        $objElasticsearch = new Elasticsearch((new Options())->getOptions());
        $objElasticsearch->connect();
        $objElasticsearch->clientIndex($arrBody['body'] ?? []);

        return new JsonResponse([]);
    }

    #[Route(
        path: '/search/delete',
        methods: ["POST"]
    )]
    public function delete(): JsonResponse
    {

        $this->container->get('contao.framework')->initialize();

        $arrBody = \json_decode(file_get_contents('php://input'), true);

        $objElasticsearch = new Elasticsearch((new Options())->getOptions());
        $objElasticsearch->connect();
        $objElasticsearch->clientDelete($arrBody['body']['index'], $arrBody['body']['id']);

        return new JsonResponse([]);
    }

    #[Route(
        path: '/search/mapping',
        methods: ["POST"]
    )]
    public function mapping(): JsonResponse
    {
        $this->container->get('contao.framework')->initialize();

        $arrBody = \json_decode(file_get_contents('php://input'), true);

        $objElasticsearch = new Elasticsearch((new Options())->getOptions());
        $objElasticsearch->connect();
        $objElasticsearch->clientMapping($arrBody['body'] ?? []);

        return new JsonResponse([]);
    }

    #[Route(
        path: '/delete/database/{index}',
        methods: ["POST"]
    )]
    public function deleteDatabase($index): JsonResponse
    {
        $this->container->get('contao.framework')->initialize();

        $objElasticsearch = new Elasticsearch((new Options())->getOptions());
        $objElasticsearch->connect();
        $objElasticsearch->deleteDatabase($index);

        return new JsonResponse([]);
    }
}