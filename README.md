# Contao Search Pro

**Professionelle Suche mit Elasticsearch für Contao 4.9, 4.13 und Contao 5***

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

Mehr Details: https://www.sineos.de/contao/contao-search-pro

_* Contao 5 Version kommt Mitte/Ende November 23_

## Installation

Über Contao-Manager: https://extensions.contao.org/?q=pro&pages=1&p=alnv%2Fprosearch-indexer-contao-adapter-bundle

oder mit composer.json

``
composer require alnv/prosearch-indexer-contao-adapter-bundle
``

## Setup

Nach der Installation siehst du im Backend einen weiteren Navigationspunkt "Elasticsearch".  Als Erstes musst du die Zugangsdaten eingeben, klicke hierzu auf Zugangsdaten und wähle unter Paket die Option API-Key aus und trage anschließend in das API-Key-Feld deinen Lizenzschlüssel ein.
Da die Erweiterung sich noch in der Test-Phase befindet, kannst du diese kostenlos ausprobieren, indem du in das API-Key-Feld "alpha-test" einträgst.

### Seitenstruktur

In der Seitenstruktur im Seitentyp "Startpunkt einer Webseite" gibt es weitere Elasticsearch-Einstellungen. Hier kannst du für deine Webseite den passenden Analyzer auswählen.

### Was sind Analyzer?

In Elasticsearch ist ein Analyzer ein Feature, das für die Textanalyse verantwortlich ist. Das wird verwendet, um Textdaten in einem Dokument in sinnvolle Tokens (Wörter oder Begriffe) zu zerlegen und diese Tokens für die Indexierung und die spätere Suchabfrage zu optimieren. Elasticsearch unterstützt eine Vielzahl von Analyzern, die an die spezifischen Anforderungen Ihrer Anwendung angepasst werden können.

### Kategorien

In der Seitenstruktur im Seitentyp "Reguläre Seite" lassen sich die Kategorien eintragen. Diese können später für die Filterung der Suchergebnisse verwendet werden. 

Es ist auch möglich die Kategorien direkt im Template z.B. im News oder Event Modul zu vergeben.

``` php
<?php
// news_full.html5
$GLOBALS['TL_HEAD']['search:type'] = '<meta name="search:type" content="news"/>';
?>
```

Der search:type wird erst nach einem erneuten Aufbau des Suchindexes übernommen.

### Frontend-Modul

Es gibt zwei Frontend-Module "Elasticsearch" und "Elasticsearch Type Ahead". Lege dein gewünschtes Modul an und binden es in der Seite ein.

### Suchindex aufbauen

Jetzt kannst du den Suchindex aufbauen. Gehe dazu in die Systemwartung und klicke auf "Den Suchindex aktualisieren". Wenn die Seiten einmal indexiert sind, kannst du die Suche im Frontend aufrufen und testen.

### Kategorien Texte einpflegen

Nach der Indexierung stehen dir die Kategorien unter Elasticsearch → Kategorien zur Verfügung. Hier siehst du eine Auflistung aller gefundenen Kategorien. Zudem kannst du je Kategorie ein Label vergeben oder die Kategorie übersetzen.

### Synonyme

Es ist möglich, je Suchbegriff Synonyme anzulegen. Das kannst du unter Elasticsearch → Synonyme erledigen. Erstelle ein Synonym und trage beim "Suchbegriff" das wonach gesucht werden soll ein z.B. "Karriere". Und unter Synonyme andere Bezeichnungen für den Suchbegriff z.B. "Jobs", "Stellenbörse", "Arbeit" etc. Wenn Seitenbesucher einen der Synonyme in die Suche einträgt, dann wird nach "Karriere" gesucht.

### Statistik

Hier hast du einen Überblick über die gesuchten Suchbegriffe.

### Bilder anzeigen

Die Bilder für die Suche werden über die Open Graph Meta Tags gepflegt. Am besten installiert man eine Open Graph Erweiterung für Contao oder man vergibt je Seite ein og:image Tag. Der og:image wird erst nach einem erneuten Aufbau des Suchindexes übernommen.

``` html
<meta property=“og:image“ content="url"/>
```

