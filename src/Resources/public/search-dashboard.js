(function () {
    'use strict';

    if (window.PsSearchDashboard) {
        window.PsSearchDashboard.mountAll();
        return;
    }

    var I18N = {
        de: {
            title: 'Suchanalyse',
            subtitle: 'Suchverhalten der Besucher auf Basis der ProSearch-Statistik',
            presets: {
                today: 'Heute',
                yesterday: 'Gestern',
                '7d': '7 Tage',
                '30d': '30 Tage',
                '90d': '90 Tage',
                '12m': '12 Monate',
                year: 'Dieses Jahr',
                all: 'Gesamt',
                custom: 'Zeitraum'
            },
            filterLabel: 'Zeitraum',
            from: 'Von',
            to: 'Bis',
            apply: 'Anwenden',
            calendarHeading: 'Kalender',
            calendarHint: 'unabhängig vom gewählten Zeitraum',
            periodHeading: 'Gewählter Zeitraum',
            today: 'Suchanfragen heute',
            yesterday: 'Suchanfragen gestern',
            week: 'Diese Woche',
            month: 'Dieser Monat',
            searches: 'Suchanfragen',
            keywords: 'Einzigartige Suchbegriffe',
            top: 'Beliebtester Suchbegriff',
            avgPerDay: 'Ø Suchanfragen pro Tag',
            zeroRate: 'Suchen ohne Treffer',
            clickRate: 'Klickrate',
            allTime: 'Suchanfragen gesamt',
            allTimeSub: 'seit Beginn der Statistik · {k} Begriffe',
            clickSub: '{n} Klicks auf Suchergebnisse',
            exportTerms: 'Begriffe exportieren (CSV)',
            exportLog: 'Protokoll exportieren (CSV)',
            exportHint: 'Export für den gewählten Zeitraum',
            manageLog: 'Suchprotokoll verwalten',
            noticeLegacy: '{n} der Suchanfragen in diesem Zeitraum stammen aus der früheren Statistik. Sie tragen nur das Datum der jeweils letzten Suche nach dem Begriff, Treffer sind gleichmäßig verteilt. Verlauf, Wochentage, Uhrzeiten und „ohne Treffer“ sind dafür nur Näherungen.',
            vsToday: 'ggü. gestern bis zur gleichen Uhrzeit',
            vsYesterday: 'ggü. vorgestern',
            vsWeek: 'ggü. Vorwoche (gleicher Stand)',
            vsMonth: 'ggü. Vormonat (gleicher Stand)',
            vsPeriod: 'ggü. Vorzeitraum',
            noCompare: 'kein Vergleich möglich',
            points: 'Pp.',
            share: '{p} % aller Suchanfragen',
            days: 'über {d} Tage',
            day: 'an 1 Tag',
            timeline: 'Suchanfragen im Zeitverlauf',
            perDay: 'pro Tag',
            perWeek: 'pro Woche',
            current: 'Zeitraum',
            previous: 'Vorzeitraum',
            peak: 'Spitze: {d} mit {n} Suchanfragen',
            trendUp: 'Trend: +{p} % (2. Hälfte ggü. 1. Hälfte)',
            trendDown: 'Trend: {p} % (2. Hälfte ggü. 1. Hälfte)',
            topTerms: 'Beliebteste Suchbegriffe',
            weekdays: 'Suchanfragen nach Wochentag',
            hours: 'Suchaktivität nach Tageszeit',
            busiestDay: 'Aktivster Tag: {d} ({p} %)',
            busiestHour: 'Aktivste Stunde: {h} Uhr ({p} %)',
            trends: 'Suchbegriffe im Trend',
            rising: 'Steigend',
            falling: 'Fallend',
            emerging: 'Neu',
            trendNoPrevious: 'Für „Gesamt“ gibt es keinen Vergleichszeitraum.',
            trendLogShort: 'Noch kein Vergleich möglich: Der Vorzeitraum liegt vor Beginn des Suchprotokolls.',
            trendEmpty: 'Keine Suchbegriffe mit mindestens {n} Suchanfragen.',
            newBadge: 'neu',
            zeroTerms: 'Häufige Suchen ohne Treffer',
            zeroHint: 'Kandidaten für Synonyme oder neue Inhalte',
            table: 'Alle Suchanfragen',
            tableSearch: 'Suchbegriff filtern',
            perPage: 'Einträge pro Seite',
            colKeyword: 'Suchbegriff',
            colSearches: 'Suchanfragen',
            colAvgHits: 'Ø Treffer',
            colZero: 'Ohne Treffer',
            colClicks: 'Klicks',
            colFirst: 'Erste Suche',
            colLast: 'Letzte Suche',
            firstSeen: 'erstmals gesucht: {d}',
            pageInfo: 'Seite {p} von {n} · {t} Suchbegriffe',
            prev: 'Zurück',
            next: 'Weiter',
            noRows: 'Keine Suchbegriffe gefunden.',
            details: 'Verlauf',
            detailOf: 'Suchbegriff „{k}“',
            close: 'Schließen',
            inPeriod: 'Suchanfragen im Zeitraum',
            lastSearch: 'Letzte Suche',
            firstSearch: 'Erste Suche',
            clicks: 'Klicks gesamt',
            termNoLog: 'Im gewählten Zeitraum gibt es keine Suchanfragen zu diesem Begriff.',
            noticeNoLog: 'Es wurden noch keine Suchanfragen erfasst. Die Auswertungen füllen sich ab der nächsten Suche auf der Website.',
            noticeLogStart: 'Die Statistik beginnt am {d}. Davor bleibt der Verlauf leer.',
            noticeRetention: 'Einträge werden nach {n} Tagen automatisch gelöscht.',
            loading: 'Daten werden geladen …',
            error: 'Die Daten konnten nicht geladen werden.',
            forbidden: 'Keine Berechtigung für das Such-Dashboard.',
            invalidRange: 'Bitte einen gültigen Zeitraum wählen (Von ≤ Bis).',
            weekdayNames: ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'],
            weekdayShort: ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'],
            weekShort: 'KW',
            date: 'Datum',
            logSince: 'Statistik erst seit {d}',
            searchesUnit: 'Suchanfragen',
            hitsUnit: 'Treffer',
            more: 'Top {n}',
            chartSummary: 'Diagramm: {t}. Werte siehe Tabelle.',
            dataTable: 'Werte als Tabelle',
            hourLabel: '{h} Uhr',
            openDetail: 'Verlauf von „{k}“ anzeigen'
        },
        en: {
            title: 'Search analytics',
            subtitle: 'Visitor search behaviour based on the ProSearch statistics',
            presets: {
                today: 'Today',
                yesterday: 'Yesterday',
                '7d': '7 days',
                '30d': '30 days',
                '90d': '90 days',
                '12m': '12 months',
                year: 'This year',
                all: 'All time',
                custom: 'Custom'
            },
            filterLabel: 'Period',
            from: 'From',
            to: 'To',
            apply: 'Apply',
            calendarHeading: 'Calendar',
            calendarHint: 'independent of the selected period',
            periodHeading: 'Selected period',
            today: 'Searches today',
            yesterday: 'Searches yesterday',
            week: 'This week',
            month: 'This month',
            searches: 'Searches',
            keywords: 'Unique search terms',
            top: 'Most popular term',
            avgPerDay: 'Ø searches per day',
            zeroRate: 'Searches without results',
            clickRate: 'Click rate',
            allTime: 'Total searches',
            allTimeSub: 'since the statistics started · {k} terms',
            clickSub: '{n} clicks on search results',
            exportTerms: 'Export terms (CSV)',
            exportLog: 'Export log (CSV)',
            exportHint: 'Export of the selected period',
            manageLog: 'Manage search log',
            noticeLegacy: '{n} of the searches in this period come from the former statistics. They only carry the date of the last search for the term, results are distributed evenly. History, weekdays, hours and “no results” are approximations for them.',
            vsToday: 'vs. yesterday until the same time',
            vsYesterday: 'vs. the day before',
            vsWeek: 'vs. last week (same point)',
            vsMonth: 'vs. last month (same point)',
            vsPeriod: 'vs. previous period',
            noCompare: 'no comparison available',
            points: 'pp',
            share: '{p} % of all searches',
            days: 'over {d} days',
            day: 'on 1 day',
            timeline: 'Searches over time',
            perDay: 'per day',
            perWeek: 'per week',
            current: 'Period',
            previous: 'Previous period',
            peak: 'Peak: {d} with {n} searches',
            trendUp: 'Trend: +{p} % (2nd half vs. 1st half)',
            trendDown: 'Trend: {p} % (2nd half vs. 1st half)',
            topTerms: 'Most popular search terms',
            weekdays: 'Searches by weekday',
            hours: 'Search activity by hour',
            busiestDay: 'Busiest day: {d} ({p} %)',
            busiestHour: 'Busiest hour: {h}:00 ({p} %)',
            trends: 'Trending search terms',
            rising: 'Rising',
            falling: 'Falling',
            emerging: 'New',
            trendNoPrevious: '“All time” has no previous period.',
            trendLogShort: 'No comparison yet: the previous period lies before the search log started.',
            trendEmpty: 'No search terms with at least {n} searches.',
            newBadge: 'new',
            zeroTerms: 'Frequent searches without results',
            zeroHint: 'Candidates for synonyms or new content',
            table: 'All searches',
            tableSearch: 'Filter search terms',
            perPage: 'Entries per page',
            colKeyword: 'Search term',
            colSearches: 'Searches',
            colAvgHits: 'Ø results',
            colZero: 'No results',
            colClicks: 'Clicks',
            colFirst: 'First search',
            colLast: 'Last search',
            firstSeen: 'first searched: {d}',
            pageInfo: 'Page {p} of {n} · {t} search terms',
            prev: 'Previous',
            next: 'Next',
            noRows: 'No search terms found.',
            details: 'History',
            detailOf: 'Search term “{k}”',
            close: 'Close',
            inPeriod: 'Searches in period',
            lastSearch: 'Last search',
            firstSearch: 'First search',
            clicks: 'Total clicks',
            termNoLog: 'There are no searches for this term in the selected period.',
            noticeNoLog: 'No searches have been recorded yet. The evaluations fill up with the next search on the website.',
            noticeLogStart: 'The statistics start on {d}. The history before stays empty.',
            noticeRetention: 'Entries are deleted automatically after {n} days.',
            loading: 'Loading data …',
            error: 'The data could not be loaded.',
            forbidden: 'No permission for the search dashboard.',
            invalidRange: 'Please select a valid period (from ≤ to).',
            weekdayNames: ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
            weekdayShort: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            weekShort: 'CW',
            date: 'Date',
            logSince: 'statistics only since {d}',
            searchesUnit: 'searches',
            hitsUnit: 'results',
            more: 'Top {n}',
            chartSummary: 'Chart: {t}. Values see table.',
            dataTable: 'Values as table',
            hourLabel: '{h}:00',
            openDetail: 'Show history of “{k}”'
        }
    };

    var PRESETS = ['today', 'yesterday', '7d', '30d', '90d', '12m', 'year', 'all', 'custom'];
    var STORAGE_KEY = 'psSearchDashboard';
    var uid = 0;

    // ---------------------------------------------------------------- helpers

    function h(tag, attrs, children) {
        var el = document.createElement(tag);
        Object.keys(attrs || {}).forEach(function (key) {
            var value = attrs[key];
            if (value === null || value === undefined || value === false) {
                return;
            }
            if (key === 'text') {
                el.textContent = value;
            } else if (key === 'class') {
                el.className = value;
            } else if (key.indexOf('on') === 0) {
                el.addEventListener(key.slice(2), value);
            } else {
                el.setAttribute(key, value === true ? '' : value);
            }
        });
        (children || []).forEach(function (child) {
            if (child !== null && child !== undefined && child !== false) {
                el.appendChild(typeof child === 'string' ? document.createTextNode(child) : child);
            }
        });
        return el;
    }

    function fmt(template, values) {
        return String(template).replace(/\{(\w+)\}/g, function (match, key) {
            return values && values[key] !== undefined ? values[key] : match;
        });
    }

    function nextId(prefix) {
        uid += 1;
        return prefix + '-' + uid;
    }

    function cssVar(name, fallback) {
        var value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
        return value || fallback;
    }

    function alpha(color, a) {
        var c = color.trim();
        var m = c.match(/^#([0-9a-f]{3}|[0-9a-f]{6})$/i);
        if (m) {
            var hex = m[1].length === 3 ? m[1].replace(/(.)/g, '$1$1') : m[1];
            return 'rgba(' + parseInt(hex.slice(0, 2), 16) + ',' + parseInt(hex.slice(2, 4), 16) + ',' + parseInt(hex.slice(4, 6), 16) + ',' + a + ')';
        }
        m = c.match(/^rgba?\(([^)]+)\)$/i);
        if (m) {
            var parts = m[1].split(',').slice(0, 3).map(function (p) {
                return p.trim();
            });
            return 'rgba(' + parts.join(',') + ',' + a + ')';
        }
        return c;
    }

    function readStorage() {
        try {
            return JSON.parse(window.sessionStorage.getItem(STORAGE_KEY) || '{}') || {};
        } catch (e) {
            return {};
        }
    }

    function writeStorage(value) {
        try {
            window.sessionStorage.setItem(STORAGE_KEY, JSON.stringify(value));
        } catch (e) {
            // storage not available (private mode) → state is not remembered
        }
    }

    // ---------------------------------------------------------------- dashboard

    function Dashboard(root) {
        this.root = root;
        this.config = JSON.parse(root.getAttribute('data-ps-dashboard') || '{}');
        this.t = I18N[this.config.language] || I18N.de;
        this.locale = this.config.language === 'en' ? 'en-GB' : 'de-DE';
        this.tz = this.config.timezone || undefined;
        this.charts = {};
        this.controllers = {};
        this.busy = {};
        this.loaders = [];
        this.data = null;

        var stored = readStorage();
        this.state = {
            period: PRESETS.indexOf(stored.period) !== -1 ? stored.period : '30d',
            from: stored.from || '',
            to: stored.to || '',
            topLimit: [10, 20, 50].indexOf(stored.topLimit) !== -1 ? stored.topLimit : 10,
            trendTab: 'rising',
            table: {
                q: '',
                sort: 'searches',
                dir: 'desc',
                page: 1,
                perPage: [10, 25, 50, 100].indexOf(stored.perPage) !== -1 ? stored.perPage : 25
            }
        };

        this.number = new Intl.NumberFormat(this.locale);
        this.decimal = new Intl.NumberFormat(this.locale, {maximumFractionDigits: 1});
        this.build();
        this.observeTheme();
        this.load();
    }

    Dashboard.prototype.persist = function () {
        writeStorage({
            period: this.state.period,
            from: this.state.from,
            to: this.state.to,
            topLimit: this.state.topLimit,
            perPage: this.state.table.perPage
        });
    };

    Dashboard.prototype.params = function (extra) {
        var params = new URLSearchParams({period: this.state.period});
        if (this.state.period === 'custom') {
            params.set('from', this.state.from);
            params.set('to', this.state.to);
        }
        Object.keys(extra || {}).forEach(function (key) {
            params.set(key, extra[key]);
        });
        return params;
    };

    Dashboard.prototype.request = function (channel, url, params) {
        if (this.controllers[channel]) {
            this.controllers[channel].abort();
        }
        var controller = new AbortController();
        this.controllers[channel] = controller;
        var t = this.t;
        var self = this;

        this.setBusy(channel, true);

        // Only the latest request of a channel ends the busy state (an aborted predecessor must not hide the loader)
        var done = function () {
            if (self.controllers[channel] === controller) {
                self.setBusy(channel, false);
            }
        };

        return fetch(url + '?' + params.toString(), {
            credentials: 'same-origin',
            headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
            signal: controller.signal
        }).then(function (response) {
            return response.json().catch(function () {
                return {};
            }).then(function (json) {
                if (!response.ok) {
                    var error = new Error(response.status === 403 ? t.forbidden : (json.error || t.error));
                    error.status = response.status;
                    throw error;
                }
                return json;
            });
        }).then(function (json) {
            done();
            return json;
        }, function (error) {
            done();
            throw error;
        });
    };

    // ---------------------------------------------------------------- loader

    /**
     * Small spinner with text; shown while one of the given request channels is busy.
     */
    Dashboard.prototype.createLoader = function (channels, modifier) {
        var el = h('span', {
            class: 'ps-dash__loader' + (modifier ? ' ps-dash__loader--' + modifier : ''),
            role: 'status',
            hidden: true
        }, [
            h('span', {class: 'ps-dash__spinner', 'aria-hidden': 'true'}),
            h('span', {class: 'ps-dash__loader-text', text: this.t.loading})
        ]);
        this.loaders.push({el: el, channels: channels, timer: null});
        return el;
    };

    Dashboard.prototype.setBusy = function (channel, busy) {
        var self = this;
        this.busy[channel] = busy;

        this.loaders.forEach(function (loader) {
            var active = loader.channels.some(function (name) {
                return self.busy[name];
            });

            if (active && loader.el.hidden && !loader.timer) {
                // Delay: fast responses do not flicker
                loader.timer = setTimeout(function () {
                    loader.timer = null;
                    loader.el.hidden = false;
                }, 150);
            } else if (!active) {
                clearTimeout(loader.timer);
                loader.timer = null;
                loader.el.hidden = true;
            }
        });
    };

    // ---------------------------------------------------------------- formatting

    Dashboard.prototype.formatNumber = function (value) {
        return value === null || value === undefined ? '–' : this.number.format(value);
    };

    Dashboard.prototype.formatDecimal = function (value) {
        return value === null || value === undefined ? '–' : this.decimal.format(value);
    };

    // "YYYY-MM-DD" is a local calendar day → format it without time zone shift
    Dashboard.prototype.formatDay = function (key, options) {
        var parts = key.split('-');
        var date = new Date(Date.UTC(+parts[0], +parts[1] - 1, +parts[2], 12));
        return new Intl.DateTimeFormat(this.locale, Object.assign({timeZone: 'UTC'}, options || {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        })).format(date);
    };

    Dashboard.prototype.formatBucket = function (key, granularity, long) {
        if (granularity === 'week') {
            return this.t.weekShort + ' ' + isoWeek(key) + ' (' + this.formatDay(key, {
                day: '2-digit',
                month: '2-digit'
            }) + ')';
        }
        return this.formatDay(key, long ? {
            weekday: 'short',
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        } : {day: '2-digit', month: '2-digit'});
    };

    Dashboard.prototype.formatTimestamp = function (ts, withTime) {
        if (!ts) {
            return '–';
        }
        var options = {timeZone: this.tz, day: '2-digit', month: '2-digit', year: 'numeric'};
        if (withTime) {
            options.hour = '2-digit';
            options.minute = '2-digit';
        }
        return new Intl.DateTimeFormat(this.locale, options).format(new Date(ts * 1000));
    };

    function isoWeek(key) {
        var parts = key.split('-');
        var date = new Date(Date.UTC(+parts[0], +parts[1] - 1, +parts[2]));
        var day = date.getUTCDay() || 7;
        date.setUTCDate(date.getUTCDate() + 4 - day);
        var yearStart = new Date(Date.UTC(date.getUTCFullYear(), 0, 1));
        return Math.ceil(((date - yearStart) / 86400000 + 1) / 7);
    }

    // ---------------------------------------------------------------- layout

    Dashboard.prototype.build = function () {
        var t = this.t;
        var self = this;
        this.root.textContent = '';

        // Header + filter
        this.presetButtons = {};
        var presetGroup = h('div', {class: 'ps-dash__presets', role: 'group', 'aria-label': t.filterLabel});
        PRESETS.forEach(function (preset) {
            var button = h('button', {
                type: 'button',
                class: 'ps-dash__preset',
                text: t.presets[preset],
                onclick: function () {
                    self.selectPreset(preset);
                }
            });
            self.presetButtons[preset] = button;
            presetGroup.appendChild(button);
        });

        var fromId = nextId('ps-from');
        var toId = nextId('ps-to');
        this.fromInput = h('input', {type: 'date', id: fromId, class: 'tl_text', value: this.state.from});
        this.toInput = h('input', {type: 'date', id: toId, class: 'tl_text', value: this.state.to});
        this.customBox = h('form', {
            class: 'ps-dash__custom', onsubmit: function (e) {
                e.preventDefault();
                self.applyCustom();
            }
        }, [
            h('label', {for: fromId, text: t.from}), this.fromInput,
            h('label', {for: toId, text: t.to}), this.toInput,
            h('button', {type: 'submit', class: 'tl_submit', text: t.apply})
        ]);

        this.periodInfo = h('p', {class: 'ps-dash__period'});
        this.notice = h('div', {class: 'ps-dash__notices', role: 'status'});

        // Downloads of the selected period (links are updated in updateExportLinks)
        this.exportLinks = {
            terms: h('a', {class: 'ps-dash__action', text: t.exportTerms, download: '', title: t.exportHint}),
            log: h('a', {class: 'ps-dash__action', text: t.exportLog, download: '', title: t.exportHint})
        };
        var actions = h('div', {class: 'ps-dash__actions'}, [
            this.exportLinks.terms,
            this.exportLinks.log,
            this.config.logUrl ? h('a', {class: 'ps-dash__action', href: this.config.logUrl, text: t.manageLog}) : null
        ]);

        this.root.appendChild(h('header', {class: 'ps-dash__header'}, [
            h('div', {class: 'ps-dash__titles'}, [h('h2', {
                class: 'ps-dash__title',
                text: t.title
            }), h('p', {class: 'ps-dash__subtitle', text: t.subtitle}), actions]),
            h('div', {class: 'ps-dash__filter'}, [presetGroup, this.customBox])
        ]));
        this.root.appendChild(h('div', {class: 'ps-dash__statusbar'}, [this.periodInfo, this.createLoader(['overview', 'terms'])]));
        this.root.appendChild(this.notice);

        // KPI sections
        this.kpi = {};
        var calendar = h('div', {class: 'ps-dash__kpis'});
        ['today', 'yesterday', 'week', 'month'].forEach(function (key) {
            calendar.appendChild(self.kpiCard(key, t[key]));
        });
        var period = h('div', {class: 'ps-dash__kpis'});
        ['searches', 'keywords', 'top', 'avgPerDay', 'zeroRate', 'clickRate', 'allTime'].forEach(function (key) {
            period.appendChild(self.kpiCard(key, t[key]));
        });

        this.root.appendChild(h('section', {class: 'ps-dash__section', 'aria-labelledby': 'ps-h-cal'}, [
            h('h3', {
                id: 'ps-h-cal',
                class: 'ps-dash__section-title'
            }, [t.calendarHeading + ' ', h('span', {class: 'ps-dash__muted', text: '· ' + t.calendarHint})]),
            calendar
        ]));
        this.root.appendChild(h('section', {class: 'ps-dash__section', 'aria-labelledby': 'ps-h-period'}, [
            h('h3', {id: 'ps-h-period', class: 'ps-dash__section-title', text: t.periodHeading}),
            period
        ]));

        // Charts
        this.cards = {};
        var grid = h('div', {class: 'ps-dash__grid'});
        grid.appendChild(this.chartCard('timeline', t.timeline, 'ps-dash__card--wide'));

        var topSwitch = h('div', {class: 'ps-dash__segmented', role: 'group', 'aria-label': t.topTerms});
        this.topButtons = {};
        [10, 20, 50].forEach(function (n) {
            var button = h('button', {
                type: 'button', class: 'ps-dash__preset', text: fmt(t.more, {n: n}), onclick: function () {
                    self.state.topLimit = n;
                    self.persist();
                    self.renderTopTerms();
                }
            });
            self.topButtons[n] = button;
            topSwitch.appendChild(button);
        });
        grid.appendChild(this.chartCard('top', t.topTerms, 'ps-dash__card--wide', topSwitch));
        grid.appendChild(this.chartCard('weekdays', t.weekdays));
        grid.appendChild(this.chartCard('hours', t.hours));
        grid.appendChild(this.listCard('trends', t.trends));
        grid.appendChild(this.listCard('zero', t.zeroTerms, t.zeroHint));
        this.root.appendChild(grid);

        this.root.appendChild(this.buildTable());
        this.root.appendChild(this.buildDialog());
        this.updatePresetButtons();
    };

    Dashboard.prototype.kpiCard = function (key, label) {
        var value = h('p', {class: 'ps-dash__kpi-value', text: '–'});
        var sub = h('p', {class: 'ps-dash__kpi-sub'});
        var hint = h('p', {class: 'ps-dash__kpi-hint'});
        var card = h('article', {class: 'ps-dash__kpi ps-dash__kpi--' + key, 'aria-label': label}, [
            h('h4', {class: 'ps-dash__kpi-label', text: label}), value, sub, hint
        ]);
        this.kpi[key] = {card: card, value: value, sub: sub, hint: hint};
        return card;
    };

    Dashboard.prototype.chartCard = function (key, title, modifier, tools) {
        var titleId = nextId('ps-card');
        var canvas = h('canvas', {role: 'img', 'aria-label': title});
        var summary = h('p', {class: 'ps-dash__chart-summary'});
        var table = h('div', {class: 'ps-dash__sr'});
        var body = h('div', {class: 'ps-dash__chart'}, [canvas]);
        var card = h('section', {class: 'ps-dash__card ' + (modifier || ''), 'aria-labelledby': titleId}, [
            h('div', {class: 'ps-dash__card-head'}, [h('h3', {
                id: titleId,
                class: 'ps-dash__card-title',
                text: title
            }), tools || null]),
            body, summary, table
        ]);
        this.cards[key] = {card: card, canvas: canvas, body: body, summary: summary, table: table, title: title};
        return card;
    };

    Dashboard.prototype.listCard = function (key, title, hint) {
        var titleId = nextId('ps-card');
        var content = h('div', {class: 'ps-dash__list-body'});
        var card = h('section', {class: 'ps-dash__card', 'aria-labelledby': titleId}, [
            h('div', {class: 'ps-dash__card-head'}, [h('h3', {
                id: titleId,
                class: 'ps-dash__card-title',
                text: title
            })]),
            hint ? h('p', {class: 'ps-dash__muted ps-dash__card-hint', text: hint}) : null,
            content
        ]);
        this.cards[key] = {card: card, content: content};
        return card;
    };

    Dashboard.prototype.buildTable = function () {
        var t = this.t;
        var self = this;
        var searchId = nextId('ps-q');
        var perPageId = nextId('ps-pp');

        this.searchInput = h('input', {
            type: 'search',
            id: searchId,
            class: 'tl_text',
            placeholder: t.tableSearch,
            autocomplete: 'off',
            oninput: function () {
                clearTimeout(self.searchTimer);
                self.searchTimer = setTimeout(function () {
                    self.state.table.q = self.searchInput.value;
                    self.state.table.page = 1;
                    self.loadTerms();
                }, 300);
            }
        });

        var perPage = h('select', {
            id: perPageId, class: 'tl_select', onchange: function () {
                self.state.table.perPage = parseInt(perPage.value, 10);
                self.state.table.page = 1;
                self.persist();
                self.loadTerms();
            }
        });
        [10, 25, 50, 100].forEach(function (n) {
            perPage.appendChild(h('option', {
                value: String(n),
                text: String(n),
                selected: n === self.state.table.perPage
            }));
        });

        this.tableHead = h('thead');
        this.tableBody = h('tbody');
        this.tableInfo = h('p', {class: 'ps-dash__muted', 'aria-live': 'polite'});
        this.tableSource = h('p', {class: 'ps-dash__kpi-hint'});
        this.prevButton = h('button', {
            type: 'button', class: 'tl_submit', text: t.prev, onclick: function () {
                self.state.table.page -= 1;
                self.loadTerms();
            }
        });
        this.nextButton = h('button', {
            type: 'button', class: 'tl_submit', text: t.next, onclick: function () {
                self.state.table.page += 1;
                self.loadTerms();
            }
        });

        return h('section', {class: 'ps-dash__card ps-dash__card--table', 'aria-labelledby': 'ps-h-table'}, [
            h('div', {class: 'ps-dash__card-head'}, [
                h('h3', {id: 'ps-h-table', class: 'ps-dash__card-title', text: t.table}),
                h('div', {class: 'ps-dash__table-tools'}, [
                    h('label', {for: searchId, class: 'ps-dash__sr', text: t.tableSearch}), this.searchInput,
                    h('label', {for: perPageId, text: t.perPage}), perPage
                ])
            ]),
            h('div', {class: 'ps-dash__table-wrap'}, [h('table', {class: 'ps-dash__table'}, [this.tableHead, this.tableBody])]),
            h('div', {class: 'ps-dash__pagination'}, [this.prevButton, h('div', {class: 'ps-dash__pagination-info'}, [this.tableInfo, this.createLoader(['terms'], 'inline')]), this.nextButton]),
            this.tableSource
        ]);
    };

    Dashboard.prototype.buildDialog = function () {
        var self = this;
        this.dialogTitle = h('h3', {class: 'ps-dash__dialog-title', id: nextId('ps-dlg')});
        this.dialogKpis = h('div', {class: 'ps-dash__kpis ps-dash__kpis--compact'});
        this.dialogCanvas = h('canvas', {role: 'img'});
        this.dialogStatus = h('p', {class: 'ps-dash__muted', role: 'status'});
        this.dialog = h('dialog', {class: 'ps-dash__dialog', 'aria-labelledby': this.dialogTitle.id}, [
            h('div', {class: 'ps-dash__dialog-head'}, [
                this.dialogTitle,
                h('button', {
                    type: 'button',
                    class: 'ps-dash__close',
                    'aria-label': this.t.close,
                    title: this.t.close,
                    text: '×',
                    onclick: function () {
                        self.dialog.close();
                    }
                })
            ]),
            this.createLoader(['term']),
            this.dialogStatus,
            this.dialogKpis,
            h('div', {class: 'ps-dash__chart'}, [this.dialogCanvas])
        ]);
        this.dialog.addEventListener('click', function (e) {
            if (e.target === self.dialog) {
                self.dialog.close();
            }
        });
        return this.dialog;
    };

    // ---------------------------------------------------------------- filter

    Dashboard.prototype.updatePresetButtons = function () {
        var self = this;
        PRESETS.forEach(function (preset) {
            self.presetButtons[preset].setAttribute('aria-pressed', String(self.state.period === preset));
        });
        [10, 20, 50].forEach(function (n) {
            self.topButtons[n].setAttribute('aria-pressed', String(self.state.topLimit === n));
        });
        this.customBox.hidden = this.state.period !== 'custom';
    };

    Dashboard.prototype.selectPreset = function (preset) {
        this.state.period = preset;
        this.updatePresetButtons();

        if (preset === 'custom') {
            if (!this.fromInput.value || !this.toInput.value) {
                var today = new Date();
                var start = new Date(today.getTime() - 29 * 86400000);
                this.toInput.value = this.toInput.value || today.toISOString().slice(0, 10);
                this.fromInput.value = this.fromInput.value || start.toISOString().slice(0, 10);
            }
            this.fromInput.focus();
            return;
        }

        this.persist();
        this.state.table.page = 1;
        this.load();
    };

    Dashboard.prototype.applyCustom = function () {
        var from = this.fromInput.value;
        var to = this.toInput.value;

        if (!from || !to || from > to) {
            this.showNotices([{type: 'error', text: this.t.invalidRange}]);
            this.fromInput.setAttribute('aria-invalid', 'true');
            return;
        }

        this.fromInput.removeAttribute('aria-invalid');
        this.state.from = from;
        this.state.to = to;
        this.state.table.page = 1;
        this.persist();
        this.load();
    };

    // ---------------------------------------------------------------- loading

    Dashboard.prototype.updateExportLinks = function () {
        var self = this;
        Object.keys(this.exportLinks).forEach(function (mode) {
            self.exportLinks[mode].href = self.config.endpoints.export + '?' + self.params({mode: mode}).toString();
        });
    };

    Dashboard.prototype.load = function () {
        var self = this;
        this.updateExportLinks();
        this.root.setAttribute('aria-busy', 'true');
        this.root.classList.add('is-loading');

        this.request('overview', this.config.endpoints.overview, this.params()).then(function (data) {
            self.data = data;
            self.render();
        }).catch(function (error) {
            if (error.name !== 'AbortError') {
                self.showNotices([{type: 'error', text: error.message}]);
            }
        }).then(function () {
            self.root.removeAttribute('aria-busy');
            self.root.classList.remove('is-loading');
        });

        this.loadTerms();
    };

    Dashboard.prototype.loadTerms = function () {
        var self = this;
        var s = this.state.table;
        this.tableBody.setAttribute('aria-busy', 'true');

        this.request('terms', this.config.endpoints.terms, this.params({
            q: s.q,
            sort: s.sort,
            dir: s.dir,
            page: s.page,
            perPage: s.perPage
        })).then(function (data) {
            self.renderTable(data);
        }).catch(function (error) {
            if (error.name !== 'AbortError') {
                self.tableBody.textContent = '';
                self.tableBody.appendChild(h('tr', {}, [h('td', {
                    colspan: '6',
                    class: 'ps-dash__error',
                    text: error.message
                })]));
            }
        }).then(function () {
            self.tableBody.removeAttribute('aria-busy');
        });
    };

    // ---------------------------------------------------------------- rendering

    Dashboard.prototype.render = function () {
        var d = this.data;
        this.renderPeriod(d);
        this.renderNotices(d);
        this.renderKpis(d);
        this.renderTimeline(d);
        this.renderTopTerms();
        this.renderDistribution('weekdays', d.weekdays, this.t.weekdayShort, this.t.weekdayNames);
        this.renderDistribution('hours', d.hours, d.hours.map(function (v, i) {
            return String(i).padStart(2, '0');
        }), null);
        this.renderTrends();
        this.renderZeroTerms(d.zeroTerms);
    };

    Dashboard.prototype.renderPeriod = function (d) {
        var p = d.period;
        var self = this;
        var range = function (start, end) {
            var startTs = start ? Date.parse(start) / 1000 : d.log.start;
            return self.formatTimestamp(startTs, false) + ' – ' + self.formatTimestamp(Date.parse(end) / 1000 - 1, p.preset === 'today' || p.preset === '7d' || p.preset === '30d');
        };
        var text = this.t.presets[p.preset] + ': ' + range(p.start, p.end);
        if (p.previousStart && d.log.hasPrevious) {
            text += ' · ' + this.t.previous + ': ' + range(p.previousStart, p.previousEnd);
        }
        this.periodInfo.textContent = text + ' · ' + p.timezone;
    };

    Dashboard.prototype.renderNotices = function (d) {
        var notices = [];
        if (!d.log.start) {
            notices.push({type: 'info', text: this.t.noticeNoLog});
        } else if (!d.log.coversPeriod) {
            notices.push({
                type: 'info',
                text: fmt(this.t.noticeLogStart, {d: this.formatTimestamp(d.log.start, true)})
            });
        }
        if (d.log.legacy > 0) {
            notices.push({type: 'info', text: fmt(this.t.noticeLegacy, {n: this.formatNumber(d.log.legacy)})});
        }
        if (notices.length && d.log.retentionDays > 0) {
            notices[notices.length - 1].text += ' ' + fmt(this.t.noticeRetention, {n: d.log.retentionDays});
        }
        this.showNotices(notices);
    };

    Dashboard.prototype.showNotices = function (notices) {
        this.notice.textContent = '';
        var self = this;
        notices.forEach(function (n) {
            self.notice.appendChild(h('p', {class: n.type === 'error' ? 'tl_error' : 'tl_info', text: n.text}));
        });
    };

    Dashboard.prototype.setKpi = function (key, value, change, compareLabel, hint, options) {
        var kpi = this.kpi[key];
        options = options || {};
        kpi.value.textContent = value;
        kpi.sub.textContent = '';
        kpi.card.classList.remove('is-up', 'is-down', 'is-good', 'is-bad');

        if (change === null || change === undefined) {
            if (compareLabel) {
                kpi.sub.appendChild(h('span', {class: 'ps-dash__muted', text: compareLabel}));
            }
        } else {
            var up = change > 0;
            var flat = change === 0;
            var good = options.invert ? !up : up;
            var unit = options.points ? ' ' + this.t.points : ' %';
            kpi.card.classList.add(up ? 'is-up' : 'is-down', flat ? 'is-flat' : (good ? 'is-good' : 'is-bad'));
            kpi.sub.appendChild(h('span', {
                class: 'ps-dash__delta',
                text: (flat ? '± ' : (up ? '▲ +' : '▼ ')) + this.formatDecimal(change) + unit
            }));
            kpi.sub.appendChild(document.createTextNode(' ' + compareLabel));
        }

        kpi.hint.textContent = hint || '';
    };

    Dashboard.prototype.renderKpis = function (d) {
        var t = this.t;
        var c = d.calendar;
        var k = d.kpis;
        var self = this;
        var cmp = function (metric, label) {
            return metric.change === null && metric.previous === null ? t.noCompare : label;
        };

        [['today', t.vsToday], ['yesterday', t.vsYesterday], ['week', t.vsWeek], ['month', t.vsMonth]].forEach(function (entry) {
            var metric = c[entry[0]];
            self.setKpi(entry[0], self.formatNumber(metric.value), metric.change, cmp(metric, entry[1]), metric.coversWindow || !d.log.start ? '' : fmt(t.logSince, {d: self.formatTimestamp(d.log.start, true)}));
        });

        this.setKpi('searches', this.formatNumber(k.searches.value), k.searches.change, cmp(k.searches, t.vsPeriod));
        this.setKpi('keywords', this.formatNumber(k.keywords.value), k.keywords.change, cmp(k.keywords, t.vsPeriod));
        this.setKpi('top', k.top.keyword || '–', null, k.top.keyword ? this.formatNumber(k.top.searches) + ' ' + t.searchesUnit + (k.top.share !== null ? ' · ' + fmt(t.share, {p: this.formatDecimal(k.top.share)}) : '') : '');
        this.kpi.top.value.classList.add('ps-dash__kpi-value--text');
        this.kpi.top.value.title = k.top.keyword || '';
        this.setKpi('avgPerDay', this.formatDecimal(k.avgPerDay.value), k.avgPerDay.change, cmp(k.avgPerDay, t.vsPeriod), k.avgPerDay.days ? (k.avgPerDay.days === 1 ? t.day : fmt(t.days, {d: self.formatNumber(k.avgPerDay.days)})) : '');
        this.setKpi('zeroRate', k.zeroRate.value === null ? '–' : this.formatDecimal(k.zeroRate.value) + ' %', k.zeroRate.change, k.zeroRate.change === null ? t.noCompare : t.vsPeriod, this.formatNumber(k.zeroRate.zero) + ' ' + t.searchesUnit, {
            invert: true,
            points: true
        });
        this.setKpi('clickRate', k.clickRate.value === null ? '–' : this.formatDecimal(k.clickRate.value) + ' %', k.clickRate.change, k.clickRate.change === null ? t.noCompare : t.vsPeriod, fmt(t.clickSub, {n: this.formatNumber(k.clickRate.clicks)}), {points: true});
        this.setKpi('allTime', this.formatNumber(d.allTime.searches), null, fmt(t.allTimeSub, {k: this.formatNumber(d.allTime.keywords)}));
    };

    // ---------------------------------------------------------------- charts

    Dashboard.prototype.theme = function () {
        return {
            accent: cssVar('--contao', '#f47c00'),
            blue: cssVar('--blue', '#006494'),
            text: cssVar('--text', '#222'),
            gray: cssVar('--gray', '#747476'),
            border: cssVar('--border', '#eaeaec'),
            font: cssVar('--font-family', 'system-ui, sans-serif')
        };
    };

    Dashboard.prototype.applyChartDefaults = function () {
        if (!window.Chart) {
            return false;
        }
        var theme = this.theme();
        Chart.defaults.color = theme.text;
        Chart.defaults.borderColor = theme.border;
        Chart.defaults.font.family = theme.font;
        Chart.defaults.font.size = 12;
        Chart.defaults.plugins.tooltip.padding = 10;
        Chart.defaults.plugins.tooltip.cornerRadius = 6;
        Chart.defaults.maintainAspectRatio = false;
        Chart.defaults.animation = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? false : {duration: 300};
        return true;
    };

    Dashboard.prototype.drawChart = function (key, canvas, config) {
        if (!this.applyChartDefaults()) {
            return;
        }
        if (this.charts[key]) {
            this.charts[key].destroy();
        }
        this.charts[key] = new Chart(canvas, config);
    };

    Dashboard.prototype.srTable = function (container, headers, rows) {
        container.textContent = '';
        var table = h('table', {}, [
            h('caption', {text: this.t.dataTable}),
            h('thead', {}, [h('tr', {}, headers.map(function (head) {
                return h('th', {scope: 'col', text: head});
            }))]),
            h('tbody', {}, rows.map(function (row) {
                return h('tr', {}, row.map(function (cell) {
                    return h('td', {text: String(cell)});
                }));
            }))
        ]);
        container.appendChild(table);
    };

    Dashboard.prototype.renderTimeline = function (d) {
        var t = this.t;
        var self = this;
        var tl = d.timeline;
        var card = this.cards.timeline;
        var theme = this.theme();
        var labels = tl.current.map(function (p) {
            return self.formatBucket(p.key, tl.granularity, false);
        });
        var peakIndex = tl.peak ? tl.current.findIndex(function (p) {
            return p.key === tl.peak.key;
        }) : -1;
        var datasets = [{
            label: t.current + ' (' + (tl.granularity === 'week' ? t.perWeek : t.perDay) + ')',
            data: tl.current.map(function (p) {
                return p.value;
            }),
            borderColor: theme.accent,
            backgroundColor: alpha(theme.accent, 0.12),
            fill: true,
            tension: 0.3,
            spanGaps: false,
            pointRadius: tl.current.map(function (p, i) {
                return i === peakIndex ? 5 : (tl.current.length > 60 ? 0 : 2);
            }),
            pointHoverRadius: 5
        }];

        if (tl.previous) {
            datasets.push({
                label: t.previous,
                data: tl.previous.map(function (p) {
                    return p.value;
                }),
                borderColor: theme.gray,
                borderDash: [5, 4],
                backgroundColor: 'transparent',
                tension: 0.3,
                pointRadius: 0,
                pointHoverRadius: 4
            });
        }

        this.drawChart('timeline', card.canvas, {
            type: 'line',
            data: {labels: labels, datasets: datasets},
            options: {
                interaction: {mode: 'index', intersect: false},
                scales: {
                    y: {beginAtZero: true, ticks: {precision: 0}},
                    x: {ticks: {maxRotation: 0, autoSkipPadding: 12}}
                },
                plugins: {
                    legend: {position: 'bottom', labels: {boxWidth: 12}},
                    tooltip: {
                        callbacks: {
                            title: function (items) {
                                return self.formatBucket(tl.current[items[0].dataIndex].key, tl.granularity, true);
                            },
                            label: function (item) {
                                var list = item.datasetIndex === 1 ? tl.previous : tl.current;
                                var point = list[item.dataIndex];
                                var value = item.raw === null ? '–' : self.formatNumber(item.raw);
                                return item.dataset.label.split(' (')[0] + (item.datasetIndex === 1 && point ? ' (' + self.formatBucket(point.key, tl.granularity, false) + ')' : '') + ': ' + value;
                            }
                        }
                    }
                }
            }
        });

        var parts = [];
        if (tl.peak) {
            parts.push(fmt(t.peak, {
                d: this.formatBucket(tl.peak.key, tl.granularity, true),
                n: this.formatNumber(tl.peak.value)
            }));
        }
        if (tl.trend && tl.trend.change !== null) {
            parts.push(fmt(tl.trend.change >= 0 ? t.trendUp : t.trendDown, {p: this.formatDecimal(tl.trend.change)}));
        }
        card.summary.textContent = parts.join(' · ');
        card.canvas.setAttribute('aria-label', fmt(t.chartSummary, {t: card.title + (parts.length ? '. ' + parts.join('. ') : '')}));

        var headers = [tl.granularity === 'week' ? t.weekShort : t.date, t.current].concat(tl.previous ? [t.previous] : []);
        this.srTable(card.table, headers, tl.current.map(function (p, i) {
            var row = [self.formatBucket(p.key, tl.granularity, true), p.value === null ? '–' : p.value];
            if (tl.previous) {
                row.push(tl.previous[i] ? tl.previous[i].value : '–');
            }
            return row;
        }));
    };

    Dashboard.prototype.renderTopTerms = function () {
        if (!this.data) {
            return;
        }
        this.updatePresetButtons();
        var t = this.t;
        var self = this;
        var card = this.cards.top;
        var theme = this.theme();
        var items = this.data.topTerms.items.slice(0, this.state.topLimit);

        card.body.style.height = Math.max(160, items.length * 26 + 40) + 'px';
        this.drawChart('top', card.canvas, {
            type: 'bar',
            data: {
                labels: items.map(function (i) {
                    return i.keyword.length > 40 ? i.keyword.slice(0, 39) + '…' : i.keyword;
                }),
                datasets: [{
                    label: t.searchesUnit,
                    data: items.map(function (i) {
                        return i.searches;
                    }),
                    backgroundColor: alpha(theme.accent, 0.8),
                    hoverBackgroundColor: theme.accent,
                    borderRadius: 4,
                    maxBarThickness: 20
                }]
            },
            options: {
                indexAxis: 'y',
                scales: {x: {beginAtZero: true, ticks: {precision: 0}}, y: {grid: {display: false}}},
                plugins: {
                    legend: {display: false},
                    tooltip: {
                        callbacks: {
                            title: function (ctx) {
                                return items[ctx[0].dataIndex].keyword;
                            },
                            label: function (ctx) {
                                var i = items[ctx.dataIndex];
                                return self.formatNumber(i.searches) + ' ' + t.searchesUnit + ' · Ø ' + self.formatDecimal(i.avg_hits) + ' ' + t.hitsUnit;
                            }
                        }
                    }
                },
                onClick: function (event, elements) {
                    if (elements.length) {
                        self.openTerm(items[elements[0].index].keyword);
                    }
                },
                onHover: function (event, elements) {
                    event.native.target.style.cursor = elements.length ? 'pointer' : 'default';
                }
            }
        });

        card.summary.textContent = '';
        card.canvas.setAttribute('aria-label', fmt(t.chartSummary, {t: card.title}));

        // Accessible alternative incl. buttons to open the history of a term
        card.table.textContent = '';
        card.table.appendChild(h('ol', {}, items.map(function (i) {
            return h('li', {}, [h('button', {
                type: 'button',
                text: fmt(t.openDetail, {k: i.keyword}) + ': ' + self.formatNumber(i.searches),
                onclick: function () {
                    self.openTerm(i.keyword);
                }
            })]);
        })));
    };

    Dashboard.prototype.renderDistribution = function (key, values, labels, longLabels) {
        var t = this.t;
        var self = this;
        var card = this.cards[key];
        var theme = this.theme();
        var total = values.reduce(function (a, b) {
            return a + b;
        }, 0);
        var max = Math.max.apply(null, values);
        var maxIndex = total > 0 ? values.indexOf(max) : -1;

        this.drawChart(key, card.canvas, {
            type: 'bar',
            data: {
                labels: labels, datasets: [{
                    label: t.searchesUnit,
                    data: values,
                    backgroundColor: values.map(function (v, i) {
                        return i === maxIndex ? theme.accent : alpha(theme.blue, 0.55);
                    }),
                    borderRadius: 4
                }]
            },
            options: {
                scales: {
                    y: {beginAtZero: true, ticks: {precision: 0}},
                    x: {grid: {display: false}, ticks: {maxRotation: 0, autoSkipPadding: 6}}
                },
                plugins: {
                    legend: {display: false}, tooltip: {
                        callbacks: {
                            title: function (ctx) {
                                return longLabels ? longLabels[ctx[0].dataIndex] : fmt(t.hourLabel, {h: labels[ctx[0].dataIndex]});
                            },
                            label: function (ctx) {
                                return self.formatNumber(ctx.raw) + ' ' + t.searchesUnit + (total ? ' (' + self.formatDecimal(ctx.raw / total * 100) + ' %)' : '');
                            }
                        }
                    }
                }
            }
        });

        var summary = '';
        if (maxIndex !== -1) {
            var share = this.formatDecimal(max / total * 100);
            summary = longLabels ? fmt(t.busiestDay, {
                d: longLabels[maxIndex],
                p: share
            }) : fmt(t.busiestHour, {h: labels[maxIndex], p: share});
        }
        card.summary.textContent = summary;
        card.canvas.setAttribute('aria-label', fmt(t.chartSummary, {t: card.title + (summary ? '. ' + summary : '')}));
        this.srTable(card.table, [longLabels ? t.weekdays : t.hours, t.searchesUnit], values.map(function (v, i) {
            return [longLabels ? longLabels[i] : fmt(t.hourLabel, {h: labels[i]}), v];
        }));
    };

    Dashboard.prototype.termButton = function (keyword) {
        var self = this;
        return h('button', {
            type: 'button',
            class: 'ps-dash__term',
            title: fmt(this.t.openDetail, {k: keyword}),
            text: keyword,
            onclick: function () {
                self.openTerm(keyword);
            }
        });
    };

    Dashboard.prototype.renderTrends = function () {
        var t = this.t;
        var self = this;
        var trends = this.data.trends;
        var content = this.cards.trends.content;
        content.textContent = '';

        if (!trends.available) {
            content.appendChild(h('p', {
                class: 'ps-dash__muted',
                text: trends.reason === 'no_previous' ? t.trendNoPrevious : t.trendLogShort
            }));
            return;
        }

        var tabs = h('div', {class: 'ps-dash__tabs', role: 'tablist', 'aria-label': t.trends});
        var panel = h('div', {class: 'ps-dash__tabpanel', role: 'tabpanel', tabindex: '0'});
        var keys = ['rising', 'falling', 'emerging'];

        var show = function (key, focus) {
            self.state.trendTab = key;
            Array.prototype.forEach.call(tabs.children, function (tab) {
                var active = tab.getAttribute('data-key') === key;
                tab.setAttribute('aria-selected', String(active));
                tab.tabIndex = active ? 0 : -1;
                if (active) {
                    panel.setAttribute('aria-labelledby', tab.id);
                    if (focus) {
                        tab.focus();
                    }
                }
            });
            panel.textContent = '';
            var items = trends[key];
            if (!items.length) {
                panel.appendChild(h('p', {class: 'ps-dash__muted', text: fmt(t.trendEmpty, {n: trends.minVolume})}));
                return;
            }
            panel.appendChild(h('ol', {class: 'ps-dash__rank'}, items.map(function (item) {
                var meta;
                if (key === 'emerging') {
                    meta = [h('span', {
                        class: 'ps-dash__badge',
                        text: t.newBadge
                    }), ' ' + self.formatNumber(item.current)];
                } else {
                    var diff = item.current - item.previous;
                    var pct = item.previous > 0 ? Math.round(diff / item.previous * 100) : null;
                    meta = [h('span', {
                        class: 'ps-dash__delta ' + (diff > 0 ? 'is-good' : 'is-bad'),
                        text: (diff > 0 ? '▲ +' : '▼ ') + self.formatNumber(diff) + (pct !== null ? ' (' + (pct > 0 ? '+' : '') + pct + ' %)' : '')
                    }),
                        h('span', {
                            class: 'ps-dash__muted',
                            text: ' ' + self.formatNumber(item.previous) + ' → ' + self.formatNumber(item.current)
                        })];
                }
                return h('li', {}, [self.termButton(item.keyword), h('span', {class: 'ps-dash__rank-meta'}, meta)]);
            })));
        };

        keys.forEach(function (key, index) {
            var tab = h('button', {
                type: 'button',
                role: 'tab',
                id: nextId('ps-tab'),
                'data-key': key,
                class: 'ps-dash__tab',
                text: t[key] + ' (' + trends[key].length + ')',
                onclick: function () {
                    show(key, false);
                },
                onkeydown: function (e) {
                    if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
                        e.preventDefault();
                        show(keys[(index + (e.key === 'ArrowRight' ? 1 : keys.length - 1)) % keys.length], true);
                    }
                }
            });
            tab.setAttribute('aria-controls', panel.id = panel.id || nextId('ps-panel'));
            tabs.appendChild(tab);
        });

        content.appendChild(tabs);
        content.appendChild(panel);
        show(this.state.trendTab, false);
    };

    Dashboard.prototype.renderZeroTerms = function (items) {
        var self = this;
        var content = this.cards.zero.content;
        content.textContent = '';
        if (!items.length) {
            content.appendChild(h('p', {class: 'ps-dash__muted', text: this.t.noRows}));
            return;
        }
        var max = items[0].searches;
        content.appendChild(h('ol', {class: 'ps-dash__rank'}, items.map(function (item) {
            return h('li', {}, [
                self.termButton(item.keyword),
                h('span', {class: 'ps-dash__rank-meta'}, [
                    h('span', {
                        class: 'ps-dash__meter',
                        'aria-hidden': 'true'
                    }, [h('span', {style: 'width:' + Math.max(4, Math.round(item.searches / max * 100)) + '%'})]),
                    ' ' + self.formatNumber(item.searches)
                ])
            ]);
        })));
    };

    // ---------------------------------------------------------------- table

    Dashboard.prototype.renderTable = function (data) {
        var t = this.t;
        var self = this;
        var columns = [
            {key: 'keyword', label: t.colKeyword, sort: 'keyword'},
            {key: 'searches', label: t.colSearches, sort: 'searches', num: true},
            {key: 'avg_hits', label: t.colAvgHits, sort: 'avg_hits', num: true},
            {key: 'zero', label: t.colZero, num: true},
            {key: 'clicks', label: t.colClicks, sort: 'clicks', num: true},
            {key: 'first', label: t.colFirst, sort: 'first'},
            {key: 'last', label: t.colLast, sort: 'last'}
        ];

        this.state.table.sort = data.sort;
        this.state.table.dir = data.direction;
        this.state.table.page = data.page;

        this.tableHead.textContent = '';
        this.tableHead.appendChild(h('tr', {}, columns.map(function (col) {
            var active = col.sort && col.sort === data.sort;
            var th = h('th', {
                scope: 'col',
                class: col.num ? 'is-num' : '',
                'aria-sort': active ? (data.direction === 'asc' ? 'ascending' : 'descending') : null
            });
            if (!col.sort) {
                th.textContent = col.label;
                return th;
            }
            th.appendChild(h('button', {
                type: 'button', class: 'ps-dash__sort', onclick: function () {
                    var s = self.state.table;
                    s.dir = s.sort === col.sort ? (s.dir === 'asc' ? 'desc' : 'asc') : (col.sort === 'keyword' ? 'asc' : 'desc');
                    s.sort = col.sort;
                    s.page = 1;
                    self.loadTerms();
                }
            }, [col.label, h('span', {
                class: 'ps-dash__sort-icon',
                'aria-hidden': 'true',
                text: active ? (data.direction === 'asc' ? ' ▲' : ' ▼') : ' ↕'
            })]));
            return th;
        })));

        this.tableBody.textContent = '';
        if (!data.rows.length) {
            this.tableBody.appendChild(h('tr', {}, [h('td', {
                colspan: String(columns.length),
                class: 'ps-dash__muted',
                text: t.noRows
            })]));
        }
        data.rows.forEach(function (row) {
            var firstCell = h('td', {text: self.formatTimestamp(row.first, true)});
            if (row.firstSeen && row.firstSeen < row.first) {
                firstCell.appendChild(h('span', {
                    class: 'ps-dash__cell-hint',
                    text: fmt(t.firstSeen, {d: self.formatTimestamp(row.firstSeen, false)})
                }));
            }
            self.tableBody.appendChild(h('tr', {}, [
                h('th', {scope: 'row'}, [self.termButton(row.keyword)]),
                h('td', {class: 'is-num', text: self.formatNumber(row.searches)}),
                h('td', {class: 'is-num', text: self.formatDecimal(row.avg_hits)}),
                h('td', {class: 'is-num', text: self.formatNumber(row.zero)}),
                h('td', {class: 'is-num', text: self.formatNumber(row.clicks)}),
                firstCell,
                h('td', {text: self.formatTimestamp(row.last, true)})
            ]));
        });

        this.tableInfo.textContent = fmt(t.pageInfo, {
            p: this.formatNumber(data.page),
            n: this.formatNumber(data.pages),
            t: this.formatNumber(data.total)
        });
        this.prevButton.disabled = data.page <= 1;
        this.nextButton.disabled = data.page >= data.pages;
        this.tableSource.textContent = '';
    };

    // ---------------------------------------------------------------- detail

    Dashboard.prototype.openTerm = function (keyword) {
        var t = this.t;
        var self = this;
        this.lastFocus = document.activeElement;
        this.dialogTitle.textContent = fmt(t.detailOf, {k: keyword});
        this.dialogKpis.textContent = '';
        this.dialogStatus.textContent = '';
        if (this.charts.detail) {
            this.charts.detail.destroy();
            delete this.charts.detail;
        }
        if (!this.dialog.open) {
            this.dialog.showModal();
            this.dialog.addEventListener('close', function () {
                if (self.lastFocus) {
                    self.lastFocus.focus();
                }
            }, {once: true});
        }

        this.request('term', this.config.endpoints.term, this.params({keyword: keyword})).then(function (d) {
            var isAll = d.period.preset === 'all';
            var term = d.term;
            var found = term.searches > 0;
            self.dialogStatus.textContent = found ? '' : t.termNoLog;
            [
                isAll ? null : [t.inPeriod, self.formatNumber(term.searches)],
                [t.allTime, self.formatNumber(d.allTime.searches)],
                [t.colAvgHits, found ? self.formatDecimal(term.avg_hits) : '–'],
                [t.zeroRate, found ? self.formatDecimal(term.zero / term.searches * 100) + ' %' : '–'],
                [t.colClicks, self.formatNumber(term.clicks)],
                [t.firstSearch, self.formatTimestamp(d.allTime.first, true)],
                [t.lastSearch, self.formatTimestamp(d.allTime.last, true)]
            ].filter(Boolean).forEach(function (pair) {
                self.dialogKpis.appendChild(h('div', {class: 'ps-dash__kpi'}, [h('h4', {
                    class: 'ps-dash__kpi-label',
                    text: pair[0]
                }), h('p', {class: 'ps-dash__kpi-value', text: pair[1]})]));
            });

            var theme = self.theme();
            var tl = d.timeline;
            self.dialogCanvas.setAttribute('aria-label', fmt(t.chartSummary, {t: t.timeline + ' · ' + keyword}));
            self.drawChart('detail', self.dialogCanvas, {
                type: 'line',
                data: {
                    labels: tl.current.map(function (p) {
                        return self.formatBucket(p.key, tl.granularity, false);
                    }),
                    datasets: [{
                        label: t.searchesUnit,
                        data: tl.current.map(function (p) {
                            return p.value;
                        }),
                        borderColor: theme.accent,
                        backgroundColor: alpha(theme.accent, 0.12),
                        fill: true,
                        tension: 0.3,
                        pointRadius: tl.current.length > 60 ? 0 : 2
                    }]
                },
                options: {
                    interaction: {mode: 'index', intersect: false},
                    scales: {
                        y: {beginAtZero: true, ticks: {precision: 0}},
                        x: {ticks: {maxRotation: 0, autoSkipPadding: 12}}
                    },
                    plugins: {
                        legend: {display: false}, tooltip: {
                            callbacks: {
                                title: function (items) {
                                    return self.formatBucket(tl.current[items[0].dataIndex].key, tl.granularity, true);
                                }
                            }
                        }
                    }
                }
            });
        }).catch(function (error) {
            if (error.name !== 'AbortError') {
                self.dialogStatus.textContent = error.message;
            }
        });
    };

    // ---------------------------------------------------------------- theme

    Dashboard.prototype.observeTheme = function () {
        var self = this;
        this.themeObserver = new MutationObserver(function () {
            if (self.data) {
                self.render();
            }
        });
        this.themeObserver.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['data-color-scheme']
        });
    };

    Dashboard.prototype.destroy = function () {
        var self = this;
        Object.keys(this.charts).forEach(function (key) {
            self.charts[key].destroy();
        });
        Object.keys(this.controllers).forEach(function (key) {
            self.controllers[key].abort();
        });
        this.charts = {};
        if (this.themeObserver) {
            this.themeObserver.disconnect();
        }
    };

    // ---------------------------------------------------------------- mounting

    var instances = [];

    function mountAll() {
        instances = instances.filter(function (instance) {
            if (!document.contains(instance.root)) {
                instance.destroy();
                return false;
            }
            return true;
        });
        document.querySelectorAll('[data-ps-dashboard]').forEach(function (root) {
            if (!root.psDashboard) {
                root.psDashboard = new Dashboard(root);
                instances.push(root.psDashboard);
            }
        });
    }

    window.PsSearchDashboard = {mountAll: mountAll, Dashboard: Dashboard};

    // Contao back end uses Turbo: mount on every visit, clean up before the page is replaced
    document.addEventListener('turbo:load', mountAll);
    document.addEventListener('turbo:before-render', function () {
        instances.forEach(function (instance) {
            instance.destroy();
        });
        instances = [];
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mountAll);
    } else {
        mountAll();
    }
})();
