<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Indexing;

/**
 * Transfer state of a tl_indices record (column sync_status); independent of "state" (active/delete).
 */
final class SyncStatus
{
    /** waiting for the next queue run (new, changed, marked for deletion or retry) */
    public const string PENDING = 'pending';

    /** the search index contains the current revision */
    public const string DONE = 'done';

    /** given up after the maximum number of attempts; retried after the next change or "--retry-failed" */
    public const string FAILED = 'failed';

    public static function all(): array
    {
        return [self::PENDING, self::DONE, self::FAILED];
    }
}
