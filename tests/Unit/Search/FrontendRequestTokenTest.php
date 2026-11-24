<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Tests\Unit\Search;

use Alnv\ProSearchIndexerContaoAdapterBundle\Search\FrontendRequestToken;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Csrf\MemoryTokenStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class FrontendRequestTokenTest extends TestCase
{
    private RequestStack $requestStack;

    private FrontendRequestToken $guard;

    protected function setUp(): void
    {
        $storage = new MemoryTokenStorage();
        $storage->initialize([]);

        $this->requestStack = new RequestStack();
        $manager = new ContaoCsrfTokenManager($this->requestStack, 'csrf_', null, $storage, null, 'contao_csrf_token');
        $this->guard = new FrontendRequestToken($manager, 'contao_csrf_token');
    }

    public function testValidTokenInHeaderOrBody(): void
    {
        $token = $this->guard->getValue();

        $this->assertTrue($this->guard->isValid($this->request(['X-CSRF-Token' => $token])));
        $this->assertTrue($this->guard->isValid($this->request([], ['REQUEST_TOKEN' => $token])));
    }

    public function testInvalidOrMissingToken(): void
    {
        $this->assertFalse($this->guard->isValid($this->request(['X-CSRF-Token' => 'wrong'])));
        $this->assertFalse($this->guard->isValid($this->request()));
        $this->assertFalse($this->guard->isValid($this->request([], ['REQUEST_TOKEN' => ['array']])));
    }

    public function testEmptyTokenIsRejectedEvenIfContaoWouldSkipTheCheck(): void
    {
        // cookie-less same-origin POST: Contao accepts any token value, the guard must not accept an empty one
        $request = $this->request(['Sec-Fetch-Site' => 'same-origin']);
        $this->requestStack->push($request);

        $this->assertFalse($this->guard->isValid($request));
    }

    public function testResponsesCarryAFreshTokenAndAreNotCached(): void
    {
        $response = $this->guard->createInvalidTokenResponse();
        $data = json_decode((string) $response->getContent(), true);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertNotEmpty($data['requestToken']);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        // invalid UTF-8 from old index data must not break the response
        $response = $this->guard->createResponse(['title' => "Bad \xB1 byte"]);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Bad', (string) $response->getContent());
    }

    private function request(array $headers = [], array $post = []): Request
    {
        $request = Request::create('https://example.org/elastic/search/results', 'POST', $post);

        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }

        return $request;
    }
}
