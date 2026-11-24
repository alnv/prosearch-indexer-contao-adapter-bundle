<?php

// Legends
$GLOBALS['TL_LANG']['tl_module']['search_legend'] = 'Search settings';
$GLOBALS['TL_LANG']['tl_module']['style_legend'] = 'Style settings';

// Fields
$GLOBALS['TL_LANG']['tl_module']['psLanguage'] = ['Language (ISO 639)', 'Which language should be searched? It has to match the language set in the site structure. Empty = language of the current page.'];
$GLOBALS['TL_LANG']['tl_module']['psDomains'] = ['Domains', 'Domains that can be searched. Separate several domains with commas.'];
$GLOBALS['TL_LANG']['tl_module']['psSearchCategories'] = ['Categories', 'The search only returns the selected categories, in this order. No selection = all categories.'];
$GLOBALS['TL_LANG']['tl_module']['psAutoCompletionType'] = ['Type of suggestions', '"Word completion" suggests search terms, "Search results" shows matching pages directly.'];
$GLOBALS['TL_LANG']['tl_module']['psOpenDocumentInBrowser'] = ['Open documents in the browser', 'Show PDFs and other files in the browser instead of downloading them.'];
$GLOBALS['TL_LANG']['tl_module']['psPreventCssLoading'] = ['Do not load CSS files', 'Do not include the bundled style sheets, e.g. if the theme styles the search itself.'];
$GLOBALS['TL_LANG']['tl_module']['psResultsTemplate'] = ['Search result template', 'Template of a single search result (default: elasticsearch_result).'];

// References
$GLOBALS['TL_LANG']['tl_module']['psReference'] = [];
$GLOBALS['TL_LANG']['tl_module']['psReference']['simple'] = ['Word completion', 'Suggest search terms'];
$GLOBALS['TL_LANG']['tl_module']['psReference']['advanced'] = ['Search results', 'Suggest matching pages directly'];
