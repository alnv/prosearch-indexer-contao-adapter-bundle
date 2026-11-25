# Upgrade auf 4.0 (Aufteilung Adapter / Proxy)

## Was sich ändert

- Das Adapter-Bundle spricht nur noch mit der REST-API des ProSearch-Proxys
  (`alnv/prosearch-proxy-bundle`). Direkte Verbindungen zu OpenSearch/Elasticsearch und die
  Abhängigkeiten `elasticsearch/elasticsearch`, `opensearch-project/opensearch-php`, `guzzlehttp/guzzle` und
  `ext-curl` entfallen.
- Die Lizenzprüfung findet ausschließlich im Proxy statt. Lizenzdaten in `tl_search_credentials` bleiben unverändert
  (Signatur, API-Token, Lizenzschlüssel pro Domain). Neu sind die Felder `proxyUrl` und `proxyApiKey`: Die
  Proxy-URL wird im Backend eingetragen, leer = `PROSEARCH_PROXY_URL`. Schema per `contao:migrate` aktualisieren.
- Die Index-Namen bleiben gleich (`contao_search_<Signatur>_<Root-ID|single>`), bestehende Indizes werden
  weiterverwendet.
- Der bisherige Controller `/proxy/*` ist aus dem Adapter entfernt und lebt wire-kompatibel (deprecated) im
  Proxy-Bundle weiter, damit noch nicht aktualisierte Installationen funktionieren.
- Die OpenAI-Integration (`alnv/contao-open-ai-assistant-bundle`) ist vollständig entfernt: OpenAI-Fallback-Suche,
  Chat-Ergebnis-Parser (`$GLOBALS['OPEN_AI_MESSAGE_PARSER']['elasticsearch']`), Vektordateien für den Vector Store
  (`tl_search_vector_files`, Backend „Indexes“ → „Vektordatei erstellen“) und die Modul-Optionen „Open-AI verwenden“,
  „Assistant“ und „Score-Relevanz“.
- Die Mikrodaten-Funktion (Schema.org/JSON-LD) ist vollständig entfernt: Klassen unter `MicroData\*`, Tabelle
  `tl_microdata`, `$GLOBALS['PS_MICRODATA_CLASSES']`, Rich Snippets in den Suchergebnissen (Modul-Option
  „RichSnippets verwenden“, FAQ-Block) und die Templates `md_person`/`md_product`. Mikrodaten-Typen werden nicht mehr
  als Kategorien bzw. Dokument-Typen übernommen.
- Neues Backend-Modul „Dashboard“ (Suchanalyse, `searchdashboard`). Nicht-Admins brauchen dafür das Modulrecht
  „Dashboard“.
- Die Suchstatistik ist jetzt eine Tabelle mit einer Zeile je Suche: `tl_search_stats_log` ersetzt `tl_search_stats`.
  Das Backend-Modul „Statistik“ heißt jetzt „Suchprotokoll“ (`do=stats`) und bietet „Begriffe exportieren“,
  „Protokoll exportieren“ (beides CSV) und „Protokoll leeren“. Der CSV-Export kommt ohne `phpoffice/phpspreadsheet` aus,
  die Abhängigkeit ist entfernt. `Helpers\Stats` hat nur noch `setKeyword()` und `setClick()`, `export()`, `reset()`
  und die internen Zähler-Methoden sind entfernt.

### Übernahme der bisherigen Statistik

`contao:migrate` führt die Migration „ProSearch: merge tl_search_stats into tl_search_stats_log“ aus. Sie fächert jede
Zeile der alten Tabelle in so viele Einzelzeilen auf, wie Suchen gezählt wurden, markiert sie als Altdaten
(`legacy = '1'`) und löscht danach `tl_search_stats`. Was dabei erhalten bleibt und was nicht:

