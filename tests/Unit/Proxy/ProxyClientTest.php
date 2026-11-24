<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Tests\Unit\Proxy;

use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\Exception\ProxyException;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\Exception\ProxyLicenseException;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\Exception\ProxyRateLimitException;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\Exception\ProxyUnavailableException;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\ProxyClient;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ProxyClientTest extends TestCase
{
    public function testSendsLicenseAndApiKeyAsHeadersOnly(): void
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://proxy.example/api/v1/indices/contao_search_a/search', $url);
            $this->assertStringNotContainsString('LICENSE-ID', $url);
            $this->assertContains('X-ProSearch-License: LICENSE-ID', $options['headers']);
            $this->assertContains('X-Api-Key: key', $options['headers']);
            $this->assertSame(['query' => 'bürger', 'profile' => 'prosearch_default'], json_decode($options['body'], true));

            return new MockResponse('{"hits":[],"suggestions":{}}');
        });

        $result = (new ProxyClient($client, 'https://proxy.example/', 'key'))->search('LICENSE-ID', 'contao_search_a', ['query' => 'bürger', 'profile' => 'prosearch_default']);

        $this->assertSame([], $result['hits']);
    }

    public function testDocumentAndIndexCalls(): void
    {
        $calls = [];
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$calls): MockResponse {
            $calls[] = $method.' '.parse_url($url, PHP_URL_PATH);

            return match (\count($calls)) {
                1 => new MockResponse('{"index":"i","created":true}', ['http_code' => 201]),
                2 => new MockResponse('{"result":"created"}', ['http_code' => 201]),
                3 => new MockResponse('{"error":{"code":"document_not_found","message":"x"}}', ['http_code' => 404]),
                4 => new MockResponse('{"deleted":true}'),
                5 => new MockResponse('{"error":{"code":"index_not_found","message":"x"}}', ['http_code' => 404]),
                6 => new MockResponse('{"errors":true,"items":[]}', ['http_code' => 207]),
                default => new MockResponse('{"index":"i","deleted":false}', ['http_code' => 404]),
            };
        });

        $proxy = new ProxyClient($client, 'https://proxy.example');

        $this->assertTrue($proxy->createIndex('L', 'contao_search_a', ['mappings' => []])['created']);
        $this->assertSame('created', $proxy->upsertDocument('L', 'contao_search_a', '7', ['id' => 7])['result']);
        $this->assertNull($proxy->getDocument('L', 'contao_search_a', '8'));
        $this->assertTrue($proxy->deleteDocument('L', 'contao_search_a', '7'));
        $this->assertFalse($proxy->deleteDocument('L', 'contao_search_a', '7'), 'missing index counts as deleted');
        $this->assertTrue($proxy->bulk('L', 'contao_search_a', [['action' => 'delete', 'id' => '1']])['errors'], '207 is a regular answer');
        $this->assertFalse($proxy->deleteIndex('L', 'contao_search_a'));

        $this->assertSame([
            'PUT /api/v1/indices/contao_search_a',
            'PUT /api/v1/indices/contao_search_a/documents/7',
            'GET /api/v1/indices/contao_search_a/documents/8',
            'DELETE /api/v1/indices/contao_search_a/documents/7',
            'DELETE /api/v1/indices/contao_search_a/documents/7',
            'POST /api/v1/indices/contao_search_a/documents/_bulk',
            'DELETE /api/v1/indices/contao_search_a',
        ], $calls);
    }

    /**
     * @param class-string<ProxyException> $exception
     */
    #[DataProvider('errorProvider')]
    public function testErrorMapping(MockResponse $response, string $exception, string $code): void
    {
        try {
            (new ProxyClient(new MockHttpClient($response), 'https://proxy.example'))->search('L', 'contao_search_a', []);
            $this->fail('Exception expected');
        } catch (ProxyException $e) {
            $this->assertInstanceOf($exception, $e);
            $this->assertSame($code, $e->getErrorCode());
        }
    }

    public static function errorProvider(): iterable
    {
        yield 'unreachable' => [new MockResponse('', ['error' => 'Connection refused']), ProxyUnavailableException::class, 'proxy_unreachable'];
        yield 'invalid response' => [new MockResponse('<html>', ['http_code' => 200]), ProxyUnavailableException::class, 'proxy_invalid_response'];
        yield 'backend down' => [new MockResponse('{"error":{"code":"backend_unavailable","message":"down"}}', ['http_code' => 503]), ProxyUnavailableException::class, 'backend_unavailable'];
        yield 'license invalid' => [new MockResponse('{"error":{"code":"license_invalid","message":"invalid"}}', ['http_code' => 403]), ProxyLicenseException::class, 'license_invalid'];
        yield 'license missing' => [new MockResponse('{"error":{"code":"license_missing","message":"missing"}}', ['http_code' => 401]), ProxyLicenseException::class, 'license_missing'];
        yield 'rate limit' => [new MockResponse('{"error":{"code":"rate_limit_exceeded","message":"slow down"}}', ['http_code' => 429, 'response_headers' => ['Retry-After' => '12']]), ProxyRateLimitException::class, 'rate_limit_exceeded'];
        yield 'validation' => [new MockResponse('{"error":{"code":"validation_failed","message":"bad"}}', ['http_code' => 422]), ProxyException::class, 'validation_failed'];
        yield 'foreign index' => [new MockResponse('{"error":{"code":"index_not_allowed","message":"no"}}', ['http_code' => 403]), ProxyException::class, 'index_not_allowed'];
    }

    public function testRetryAfterIsExposed(): void
    {
        try {
            (new ProxyClient(new MockHttpClient(new MockResponse('{"error":{"code":"rate_limit_exceeded","message":"x"}}', ['http_code' => 429, 'response_headers' => ['Retry-After' => '12']])), 'https://p'))->capabilities();
        } catch (ProxyRateLimitException $e) {
            $this->assertSame(12, $e->getRetryAfter());
        }
    }

    public function testDegradedHealthReturnsTheDocument(): void
    {
        $health = (new ProxyClient(new MockHttpClient(new MockResponse('{"status":"degraded","backend":{"reachable":false}}', ['http_code' => 503])), 'https://p'))->health();

        $this->assertSame('degraded', $health['status']);
    }

    public function testLicenseStatus(): void
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $this->assertSame('https://p/api/v1/license', $url);
            $this->assertContains('X-ProSearch-License: ID', $options['headers']);

            return new MockResponse('{"license":{"valid":false,"reason":"license_invalid","message":"no"}}');
        });

        $this->assertFalse((new ProxyClient($client, 'https://p'))->getLicenseStatus('ID')['valid']);
    }
}
