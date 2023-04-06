<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Helpers;

class Signature {

    public static function generate() {

        return 'si' . time() . substr(str_shuffle(uniqid()), 0, 8);
    }
}