| Erhalten (exakt) | Verloren bzw. angenähert |
|---|---|
| Anzahl der Suchen je Begriff | Zeitpunkt der einzelnen Suchen: alle tragen den alten `tstamp`, also das Datum der letzten Suche oder des letzten Klicks auf den Begriff |
| Summe der Treffer je Begriff (gleichmäßig auf die Suchen verteilt) | Trefferzahl der einzelnen Suche und damit „ohne Treffer“ (nur Begriffe, die nie etwas gefunden haben, zählen als ohne Treffer) |
| Klicks und aufgerufene Seiten (an der ersten Zeile je alter Zeile) | |
| Quellen mit ihrer Anzahl, Kontext (Kategorien) | |

Nicht übernommen werden Zeilen mit leerem Suchbegriff (das Dashboard hat sie schon vorher ignoriert). Begriffe werden
getrimmt (auch Tabulatoren), dadurch können zwei alte Schreibweisen zu einem Begriff zusammenfallen.

Die Migration prüft vor dem Löschen der alten Tabelle, ob Suchen, Treffer und Klicks in Summe übereinstimmen. Weicht
etwas ab, bricht sie mit einer Exception ab, `tl_search_stats` bleibt erhalten und die Migration kann erneut laufen.
Ein abgebrochener Lauf hinterlässt keine doppelten Zeilen.

Richtwert: 124 000 alte Zeilen mit 584 000 Suchen dauern rund 15 Sekunden bei unter 20 MB Speicher. Große
Installationen sollten `contao:migrate` auf der Kommandozeile ausführen, nicht im Contao Manager, um Zeitlimits des
Webservers zu umgehen. Vorher ein Backup anlegen (`contao:backup:create`): Der Zeitpunkt der alten Suchen lässt sich
nach der Migration nicht wiederherstellen.

Die automatische Löschung alter Einträge ist standardmäßig aus (`stats.log_retention_days: 0`). Ein Wert über 0 würde
auch übernommene Altdaten löschen, deren Datum älter ist.

## Migrationsschritte

### Gehosteter Proxy (Pakettyp „API-Key“ / `licence`)

1. **Proxy-Server zuerst aktualisieren** (Betreiber): Proxy-Bundle auf `elasticsearch.sineos.de` installieren,
   `PROSEARCH_BACKEND_*` setzen, `license.test_keys` auf `[]` setzen, Routen prüfen
   (`/api/v1/health` muss `"status": "ok"` liefern). Die alten `/proxy/*`-Endpunkte bleiben aktiv.
2. Adapter auf 4.0 aktualisieren (`composer update alnv/prosearch-indexer-contao-adapter-bundle`).
3. Cache leeren (`vendor/bin/contao-console cache:clear`) und Datenbank aktualisieren (`contao:migrate`, legt
   `tl_search_credentials.proxyUrl`/`proxyApiKey` an). Die nicht mehr verwendeten Tabellen `tl_microdata`,
   `tl_search_vector_files` und die Spalten `tl_module.psUseOpenAi`, `psOpenAssistant`, `psOpenAiRelevance`,
   `psUseRichSnippets` entfernt erst `contao:migrate --with-deletes` (vorher Backup). Früher erzeugte
   Vektordateien unter `files/_vectors/` können gelöscht werden.
4. Backend → ProSearch → Zugangsdaten öffnen, bei Bedarf die „ProSearch-Proxy-URL“ eintragen (leer = gehosteter
   Proxy) und speichern: Verbindung und Lizenz werden über den Proxy geprüft, Fehler erscheinen als Meldung.
5. Optional Suchindex neu aufbauen (Systemwartung → „Den Suchindex aktualisieren“). Nötig ist das nicht, das
   Mapping ist unverändert.

### Eigener Cluster (früher Pakettyp „elasticsearch“, „opensearch“, „elasticsearch_cloud“)

Die Felder Host/Port/Benutzer/Passwort/Cert in `tl_search_credentials` werden vom Adapter nicht mehr verwendet.

1. Proxy-Bundle installieren – in derselben Contao-Installation (`composer require
   alnv/prosearch-proxy-bundle`) oder auf einem eigenen Server. Das Bundle hat kein Contao-Manager-Plugin:
   in Contao per App-Plugin (`src/ContaoManager/Plugin.php`) registrieren und die Routen in
   `config/routes.yaml` importieren (Anleitung im README dieses Adapters, „Proxy in derselben Contao-Installation betreiben“).
