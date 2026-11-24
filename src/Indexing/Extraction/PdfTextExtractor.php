<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\Extraction;

use Smalot\PdfParser\Config;
use Smalot\PdfParser\Parser;

class PdfTextExtractor
{
    /**
     * Rough memory factor of the parser in relation to the file size (measured with typical
     * office PDFs incl. images, images are dropped by retainImageContent=false).
     */
    private const int MEMORY_FACTOR = 12;

    private const int MEMORY_RESERVE = 32 * 1024 * 1024;

    public function __construct(
        private readonly int  $maxFileSize = 5 * 1024 * 1024,
        private readonly int  $maxTextBytes = 1000000,
        private readonly int  $maxPages = 0,
        private readonly int  $decodeMemoryLimit = 64 * 1024 * 1024,
        private readonly ?int $memoryLimit = null,
    )
    {
    }

    /**
     * @throws DocumentExtractionException
     */
    public function extract(string $path): string
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new DocumentExtractionException(\sprintf('The file "%s" does not exist or is not readable.', basename($path)), DocumentExtractionException::NOT_READABLE);
        }

        $size = (int)filesize($path);

        if ($size > $this->maxFileSize) {
            throw new DocumentExtractionException(\sprintf('The file is %s, at most %s are parsed (indexing.pdf_max_file_size).', self::formatBytes($size), self::formatBytes($this->maxFileSize)), DocumentExtractionException::TOO_LARGE);
        }

        if (0 === $size) {
            throw new DocumentExtractionException('The file is empty.', DocumentExtractionException::INVALID);
        }

        $this->assertEnoughMemory($size);

        $config = new Config();
        $config->setRetainImageContent(false);
        $config->setDecodeMemoryLimit($this->decodeMemoryLimit);

        try {
            $content = file_get_contents($path);

            if (false === $content) {
                throw new DocumentExtractionException('The file could not be read.', DocumentExtractionException::NOT_READABLE);
            }

            if (!str_starts_with(ltrim(substr($content, 0, 1024)), '%PDF')) {
                throw new DocumentExtractionException('The file is not a PDF document (missing %PDF header).', DocumentExtractionException::INVALID);
            }

            $document = (new Parser([], $config))->parseContent($content);
            unset($content);

            $text = '';
            $pageNumber = 0;

            foreach ($document->getPages() as $page) {
                if ($this->maxPages > 0 && ++$pageNumber > $this->maxPages) {
                    break;
                }

                $text .= $page->getText() . "\n";

                if (\strlen($text) > $this->maxTextBytes) {
                    break;
                }
            }
        } catch (DocumentExtractionException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new DocumentExtractionException('The PDF could not be parsed: ' . $e->getMessage(), DocumentExtractionException::INVALID, $e);
        }

        return TextNormalizer::truncate(self::clean($text), $this->maxTextBytes);
    }

    /**
     * Joins words hyphenated at line ends and normalizes the whitespace of the PDF text layer.
     */
    public static function clean(string $text): string
    {
        $text = TextNormalizer::normalize($text);

        return (string)preg_replace('/(\p{Ll})-\n(\p{Ll})/u', '$1$2', $text);
    }

    private function assertEnoughMemory(int $fileSize): void
    {
        $limit = $this->memoryLimit ?? self::parseMemoryLimit((string)ini_get('memory_limit'));

        if ($limit <= 0) {
            return;
        }

        $required = $fileSize * self::MEMORY_FACTOR + self::MEMORY_RESERVE;
        $available = $limit - memory_get_usage(true);

        if ($required > $available) {
            throw new DocumentExtractionException(\sprintf('Parsing would need about %s memory, only %s are available (memory_limit).', self::formatBytes($required), self::formatBytes(max(0, $available))), DocumentExtractionException::INSUFFICIENT_MEMORY);
        }
    }

    public static function parseMemoryLimit(string $value): int
    {
        $value = trim($value);

        if ('' === $value || '-1' === $value) {
            return -1;
        }

        $number = (int)$value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }

    public static function formatBytes(int $bytes): string
    {
        return $bytes >= 1048576 ? round($bytes / 1048576, 1) . ' MB' : round($bytes / 1024) . ' KB';
    }
}
