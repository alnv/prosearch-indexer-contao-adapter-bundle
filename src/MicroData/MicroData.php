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
     * @var bool
     */
    public bool $richSnippet = false;

    /**
     * @var bool
     */
    public bool $globalRichSnippet = false;

    /***
     * @var array|mixed
     */
    protected array $jsonLdScriptsData = [];

    /**
     * @param array $jsonLdScriptsData
     */
    public function __construct(array $jsonLdScriptsData = [])
    {

        $this->jsonLdScriptsData = $jsonLdScriptsData;
    }

    /**
     * @param array $jsonLdScriptsData
     * @param int $indicesId
     * @return void
     */
    public function dispatch(array $jsonLdScriptsData, int $indicesId): void
    {

        if (empty($jsonLdScriptsData)) {
            return;
        }

        foreach ($jsonLdScriptsData as $arrDataScriptsData) {

            $strSerialized = serialize($arrDataScriptsData);

            $strChecksum = md5($strSerialized);
            $objMicrodataModel = MicrodataModel::findByChecksumAndPid($strChecksum, $indicesId);

            if (!$objMicrodataModel) {
                $objMicrodataModel = new MicrodataModel();
            }

            $objMicrodataModel->tstamp = time();
            $objMicrodataModel->pid = $indicesId;
            $objMicrodataModel->type = $this->type;
            $objMicrodataModel->checksum = $strChecksum;
            $objMicrodataModel->data = $strSerialized;

            $objMicrodataModel->save();
        }
    }

    public function getJsonLdScriptsData(): array
    {

        return $this->jsonLdScriptsData;
    }

    public function generate(array $arrParentData = []): string
    {
        return '';
    }

    public function match($arrKeyWords): void
    {
        //
    }

    protected function getImage($varImage): string
    {

        if (is_array($varImage) && !empty($varImage)) {
            return $varImage[0] ?? '';
        }

        if (is_string($varImage) && !empty($varImage)) {
            return $varImage;
        }

        return '';
    }
}