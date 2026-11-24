<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Chunking;

interface TokenizerInterface
{
    /**
     * @return iterable<array{0: int, 1: int}> [start byte offset, end byte offset (exclusive)] in text order
     */
    public function tokenize(string $text): iterable;

    public function count(string $text): int;
}
