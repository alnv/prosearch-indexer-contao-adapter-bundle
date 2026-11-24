<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Tests\Unit\Indexing;

use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\Queue\DocumentPayloadBuilder;
use Alnv\ProSearchIndexerContaoAdapterBundle\Migration\IndicesChunksMigration;
use Alnv\ProSearchIndexerContaoAdapterBundle\Search\MappingFactory;
use PHPUnit\Framework\TestCase;

/**
 * Document structure sent to the proxy, index mapping and conversion of the 3.x "document" field.
 */
final class DocumentStructureTest extends TestCase
{
    public function testPayloadContainsTextAndOptionalChunks(): void
    {
        $row = [
            'id' => 7, 'title' => 'Kita', 'description' => 'Teaser', 'url' => 'https://x.test/kita', 'domain' => 'x.test', 'language' => 'de',
            'types' => serialize(['news', '']), 'images' => serialize(['uuid-1', 'uuid-2']), 'text' => "Absatz eins\nAbsatz zwei",
            'strong' => serialize(['wichtig', 'neu']), 'headings' => serialize(['h1' => ['Kitas'], 'h2' => ['A', 'B']]),
        ];

        $document = (new DocumentPayloadBuilder())->build($row);

        $this->assertSame([
            'id' => '7', 'title' => 'Kita', 'description' => 'Teaser', 'url' => 'https://x.test/kita', 'domain' => 'x.test', 'language' => 'de',
            'types' => ['news'], 'image' => 'uuid-1', 'text' => "Absatz eins\nAbsatz zwei", 'strong' => 'wichtig, neu',
            'h1' => 'Kitas', 'h2' => 'A, B', 'h3' => '', 'h4' => '', 'h5' => '', 'h6' => '',
        ], $document);
        $this->assertArrayNotHasKey('document', $document);
        $this->assertArrayNotHasKey('chunks', $document, 'Elasticsearch: no chunks');

        $chunks = [['text' => 'Absatz eins', 'position' => 0, 'token_count' => 3]];
        $this->assertSame($chunks, (new DocumentPayloadBuilder())->build($row, $chunks)['chunks']);
    }

    public function testOpenSearch3MappingHasNestedChunks(): void
    {
        $body = (new MappingFactory())->create('idx', ['analyzer' => 'german'], null, true, true)['body'];

        $this->assertSame('nested', $body['mappings']['properties']['chunks']['type']);
        $this->assertSame(['type' => 'text', 'analyzer' => 'german'], $body['mappings']['properties']['chunks']['properties']['text']);
        $this->assertTrue($body['settings']['index']['knn']);
        // existing search fields are unchanged
        $this->assertSame(['type' => 'text', 'analyzer' => 'german'], $body['mappings']['properties']['text']);
        $this->assertArrayHasKey('document', $body['mappings']['properties']);
    }

    public function testElasticsearchMappingHasNoChunks(): void
    {
        $body = (new MappingFactory())->create('idx', [], null, false, false)['body'];

        $this->assertArrayNotHasKey('chunks', $body['mappings']['properties']);
        $this->assertArrayNotHasKey('index', $body['settings']);
        $this->assertSame((new MappingFactory())->create('idx')['body'], $body, 'default = previous behaviour');
    }

    public function testConvertsLegacyPageDocuments(): void
    {
        $document = serialize(['text' => ['Seitentext'], 'strong' => ['fett'], 'h1' => ['Titel'], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => [], 'document' => []]);

        $this->assertSame(
            ['text' => 'Seitentext', 'strong' => ['fett'], 'headings' => ['h1' => ['Titel'], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => []]],
            IndicesChunksMigration::convert($document, 'page'),
        );
    }

    public function testConvertsLegacyPdfDocuments(): void
    {
        $document = serialize(['text' => [0 => 'Linktext', 5 => 'datei.pdf'], 'document' => ['PDF Inhalt'], 'strong' => ['Linktext'], 'h1' => []]);
        $result = IndicesChunksMigration::convert($document, 'file');

        $this->assertSame('PDF Inhalt', $result['text']);
        $this->assertSame(['Linktext', 'datei.pdf'], $result['strong']);
    }

    public function testInvalidUtf8OfOldPdfImportsIsRepairedWithoutLoss(): void
    {
        // "BETREUUNGSVERFÜGUNG" with a Windows-1252 "Ü" next to valid UTF-8 umlauts
        $document = serialize(['document' => ["BETREUUNGSVERF\xDCGUNG Betreuungsverfügung"]]);

        $this->assertSame('BETREUUNGSVERFÜGUNG Betreuungsverfügung', IndicesChunksMigration::convert($document, 'file')['text']);
        $this->assertSame('a€b', \Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\Extraction\TextNormalizer::toUtf8("a\x80b"));
    }

    public function testUnreadableLegacyDocumentsAreNotConverted(): void
    {
        $this->assertNull(IndicesChunksMigration::convert('a:2:{broken', 'page'));
        $this->assertSame('', IndicesChunksMigration::convert(null, 'page')['text']);
    }
}
