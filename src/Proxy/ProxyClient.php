<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Proxy;

use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\Exception\ProxyException;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\Exception\ProxyLicenseException;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\Exception\ProxyRateLimitException;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\Exception\ProxyUnavailableException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The only connection of the adapter to the search infrastructure: a client for the REST API (v1)
 * of alnv/prosearch-proxy-bundle. The adapter never talks to OpenSearch/Elasticsearch.
 *
 * Proxy URL and API key are resolved on every request:
 *  1. back end "Zugangsdaten" (tl_search_credentials.proxyUrl / proxyApiKey), if filled in,
 *  2. otherwise the bundle configuration (PROSEARCH_PROXY_URL / PROSEARCH_PROXY_API_KEY).
 *
 * The license identifier is transmitted in the X-ProSearch-License header (never in the URL), the
 * optional API key in X-Api-Key. Both are never logged.
 */
class ProxyClient
{
    public const string LICENSE_HEADER = 'X-ProSearch-License';
    public const string API_KEY_HEADER = 'X-Api-Key';

    public const string SOURCE_CREDENTIALS = 'credentials';
    public const string SOURCE_CONFIG = 'config';

    private readonly string $defaultBaseUrl;
    private readonly string $defaultApiKey;

    /**
     * Scoped HTTP client of the last resolved connection (rebuilt when URL or API key change).
     */
    private ?HttpClientInterface $scopedClient = null;
    private string $scopedClientKey = '';

    public function __construct(
        private readonly HttpClientInterface $client,
        string                               $baseUrl = 'https://elasticsearch.sineos.de',
        #[\SensitiveParameter] string        $apiKey = '',
        private readonly bool                $verifyTls = true,
        private readonly float               $timeout = 30.0,
        private readonly ?LoggerInterface    $logger = null,
        private readonly ?LicenseProvider    $credentials = null,
    )
    {
        $this->defaultBaseUrl = rtrim(trim($baseUrl), '/');
        $this->defaultApiKey = $apiKey;
    }

