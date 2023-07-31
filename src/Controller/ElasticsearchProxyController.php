<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;

/**
 *
 * @Route("/proxy", defaults={"_scope" = "frontend", "_token_check" = false})
 */
class ElasticsearchProxyController
{


    /**
     *
     * @Route("/search/results", methods={"POST"}, name="proxy-search-results")
     */
    public function search() {


    }


    /**
     *
     * @Route("/search/results", methods={"POST"}, name="proxy-search-autocompletion")
     */
    public function autocompletion() {}


    /**
     *
     * @Route("/search/index", methods={"POST"}, name="proxy-search-index")
     */
    public function index() {}


    /**
     *
     * @Route("/search/delete", methods={"POST"}, name="proxy-search-delete")
     */
    public function delete() {}


    /**
     *
     * @Route("/search/mapping", methods={"POST"}, name="proxy-search-mapping")
     */
    public function mapping() {}
}