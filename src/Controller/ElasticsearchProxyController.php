<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Controller;

use Contao\Input;
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

    //
}