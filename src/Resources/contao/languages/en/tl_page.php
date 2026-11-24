<?php

// Legends
$GLOBALS['TL_LANG']['tl_page']['ps_search_legend'] = 'ProSearch settings';

// Fields
$GLOBALS['TL_LANG']['tl_page']['psAnalyzer'] = ['Analyzer', 'Splits the texts of this website root into search terms, e.g. "german" (stop words, stemming), "standard" (words without punctuation) or "contao" (split at spaces only). Applies to the index and, matching it, to the queries of all search modules. Takes effect after rebuilding the index.'];
$GLOBALS['TL_LANG']['tl_page']['psAnalyzerRebuild'] = 'The analyzer takes effect after rebuilding the index: vendor/bin/contao-console prosearch:index:rebuild --root=%s --force. Until then searches keep using the previous analyzer.';
$GLOBALS['TL_LANG']['tl_page']['psSearchCategory'] = ['Category', 'Categories of this page for the search, separated by commas (e.g. "page,service"). Spaces, hyphens, underscores and dots are removed.'];
