<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Tests\Unit\Stats;

use Alnv\ProSearchIndexerContaoAdapterBundle\Stats\HourlyBuckets;
use Alnv\ProSearchIndexerContaoAdapterBundle\Stats\StatsPeriod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StatsPeriodTest extends TestCase
{
    private \DateTimeZone $tz;

    protected function setUp(): void
    {
        $this->tz = new \DateTimeZone('Europe/Berlin');
    }

    public function testTodayIsComparedWithYesterdayUntilTheSameTime(): void
    {
        $period = StatsPeriod::create('today', null, null, $this->now('2026-11-23 11:30'));

        $this->assertSame('2026-11-23 00:00', $period->start->format('Y-m-d H:i'));
        $this->assertSame('2026-11-23 11:30', $period->end->format('Y-m-d H:i'));
        $this->assertSame('2026-11-22 00:00', $period->previousStart->format('Y-m-d H:i'));
        $this->assertSame('2026-11-22 11:30', $period->previousEnd->format('Y-m-d H:i'));
    }

    /**
     * @param array{0: string, 1: string, 2: string} $expected start, previous start, previous end
     */
    #[DataProvider('presetProvider')]
    public function testPresets(string $preset, array $expected): void
    {
        $period = StatsPeriod::create($preset, null, null, $this->now('2026-11-23 11:30'));

        $this->assertSame($expected[0], $period->start->format('Y-m-d H:i'));
        $this->assertSame($expected[1], $period->previousStart->format('Y-m-d H:i'));
        $this->assertSame($expected[2], $period->previousEnd->format('Y-m-d H:i'));
        $this->assertLessThanOrEqual($period->start, $period->previousEnd, 'previous period must not overlap');
    }

    public static function presetProvider(): iterable
    {
        yield 'yesterday' => ['yesterday', ['2026-11-22 00:00', '2026-11-21 00:00', '2026-11-22 00:00']];
        yield '7 days incl. today' => ['7d', ['2026-11-17 00:00', '2026-11-10 00:00', '2026-11-16 11:30']];
        yield '30 days' => ['30d', ['2026-10-25 00:00', '2026-09-25 00:00', '2026-10-24 11:30']];
        yield '12 months' => ['12m', ['2025-11-24 00:00', '2024-11-24 00:00', '2025-11-23 11:30']];
        yield 'this year' => ['year', ['2026-01-01 00:00', '2025-01-01 00:00', '2025-11-23 11:30']];
    }

    public function testDayBoundariesFollowDaylightSavingTime(): void
    {
        // 30 days across the switch to winter time (2026-10-25): every start is local midnight
        $period = StatsPeriod::create('30d', null, null, $this->now('2026-11-02 08:00'));

        $this->assertSame('2026-10-04 00:00 +02:00', $period->start->format('Y-m-d H:i P'));
        $this->assertSame('2026-09-04 00:00 +02:00', $period->previousStart->format('Y-m-d H:i P'));
        $this->assertSame('2026-11-02 08:00 +01:00', $period->end->format('Y-m-d H:i P'));
    }

    public function testAllHasNoLowerBoundAndNoPreviousPeriod(): void
    {
        $period = StatsPeriod::create('all', null, null, $this->now('2026-11-23 11:30'));

        $this->assertNull($period->start);
        $this->assertFalse($period->hasPrevious());
    }

    public function testCustomRangeIsInclusiveAndEndsNow(): void
    {
        $period = StatsPeriod::create('custom', '2026-11-01', '2026-11-30', $this->now('2026-11-23 11:30'));

        $this->assertSame('2026-11-01 00:00', $period->start->format('Y-m-d H:i'));
        $this->assertSame('2026-11-23 11:30', $period->end->format('Y-m-d H:i'), 'no future end');
        $this->assertSame('2026-10-02 00:00', $period->previousStart->format('Y-m-d H:i'), '30 days before');

        $closed = StatsPeriod::create('custom', '2026-11-01', '2026-11-07', $this->now('2026-11-23 11:30'));
        $this->assertSame('2026-11-08 00:00', $closed->end->format('Y-m-d H:i'), 'end date is included');
        $this->assertSame('2026-10-25 00:00', $closed->previousStart->format('Y-m-d H:i'));
    }

    #[DataProvider('invalidProvider')]
    public function testInvalidInput(string $preset, ?string $from, ?string $to): void
    {
        $this->expectException(\InvalidArgumentException::class);

        StatsPeriod::create($preset, $from, $to, $this->now('2026-11-23 11:30'));
    }

    public static function invalidProvider(): iterable
    {
        yield 'unknown preset' => ['week', null, null];
        yield 'missing dates' => ['custom', null, null];
        yield 'from after to' => ['custom', '2026-11-10', '2026-11-01'];
        yield 'overflow date' => ['custom', '2026-02-31', '2026-03-05'];
        yield 'future' => ['custom', '2026-12-01', '2026-12-05'];
        yield 'sql injection attempt' => ['custom', "2026-11-01' OR 1=1", '2026-11-05'];
        yield 'too long' => ['custom', '2010-01-01', '2026-11-01'];
    }

    public function testGranularity(): void
    {
        $this->assertSame('day', StatsPeriod::create('90d', null, null, $this->now('2026-11-23 11:30'))->getGranularity());
        $this->assertSame('week', StatsPeriod::create('12m', null, null, $this->now('2026-11-23 11:30'))->getGranularity());
        $this->assertSame('day', StatsPeriod::create('all', null, null, $this->now('2026-11-23 11:30'))->getGranularity(strtotime('2026-11-01')), 'all with a young log');
    }

    public function testHourlyBucketsInLocalTime(): void
    {
        $at = fn (string $local): int => intdiv((new \DateTimeImmutable($local, $this->tz))->getTimestamp(), 3600);

        $buckets = new HourlyBuckets([
            $at('2026-10-24 23:30') => 2,  // Saturday, summer time
            $at('2026-10-25 00:10') => 3,  // Sunday 00:xx, first hour of the switch day
            $at('2026-10-25 23:50') => 4,  // Sunday 23:xx, winter time
            $at('2026-10-26 09:00') => 5,  // Monday
        ], $this->tz);

        $series = $buckets->series(new \DateTimeImmutable('2026-10-24', $this->tz), new \DateTimeImmutable('2026-10-27', $this->tz), 'day');
        $this->assertSame([
            ['key' => '2026-10-24', 'value' => 2],
            ['key' => '2026-10-25', 'value' => 7],
            ['key' => '2026-10-26', 'value' => 5],
        ], $series);

        $weekdays = $buckets->weekdays();
        $this->assertSame(5, $weekdays[0], 'Monday');
        $this->assertSame(2, $weekdays[5], 'Saturday');
        $this->assertSame(7, $weekdays[6], 'Sunday');

        $hours = $buckets->hours();
        $this->assertSame(2 + 4, $hours[23]);
        $this->assertSame(3, $hours[0]);
        $this->assertSame(5, $hours[9]);
        $this->assertSame(14, $buckets->total());

        $weeks = $buckets->series(new \DateTimeImmutable('2026-10-20', $this->tz), new \DateTimeImmutable('2026-10-28', $this->tz), 'week');
        $this->assertSame([['key' => '2026-10-19', 'value' => 9], ['key' => '2026-10-26', 'value' => 5]], $weeks, 'ISO weeks start on Monday');
    }

    private function now(string $local): \DateTimeImmutable
    {
        return new \DateTimeImmutable($local, $this->tz);
    }
}
