<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Search;

use Alnv\ProSearchIndexerContaoAdapterBundle\Adapter\Elasticsearch;
use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\States;
use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Text;
use Alnv\ProSearchIndexerContaoAdapterBundle\Models\IndicesModel;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Frontend;
use Contao\File;
use Contao\StringUtil;
use Contao\System;
use Psr\Log\LogLevel;
use Smalot\PdfParser\Parser;
use Smalot\PdfParser\Config;
use Contao\CoreBundle\Search\Document;
use Symfony\Component\DomCrawler\Crawler;

class PDFIndices extends Searcher
{

    public function __construct(Document $document)
    {

        set_time_limit(180);

        try {
            $strLanguage = $document->getContentCrawler()->filterXPath('//html[@lang]')->first()->attr('lang');
        } catch (\Exception $e) {
            $strLanguage = 'en';
        }

        $objConfig = new Config();
        $objConfig->setRetainImageContent(false);
        $objConfig->setDecodeMemoryLimit(128);

        $_strUrl = $document->getUri()->__toString();
        $arrDomain = parse_url($_strUrl);
        $strDomain = ($arrDomain['scheme']??'')
            . '://'
            . ($arrDomain['host']??'')
            . (isset($arrDomain['port']) && $arrDomain['port']? ':' . $arrDomain['port']:'');

        $strHtml = $document->getBody();
        $this->objCrawler = new Crawler($strHtml);
        $objLinks = $this->objCrawler->filter("body a");

        foreach ($objLinks as $objLink) {

            $strHref = $objLink->getAttribute('href');
            $strTitle = $objLink->getAttribute('title');

            if (!$strHref || strpos($strHref, '.pdf') === false) {
                continue;
            }

            $arrUrl = parse_url($strHref);
            $strFile = $arrUrl['path'];
            $objFile = \FilesModel::findByPath($strFile);

            if (!$objFile) {
                continue;
            }

            $_File = new File($objFile->path);
            if (($_File->filesize/1000000) > 10) {
                continue;
            }

            $arrMeta = Frontend::getMetaData(\StringUtil::deserialize($objFile->meta), $strLanguage);
            $strDescription = $arrMeta['caption'] ?? '';

            if (!$strTitle) {

                if ($arrMeta['title'] == '') {
                    $arrMeta['title'] = StringUtil::specialchars($objFile->basename);
                }

                $strTitle = $arrMeta['title'] ?? '';
            }

            try {

                $objParser = new Parser([], $objConfig);
                $objPdf = $objParser->parseFile(TL_ROOT . '/' . $objFile->path);

                $arrDocument = [
                    'text' => Text::tokenize(strip_tags($objPdf->getText())),
                    'strong' => [Text::tokenize($objLink->textContent)],
                    'h1' => [$strTitle],
                    'h2' => [],
                    'h3' => [],
                    'h4' => [],
                    'h5' => [],
                    'h6' => []
                ];

                $strUrl = $strDomain . '/' . $objFile->path;
                $objIndicesModel = IndicesModel::findByUrl($strUrl);

                if (!$objIndicesModel) {
                    $objIndicesModel = new IndicesModel();
                }

                $objIndicesModel->tstamp = time();
                $objIndicesModel->url = $strUrl;
                $objIndicesModel->origin_url = $_strUrl;
                $objIndicesModel->state = States::ACTIVE;
                $objIndicesModel->language = $strLanguage;
                $objIndicesModel->types = ['pdf'];
                $objIndicesModel->images = ['assets/contao/images/pdf.svg'];
                $objIndicesModel->document = serialize($arrDocument);
                $objIndicesModel->domain = $document->getUri()->getHost();
                $objIndicesModel->title = $strTitle;
                $objIndicesModel->description = Text::tokenize($strDescription);
                $objIndicesModel->doc_type = 'file';
                $objIndicesModel->save();

                (new Elasticsearch())->indexDocuments($objIndicesModel->id);

            } catch (\Exception $exception) {

                System::getContainer()
                    ->get('monolog.logger.contao')
                    ->log(LogLevel::ERROR, 'PDF Parser: ' . $exception->getMessage(), ['contao' => new ContaoContext(__CLASS__ . '::' . __FUNCTION__, TL_ERROR)]);
            }
        }
    }
}