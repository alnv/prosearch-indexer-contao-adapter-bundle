<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Helpers;

class Signature
{

    public static function generate(): string
    {

        return 'si' . time() . substr(str_shuffle(uniqid()), 0, 8);
    }
}