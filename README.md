# Contao Search Pro

### Professionelle Suche mit OpenSearch/Elasticsearch für Contao 5

Mit stetig wachsenden Online-Inhalten wird es immer anspruchsvoller, relevanten und bedeutsamen Content schnell zu finden. Genau hier setzt Contao Search Pro an. Basierend auf der hochmodernen Elasticsearch-Technologie und ausgestattet mit einer Fülle an nützlichen Features.

- Definieren von eigenen Tags
- Filtern bereits in den Suchergebnissen
- Speichern von eingegebenen Suchbegriffen
- Statistiken
- Suchvorschläge
- Meinten Sie…?
- Bildvorschau
- Durchsuchen von PDF Dokumenten
- Synonyme

[![IMAGE ALT TEXT HERE](https://img.youtube.com/vi/MFjcACLUUbQ/0.jpg)](https://www.youtube.com/watch?v=MFjcACLUUbQ)

Mehr Details: https://www.sineos.de/contao/contao-search-pro

## Installation

Über Contao-Manager: https://extensions.contao.org/?q=pro&pages=1&p=alnv%2Fprosearch-indexer-contao-adapter-bundle

oder mit composer.json

``
composer require alnv/prosearch-indexer-contao-adapter-bundle
``

## Setup

Nach der Installation erscheint im Backend ein neuer Navigationspunkt namens "Elasticsearch". Als erstes musst du die Zugangsdaten eingeben. Klicke dazu auf "Zugangsdaten" und wähle unter "Paket" die Option "API-Key" aus. Trage anschließend deinen API-Token und den Lizenzschlüssel für deine Domain ein.

### Kostenlos testen

**Du kannst die Erweiterung jederzeit kostenlos testen, indem du dir einen Demo-Zugang erstellst: https://app.sineos.de/. Der Zugang ist 30 Tage gültig.**

### Seitenstruktur

In der Seitenstruktur im Seitentyp "Startpunkt einer Webseite" gibt es weitere Elasticsearch-Einstellungen. Hier kannst du für deine Webseite den passenden Analyzer auswählen.

### Was sind Analyzer?

In Elasticsearch ist ein Analyzer ein Feature, das für die Textanalyse verantwortlich ist. Das wird verwendet, um Textdaten in einem Dokument in sinnvolle Tokens (Wörter oder Begriffe) zu zerlegen und diese Tokens für die Indexierung und die spätere Suchabfrage zu optimieren. Elasticsearch unterstützt eine Vielzahl von Analyzern, die an die spezifischen Anforderungen Ihrer Anwendung angepasst werden können.

| Analyzer | Wirkung |
|---|---|
| `german` / `english` / `french` | trennt an Satzzeichen, entfernt Stoppwörter, reduziert vorsichtig auf den Wortstamm (`german`: zusätzlich ä/ö/ü/ß angeglichen, „Anträge“ findet „Antrag“, „fuhrerschein“ findet „Führerschein“) |
| `standard` | trennt an Satzzeichen, Kleinschreibung |
| `contao` (Vorgabe) | trennt nur an Leerzeichen, Satzzeichen bleiben am Wort hängen |
| `simple`, `whitespace`, `stop` | Analyzer der Suchmaschine |

- Der Analyzer der **Root-Seite** gilt beim Anlegen des Index. Mit „Alle Dokumente in einer Datenbank“ gilt der
  Analyzer der Zugangsdaten.
- Die Suche verwendet automatisch den Analyzer, mit dem der Index tatsächlich angelegt wurde (aus dem Mapping,
  1 h gecacht). Suchmodule haben seit 4.0 keine eigene Einstellung mehr.
- Eine Änderung wirkt erst nach dem Neuaufbau des Index:
  `vendor/bin/contao-console prosearch:index:rebuild --root=<ID> --force`. Bis dahin sucht die Suche weiter mit dem
  bisherigen Analyzer, Suche und Index passen also immer zusammen.

### Kategorien

In der Seitenstruktur im Seitentyp "Reguläre Seite" lassen sich die Kategorien eintragen. Diese können später für die Filterung der Suchergebnisse verwendet werden.

Es ist auch möglich, die Kategorien direkt im Template z.B. im News oder Event Modul zu vergeben.

``` php
<?php
// news_full.html5
$GLOBALS['TL_HEAD']['search:type'] = '<meta name="search:type" content="news"/>';
?>
```

Mehrere Kategorien werden mit Komma getrennt. Der Adapter vereinheitlicht die Schlüssel überall (Seiteneinstellung,
Meta-Tag, Übertragung, Filter): Kleinschreibung, Leerzeichen und Punkte werden entfernt; Buchstaben (auch Umlaute),
Ziffern, „-“ und „_“ bleiben erhalten (z. B. `bauen-wohnen`). Bis Version 3 wurden „-“ und „_“ in der
Seiteneinstellung entfernt.

Der search:type wird erst nach einem erneuten Aufbau des Suchindexes übernommen.

### Frontend-Modul

Es gibt zwei Frontend-Module "Elasticsearch" und "Elasticsearch Type Ahead". Lege dein gewünschtes Modul an und binde es in der Seite ein.

| Modul | Verhalten |
|---|---|
| Elasticsearch (`mod_elasticsearch`, Variante `mod_elasticsearch_header`) | Live-Ergebnisse in einem Panel, gruppiert nach Kategorie, mit Kategorie-Filter und „Mehr laden“. Auf der Weiterleitungsseite selbst ist es nur ein Suchformular. |
| Elasticsearch Type Ahead (`mod_elasticsearch_type_ahead`) | Suchfeld mit Vorschlägen: Suchbegriffe („einfach“) oder Treffer („erweitert“, Auswahl öffnet die Seite). Die Ergebnisliste erscheint auf der Weiterleitungsseite bzw. ohne Weiterleitungsseite direkt unter dem Feld. |

**Barrierefreiheit (BFSG, EN 301 549 / WCAG 2.1 AA):**

- Suchformular als `search`-Landmarke. Das Eingabefeld hat immer ein Label; ist `searchInputLabel` leer, ist es nur für Screenreader sichtbar. Der Hinweis zur Mindestlänge ist per `aria-describedby` verknüpft.
- Vorschläge folgen dem ARIA-1.2-Muster „Combobox mit Listbox“: Pfeiltasten, Enter, Escape. Tab wird nie abgefangen. Die Anzahl der Vorschläge wird angesagt.
- Ergebnisse, „keine Treffer“, Fehler und „N weitere geladen“ werden über eine `role="status"`-Live-Region angesagt.
- Kategorien sind Umschalter (`aria-pressed`). Der aktive Zustand ist nicht nur farblich erkennbar. Gruppen haben echte Überschriften, eine Ebene unter der Modulüberschrift (ohne Überschrift `h2`).
- „Mehr laden“ setzt den Fokus auf den ersten neuen Treffer. Das Panel schließt mit Escape, mit dem Schließen-Button (beschriftet) oder sobald der Fokus das Modul verlässt; der Fokus geht dann ins Suchfeld zurück.
- Sichtbarer Fokus (`:focus-visible`), Rahmen des Suchfelds mit 4,5:1 Kontrast, `prefers-reduced-motion` und `forced-colors` werden berücksichtigt.
- Links, die in einem neuen Fenster öffnen, tragen einen Hinweis für Screenreader und `rel="noopener"`.
- Geprüft mit axe-core 4.11 (WCAG 2.0/2.1/2.2 A+AA und Best Practices): 0 Verstöße im Panel, in der Combobox mit geöffneter Liste und in der Ergebnisliste.

**POST und CSRF:** Das Suchformular sendet per POST mit Contao-Request-Token. Der Suchbegriff steht damit weder in der
URL noch in Server-Logs oder im Referrer. Die Ergebnisseite rendert die Treffer auf dem Server, die Suche funktioniert
also auch ohne JavaScript. Mit JavaScript wird in place gesucht. Damit Neuladen und Zurück nicht nach erneutem Senden
fragen, macht `history.replaceState()` aus dem POST-Eintrag einen GET-Eintrag. Der Suchbegriff liegt dann in
`history.state` und wird von dort wiederhergestellt. Alte Links mit `?keywords=` bzw. `?q=` und `?categories[]=`
funktionieren weiter.

**Content Security Policy:** Das Frontend ist eine externe Datei (`prosearch-search.js`). Es enthält kein Inline-Skript,
keine Inline-Handler und keine Style-Attribute und verwendet weder `eval` noch `new Function`. Die Konfiguration steht im
Attribut `data-prosearch`. Ist CSP in der Root-Seite aktiv, bekommt das Skript eine Nonce. Mit
`default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; object-src 'none'; base-uri 'self'; form-action 'self'`
gab es bei Suche, Filter und Vorschlägen keinen einzigen Verstoß.

**Eigene Templates:** Ein Modul-Template gilt als neu, wenn es das Attribut `data-prosearch` enthält. Die
`data-ps-*`-Attribute sind die Anknüpfungspunkte des Skripts. Die Ergebnisse rendert das Template `ps_search_panel`;
es lässt sich ebenfalls überschreiben. Templates **ohne** `data-prosearch` (Vue-Templates aus ProSearch < 4) laufen im
Legacy-Modus weiter: Vue, vue-resource und autoComplete.js werden geladen, und `$this->script` liefert das alte
`j_elasticsearch*`. Diese Templates sind nicht barrierefrei und mit strikter CSP nicht vereinbar. Vue 2 braucht
`'unsafe-eval'`, das der Adapter bei aktiver CSP automatisch ergänzt, und Inline-Skripte brauchen eine Nonce
(`<?= $this->nonce('script-src') ?>`).

### Suchindex aufbauen

Jetzt kannst du den Suchindex aufbauen. Gehe dazu in die Systemwartung und klicke auf "Den Suchindex aktualisieren". Wenn die Seiten einmal indexiert sind, kannst du die Suche im Frontend aufrufen und testen.

### Kategorien Texte einpflegen

Nach der Indexierung stehen dir die Kategorien unter Elasticsearch → Kategorien zur Verfügung. Hier siehst du eine Auflistung aller gefundenen Kategorien. Zudem kannst du je Kategorie ein Label vergeben oder die Kategorie übersetzen.

- Neue Kategorien werden automatisch angelegt, „Aktiv“ zeigt, ob Dokumente die Kategorie verwenden. Abgeglichen wird
  beim Öffnen der Kategorien und der Moduleinstellungen, nach jeder Übertragung der Queue und täglich. Die
  Frontend-Suche liest nur (bis Version 3 schrieb jede Suche in `tl_ps_categories`).
- Das Label gilt für die Sprache der Root-Seite; `de` passt auch für `de-CH`/`de-AT`, sofern es keine eigene
  Übersetzung gibt. Ohne Übersetzung wird der Schlüssel angezeigt.
- Die Ergebnisse werden je Kategorie gruppiert. Haben zwei Kategorien dasselbe Label, bleiben es zwei Gruppen.

### Synonyme

Unter Elasticsearch → Synonyme legst du fest, bei welchen Suchbegriffen zusätzlich nach einem Zielbegriff gesucht
wird. Beispiel: Zielbegriff „Bürgergeld“, Synonyme „Hartz 4“, „ALG 2“, „Jobcenter“. Wer „hartz 4 antrag“ sucht,
findet Seiten zu „hartz 4 antrag“ **und** zu „Bürgergeld antrag“.

- **Erweitern statt Ersetzen:** Der eingegebene Suchbegriff wird immer gesucht. Die Synonyme kommen hinzu, in jedem
  Suchschritt (Präfix, Teilwort, Tippfehler).
- **Exakter Abgleich:** Es zählen ganze Wörter bzw. Wortgruppen, Groß-/Kleinschreibung und Satzzeichen am Wortrand
  spielen keine Rolle. Längere Wortgruppen haben Vorrang („ALG 2“ vor „ALG“). Tippfehler fängt die Fuzzy-Suche ab.
- Steht ein Begriff in mehreren Einträgen, wird nach allen Zielbegriffen gesucht (höchstens 10 Alternativen). Das
  Backend weist beim Speichern darauf hin. Sehr allgemeine Begriffe wie „Antrag“ oder „Beratung“ besser nicht als
  Synonym eintragen: Sie lösen bei sehr vielen Suchen aus.
- Leere Einträge, doppelte Begriffe und der Zielbegriff selbst werden beim Speichern entfernt.
- Die Suchstatistik speichert den eingegebenen Begriff, nicht das Synonym.

Bis Version 3 wurde die *ganze* Suchanfrage per Ähnlichkeitsvergleich (`similar_text` ≥ 75 %) mit den Synonymen
verglichen und durch den Zielbegriff *ersetzt*. Das führte zu Fehltreffern („antrag“, „erstattung“, „verwaltung“ →
„Ausbildungsförderung“), und die Seiten mit dem eingegebenen Begriff fehlten. Die Erweiterung braucht einen Proxy ab
4.1 (Parameter `synonyms` des Profils `prosearch_default`, erkannt über `GET /api/v1/search/capabilities`, eine
Stunde gecacht). Mit einem älteren Proxy ersetzt der Adapter weiterhin die Anfrage, jetzt aber mit dem exakten
Abgleich.

### Suchstatistik

Jede Suche im Frontend wird als eigene Zeile in `tl_search_stats_log` gespeichert: Zeitpunkt, Suchbegriff,
Trefferzahl, Kontext (Kategorien der Suche), Quelle (Seite, auf der gesucht wurde, ohne Query-String) und Klicks auf
Suchergebnisse samt aufgerufener Seiten. IP-Adressen oder andere personenbezogene Daten werden nicht gespeichert. Ein
Klick wird der jeweils letzten Suche nach dem Begriff zugeordnet.

**Elasticsearch → Suchprotokoll** listet alle Einträge (Suche, Filter „Altdaten“, Sortierung, einzelne Einträge
löschen). Über das Menü „…“ oben rechts:

- **Begriffe exportieren:** CSV mit einer Zeile je Suchbegriff (Suchanfragen, Ø Treffer, Suchen ohne Treffer, Klicks,
  erste und letzte Suche),
- **Protokoll exportieren:** CSV mit einer Zeile je Suche,
- **Protokoll leeren:** löscht die gesamte Statistik einschließlich der Altdaten (mit Rückfrage, nicht rückgängig zu
  machen).

Die CSV-Dateien sind für Excel aufbereitet (UTF-8 mit BOM, Trennzeichen `;`). Zellen, die eine Tabellenkalkulation als
Formel ausführen würde (`=`, `+`, `-`, `@` am Anfang), werden mit `'` entschärft, weil Suchbegriffe Besuchereingaben
sind. Der Export wird gestreamt und braucht auch bei Millionen Zeilen kaum Speicher.

### Dashboard (Suchanalyse)

Unter Elasticsearch → Dashboard findest du die Suchanalyse: Kennzahlen (heute, gestern, Woche, Monat, Zeitraum, gesamt,
einzigartige Begriffe, beliebtester Begriff, Ø pro Tag, Anteil ohne Treffer, Klickrate), den Verlauf im Vergleich zum
Vorzeitraum, die beliebtesten Begriffe (Top 10/20/50), die Verteilung nach Wochentag und Uhrzeit, Begriffe im Trend
(steigend, fallend, neu), häufige Suchen ohne Treffer sowie eine durchsuch- und sortierbare Tabelle aller Begriffe mit
Verlauf je Begriff. Der Filter (Heute, Gestern, 7/30/90 Tage, 12 Monate, dieses Jahr, gesamt, Von/Bis) wirkt auf alle
Werte. Beide CSV-Exporte gibt es dort auch für den gewählten Zeitraum. Während Daten geladen werden, zeigt das Dashboard
einen Ladeindikator.

- Prozentvergleiche erscheinen nur, wenn der Vorzeitraum vollständig nach der ersten erfassten Suche liegt. Davor zeigt
  der Verlauf Lücken statt Nullen.
- Tagesgrenzen richten sich nach der Zeitzone der Installation (Einstellungen → Zeitzone), einschließlich
  Sommer- und Winterzeit.
- **Altdaten:** Beim Update auf 4.0 werden die Zähler der früheren Tabelle `tl_search_stats` übernommen (siehe
  UPGRADE-4.0.md). Diese Einträge tragen nur das Datum der jeweils letzten Suche nach dem Begriff. Das Dashboard weist
  darauf hin, wenn ein Zeitraum Altdaten enthält: Verlauf, Wochentage, Uhrzeiten und „ohne Treffer“ sind dafür nur
  Näherungen, Summen je Begriff, Treffer und Klicks dagegen exakt.

**Berechtigungen.** Dashboard (`searchdashboard`) und Suchprotokoll (`stats`) sind getrennte Backend-Module, für
Redakteur:innen also getrennt freizuschalten. Die Endpunkte unter `/contao/prosearch/search-stats/*` (Daten und Export)
prüfen das Recht für das Dashboard und antworten sonst mit 403. Export und Leeren im Suchprotokoll sind durch das
Request-Token geschützt.

**Aufbewahrung.** Standardmäßig wird nichts automatisch gelöscht. Optional entfernt ein täglicher Cron Einträge, die
älter als die eingestellte Zahl an Tagen sind (`0` = unbegrenzt):

```yaml
# config/config.yaml
alnv_pro_search_indexer_contao_adapter:
    stats:
        log_retention_days: 730
```

### Bilder anzeigen

Die Bilder für die Suche werden über die Open Graph Meta Tags gepflegt. Am besten installiert man eine Open Graph Erweiterung für Contao oder man vergibt je Seite ein og:image Tag. Der og:image wird erst nach einem erneuten Aufbau des Suchindexes übernommen.

``` html
<meta property=“og:image“ content="url"/>
```


## Indexierung ab Version 4: Erfassung, Chunks und Übertragungs-Queue

Die Erfassung der Dokumente und die Übertragung in den Suchindex sind getrennt. Der Crawler ist dadurch nicht mehr
vom Proxy abhängig. Fällt der Proxy aus, wird weiter erfasst und später übertragen.

```
Contao-Crawler / Seitenaufruf
  └─ ProSearchIndexer ──► PageDocumentCollector ─┐
                      └─► PdfDocumentCollector ──┤  (Text extrahieren, normalisieren)
                                                 ▼
             IndicesRepository (eine Transaktion pro Dokument)
               ├─ tl_indices         Metadaten + vollständiger Text, revision+1, sync_status=pending
               └─ tl_indices_chunks  max. 600 Tokens je Chunk, 60 Tokens Überlappung
                                                 ▼
Cronjob (minütlich) / prosearch:index:sync ──► IndexSynchronizer ──► Proxy (Bulk-API) ──► OpenSearch/Elasticsearch
```

### Datenstruktur

| Tabelle | Inhalt |
|---|---|
| `tl_indices` | Metadaten (`title`, `description` = Teaser, `images` = Bild, `url`, `types`, `language`, `pageId`, `rootId`, Zugriffsschutz), `text` (vollständiger Text), `strong` und `headings` (h1–h6) sowie Übertragungsstatus (`revision`, `synced_revision`, `sync_status`, `sync_attempts`, `sync_error`, `sync_next_at`, `synced_index`). Das Feld `document` gibt es nicht mehr. |
| `tl_indices_chunks` | `pid` (→ `tl_indices.id`), `sorting` (Reihenfolge), `text`, `token_count`, `start_offset`/`end_offset` (Position im Text), `hash` (z. B. um Embeddings unveränderter Chunks wiederzuverwenden). |

**Chunks:** Die Größe wird in Tokens gezählt, nicht in Wörtern oder Zeichen. Der mitgelieferte `UnicodeTokenizer`
ist sprachunabhängig. Er zerlegt Buchstaben-/Ziffernfolgen in Stücke von höchstens 5 Zeichen und zählt jedes
Satzzeichen als eigenes Token. Das liegt leicht über gängigen Embedding-Tokenizern, ein Chunk mit 600 Tokens
überschreitet also kein 600-Token-Modell. Für exakte Zählung lässt sich der Alias
`Alnv\ProSearchIndexerContaoAdapterBundle\Chunking\TokenizerInterface` auf eine eigene Implementierung setzen.

### Erfassung

- **Unveränderte Dokumente** verursachen keinen Schreibzugriff: Metadaten und Text werden per Prüfsumme verglichen.
  Ändern sich nur die Metadaten, bleiben die Chunks erhalten.
- **PDFs** werden nur neu gelesen, wenn sich die Datei geändert hat (DBAFS-Hash, Größe, Änderungszeit). Ein PDF,
  das auf vielen Seiten verlinkt ist, wird pro Crawl einmal verarbeitet. Gemessen auf `/de/aktuelles-veranstaltungen/bekanntmachungen`
  mit 119 PDFs: bisher wurden bei jedem Aufruf alle PDFs geparst (≈ 24 s), jetzt 0,15 s.
- **Fehlerisolation:** Ein defektes, verschlüsseltes, zu großes oder leeres PDF wird protokolliert (System-Log) und
  übersprungen. Die Seite und die übrigen PDFs werden trotzdem erfasst. Vor dem Parsen prüft der Adapter, ob der
  Speicher reicht, denn ein `memory_limit`-Fehler ließe sich nicht abfangen. Bilddaten werden nicht im Speicher gehalten.
  PDFs ohne Textebene (Scans) werden mit Titel und Metadaten indexiert.
- **Textqualität:** HTML-Entities werden dekodiert, Formularoptionen, SVG, iframes und Kommentare entfernt.
  Blockelemente werden zu Absätzen, Inline-Auszeichnungen trennen keine Wörter mehr, und Silbentrennungen in
  PDF-Zeilenumbrüchen werden zusammengeführt. `indexer::stop/continue` wird wie bisher beachtet, auch verschachtelt.
- Texte über `indexing.max_text_bytes` (Standard 1 MB) werden gekürzt, weil OpenSearch höchstens 1 000 000 Zeichen
  pro Feld hervorhebt.

### Übertragungs-Queue

Die Queue ist persistent und liegt direkt in `tl_indices`. Symfony Messenger wäre hier keine Erleichterung gewesen:
Contao betreibt den Messenger zwar, aber für den Abgleich lokaler Revisionen bräuchte er trotzdem einen eigenen
Status in der Datenbank, und ohne Worker-Prozess würde er nur über den Web-Worker laufen. Die DB-Queue funktioniert
dagegen mit dem normalen Contao-Cron und braucht keine zusätzliche Infrastruktur.

| Situation | Verhalten |
|---|---|
| Neues/geändertes Dokument | `revision+1`, `sync_status=pending`; der nächste Lauf überträgt das komplette Dokument (Bulk-Aktion `index`, ersetzt die alte Fassung vollständig → idempotent) |
| Gelöschtes Dokument | `state=delete`, Chunks sofort entfernt; der Lauf löscht das Dokument im Index und danach die Zeile |
| Änderung/Löschung während der Übertragung | die Bestätigung gilt nur für die gesendete Revision → das Dokument bleibt `pending` und geht im nächsten Lauf neu raus |
| Seite in anderen Seitenbaum verschoben (anderer Index) | Dokument wird aus dem alten Index (`synced_index`) gelöscht und im neuen angelegt |
| Proxy nicht erreichbar / Rate-Limit / Lizenzfehler | Lauf bricht ab, Dokumente werden ohne Zählung eines Versuchs um `queue.retry_delay` verschoben |
| Dokument vom Index abgelehnt | Bulk-Anfrage wird halbiert, bis das fehlerhafte Dokument gefunden ist; Wiederholung mit exponentiellem Backoff, nach `queue.max_attempts` Status `failed` |
| Mehrere Worker gleichzeitig (Web-Cron, CLI-Cron, Befehl) | Datensätze werden per Token atomar reserviert (`sync_claim`); abgestürzte Worker geben sie nach `queue.claim_ttl` frei |

Befehle:

```bash
php vendor/bin/contao-console prosearch:index:status            # Queue, erkannter Suchserver, Fehler
php vendor/bin/contao-console prosearch:index:sync              # Queue jetzt abarbeiten
php vendor/bin/contao-console prosearch:index:sync --retry-failed --now   # fehlgeschlagene/verschobene erneut
php vendor/bin/contao-console prosearch:index:sync --id=123     # einzelnes Dokument
php vendor/bin/contao-console prosearch:engine:detect           # Suchserver neu erkennen
php vendor/bin/contao-console prosearch:index:rebuild --requeue-only   # alle Dokumente neu übertragen
php vendor/bin/contao-console prosearch:index:rebuild --force   # Index löschen, neu anlegen, neu befüllen
php vendor/bin/contao-console prosearch:index:purge             # Vorschau: was ein kompletter Reset löschen würde
php vendor/bin/contao-console prosearch:index:purge --force     # kompletter Reset (siehe unten)
```

**Kompletter Reset** (Backend: System → Systemwartung → Daten bereinigen → „ProSearch-Index zurücksetzen“, oder
`prosearch:index:purge --force`): löscht die Suchindizes in OpenSearch/Elasticsearch, und zwar für alle Root-Seiten
und für alle Root-IDs, zu denen Dokumente gespeichert sind. Außerdem leert er `tl_indices`, `tl_indices_chunks` und
`tl_ps_categories`; die Übersetzungen der Kategorien gehen dabei verloren. **Erhalten bleiben** die Suchstatistik
(`tl_search_stats_log`), Synonyme, Zugangsdaten und die Moduleinstellungen.

- Zuerst werden die Indizes gelöscht. Schlägt das bei einem Index mit lokalen Dokumenten fehl (Proxy nicht erreichbar,
  Lizenzfehler), wird **lokal nichts gelöscht**: Die Meldung nennt den Grund, und der Reset kann einfach wiederholt
  werden. Ein Fehler bei einem Index ohne lokale Dokumente (z. B. alte, nicht lizenzierte Domain) ist nur eine Warnung;
  „Index existiert nicht“ (404) zählt als erledigt.
- Die lokalen Tabellen werden in einer Transaktion geleert. Danach werden die Indizes ein zweites Mal gelöscht, falls
  ein gleichzeitig laufender Queue-Lauf einen Index wieder angelegt hat.
- `--local-only` setzt nur die lokalen Daten zurück, z. B. wenn der alte Cluster nicht mehr existiert.
- Danach ist die Suche leer, bis die Website neu gecrawlt ist (Backend „Suchindex aufbauen“ oder `contao:crawl`).

Cronjobs: `SyncIndexQueue` (minütlich, im Web-Cron höchstens `queue.cron_time_limit` Sekunden) und
`IndexMaintenance` (täglich: verwaiste Chunks entfernen, Suchserver-Erkennung auffrischen, fehlgeschlagene Dokumente melden).

### Suchserver-Erkennung und Chunks

Der Proxy fragt die API des Clusters ab (`GET /api/v1/engine`) und meldet Produkt, Version und Funktionen. Der
Adapter speichert das Ergebnis in `tl_search_credentials` (`engineType`, `engineVersion`, `engineChunks`,
`engineVectors`, `engineCheckedAt`, `engineError`). Erkannt wird beim Speichern der Zugangsdaten, wenn sich die
Proxy-URL ändert, und danach einmal täglich. Schlägt die Erkennung fehl, gilt sie als „keine Chunks“.

| Suchserver | `text` | `chunks` (nested) | Hinweis |
|---|---|---|---|
| OpenSearch ≥ 3.0 | ✔ | ✔ | mit k-NN-Plugin zusätzlich `index.knn: true` für spätere Embeddings |
| OpenSearch 1.x/2.x | ✔ | – | |
| Elasticsearch 7/8/9 | ✔ | – | |
| nicht erreichbar / unbekannt | ✔ | – | Fehler in `engineError` und im Log |

Das Chunk-Mapping ist `chunks: {type: nested, properties: {text, position, token_count}}`. Ein Feld
`chunks.embedding` (`knn_vector`) kann später per Mapping-Update ergänzt werden, sobald ein Modell feststeht.
Bestehende Indizes bekommen das Chunk-Mapping automatisch beim ersten Lauf, denn neue Felder lassen sich hinzufügen.
Wurde `chunks` früher dynamisch als `object` angelegt oder soll k-NN nachträglich aktiviert werden, ist ein
`prosearch:index:rebuild --force` nötig. Der Proxy schützt zusätzlich: An Elasticsearch und an Indizes ohne
Nested-Mapping gibt er keine Chunks weiter, und Suchtreffer enthalten nie `chunks`.

### Konfiguration

```yaml
# config/config.yaml (alle Werte optional)
alnv_pro_search_indexer_contao_adapter:
    indexing:
        chunk_size: 600            # max. Tokens je Chunk
        chunk_overlap: 60          # Überlappung in Tokens
        tokenizer_piece_length: 5
        max_text_bytes: 1000000
        pdf_max_file_size: 5242880 # Bytes
        pdf_max_pages: 0           # 0 = alle Seiten
        pdf_decode_memory_limit: 67108864
    queue:
        cron_enabled: '%env(bool:PROSEARCH_QUEUE_CRON)%'  # Standard true
        batch_size: 50
        max_request_bytes: 8388608 # Proxy-Standardlimit: 10 MB
        max_attempts: 5
        retry_delay: 60            # Sekunden, verdoppelt sich je Versuch
        max_retry_delay: 3600
        claim_ttl: 300
        cron_time_limit: 20
        engine_ttl: 86400
```

**Staging-Kopien:** Eine Kopie der Produktionsdatenbank enthält dieselbe Lizenz-Signatur und schreibt damit in
denselben Index wie die Produktion. Dort `PROSEARCH_QUEUE_CRON=false` setzen. Erfasst wird weiterhin,
übertragen nur noch per Befehl.

## Architektur ab Version 4: ProSearch-Proxy

Das Adapter-Bundle verbindet sich **nicht mehr direkt** mit OpenSearch oder Elasticsearch. Alle Index-, Dokument-
und Suchoperationen laufen über die REST-API des ProSearch-Proxys
(`alnv/prosearch-proxy-bundle`),
der den Cluster anbindet, die Suchanfragen baut, die Lizenz prüft und Anfragen begrenzt.

| Aufgabe | Adapter (dieses Bundle) | Proxy |
|---|---|---|
| Contao-Integration, Backend-Module, Frontend-Module, Templates | ✔ | – |
| Contao-Seiten/PDFs crawlen und als Dokument aufbereiten (`tl_indices`) | ✔ | – |
| Index-Mapping für Contao-Dokumente definieren (Hook `psCreateMapping`) | ✔ | legt den Index an |
| Dokumente anlegen/aktualisieren/löschen, Suche, Auto-Completion | über API | ✔ |
| Query-Builder, Suchstrategien, Profile | – | ✔ |
| Proxy-URL und Lizenz speichern (`tl_search_credentials`) | ✔ | – |
| Lizenz prüfen | – | ✔ |
| Rate-Limiting | – | ✔ |

### Verbindung zum Proxy einrichten

Backend → Elasticsearch → **Zugangsdaten** (Paket „API-Key“):

| Feld | Bedeutung |
|---|---|
| ProSearch-Proxy-URL | Basis-URL des Proxys, z. B. `https://proxy.example.org` oder lokal `http://127.0.0.1:8000`. Ein angehängtes `/api/v1` wird entfernt. Leer = Standard aus `PROSEARCH_PROXY_URL` |
| Proxy-API-Key | Optional, falls der Proxy API-Keys vergibt (Header `X-Api-Key`). Leer = `PROSEARCH_PROXY_API_KEY` |
| API-Token, API-Schlüssel (pro Domain) | Lizenz wie bisher |

Die Werte aus dem Backend haben Vorrang. Ist das Feld leer, gilt die Konfiguration aus der `.env.local`
(bzw. Server-Umgebung). Bei einer ungültigen gespeicherten URL oder ohne Datenbank (Installation) nimmt der Adapter
ebenfalls die Konfiguration und schreibt einen Logeintrag.

| Variable | Standard | Bedeutung |
|---|---|---|
| `PROSEARCH_PROXY_URL` | `https://elasticsearch.sineos.de` | Standard-Proxy, wenn im Backend keine URL eingetragen ist |
| `PROSEARCH_PROXY_API_KEY` | leer | Standard-API-Key (Header `X-Api-Key`) |
| `PROSEARCH_PROXY_VERIFY_TLS` | `true` | TLS-Prüfung – nur in lokalen Entwicklungsumgebungen abschalten. Bewusst nicht im Backend einstellbar |

Eine `http://`-URL zu einem anderen Host als `localhost`/`127.0.0.1` wird gespeichert, das Backend weist aber darauf
hin, dass Lizenz und API-Token dann unverschlüsselt übertragen werden.

Optional in `config/config.yaml`:

```yaml
alnv_pro_search_indexer_contao_adapter:
    proxy:
        timeout: 30
        search_profile: prosearch_default   # Suchprofil des Proxys für die Frontend-Suche
```

Beim Speichern der Zugangsdaten lässt das Backend die Lizenzschlüssel von dem im Formular eingetragenen Proxy
validieren (`/api/v1/license`), prüft danach die Verbindung (`/api/v1/health`) und zeigt das Ergebnis als Meldung
(z. B. „Verbindung zum ProSearch-Proxy … hergestellt (opensearch 3.8.0)“). Die Lizenz wird als
`X-ProSearch-License`-Header übertragen, nie in der URL.

### Selbst gehosteter Proxy

Wer einen eigenen Cluster betreibt (früher Pakettyp „elasticsearch“/„opensearch“), installiert den allgemeinen
ProSearch-Proxy `alnv/prosearch-proxy-bundle`, setzt dort `PROSEARCH_BACKEND_URL`, `PROSEARCH_BACKEND_USERNAME`,
`PROSEARCH_BACKEND_PASSWORD` (bzw. `PROSEARCH_BACKEND_API_KEY`) und trägt dessen URL in den Zugangsdaten als
„ProSearch-Proxy-URL“ ein (oder als `PROSEARCH_PROXY_URL`). Ohne ProSearch-Lizenz: im Proxy `prosearch_proxy.license.required: false`. Die allgemeine Installation
beschreibt das README des Proxys, Details zur Umstellung stehen in [UPGRADE-4.0.md](UPGRADE-4.0.md).

Die Indizes dieses Adapters heißen `contao_search_<Signatur>_<Root-ID|single>`. Der Proxy erlaubt sie mit seinem
Standard `api.index_patterns: ['*_search_*']`. Wer die Liste im Proxy einschränkt, muss `contao_search_*` eintragen.

#### Proxy in derselben Contao-Installation betreiben

Der Proxy hat kein Contao-Manager-Plugin, die Contao-Installation registriert ihn selbst.

1. Paket installieren: `composer require alnv/prosearch-proxy-bundle`
2. Plugin der Anwendung anlegen, `src/ContaoManager/Plugin.php`. Wenn die Datei schon existiert, nur die
   `BundleConfig`-Zeile in deren `getBundles()` ergänzen:

   ```php
   <?php

   declare(strict_types=1);

   namespace App\ContaoManager;

   use Alnv\ProSearchProxyBundle\AlnvProSearchProxyBundle;
   use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
   use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
   use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
   use Symfony\Bundle\FrameworkBundle\FrameworkBundle;

   class Plugin implements BundlePluginInterface
   {
       public function getBundles(ParserInterface $parser): array
       {
           return [
               BundleConfig::create(AlnvProSearchProxyBundle::class)
                   ->setLoadAfter([FrameworkBundle::class]),
           ];
       }
   }
   ```

   Die Contao Managed Edition lädt `App\` per Standard aus `src/`. Das Plugin wird erst erkannt, wenn Composer die
   Plugin-Liste neu schreibt: `composer install` ausführen. Kontrolle:
   `grep "'app'" vendor/contao/manager-plugin/.generated/plugins.php`.
3. Routen in `config/routes.yaml` importieren (Contao liest die Datei automatisch):

   ```yaml
   prosearch_proxy:
       resource: '@AlnvProSearchProxyBundle/config/routes.php'
   ```

4. `PROSEARCH_BACKEND_*` in `.env.local` setzen, optional Proxy-Optionen in `config/config.yaml`
   (Schlüssel `prosearch_proxy`).
5. In den Zugangsdaten die eigene Domain als „ProSearch-Proxy-URL“ eintragen, dann
   `vendor/bin/contao-console cache:clear` und `curl https://<domain>/api/v1/health` prüfen
   (erwartet: `"status":"ok"`).

### Hooks

| Hook | Status | Signatur |
|---|---|---|
| `psCreateMapping` | unverändert | `(array &$params, array $options, object $context)` – Mapping vor dem Anlegen ändern |
| `psParseSearchResults` | unverändert, dritter Parameter erweitert | `(array $results, array $options, object $context): array` – `$context` ist der `ElasticsearchController` (XHR) oder das Modul (serverseitiges Rendern nach POST) |
| `psProxySearchRequest` | **neu** | `(array &$request, array $keywords, array $options, string $mode)` – Proxy-Request (Profil, Filter, Strategien) ändern |
| `psSearchQuery`, `psAutoCompilation` | **entfallen** (Deprecation-Hinweis) | Erhielten rohe Query-DSL; die Queries baut jetzt der Proxy. Ersatz: `psProxySearchRequest` oder ein `SearchBodyEvent`-Listener im Proxy |

### PHP-API

Neue Services (öffentlich, per `System::getContainer()->get()` erreichbar): `Proxy\ProxyClient`,
`Proxy\LicenseProvider`, `Search\IndexingService`, `Search\SearchService`, `Search\MappingFactory`,
`Search\DocumentFactory`, `Search\IndexNameResolver`. Die bisherigen Einstiegspunkte
(`(new Adapter())->getInstance($options)->indexDocuments($id)`, `->search()`, `->deleteIndex()`, `->getAnalyzer()` …)
funktionieren weiter und delegieren an diese Services; `Adapter\Elasticsearch`, `Adapter\Opensearch`,
`Adapter\Proxy` und `Helpers\Authorization` sind als deprecated markiert.

### Tests

```bash
vendor/bin/phpunit   # im Bundle oder als Path-Repository im Projekt (nutzt ../../vendor/autoload.php)
```

Abgedeckt: Proxy-Client (Header, Fehler-Mapping, nicht erreichbarer Proxy, 429), Lizenzkennung (Format-Kompatibilität
zu 3.x, Domain-Auswahl, CLI), Abbildung der Adapter-Optionen auf den Proxy-Request, Rückgabeformat für die
Frontend-Templates, Mapping, Rate-Limit-Retry beim Indexieren, Zeiträume des Such-Dashboards (Presets,
Vorzeitraum, Sommer-/Winterzeit, Validierung), Auffächern der Altdaten, CSRF-Prüfung der Such-Endpunkte (Header/Body,
leeres Token), Ergebnis-Rendering (Kategorien, Reihenfolge, Escaping, „Mehr laden“) und das Einbinden der
Kompatibilitätsschicht für alte Templates.

Die Integrationstests (Dashboard-SQL, Logger, CSV-Export, Migration von `tl_search_stats` gegen MySQL/MariaDB) laufen
nur mit einer Server-URL. Sie legen je Test eine Wegwerf-Datenbank `ps_stats_test_<zufall>` an und löschen sie danach
wieder:

```bash
PROSEARCH_STATS_TEST_DATABASE_URL='mysql://user:pass@127.0.0.1:3306/x' vendor/bin/phpunit --testsuite integration
```

## Entwickler:innen

### AJAX-Abfrage

Die Endpunkte nehmen nur POST-Anfragen mit dem Contao-Request-Token an. Das Token wird im Header `X-CSRF-Token` oder
im Feld `REQUEST_TOKEN` übertragen. Jede Antwort enthält in `requestToken` ein frisches Token. Antwortet der Server mit
`403`, etwa weil die Seite aus dem Cache kam, wird die Anfrage einmal mit diesem Token wiederholt. Den Suchbegriff
bitte im Body senden, nicht in der URL.

| Endpunkt | Parameter (Body) | Antwort |
|---|---|---|
| `POST /elastic/search/results` | `query`, `module` (ID des Frontend-Moduls), `root` (ID der Root-Seite), optional `categories[]`, `group` (`1` = nach Kategorie gruppiert), `search_after` (aus `results.searchAfter`), `view` (`1` = gerendertes Ergebnis-HTML in `view.html`) | `{keywords, results: {hits, total, didYouMean, autocomplete, searchAfter}, view?, requestToken}` |
| `POST /elastic/search/autocompletion` | `query`, `module`, `root` | `{keywords, results: {hits: [{term}], didYouMean}, requestToken}` |
| `POST /stat/click` | `url`, `query`, optional `categories[]` | `{requestToken}` |

```js
<script>
(function () {
    // Token: aus einem Formular der Seite (Contao hält es aktuell) oder aus der letzten Antwort
    let token = (document.querySelector('input[name="REQUEST_TOKEN"]') || {}).value || '';

    async function search(query, retried) {
        const response = await fetch('/elastic/search/results', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'X-CSRF-Token': token, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'},
            body: new URLSearchParams({query: query, module: '28', root: '33'})
        });
        const json = await response.json();
        token = json.requestToken || token;

        if (403 === response.status && !retried) {
            return search(query, true);
        }

        return json; // json.results.hits, json.results.total …
    }

    search('Meine Anfrage').then(function (json) {
        console.log(json);
    });
})();
</script>
```

Auf Seiten ohne eigenes Formular ist das erste Token leer. Die erste Anfrage bekommt dann `403` mit frischem Token, die
Wiederholung ist erfolgreich. Bestehende eigene Skripte aus ProSearch < 4, die mit `$http.post`/`fetch` noch per
Query-String und ohne Token anfragen, funktionieren weiter: Der Adapter erkennt sie in der Seite und bindet
`prosearch-legacy.js` ein. Dieses Skript ergänzt das Token und verlegt `query` in den Body.
