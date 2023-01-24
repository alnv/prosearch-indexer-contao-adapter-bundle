<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Search;

use Alnv\ProSearchIndexerContaoAdapterBundle\Models\IndicesModel;
use Contao\CoreBundle\Search\Document;
use Fusonic\OpenGraph\Consumer;
use Symfony\Component\DomCrawler\Crawler;

/**
 *
 */
class Indices
{

    /**
     * @var int
     */
    protected int $indicesId = 0;

    /**
     * @var Crawler
     */
    protected Crawler $objCrawler;

    /**
     * @param Document $document
     */
    public function __construct(Document $document)
    {
        $strHtml = $document->getBody();
        $this->objCrawler = new Crawler($strHtml);

        $objConsumer = new Consumer();
        $objPageObject = $objConsumer->loadHtml($strHtml);

        $arrDocument = [
            'text' => $this->getValuesByTagName('main p'),
            'list' => $this->getValuesByTagName('main li'),
            'span' => $this->getValuesByTagName('main span'),
            'strong' => $this->getValuesByTagName('main strong'),
            'h1' => $this->getValuesByTagName('h1'),
            'h2' => $this->getValuesByTagName('h2'),
            'h3' => $this->getValuesByTagName('h3'),
            'h4' => $this->getValuesByTagName('h4'),
            'h5' => $this->getValuesByTagName('h5'),
            'h6' => $this->getValuesByTagName('h6'),
            'links' => $this->getValuesByTagName('a')
        ];

        $objIndicesModel = IndicesModel::findByUrl($objPageObject->url);

        if (!$objIndicesModel) {
            $objIndicesModel = new IndicesModel();
        }

        $arrImages = [];
        foreach ($objPageObject->images as $objImage) {
            if (!$objImage->url) {
                continue;
            }
            $arrFragments = parse_url($objImage->url);
            if ($objFile = \FilesModel::findByPath(ltrim($arrFragments['path'], '/'))) {
                $strUuid = \StringUtil::binToUuid($objFile->uuid);
                if (in_array($strUuid, $arrImages)) {
                    continue;
                }
                $arrImages[] = $strUuid;
            }
        }

        $objIndicesModel->tstamp = time();
        $objIndicesModel->url = $objPageObject->url;
        $objIndicesModel->title = $objPageObject->title;
        $objIndicesModel->images = serialize($arrImages);
        $objIndicesModel->document = serialize($arrDocument);
        $objIndicesModel->description = $objPageObject->description;
        $objIndicesModel->save();

        new MicroDataDispatcher($document, $objIndicesModel->id);
    }

    /**
     * @param $strTagName
     * @return array
     */
    protected function getValuesByTagName($strTagName): array
    {

        $arrReturn = [];
        $objNodes = $this->objCrawler->filter("body $strTagName");
        foreach ($objNodes as $objNode) {
            $strText = preg_replace("/\r|\n/", "", $objNode->textContent);
            if (!$strText) {
                continue;
            }
            if (!in_array($strText, $arrReturn)) {
                $arrReturn[] = $strText;
            }
        }

        return $arrReturn;
    }

    /**
     * @return int
     */
    public function getIndicesId(): int
    {
        return $this->indicesId;
    }
}