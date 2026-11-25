<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Controller;

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Keyword;
use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Stats;
use Alnv\ProSearchIndexerContaoAdapterBundle\Search\FrontendRequestToken;
use Contao\CoreBundle\Controller\AbstractController;
use Contao\Input;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_scope' => 'frontend'])]
class StatController extends AbstractController
{
    public function __construct(private readonly ?FrontendRequestToken $requestToken = null)
    {
    }

    #[Route(path: '/prosearch/stat/click', name: 'prosearch-stat-controller.click', methods: ['POST'], defaults: ['_token_check' => false])]
    #[Route(path: '/stat/click', name: 'elastic-stat-controller.click', methods: ['POST'], defaults: ['_token_check' => false])]
    public function stateClick(Request $request): JsonResponse
    {
        if ('elastic-stat-controller.click' === $request->attributes->get('_route')) {
            \trigger_deprecation('alnv/prosearch-indexer-contao-adapter-bundle', '4.0', 'The route "/stat/click" is deprecated and will be removed in 5.0, use "/prosearch/stat/click" instead.');
        }

        $this->container->get('contao.framework')->initialize();

        if (!$this->requestToken()->isValid($request)) {
            return $this->requestToken()->createInvalidTokenResponse();
        }

        $arrCategories = Input::post('categories') ?: [];
        $strUrl = Input::post('url') ?: '';
        $strQuery = Input::post('query') ?: (Input::get('query') ?? '');

        $objKeyword = new Keyword();
        $arrKeywords = $objKeyword->setKeywords(\is_string($strQuery) ? $strQuery : '', ['categories' => \is_array($arrCategories) ? $arrCategories : []]);

        Stats::setClick($arrKeywords, \is_string($strUrl) ? $strUrl : '');

        return $this->requestToken()->createResponse([]);
    }

    private function requestToken(): FrontendRequestToken
    {
        return $this->requestToken ?? new FrontendRequestToken($this->container->get('contao.csrf.token_manager'), $this->getParameter('contao.csrf_token_name'));
    }
}
