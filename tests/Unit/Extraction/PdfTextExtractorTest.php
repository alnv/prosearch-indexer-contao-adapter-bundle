<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Tests\Unit\Extraction;

use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\Extraction\DocumentExtractionException;
use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\Extraction\PdfTextExtractor;
use PHPUnit\Framework\TestCase;

final class PdfTextExtractorTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/ps_pdf_'.bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*') ?: []);
        rmdir($this->dir);
    }

    public function testExtractsTheTextOfAllPages(): void
    {
        $file = $this->pdf(['Kindertagesstaetten im Landkreis', 'Seite zwei: Anmeldung']);

        $text = (new PdfTextExtractor())->extract($file);

        $this->assertStringContainsString('Kindertagesstaetten im Landkreis', $text);
        $this->assertStringContainsString('Seite zwei: Anmeldung', $text);
    }

    public function testPageLimit(): void
    {
        $text = (new PdfTextExtractor(maxPages: 1))->extract($this->pdf(['Erste Seite', 'Zweite Seite']));

        $this->assertStringContainsString('Erste Seite', $text);
        $this->assertStringNotContainsString('Zweite Seite', $text);
    }

    public function testTextLimitForVeryLargeDocuments(): void
    {
        $pages = array_fill(0, 40, str_repeat('Langer Text ', 40));
        $text = (new PdfTextExtractor(maxTextBytes: 2000))->extract($this->pdf($pages));

        $this->assertLessThanOrEqual(2000, \strlen($text));
        $this->assertNotSame('', $text);
    }

    public function testPdfWithoutTextLayerReturnsAnEmptyString(): void
    {
        $this->assertSame('', (new PdfTextExtractor())->extract($this->pdf([''])));
    }

    public function testCorruptPdfIsReported(): void
    {
        $file = $this->dir.'/broken.pdf';
        file_put_contents($file, "%PDF-1.4\n1 0 obj << /Type /Catalog /Pages 9 0 R >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF garbage \x00\x01");

        $this->expectException(DocumentExtractionException::class);

        try {
            (new PdfTextExtractor())->extract($file);
        } catch (DocumentExtractionException $e) {
            $this->assertSame(DocumentExtractionException::INVALID, $e->getReason());

            throw $e;
        }
    }

    public function testFileThatIsNoPdf(): void
    {
        $file = $this->dir.'/fake.pdf';
        file_put_contents($file, '<html>not a pdf</html>');

        $this->assertReason(DocumentExtractionException::INVALID, $file, new PdfTextExtractor());
    }

    public function testEmptyFile(): void
    {
        $file = $this->dir.'/empty.pdf';
        touch($file);

        $this->assertReason(DocumentExtractionException::INVALID, $file, new PdfTextExtractor());
    }

    public function testMissingFile(): void
    {
        $this->assertReason(DocumentExtractionException::NOT_READABLE, $this->dir.'/missing.pdf', new PdfTextExtractor());
    }

    public function testTooLargeFileIsNotParsed(): void
    {
        $this->assertReason(DocumentExtractionException::TOO_LARGE, $this->pdf(['x']), new PdfTextExtractor(maxFileSize: 100));
    }

    public function testInsufficientMemoryIsDetectedBeforeParsing(): void
    {
        $this->assertReason(DocumentExtractionException::INSUFFICIENT_MEMORY, $this->pdf(['x']), new PdfTextExtractor(memoryLimit: memory_get_usage(true) + 1024));
    }

    public function testHyphenatedLineBreaksAreJoined(): void
    {
        $this->assertSame("Kinder\nBetreuung und Kindertagesstätte", PdfTextExtractor::clean("Kinder\nBetreuung und Kinder-\ntagesstätte"));
    }

    private function assertReason(string $reason, string $file, PdfTextExtractor $extractor): void
    {
        try {
            $extractor->extract($file);
            $this->fail('DocumentExtractionException expected');
        } catch (DocumentExtractionException $e) {
            $this->assertSame($reason, $e->getReason(), $e->getMessage());
        }
    }

    /**
     * Writes a minimal valid PDF (Helvetica, one text line per page).
     *
     * @param list<string> $pages
     */
    private function pdf(array $pages): string
    {
        $objects = [];
        $kids = [];
        $count = \count($pages);

        foreach ($pages as $i => $text) {
            $pageId = 4 + $i * 2;
            $contentId = $pageId + 1;
            $kids[] = $pageId.' 0 R';
            $stream = '' === $text ? '' : 'BT /F1 12 Tf 50 700 Td ('.addcslashes($text, '()\\').') Tj ET';
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents '.$contentId.' 0 R /Resources << /Font << /F1 3 0 R >> >> >>';
            $objects[$contentId] = '<< /Length '.\strlen($stream)." >>\nstream\n".$stream."\nendstream";
        }

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.$count.' >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $id => $body) {
            $offsets[$id] = \strlen($pdf);
            $pdf .= $id." 0 obj\n".$body."\nendobj\n";
        }

        $xref = \strlen($pdf);
        $pdf .= "xref\n0 ".(\count($objects) + 1)."\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= \sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= 'trailer << /Size '.(\count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";

        $file = $this->dir.'/'.bin2hex(random_bytes(4)).'.pdf';
        file_put_contents($file, $pdf);

        return $file;
    }
}
