<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Helpers;

class Text
{

    public static function tokenize($strString): string
    {

        $strString = str_replace(["\r", "\n"], " ", $strString);
        $strString = preg_replace('/\s+/', ' ', $strString);

        return trim($strString);
    }
}