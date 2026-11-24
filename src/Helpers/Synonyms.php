<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Helpers;

use Contao\Database;
use Contao\StringUtil;

class Synonyms
{
    public const int MAX_ALTERNATIVES = 10;

    private static ?array $rows = null;

    protected array $arrSynonyms = [];

    private int $maxTokens = 0;

    public function __construct(?array $rows = null)
    {
        foreach ($rows ?? self::loadRows() as $row) {
            $keyword = self::clean((string) ($row['keyword'] ?? ''));

            if ('' === $keyword) {
                continue;
            }

            $keywordKey = self::normalize($keyword);

            foreach (StringUtil::deserialize($row['synonyms'] ?? null, true) as $synonym) {
                $key = self::normalize(\is_string($synonym) ? $synonym : '');

                if ('' === $key || $key === $keywordKey) {
                    continue;
                }

                if (!\in_array($keyword, $this->arrSynonyms[$key] ?? [], true)) {
                    $this->arrSynonyms[$key][] = $keyword;
                }

                $this->maxTokens = max($this->maxTokens, substr_count($key, ' ') + 1);
            }
        }
    }

    public static function reset(): void
    {
        self::$rows = null;
    }

    public function expand(string $query, int $limit = self::MAX_ALTERNATIVES): array
    {
        $tokens = self::tokens($query);

        if ([] === $tokens || [] === $this->arrSynonyms) {
            return [];
        }

        $normalizedQuery = implode(' ', $tokens);
        $alternatives = [];
        $count = \count($tokens);

        for ($i = 0; $i < $count;) {
            $matched = 0;

            for ($length = min($this->maxTokens, $count - $i); $length > 0; --$length) {
                $phrase = implode(' ', \array_slice($tokens, $i, $length));

                if (!isset($this->arrSynonyms[$phrase])) {
                    continue;
                }

                foreach ($this->arrSynonyms[$phrase] as $keyword) {
                    // the query already contains the target term ("kfz zulassung" with "zulassung" → "KfZ")
                    if (str_contains(' '.$normalizedQuery.' ', ' '.self::normalize($keyword).' ')) {
                        continue;
                    }

                    $alternative = implode(' ', [...\array_slice($tokens, 0, $i), $keyword, ...\array_slice($tokens, $i + $length)]);
                    $key = self::normalize($alternative);

                    if ($key !== $normalizedQuery && !isset($alternatives[$key])) {
                        $alternatives[$key] = $alternative;
                    }
                }

                $matched = $length;
                break;
            }

            $i += max(1, $matched);
        }

        return \array_slice(array_values($alternatives), 0, max(0, $limit));
    }

    public function predict($strQuery)
    {
        return $this->expand((string) $strQuery, 1)[0] ?? $strQuery;
    }

    public function getSynonyms(): array
    {
        return $this->arrSynonyms;
    }

    public static function normalize(string $value): string
    {
        return implode(' ', self::tokens($value));
    }

    private static function tokens(string $value): array
    {
        $value = mb_strtolower(self::clean($value));
        $tokens = [];

        foreach (explode(' ', $value) as $token) {
            $token = (string) preg_replace('/^[\p{P}\p{S}]+|[\p{P}\p{S}]+$/u', '', $token);

            if ('' !== $token) {
                $tokens[] = $token;
            }
        }

        return $tokens;
    }

    private static function clean(string $value): string
    {
        $value = StringUtil::decodeEntities($value);
        $value = str_replace('\/', '/', $value);

        return Text::tokenize($value);
    }

    private static function loadRows(): array
    {
        if (null === self::$rows) {
            self::$rows = Database::getInstance()
                ->prepare("SELECT keyword, synonyms FROM tl_synonyms WHERE disable!='1' ORDER BY id")
                ->execute()
                ->fetchAllAssoc();
        }

        return self::$rows;
    }
}
