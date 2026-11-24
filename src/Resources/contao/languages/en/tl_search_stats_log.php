<?php

// Fields
$GLOBALS['TL_LANG']['tl_search_stats_log']['id'] = ['ID', 'Unique ID of the entry.'];
$GLOBALS['TL_LANG']['tl_search_stats_log']['tstamp'] = ['Date', 'Date and time of the search. For imported legacy data the date of the last search for this term.'];
$GLOBALS['TL_LANG']['tl_search_stats_log']['keyword'] = ['Search term', 'Entered search term.'];
$GLOBALS['TL_LANG']['tl_search_stats_log']['hits'] = ['Results', 'Number of search results'];
$GLOBALS['TL_LANG']['tl_search_stats_log']['clicks'] = ['Clicks', 'Clicks on search results (conversions)'];
$GLOBALS['TL_LANG']['tl_search_stats_log']['urls'] = ['Visited pages', 'Search results clicked after this search.'];
$GLOBALS['TL_LANG']['tl_search_stats_log']['types'] = ['Context', 'Categories of the search'];
$GLOBALS['TL_LANG']['tl_search_stats_log']['source'] = ['Source', 'Page the search was made on'];
$GLOBALS['TL_LANG']['tl_search_stats_log']['legacy'] = ['Legacy data', 'Imported from the former statistics (tl_search_stats)'];

// Operations
$GLOBALS['TL_LANG']['tl_search_stats_log']['delete'] = ['Delete', 'Delete entry ID %s'];
$GLOBALS['TL_LANG']['tl_search_stats_log']['show'] = ['Details', 'Show the details of entry ID %s'];
$GLOBALS['TL_LANG']['tl_search_stats_log']['export_terms'] = ['Export terms', 'Export search terms with count, results and clicks as CSV'];
$GLOBALS['TL_LANG']['tl_search_stats_log']['export_log'] = ['Export log', 'Export all entries of the search log as CSV'];
$GLOBALS['TL_LANG']['tl_search_stats_log']['clear_log'] = ['Clear log', 'Delete all entries of the search log'];

// Messages
$GLOBALS['TL_LANG']['tl_search_stats_log']['clearConfirm'] = 'Do you really want to delete the complete search log including legacy data? This cannot be undone.';
$GLOBALS['TL_LANG']['tl_search_stats_log']['cleared'] = 'The search log has been cleared.';

// CSV export columns
$GLOBALS['TL_LANG']['tl_search_stats_log']['csv'] = [
    'date' => 'Date',
    'keyword' => 'Search term',
    'hits' => 'Results',
    'clicks' => 'Clicks',
    'urls' => 'Visited pages',
    'types' => 'Context',
    'source' => 'Source',
    'legacy' => 'Legacy data',
    'searches' => 'Searches',
    'avg_hits' => 'Ø results',
    'zero' => 'Searches without results',
    'first' => 'First search',
    'last' => 'Last search'
];
