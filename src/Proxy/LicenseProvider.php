<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Proxy;

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Credentials;
use Contao\Environment;
use Contao\StringUtil;

class LicenseProvider
{
    /**
     * @var array<string, mixed>|false|null
     */
    private array|false|null $credentials = null;

    public function __construct(private readonly ?Credentials $credentialsReader = null)
    {
    }

    /**
     * Latest tl_search_credentials row or false if none exists.
     *
     * @return array<string, mixed>|false
     */
    public function getCredentials(): array|false
    {
        if (null === $this->credentials) {
            $this->credentials = ($this->credentialsReader ?? new Credentials())->getCredentials();
        }

        return $this->credentials;
    }

    /**
     * Forgets the cached credentials (e.g. after saving them in the back end).
     */
    public function reset(): void
    {
        $this->credentials = null;
    }

    public function hasCredentials(): bool
    {
        return false !== $this->getCredentials();
    }

    public function getSignature(): string
    {
        return (string)(($this->getCredentials() ?: [])['signature'] ?? '');
    }

    /**
     * Proxy base URL entered in the back end (empty → configured default PROSEARCH_PROXY_URL).
     */
    public function getProxyUrl(): string
    {
        return trim((string)(($this->getCredentials() ?: [])['proxyUrl'] ?? ''));
    }

    /**
     * Optional client API key of the proxy entered in the back end (empty → PROSEARCH_PROXY_API_KEY).
     */
    public function getProxyApiKey(): string
    {
        return trim((string)(($this->getCredentials() ?: [])['proxyApiKey'] ?? ''));
    }

    public function isSingleDocument(): bool
    {
        return (bool)(($this->getCredentials() ?: [])['singleDocument'] ?? false);
    }

    /**
     * License identifier for a domain.
     *
     * $domain null → current request host. Without request host (CLI, cron) the domain of the first
     * stored license key is used, so indexing/deleting from the command line works as well.
     */
    public function getLicenseIdentifier(?string $domain = null): string
    {
        $credentials = $this->getCredentials();

        if (false === $credentials) {
            return '';
        }

        $keys = StringUtil::deserialize($credentials['keys'] ?? null, true);
        $domain = $this->parseDomain((string)($domain ?? $this->getRequestHost()));

        if ('' === $domain && isset($keys[0]['domain'])) {
            $domain = $this->parseDomain((string)$keys[0]['domain']);
        }

        $license = [] === $keys ? (string)($credentials['key'] ?? '') : $this->pluckKeyFromKeysGlobalByDomain($keys, $domain);

        if ('' === $license) {
            return '';
        }

        return $this->encodeLicense($license, $domain, (string)($credentials['authToken'] ?? ''));
    }

    /**
     * "https://www.example.org:8080/path" → "example.org:8080".
     */
    public function parseDomain(string $domain): string
    {
        $hasScheme = null !== parse_url($domain, PHP_URL_SCHEME);
        $fragments = parse_url($hasScheme ? $domain : 'https://' . $domain) ?: [];
        $host = $fragments['host'] ?? '';
        $port = $fragments['port'] ?? '';

        if (!$host) {
            return $domain;
        }

        $host = preg_replace('/^www\./i', '', $host);

        return trim($host) . ($port ? ':' . $port : '');
    }

    /**
     * Key configured for the domain; falls back to the first key (as in adapter 3.x).
     *
     * @param list<array{key?: string, domain?: string}> $keys
     */
    public function pluckKeyFromKeysGlobalByDomain(array $keys, string $domain): string
    {
        $domain = $this->parseDomain($domain);

        foreach ($keys as $key) {
            if ($this->parseDomain((string)($key['domain'] ?? '')) == $domain) {
                return (string)($key['key'] ?? '');
            }
        }

        return (string)($keys[0]['key'] ?? '');
    }

    public function encodeLicense(string $license, string $domain, #[\SensitiveParameter] string $authToken = ''): string
    {
        return rtrim(strtr(base64_encode($license . '::' . $domain . '::' . $authToken), '+/', '-_'), '=');
    }

    protected function getRequestHost(): string
    {
        try {
            return (string)Environment::get('httpHost');
        } catch (\Throwable) {
            return '';
        }
    }
}
