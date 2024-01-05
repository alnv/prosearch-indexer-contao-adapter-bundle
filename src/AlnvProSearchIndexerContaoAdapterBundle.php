<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class AlnvProSearchIndexerContaoAdapterBundle extends Bundle
{

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}