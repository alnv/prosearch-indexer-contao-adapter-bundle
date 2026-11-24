<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Tests\Unit\Chunking;

use Alnv\ProSearchIndexerContaoAdapterBundle\Chunking\Chunk;
use Alnv\ProSearchIndexerContaoAdapterBundle\Chunking\TextChunker;
use Alnv\ProSearchIndexerContaoAdapterBundle\Chunking\UnicodeTokenizer;
use PHPUnit\Framework\TestCase;

final class TextChunkerTest extends TestCase
{
    public function testEmptyAndWhitespaceTextsHaveNoChunks(): void
    {
        $this->assertSame([], $this->chunks(''));
        $this->assertSame([], $this->chunks(" \n\t "));
    }

    public function testDocumentBelowTheLimitIsOneChunk(): void
    {
        $chunks = $this->chunks($this->words(599));

        $this->assertCount(1, $chunks);
        $this->assertSame(599, $chunks[0]->tokenCount);
        $this->assertSame($this->words(599), $chunks[0]->text);
    }

    public function testDocumentWithExactly600TokensIsOneChunk(): void
    {
        $chunks = $this->chunks($this->words(600));

        $this->assertCount(1, $chunks);
        $this->assertSame(600, $chunks[0]->tokenCount);
    }

    public function testDocumentAbove600TokensIsSplitWith60TokensOverlap(): void
    {
        $chunks = $this->chunks($this->words(601));

        $this->assertCount(2, $chunks);
        $this->assertSame(600, $chunks[0]->tokenCount);
        // 60 tokens overlap + 1 new token
        $this->assertSame(61, $chunks[1]->tokenCount);
        $this->assertSame('w541', explode(' ', $chunks[1]->text)[0]);
        $this->assertSame('w601', $this->lastWord($chunks[1]));
    }

    public function testConsecutiveChunksOverlapBy60Tokens(): void
    {
        $chunks = $this->chunks($this->words(2000));

        // 600 + 540 + 540 + 540 >= 2000 → 4 chunks
        $this->assertCount(4, $chunks);

        for ($i = 1; $i < \count($chunks); ++$i) {
            $previous = explode(' ', $chunks[$i - 1]->text);
            $current = explode(' ', $chunks[$i]->text);

            $this->assertSame(\array_slice($previous, -60), \array_slice($current, 0, 60), 'chunk '.$i);
            $this->assertLessThanOrEqual(600, $chunks[$i]->tokenCount);
        }

        $this->assertSame('w2000', $this->lastWord(end($chunks)));
    }

    public function testChunksKeepTheOrderAndPositions(): void
    {
        $chunks = $this->chunks($this->words(3000));

        foreach ($chunks as $i => $chunk) {
            $this->assertSame($i, $chunk->position);

            if ($i > 0) {
                $this->assertGreaterThan($chunks[$i - 1]->startOffset, $chunk->startOffset);
            }
        }
    }

    public function testChunksAreSubstringsOfTheOriginalText(): void
    {
        $text = "Führerschein beantragen:\nKosten 43,40 €. Die Kindertagesstätte (Kita) öffnet um 7:00 Uhr!";
        $chunker = new TextChunker(new UnicodeTokenizer(), 5, 2);

        foreach ($chunker->chunk($text) as $chunk) {
            $this->assertSame(substr($text, $chunk->startOffset, $chunk->endOffset - $chunk->startOffset), $chunk->text);
            $this->assertLessThanOrEqual(5, $chunk->tokenCount);
            $this->assertTrue(mb_check_encoding($chunk->text, 'UTF-8'));
        }
    }

    public function testLimitIsCountedInTokensNotWords(): void
    {
        // "Kindertagesstätte" = 4 tokens of at most 5 characters
        $text = trim(str_repeat('Kindertagesstätte ', 300));
        $chunks = $this->chunks($text);

        $this->assertSame(1200, (new UnicodeTokenizer())->count($text));
        $this->assertCount(3, $chunks);
        $this->assertSame(600, $chunks[0]->tokenCount);
    }

    public function testVeryLargeDocumentIsChunkedWithBoundedMemory(): void
    {
        $text = str_repeat('Die Kreisverwaltung informiert über Kitas, Schulen und den Bürgerservice. ', 30000);
        $before = memory_get_usage();
        $count = 0;
        $tokens = 0;

        foreach ((new TextChunker(new UnicodeTokenizer()))->chunk($text) as $chunk) {
            ++$count;
            $tokens = max($tokens, $chunk->tokenCount);
        }

        $this->assertGreaterThan(900, $count);
        $this->assertSame(600, $tokens);
        $this->assertLessThan(16 * 1024 * 1024, memory_get_peak_usage() - $before);
    }

    public function testInvalidConfigurationIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new TextChunker(new UnicodeTokenizer(), 600, 600);
    }

    public function testTokenizerHandlesSliceBoundariesAndInvalidUtf8(): void
    {
        $tokenizer = new UnicodeTokenizer();
        // 70 KB without spaces near the slice border: tokens must not be cut
        $text = str_repeat('a', 65530).'ääääääää '.str_repeat('b ', 10);

        $tokens = iterator_to_array($tokenizer->tokenize($text), false);
        foreach ($tokens as [$start, $end]) {
            $this->assertTrue(mb_check_encoding(substr($text, $start, $end - $start), 'UTF-8'));
        }

        $this->assertSame(3, $tokenizer->count("ab\xFF cd ef"));
    }

    /**
     * @return list<Chunk>
     */
    private function chunks(string $text): array
    {
        return iterator_to_array((new TextChunker(new UnicodeTokenizer(), 600, 60))->chunk($text), false);
    }

    private function words(int $count): string
    {
        return implode(' ', array_map(static fn (int $i): string => 'w'.$i, range(1, $count)));
    }

    private function lastWord(Chunk $chunk): string
    {
        $words = explode(' ', $chunk->text);

        return end($words);
    }
}
