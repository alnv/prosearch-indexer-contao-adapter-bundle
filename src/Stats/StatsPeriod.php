<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Stats;

final class StatsPeriod
{
    public const PRESETS = ['today', 'yesterday', '7d', '30d', '90d', '12m', 'year', 'all', 'custom'];

    public const MAX_CUSTOM_DAYS = 3660;

    private function __construct(
        public readonly string              $preset,
        public readonly ?\DateTimeImmutable $start,
        public readonly \DateTimeImmutable  $end,
        public readonly ?\DateTimeImmutable $previousStart,
        public readonly ?\DateTimeImmutable $previousEnd,
    )
    {
    }

    public static function create(string $preset, ?string $from, ?string $to, \DateTimeImmutable $now): self
    {
        $today = $now->setTime(0, 0);

        [$start, $end, $shift] = match ($preset) {
            'today' => [$today, $now, '-1 day'],
            'yesterday' => [$today->modify('-1 day'), $today, '-1 day'],
            '7d' => [$today->modify('-6 days'), $now, '-7 days'],
            '30d' => [$today->modify('-29 days'), $now, '-30 days'],
            '90d' => [$today->modify('-89 days'), $now, '-90 days'],
            '12m' => [$today->modify('-1 year')->modify('+1 day'), $now, '-1 year'],
            'year' => [$today->setDate((int)$now->format('Y'), 1, 1), $now, '-1 year'],
            'all' => [null, $now, null],
            'custom' => self::customRange($from, $to, $now),
            default => throw new \InvalidArgumentException(\sprintf('Unknown period "%s".', $preset)),
        };

        if (null === $start || null === $shift) {
            return new self($preset, $start, $end, null, null);
        }

        return new self($preset, $start, $end, $start->modify($shift), $end->modify($shift));
    }

    public function getStartTimestamp(): ?int
    {
        return $this->start?->getTimestamp();
    }

    public function getEndTimestamp(): int
    {
        return $this->end->getTimestamp();
    }

    public function hasPrevious(): bool
    {
        return null !== $this->previousStart;
    }

    public function getGranularity(?int $logStart = null): string
    {
        $start = $this->start?->getTimestamp() ?? $logStart ?? $this->end->getTimestamp();

        return ($this->end->getTimestamp() - $start) > 120 * 86400 ? 'week' : 'day';
    }

    private static function customRange(?string $from, ?string $to, \DateTimeImmutable $now): array
    {
        $timezone = $now->getTimezone();
        $start = self::parseDate((string)$from, $timezone);
        $last = self::parseDate((string)$to, $timezone);

        if (null === $start || null === $last) {
            throw new \InvalidArgumentException('Please enter a valid period (YYYY-MM-DD).');
        }

        if ($last < $start) {
            throw new \InvalidArgumentException('The start date must not be after the end date.');
        }

        if ($start > $now) {
            throw new \InvalidArgumentException('The period must not lie in the future.');
        }

        $days = (int)$start->diff($last)->days + 1;

        if ($days > self::MAX_CUSTOM_DAYS) {
            throw new \InvalidArgumentException(\sprintf('The period must not be longer than %d days.', self::MAX_CUSTOM_DAYS));
        }

        $end = min($last->modify('+1 day'), $now);

        return [$start, $end, \sprintf('-%d days', $days)];
    }

    private static function parseDate(string $value, \DateTimeZone $timezone): ?\DateTimeImmutable
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, $timezone);

        // Rejects overflow dates such as 2026-02-31
        return false !== $date && $date->format('Y-m-d') === $value ? $date : null;
    }
}
