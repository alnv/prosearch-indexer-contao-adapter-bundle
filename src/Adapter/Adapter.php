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
    abstract public function search(array $arrKeywords, array $arrOptions=[]) : array;
}