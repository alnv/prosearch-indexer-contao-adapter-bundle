<?php

// Fields
$GLOBALS['TL_LANG']['tl_synonyms']['id'] = ['ID', 'Unique ID of the record.'];
$GLOBALS['TL_LANG']['tl_synonyms']['tstamp'] = ['Revision date', 'Date and time of the last change.'];
$GLOBALS['TL_LANG']['tl_synonyms']['keyword'] = ['Target term', 'Term that is searched in addition, e.g. the name of a service ("citizen\'s income").'];
$GLOBALS['TL_LANG']['tl_synonyms']['synonyms'] = ['Synonyms', 'Search terms that also search for the target term, one per line (e.g. "unemployment benefit"). Only whole words or phrases count, case does not matter. The typed term is still searched. Better avoid very general terms such as "application".'];
$GLOBALS['TL_LANG']['tl_synonyms']['disable'] = ['Disable', 'Temporarily do not use this entry.'];

// Operations
$GLOBALS['TL_LANG']['tl_synonyms']['edit'] = ['Edit', 'Edit synonym ID %s'];
$GLOBALS['TL_LANG']['tl_synonyms']['copy'] = ['Duplicate', 'Duplicate synonym ID %s'];
$GLOBALS['TL_LANG']['tl_synonyms']['delete'] = ['Delete', 'Delete synonym ID %s'];
$GLOBALS['TL_LANG']['tl_synonyms']['show'] = ['Details', 'Show the details of synonym ID %s'];

// Messages
$GLOBALS['TL_LANG']['tl_synonyms']['sharedTerms'] = 'These search terms are also used in other active records and therefore lead to several target terms: %s';
$GLOBALS['TL_LANG']['tl_synonyms']['noTerms'] = 'Please enter at least one search term that differs from the target term.';
$GLOBALS['TL_LANG']['tl_synonyms']['disabledLabel'] = 'disabled';
