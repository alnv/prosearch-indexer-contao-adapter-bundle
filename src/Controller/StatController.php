<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Controller;

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Keyword;
use Contao\CoreBundle\Controller\AbstractController;
use Contao\Input;
use Symfony\Component\HttpFoundation\JsonResponse;
use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Stats;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: 'stat', name: 'elastic-stat-controller', defaults: ['_scope' => 'frontend', '_token_check' => false])]
class StatController extends AbstractController
{

    #[Route(path: '/click', methods: ["POST"])]
    public function stateClick(): JsonResponse
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