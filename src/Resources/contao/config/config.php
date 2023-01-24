<?php

use \Alnv\ProSearchIndexerContaoAdapterBundle\Models\MicrodataModel;
use \Alnv\ProSearchIndexerContaoAdapterBundle\Models\IndicesModel;
use \Alnv\ProSearchIndexerContaoAdapterBundle\Models\DocumentsModels;

/**
 * Models
 */
$GLOBALS['TL_MODELS'] = [
    'tl_documents' => DocumentsModels::class,
    'tl_microdata' => MicrodataModel::class,
    'tl_indices' => IndicesModel::class
];
