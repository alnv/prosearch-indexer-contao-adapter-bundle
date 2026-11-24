<?php

// Fields
$GLOBALS['TL_LANG']['tl_search_credentials']['id'] = ['ID', 'Unique ID of the record.'];
$GLOBALS['TL_LANG']['tl_search_credentials']['tstamp'] = ['Revision date', 'Date and time of the last change.'];
$GLOBALS['TL_LANG']['tl_search_credentials']['signature'] = ['Signature', 'Identifier of this installation. It is part of the index names and generated automatically.'];
$GLOBALS['TL_LANG']['tl_search_credentials']['type'] = ['Package', 'Type of connection.'];
$GLOBALS['TL_LANG']['tl_search_credentials']['licence'] = ['API key', 'Connection via the ProSearch proxy with a license key.'];
$GLOBALS['TL_LANG']['tl_search_credentials']['proxyUrl'] = ['ProSearch proxy URL', 'Base URL of the ProSearch proxy without /api/v1, e.g. https://proxy.example.org. Leave empty to use the configured default (PROSEARCH_PROXY_URL).'];
$GLOBALS['TL_LANG']['tl_search_credentials']['proxyApiKey'] = ['Proxy API key', 'Optional: API key if the proxy issues one (header X-Api-Key). Empty = PROSEARCH_PROXY_API_KEY.'];
$GLOBALS['TL_LANG']['tl_search_credentials']['authToken'] = ['API token', 'Access token of the license key. It is sent to the proxy together with key and domain.'];
$GLOBALS['TL_LANG']['tl_search_credentials']['keys'] = ['API keys', 'License key per domain. Without a matching domain the first key is used.'];
$GLOBALS['TL_LANG']['tl_search_credentials']['key'] = ['API key', 'License key.'];
$GLOBALS['TL_LANG']['tl_search_credentials']['domain'] = ['Domain', 'Domain the key is valid for (without protocol, e.g. www.example.org).'];
$GLOBALS['TL_LANG']['tl_search_credentials']['singleDocument'] = ['Collect all documents in a single database', 'All your pages (domains, including external pages) are stored in the same database, so all pages can be searched regardless of the domain.'];
$GLOBALS['TL_LANG']['tl_search_credentials']['analyzer'] = ['Analyzer', 'Analyzer of the shared database (only with "Collect all documents in a single database").'];

// Search engine detection (read-only, set automatically)
$GLOBALS['TL_LANG']['tl_search_credentials']['engineType'] = ['Search engine', 'Detected automatically (OpenSearch/Elasticsearch).'];
$GLOBALS['TL_LANG']['tl_search_credentials']['engineVersion'] = ['Version', 'Version of the search engine.'];
$GLOBALS['TL_LANG']['tl_search_credentials']['engineChunks'] = ['Chunks', 'Chunks are transferred (OpenSearch 3 or newer).'];
$GLOBALS['TL_LANG']['tl_search_credentials']['engineVectors'] = ['Vector search', 'k-NN plugin available (for embeddings later on).'];
$GLOBALS['TL_LANG']['tl_search_credentials']['engineCheckedAt'] = ['Detected at', 'Date and time of the last detection.'];
$GLOBALS['TL_LANG']['tl_search_credentials']['engineError'] = ['Detection error', 'Error message if the search engine could not be detected.'];
$GLOBALS['TL_LANG']['tl_search_credentials']['engineConnection'] = ['Detected connection', 'Proxy connection the result applies to. If it changes, the engine is detected again.'];

// Legacy fields (ProSearch < 4, direct connection to the cluster)
$GLOBALS['TL_LANG']['tl_search_credentials']['host'] = ['Host', ''];
$GLOBALS['TL_LANG']['tl_search_credentials']['port'] = ['Port', ''];
$GLOBALS['TL_LANG']['tl_search_credentials']['username'] = ['Username', ''];
$GLOBALS['TL_LANG']['tl_search_credentials']['password'] = ['Password', ''];
$GLOBALS['TL_LANG']['tl_search_credentials']['cloudid'] = ['Cloud ID', ''];
$GLOBALS['TL_LANG']['tl_search_credentials']['cert'] = ['Certificate path', ''];

// Operations
$GLOBALS['TL_LANG']['tl_search_credentials']['edit'] = ['Edit', 'Edit record ID %s'];
$GLOBALS['TL_LANG']['tl_search_credentials']['delete'] = ['Delete', 'Delete record ID %s'];
$GLOBALS['TL_LANG']['tl_search_credentials']['show'] = ['Details', 'Show the details of record ID %s'];
