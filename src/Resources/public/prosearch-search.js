(function () {
    'use strict';

    var token = '';
    var adopted = false;
    var LIVE_DELAY = 500;
    var SUGGEST_DELAY = 300;

    function format(text, values) {
        var i = 0;
        return String(text || '').replace(/%(\d+\$)?s/g, function (match, position) {
            var index = position ? parseInt(position, 10) - 1 : i++;
            return values[index] !== undefined ? values[index] : '';
        });
    }

    function buildBody(data) {
        var body = new URLSearchParams();
        // token first: PHP drops fields beyond max_input_vars from the end
        body.append('REQUEST_TOKEN', token);
        Object.keys(data).forEach(function (key) {
            var value = data[key];
            if (Array.isArray(value)) {
                value.forEach(function (item) {
                    body.append(key + '[]', item);
                });
            } else if (value !== undefined && value !== null) {
                body.append(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value));
            }
        });
        return body;
    }

    // the REQUEST_TOKEN fields are kept fresh by Contao (request token script for cached pages)
    function currentToken() {
        if (!adopted) {
            var field = document.querySelector('[data-prosearch] input[name="REQUEST_TOKEN"]');
            if (field && field.value) {
                token = field.value;
            }
        }
        return token;
    }

    function adoptToken(value) {
        token = value;
        adopted = true;
        // native form submits (results page) use the fresh token as well
        Array.prototype.forEach.call(document.querySelectorAll('[data-prosearch] input[name="REQUEST_TOKEN"]'), function (field) {
            field.value = value;
        });
    }

    function post(url, data, options, retried) {
        options = options || {};
        currentToken();
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            cache: 'no-store',
            keepalive: !!options.keepalive,
            signal: options.signal,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': token
            },
            body: buildBody(data)
        }).then(function (response) {
            return response.json().catch(function () {
                return null;
            }).then(function (json) {
                if (json && json.requestToken) {
                    adoptToken(json.requestToken);
                }
                // expired token (e.g. page from the HTTP cache): repeat once with the fresh one
                if (403 === response.status && !retried && json && json.requestToken) {
                    return post(url, data, options, true);
                }
                if (!response.ok || !json) {
                    throw new Error('ProSearch: HTTP ' + response.status);
                }
                return json;
            });
        });
    }

    function ProSearch(root, config) {
        this.root = root;
        this.config = config;
        this.labels = config.labels || {};
        this.form = document.getElementById(config.formId) || root.querySelector('form');
        this.input = this.form ? this.form.querySelector('input[name="keywords"]') : null;
        this.status = root.querySelector('[data-ps-status]');
        this.panel = root.querySelector('[data-ps-panel]');
        this.content = root.querySelector('[data-ps-content]');
        this.listbox = root.querySelector('[data-ps-listbox]');
        this.icon = root.querySelector('[data-ps-icon]');
        this.spinner = root.querySelector('[data-ps-loading]');
        this.categories = (config.categories || []).slice();
        this.searchAfter = config.searchAfter || '';
        this.loaded = config.loaded || 0;
        this.total = config.total || 0;
        this.request = null;
        this.timer = null;
        this.suggestTimer = null;
        this.options = [];
        this.active = -1;
        this.historyKey = 'prosearch_' + config.module;

        if (!this.form || !this.input) {
            return;
        }

        this.bind();
        this.restore();
    }

    ProSearch.prototype.bind = function () {
        var self = this;

        this.form.addEventListener('submit', function (event) {
            self.onSubmit(event);
        });

        this.input.addEventListener('input', function () {
            self.onInput();
        });

        this.input.addEventListener('keydown', function (event) {
            self.onKeydown(event);
        });

        if (this.content) {
            this.content.addEventListener('click', function (event) {
                var link = event.target.closest('a[href]');
                if (link && self.content.contains(link) && link.closest('.result-hit')) {
                    self.sendStat(link.href);
                }
            });
        }

        var close = this.root.querySelector('[data-ps-close]');
        if (close) {
            close.addEventListener('click', function () {
                self.closePanel(true);
            });
        }

        if ('panel' === this.config.type && this.panel) {
            // non-modal panel: close it as soon as focus or a click leaves the module
            this.root.addEventListener('focusout', function (event) {
                if (event.relatedTarget && !self.root.contains(event.relatedTarget)) {
                    self.closePanel(false);
                }
            });
            document.addEventListener('pointerdown', function (event) {
                if (!self.root.contains(event.target)) {
                    self.closePanel(false);
                }
            });
            this.root.addEventListener('keydown', function (event) {
                if ('Escape' === event.key && !self.isListOpen() && self.isPanelOpen()) {
                    event.preventDefault();
                    self.closePanel(true);
                }
            });
        }

        if (this.listbox) {
            this.setupCombobox();
        }
    };

    /* ---------- form / search ---------- */

    ProSearch.prototype.onSubmit = function (event) {
        var submitter = event.submitter || null;
        var name = submitter ? submitter.getAttribute('name') : '';
        var keywords = this.input.value.trim();

        this.closeList();

        if ('search_after' === name) {
            event.preventDefault();
            this.loadMore();
            return;
        }

        if ('ps_toggle_category' === name) {
            event.preventDefault();
            this.toggleCategory(submitter.value, submitter);
            return;
        }

        if ('didyoumean' === name) {
            event.preventDefault();
            this.input.value = submitter.value;
            this.search({focusInput: true});
            return;
        }

        if (!keywords) {
            event.preventDefault();
            this.input.focus();
            return;
        }

        if (keywords.length < this.config.minLength) {
            event.preventDefault();
            this.announce(this.labels.minLength);
            return;
        }

        if (this.config.nativeSubmit) {
            // POST to the results page (search terms are not part of the URL)
            return;
        }

        event.preventDefault();
        this.search({announceLoading: true});
    };

    ProSearch.prototype.onInput = function () {
        var self = this;
        var keywords = this.input.value.trim();

        if (this.listbox) {
            clearTimeout(this.suggestTimer);
            if (keywords.length < this.config.minLength) {
                this.closeList();
            } else {
                this.suggestTimer = setTimeout(function () {
                    self.suggest(keywords);
                }, SUGGEST_DELAY);
            }
        }

        if (this.config.live) {
            clearTimeout(this.timer);
            if (!keywords) {
                this.abort();
                this.closePanel(false);
                return;
            }
            if (keywords.length < this.config.minLength) {
                return;
            }
            this.timer = setTimeout(function () {
                self.categories = [];
                self.search({});
            }, LIVE_DELAY);
        }
    };

    ProSearch.prototype.abort = function () {
        if (this.request) {
            this.request.abort();
            this.request = null;
        }
    };

    ProSearch.prototype.params = function (merge) {
        return {
            query: this.input.value.trim(),
            module: this.config.module,
            root: this.config.root,
            group: !!this.config.group,
            categories: this.categories,
            source: window.location.origin + window.location.pathname,
            view: true,
            form: this.config.formId,
            id_prefix: this.config.idPrefix,
            heading: this.config.headingLevel,
            loaded: merge ? this.loaded : 0,
            search_after: merge ? this.searchAfter : ''
        };
    };

    ProSearch.prototype.search = function (options) {
        var self = this;
        options = options || {};
        clearTimeout(this.timer);

        if (!this.input.value.trim()) {
            return Promise.resolve();
        }

        this.abort();
        this.request = new AbortController();
        this.setLoading(true);

        if (options.announceLoading) {
            this.announce(this.labels.loading);
        }

        return post(this.config.endpoints.results, this.params(false), {signal: this.request.signal}).then(function (json) {
            self.render(json.view || {}, options);
        }).catch(function (error) {
            self.onError(error);
        }).then(function () {
            self.setLoading(false);
        });
    };

    ProSearch.prototype.render = function (view, options) {
        var keepCategories = options.keepCategories ? this.content.querySelector('[data-ps-categories]') : null;
        var focusKey = options.focusCategory || null;

        this.content.innerHTML = view.html || '';

        if (keepCategories) {
            // keep the full category list while filtering (counts of the unfiltered search)
            var fresh = this.content.querySelector('[data-ps-categories]');
            var results = this.content.querySelector('[data-ps-results]');
            if (fresh) {
                fresh.replaceWith(keepCategories);
            } else if (results) {
                results.parentNode.insertBefore(keepCategories, results);
            } else {
                this.content.appendChild(keepCategories);
            }
            this.syncCategories();
        }

        this.loaded = view.count || 0;
        this.total = view.total || 0;
        this.searchAfter = view.searchAfter || '';
        this.saveHistory();
        this.openPanel();
        this.announce(view.status || '');

        if (focusKey) {
            var button = this.content.querySelector('[data-ps-category="' + cssEscape(focusKey) + '"]');
            if (button) {
                button.focus();
            }
        } else if (options.focusInput) {
            this.input.focus();
        }
    };

    ProSearch.prototype.loadMore = function () {
        var self = this;
        var button = this.content.querySelector('[data-ps-load-more]');

        if (!this.searchAfter) {
            return;
        }

        this.abort();
        this.request = new AbortController();
        this.setLoading(true);
        if (button) {
            button.setAttribute('aria-disabled', 'true');
        }

        post(this.config.endpoints.results, this.params(true), {signal: this.request.signal}).then(function (json) {
            self.merge(json.view || {});
        }).catch(function (error) {
            if (button) {
                button.removeAttribute('aria-disabled');
            }
            self.onError(error);
        }).then(function () {
            self.setLoading(false);
        });
    };

    ProSearch.prototype.merge = function (view) {
        var template = document.createElement('template');
        var results = this.content.querySelector('[data-ps-results]');
        var known = {};
        var added = [];

        template.innerHTML = view.html || '';

        Array.prototype.forEach.call(this.content.querySelectorAll('[data-ps-url]'), function (item) {
            known[item.getAttribute('data-ps-url')] = true;
        });

        Array.prototype.forEach.call(template.content.querySelectorAll('[data-ps-group]'), function (group) {
            var value = group.getAttribute('data-ps-group');
            var existing = results ? results.querySelector('[data-ps-group="' + cssEscape(value) + '"]') : null;
            var list = existing ? existing.querySelector('.result-list') : null;

            Array.prototype.slice.call(group.querySelectorAll('[data-ps-url]')).forEach(function (item) {
                var url = item.getAttribute('data-ps-url');
                if (known[url]) {
                    item.remove();
                    return;
                }
                known[url] = true;
                added.push(item);
                if (list) {
                    list.appendChild(item);
                }
            });

            if (existing) {
                var count = existing.querySelector('.category-count');
                if (count && list) {
                    count.textContent = '(' + list.querySelectorAll('[data-ps-url]').length + ')';
                }
            } else if (results && group.querySelector('[data-ps-url]')) {
                results.appendChild(group);
            }
        });

        var oldMore = this.content.querySelector('[data-ps-more]');
        var newMore = template.content.querySelector('[data-ps-more]');

        this.loaded += added.length;
        this.searchAfter = added.length ? (view.searchAfter || '') : '';

        if (oldMore) {
            if (newMore && added.length) {
                oldMore.replaceWith(newMore);
            } else {
                oldMore.remove();
            }
        }

        this.saveHistory();

        if (added.length) {
            var link = added[0].querySelector('a[href], button, [tabindex]');
            if (link) {
                link.focus();
            }
            this.announce(format(this.labels.moreLoaded, [added.length]));
        } else {
            this.input.focus();
            this.announce(this.labels.noMore);
        }
    };

    ProSearch.prototype.toggleCategory = function (key, button) {
        var index = this.categories.indexOf(key);

        if (-1 === index) {
            this.categories.push(key);
        } else {
            this.categories.splice(index, 1);
        }

        this.syncCategories();
        this.search({keepCategories: true, focusCategory: key || (button && button.value)});
    };

    ProSearch.prototype.syncCategories = function () {
        var self = this;
        Array.prototype.forEach.call(this.content.querySelectorAll('[data-ps-category]'), function (button) {
            var active = -1 !== self.categories.indexOf(button.getAttribute('data-ps-category'));
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
            if (button.parentNode) {
                button.parentNode.classList.toggle('active', active);
            }
        });
    };

    ProSearch.prototype.onError = function (error) {
        if (error && 'AbortError' === error.name) {
            return;
        }
        this.announce(this.labels.error);
        if (window.console) {
            window.console.error(error);
        }
    };

    ProSearch.prototype.setLoading = function (loading) {
        this.root.classList.toggle('loading_results', loading);
        if (this.content) {
            this.content.setAttribute('aria-busy', loading ? 'true' : 'false');
        }
        if (this.icon && this.spinner) {
            this.icon.hidden = loading;
            this.spinner.hidden = !loading;
        }
    };

    ProSearch.prototype.announce = function (message) {
        var status = this.status;
        if (!status) {
            return;
        }
        // clear first so that the same message is announced again
        status.textContent = '';
        if (message) {
            setTimeout(function () {
                status.textContent = message;
            }, 100);
        }
    };

    ProSearch.prototype.sendStat = function (url) {
        post(this.config.endpoints.click, {
            url: url,
            query: this.input.value.trim(),
            categories: this.categories
        }, {keepalive: true}).catch(function () {
            // statistics must never block the navigation
        });
    };

    /* ---------- panel ---------- */

    ProSearch.prototype.isPanelOpen = function () {
        return !!this.panel && !this.panel.hidden;
    };

    ProSearch.prototype.openPanel = function () {
        if (!this.panel) {
            return;
        }
        this.panel.hidden = false;
        if ('panel' === this.config.type) {
            this.root.classList.add('results_open');
            document.body.classList.add('search-panel-open');
        }
    };

    ProSearch.prototype.closePanel = function (restoreFocus) {
        if ('panel' !== this.config.type || !this.isPanelOpen()) {
            return;
        }
        var focusInside = this.panel.contains(document.activeElement);
        this.abort();
        this.panel.hidden = true;
        this.root.classList.remove('results_open');
        document.body.classList.remove('search-panel-open');
        if (restoreFocus || focusInside) {
            this.input.focus();
        }
    };

    /* ---------- history (search terms are kept in history.state, never in the URL) ---------- */

    ProSearch.prototype.saveHistory = function () {
        if (!window.history || !window.history.replaceState) {
            return;
        }
        var state = window.history.state && 'object' === typeof window.history.state ? Object.assign({}, window.history.state) : {};
        state[this.historyKey] = {keywords: this.input.value.trim(), categories: this.categories};
        try {
            // also turns a POST result page into a GET entry: reload/back do not ask to resubmit the form
            window.history.replaceState(state, '', window.location.href);
        } catch (e) {
            // ignore (e.g. sandboxed frames)
        }
    };

    ProSearch.prototype.restore = function () {
        var hasResults = this.content && this.content.querySelector('[data-ps-results]');

        if (hasResults) {
            this.saveHistory();
            this.syncCategories();
            if (this.isPanelOpen()) {
                document.body.classList.add('search-panel-open');
            }
            return;
        }

        var state = window.history.state && window.history.state[this.historyKey];

        if (state && state.keywords && this.content) {
            this.input.value = state.keywords;
            this.categories = Array.isArray(state.categories) ? state.categories : [];
            this.search({});
        }
    };

    /* ---------- combobox (WAI-ARIA APG "combobox with listbox popup") ---------- */

    ProSearch.prototype.setupCombobox = function () {
        var self = this;
        var input = this.input;

        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-expanded', 'false');
        input.setAttribute('aria-controls', this.listbox.id);

        this.listbox.addEventListener('mousedown', function (event) {
            // keep the focus in the input
            event.preventDefault();
        });

        this.listbox.addEventListener('click', function (event) {
            var option = event.target.closest('[role="option"]');
            if (option) {
                self.select(parseInt(option.getAttribute('data-index'), 10));
            }
        });

        input.addEventListener('blur', function () {
            setTimeout(function () {
                if (document.activeElement !== input) {
                    self.closeList();
                }
            }, 150);
        });
    };

    ProSearch.prototype.isListOpen = function () {
        return !!this.listbox && !this.listbox.hidden;
    };

    ProSearch.prototype.suggest = function (keywords) {
        var self = this;
        var terms = 'terms' === this.config.suggest;
        var url = terms ? this.config.endpoints.autocomplete : this.config.endpoints.results;

        if (this.suggestRequest) {
            this.suggestRequest.abort();
        }
        this.suggestRequest = new AbortController();

        post(url, {
            query: keywords,
            module: this.config.module,
            root: this.config.root,
            categories: this.categories,
            group: false,
            source: window.location.origin + window.location.pathname
        }, {signal: this.suggestRequest.signal}).then(function (json) {
            if (self.input.value.trim() !== keywords) {
                return;
            }
            var hits = (json.results && Array.isArray(json.results.hits)) ? json.results.hits : [];
            var items = [];
            hits.forEach(function (hit) {
                if (terms && hit.term) {
                    items.push({label: hit.term, value: hit.term});
                } else if (!terms && hit.title) {
                    items.push({label: textOf(hit.title), url: hit.usedUrl || hit.url});
                }
            });
            self.renderOptions(items.slice(0, 10));
        }).catch(function (error) {
            if (!error || 'AbortError' !== error.name) {
                self.closeList();
            }
        });
    };

    ProSearch.prototype.renderOptions = function (items) {
        var self = this;
        var fragment = document.createDocumentFragment();

        this.options = items;
        this.active = -1;
        this.input.removeAttribute('aria-activedescendant');

        items.forEach(function (item, index) {
            var option = document.createElement('li');
            option.id = self.listbox.id + '_option_' + index;
            option.className = 'ps-combobox__option';
            option.setAttribute('role', 'option');
            option.setAttribute('aria-selected', 'false');
            option.setAttribute('data-index', String(index));
            option.textContent = item.label;
            fragment.appendChild(option);
        });

        this.listbox.replaceChildren(fragment);

        if (!items.length) {
            this.closeList();
            this.announce(this.labels.noSuggestions);
            return;
        }

        this.listbox.hidden = false;
        this.input.setAttribute('aria-expanded', 'true');
        this.announce(1 === items.length ? this.labels.suggestionsOne : format(this.labels.suggestions, [items.length]));
    };

    ProSearch.prototype.closeList = function () {
        if (!this.listbox) {
            return;
        }
        this.listbox.hidden = true;
        this.active = -1;
        this.input.setAttribute('aria-expanded', 'false');
        this.input.removeAttribute('aria-activedescendant');
    };

    ProSearch.prototype.activate = function (index) {
        var options = this.listbox.querySelectorAll('[role="option"]');
        if (!options.length) {
            return;
        }
        index = (index + options.length) % options.length;
        Array.prototype.forEach.call(options, function (option, i) {
            var selected = i === index;
            option.setAttribute('aria-selected', selected ? 'true' : 'false');
            option.classList.toggle('is-active', selected);
        });
        this.active = index;
        this.input.setAttribute('aria-activedescendant', options[index].id);
        if (options[index].scrollIntoView) {
            options[index].scrollIntoView({block: 'nearest'});
        }
    };

    ProSearch.prototype.select = function (index) {
        var item = this.options[index];
        if (!item) {
            return;
        }
        this.closeList();

        if (item.url) {
            // "advanced" suggestions are results: open the page
            this.sendStat(item.url);
            window.location.href = item.url;
            return;
        }

        this.input.value = item.value;
        if (this.form.requestSubmit) {
            this.form.requestSubmit();
        } else {
            this.form.submit();
        }
    };

    ProSearch.prototype.onKeydown = function (event) {
        if (!this.listbox) {
            return;
        }

        switch (event.key) {
            case 'ArrowDown':
                if (!this.options.length) {
                    return;
                }
                event.preventDefault();
                if (!this.isListOpen()) {
                    this.listbox.hidden = false;
                    this.input.setAttribute('aria-expanded', 'true');
                    this.activate(0);
                } else {
                    this.activate(this.active + 1);
                }
                break;
            case 'ArrowUp':
                if (!this.isListOpen()) {
                    return;
                }
                event.preventDefault();
                this.activate(this.active - 1);
                break;
            case 'Enter':
                if (this.isListOpen() && this.active > -1) {
                    event.preventDefault();
                    this.select(this.active);
                }
                break;
            case 'Escape':
                if (this.isListOpen()) {
                    event.preventDefault();
                    event.stopPropagation();
                    this.closeList();
                }
                break;
            case 'Tab':
                this.closeList();
                break;
        }
    };

    /* ---------- helpers ---------- */

    function cssEscape(value) {
        return window.CSS && window.CSS.escape ? window.CSS.escape(value) : String(value).replace(/["\\]/g, '\\$&');
    }

    function textOf(html) {
        var template = document.createElement('template');
        template.innerHTML = String(html);
        return (template.content.textContent || '').replace(/\s+/g, ' ').trim();
    }

    function init() {
        Array.prototype.forEach.call(document.querySelectorAll('[data-prosearch]'), function (root) {
            if (root.prosearch) {
                return;
            }
            var config;
            try {
                config = JSON.parse(root.getAttribute('data-prosearch'));
            } catch (e) {
                return;
            }
            if (!token && config.requestToken) {
                token = config.requestToken;
            }
            root.prosearch = new ProSearch(root, config);
        });
    }

    window.ProSearch = {init: init};

    if ('loading' === document.readyState) {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
