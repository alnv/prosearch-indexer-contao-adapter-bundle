<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Tests\Integration\Indexing;

use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\LicenseProvider;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\ProxyClient;
use Alnv\ProSearchIndexerContaoAdapterBundle\Purge\PurgeElasticsearch;
use Alnv\ProSearchIndexerContaoAdapterBundle\Purge\PurgeException;
use Alnv\ProSearchIndexerContaoAdapterBundle\Search\IndexNameResolver;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class PurgeElasticsearchTest extends IndicesTestCase
{
    /**
     * @var list<array{method: string, url: string, license: string}>
     */
    private array $requests = [];

    /**
     * Index name => HTTP status of the DELETE (default 200).
     *
     * @var array<string, int|list<int>>
     */
    private array $status = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate();
        $this->connection->executeStatement("ALTER TABLE tl_page ADD dns VARCHAR(255) DEFAULT '' NOT NULL");
        $this->connection->executeStatement("UPDATE tl_page SET dns='other.test' WHERE id=10");
        $this->connection->executeStatement("CREATE TABLE tl_ps_categories (id INT UNSIGNED AUTO_INCREMENT NOT NULL, tstamp INT UNSIGNED DEFAULT 0, category VARCHAR(128) DEFAULT '' NOT NULL, translations BLOB DEFAULT NULL, exist CHAR(1) DEFAULT '' NOT NULL, PRIMARY KEY (id))");
        $this->connection->executeStatement(self::LOG_TABLE_SQL);

        $this->repository->save($this->document(text: $this->words(1500)));
        $this->repository->save($this->document('https://x.test/b.pdf', $this->words(20), ['docType' => 'pdf']));
        $this->repository->save($this->document('https://other.test/', 'Startseite', ['domain' => 'other.test', 'pageId' => 11, 'rootId' => 10]));
        $this->connection->insert('tl_ps_categories', ['category' => 'news', 'exist' => '1']);
        $this->connection->insert('tl_ps_categories', ['category' => 'pdf', 'exist' => '1']);
        $this->insertLog('kita', '2026-11-20 10:00', 8, 3);
    }

    public function testDeletesIndicesFirstThenAllLocalDataAndKeepsStatistics(): void
    {
        $this->assertGreaterThan(0, $this->connection->fetchOne('SELECT COUNT(*) FROM tl_indices_chunks'));
        $this->status['contao_search_sig_10'] = [404, 404];

        $report = $this->purge()->purge();

        $this->assertSame(['contao_search_sig_1'], $report->deletedIndices);
        $this->assertSame(['contao_search_sig_10'], $report->missingIndices);
        $this->assertSame(3, $report->documents);
        $this->assertSame(2, $report->categories);
        $this->assertGreaterThan(1, $report->chunks);
        $this->assertSame([], $report->warnings);

        foreach (['tl_indices', 'tl_indices_chunks', 'tl_ps_categories'] as $table) {
            $this->assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM '.$table), $table);
        }

        $this->assertSame(3, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM tl_search_stats_log'));
        $this->assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM tl_page WHERE type=\'root\''));

        // first pass + second pass per index, license by domain
        $this->assertSame(
            ['DELETE contao_search_sig_1 LIC-x.test', 'DELETE contao_search_sig_10 LIC-other.test', 'DELETE contao_search_sig_1 LIC-x.test', 'DELETE contao_search_sig_10 LIC-other.test'],
            array_map(static fn (array $r): string => $r['method'].' '.basename($r['url']).' '.$r['license'], $this->requests)
        );
    }

    public function testProxyErrorAbortsBeforeAnyLocalDeletion(): void
    {
        $this->status['contao_search_sig_10'] = 503;

        try {
            $this->purge()->purge();
            $this->fail('PurgeException expected');
        } catch (PurgeException $e) {
            $this->assertStringContainsString('contao_search_sig_10', $e->getMessage());
            $this->assertStringContainsString('Already deleted: contao_search_sig_1', $e->getMessage());
        }

        $this->assertSame(3, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM tl_indices'));
        $this->assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM tl_ps_categories'));
    }

    public function testUnreachableProxyAbortsBeforeAnyLocalDeletion(): void
    {
        $purge = $this->purge(new MockHttpClient(static fn () => throw new \Symfony\Component\HttpClient\Exception\TransportException('Connection refused')));

        $this->expectException(PurgeException::class);

        try {
            $purge->purge();
        } finally {
            $this->assertSame(3, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM tl_indices'));
        }
    }

    public function testWithoutCredentialsOnlyTheLocalResetIsPossible(): void
    {
        $purge = $this->purge(null, false);

        try {
            $purge->purge();
            $this->fail('PurgeException expected');
        } catch (PurgeException) {
            $this->assertSame(3, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM tl_indices'));
        }

        $report = $purge->purge(false);
        $this->assertTrue($report->remoteSkipped);
        $this->assertSame(3, $report->documents);
        $this->assertSame([], $this->requests);
        $this->assertSame(3, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM tl_search_stats_log'));
    }

    public function testSecondPassFailureIsAWarning(): void
    {
        $this->status['contao_search_sig_1'] = [200, 503];
        $this->status['contao_search_sig_10'] = [404, 200];

        $report = $this->purge()->purge();

        $this->assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM tl_indices'));
        $this->assertCount(1, $report->warnings);
        $this->assertStringContainsString('contao_search_sig_1', $report->warnings[0]);
        // recreated by a parallel transfer and deleted in the second pass
        $this->assertSame(['contao_search_sig_1', 'contao_search_sig_10'], $report->deletedIndices);
        $this->assertSame([], $report->missingIndices);
    }

    public function testFailureOnAnIndexWithoutLocalDocumentsIsOnlyAWarning(): void
    {
        // old root page with an unlicensed domain: no documents of it are stored locally
        $this->connection->executeStatement("INSERT INTO tl_page (id, pid, type, dns) VALUES (20, 0, 'root', 'old.test')");
        $this->status['contao_search_sig_20'] = 403;

        $report = $this->purge()->purge();

        $this->assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM tl_indices'));
        $this->assertCount(1, $report->warnings);
        $this->assertStringContainsString('contao_search_sig_20 (no local documents)', $report->warnings[0]);
        $this->assertNotContains('contao_search_sig_20', $report->deletedIndices);
        // not retried in the second pass
        $this->assertCount(1, array_filter($this->requests, static fn (array $r): bool => str_ends_with($r['url'], '_20')));
    }

    public function testUnavailableProxyAbortsAlsoForAnIndexWithoutLocalDocuments(): void
    {
        $this->connection->executeStatement("INSERT INTO tl_page (id, pid, type, dns) VALUES (20, 0, 'root', 'old.test')");
        $this->status['contao_search_sig_20'] = 503;

        try {
            $this->purge()->purge();
            $this->fail('PurgeException expected');
        } catch (PurgeException) {
            $this->assertSame(3, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM tl_indices'));
        }
    }

    public function testPreviewLists(): void
    {
        $purge = $this->purge();

        $this->assertSame(['contao_search_sig_1' => 'LIC-x.test', 'contao_search_sig_10' => 'LIC-other.test'], $purge->getIndices());
        $this->assertSame(['documents' => 3, 'chunks' => (int) $this->connection->fetchOne('SELECT COUNT(*) FROM tl_indices_chunks'), 'categories' => 2], $purge->countLocal());
        $this->assertSame([], $this->requests);
    }

    private function purge(?MockHttpClient $client = null, bool $credentials = true): PurgeElasticsearch
    {
        $licenses = new class($credentials) extends LicenseProvider {
            public function __construct(private readonly bool $configured)
            {
            }

            public function hasCredentials(): bool
            {
                return $this->configured;
            }

            public function getSignature(): string
            {
                return 'sig';
            }

            public function isSingleDocument(): bool
            {
                return false;
            }

            public function getLicenseIdentifier(?string $domain = null): string
            {
                // root 1 has no dns: falls back to the first key (x.test)
                return 'LIC-'.($domain ?: 'x.test');
            }
        };

        $client ??= new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $license = '';

            foreach ($options['headers'] ?? [] as $header) {
                if (str_starts_with(strtolower($header), strtolower(ProxyClient::LICENSE_HEADER).':')) {
                    $license = trim(substr($header, \strlen(ProxyClient::LICENSE_HEADER) + 1));
                }
            }

            $this->requests[] = ['method' => $method, 'url' => $url, 'license' => $license];
            $index = basename(parse_url($url, PHP_URL_PATH));
            $status = $this->status[$index] ?? 200;

            if (\is_array($status)) {
                $status = array_shift($this->status[$index]) ?? 200;
            }

            $body = $status < 300 ? ['acknowledged' => true] : ['error' => ['code' => 'http_'.$status, 'message' => 'HTTP '.$status]];

            return new MockResponse(json_encode($body), ['http_code' => $status]);
        });

        return new PurgeElasticsearch($this->connection, new ProxyClient($client, 'https://proxy.test'), $licenses, new IndexNameResolver($licenses));
    }
}