2. Zugangsdaten in die Umgebung des Proxys übernehmen:
   ```dotenv
   PROSEARCH_BACKEND_TYPE=opensearch          # oder elasticsearch
   PROSEARCH_BACKEND_URL=https://search.example.org:9200
   PROSEARCH_BACKEND_USERNAME=…
   PROSEARCH_BACKEND_PASSWORD=…
   # Elastic Cloud: PROSEARCH_BACKEND_API_KEY=…
   # Selbst signiertes Zertifikat: PROSEARCH_BACKEND_CA_FILE=/pfad/zur/ca.pem
   ```
3. Ohne ProSearch-Lizenz im Proxy `prosearch_proxy: { license: { required: false } }` setzen.
4. Im Adapter unter Zugangsdaten die „ProSearch-Proxy-URL“ der Proxy-Installation eintragen (bei gleicher
   Installation die eigene Domain). Alternativ `PROSEARCH_PROXY_URL` setzen.
5. Cache leeren, Zugangsdaten im Backend einmal speichern (Verbindungstest).

## Entfernte bzw. geänderte Schnittstellen

| Bisher | Jetzt |
|---|---|
| `Adapter\Elasticsearch`, `Adapter\Opensearch` (direkter Client) | deprecated Unterklassen von `Adapter\ProxyAdapter`, gleiche öffentliche Methoden |
| `Adapter\Adapter::getInstance()` wählte nach Pakettyp | liefert immer `ProxyAdapter` |
| `AbstractAdapter::getClient()` lieferte ES/OS-Client | liefert `Proxy\ProxyClient` oder `null` ohne Zugangsdaten |
| `Adapter\Proxy` (Guzzle, `?licence=`) | deprecated, nutzt intern `ProxyClient` |
| `Helpers\Authorization::isValid()` (lokale Prüfung + `var/authorization/auth.txt`) | deprecated, fragt den Proxy (`/api/v1/license`); Hilfsmethoden delegieren an `LicenseProvider` |
| `Controller\ElasticsearchProxyController` (`/proxy/*`) | entfernt, Ersatz im Proxy-Bundle (`Controller\Legacy\LegacyProxyController`) |
| Hooks `psSearchQuery`, `psAutoCompilation` | werden nicht mehr aufgerufen (Deprecation-Hinweis); neu: `psProxySearchRequest` |
| `var/authorization/auth.txt` | wird nicht mehr gelesen/geschrieben und kann gelöscht werden |
| `AI\AiElasticsearch`, `AI\AiChatComponentParser`, `Entity\SearchVectorFile` | entfernt (OpenAI-Integration) |
| `MicroData\*`, `Models\MicrodataModel`, `Search\MicroDataDispatcher` | entfernt (Mikrodaten) |
| `Adapter\Options::setUseOpenAi()`, `setOpenAiAssistant()`, `setOpenAiRelevanceScore()`, `setUseRichSnippets()` | entfernt, die Schlüssel fehlen in `getOptions()` |
| Suchantwort `/elastic/search/results`: `globalRichSnippets`; Treffer: `microdata`, `rich_snippet` | entfernt (auch im Hook `psParseSearchHit`) |
| Template-Variablen `openAi` (`j_elasticsearch`), `rich_snippet` (`elasticsearch_result`) | entfernt; eigene Template-Kopien entsprechend anpassen |
| `POST /stat/click` ohne CSRF-Prüfung | verlangt `REQUEST_TOKEN` im Body (XHR ohne/mit ungültigem Token: 403 JSON, klassischer POST: Contao-Standard 400). Die Module stellen das Token als Template-Variable `requestToken` bereit, jede Antwort enthält ein frisches `requestToken`. Eigene Kopien von `j_elasticsearch*` bzw. eigene Such-Scripts müssen das Token mitsenden |

## Dokumentstruktur, Chunks und Übertragungs-Queue

### Was die Migration macht

`IndicesChunksMigration` läuft mit `contao:migrate` bzw. über das Install-Tool:

