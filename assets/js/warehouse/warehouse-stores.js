/**
 * Warehouse store network — multi-branch enterprise view with WMS linkage
 */
document.addEventListener('DOMContentLoaded', () => {
    const tableWrap = document.getElementById('whStnTableWrap');
    if (!tableWrap) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv } = WarehouseUI;

    const TYPE_KEYS = {
        central: 'wms_wh_type_central',
        regional: 'wms_wh_type_regional',
        store: 'wms_wh_type_store',
        distribution: 'wms_wh_type_distribution',
        cold_storage: 'wms_wh_type_cold_storage',
        temporary: 'wms_wh_type_temporary',
    };

    const VIEW_KEY = 'wh_stn_view_mode';

    const state = {
        page: 1,
        limit: 50,
        total: 0,
        items: [],
        summary: null,
        searchTimer: null,
        view: localStorage.getItem(VIEW_KEY) || 'table',
    };

    const els = {
        loading: document.getElementById('whStnLoading'),
        empty: document.getElementById('whStnEmpty'),
        cardsWrap: document.getElementById('whStnCardsWrap'),
        search: document.getElementById('whStnSearch'),
        status: document.getElementById('whStnStatus'),
        refresh: document.getElementById('whStnRefreshBtn'),
        exportBtn: document.getElementById('whStnExportBtn'),
        heroMeta: document.getElementById('whStnHeroMeta'),
        statBranches: document.getElementById('whStnStatBranches'),
        statActive: document.getElementById('whStnStatActive'),
        statWarehouses: document.getElementById('whStnStatWarehouses'),
        statUnits: document.getElementById('whStnStatUnits'),
        statCurrencies: document.getElementById('whStnStatCurrencies'),
        statCountries: document.getElementById('whStnStatCountries'),
        currencyPanel: document.getElementById('whStnCurrencyPanel'),
        currencyHint: document.getElementById('whStnCurrencyHint'),
        currencyGrid: document.getElementById('whStnCurrencyGrid'),
        pagination: document.getElementById('whStnPagination'),
        prev: document.getElementById('whStnPrev'),
        next: document.getElementById('whStnNext'),
        pageMeta: document.getElementById('whStnPageMeta'),
        viewTable: document.getElementById('whStnViewTable'),
        viewCards: document.getElementById('whStnViewCards'),
        modal: document.getElementById('whStnDetailModal'),
        modalClose: document.getElementById('whStnDetailClose'),
        modalCloseBtn: document.getElementById('whStnDetailCloseBtn'),
        modalTitle: document.getElementById('whStnDetailTitle'),
        modalSubtitle: document.getElementById('whStnDetailSubtitle'),
        modalBody: document.getElementById('whStnDetailBody'),
        modalWhLink: document.getElementById('whStnDetailWhLink'),
    };

    function typeLabel(type) {
        return t(TYPE_KEYS[type] || type) || type || '—';
    }

    function statusBadge(active) {
        const cls = active ? 'ok' : 'off';
        const label = active ? t('wh_stn_active') : t('wh_stn_inactive');
        return `<span class="cr-badge cr-badge--${cls}">${esc(label)}</span>`;
    }

    function setStatsLoading(loading) {
        document.querySelectorAll('.wh-stn-stat__value').forEach((el) => el.classList.toggle('is-loading', loading));
    }

    function renderStats(summary) {
        const s = summary || {};
        if (els.statBranches) els.statBranches.textContent = String(s.store_count ?? 0);
        if (els.statActive) els.statActive.textContent = String(s.active_stores ?? 0);
        if (els.statWarehouses) els.statWarehouses.textContent = String(s.warehouse_count ?? 0);
        if (els.statUnits) els.statUnits.textContent = Number(s.total_units ?? 0).toLocaleString();
        if (els.statCurrencies) els.statCurrencies.textContent = String(s.currency_count ?? 0);
        if (els.statCountries) {
            const countries = s.countries || [];
            els.statCountries.textContent = countries.length ? countries.slice(0, 3).join(', ') + (countries.length > 3 ? '…' : '') : '—';
        }
        setStatsLoading(false);
        renderCurrencyBreakdown(s.by_currency || [], s.currency_count > 1);
    }

    function renderCurrencyBreakdown(rows, multi) {
        if (!els.currencyPanel || !els.currencyGrid) return;
        if (!rows.length) {
            els.currencyPanel.hidden = true;
            return;
        }
        els.currencyPanel.hidden = false;
        if (els.currencyHint) els.currencyHint.hidden = !multi;
        els.currencyGrid.innerHTML = rows.map((r) => `
            <article class="wh-stn-currency-card">
                <header class="wh-stn-currency-card__head">
                    <strong>${esc(r.store_name || '—')}</strong>
                    <span class="wh-stn-currency-card__code">${esc(r.currency || '')}</span>
                </header>
                <p class="wh-stn-currency-card__value">${money(r.stock_value, r.currency)}</p>
                <footer class="wh-stn-currency-card__meta">
                    <span>${Number(r.warehouse_count || 0)} WH</span>
                    ${r.country ? `<span>${esc(r.country)}</span>` : ''}
                </footer>
            </article>`).join('');
    }

    function buildParams(forExport = false) {
        return {
            limit: forExport ? 10000 : state.limit,
            offset: forExport ? 0 : (state.page - 1) * state.limit,
            q: els.search?.value?.trim() || undefined,
            status: els.status?.value !== 'all' ? els.status?.value : undefined,
        };
    }

    function renderTable(items) {
        tableWrap.hidden = state.view !== 'table';
        if (state.view !== 'table') return;
        if (!items.length) {
            tableWrap.innerHTML = '';
            return;
        }
        tableWrap.innerHTML = `<div class="cr-table-wrap"><table class="modern-table wh-stn-table"><thead><tr>
            <th>${esc(t('wh_stn_col_branch'))}</th>
            <th>${esc(t('wh_stn_col_location'))}</th>
            <th>${esc(t('wh_stn_col_currency'))}</th>
            <th>${esc(t('wh_stn_col_warehouses'))}</th>
            <th>${esc(t('wh_stn_col_wms_units'))}</th>
            <th>${esc(t('wh_stn_col_wms_value'))}</th>
            <th>${esc(t('wh_stn_col_staff'))}</th>
            <th>${esc(t('wh_stn_col_products'))}</th>
            <th>${esc(t('wh_stn_col_regions'))}</th>
            <th>${esc(t('col_status'))}</th>
            <th></th>
        </tr></thead><tbody>${items.map((s) => `
            <tr>
                <td><strong>${esc(s.name)}</strong><br><code class="wms-sku">${esc(s.code || '—')}</code></td>
                <td class="wh-stn-loc">${esc(s.location || '—')}</td>
                <td><span class="wh-stn-currency-tag">${esc(s.currency || '—')}</span></td>
                <td>${Number(s.warehouse_count || 0)} <span class="wh-stn-muted">(${Number(s.active_warehouse_count || 0)} ${esc(t('wh_stn_wh_active'))})</span></td>
                <td>${Number(s.warehouse_units || 0).toLocaleString()}</td>
                <td>${money(s.warehouse_value, s.currency)}</td>
                <td>${Number(s.staff_count || 0).toLocaleString()}</td>
                <td>${Number(s.product_count || 0).toLocaleString()}</td>
                <td class="wh-stn-regions">${esc((s.countries || []).join(', ') || '—')}</td>
                <td>${statusBadge(s.is_active !== false)}</td>
                <td><button type="button" class="wh-btn wh-btn--ghost wh-btn--sm" data-store-id="${s.id}">${esc(t('wh_stn_details'))}</button></td>
            </tr>`).join('')}</tbody></table></div>`;
        tableWrap.querySelectorAll('[data-store-id]').forEach((btn) => {
            btn.addEventListener('click', () => openDetail(Number(btn.dataset.storeId)));
        });
    }

    function renderCards(items) {
        if (!els.cardsWrap) return;
        els.cardsWrap.hidden = state.view !== 'cards';
        if (state.view !== 'cards') return;
        if (!items.length) {
            els.cardsWrap.innerHTML = '';
            return;
        }
        els.cardsWrap.innerHTML = items.map((s) => `
            <article class="wh-stn-card ${s.is_active === false ? 'wh-stn-card--inactive' : ''}">
                <header class="wh-stn-card__head">
                    <div>
                        <h4>${esc(s.name)}</h4>
                        <code>${esc(s.code || '')}</code>
                    </div>
                    ${statusBadge(s.is_active !== false)}
                </header>
                <p class="wh-stn-card__loc">${esc(s.location || '—')}</p>
                <div class="wh-stn-card__metrics">
                    <div><span>${esc(t('wh_stn_col_warehouses'))}</span><strong>${Number(s.warehouse_count || 0)}</strong></div>
                    <div><span>${esc(t('wh_stn_col_wms_units'))}</span><strong>${Number(s.warehouse_units || 0).toLocaleString()}</strong></div>
                    <div><span>${esc(t('wh_stn_col_wms_value'))}</span><strong>${money(s.warehouse_value, s.currency)}</strong></div>
                    <div><span>${esc(t('wh_stn_col_currency'))}</span><strong>${esc(s.currency || '—')}</strong></div>
                </div>
                ${(s.countries || []).length ? `<p class="wh-stn-card__regions">${esc(s.countries.join(' · '))}</p>` : ''}
                <footer class="wh-stn-card__foot">
                    <span>${Number(s.staff_count || 0)} ${esc(t('wh_stn_col_staff'))} · ${Number(s.product_count || 0)} ${esc(t('wh_stn_col_products'))}</span>
                    <button type="button" class="wh-btn wh-btn--ghost wh-btn--sm" data-store-id="${s.id}">${esc(t('wh_stn_details'))}</button>
                </footer>
            </article>`).join('');
        els.cardsWrap.querySelectorAll('[data-store-id]').forEach((btn) => {
            btn.addEventListener('click', () => openDetail(Number(btn.dataset.storeId)));
        });
    }

    function renderViews(items) {
        const hasItems = items.length > 0;
        if (els.empty) els.empty.hidden = hasItems;
        renderTable(items);
        renderCards(items);
    }

    function renderPagination() {
        const totalPages = Math.max(1, Math.ceil(state.total / state.limit));
        const show = state.total > state.limit;
        if (els.pagination) els.pagination.hidden = !show;
        if (els.prev) els.prev.disabled = state.page <= 1;
        if (els.next) els.next.disabled = state.page >= totalPages;
        if (els.pageMeta) {
            const from = state.total ? (state.page - 1) * state.limit + 1 : 0;
            const to = Math.min(state.page * state.limit, state.total);
            els.pageMeta.textContent = `${from}–${to} / ${state.total} ${t('records')}`;
        }
    }

    function setViewMode(mode) {
        state.view = mode === 'cards' ? 'cards' : 'table';
        localStorage.setItem(VIEW_KEY, state.view);
        els.viewTable?.classList.toggle('is-active', state.view === 'table');
        els.viewCards?.classList.toggle('is-active', state.view === 'cards');
        renderViews(state.items);
    }

    async function openDetail(storeId) {
        const store = state.items.find((s) => Number(s.id) === storeId);
        if (!store || !els.modal) return;
        els.modalTitle.textContent = store.name || t('wh_stn_details');
        els.modalSubtitle.textContent = [store.code, store.location, store.currency].filter(Boolean).join(' · ');
        if (els.modalWhLink) els.modalWhLink.href = `warehouses.php?store_id=${storeId}`;
        els.modalBody.innerHTML = `<div class="wh-loading">${esc(t('loading'))}</div>`;
        els.modal.classList.add('is-open');
        els.modal.setAttribute('aria-hidden', 'false');
        try {
            const res = await AdminAPI.getWmsStoreNetworkWarehouses(storeId);
            const warehouses = res.data || [];
            const metaHtml = `<dl class="wh-stn-detail-meta">
                <div><dt>${esc(t('wh_stn_col_staff'))}</dt><dd>${Number(store.staff_count || 0).toLocaleString()}</dd></div>
                <div><dt>${esc(t('wh_stn_col_products'))}</dt><dd>${Number(store.product_count || 0).toLocaleString()}</dd></div>
                <div><dt>${esc(t('wh_stn_col_wms_units'))}</dt><dd>${Number(store.warehouse_units || 0).toLocaleString()}</dd></div>
                <div><dt>${esc(t('wh_stn_col_wms_value'))}</dt><dd>${money(store.warehouse_value, store.currency)}</dd></div>
            </dl>`;
            if (!warehouses.length) {
                els.modalBody.innerHTML = metaHtml + `<p class="wh-stn-empty-inline">${esc(t('wh_stn_no_warehouses'))}</p>`;
                return;
            }
            els.modalBody.innerHTML = metaHtml + `<h4 class="wh-stn-detail-heading">${esc(t('wh_stn_linked_wh'))}</h4>
                <div class="cr-table-wrap"><table class="modern-table wh-stn-wh-table"><thead><tr>
                    <th>${esc(t('wms_wh_code'))}</th><th>${esc(t('wms_wh_name'))}</th><th>${esc(t('wms_wh_type'))}</th>
                    <th>${esc(t('wms_col_units'))}</th><th>${esc(t('wms_stat_inv_value'))}</th><th>${esc(t('col_status'))}</th>
                </tr></thead><tbody>${warehouses.map((w) => `
                    <tr>
                        <td><strong>${esc(w.warehouse_code)}</strong></td>
                        <td>${esc(w.name)}${w.city ? `<br><span class="wh-stn-muted">${esc(w.city)}${w.country ? `, ${esc(w.country)}` : ''}</span>` : ''}</td>
                        <td>${esc(typeLabel(w.warehouse_type))}</td>
                        <td>${Number(w.total_units || 0).toLocaleString()}</td>
                        <td>${money(w.stock_value, store.currency)}</td>
                        <td><span class="cr-badge cr-badge--${w.status === 'active' ? 'ok' : 'off'}">${esc(w.status === 'active' ? t('wms_status_active') : t('wms_status_inactive'))}</span></td>
                    </tr>`).join('')}</tbody></table></div>`;
        } catch (err) {
            els.modalBody.innerHTML = `<p class="wh-stn-empty-inline">${esc(err.message || t('load_error'))}</p>`;
        }
    }

    function closeDetail() {
        if (!els.modal) return;
        els.modal.classList.remove('is-open');
        els.modal.setAttribute('aria-hidden', 'true');
    }

    async function load(resetPage = false) {
        if (resetPage) state.page = 1;
        hideError();
        if (els.loading) els.loading.hidden = false;
        setStatsLoading(true);
        try {
            const res = await AdminAPI.getWmsStoreNetwork(buildParams());
            if (res.status !== 'success') throw new Error(res.message || t('load_error'));
            setMigrationHint(res.module_ready !== false);
            state.items = res.data || [];
            state.total = Number(res.total ?? state.items.length);
            state.summary = res.summary || null;
            renderStats(state.summary);
            renderViews(state.items);
            renderPagination();
            if (els.heroMeta) {
                els.heroMeta.textContent = `${state.total} ${t('records')} · ${t('dash_all_stores')}`;
            }
            updateLastUpdated();
        } catch (err) {
            showError(err.message || t('load_error'));
            tableWrap.innerHTML = '';
            if (els.cardsWrap) els.cardsWrap.innerHTML = '';
            if (els.empty) els.empty.hidden = true;
        } finally {
            if (els.loading) els.loading.hidden = true;
        }
    }

    async function exportAll() {
        try {
            const res = await AdminAPI.getWmsStoreNetwork(buildParams(true));
            const items = res.data || [];
            if (!items.length) return;
            const rows = [
                [t('wh_stn_col_branch'), 'Code', t('wh_stn_col_location'), t('wh_stn_col_currency'),
                    t('wh_stn_col_warehouses'), t('wh_stn_col_wms_units'), t('wh_stn_col_wms_value'),
                    t('wh_stn_col_staff'), t('wh_stn_col_products'), t('wh_stn_col_regions'), t('col_status')],
                ...items.map((s) => [
                    s.name, s.code, s.location, s.currency, s.warehouse_count, s.warehouse_units,
                    s.warehouse_value, s.staff_count, s.product_count, (s.countries || []).join('; '),
                    s.is_active !== false ? t('wh_stn_active') : t('wh_stn_inactive'),
                ]),
            ];
            exportCsv(`store-network-${new Date().toISOString().slice(0, 10)}.csv`, rows);
        } catch (err) {
            showError(err.message || t('load_error'));
        }
    }

    els.search?.addEventListener('input', () => {
        clearTimeout(state.searchTimer);
        state.searchTimer = setTimeout(() => load(true), 320);
    });
    els.status?.addEventListener('change', () => load(true));
    els.refresh?.addEventListener('click', () => load());
    els.exportBtn?.addEventListener('click', exportAll);
    els.prev?.addEventListener('click', () => { if (state.page > 1) { state.page -= 1; load(); } });
    els.next?.addEventListener('click', () => {
        if (state.page < Math.ceil(state.total / state.limit)) { state.page += 1; load(); }
    });
    els.viewTable?.addEventListener('click', () => setViewMode('table'));
    els.viewCards?.addEventListener('click', () => setViewMode('cards'));
    els.modalClose?.addEventListener('click', closeDetail);
    els.modalCloseBtn?.addEventListener('click', closeDetail);
    els.modal?.addEventListener('click', (e) => { if (e.target === els.modal) closeDetail(); });
    document.addEventListener('wh:refresh', () => load());

    setViewMode(state.view);
    load();
});
