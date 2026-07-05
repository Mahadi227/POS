(function () {
    'use strict';

    const cfg = window.PLATFORM_CONFIG || {};
    const { apiGet, t, setLastUpdated } = window.PlatformAPI || {};

    let debounceTimer = null;

    const CATEGORY_I18N = {
        payments: 'plat_market_cat_payments',
        developer: 'plat_market_cat_developer',
        branding: 'plat_market_cat_branding',
        analytics: 'plat_market_cat_analytics',
        shipping: 'plat_market_cat_shipping',
        other: 'plat_market_cat_other',
    };

    const PRICING_I18N = {
        free: 'plat_market_pricing_free',
        paid: 'plat_market_pricing_paid',
        contact: 'plat_market_pricing_contact',
    };

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    }

    function catLabel(cat) {
        const key = CATEGORY_I18N[cat];
        return key ? t(key) : cat;
    }

    function pricingLabel(pricing) {
        const key = PRICING_I18N[pricing];
        return key ? t(key) : pricing;
    }

    function resolveHref(url) {
        if (!url) return null;
        if (/^https?:\/\//i.test(url)) return url;
        return url.replace(/^\.\.\//, '../../');
    }

    function showError(msg) {
        const el = document.getElementById('platMarketError');
        const text = document.getElementById('platMarketErrorText');
        if (!el || !text) return;
        text.textContent = msg || t('plat_market_load_error');
        el.hidden = false;
    }

    function hideError() {
        const el = document.getElementById('platMarketError');
        if (el) el.hidden = true;
    }

    function setKpiLoading(loading) {
        document.querySelectorAll('#platMarketKpiGrid .plat-kpi-card').forEach((card) => {
            card.classList.toggle('is-loading', loading);
        });
    }

    function updateCount(n) {
        const el = document.getElementById('platMarketCount');
        if (!el) return;
        const template = t('plat_market_count');
        el.textContent = template.includes('%d') ? template.replace('%d', String(n)) : `${n}`;
    }

    function updateClearButton() {
        const search = document.getElementById('platMarketSearch')?.value || '';
        const cat = document.getElementById('platMarketCategoryFilter')?.value || '';
        const status = document.getElementById('platMarketStatusFilter')?.value || '';
        const btn = document.getElementById('platMarketClearFilters');
        if (btn) btn.hidden = !search && !cat && !status;
    }

    function listQuery() {
        const params = new URLSearchParams();
        const q = document.getElementById('platMarketSearch')?.value?.trim();
        const category = document.getElementById('platMarketCategoryFilter')?.value;
        const status = document.getElementById('platMarketStatusFilter')?.value;
        if (q) params.set('q', q);
        if (category) params.set('category', category);
        if (status) params.set('status', status);
        params.set('per_page', '50');
        const qs = params.toString();
        return qs ? `marketplace/catalog?${qs}` : 'marketplace/catalog?per_page=50';
    }

    function renderAppCard(app) {
        const official = app.is_official ? `<span class="plat-market-official">${esc(t('plat_market_official'))}</span>` : '';
        const pricingCls = `plat-market-tag--pricing-${esc(app.pricing || 'free')}`;
        const deprecatedCls = app.status === 'deprecated' ? ' is-deprecated' : '';
        const modules = (app.modules_required || []).map((m) =>
            `<span class="plat-market-module-chip">${esc(m)}</span>`
        ).join('');
        const docsHref = resolveHref(app.docs_url);
        const siteHref = resolveHref(app.website_url);

        return `
            <article class="plat-market-card${deprecatedCls}">
                <div class="plat-market-card__head">
                    <div class="plat-market-card__icon" aria-hidden="true">
                        <span class="material-icons-round">${esc(app.icon || 'extension')}</span>
                    </div>
                    <div>
                        <div class="plat-market-card__title-row">
                            <h3 class="plat-market-card__title">${esc(app.name)}</h3>
                            ${official}
                        </div>
                        <p class="plat-market-card__vendor">${esc(app.vendor || '')}</p>
                    </div>
                </div>
                <p class="plat-market-card__desc">${esc(app.short_description || app.description || '')}</p>
                <div class="plat-market-card__meta">
                    <span class="plat-market-tag">${esc(catLabel(app.category))}</span>
                    <span class="plat-market-tag ${pricingCls}">${esc(pricingLabel(app.pricing))}</span>
                </div>
                ${modules ? `<div class="plat-market-modules" aria-label="${esc(t('plat_market_modules_req'))}">${modules}</div>` : ''}
                <div class="plat-market-card__foot">
                    <span class="plat-market-installs">
                        ${esc(t('plat_market_col_installs'))}:
                        <strong>${esc(String(app.install_count ?? 0))}</strong>
                    </span>
                    <div class="plat-market-links">
                        ${docsHref ? `<a class="plat-market-link" href="${esc(docsHref)}" target="_blank" rel="noopener">
                            ${esc(t('plat_market_view_docs'))}
                            <span class="material-icons-round" aria-hidden="true">open_in_new</span>
                        </a>` : ''}
                        ${siteHref ? `<a class="plat-market-link" href="${esc(siteHref)}" target="_blank" rel="noopener">
                            ${esc(t('plat_market_view_site'))}
                        </a>` : ''}
                    </div>
                </div>
            </article>
        `;
    }

    async function loadStats() {
        const res = await apiGet('marketplace/stats');
        if (res.status !== 'success') return;
        const stats = res.data || {};
        document.getElementById('platMarketKpiTotal').textContent = String(stats.total ?? 0);
        document.getElementById('platMarketKpiPublished').textContent = String(stats.published ?? 0);
        document.getElementById('platMarketKpiOfficial').textContent = String(stats.official ?? 0);
        document.getElementById('platMarketKpiInstalls').textContent = String(stats.installs ?? 0);
    }

    async function loadCatalog() {
        hideError();
        const grid = document.getElementById('platMarketGrid');
        const empty = document.getElementById('platMarketEmpty');
        const wrap = document.querySelector('.plat-market-panel');
        if (!grid) return;

        grid.innerHTML = `
            <div class="plat-market-loading">
                <span class="plat-market-spinner" aria-hidden="true"></span>
                ${esc(t('loading'))}…
            </div>
        `;
        if (empty) empty.hidden = true;

        const res = await apiGet(listQuery());
        if (res.status !== 'success') {
            throw new Error(res.message || t('plat_market_load_error'));
        }

        const apps = res.data || [];
        updateCount(apps.length);

        if (!apps.length) {
            grid.innerHTML = '';
            if (empty) empty.hidden = false;
            return;
        }

        grid.innerHTML = apps.map(renderAppCard).join('');
    }

    async function refresh() {
        setKpiLoading(true);
        try {
            await Promise.all([loadStats(), loadCatalog()]);
            setLastUpdated?.();
        } catch (e) {
            showError(e.message || t('load_error'));
        } finally {
            setKpiLoading(false);
        }
    }

    document.getElementById('platMarketSearch')?.addEventListener('input', () => {
        updateClearButton();
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => loadCatalog().catch((e) => showError(e.message)), 300);
    });

    ['platMarketCategoryFilter', 'platMarketStatusFilter'].forEach((id) => {
        document.getElementById(id)?.addEventListener('change', () => {
            updateClearButton();
            loadCatalog().catch((e) => showError(e.message));
        });
    });

    document.getElementById('platMarketClearFilters')?.addEventListener('click', () => {
        const search = document.getElementById('platMarketSearch');
        const cat = document.getElementById('platMarketCategoryFilter');
        const status = document.getElementById('platMarketStatusFilter');
        if (search) search.value = '';
        if (cat) cat.value = '';
        if (status) status.value = '';
        updateClearButton();
        loadCatalog().catch((e) => showError(e.message));
    });

    document.addEventListener('DOMContentLoaded', refresh);
    document.addEventListener('plat:refresh', refresh);
})();
