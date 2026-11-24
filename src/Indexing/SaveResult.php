<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Indexing;

/**
 * Outcome of storing a captured document.
 */
final class SaveResult
{
    public const string CREATED = 'created';
    public const string UPDATED = 'updated';
    public const string UNCHANGED = 'unchanged';
    public const string PREVENTED = 'prevented';

    public function __construct(
        public readonly string $status,
        public readonly int    $id = 0,
        public readonly int    $chunkCount = 0,
        public readonly bool   $chunksRebuilt = false,
    )
    {
    }

    public function isQueued(): bool
    {
        return self::CREATED === $this->status || self::UPDATED === $this->status;
    }
}