1. legt in `tl_indices` die neuen Spalten an (`text`, `strong`, `headings`, `url_hash`, `rootId`, Prüfsummen,
   Queue-Felder) sowie die Tabelle `tl_indices_chunks`, mit denselben Definitionen wie die DCA, sodass danach kein Schema-Diff bleibt;
2. füllt `url_hash` und `rootId` (Startpunkt der Seite, bestimmt den Indexnamen);
3. überführt `document` in Blöcken zu je 100 Datensätzen, eine Transaktion pro Block:
   - Seiten: `text` = bisheriger Seitentext, `strong`/`headings` unverändert;
   - PDFs: `text` = Textebene des PDFs, Linktext, Dateiname und Metadaten kommen nach `strong`;
   - ungültiges UTF-8 aus alten PDF-Importen (das Feld war binär) wird repariert, ohne Zeichen zu verlieren;
4. erzeugt die Chunks aktiver Dokumente;
5. entfernt `document` erst, wenn **jeder** Datensatz überführt ist. Lässt sich ein Datensatz nicht lesen, bleibt die
   Spalte erhalten, die Migration meldet die IDs und läuft beim nächsten `contao:migrate` weiter.

Danach setzt `IndicesUniqueUrlMigration` einen Unique-Key auf `url_hash`: ein Datensatz je URL. Gibt es eine URL
mehrfach, bleibt der aktive bzw. zuletzt geänderte Datensatz erhalten. Die übrigen werden zum Löschen vorgemerkt und
von der Queue aus dem Index und der Tabelle entfernt. Auf dem Entwicklungssystem betraf das einen Datensatz.

Die Migration ist wiederholbar und setzt dort fort, wo ein Abbruch sie gestoppt hat. Alle überführten Dokumente
werden für die Übertragung vorgemerkt: Der Suchindex erhält damit `text` (und auf OpenSearch 3 die Chunks), das
alte Feld `document` verschwindet aus den Index-Dokumenten.

Auf dem Entwicklungssystem (2590 Datensätze, 18 MB `document`) dauerte die Migration 1,8 s bei 20 MB Spitzenspeicher.
Ein Abgleich gegen das Backup ergab, dass alle 2590 Datensätze entsprechen und kein einziges Wort verloren ging.

### Deployment

```bash
# 1. Backup von tl_indices (enthält die bisherigen Inhalte in "document")
# 2. Code ausrollen, dann:
php vendor/bin/contao-console contao:migrate
php vendor/bin/contao-console prosearch:engine:detect      # oder Zugangsdaten im Backend speichern
php vendor/bin/contao-console prosearch:index:status
# 3. Übertragung: erfolgt durch den minütlichen Cron. Für die Erstübertragung bietet sich die Kommandozeile an:
php vendor/bin/contao-console prosearch:index:sync
```

Der Contao-Cron muss laufen, am besten als echter Cron (`* * * * * php vendor/bin/contao-console contao:cron`),
sonst wird erst bei Seitenaufrufen übertragen.

**Bisher nicht verarbeitete Löschungen:** Der Cronjob `DeleteIndices` war mit dem Tag `contao.cron` statt
`contao.cronjob` registriert und lief deshalb nie. Datensätze mit `state = delete` blieben liegen, auf dem
Entwicklungssystem 2452 Stück. Die neue Queue verarbeitet sie beim ersten Lauf: Sie werden im Suchindex gelöscht,
fehlen sie dort, gilt das ebenfalls als erledigt, und danach lokal entfernt.

### Geänderte Schnittstellen

