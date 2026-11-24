<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\Extraction;

final class TextNormalizer
{
    public static function normalize(string $text): string
    {
        if ('' === $text) {
            return '';
        }

        $text = self::toUtf8($text);

        $text = str_replace(["\r\n", "\r", "\u{00AD}", "\u{200B}", "\u{200C}", "\u{200D}", "\u{2060}", "\u{FEFF}"], ["\n", "\n", '', '', '', '', '', ''], $text);
        // tabs, no-break and other unicode spaces become normal spaces; other control characters are removed
        $text = (string)preg_replace(['/[\t\x{00A0}\x{1680}\x{2000}-\x{200A}\x{202F}\x{205F}\x{3000}]/u', '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u'], [' ', ''], $text);
        $text = (string)preg_replace(['/ {2,}/', '/ *\n */', '/\n{2,}/'], [' ', "\n", "\n"], $text);

        return trim($text);
    }

    /**
     * Repairs invalid UTF-8 without losing characters: valid multibyte sequences are kept, single
     * invalid bytes (typically Windows-1252/ISO-8859-1 from PDF text layers, e.g. "\xDC" = "Ü")
     * are converted.
     */
    public static function toUtf8(string $text): string
    {
        if (mb_check_encoding($text, 'UTF-8')) {
            return $text;
        }

        $fixed = preg_replace_callback(
            '/[\xC2-\xDF][\x80-\xBF]|\xE0[\xA0-\xBF][\x80-\xBF]|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}|\xED[\x80-\x9F][\x80-\xBF]|\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2}|([\x80-\xFF])/',
            static fn(array $m): string => isset($m[1]) ? (string)mb_convert_encoding($m[1], 'UTF-8', 'Windows-1252') : $m[0],
            $text,
        );

        return null !== $fixed && mb_check_encoding($fixed, 'UTF-8') ? $fixed : mb_scrub($text, 'UTF-8');
    }

    /**
     * One line, single spaces (titles, headings, strong texts).
     */
    public static function singleLine(string $text): string
    {
        return trim((string)preg_replace('/\s+/u', ' ', self::normalize($text)));
    }

    /**
     * Cuts the text to at most $maxBytes bytes at a whitespace (or UTF-8 character) boundary.
     */
    public static function truncate(string $text, int $maxBytes): string
    {
        if (\strlen($text) <= $maxBytes) {
            return $text;
        }

        $cut = substr($text, 0, $maxBytes);
        $space = max(strrpos($cut, ' ') ?: 0, strrpos($cut, "\n") ?: 0);

        if ($space > $maxBytes * 0.9) {
            return rtrim(substr($cut, 0, $space));
        }

        return rtrim(mb_strcut($text, 0, $maxBytes, 'UTF-8'));
    }
}
