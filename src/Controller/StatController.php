<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Controller;

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Keyword;
use Contao\CoreBundle\Controller\AbstractController;
use Contao\Input;
use Symfony\Component\HttpFoundation\JsonResponse;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Stats;

/**
 *
 * @Route("/stat", defaults={"_scope" = "frontend", "_token_check" = false})
 */
class StatController extends AbstractController
{

    /**
     *
     * @Route("/click", methods={"POST"}, name="state-click")
     */
    public function stateClick()
    {
        $this->container->get('contao.framework')->initialize();

        $arrCategories = Input::post('categories') ?: [];
        $strUrl = Input::post('url') ?: '';
        $strQuery = Input::get('query') ?? '';

        $objKeyword = new Keyword();
        $arrKeywords = $objKeyword->setKeywords($strQuery, ['categories' => $arrCategories]);

        Stats::setClick($arrKeywords, $strUrl);

        return new JsonResponse();
    }
}