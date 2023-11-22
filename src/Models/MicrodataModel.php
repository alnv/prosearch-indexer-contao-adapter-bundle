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

    public static function findByPid(int $intPid)
    {

        $t = static::$strTable;

        return static::findAll([
            'column' => ["$t.pid=?"],
            'value' => [$intPid],
            'order' => 'tstamp DESC'
        ]);
    }

    public static function findByPidAndType(int $intPid, string $strType)
    {

        $t = static::$strTable;

        return static::findAll([
            'column' => ["$t.pid=? AND type=?"],
            'value' => [$intPid, $strType],
            'order' => 'tstamp DESC'
        ]);
    }
}