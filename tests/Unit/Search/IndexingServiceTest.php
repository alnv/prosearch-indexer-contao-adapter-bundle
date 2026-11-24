<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Tests\Unit\Search;

use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\Exception\ProxyRateLimitException;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\LicenseProvider;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\ProxyClient;
use Alnv\ProSearchIndexerContaoAdapterBundle\Search\DocumentFactory;
use Alnv\ProSearchIndexerContaoAdapterBundle\Search\IndexingService;
use Alnv\ProSearchIndexerContaoAdapterBundle\Search\IndexNameResolver;
use Alnv\ProSearchIndexerContaoAdapterBundle\Search\MappingFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;

final class IndexingServiceTest extends TestCase
{
    public function testRetriesOnceAfterShortRateLimit(): void
    {
        $calls = 0;
        $result = $this->invokeRetry($this->service(5), function () use (&$calls): string {
            if (1 === ++$calls) {
                throw new ProxyRateLimitException('slow down', 1);
            }

            return 'ok';
        });

        $this->assertSame('ok', $result);
        $this->assertSame(2, $calls);
    }

    public function testDoesNotWaitForLongRateLimits(): void
    {
        $this->expectException(ProxyRateLimitException::class);
        $this->invokeRetry($this->service(5), static fn () => throw new ProxyRateLimitException('slow down', 60));
    }

    private function service(int $maxWait): IndexingService
    {
        $licenseProvider = new LicenseProvider();

        return new IndexingService(new ProxyClient(new MockHttpClient(), 'https://p'), $licenseProvider, new IndexNameResolver($licenseProvider), new MappingFactory(), new DocumentFactory(), null, $maxWait);
    }

    private function invokeRetry(IndexingService $service, callable $operation): mixed
    {
        return (new \ReflectionMethod($service, 'withRateLimitRetry'))->invoke($service, $operation);
    }
}
