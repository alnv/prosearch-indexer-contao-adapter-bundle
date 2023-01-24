<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Models;

use Contao\Model;

/**
 *
 */
class MicrodataModel extends Model
{

    /**
     * @var string
     */
    protected static $strTable = 'tl_microdata';

    /**
     * @param string $strChecksum
     * @param int $intPid
     * @return MicrodataModel|Model|Model[]|Model\Collection|null
     */
    public static function findByChecksumAndPid(string $strChecksum, int $intPid)
    {
        $t = static::$strTable;
        $arrColumns = ["$t.checksum=? AND $t.pid=?"];

        return static::findOneBy($arrColumns, [$strChecksum, $intPid], []);
    }
}