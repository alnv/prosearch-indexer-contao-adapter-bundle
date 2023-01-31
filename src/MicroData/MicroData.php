<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\MicroData;

use Alnv\ProSearchIndexerContaoAdapterBundle\Models\MicrodataModel;

/**
 *
 */
abstract class MicroData
{

    /**
     * @var string
     */
    protected string $type;

    /**
     * @param array $jsonLdScriptsData
     * @param int $indicesId
     * @return void
     */
    public function dispatch(array $jsonLdScriptsData, int $indicesId) : void {

        if (empty($jsonLdScriptsData)) {
            return;
        }

        foreach ($jsonLdScriptsData as $arrDataScriptsData) {

            $strChecksum = md5(serialize($arrDataScriptsData));
            $objMicrodataModel = MicrodataModel::findByChecksumAndPid($strChecksum, $indicesId);

            if (!$objMicrodataModel) {
                $objMicrodataModel = new MicrodataModel();
            }

            $objMicrodataModel->tstamp = time();
            $objMicrodataModel->pid = $indicesId;
            $objMicrodataModel->type = $this->type;
            $objMicrodataModel->checksum = $strChecksum;
            $objMicrodataModel->data = serialize($arrDataScriptsData);

            $objMicrodataModel->save();
        }
    }
}