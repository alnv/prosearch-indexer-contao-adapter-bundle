<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Chunking;

final class TextChunker
{
    public function __construct(
        private readonly TokenizerInterface $tokenizer,
        private readonly int                $maxTokens = 600,
        private readonly int                $overlap = 60,
    )
    {
        if ($maxTokens < 1) {
            throw new \InvalidArgumentException('The chunk size must be at least 1 token.');
        }

        if ($overlap < 0 || $overlap >= $maxTokens) {
            throw new \InvalidArgumentException('The overlap must be between 0 and the chunk size - 1.');
        }
    }

    public function getMaxTokens(): int
    {
        return $this->maxTokens;
    }

    public function getOverlap(): int
    {
        return $this->overlap;
    }

    /**
     * @return \Generator<int, Chunk>
     */
    public function chunk(string $text): \Generator
    {
        if ('' === trim($text)) {
            return;
        }

        /** @var list<array{0: int, 1: int}> $window */
        $window = [];
        $position = 0;
        // number of tokens at the start of the window that were already part of the previous chunk
        $carried = 0;

        foreach ($this->tokenizer->tokenize($text) as $token) {
            if (\count($window) === $this->maxTokens) {
                yield $this->createChunk($text, $window, $position++);

                $window = $this->overlap > 0 ? \array_slice($window, -$this->overlap) : [];
                $carried = \count($window);
            }

            $window[] = $token;
        }

        // the rest, unless it only consists of the overlap of the previous chunk
        if (\count($window) > $carried) {
            yield $this->createChunk($text, $window, $position);
        }
    }

    /**
     * @param list<array{0: int, 1: int}> $window
     */
    private function createChunk(string $text, array $window, int $position): Chunk
    {
        $start = $window[0][0];
        $end = $window[\count($window) - 1][1];

        return new Chunk($position, substr($text, $start, $end - $start), \count($window), $start, $end);
    }
}
