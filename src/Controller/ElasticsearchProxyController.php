<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Controller;

use Alnv\ProSearchIndexerContaoAdapterBundle\Adapter\Adapter;
use Alnv\ProSearchIndexerContaoAdapterBundle\Adapter\Options;
use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Authorization;
use Contao\CoreBundle\Controller\AbstractController;
use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\Environment;
use Contao\Input;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: 'proxy', name: 'elastic-proxy-controller', defaults: ['_scope' => 'frontend', '_token_check' => false])]
class ElasticsearchProxyController extends AbstractController
{

    #[Route(path: '/search/results', methods: ["POST"])]
    public function search(): JsonResponse
    {
        $this->container->get('contao.framework')->initialize();

        $arrBody = \json_decode(file_get_contents('php://input'), true);
        $objElasticsearch = (new Adapter())->getInstance($arrBody['options']);
        $objElasticsearch->connect();

        if (!$objElasticsearch->getClient()) {
            return new JsonResponse([]);
        }

        return new JsonResponse($objElasticsearch->search($arrBody['keywords'], $arrBody['index']));
    }

    #[Route(path: '/search/autocompletion', methods: ["POST"])]
    public function autocompletion(): JsonResponse
    {

        $this->container->get('contao.framework')->initialize();

        $arrBody = \json_decode(file_get_contents('php://input'), true);

        $objElasticsearch = (new Adapter())->getInstance($arrBody['options']);
        $objElasticsearch->connect();

        if (!$objElasticsearch->getClient()) {
            return new JsonResponse([]);
        }

        return new JsonResponse($objElasticsearch->autoCompilation($arrBody['keywords'], $arrBody['index']));
    }

    #[Route(path: '/search/index', methods: ["POST"])]
    public function index(): JsonResponse
    {
        $this->container->get('contao.framework')->initialize();

        $arrBody = \json_decode(file_get_contents('php://input'), true);
        $strLicence = Input::post('licence') ?: Input::get('licence');

        if (!(new Authorization())->isValid($strLicence)) {
            throw new AccessDeniedException('Page access denied:  ' . Environment::get('uri'));
        }

        $objElasticsearch = (new Adapter())->getInstance((new Options())->getOptions());
        $objElasticsearch->connect();

        if (!$objElasticsearch->getClient()) {
            return new JsonResponse([
                'error' => true,
                'message' => 'no connection to client'
            ]);
        }

        $objElasticsearch->clientIndex($arrBody['body'] ?? []);

        return new JsonResponse([
            'success' => true
        ]);
    }

    #[Route(path: '/search/delete', methods: ["POST"])]
    public function delete(): JsonResponse
    {

        $this->container->get('contao.framework')->initialize();

        $arrBody = \json_decode(file_get_contents('php://input'), true);
        $objElasticsearch = (new Adapter())->getInstance((new Options())->getOptions());
        $objElasticsearch->connect();

        if (!$objElasticsearch->getClient()) {
            return new JsonResponse([
                'error' => true,
                'message' => 'no connection to client'
            ]);
        }


        $objElasticsearch->clientDelete($arrBody['body']['index'], $arrBody['body']['id']);

        return new JsonResponse([
            'success' => true
        ]);
    }

    #[Route(path: '/search/mapping', methods: ["POST"])]
    public function mapping(): JsonResponse
    {
        $this->container->get('contao.framework')->initialize();

        $strLicence = Input::post('licence') ?: Input::get('licence');
        $arrBody = \json_decode(file_get_contents('php://input'), true);

        if (!(new Authorization())->isValid($strLicence)) {
            throw new AccessDeniedException('Page access denied:  ' . Environment::get('uri'));
        }

        $objElasticsearch = (new Adapter())->getInstance((new Options())->getOptions());
        $objElasticsearch->connect();

        if (!$objElasticsearch->getClient()) {
            return new JsonResponse([
                'error' => true,
                'message' => 'no connection to client'
            ]);
        }

        $objElasticsearch->clientMapping($arrBody['body'] ?? []);

        return new JsonResponse([
            'success' => true
        ]);
    }
}