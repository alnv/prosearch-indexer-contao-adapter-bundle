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

/**
 * Click statistics of the front end search (POST only, CSRF protected).
 *
 * The token is checked here for every request (header X-CSRF-Token or field REQUEST_TOKEN), see
 * FrontendRequestToken. "query" is read from the body; the query string is accepted for templates of ProSearch < 4.
 */
#[Route(path: 'stat', name: 'elastic-stat-controller', defaults: ['_scope' => 'frontend'])]
class StatController extends AbstractController
{
    public function __construct(private readonly ?FrontendRequestToken $requestToken = null)
    {
    }

    #[Route(path: '/click', name: '.click', methods: ['POST'], defaults: ['_token_check' => false])]
    public function stateClick(Request $request): JsonResponse
    {
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