| Bisher | Ab 4.0 |
|---|---|
| `new Indices($document, $meta)` / `new PDFIndices(…)` übertragen sofort | **entfernt** (ebenso die Basisklasse `Search\Searcher`). Ersatz: die Services `Indexing\Collector\PageDocumentCollector` bzw. `PdfDocumentCollector`, `->collect($document, $meta)` erfasst und reiht ein und liefert `SaveResult` (Seite) bzw. eine Liste davon (PDFs) mit der Datensatz-ID |
| `IndexingService::indexDocuments($id)` sendet direkt | reiht ein und verarbeitet die Queue sofort für diese ID |
| `IndexingService::deleteIndex($id): void` | gibt `bool` zurück: `true`, wenn Index und Datenbank bereinigt sind; sonst bleibt der Datensatz zum Löschen vorgemerkt |
| `Cron\DeleteIndices` | ersetzt durch `Cron\SyncIndexQueue` (Klasse bleibt, ist kein Cronjob mehr) |
| Spalte `tl_indices.document` | `text`, `strong`, `headings` + `tl_indices_chunks` |
| `MappingFactory::create($index, $options, $context)` | zusätzliche optionale Parameter `$chunks`, `$vectors`; ohne sie unverändert |
| Abhängigkeit `fusonic/opengraph` | entfernt, OpenGraph-Daten liest der ohnehin vorhandene Crawler |

Backend: Das Löschen eines Eintrags unter „Indizes“ läuft über die Queue. Ist der Proxy nicht erreichbar, bleibt der
Eintrag zum Löschen vorgemerkt und wird automatisch entfernt, sobald der Proxy wieder antwortet. „Re-Indexieren“
überträgt das gespeicherte Dokument sofort.

## Frontend: Barrierefreiheit, POST/CSRF, Content Security Policy

Die Suchmodule „ProSearch“ und „ProSearch Type Ahead“ (bisher „Elasticsearch“ und „Elasticsearch Type Ahead“) haben ein neues Frontend: semantisches HTML, ARIA-Combobox,
Live-Region, serverseitig gerenderte Ergebnisse (Template `ps_search_panel`) und ein externes Skript
`prosearch-search.js` ohne Vue. Details siehe README, Abschnitt „Frontend-Modul“.

### Was ohne eigene Templates passiert

Module ohne eigenes Template (`customTpl` leer) und die Bundle-Variante `mod_elasticsearch_header` nutzen das neue
Frontend automatisch. Vue, vue-resource und autoComplete.js werden für sie nicht mehr geladen.

### Eigene Templates

| Eigenes Template | Verhalten |
|---|---|
| enthält `data-prosearch` (Kopie der neuen Bundle-Templates) | neues Frontend |
| ohne `data-prosearch` (Vue-Template aus ProSearch < 4) | Legacy-Modus: Vue + vue-resource + autoComplete.js wie bisher, `$this->script` liefert das alte `j_elasticsearch*` |

Legacy-Templates funktionieren weiter, sind aber **nicht** barrierefrei. Für BFSG-Konformität sollten sie auf Basis der
neuen Templates neu aufgebaut werden. Die Klassennamen sind weitgehend erhalten (`elasticsearch-container`,
`elasticsearch-results-panel`, `categories-results`, `search-results`, `result-hit` …), das Theme-CSS greift also
größtenteils weiter.

Template-Variablen der Module, die nicht mehr gesetzt bzw. geändert sind:

- `keywords`/`keyword` und `inputCategories` kommen aus dem POST-Formular. GET (`?keywords=`, `?q=`, `?categories[]=`)
  wird weiterhin gelesen.
- Neu: `config`, `formId`, `inputId`, `panel`, `panelOpen`, `showResults`, `action` und weitere, siehe die Templates.
- Entfernte geschützte Methoden der Modulklassen: `loadAssets()`, `getJsScript()`, `getKeywords()`,
  `getInputCategories()`. Ersatz ist der Trait `Modules\SearchModuleTrait`.

### Umbenennung Elasticsearch → ProSearch

