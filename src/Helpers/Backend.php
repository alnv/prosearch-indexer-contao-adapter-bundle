<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Helpers;

use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\IndicesRepository;
use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\Queue\IndexSynchronizer;
use Alnv\ProSearchIndexerContaoAdapterBundle\Models\IndicesModel;
use Contao\Controller;
use Contao\DC_Table;
use Contao\Message;
use Contao\System;

class Backend
{
    public function reIndexSite(DC_Table $objDataContainer): void
    {
        $objContainer = System::getContainer();
        $strBackUrl = 'contao?do=indices&rt=' . $objContainer->get('contao.csrf.token_manager')->getDefaultTokenValue();

        $objIndicesModel = IndicesModel::findByPk($objDataContainer->id);

        if (!$objIndicesModel) {
            Message::addError('Etwas ist schiefgelaufen');
            Controller::redirect($strBackUrl);
        }

        $intId = (int)$objIndicesModel->id;
        $objContainer->get(IndicesRepository::class)->requeue([$intId]);
        $objReport = $objContainer->get(IndexSynchronizer::class)->run(ids: [$intId]);

        if ($objReport->indexed > 0) {
            Message::addConfirmation('Das Dokument wurde in den Suchindex übertragen.');
        } elseif ($objReport->deleted > 0) {
            Message::addConfirmation('Das Dokument war zum Löschen markiert und wurde aus dem Suchindex entfernt.');
        } else {
            Message::addInfo('Das Dokument ist für die Übertragung vorgemerkt und wird beim nächsten Lauf übertragen.' . ($objReport->errors ? ' Fehler: ' . implode(' | ', $objReport->errors) : ''));
        }

        Controller::redirect($strBackUrl);
    }
}