    /**
     * Copy of this client bound to a fixed connection, e.g. to check values entered in the back end
     * before they are saved.
     */
    public function withConnection(string $baseUrl, #[\SensitiveParameter] string $apiKey = ''): self
    {
        return new self($this->client, self::normalizeBaseUrl($baseUrl), $apiKey ?: $this->defaultApiKey, $this->verifyTls, $this->timeout, $this->logger);
    }

    /**
     * Effective proxy base URL (without /api/v1).
     */
    public function getBaseUrl(): string
    {
        return $this->resolveConnection()['url'];
    }

    /**
     * Where the effective URL comes from: self::SOURCE_CREDENTIALS or self::SOURCE_CONFIG.
     */
    public function getConnectionSource(): string
    {
        return $this->resolveConnection()['source'];
    }

    /**
     * "https://proxy.example.org/api/v1/" → "https://proxy.example.org".
     *
     * @throws \InvalidArgumentException if the value is not an absolute http(s) URL or contains credentials
     */
    public static function normalizeBaseUrl(string $url): string
    {
        $url = rtrim(trim($url), '/');
        $url = (string)preg_replace('~/api/v1$~i', '', $url);

        if (!filter_var($url, FILTER_VALIDATE_URL) || !\in_array(strtolower((string)parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            throw new \InvalidArgumentException('The ProSearch proxy URL must be an absolute http(s) URL, e.g. https://proxy.example.org');
        }

        if (null !== parse_url($url, PHP_URL_USER)) {
            throw new \InvalidArgumentException('Do not put credentials into the ProSearch proxy URL.');
        }

        if (null !== parse_url($url, PHP_URL_QUERY) || null !== parse_url($url, PHP_URL_FRAGMENT)) {
            throw new \InvalidArgumentException('The ProSearch proxy URL must not contain a query string or fragment.');
        }

        return $url;
    }

    public function health(): array
    {
        try {
            return $this->request('GET', 'api/v1/health');
        } catch (ProxyUnavailableException $e) {
            // 503 "degraded" still carries a health document
            if ($e->getDetails()['status'] ?? null) {
                return $e->getDetails();
            }

            throw $e;
        }
    }

    public function getLicenseStatus(#[\SensitiveParameter] string $license): array
    {
        return (array)($this->request('GET', 'api/v1/license', null, $license)['license'] ?? []);
    }

    public function createIndex(#[\SensitiveParameter] string $license, string $index, array $body, bool $ifNotExists = true): array
    {
        return $this->request('PUT', 'api/v1/indices/' . rawurlencode($index), $body + ['if_not_exists' => $ifNotExists], $license);
    }

    public function getIndex(#[\SensitiveParameter] string $license, string $index): array
    {
        return $this->request('GET', 'api/v1/indices/' . rawurlencode($index), null, $license);
    }

    public function getMapping(#[\SensitiveParameter] string $license, string $index): array
    {
        return (array)($this->request('GET', 'api/v1/indices/' . rawurlencode($index) . '/mapping', null, $license)['mappings'] ?? []);
    }

    public function updateMapping(#[\SensitiveParameter] string $license, string $index, array $mapping): array
    {
        return $this->request('PUT', 'api/v1/indices/' . rawurlencode($index) . '/mapping', $mapping, $license);
    }

    public function deleteIndex(#[\SensitiveParameter] string $license, string $index): bool
    {
        try {
            $this->request('DELETE', 'api/v1/indices/' . rawurlencode($index), null, $license);
        } catch (ProxyException $e) {
            if (404 === $e->getStatusCode()) {
                return false;
            }

            throw $e;
        }

        return true;
    }

    public function upsertDocument(#[\SensitiveParameter] string $license, string $index, string $id, array $document): array
    {
        return $this->request('PUT', 'api/v1/indices/' . rawurlencode($index) . '/documents/' . rawurlencode($id), $document, $license);
    }

    public function getDocument(#[\SensitiveParameter] string $license, string $index, string $id): ?array
    {
        try {
            return $this->request('GET', 'api/v1/indices/' . rawurlencode($index) . '/documents/' . rawurlencode($id), null, $license);
        } catch (ProxyException $e) {
            if (404 === $e->getStatusCode()) {
                return null;
            }

            throw $e;
        }
    }

    public function deleteDocument(#[\SensitiveParameter] string $license, string $index, string $id): bool
    {
        try {
            return (bool)($this->request('DELETE', 'api/v1/indices/' . rawurlencode($index) . '/documents/' . rawurlencode($id), null, $license)['deleted'] ?? false);
        } catch (ProxyException $e) {
            if (404 === $e->getStatusCode() && 'index_not_found' === $e->getErrorCode()) {
                return false;
            }

            throw $e;
        }
    }

    public function bulk(#[\SensitiveParameter] string $license, string $index, array $operations): array
    {
        return $this->request('POST', 'api/v1/indices/' . rawurlencode($index) . '/documents/_bulk', ['operations' => $operations], $license, [200, 207]);
    }

    public function search(#[\SensitiveParameter] string $license, string $index, array $request): array
    {
        return $this->request('POST', 'api/v1/indices/' . rawurlencode($index) . '/search', $request, $license);
    }

    public function searchStrategy(#[\SensitiveParameter] string $license, string $index, string $strategy, array $request): array
    {
        return $this->request('POST', 'api/v1/indices/' . rawurlencode($index) . '/search/' . rawurlencode($strategy), $request, $license);
    }

    /**
     * Product, version and features of the search engine behind the proxy.
     *
     * Proxies without the engine endpoint (before 4.0) are answered from the health check: chunk
     * support is then derived from product and version (OpenSearch >= 3), vectors are unknown (false).
     *
     * @return array{product: string, version: string, reachable: bool, supports: array{nested: bool, chunks: bool, vectors: bool}, error: string|null}
     * @throws ProxyException when the proxy is unreachable
     *
     */
    public function engine(bool $refresh = false): array
    {
        try {
            $data = $this->request('GET', 'api/v1/engine' . ($refresh ? '?refresh=1' : ''));
        } catch (ProxyUnavailableException $e) {
            // 503: the proxy works, the cluster is not reachable (engine document in the details)
            if (!isset($e->getDetails()['product'])) {
                throw $e;
            }

            $data = $e->getDetails();
        } catch (ProxyException $e) {
            if (404 !== $e->getStatusCode()) {
                throw $e;
            }

            $data = $this->engineFromHealth();
        }

        $supports = \is_array($data['supports'] ?? null) ? $data['supports'] : [];

        return [
            'product' => (string)($data['product'] ?? 'unknown'),
            'version' => (string)($data['version'] ?? ''),
            'reachable' => (bool)($data['reachable'] ?? false),
            'supports' => [
                'nested' => (bool)($supports['nested'] ?? false),
                'chunks' => (bool)($supports['chunks'] ?? false),
                'vectors' => (bool)($supports['vectors'] ?? false),
            ],
            'error' => isset($data['error']) && '' !== (string)$data['error'] ? (string)$data['error'] : null,
        ];
    }

    private function engineFromHealth(): array
    {
        $backend = (array)($this->health()['backend'] ?? []);
        $product = strtolower((string)($backend['distribution'] ?? $backend['type'] ?? 'unknown'));
        $version = (string)($backend['version'] ?? '');
        $reachable = (bool)($backend['reachable'] ?? false);
        $valid = $reachable && (bool)preg_match('/^\d+\.\d+/', $version);

        return [
            'product' => $product,
            'version' => $version,
            'reachable' => $reachable,
            'supports' => [
                'nested' => $valid,
                'chunks' => $valid && 'opensearch' === $product && (int)$version >= 3,
                'vectors' => false,
            ],
            'error' => $backend['error'] ?? null,
        ];
    }

    public function capabilities(): array
    {
        return $this->request('GET', 'api/v1/search/capabilities');
    }

    private function request(string $method, string $path, ?array $body = null, #[\SensitiveParameter] string $license = '', array $successCodes = []): array
    {
        $options = [];

        if ('' !== $license) {
            $options['headers'][self::LICENSE_HEADER] = $license;
        }

        if (null !== $body) {
            $options['body'] = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE);
            $options['headers']['Content-Type'] = 'application/json';
        }

        $connection = $this->resolveConnection();

        try {
            $response = $this->http($connection)->request($method, $path, $options);
            $status = $response->getStatusCode();
            $content = $response->getContent(false);
            $retryAfter = (int)($response->getHeaders(false)['retry-after'][0] ?? 0);
        } catch (TransportExceptionInterface $e) {
            $this->logger?->error('ProSearch proxy not reachable ({url}): {message}', ['url' => $connection['url'], 'message' => $e->getMessage()]);

            throw new ProxyUnavailableException('The ProSearch proxy is not reachable: ' . $e->getMessage(), 0, 'proxy_unreachable', [], $e);
        }

        $data = '' === $content ? [] : json_decode($content, true);

        if (!\is_array($data)) {
            throw new ProxyUnavailableException(\sprintf('The ProSearch proxy returned an invalid response (HTTP %d).', $status), $status, 'proxy_invalid_response');
        }

        if ($status < 300 || \in_array($status, $successCodes, true)) {
            return $data;
        }

        $error = \is_array($data['error'] ?? null) ? $data['error'] : [];
        $code = (string)($error['code'] ?? 'http_' . $status);
        $message = (string)($error['message'] ?? 'HTTP ' . $status);
        $details = (array)($error['details'] ?? []);

        // Health and engine endpoints answer 503 with a regular document (no error object)
        if (!\is_array($data['error'] ?? null) && 503 === $status) {
            throw new ProxyUnavailableException('The ProSearch proxy reports a degraded state.', $status, 'proxy_degraded', $data);
        }

        throw match (true) {
            429 === $status => new ProxyRateLimitException($message, $retryAfter, $details),
            (401 === $status || 403 === $status) && str_starts_with($code, 'license') => new ProxyLicenseException($message, $status, $code, $details),
            \in_array($status, [502, 503, 504], true) => new ProxyUnavailableException($message, $status, $code, $details),
            default => new ProxyException($message, $status, $code, $details),
        };
    }

    private function resolveConnection(): array
    {
        $url = '';
        $apiKey = '';

        if (null !== $this->credentials) {
            try {
                $url = $this->credentials->getProxyUrl();
                $apiKey = $this->credentials->getProxyApiKey();
            } catch (\Throwable $e) {
                // No database (yet), e.g. during installation: use the configured proxy
                $this->logger?->warning('ProSearch proxy credentials could not be read, using the configured proxy: {message}', ['message' => $e->getMessage()]);
            }
        }

        if ('' !== $url) {
            try {
                $url = self::normalizeBaseUrl($url);
            } catch (\InvalidArgumentException $e) {
                $this->logger?->error('Invalid ProSearch proxy URL in the credentials, using the configured proxy: {message}', ['message' => $e->getMessage()]);
                $url = '';
            }
        }

        return [
            'url' => '' !== $url ? $url : $this->defaultBaseUrl,
            'api_key' => '' !== $apiKey ? $apiKey : $this->defaultApiKey,
            'source' => '' !== $url ? self::SOURCE_CREDENTIALS : self::SOURCE_CONFIG,
        ];
    }

    private function http(array $connection): HttpClientInterface
    {
        $key = hash('xxh128', $connection['url'] . "\0" . $connection['api_key']);

        if (null !== $this->scopedClient && $key === $this->scopedClientKey) {
            return $this->scopedClient;
        }

        $headers = ['Accept' => 'application/json'];

        if ('' !== $connection['api_key']) {
            $headers[self::API_KEY_HEADER] = $connection['api_key'];
        }

        $this->scopedClientKey = $key;

        return $this->scopedClient = $this->client->withOptions([
            'base_uri' => $connection['url'] . '/',
            'timeout' => $this->timeout,
            'verify_peer' => $this->verifyTls,
            'verify_host' => $this->verifyTls,
            'headers' => $headers,
        ]);
    }
}
