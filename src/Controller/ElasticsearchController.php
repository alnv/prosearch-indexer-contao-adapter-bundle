<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Controller;

\trigger_deprecation('alnv/prosearch-indexer-contao-adapter-bundle', '4.0', 'Using the "Alnv\ProSearchIndexerContaoAdapterBundle\Controller\ElasticsearchController" class is deprecated and will no longer work in 5.0. Use "Alnv\ProSearchIndexerContaoAdapterBundle\Controller\ProSearchController" instead.');

// no class declaration on purpose: the attribute route loader must not register the routes twice
class_alias(ProSearchController::class, 'Alnv\ProSearchIndexerContaoAdapterBundle\Controller\ElasticsearchController');
