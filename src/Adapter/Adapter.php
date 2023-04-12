<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Adapter;

use Contao\ModuleModel;
use Contao\PageModel;

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
     * @var \Contao\ModuleModel|null
     */
    protected $objModule;

    /**
     * @var
     */
    protected $objRoot;

    /**
     * @param $strModuleId
     * @param $strRootId
     */
    public function __construct($strModuleId = null, $strRootId = null)
    {
        if ($strModuleId) {
            $this->objModule = ModuleModel::findByPk($strModuleId);
        }

        if ($strRootId) {
            $this->objRoot = PageModel::findByPk($strRootId);
        }
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