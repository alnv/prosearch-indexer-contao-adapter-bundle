<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Proxy;

/**
 * Search engine behind the proxy as cached in tl_search_credentials.
 */
final class EngineState
{
    public function __construct(
        public readonly string $type,
        public readonly string $version,
        public readonly bool   $supportsChunks,
        public readonly bool   $supportsVectors,
        public readonly int    $checkedAt,
        public readonly string $error = '',
        public readonly string $connection = '',
    )
    {
    }

    public static function unknown(string $error = '', int $checkedAt = 0, string $connection = ''): self
    {
        return new self('', '', false, false, $checkedAt, $error, $connection);
    }

    /**
     * @param array<string, mixed> $credentials row of tl_search_credentials
     */
    public static function fromCredentials(array $credentials): self
    {
        return new self(
            (string)($credentials['engineType'] ?? ''),
            (string)($credentials['engineVersion'] ?? ''),
            '1' === (string)($credentials['engineChunks'] ?? ''),
            '1' === (string)($credentials['engineVectors'] ?? ''),
            (int)($credentials['engineCheckedAt'] ?? 0),
            (string)($credentials['engineError'] ?? ''),
            (string)($credentials['engineConnection'] ?? ''),
        );
    }

    /**
     * @return array<string, string|int>
     */
    public function toCredentials(): array
    {
        return [
            'engineType' => $this->type,
            'engineVersion' => $this->version,
            'engineChunks' => $this->supportsChunks ? '1' : '',
            'engineVectors' => $this->supportsVectors ? '1' : '',
            'engineCheckedAt' => $this->checkedAt,
            'engineError' => mb_substr($this->error, 0, 255),
            'engineConnection' => $this->connection,
        ];
    }

    public function describe(): string
    {
        if ('' === $this->type) {
            return 'unbekannt' . ('' !== $this->error ? ' (' . $this->error . ')' : '');
        }

        $name = match ($this->type) {
            'opensearch' => 'OpenSearch',
            'elasticsearch' => 'Elasticsearch',
            default => $this->type,
        };

        return trim($name . ' ' . $this->version);
    }
}
