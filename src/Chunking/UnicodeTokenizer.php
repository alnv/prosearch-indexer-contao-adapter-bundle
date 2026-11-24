<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Chunking;

final class UnicodeTokenizer implements TokenizerInterface
{
    private const int SLICE_BYTES = 65536;

    private readonly string $pattern;

    public function __construct(private readonly int $maxPieceLength = 5)
    {
        if ($maxPieceLength < 1) {
            throw new \InvalidArgumentException('The maximum piece length must be at least 1.');
        }

        $this->pattern = '/[\p{L}\p{M}\p{N}]{1,' . $maxPieceLength . '}|[^\s\p{L}\p{M}\p{N}\p{Z}\p{C}]/u';
    }

    public function tokenize(string $text): iterable
    {
        $length = \strlen($text);
        $offset = 0;

        while ($offset < $length) {
            $end = $this->sliceEnd($text, $offset, $length);
            $slice = substr($text, $offset, $end - $offset);

            if (false === preg_match_all($this->pattern, $slice, $matches, PREG_OFFSET_CAPTURE)) {
                preg_match_all('/\S+/', $slice, $matches, PREG_OFFSET_CAPTURE);
            }

            foreach ($matches[0] as [$token, $position]) {
                yield [$offset + $position, $offset + $position + \strlen($token)];
            }

            unset($matches, $slice);
            $offset = $end;
        }
    }

    public function count(string $text): int
    {
        $count = 0;

        foreach ($this->tokenize($text) as $ignored) {
            ++$count;
        }

        return $count;
    }

    private function sliceEnd(string $text, int $offset, int $length): int
    {
        $end = $offset + self::SLICE_BYTES;

        if ($end >= $length) {
            return $length;
        }

        $space = strrpos(substr($text, $offset, self::SLICE_BYTES), ' ');

        if (false !== $space && $space > 0) {
            return $offset + $space + 1;
        }

        // no whitespace in the slice: move back to the start of a UTF-8 character
        while ($end > $offset && (\ord($text[$end]) & 0xC0) === 0x80) {
            --$end;
        }

        return $end;
    }
}
