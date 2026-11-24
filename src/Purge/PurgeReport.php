<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Purge;

/**
 * Result of a complete reset of the search data (see PurgeElasticsearch).
 */
final class PurgeReport
{
    /**
     * Indices removed from the search engine.
     *
     * @var list<string>
     */
    public array $deletedIndices = [];

    /**
     * Indices that did not exist (HTTP 404 from the proxy).
     *
     * @var list<string>
     */
    public array $missingIndices = [];

    /**
     * Remote deletion was skipped on purpose (local reset only).
     */
    public bool $remoteSkipped = false;

    public int $documents = 0;
    public int $chunks = 0;
    public int $categories = 0;

    /**
     * Non-fatal problems, e.g. the second deletion pass after the local reset failed.
     *
     * @var list<string>
     */
    public array $warnings = [];

    public function summary(): string
    {
        $indices = $this->remoteSkipped
            ? 'search engine not touched'
            : \sprintf('%d indices deleted (%s), %d not present', \count($this->deletedIndices), implode(', ', $this->deletedIndices) ?: '-', \count($this->missingIndices));

        return \sprintf('%s; %d documents, %d chunks, %d categories deleted', $indices, $this->documents, $this->chunks, $this->categories);
    }
}
