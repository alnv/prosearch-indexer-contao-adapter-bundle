<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Adapter;

class Adapter
{
    public function getInstance(array $options = []): AbstractAdapter
    {
        return new ProxyAdapter($options);
    }
}