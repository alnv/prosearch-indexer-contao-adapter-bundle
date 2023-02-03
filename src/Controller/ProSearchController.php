<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;

/**
 *
 * @Route("/ps", defaults={"_scope" = "frontend", "_token_check" = false})
 */
class ProSearchController extends \Contao\CoreBundle\Controller\AbstractController {

    /**
     *
     * @Route("/search/results/{keywords}", methods={"POST"}, name="get-search-results")
     */
    public function getSearchResults($keywords) {

        $this->container->get('contao.framework')->initialize();

        //

        return new JsonResponse([$keywords]);
    }
}