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

        $strChecksum = md5(serialize($jsonLdScriptsData));
        $objMicrodataModel = MicrodataModel::findByChecksumAndPid($strChecksum, $indicesId);

        if (!$objMicrodataModel) {
            $objMicrodataModel = new MicrodataModel();
        }

        $objMicrodataModel->tstamp = time();
        $objMicrodataModel->pid = $indicesId;
        $objMicrodataModel->type = $this->type;
        $objMicrodataModel->checksum = $strChecksum;
        $objMicrodataModel->data = serialize(serialize($jsonLdScriptsData));

        $objMicrodataModel->save();
    }
}