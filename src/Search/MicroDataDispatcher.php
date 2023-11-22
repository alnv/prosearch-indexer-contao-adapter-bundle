<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Search;

use Contao\CoreBundle\Search\Document;

/**
 *
 */
class MicroDataDispatcher
{

    /**
     * @param Document $document
     * @param int $indicesId
     */
    public function __construct(Document $document, int $indicesId)
    {

        if (!empty($GLOBALS['PS_MICRODATA_CLASSES']) && is_array($GLOBALS['PS_MICRODATA_CLASSES'])) {

            foreach ($GLOBALS['PS_MICRODATA_CLASSES'] as $strKey => $strClass) {
                (new $strClass())->dispatch($document->extractJsonLdScripts('https://schema.org', $strKey), $indicesId);
            }
        }
    }
}