<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Stats;

use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SearchStatsExporter
{
    public const MODE_LOG = 'log';
    public const MODE_TERMS = 'terms';

    private const DEFAULT_LABELS = [
        'date' => 'Datum',
        'keyword' => 'Suchbegriff',
        'hits' => 'Treffer',
        'clicks' => 'Klicks',
        'urls' => 'Aufgerufene Seiten',
        'types' => 'Kontext',
        'source' => 'Quelle',
        'legacy' => 'Altdaten',
        'searches' => 'Suchanfragen',
        'avg_hits' => 'Ø Treffer',
        'zero' => 'Suchen ohne Treffer',
        'first' => 'Erste Suche',
        'last' => 'Letzte Suche',
    ];

    public function __construct(private readonly SearchStatsRepository $repository)
    {
    }

    public function createResponse(string $mode, ?int $start, int $end, array $labels = []): StreamedResponse
    {
        $mode = self::MODE_TERMS === $mode ? self::MODE_TERMS : self::MODE_LOG;
        $labels = array_merge(self::DEFAULT_LABELS, array_filter($labels, 'is_string'));

        $response = new StreamedResponse(function () use ($mode, $start, $end, $labels): void {
            @set_time_limit(0);
            $this->write(fopen('php://output', 'w'), $mode, $start, $end, $labels);
        });

        $filename = \sprintf('prosearch-%s-%s.csv', self::MODE_TERMS === $mode ? 'suchbegriffe' : 'suchprotokoll', date('Y-m-d-His'));

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename));
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    /**
     * Writes the CSV into a stream (public for tests).
     *
     * @param resource $stream
     * @param array<string, string> $labels
     */
    public function write($stream, string $mode, ?int $start, int $end, array $labels = []): void
    {
        $labels = array_merge(self::DEFAULT_LABELS, $labels);
        fwrite($stream, "\xEF\xBB\xBF");

        if (self::MODE_TERMS === $mode) {
            $this->put($stream, [$labels['keyword'], $labels['searches'], $labels['avg_hits'], $labels['zero'], $labels['clicks'], $labels['first'], $labels['last']]);

            foreach ($this->repository->iterateTerms($start, $end) as $row) {
                $this->put($stream, [$row['keyword'], $row['searches'], $this->decimal($row['avg_hits']), $row['zero'], $row['clicks'], $this->date($row['first']), $this->date($row['last'])]);
            }

            return;
        }

        $this->put($stream, [$labels['date'], $labels['keyword'], $labels['hits'], $labels['clicks'], $labels['urls'], $labels['types'], $labels['source'], $labels['legacy']]);

        foreach ($this->repository->iterateLog($start, $end) as $row) {
            $this->put($stream, [
                $this->date((int)$row['tstamp']),
                (string)$row['keyword'],
                (int)$row['hits'],
                (int)$row['clicks'],
                implode("\n", SearchStatsLogger::unserializeUrls($row['urls'] ?? null)),
                (string)$row['types'],
                (string)$row['source'],
                '1' === (string)$row['legacy'] ? 'x' : '',
            ]);
        }
    }

    private function put($stream, array $fields): void
    {
        fputcsv($stream, array_map($this->cell(...), $fields), ';', '"', '');
    }

    private function cell(string|int|float|null $value): string
    {
        if (\is_int($value) || \is_float($value)) {
            return (string)$value;
        }

        $value = (string)$value;

        return '' !== $value && \in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'" . $value : $value;
    }

    private function date(int $timestamp): string
    {
        return $timestamp > 0 ? date('d.m.Y H:i:s', $timestamp) : '';
    }

    private function decimal(float $value): string
    {
        return number_format($value, 1, ',', '');
    }
}