| Bisher | Neu | Hinweis |
|---|---|---|
| Backend-Menü und Modulgruppe „Elasticsearch“ | „ProSearch“ | nur Beschriftung |
| Frontend-Modultyp `elasticsearch` | `prosearch` | `FrontendModuleTypeMigration` stellt `tl_module.type` und die erlaubten Modultypen von Benutzern/Gruppen (`frontendModules`) um |
| Frontend-Modultyp `elasticsearch_type_ahead` | `prosearch_type_ahead` | wie oben |
| `$GLOBALS['TL_LANG']['FMD']['elasticsearch']`, `['elasticsearch_type_ahead']` | `['prosearch']`, `['prosearch_type_ahead']` | eigene Übersetzungen in `contao/languages` anpassen |
| CSS-Klasse `mod_elasticsearch`, `mod_elasticsearch_type_ahead` | `mod_prosearch`, `mod_prosearch_type_ahead` | die alte Klasse wird vorerst zusätzlich gesetzt (deprecated, entfällt in 5.0) |
| `POST /elastic/search/results`, `/elastic/search/autocompletion` (Routen `elastic-controller.*`) | `POST /prosearch/search/results`, `/prosearch/search/autocompletion` (`prosearch-controller.*`) | alte Pfade und Routennamen funktionieren weiter, deprecated, entfallen in 5.0 |
| `POST /stat/click` (`elastic-stat-controller.click`) | `POST /prosearch/stat/click` (`prosearch-stat-controller.click`) | wie oben |
| `Controller\ElasticsearchController` | `Controller\ProSearchController` | alter Name als Alias (`class_alias`), deprecated, entfällt in 5.0; betrifft z. B. `instanceof`-Prüfungen auf `$context` im Hook `psParseSearchResults` |

Templates (`mod_elasticsearch*`, `j_elasticsearch*`, `elasticsearch_result`) und die Modulklassen (`ElasticsearchModule`,
`ElasticsearchTypeAheadModule`) bleiben unverändert, eigene Templates gelten also weiter.

Nach dem Update: `vendor/bin/contao-console contao:migrate` (bzw. Contao Manager → Datenbank aktualisieren).

### Endpunkte nur noch per POST mit Request-Token

`/prosearch/search/results`, `/prosearch/search/autocompletion` und `/prosearch/stat/click` (sowie die alten Pfade `/elastic/search/*`, `/stat/click`) nehmen **nur noch POST** an
(GET → 404). Außerdem verlangen sie das Contao-Request-Token im Header `X-CSRF-Token` oder im Feld `REQUEST_TOKEN`,
sonst antworten sie mit `403` und einem frischen Token in `requestToken`.

- Eigene Templates, Layout-Skripte oder Inhaltselemente mit dem alten Muster (`this.$http.post('/elastic/search/…?query=…')`,
  `fetch('/elastic/search/…')`) erkennt der Adapter in der Seite und bindet `prosearch-legacy.js` ein. Dieses Skript
  ergänzt das Token, verschiebt `query`/`search_after` aus der URL in den Body und wiederholt eine mit 403 abgelehnte
  Anfrage einmal. Auf Seiten ohne Formular ist die erste Anfrage deshalb ein 403, gefolgt von einem erfolgreichen
  Retry. Ein Token wird bewusst nicht eingebettet, damit die Seiten cachebar bleiben.
- Eigene Integrationen außerhalb von Contao-Seiten (z. B. Apps) müssen das Token mitsenden, siehe README „AJAX-Abfrage“.
- `query` und `search_after` im Query-String werden noch akzeptiert, sollten aber im Body stehen.
- `didYouMean` ist jetzt immer eine JSON-Liste. Bisher konnte daraus ein Objekt werden (`{"1": "…"}`), wenn der erste
  Vorschlag dem Suchbegriff entsprach.
- Neu in `results`: `searchAfter` (Cursor für „Mehr laden“). Mit `view=1` liefert die Antwort zusätzlich das gerenderte
  Ergebnis-HTML.

### Content Security Policy

Das neue Frontend läuft mit einer strikten CSP ohne `'unsafe-inline'` und `'unsafe-eval'`. Im Legacy-Modus ergänzt
der Adapter bei aktiver CSP `'unsafe-eval'` für `script-src`, weil Vue 2 Templates zur Laufzeit kompiliert. Eigene
Inline-Skripte in Legacy-Templates brauchen eine Nonce:
`<script<?= ($n = $this->nonce('script-src')) ? ' nonce="'.$n.'"' : '' ?>>`.

## Verhaltensänderungen

