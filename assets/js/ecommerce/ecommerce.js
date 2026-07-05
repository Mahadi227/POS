(function () {
    'use strict';

    var cfg = window.ECOM || {};
    var i18n = cfg.i18n || {};
    var searchCfg = cfg.search || {};

    function t(key, fallback) {
        return i18n[key] || fallback || key;
    }

    function apiUrl(route) {
        var url = (cfg.apiBase || 'api/').replace(/\/?$/, '/');
        url += (url.indexOf('?') >= 0 ? '&' : '?') + 'route=' + encodeURIComponent(route);
        if (cfg.tenantSlug && url.indexOf((cfg.tenantParam || 'tenant') + '=') < 0) {
            url += '&' + (cfg.tenantParam || 'tenant') + '=' + encodeURIComponent(cfg.tenantSlug);
        }
        if (cfg.storeId && url.indexOf('store_id=') < 0) {
            url += '&store_id=' + encodeURIComponent(cfg.storeId);
        }
        return url;
    }

    function apiGet(route, params) {
        var url = apiUrl(route);
        Object.keys(params || {}).forEach(function (key) {
            if (params[key] !== undefined && params[key] !== null && params[key] !== '') {
                url += '&' + encodeURIComponent(key) + '=' + encodeURIComponent(params[key]);
            }
        });
        return fetch(url, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        }).then(function (r) { return r.json(); });
    }

    function post(route, body) {
        return fetch(apiUrl(route), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(body || {}),
        }).then(function (r) { return r.json(); });
    }

    function toast(msg) {
        var el = document.getElementById('ecom-toast');
        if (!el) {
            el = document.createElement('div');
            el.id = 'ecom-toast';
            el.className = 'ecom-toast';
            el.setAttribute('role', 'status');
            document.body.appendChild(el);
        }
        el.textContent = msg;
        el.classList.add('is-visible');
        clearTimeout(el._t);
        el._t = setTimeout(function () { el.classList.remove('is-visible'); }, 2600);
    }

    function setBadge(id, n) {
        var el = document.getElementById(id);
        if (el) el.textContent = String(n);
    }

    function escapeHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function highlightQuery(text, query) {
        var safe = escapeHtml(text);
        var q = String(query || '').trim();
        if (q.length < 2) return safe;
        var re = new RegExp('(' + q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'ig');
        return safe.replace(re, '<mark>$1</mark>');
    }

    function productHref(slug) {
        var base = searchCfg.productUrl || 'products/view.php';
        var join = base.indexOf('?') >= 0 ? '&' : '?';
        return base + join + 'slug=' + encodeURIComponent(slug);
    }

    function searchPageHref(query) {
        var base = searchCfg.pageUrl || 'search/';
        var join = base.indexOf('?') >= 0 ? '&' : '?';
        return base + join + 'q=' + encodeURIComponent(query);
    }

    function debounce(fn, ms) {
        var timer;
        return function () {
            var args = arguments;
            var ctx = this;
            clearTimeout(timer);
            timer = setTimeout(function () { fn.apply(ctx, args); }, ms);
        };
    }

    function renderSuggestionItem(item, query, active) {
        var img = item.image_url
            ? '<img src="' + escapeHtml(item.image_url) + '" alt="" class="ecom-search-item__img">'
            : '<span class="ecom-search-item__placeholder"><span class="material-icons-round">inventory_2</span></span>';
        var cat = item.category_name
            ? '<span class="ecom-search-item__cat">' + escapeHtml(item.category_name) + '</span>'
            : '';
        var sku = item.sku
            ? '<span class="ecom-search-item__sku">' + highlightQuery(item.sku, query) + '</span>'
            : '';
        var stock = item.stock_quantity > 0
            ? t('search_in_stock', ':qty in stock').replace(':qty', String(item.stock_quantity))
            : '';

        return '<a href="' + escapeHtml(productHref(item.slug)) + '" class="ecom-search-item' + (active ? ' is-active' : '') + '" role="option" data-ecom-search-item data-slug="' + escapeHtml(item.slug) + '">'
            + '<span class="ecom-search-item__media">' + img + '</span>'
            + '<span class="ecom-search-item__body">'
            + cat
            + '<span class="ecom-search-item__name">' + highlightQuery(item.name, query) + '</span>'
            + sku
            + (stock ? '<span class="ecom-search-item__stock">' + escapeHtml(stock) + '</span>' : '')
            + '</span>'
            + '<span class="ecom-search-item__price">' + escapeHtml(item.price_label || '') + '</span>'
            + '<button type="button" class="ecom-search-item__add" data-ecom-add="' + item.id + '" aria-label="' + escapeHtml(searchCfg.addCartLabel || 'Add to cart') + '">'
            + '<span class="material-icons-round">add_shopping_cart</span>'
            + '</button>'
            + '</a>';
    }

    function renderLiveCard(item) {
        var img = item.image_url
            ? '<img class="ecom-product-card__img" src="' + escapeHtml(item.image_url) + '" alt="' + escapeHtml(item.name) + '" loading="lazy">'
            : '<span class="ecom-product-card__placeholder"><span class="material-icons-round">inventory_2</span></span>';
        var cat = item.category_name
            ? '<span class="ecom-product-card__cat">' + escapeHtml(item.category_name) + '</span>'
            : '';

        return '<article class="ecom-product-card">'
            + '<a href="' + escapeHtml(productHref(item.slug)) + '" class="ecom-product-card__media">' + img + '</a>'
            + '<div class="ecom-product-card__body">'
            + cat
            + '<h3><a href="' + escapeHtml(productHref(item.slug)) + '">' + escapeHtml(item.name) + '</a></h3>'
            + '<p class="ecom-product-card__price">' + escapeHtml(item.price_label || '') + '</p>'
            + '<div class="ecom-product-card__actions">'
            + '<button type="button" class="ecom-btn ecom-btn--primary ecom-btn--sm" data-ecom-add="' + item.id + '">' + escapeHtml(searchCfg.addCartLabel || 'Add to cart') + '</button>'
            + '<button type="button" class="ecom-btn ecom-btn--ghost ecom-btn--sm" data-ecom-wishlist="' + item.id + '"><span class="material-icons-round">favorite_border</span></button>'
            + '</div></div></article>';
    }

    function initSearchWrap(wrap) {
        var input = wrap.querySelector('[data-ecom-search-input]');
        var panel = wrap.querySelector('[data-ecom-search-panel]');
        var clearBtn = wrap.querySelector('[data-ecom-search-clear]');
        if (!input || !panel) return;

        var minChars = searchCfg.minChars || 2;
        var limit = searchCfg.limit || 8;
        var activeIndex = -1;
        var lastQuery = '';
        var requestId = 0;
        var isPageSearch = !!document.querySelector('[data-ecom-search-live]');

        function setExpanded(open) {
            input.setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        function closePanel() {
            panel.hidden = true;
            panel.innerHTML = '';
            activeIndex = -1;
            setExpanded(false);
        }

        function openPanel() {
            panel.hidden = false;
            setExpanded(true);
        }

        function updateClearBtn() {
            if (!clearBtn) return;
            clearBtn.hidden = input.value.trim() === '';
        }

        function renderPanel(data, query) {
            var items = data.items || [];
            var total = data.total || 0;
            var html = '';

            if (items.length === 0) {
                html = '<p class="ecom-search-panel__empty">' + escapeHtml(t('search_no_results', 'No products found.')) + '</p>';
            } else {
                html += '<div class="ecom-search-panel__list">';
                items.forEach(function (item, idx) {
                    html += renderSuggestionItem(item, query, idx === activeIndex);
                });
                html += '</div>';
                if (total > items.length) {
                    html += '<a href="' + escapeHtml(searchPageHref(query)) + '" class="ecom-search-panel__footer">'
                        + escapeHtml(t('search_view_all', 'View all :count results').replace(':count', String(total)))
                        + '<span class="material-icons-round">arrow_forward</span></a>';
                }
            }

            panel.innerHTML = html;
            openPanel();
        }

        function renderLiveResults(data, query) {
            var live = document.querySelector('[data-ecom-search-live]');
            var results = document.querySelector('[data-ecom-search-results]');
            var meta = document.querySelector('[data-ecom-search-meta]');
            var hint = document.querySelector('[data-ecom-search-hint]');
            if (!live || !results) return;

            var items = data.items || [];
            var total = data.total || 0;

            if (query.length < minChars) {
                live.hidden = true;
                if (hint) hint.hidden = false;
                return;
            }

            live.hidden = false;
            if (hint) hint.hidden = true;

            if (meta) {
                var tpl = searchCfg.metaTemplate || '{count} results for "{q}"';
                meta.textContent = tpl.replace('{count}', String(total)).replace('{q}', query);
            }

            if (items.length === 0) {
                results.innerHTML = '<p class="ecom-search-empty">' + escapeHtml(t('search_no_results', 'No products found.')) + '</p>';
                return;
            }

            var html = '';
            items.forEach(function (item) {
                html += renderLiveCard(item);
            });
            if (total > items.length) {
                html += '<div class="ecom-search-live__more">'
                    + '<a href="' + escapeHtml(searchPageHref(query)) + '" class="ecom-btn ecom-btn--ghost">'
                    + escapeHtml(t('search_view_all', 'View all :count results').replace(':count', String(total)))
                    + '</a></div>';
            }
            results.innerHTML = html;
        }

        function showLoading() {
            panel.innerHTML = '<p class="ecom-search-panel__loading"><span class="ecom-search-spinner"></span>' + escapeHtml(t('search_loading', 'Searching…')) + '</p>';
            openPanel();
        }

        function showMinChars() {
            panel.innerHTML = '<p class="ecom-search-panel__hint">' + escapeHtml(t('search_min_chars', 'Type at least 2 characters')) + '</p>';
            openPanel();
        }

        var runSearch = debounce(function () {
            var query = input.value.trim();
            lastQuery = query;
            updateClearBtn();

            if (query.length === 0) {
                closePanel();
                if (isPageSearch) {
                    var live = document.querySelector('[data-ecom-search-live]');
                    var hint = document.querySelector('[data-ecom-search-hint]');
                    if (live) live.hidden = true;
                    if (hint) hint.hidden = false;
                }
                return;
            }

            if (query.length < minChars) {
                showMinChars();
                return;
            }

            showLoading();
            var currentRequest = ++requestId;

            apiGet('search/suggest', { q: query, limit: isPageSearch ? 48 : limit }).then(function (data) {
                if (currentRequest !== requestId || query !== input.value.trim()) return;
                activeIndex = -1;
                renderPanel(data, query);
                if (isPageSearch) renderLiveResults(data, query);
            }).catch(function () {
                if (currentRequest !== requestId) return;
                panel.innerHTML = '<p class="ecom-search-panel__empty">' + escapeHtml(t('error', 'Something went wrong')) + '</p>';
                openPanel();
            });
        }, searchCfg.debounceMs || 280);

        input.addEventListener('input', runSearch);
        input.addEventListener('focus', function () {
            updateClearBtn();
            if (input.value.trim().length >= minChars) runSearch();
            else if (input.value.trim().length > 0) showMinChars();
        });

        input.addEventListener('keydown', function (e) {
            var options = panel.querySelectorAll('[data-ecom-search-item]');
            if (e.key === 'Escape') {
                closePanel();
                input.blur();
                return;
            }
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (panel.hidden) runSearch();
                if (!options.length) return;
                activeIndex = Math.min(activeIndex + 1, options.length - 1);
                options.forEach(function (el, idx) { el.classList.toggle('is-active', idx === activeIndex); });
                return;
            }
            if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (!options.length) return;
                activeIndex = Math.max(activeIndex - 1, 0);
                options.forEach(function (el, idx) { el.classList.toggle('is-active', idx === activeIndex); });
                return;
            }
            if (e.key === 'Enter') {
                if (activeIndex >= 0 && options[activeIndex]) {
                    e.preventDefault();
                    window.location.href = options[activeIndex].getAttribute('href');
                }
            }
        });

        panel.addEventListener('click', function (e) {
            var addBtn = e.target.closest('[data-ecom-add]');
            if (addBtn) {
                e.preventDefault();
                e.stopPropagation();
                var pid = parseInt(addBtn.getAttribute('data-ecom-add'), 10);
                post('cart/add', { product_id: pid, quantity: 1 }).then(function (res) {
                    if (res.ok) {
                        setBadge('ecom-cart-count', res.count);
                        toast(t('added_cart', 'Added to cart'));
                    }
                }).catch(function () { toast(t('error', 'Error')); });
            }
        });

        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                input.value = '';
                updateClearBtn();
                closePanel();
                input.focus();
                if (isPageSearch) {
                    var live = document.querySelector('[data-ecom-search-live]');
                    var hint = document.querySelector('[data-ecom-search-hint]');
                    if (live) live.hidden = true;
                    if (hint) hint.hidden = false;
                }
            });
        }

        document.addEventListener('click', function (e) {
            if (!wrap.contains(e.target)) closePanel();
        });

        updateClearBtn();
        if (isPageSearch && input.value.trim().length >= minChars) {
            runSearch();
        }
    }

    document.querySelectorAll('[data-ecom-search-wrap]').forEach(initSearchWrap);

    document.addEventListener('click', function (e) {
        var addBtn = e.target.closest('[data-ecom-add]');
        if (addBtn && !e.target.closest('[data-ecom-search-panel]')) {
            e.preventDefault();
            var pid = parseInt(addBtn.getAttribute('data-ecom-add'), 10);
            var qty = 1;
            if (addBtn.hasAttribute('data-ecom-use-qty')) {
                var actions = addBtn.closest('[data-ecom-product-actions]');
                var qtyInput = actions ? actions.querySelector('[data-ecom-qty]') : null;
                if (qtyInput) {
                    qty = parseInt(qtyInput.value, 10);
                    if (Number.isNaN(qty) || qty < 1) qty = 1;
                    var maxQty = parseInt(qtyInput.max || '9999', 10);
                    if (!Number.isNaN(maxQty) && qty > maxQty) qty = maxQty;
                }
            }
            post('cart/add', { product_id: pid, quantity: qty }).then(function (res) {
                if (res.ok) {
                    setBadge('ecom-cart-count', res.count);
                    toast(t('added_cart', 'Added to cart'));
                }
            }).catch(function () { toast(t('error', 'Error')); });
            return;
        }

        var wishBtn = e.target.closest('[data-ecom-wishlist]');
        if (wishBtn) {
            e.preventDefault();
            var wpid = parseInt(wishBtn.getAttribute('data-ecom-wishlist'), 10);
            post('wishlist/toggle', { product_id: wpid }).then(function (res) {
                if (res.ok) setBadge('ecom-wishlist-count', res.count);
            });
        }
    });

    var menuToggle = document.getElementById('ecom-menu-toggle');
    var nav = document.querySelector('.ecom-nav');
    if (menuToggle && nav) {
        menuToggle.addEventListener('click', function () {
            var open = nav.style.display === 'flex';
            nav.style.display = open ? 'none' : 'flex';
            nav.style.flexDirection = 'column';
            nav.style.position = 'absolute';
            nav.style.top = '100%';
            nav.style.left = '0';
            nav.style.right = '0';
            nav.style.background = '#fff';
            nav.style.padding = '1rem';
            nav.style.borderBottom = '1px solid #e2e8f0';
            menuToggle.setAttribute('aria-expanded', open ? 'false' : 'true');
        });
    }
})();
