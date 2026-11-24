<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Adapter;

use Alnv\ProSearchIndexerContaoAdapterBundle\Search\IndexNameResolver;
use Contao\System;

abstract class AbstractAdapter
{
    /**
     * @var mixed client of the former implementations; ProxyAdapter stores the ProxyClient here
     */
    protected $objClient = null;

    protected string $strLicense = '';

    protected array $arrOptions = [];

    public function __construct(array $arrOptions = [])
    {
        $this->arrOptions = $arrOptions;
    }

    abstract public function connect(): void;

    abstract public function getClient(): mixed;

    abstract public function search(array $arrKeywords, string $strIndexName, int $intTryCounts): array;

    abstract public function deleteIndex($strIndicesId): void;

    abstract public function deleteDatabases(): void;

    protected function getRootIdentifierFromIndicesId($strIndicesId): string
    {
        return System::getContainer()->get(IndexNameResolver::class)->getRootIdentifierFromIndicesId($strIndicesId);
    }

    public function getIndexName($strRootIdentifier): string
    {
        return System::getContainer()->get(IndexNameResolver::class)->getIndexName($strRootIdentifier);
    }
}