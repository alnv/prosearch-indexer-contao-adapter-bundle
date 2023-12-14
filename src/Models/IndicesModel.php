<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Models;

use Contao\Model;

/**
 *
 */
class IndicesModel extends Model
{

    protected static $strTable = 'tl_indices';

    public static function findByUrl(string $strUrl): null|Model
    {
        $t = static::$strTable;
        $arrColumns = ["$t.url=?"];

        return static::findOneBy($arrColumns, [$strUrl], []);
    }
}