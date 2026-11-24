<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Tests\Unit\Proxy;

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Credentials;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\LicenseProvider;
use Alnv\ProSearchIndexerContaoAdapterBundle\Search\IndexNameResolver;
use PHPUnit\Framework\TestCase;

final class LicenseProviderTest extends TestCase
{
    public function testIdentifierForMatchingDomain(): void
    {
        $provider = $this->provider(['keys' => serialize([['key' => 'K1', 'domain' => 'a.de'], ['key' => 'K2', 'domain' => 'b.de']]), 'authToken' => 'tok']);

        $this->assertSame(['K2', 'b.de', 'tok'], $this->decode($provider->getLicenseIdentifier('https://www.b.de/path')));
    }

    public function testFallsBackToTheFirstKeyLikeBefore(): void
    {
        $provider = $this->provider(['keys' => serialize([['key' => 'K1', 'domain' => 'a.de']])]);

        $this->assertSame(['K1', 'c.de', ''], $this->decode($provider->getLicenseIdentifier('c.de')));
    }

    public function testUsesRequestHostAndKeyDomainWithoutHost(): void
    {
        $provider = $this->provider(['keys' => serialize([['key' => 'K1', 'domain' => 'www.a.de']])], 'www.a.de:8080');
        $this->assertSame(['K1', 'a.de:8080', ''], $this->decode($provider->getLicenseIdentifier()));

        $cli = $this->provider(['keys' => serialize([['key' => 'K1', 'domain' => 'www.a.de']])], '');
        $this->assertSame(['K1', 'a.de', ''], $this->decode($cli->getLicenseIdentifier()), 'CLI uses the domain of the first key');
    }

    public function testSingleKeyFieldAndMissingCredentials(): void
    {
        $this->assertSame(['SINGLE', 'x.de', ''], $this->decode($this->provider(['key' => 'SINGLE'])->getLicenseIdentifier('x.de')));
        $this->assertSame('', $this->provider(false)->getLicenseIdentifier('x.de'));
    }

    public function testEncodingIsCompatibleWithAdapter3x(): void
    {
        $provider = $this->provider([]);

        // value produced by Authorization::encodeLicense() of adapter 3.x
        $this->assertSame(rtrim(strtr(base64_encode('L::d.de::t+/'), '+/', '-_'), '='), $provider->encodeLicense('L', 'd.de', 't+/'));
        $this->assertSame('example.org:8080', $provider->parseDomain('https://WWW.example.org:8080/x'));
        $this->assertSame('example.org', $provider->parseDomain('www.example.org'));
    }

    public function testIndexNames(): void
    {
        $this->assertSame('contao_search_si1_5', (new IndexNameResolver($this->provider(['signature' => 'si1'])))->getIndexName(5));
        $this->assertSame('contao_search_si1', (new IndexNameResolver($this->provider(['signature' => 'si1'])))->getIndexName(0));
        $this->assertSame('contao_search_si1_single', (new IndexNameResolver($this->provider(['signature' => 'si1', 'singleDocument' => '1'])))->getIndexName(5));
    }

    /**
     * @param array<string, mixed>|false $credentials
     */
    private function provider(array|false $credentials, string $host = 'host.test'): LicenseProvider
    {
        $reader = new class($credentials) extends Credentials {
            public function __construct(private readonly array|false $row)
            {
            }

            public function getCredentials(): array|bool
            {
                return $this->row;
            }
        };

        return new class($reader, $host) extends LicenseProvider {
            public function __construct(Credentials $reader, private readonly string $host)
            {
                parent::__construct($reader);
            }

            protected function getRequestHost(): string
            {
                return $this->host;
            }
        };
    }

    /**
     * @return list<string>
     */
    private function decode(string $identifier): array
    {
        return explode('::', base64_decode(strtr($identifier, '-_', '+/'), false));
    }
}
