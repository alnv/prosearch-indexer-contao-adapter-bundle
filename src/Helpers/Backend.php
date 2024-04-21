<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Helpers;

use Alnv\ProSearchIndexerContaoAdapterBundle\Adapter\Elasticsearch;
use Alnv\ProSearchIndexerContaoAdapterBundle\Adapter\Options;
use Alnv\ProSearchIndexerContaoAdapterBundle\Models\IndicesModel;
use Contao\Controller;
use Contao\DC_Table;
use Contao\Message;
use Contao\PageModel;
use Contao\System;

class Backend
{

    public function reIndexSite(DC_Table $objDataContainer): void
    {
        $objContainer = System::getContainer();

        $objIndicesModel = IndicesModel::findByPk($objDataContainer->id);
        if (!$objIndicesModel) {
            Message::addInfo('Etwas ist schiefgelaufen');
            Controller::redirect('contao/main.php?do=indices&rt=' . $objContainer->get('contao.csrf.token_manager')->getDefaultTokenValue());
        }

        $objOptions = new Options();

        if ($objPage = PageModel::findByPk($objIndicesModel->pageId)) {
            $objPage->loadDetails();
            $objOptions->setRootPageId($objPage->rootId);
        }

        $objOptions->setLanguage($objIndicesModel->language);
        (new Elasticsearch($objOptions->getOptions()))->indexDocuments($objIndicesModel->id);
        Message::addInfo('Seite wurde indexiert');
        Controller::redirect('contao/main.php?do=indices&rt=' . $objContainer->get('contao.csrf.token_manager')->getDefaultTokenValue());
    }
}