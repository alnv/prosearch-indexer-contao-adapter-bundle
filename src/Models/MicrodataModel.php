<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Models;

use Contao\Model;
use Contao\Model\Collection;

/**
 *
 */
class MicrodataModel extends Model
{

    protected static $strTable = 'tl_microdata';

    public static function findByChecksumAndPid(string $strChecksum, int $intPid): Model|null
    {

        $t = static::$strTable;
        $arrColumns = ["$t.checksum=? AND $t.pid=?"];

        return static::findOneBy($arrColumns, [$strChecksum, $intPid], []);
    }

    public static function findByPid(int $intPid): Collection|null
    {

        $t = static::$strTable;

        return static::findAll([
            'column' => ["$t.pid=?"],
            'value' => [$intPid],
            'order' => 'tstamp DESC'
        ]);
    }

    public static function findByPidAndType(int $intPid, string $strType): Collection|null
    {

        $t = static::$strTable;

        return static::findAll([
            'column' => ["$t.pid=? AND type=?"],
            'value' => [$intPid, $strType],
            'order' => 'tstamp DESC'
        ]);
    }
}