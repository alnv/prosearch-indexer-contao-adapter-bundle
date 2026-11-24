<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Stats;

final class HourlyBuckets
{
    private array $localTimes = [];

    public function __construct(private readonly array $buckets, private readonly \DateTimeZone $timezone)
    {
    }

    public function series(\DateTimeImmutable $start, \DateTimeImmutable $end, string $granularity): array
    {
        $isWeek = 'week' === $granularity;
        $start = $start->setTimezone($this->timezone);
        $end = $end->setTimezone($this->timezone);
        $cursor = $this->bucketStart($start, $isWeek);
        $points = [];

        while ($cursor < $end) {
            $points[$cursor->format('Y-m-d')] = 0;
            $cursor = $cursor->modify($isWeek ? '+7 days' : '+1 day');
        }

        foreach ($this->buckets as $hour => $count) {
            $key = $this->bucketStart($this->localTime($hour), $isWeek)->format('Y-m-d');

            if (isset($points[$key])) {
                $points[$key] += $count;
            }
        }

        $series = [];

        foreach ($points as $key => $value) {
            $series[] = ['key' => $key, 'value' => $value];
        }

        return $series;
    }
    
    public function weekdays(): array
    {
        $weekdays = array_fill(0, 7, 0);

        foreach ($this->buckets as $hour => $count) {
            $weekdays[(int)$this->localTime($hour)->format('N') - 1] += $count;
        }

        return $weekdays;
    }

    public function hours(): array
    {
        $hours = array_fill(0, 24, 0);

        foreach ($this->buckets as $hour => $count) {
            $hours[(int)$this->localTime($hour)->format('G')] += $count;
        }

        return $hours;
    }

    public function total(): int
    {
        return array_sum($this->buckets);
    }

    private function bucketStart(\DateTimeImmutable $time, bool $isWeek): \DateTimeImmutable
    {
        $day = $time->setTime(0, 0);

        return $isWeek ? $day->modify('-' . ((int)$day->format('N') - 1) . ' days') : $day;
    }

    private function localTime(int $hour): \DateTimeImmutable
    {
        return $this->localTimes[$hour] ??= (new \DateTimeImmutable('@' . ($hour * 3600)))->setTimezone($this->timezone);
    }
}
