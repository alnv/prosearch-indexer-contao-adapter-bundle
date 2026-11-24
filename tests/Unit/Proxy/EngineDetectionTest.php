<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Tests\Unit\Proxy;

use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\Exception\ProxyUnavailableException;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\ProxyClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class EngineDetectionTest extends TestCase
{
    public function testOpenSearch3(): void
    {
        $engine = $this->client([new MockResponse('{"api_version":"v1","product":"opensearch","version":"3.0.0","reachable":true,"supports":{"nested":true,"chunks":true,"vectors":true}}')])->engine();

        $this->assertSame('opensearch', $engine['product']);
        $this->assertTrue($engine['supports']['chunks']);
        $this->assertTrue($engine['supports']['vectors']);
    }

    public function testElasticsearch(): void
    {
        $engine = $this->client([new MockResponse('{"product":"elasticsearch","version":"8.15.0","reachable":true,"supports":{"nested":true,"chunks":false,"vectors":false}}')])->engine();

        $this->assertFalse($engine['supports']['chunks']);
    }

    public function testUnreachableClusterBehindTheProxy(): void
    {
        $engine = $this->client([new MockResponse('{"product":"unknown","version":"","reachable":false,"supports":{"nested":false,"chunks":false,"vectors":false},"error":"down"}', ['http_code' => 503])])->engine();

        $this->assertFalse($engine['reachable']);
        $this->assertFalse($engine['supports']['chunks']);
        $this->assertSame('down', $engine['error']);
    }

    public function testUnreachableProxyThrows(): void
    {
        $this->expectException(ProxyUnavailableException::class);

        $this->client([new MockResponse('', ['error' => 'Connection refused'])])->engine();
    }

    /**
     * Proxies before 4.0 have no engine endpoint: OpenSearch 3 is derived from the health check,
     * OpenSearch 2 and Elasticsearch get no chunks.
     */
    public function testFallbackToTheHealthCheckOfOlderProxies(): void
    {
        $notFound = '{"error":{"code":"not_found","message":"Unknown endpoint"}}';

        foreach ([['opensearch', '3.1.0', true], ['opensearch', '2.19.0', false], ['elasticsearch', '8.15.0', false]] as [$distribution, $version, $chunks]) {
            $engine = $this->client([
                new MockResponse($notFound, ['http_code' => 404]),
                new MockResponse(json_encode(['status' => 'ok', 'backend' => ['type' => 'opensearch', 'reachable' => true, 'distribution' => $distribution, 'version' => $version]])),
            ])->engine();

            $this->assertSame($chunks, $engine['supports']['chunks'], $distribution.' '.$version);
            $this->assertFalse($engine['supports']['vectors']);
        }
    }

    /**
     * @param list<MockResponse> $responses
     */
    private function client(array $responses): ProxyClient
    {
        return new ProxyClient(new MockHttpClient($responses), 'https://proxy.test');
    }
}
