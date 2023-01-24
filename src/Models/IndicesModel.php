<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Models;

use Contao\Model;

/**
 *
 */
class IndicesModel extends Model
{

    /**
     * @var string
     */
    protected static $strTable = 'tl_indices';

    /**
     * @param string $strUrl
     * @return IndicesModel|Model|Model[]|Model\Collection|null
     */
    public static function findByUrl(string $strUrl)
    {
        $t = static::$strTable;
        $arrColumns = ["$t.url=?"];

        return static::findOneBy($arrColumns, [$strUrl], []);
    }
}