<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Adapter;

use Alnv\ProSearchIndexerContaoAdapterBundle\Models\IndicesModel;
use Contao\PageModel;

abstract class AbstractAdapter
{
    protected $objClient = null;
    protected string $strLicense = "";
    protected array $arrOptions = [];

    public function __construct(array $arrOptions)
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
        $objIndicesModel = IndicesModel::findByPk($strIndicesId);
        if (!$objIndicesModel) {
            return '';
        }

        if (!$objIndicesModel->pageId) {
            return '';
        }

        $objPage = PageModel::findByPk($objIndicesModel->pageId);
        if (!$objPage) {
            return '';
        }

        $objPage->loadDetails();

        return $objPage->rootId;
    }

    public function getIndexName($strRootIdentifier): string
    {
        $blnUseSingleDocument = (bool)($this->arrCredentials['singleDocument'] ?? false);
        if ($blnUseSingleDocument) {
            $strRootIdentifier = 'single';
        }

        return Elasticsearch::INDEX . '_' . $this->strSignature . ($strRootIdentifier ? '_' . $strRootIdentifier : '');
    }
}