- **Analyzer:** `tl_page.psAnalyzer` (Root-Seite) bestimmt jetzt den Analyzer beim Anlegen des Index (bisher wurde das
  Feld nicht ausgewertet, es galt immer der Analyzer der Zugangsdaten). Das Modulfeld `tl_module.psAnalyzer` ist
  entfernt; die Suche nimmt den Analyzer aus dem Mapping des Index (`Search\IndexSchemaResolver`). Die Spalte
  `tl_module.psAnalyzer` bietet das Contao-Install-Tool zum Löschen an, sie wird nicht mehr gelesen.
  `german`/`english`/`french` trennen jetzt an Satzzeichen (Tokenizer `standard`) und nutzen leichte Stemmer,
  `german` zusätzlich `german_normalization`. Gilt für neu angelegte bzw. neu aufgebaute Indizes. Die Option
  `keyword` steht nicht mehr zur Wahl (sie machte den ganzen Text zu einem Begriff).
- **Kategorie-Filter:** Neue Indizes mappen `types` als `keyword`. Bei bestehenden Indizes (dynamisches Text-Mapping)
  filtert die Suche auf `types.keyword`, damit Kategorien mit Bindestrich („bauen-wohnen“) exakt passen.

- **Kategorien:** Schlüssel werden überall gleich normalisiert (Kleinschreibung, ohne Leerzeichen/Punkte; „-“ und „_“
  bleiben). Gruppiert wird nach Kategorie statt nach Label (vorher wurden Kategorien mit gleichem oder fehlendem Label
  zu einer Gruppe zusammengelegt). Das JSON bleibt nach Label geschlüsselt, bei gleichen Labels mit angehängtem
  Schlüssel. Die Frontend-Suche schreibt nicht mehr in `tl_ps_categories`. `Categories::setCategories()` liefert
  jetzt die Anzahl der Änderungen zurück.

- **Synonyme** erweitern die Suche, statt sie zu ersetzen: gesucht wird der eingegebene Begriff **oder** der Begriff mit
  dem Synonym durch den Zielbegriff ersetzt. Der Abgleich ist exakt (ganze Wörter/Wortgruppen) statt `similar_text()`
  ≥ 75 % auf der ganzen Anfrage. `Helpers\Keyword::setKeywords()` liefert in `query` jetzt den eingegebenen Begriff
  (bisher das Synonym), neu `synonyms` (Alternativen); `synonym` enthält wie bisher die Ersetzung.
  `Synonyms::predict()` und `Keyword::convertSynonyms()` bleiben erhalten. Voraussetzung ist ein Proxy mit
  `profile_params.prosearch_default` ⊇ `synonyms`; ältere Proxys bekommen die ersetzte Anfrage.

- Wartungsjob „Elasticsearch leeren“ heißt jetzt „ProSearch-Index zurücksetzen“ und setzt die Suche komplett zurück:
  Er löscht die Indizes **und** alle lokalen Indexdaten (`tl_indices`, `tl_indices_chunks`, `tl_ps_categories`).
  Bisher löschte er nur die Indizes der veröffentlichten Root-Seiten, und die lokalen Datensätze blieben stehen. Die
  Suchstatistik bleibt erhalten. Fehler des Proxys werden jetzt im Backend angezeigt statt nur geloggt; lokal wird
  dann nichts gelöscht. Neu ist der Befehl `prosearch:index:purge` (Vorschau, `--force`, `--local-only`).

- Suchtreffer enthalten im `_source` nicht mehr die großen Felder `text`, `document`, `autocomplete`
  (wurden vom Frontend nicht verwendet – die Trefferdarstellung liest aus `tl_indices`).
- Fuzzy-Suche (Modul-Option „Fuzzy“): der dritte Suchschritt findet jetzt tatsächlich Tippfehler (vorher
  wirkungslose Fuzziness auf Wildcard-Termen).
- Sonderzeichen in Suchbegriffen (z. B. `(`, `:`) führen nicht mehr zu Fehlern der Suchmaschine.
- Indexieren aus der Kommandozeile/Cron nutzt für die Lizenz die Domain des Dokuments bzw. des ersten Lizenzschlüssels
  (vorher leere Domain → ungültig).
- Erreicht der Adapter beim Indexieren das Rate-Limit des Proxys, wartet er die angekündigte `Retry-After`-Zeit
  (höchstens 15 s) und versucht es einmal erneut.
