(function () {
    'use strict';

    if (window.ProSearchLegacy) {
        return;
    }

    var script = document.currentScript;
    var attributeToken = script ? (script.getAttribute('data-request-token') || '') : '';
    var adopted = '';
    var MOVE = ['query', 'search_after'];

    function currentToken() {
        if (adopted) {
            return adopted;
        }
        var field = document.querySelector('input[name="REQUEST_TOKEN"]');
        return (field && field.value) || attributeToken;
    }

    function isSearchEndpoint(url) {
        try {
            var parsed = new URL(url, window.location.href);
            return parsed.origin === window.location.origin && /\/((?:elastic|prosearch)\/search|stat\/click)(\/|$)/.test(parsed.pathname);
        } catch (e) {
            return false;
        }
    }

    // removes query/search_after from the URL, returns [url, fields]
    function extractFields(url) {
        var parsed = new URL(url, window.location.href);
        var fields = {};
        MOVE.forEach(function (key) {
            if (parsed.searchParams.has(key)) {
                fields[key] = parsed.searchParams.get(key);
                parsed.searchParams.delete(key);
            }
        });
        return [parsed.pathname + parsed.search, fields];
    }

    // adds fields to a request body; returns null if the body type is not supported
    function addFields(body, fields) {
        var keys = Object.keys(fields);
        if (!keys.length) {
            return body;
        }
        if (body === undefined || body === null || '' === body) {
            return new URLSearchParams(fields).toString();
        }
        if (window.FormData && body instanceof FormData) {
            keys.forEach(function (key) { body.append(key, fields[key]); });
            return body;
        }
        if (body instanceof URLSearchParams) {
            keys.forEach(function (key) { body.append(key, fields[key]); });
            return body;
        }
        if ('string' === typeof body) {
            var trimmed = body.trim();
            if ('{' === trimmed.charAt(0)) {
                try {
                    var json = JSON.parse(trimmed);
                    keys.forEach(function (key) { json[key] = fields[key]; });
                    return JSON.stringify(json);
                } catch (e) {
                    return null;
                }
            }
            return body + (body ? '&' : '') + new URLSearchParams(fields).toString();
        }
        if ('object' === typeof body) {
            keys.forEach(function (key) { body[key] = fields[key]; });
            return body;
        }
        return null;
    }

    function adoptToken(data) {
        if ('string' === typeof data) {
            try {
                data = JSON.parse(data);
            } catch (e) {
                return false;
            }
        }
        if (data && 'object' === typeof data && data.requestToken) {
            adopted = data.requestToken;
            // keep the forms of the page in sync
            Array.prototype.forEach.call(document.querySelectorAll('input[name="REQUEST_TOKEN"]'), function (field) {
                field.value = adopted;
            });
            return true;
        }
        return false;
    }

    window.ProSearchLegacy = {
        getToken: currentToken
    };

    // vue-resource (this.$http.post) of the old Vue templates: token, body fields and one retry
    if (window.Vue && window.Vue.http) {
        window.Vue.http.interceptors.push(function (request) {
            if (!isSearchEndpoint(request.url)) {
                return;
            }

            var extracted = extractFields(request.url);
            var body = addFields(request.body, extracted[1]);
            if (null !== body) {
                request.url = extracted[0];
                request.body = body;
            }

            request.headers.set('X-CSRF-Token', currentToken());
            request.headers.set('X-Requested-With', 'XMLHttpRequest');

            return function (response) {
                if (adoptToken(response.body) && 403 === response.status && !request.proSearchRetried) {
                    request.proSearchRetried = true;
                    request.headers.set('X-CSRF-Token', currentToken());
                    if (request.body && 'object' === typeof request.body && !(window.FormData && request.body instanceof FormData) && 'REQUEST_TOKEN' in request.body) {
                        request.body.REQUEST_TOKEN = currentToken();
                    }
                    return window.Vue.http(request);
                }

                return response;
            };
        });
    }

    // plain XMLHttpRequest (also vue-resource if it was loaded after this script): token header only
    if (window.XMLHttpRequest) {
        var proto = window.XMLHttpRequest.prototype;
        var nativeOpen = proto.open;
        var nativeSetHeader = proto.setRequestHeader;
        var nativeSend = proto.send;

        proto.open = function (method, url) {
            this.proSearch = isSearchEndpoint(String(url));
            this.proSearchHeaders = {};
            return nativeOpen.apply(this, arguments);
        };

        proto.setRequestHeader = function (name, value) {
            if (this.proSearchHeaders) {
                this.proSearchHeaders[String(name).toLowerCase()] = true;
            }
            return nativeSetHeader.apply(this, arguments);
        };

        proto.send = function () {
            if (this.proSearch) {
                var xhr = this;
                if (!this.proSearchHeaders['x-csrf-token']) {
                    nativeSetHeader.call(this, 'X-CSRF-Token', currentToken());
                }
                if (!this.proSearchHeaders['x-requested-with']) {
                    nativeSetHeader.call(this, 'X-Requested-With', 'XMLHttpRequest');
                }
                this.addEventListener('load', function () {
                    try {
                        adoptToken(xhr.responseType && 'text' !== xhr.responseType ? xhr.response : xhr.responseText);
                    } catch (e) {
                        // ignore
                    }
                });
            }
            return nativeSend.apply(this, arguments);
        };
    }

    // fetch() of custom templates (e.g. autocompletion): token, body fields and one retry
    if (window.fetch) {
        var nativeFetch = window.fetch.bind(window);

        window.fetch = function (input, init) {
            var url = 'string' === typeof input ? input : (input instanceof URL ? input.href : null);

            if (!url || !isSearchEndpoint(url)) {
                return nativeFetch(input, init);
            }

            init = Object.assign({}, init || {});
            var extracted = extractFields(url);
            var body = addFields(init.body, extracted[1]);
            if (null !== body) {
                url = extracted[0];
                init.body = body;
            }

            var send = function () {
                var headers = new Headers(init.headers || {});
                headers.set('X-CSRF-Token', currentToken());
                headers.set('X-Requested-With', 'XMLHttpRequest');
                return nativeFetch(url, Object.assign({}, init, {method: 'POST', credentials: 'same-origin', headers: headers}));
            };

            return send().then(function (response) {
                return response.clone().json().catch(function () {
                    return null;
                }).then(function (data) {
                    if (adoptToken(data) && 403 === response.status) {
                        return send();
                    }
                    return response;
                });
            });
        };
    }
})();
