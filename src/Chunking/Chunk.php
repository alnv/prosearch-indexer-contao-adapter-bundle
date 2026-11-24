<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Chunking;

final class Chunk
{
    public function __construct(
        public readonly int    $position,
        public readonly string $text,
        public readonly int    $tokenCount,
        public readonly int    $startOffset,
        public readonly int    $endOffset,
    )
    {
    }

    public function getHash(): string
    {
        return hash('sha256', $this->text);
    }
}
