<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\Queue;

/**
 * Result of a queue run.
 */
final class SyncReport
{
    public int $batches = 0;
    public int $indexed = 0;
    public int $deleted = 0;
    /** records that changed while they were sent (stay pending) */
    public int $superseded = 0;
    /** document errors (retried later) */
    public int $failed = 0;
    /** document errors after the last attempt */
    public int $failedPermanently = 0;
    /** records postponed because the proxy was not reachable */
    public int $postponed = 0;
    public int $chunksSent = 0;
    public bool $proxyUnavailable = false;
    public bool $timeLimitReached = false;

    /**
     * @var list<string>
     */
    public array $errors = [];

    /**
     * @var list<string>
     */
    public array $warnings = [];

    public function processed(): int
    {
        return $this->indexed + $this->deleted + $this->superseded + $this->failed + $this->failedPermanently + $this->postponed;
    }

    public function addError(string $error): void
    {
        if (\count($this->errors) < 50 && !\in_array($error, $this->errors, true)) {
            $this->errors[] = $error;
        }
    }

    public function addWarning(string $warning): void
    {
        if (\count($this->warnings) < 50 && !\in_array($warning, $this->warnings, true)) {
            $this->warnings[] = $warning;
        }
    }

    public function summary(): string
    {
        return \sprintf(
            '%d indexed, %d deleted, %d superseded, %d failed (%d permanently), %d postponed, %d chunks',
            $this->indexed, $this->deleted, $this->superseded, $this->failed + $this->failedPermanently, $this->failedPermanently, $this->postponed, $this->chunksSent,
        );
    }
}
