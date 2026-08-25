<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Adapter;

use Alnv\ProSearchIndexerContaoAdapterBundle\Models\IndicesModel;
use Contao\PageModel;

/**
 *
 */
abstract class AbstractAdapter
{

    protected $objClient = null;

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
     * @return mixed
     */
    abstract public function getClient(): mixed;

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
        $blnUseSingleDocument = (bool)$this->arrCredentials['singleDocument'];
        if ($blnUseSingleDocument) {
            $strRootIdentifier = 'single';
        }

        return Elasticsearch::INDEX . '_' . $this->strSignature . ($strRootIdentifier ? '_' . $strRootIdentifier : '');
    }
}