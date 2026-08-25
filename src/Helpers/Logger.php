<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Helpers;

use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Database;
use Contao\System;

class Logger
{

    public static function set($message, $logLevel, $context): void
    {
        if (!Database::getInstance()->tableExists('tl_log')) {
            return;
        }

        $message = \strip_tags($message);
        $message = \str_replace(["\r", "\n"], '', $message);
        $message = \trim($message);

        $log = Database::getInstance()
            ->prepare('SELECT text FROM tl_log WHERE text=?')
            ->limit(1)
            ->execute($message);

        if ($log->numRows) {
            return;
        }

        System::getContainer()
            ->get('monolog.logger.contao')
            ->log($logLevel, $message, ['contao' => new ContaoContext($context)]);
    }
}