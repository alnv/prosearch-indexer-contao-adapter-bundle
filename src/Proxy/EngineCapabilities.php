<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Proxy;

use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\Exception\ProxyException;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

class EngineCapabilities
{
    private const int FAILURE_TTL = 900;

    private ?EngineState $state = null;

    public function __construct(
        private readonly ProxyClient      $proxy,
        private readonly LicenseProvider  $licenseProvider,
        private readonly Connection       $connection,
        private readonly ?LoggerInterface $logger = null,
        private readonly int              $ttl = 86400,
    )
    {
    }

    public function get(): EngineState
    {
        if (null !== $this->state && !$this->isStale($this->state)) {
            return $this->state;
        }

        $credentials = $this->licenseProvider->getCredentials();
        $state = \is_array($credentials) ? EngineState::fromCredentials($credentials) : EngineState::unknown();

        if ($this->isStale($state)) {
            return $this->refresh();
        }

        return $this->state = $state;
    }

    public function supportsChunks(): bool
    {
        return $this->get()->supportsChunks;
    }

    /**
     * Detects the engine again and stores the result in the credentials.
     */
    public function refresh(): EngineState
    {
        $now = time();
        $connection = $this->connectionKey();

        try {
            $engine = $this->proxy->engine(true);
            $state = new EngineState(
                'unknown' === $engine['product'] ? '' : $engine['product'],
                $engine['version'],
                $engine['reachable'] && $engine['supports']['chunks'],
                $engine['reachable'] && $engine['supports']['vectors'],
                $now,
                $engine['reachable'] ? '' : (string)($engine['error'] ?? 'The search engine is not reachable.'),
                $connection,
            );
        } catch (ProxyException $e) {
            $state = EngineState::unknown($e->getMessage(), $now, $connection);
        }

        if ('' !== $state->error) {
            $this->logger?->warning('ProSearch: the search engine could not be detected, chunks are not transferred: ' . $state->error);
        }

        $this->persist($state);

        return $this->state = $state;
    }

    private function isStale(EngineState $state): bool
    {
        if ($state->checkedAt <= 0 || $state->connection !== $this->connectionKey()) {
            return true;
        }

        $ttl = '' !== $state->error ? min(self::FAILURE_TTL, $this->ttl) : $this->ttl;

        return $state->checkedAt + $ttl < time();
    }

    private function connectionKey(): string
    {
        try {
            return substr(hash('sha256', $this->proxy->getBaseUrl()), 0, 32);
        } catch (\Throwable) {
            return '';
        }
    }

    private function persist(EngineState $state): void
    {
        $credentials = $this->licenseProvider->getCredentials();

        if (!\is_array($credentials) || !isset($credentials['id'])) {
            return;
        }

        try {
            $this->connection->update('tl_search_credentials', $state->toCredentials(), ['id' => (int)$credentials['id']]);
            $this->licenseProvider->reset();
        } catch (\Throwable $e) {
            // columns not yet created (database update pending): keep the result in memory only
            $this->logger?->notice('ProSearch: the engine detection could not be stored: ' . $e->getMessage());
        }
    }
}
