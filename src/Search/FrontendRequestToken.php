<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Search;

use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfToken;

class FrontendRequestToken
{
    public const string HEADER = 'X-CSRF-Token';

    public function __construct(
        private readonly ContaoCsrfTokenManager $tokenManager,
        private readonly string                 $tokenName,
    )
    {
    }

    public function isValid(Request $request): bool
    {
        $token = $request->headers->get(self::HEADER) ?: ($request->request->all()['REQUEST_TOKEN'] ?? '');

        if (!\is_string($token) || '' === $token) {
            return false;
        }

        return $this->tokenManager->isTokenValid(new CsrfToken($this->tokenName, $token));
    }

    public function getValue(): string
    {
        return $this->tokenManager->getDefaultTokenValue();
    }

    /**
     * JSON response with a fresh token, never cached (contains the token and personalized results).
     */
    public function createResponse(array $data, int $status = JsonResponse::HTTP_OK): JsonResponse
    {
        $data['requestToken'] = $this->getValue();

        $response = new JsonResponse(null, $status);
        $response->setEncodingOptions($response->getEncodingOptions() | JSON_INVALID_UTF8_SUBSTITUTE);
        $response->setData($data);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    public function createInvalidTokenResponse(): JsonResponse
    {
        return $this->createResponse(['error' => 'Invalid or missing request token.'], JsonResponse::HTTP_FORBIDDEN);
    }
}