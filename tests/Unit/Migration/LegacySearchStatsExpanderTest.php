<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Tests\Unit\Migration;

use Alnv\ProSearchIndexerContaoAdapterBundle\Migration\LegacySearchStatsExpander;
use PHPUnit\Framework\TestCase;

final class LegacySearchStatsExpanderTest extends TestCase
{
    public function testExpandsOneRowPerSearchAndKeepsTheSums(): void
    {
        $rows = iterator_to_array(LegacySearchStatsExpander::expand([
            'keywords' => ' Führerschein ',
            'count' => 4,
            'hits' => 10,
            'clicks' => 3,
            'tstamp' => 1794320653,
            'types' => serialize(['pdf', 'leistung', 'page', 'pdf']),
            'urls' => serialize(['https://example.org/a']),
            'source' => serialize([['source' => 'https://example.org/de/?keywords=x', 'click' => 1], ['source' => 'https://example.org/en/', 'click' => 9]]),
        ]), false);

        $this->assertCount(4, $rows);
        $this->assertSame([3, 3, 2, 2], array_column($rows, 'hits'));
        $this->assertSame([3, 0, 0, 0], array_column($rows, 'clicks'));
        $this->assertSame([serialize(['https://example.org/a']), null, null, null], array_column($rows, 'urls'));
        $this->assertSame(['https://example.org/de/', 'https://example.org/en/', 'https://example.org/en/', 'https://example.org/en/'], array_column($rows, 'source'), 'sources are capped at the number of searches');
        $this->assertSame(['leistung,page,pdf'], array_values(array_unique(array_column($rows, 'types'))));
        $this->assertSame([1794320653], array_values(array_unique(array_column($rows, 'tstamp'))));
        $this->assertSame(['Führerschein'], array_values(array_unique(array_column($rows, 'keyword'))));
        $this->assertSame(['1'], array_values(array_unique(array_column($rows, 'legacy'))));
        $this->assertSame(['searches' => 4, 'hits' => 10, 'clicks' => 3], LegacySearchStatsExpander::totals(['keywords' => ' Führerschein ', 'count' => 4, 'hits' => 10, 'clicks' => 3]));
    }

    public function testSkipsRowsWithoutTermOrSearches(): void
    {
        foreach ([['keywords' => '   ', 'count' => 5], ['keywords' => 'x', 'count' => 0], ['keywords' => 'x', 'count' => null], []] as $row) {
            $this->assertSame([], iterator_to_array(LegacySearchStatsExpander::expand($row), false));
            $this->assertSame(['searches' => 0, 'hits' => 0, 'clicks' => 0], LegacySearchStatsExpander::totals($row));
        }
    }

    public function testToleratesBrokenSerializedValues(): void
    {
        $rows = iterator_to_array(LegacySearchStatsExpander::expand([
            'keywords' => 'x', 'count' => 2, 'hits' => null, 'clicks' => null,
            'types' => 'kaputt', 'urls' => 'O:8:"stdClass":0:{}', 'source' => serialize(['kein array']),
        ]), false);

        $this->assertSame([0, 0], array_column($rows, 'hits'));
        $this->assertSame(['', ''], array_column($rows, 'types'));
        $this->assertSame([null, null], array_column($rows, 'urls'), 'objects are never unserialized');
        $this->assertSame(['', ''], array_column($rows, 'source'));
    }
}
