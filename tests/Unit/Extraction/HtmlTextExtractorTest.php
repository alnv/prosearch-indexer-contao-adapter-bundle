<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Tests\Unit\Extraction;

use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\Extraction\HtmlTextExtractor;
use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\Extraction\TextNormalizer;
use PHPUnit\Framework\TestCase;

final class HtmlTextExtractorTest extends TestCase
{
    public function testExtractsTheReadableTextOfAContaoPage(): void
    {
        $html = <<<'HTML'
            <!DOCTYPE html><html lang="de"><head><title>Kita</title><style>.a{color:red}</style><script>var x = "<p>nope</p>";</script></head>
            <body>
            <!-- indexer::stop --><nav><ul><li>Menü</li></ul></nav><!-- indexer::continue -->
            <main><h1>Kitas &amp; Horte</h1><p>Die <strong>Kita</strong>-Plätze werden&nbsp;vergeben.</p>
            <p>Ver<span>wal</span>tung</p><ul><li>Eins</li><li>Zwei</li></ul>
            <form><select name="ort"><option>Beelitz</option><option>Werder</option></select><textarea>Eingabe</textarea></form>
            <svg><text>Icon</text></svg><noscript>Bitte JS</noscript><!-- Kommentar -->
            <p>Preis: 5&nbsp;€ &ndash; &quot;Gebühr&quot;</p></main>
            </body></html>
            HTML;

        $text = (new HtmlTextExtractor())->extract($html);

        $this->assertSame("Kitas & Horte\nDie Kita-Plätze werden vergeben.\nVerwaltung\nEins\nZwei\nPreis: 5 € – \"Gebühr\"", $text);
    }

    public function testNestedIndexerStopBlocks(): void
    {
        $html = '<html><head></head><body>A <!-- indexer::stop -->B <!-- indexer::stop -->C<!-- indexer::continue --> D<!-- indexer::continue --> E</body></html>';

        $this->assertSame('A E', (new HtmlTextExtractor())->extract($html));
    }

    public function testUnbalancedIndexerStopExcludesTheRest(): void
    {
        $this->assertSame('A', (new HtmlTextExtractor())->extract('<body>A <!-- indexer::stop --> B</body>'));
    }

    public function testAdjacentLinksAndBlocksDoNotMergeWords(): void
    {
        $text = (new HtmlTextExtractor())->extract('<body><a href="#">Impressum</a><a href="#">Datenschutz</a><div>Eins</div><div>Zwei</div><td>A</td><td>B</td></body>');

        $this->assertSame("Impressum Datenschutz\nEins\nZwei\nA\nB", $text);
    }

    public function testEmptyPage(): void
    {
        $this->assertSame('', (new HtmlTextExtractor())->extract('<html><head><title>x</title></head><body><script>1</script></body></html>'));
    }

    public function testNormalizerTruncatesAtWordBoundary(): void
    {
        $text = TextNormalizer::truncate(str_repeat('Wort ', 100), 52);

        $this->assertLessThanOrEqual(52, \strlen($text));
        $this->assertStringEndsWith('Wort', $text);
        $this->assertTrue(mb_check_encoding(TextNormalizer::truncate(str_repeat('ä', 100), 51), 'UTF-8'));
    }
}
