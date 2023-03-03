<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Search;

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\States;
use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Text;
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
            'text' => $this->getValuesByTagName('main p'),
            'list' => $this->getValuesByTagName('main li'),
            'span' => $this->getValuesByTagName('main span'),
            'strong' => $this->getValuesByTagName('main strong'),
            'h1' => $this->getValuesByTagName('h1'),
            'h2' => $this->getValuesByTagName('h2'),
            'h3' => $this->getValuesByTagName('h3'),
            'h4' => $this->getValuesByTagName('h4'),
            'h5' => $this->getValuesByTagName('h5'),
            'h6' => $this->getValuesByTagName('h6')
        ];

        $strUrl = $document->getUri()->__toString();
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

        $objIndicesModel->tstamp = time();
        $objIndicesModel->url = $strUrl;
        $objIndicesModel->state = States::ACTIVE;
        $objIndicesModel->language = $strLanguage;
        $objIndicesModel->types = $arrSearchTypes;
        $objIndicesModel->images = serialize($arrImages);
        $objIndicesModel->document = serialize($arrDocument);
        $objIndicesModel->title = Text::tokenize($this->getTitle($objPageObject));
        $objIndicesModel->description = Text::tokenize($this->getDescription($objPageObject));
        $objIndicesModel->save();

        new MicroDataDispatcher($document, $objIndicesModel->id);
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

    /**
     * @return int
     */
    public function getIndicesId(): int
    {
        return $this->indicesId;
    }
}