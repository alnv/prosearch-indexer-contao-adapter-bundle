<?php

$GLOBALS['TL_LANG']['MSC']['didYouMeanLabel'] = 'Meinten Sie ';
$GLOBALS['TL_LANG']['MSC']['searchResultsForLabel'] = 'Ergebnisse für ';
$GLOBALS['TL_LANG']['MSC']['searchResultsCategoriesHeadline'] = 'Kategorien';
$GLOBALS['TL_LANG']['MSC']['noSearchResults'] = 'Keine Suchergebnisse gefunden.';
$GLOBALS['TL_LANG']['MSC']['searchResultsCloseButtonLabel'] = 'Schließen';
$GLOBALS['TL_LANG']['MSC']['searchResultsMoreButton'] = 'Mehr laden';
$GLOBALS['TL_LANG']['MSC']['searchButtonLabel'] = '';
$GLOBALS['TL_LANG']['MSC']['searchInputLabel'] = '';

// accessible front end (ProSearch 4)
$GLOBALS['TL_LANG']['MSC']['searchFormLabel'] = 'Website-Suche';
$GLOBALS['TL_LANG']['MSC']['searchInputLabelDefault'] = 'Suchbegriff';
$GLOBALS['TL_LANG']['MSC']['searchSubmitLabel'] = 'Suche starten';
$GLOBALS['TL_LANG']['MSC']['searchHint'] = 'Bitte mindestens %s Zeichen eingeben.';
$GLOBALS['TL_LANG']['MSC']['searchResultsHeadline'] = 'Suchergebnisse';
$GLOBALS['TL_LANG']['MSC']['searchResultsClose'] = 'Suchergebnisse schließen';
$GLOBALS['TL_LANG']['MSC']['searchResultsClosed'] = 'Suchergebnisse geschlossen.';
$GLOBALS['TL_LANG']['MSC']['searchResultsCount'] = '%1$s Ergebnisse für „%2$s“';
$GLOBALS['TL_LANG']['MSC']['searchResultsCountOne'] = '1 Ergebnis für „%2$s“';
$GLOBALS['TL_LANG']['MSC']['searchResultsLoading'] = 'Suche läuft …';
$GLOBALS['TL_LANG']['MSC']['searchResultsMoreLoaded'] = '%s weitere Ergebnisse geladen.';
$GLOBALS['TL_LANG']['MSC']['searchResultsNoMore'] = 'Keine weiteren Ergebnisse.';
$GLOBALS['TL_LANG']['MSC']['searchResultsError'] = 'Die Suche ist gerade nicht verfügbar. Bitte versuchen Sie es später noch einmal.';
$GLOBALS['TL_LANG']['MSC']['searchSuggestionsLabel'] = 'Suchvorschläge';
$GLOBALS['TL_LANG']['MSC']['searchSuggestionsCount'] = '%s Vorschläge verfügbar. Mit den Pfeiltasten auswählen, mit Enter übernehmen.';
$GLOBALS['TL_LANG']['MSC']['searchSuggestionsCountOne'] = '1 Vorschlag verfügbar. Mit der Pfeiltaste nach unten auswählen, mit Enter übernehmen.';
$GLOBALS['TL_LANG']['MSC']['searchNoSuggestions'] = 'Keine Vorschläge.';
$GLOBALS['TL_LANG']['MSC']['searchResultNewWindow'] = '(öffnet in neuem Fenster)';

$GLOBALS['TL_LANG']['MSC']['searchButtonIcon'] = '/bundles/alnvprosearchindexercontaoadapter/search-icon.svg';
$GLOBALS['TL_LANG']['MSC']['searchButtonLoadingIcon'] = '/system/themes/flexible/icons/loading.svg';

// Analyzer names (technical, same in all languages)
$GLOBALS['TL_LANG']['MSC']['psAnalyzer'] = [
    'contao' => 'whitespace',
    'german' => 'lowercase//german_stopwords//german_stemmer',
    'english' => 'lowercase//english_stopwords//english_stemmer',
    'standard' => 'ProSearch//Standard',
    'lowercase' => 'ProSearch//lowercase',
    'whitespace' => 'ProSearch//Whitespace',
    'keyword' => 'ProSearch//Keyword',
    'stop' => 'ProSearch//Stop',
    'simple' => 'ProSearch//Simple',
    'french' => 'lowercase//french_stopwords//french_stemmer'
];
