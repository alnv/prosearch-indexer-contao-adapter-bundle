<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Adapter;

use Elastic\Elasticsearch\Client;

/**
 *
 */
abstract class Adapter
{

    /**
     * @var Client|null
     */
    protected Client|null $objClient = null;

    /**
     * @var string
     */
    protected string $strLicense = "";

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
     * @return void
     */
    abstract public function connect(): void;

    /**
     * @return Client|null
     */
    abstract public function getClient(): Client|null;

    /**
     * @param array $arrKeywords
     * @param string $strIndexName
     * @param int $intTryCounts
     * @return array
     */
    abstract public function search(array $arrKeywords, string $strIndexName, int $intTryCounts): array;

    /**
     * @param $strIndicesId
     * @return void
     */
    abstract public function deleteIndex($strIndicesId): void;

    /**
     * @return void
     */
    abstract public function deleteDatabases(): void;
}