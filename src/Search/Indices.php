<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Search;

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\States;
use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Text;
use Alnv\ProSearchIndexerContaoAdapterBundle\Models\IndicesModel;
use Contao\CoreBundle\Search\Document;
use Fusonic\OpenGraph\Consumer;
use Symfony\Component\DomCrawler\Crawler;
use Alnv\ProSearchIndexerContaoAdapterBundle\Adapter\Elasticsearch;

/**
 *
 */
class Indices extends Searcher
{

    /**
     * @param Document $document
     * @param array $meta
     * @throws \Elastic\Elasticsearch\Exception\ClientResponseException
     * @throws \Elastic\Elasticsearch\Exception\MissingParameterException
     * @throws \Elastic\Elasticsearch\Exception\ServerResponseException
     */
    public function __construct(Document $document, array $meta = [])
    {

        try {
            $strLanguage = $document->getContentCrawler()->filterXPath('//html[@lang]')->first()->attr('lang');
        } catch (\Exception $e) {
            $strLanguage = 'en';
        }

        $strHtml = $document->getBody();
        $this->objCrawler = new Crawler($strHtml);

        $objConsumer = new Consumer();
        $objPageObject = $objConsumer->loadHtml($strHtml);

        $arrDocument = [
            'text' => [Text::tokenize($this->parseContent($document->getBody()))],
            'strong' => $this->getValuesByTagName('main strong'),
            'h1' => $this->getValuesByTagName('h1'),
            'h2' => $this->getValuesByTagName('h2'),
            'h3' => $this->getValuesByTagName('h3'),
            'h4' => $this->getValuesByTagName('h4'),
            'h5' => $this->getValuesByTagName('h5'),
            'h6' => $this->getValuesByTagName('h6'),
            'document' => []
        ];

        $strUrl = $document->getUri()->__toString();
        $strUrl = \StringUtil::decodeEntities($strUrl);
        $strUrl = strtok($strUrl, '?');

        $objIndicesModel = IndicesModel::findByUrl($strUrl);
        $arrSearchTypes = $this->objCrawler->filterXpath("//meta[@name='search:type']")->extract(['content']);

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

        $objIndicesModel->url = $strUrl;
        $objIndicesModel->tstamp = time();
        $objIndicesModel->state = States::ACTIVE;
        $objIndicesModel->language = $strLanguage;
        $objIndicesModel->types = $arrSearchTypes;
        $objIndicesModel->pageId = $meta['pageId'];
        $objIndicesModel->images = serialize($arrImages);
        $objIndicesModel->document = serialize($arrDocument);
        $objIndicesModel->domain = $document->getUri()->getHost();
        $objIndicesModel->title = Text::tokenize($this->getTitle($objPageObject));
        $objIndicesModel->description = Text::tokenize($this->getDescription($objPageObject));
        $objIndicesModel->doc_type = 'page';
        $objIndicesModel->origin_url = '';
        $objIndicesModel->save();

        new MicroDataDispatcher($document, $objIndicesModel->id);

        // todo event service for indexer
        (new Elasticsearch())->indexDocuments($objIndicesModel->id);
    }

    protected function getDescription($objPageObject) {

        if ($objPageObject->description) {
            return $objPageObject->description;
        }

        $arrDescriptions = $this->objCrawler->filterXpath("//meta[@name='description']")->extract(['content']);
        if (!empty($arrDescriptions)) {
            return $arrDescriptions[0] ?? '';
        }

        return '';
    }

    protected function getTitle($objPageObject) {

        if ($objPageObject->title) {
            return $objPageObject->title;
        }

        $strTitle = $this->objCrawler->filter('title')->text();

        if ($strTitle) {
            return $strTitle;
        }

        $arrH1 = $this->getValuesByTagName('h1');

        if (empty($arrH1)) {
            return '';
        }

        return $arrH1[0] ?? '';
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

            $strText = Text::tokenize($objNode->textContent);

            if (!$strText) {
                continue;
            }

            if (!in_array($strText, $arrReturn)) {
                $arrReturn[] = $strText;
            }
        }

        return $arrReturn;
    }
}