<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Tests\Unit\Helpers;

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Keyword;
use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Synonyms;
use PHPUnit\Framework\TestCase;

final class SynonymsTest extends TestCase
{
    public function testExpandsWholeWordsAndPhrasesCaseInsensitively(): void
    {
        $synonyms = $this->synonyms();

        $this->assertSame(['Bürgergeld'], $synonyms->expand('hartz 4'));
        $this->assertSame(['Bürgergeld'], $synonyms->expand('HARTZ  4'));
        $this->assertSame(['Bürgergeld antrag'], $synonyms->expand('alg 2 antrag'));
        // longest phrase wins: "alg 2" (Bürgergeld), not "alg" (Arbeitslosengeld I)
        $this->assertSame(['Bürgergeld'], $synonyms->expand('alg 2'));
        $this->assertSame(['Arbeitslosengeld I'], $synonyms->expand('alg'));
        $this->assertSame(['kosten Fahrerlaubnis'], $synonyms->expand('kosten führerschein'));
        // target term already in the query: no alternative
        $this->assertSame([], $synonyms->expand('bürgergeld jobcenter'));
    }

    public function testNoFuzzyFalsePositives(): void
    {
        $synonyms = $this->synonyms();

        // similar_text() >= 75 % matched all of these before
        foreach (['antrags', 'beratung', 'verwaltung', 'haus', 'hartz', 'führerscheine', 'alg2'] as $query) {
            $this->assertSame([], $synonyms->expand($query), $query);
            $this->assertSame($query, $synonyms->predict($query), $query);
        }
    }

    public function testATermOfSeveralRecordsGivesSeveralAlternatives(): void
    {
        $synonyms = $this->synonyms();

        $this->assertSame(['Berufsorientierungspraktikum', 'Einstiegsqualifizierung'], $synonyms->expand('praktikum'));
        $this->assertSame(['Berufsorientierungspraktikum'], $synonyms->expand('praktikum', 1));
        $this->assertSame('Berufsorientierungspraktikum', $synonyms->predict('praktikum'));
    }

    public function testEntitiesPunctuationAndSelfReferences(): void
    {
        $synonyms = new Synonyms([
            ['keyword' => 'Fleischhygiene', 'synonyms' => serialize(['Trichinen,', '&#40;teil&#41;mobile Schlachtung', 'Fleischhygiene', '', '  '])],
            ['keyword' => '', 'synonyms' => serialize(['ignored'])],
            ['keyword' => 'Leer', 'synonyms' => null],
        ]);

        $this->assertSame(['trichinen' => ['Fleischhygiene'], 'teil)mobile schlachtung' => ['Fleischhygiene']], $synonyms->getSynonyms());
        $this->assertSame(['Fleischhygiene'], $synonyms->expand('Trichinen?'));
        $this->assertSame([], $synonyms->expand('fleischhygiene'));
        $this->assertSame([], $synonyms->expand('ignored'));
        $this->assertSame([], $synonyms->expand(''));
    }

    public function testKeywordKeepsTheTypedQuery(): void
    {
        $keywords = (new Keyword($this->synonyms()))->setKeywords('Hartz 4 Ü-Antrag', ['categories' => ['pdf', '']]);

        $this->assertSame('hartz 4 ü-antrag', $keywords['query']);
        $this->assertSame(['Bürgergeld ü-antrag'], $keywords['synonyms']);
        $this->assertSame('Bürgergeld ü-antrag', $keywords['synonym']);
        $this->assertSame(['hartz', '4', 'ü-antrag'], $keywords['words']);
        $this->assertSame('Hartz 4 Ü-Antrag', $keywords['keyword']);

        $none = (new Keyword($this->synonyms()))->setKeywords('Kita');
        $this->assertSame([], $none['synonyms']);
        $this->assertSame('kita', $none['synonym']);

        $this->assertSame(['Bürgergeld', 'kita'], (new Keyword($this->synonyms()))->convertSynonyms(['Jobcenter', '', 'Kita']));
    }

    public function testManyRecordsStayFast(): void
    {
        $rows = [];

        for ($i = 0; $i < 2000; ++$i) {
            $rows[] = ['keyword' => 'Ziel '.$i, 'synonyms' => serialize(array_map(static fn (int $j): string => "begriff {$i} {$j}", range(1, 20)))];
        }

        $synonyms = new Synonyms($rows);
        $start = hrtime(true);

        for ($i = 0; $i < 1000; ++$i) {
            $synonyms->expand('ein langer suchbegriff begriff 1999 20 mit mehreren wörtern');
        }

        $this->assertSame(['ein langer suchbegriff Ziel 1999 mit mehreren wörtern'], $synonyms->expand('ein langer suchbegriff begriff 1999 20 mit mehreren wörtern'));
        $this->assertLessThan(500, (hrtime(true) - $start) / 1e6, '1000 expansions with 40,000 terms should take well under 0.5 s');
    }

    private function synonyms(): Synonyms
    {
        return new Synonyms([
            ['keyword' => 'Bürgergeld', 'synonyms' => serialize(['Hartz 4', 'ALG 2', 'ALG II', 'Jobcenter'])],
            ['keyword' => 'Arbeitslosengeld I', 'synonyms' => serialize(['ALG'])],
            ['keyword' => 'Fahrerlaubnis', 'synonyms' => serialize(['Führerschein', 'Lappen'])],
            ['keyword' => 'Berufsorientierungspraktikum', 'synonyms' => serialize(['BOP', 'Praktikum'])],
            ['keyword' => 'Einstiegsqualifizierung', 'synonyms' => serialize(['EQ', 'praktikum'])],
        ]);
    }
}
