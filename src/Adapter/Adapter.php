<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Adapter;

/**
 *
 */
abstract class Adapter
{

    /**
     * @var
     */
    private $objClient;

    /**
     * @var string
     */
    private string $strLicense = "";

    /**
     * @var array
     */
    protected array $arrOptions = [];

    /**
     * @param array $arrOptions
     */
    public function __construct(array $arrOptions)
    {
        $this->arrOptions = $arrOptions;
    }

    /**
     * @return mixed
     */
    abstract public function connect();

    /**
     * @return mixed
     */
    abstract public function getClient();

    /**
     * @param array $arrKeywords
     * @param array $arrOptions
     * @return array
     */
    abstract public function search(array $arrKeywords, array $arrOptions = []): array;

    /**
     * @param $strIndicesId
     * @return void
     */
    abstract public function deleteIndex($strIndicesId): void;
}