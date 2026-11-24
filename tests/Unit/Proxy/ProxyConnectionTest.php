<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Tests\Unit\Proxy;

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Credentials;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\LicenseProvider;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\ProxyClient;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Proxy URL / API key from the back end credentials (tl_search_credentials) with fallback to the configuration.
 */
final class ProxyConnectionTest extends TestCase
{
    public function testUsesProxyUrlAndApiKeyFromCredentials(): void
    {
        $requests = [];
        $proxy = new ProxyClient($this->recorder($requests), 'https://configured.example', 'config-key', true, 30.0, null, $this->credentials(['proxyUrl' => 'http://127.0.0.1:8000/', 'proxyApiKey' => 'backend-key']));

        $proxy->search('LIC', 'contao_search_a', ['query' => 'x']);

        $this->assertSame('http://127.0.0.1:8000', $proxy->getBaseUrl());
        $this->assertSame(ProxyClient::SOURCE_CREDENTIALS, $proxy->getConnectionSource());
        $this->assertSame('POST http://127.0.0.1:8000/api/v1/indices/contao_search_a/search', $requests[0]['line']);
        $this->assertContains('X-Api-Key: backend-key', $requests[0]['headers']);
        $this->assertContains('X-ProSearch-License: LIC', $requests[0]['headers']);
    }

    public function testFallsBackToConfigurationWhenFieldIsEmpty(): void
    {
        $requests = [];
        $proxy = new ProxyClient($this->recorder($requests), 'https://configured.example/', 'config-key', true, 30.0, null, $this->credentials(['proxyUrl' => '', 'proxyApiKey' => '']));

        $proxy->capabilities();

        $this->assertSame(ProxyClient::SOURCE_CONFIG, $proxy->getConnectionSource());
        $this->assertSame('GET https://configured.example/api/v1/search/capabilities', $requests[0]['line']);
        $this->assertContains('X-Api-Key: config-key', $requests[0]['headers']);
    }

    public function testFallsBackWithoutCredentialsRowOrOnInvalidStoredUrl(): void
    {
        $requests = [];
        (new ProxyClient($this->recorder($requests), 'https://configured.example', '', true, 30.0, null, $this->credentials(false)))->health();
        (new ProxyClient($this->recorder($requests), 'https://configured.example', '', true, 30.0, null, $this->credentials(['proxyUrl' => 'ftp://nope'])))->health();

        $this->assertSame('GET https://configured.example/api/v1/health', $requests[0]['line']);
        $this->assertSame('GET https://configured.example/api/v1/health', $requests[1]['line']);
    }

    public function testFallsBackWhenTheDatabaseCannotBeRead(): void
    {
        $requests = [];
        $broken = new class(new class extends Credentials {
            public function getCredentials(): array|bool
            {
                throw new \RuntimeException('Table tl_search_credentials does not exist');
            }
        }) extends LicenseProvider {
        };

        (new ProxyClient($this->recorder($requests), 'https://configured.example', '', true, 30.0, null, $broken))->health();

        $this->assertSame('GET https://configured.example/api/v1/health', $requests[0]['line']);
    }

    public function testPicksUpChangedCredentialsAfterReset(): void
    {
        $requests = [];
        $row = ['proxyUrl' => 'https://old.example'];
        $reader = new class($row) extends Credentials {
            public function __construct(public array $row)
            {
            }

            public function getCredentials(): array|bool
            {
                return $this->row;
            }
        };
        $credentials = new LicenseProvider($reader);
        $proxy = new ProxyClient($this->recorder($requests), 'https://configured.example', '', true, 30.0, null, $credentials);

        $proxy->health();
        $reader->row = ['proxyUrl' => 'https://new.example'];
        $credentials->reset();
        $proxy->health();

        $this->assertSame('GET https://old.example/api/v1/health', $requests[0]['line']);
        $this->assertSame('GET https://new.example/api/v1/health', $requests[1]['line']);
    }

    public function testWithConnectionUsesTheGivenValues(): void
    {
        $requests = [];
        $proxy = (new ProxyClient($this->recorder($requests), 'https://configured.example', 'config-key', true, 30.0, null, $this->credentials(['proxyUrl' => 'https://stored.example'])))
            ->withConnection('https://unsaved.example/api/v1/', '');

        $proxy->getLicenseStatus('LIC');

        $this->assertSame('GET https://unsaved.example/api/v1/license', $requests[0]['line']);
        $this->assertContains('X-Api-Key: config-key', $requests[0]['headers'], 'empty key falls back to the configured key');
    }

    #[DataProvider('urlProvider')]
    public function testNormalizeBaseUrl(string $input, ?string $expected): void
    {
        if (null === $expected) {
            $this->expectException(\InvalidArgumentException::class);
        }

        $this->assertSame($expected, ProxyClient::normalizeBaseUrl($input));
    }

    public static function urlProvider(): iterable
    {
        yield 'trailing slash' => ['http://127.0.0.1:8000/', 'http://127.0.0.1:8000'];
        yield 'api suffix' => [' https://proxy.example.org/api/v1/ ', 'https://proxy.example.org'];
        yield 'sub directory' => ['https://example.org/prosearch', 'https://example.org/prosearch'];
        yield 'no scheme' => ['proxy.example.org', null];
        yield 'other scheme' => ['ftp://proxy.example.org', null];
        yield 'credentials' => ['https://user:pass@proxy.example.org', null];
        yield 'query' => ['https://proxy.example.org/?x=1', null];
    }

    /**
     * @param list<array{line: string, headers: list<string>}> $requests
     */
    private function recorder(array &$requests): MockHttpClient
    {
        return new MockHttpClient(function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = ['line' => $method.' '.$url, 'headers' => $options['headers']];

            return new MockResponse('{"status":"ok","license":{"valid":true}}');
        });
    }

    private function credentials(array|false $row): LicenseProvider
    {
        return new LicenseProvider(new class($row) extends Credentials {
            public function __construct(private readonly array|false $row)
            {
            }

            public function getCredentials(): array|bool
            {
                return $this->row;
            }
        });
    }
}
