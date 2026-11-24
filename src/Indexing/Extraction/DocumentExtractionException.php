<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\Extraction;

/**
 * A single document could not be read; the crawl continues with the next document.
 */
class DocumentExtractionException extends \RuntimeException
{
    public const string TOO_LARGE = 'too_large';
    public const string NOT_READABLE = 'not_readable';
    public const string INSUFFICIENT_MEMORY = 'insufficient_memory';
    public const string INVALID = 'invalid';

    public function __construct(string $message, private readonly string $reason, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function getReason(): string
    {
        return $this->reason;
    }
}
