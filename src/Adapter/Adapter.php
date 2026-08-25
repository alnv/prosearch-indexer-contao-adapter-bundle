<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Adapter;

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Credentials;

class Adapter
{
    public function getInstance(array $options = []): AbstractAdapter
    {
        $credentials = (new Credentials())->getCredentials();

        if ($credentials === false) {
            return new Elasticsearch($options);
        }

        if ($credentials['type'] === 'opensearch') {
            return new Opensearch($options);
        }

        return new Elasticsearch($options);
    }
}