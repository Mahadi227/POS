/**
 * Warehouse network page — enterprise multi-site warehouse management
 */
document.addEventListener('DOMContentLoaded', () => {
    const tableWrap = document.getElementById('whWhTableWrap');
    if (!tableWrap) return;

    const { t, esc, money, pct, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv } = WarehouseUI;
    const canManage = !!window.WH_PAGE?.canManage;

    const TYPE_KEYS = {
        central: 'wms_wh_type_central',
        regional: 'wms_wh_type_regional',
        store: 'wms_wh_type_store',
        distribution: 'wms_wh_type_distribution',
        cold_storage: 'wms_wh_type_cold_storage',
        temporary: 'wms_wh_type_temporary',
    };

    const VIEW_KEY = 'wh_wh_view_mode';

    const state = {
        page: 1,
        limit: 50,
        total: 0,
        items: [],
        summary: null,
        stores: [],
        searchTimer: null,
        view: localStorage.getItem(VIEW_KEY) || 'table',
    };

    const els = {
        loading: document.getElementById('whWhLoading'),
        empty: document.getElementById('whWhEmpty'),
        cardsWrap: document.getElementById('whWhCardsWrap'),
        search: document.getElementById('whWhSearch'),
        status: document.getElementById('whWhStatus'),
        type: document.getElementById('whWhType'),
        store: document.getElementById('whWhStore'),
        refresh: document.getElementById('whWhRefreshBtn'),
        exportBtn: document.getElementById('whWhExportBtn'),
        heroMeta: document.getElementById('whWhHeroMeta'),
        statTotal: document.getElementById('whWhStatTotal'),
        statActive: document.getElementById('whWhStatActive'),
        statInactive: document.getElementById('whWhStatInactive'),
        statUnits: document.getElementById('whWhStatUnits'),
        statValue: document.getElementById('whWhStatValue'),
        statCapacity: document.getElementById('whWhStatCapacity'),
        statCountries: document.getElementById('whWhStatCountries'),
        breakdownRow: document.getElementById('whWhBreakdownRow'),
        typePanel: document.getElementById('whWhTypePanel'),
        typeChips: document.getElementById('whWhTypeChips'),
        storePanel: document.getElementById('whWhStorePanel'),
        storeHint: document.getElementById('whWhStoreHint'),
        storeGrid: document.getElementById('whWhStoreGrid'),
        pagination: document.getElementById('whWhPagination'),
        prev: document.getElementById('whWhPrev'),
        next: document.getElementById('whWhNext'),
        pageMeta: document.getElementById('whWhPageMeta'),
        viewTable: document.getElementById('whWhViewTable'),
        viewCards: document.getElementById('whWhViewCards'),
        modal: document.getElementById('whWhDetailModal'),
        modalClose: document.getElementById('whWhDetailClose'),
        modalCloseBtn: document.getElementById('whWhDetailCloseBtn'),
        modalTitle: document.getElementById('whWhDetailTitle'),
        modalSubtitle: document.getElementById('whWhDetailSubtitle'),
        modalBody: document.getElementById('whWhDetailBody'),
        modalInvLink: document.getElementById('whWhDetailInvLink'),
        modalLocLink: document.getElementById('whWhDetailLocLink'),
        modalEditLink: document.getElementById('whWhDetailEditLink'),
    };

    function typeLabel(type) {
        return t(TYPE_KEYS[type] || type) || type || '—';
    }

    function statusBadge(status) {
        const active = status === 'active';
        return `<span class="cr-badge cr-badge--${active ? 'ok' : 'off'}">${esc(active ? t('wms_status_active') : t('wms_status_inactive'))}</span>`;
    }

    function capacityBar(pct) {
        if (pct == null) return '—';
        const n = Math.min(100, Math.max(0, Number(pct) || 0));
        const cls = n >= 90 ? 'high' : (n >= 70 ? 'mid' : 'ok');
        return `<div class="wh-wh-cap" aria-hidden="true"><div class="wh-wh-cap__bar wh-wh-cap__bar--${cls}" style="width:${n}%"></div></div><span class="wh-wh-cap__pct">${n}%</span>`;
    }

    function locationLine(w) {
        const parts = [w.city, w.country].filter(Boolean);
        return parts.length ? parts.join(', ') : (w.address || '—');
    }

    function setStatsLoading(loading) {
        document.querySelectorAll('.wh-wh-stat__value').forEach((el) => el.classList.toggle('is-loading', loading));
    }

    function renderStats(summary) {
        const s = summary || {};
        if (els.statTotal) els.statTotal.textContent = String(s.total ?? 0);
        if (els.statActive) els.statActive.textContent = String(s.active ?? 0);
        if (els.statInactive) els.statInactive.textContent = String(s.inactive ?? 0);
        if (els.statUnits) els.statUnits.textContent = Number(s.total_units ?? 0).toLocaleString();
        if (els.statValue) els.statValue.textContent = money(s.total_value);
        if (els.statCapacity) els.statCapacity.textContent = s.total_capacity ? pct(s.capacity_used_pct ?? 0) : '—';
        if (els.statCountries) {
            const countries = s.countries || [];
            els.statCountries.textContent = countries.length ? countries.slice(0, 3).join(', ') + (countries.length > 3 ? '…' : '') : '—';
        }
        setStatsLoading(false);
        renderBreakdowns(s);
    }

    function renderBreakdowns(summary) {
        const types = summary.by_type || [];
        const stores = summary.by_store || [];
        const show = types.length || stores.length;
        if (els.breakdownRow) els.breakdownRow.hidden = !show;

        if (els.typePanel && els.typeChips) {
            els.typePanel.hidden = !types.length;
            els.typeChips.innerHTML = types.map((row) => `
                <button type="button" class="wh-wh-type-chip" data-type="${esc(row.type)}">
                    <span>${esc(typeLabel(row.type))}</span>
                    <strong>${row.count}</strong>
                </button>`).join('');
            els.typeChips.querySelectorAll('[data-type]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    if (els.type) els.type.value = btn.dataset.type || 'all';
                    load(true);
                });
            });
        }

        if (els.storePanel && els.storeGrid) {
            els.storePanel.hidden = !stores.length;
            const currencies = new Set(stores.map((r) => r.currency).filter(Boolean));
            if (els.storeHint) els.storeHint.hidden = currencies.size <= 1;
            els.storeGrid.innerHTML = stores.map((r) => `
                <article class="wh-wh-store-card">
                    <header><strong>${esc(r.store_name)}</strong><span>${esc(r.currency || '')}</span></header>
                    <p class="wh-wh-store-card__value">${money(r.stock_value, r.currency)}</p>
                    <footer><span>${r.warehouse_count} WH</span>${r.country ? `<span>${esc(r.country)}</span>` : ''}</footer>
                </article>`).join('');
        }
    }

    function buildParams(forExport = false) {
        return {
            limit: forExport ? 10000 : state.limit,
            offset: forExport ? 0 : (state.page - 1) * state.limit,
            q: els.search?.value?.trim() || undefined,
            status: els.status?.value !== 'all' ? els.status?.value : undefined,
            type: els.type?.value !== 'all' ? els.type?.value : undefined,
            store_id: els.store?.value || undefined,
        };
    }

    function renderTable(items) {
        tableWrap.hidden = state.view !== 'table';
        if (state.view !== 'table') return;
        if (!items.length) {
            tableWrap.innerHTML = '';
            return;
        }
        tableWrap.innerHTML = `<div class="cr-table-wrap"><table class="modern-table wh-wh-table"><thead><tr>
            <th>${esc(t('wh_wh_col_code'))}</th>
            <th>${esc(t('wh_wh_col_name'))}</th>
            <th>${esc(t('wh_wh_col_type'))}</th>
            <th>${esc(t('wh_wh_col_branch'))}</th>
            <th>${esc(t('wh_wh_col_manager'))}</th>
            <th>${esc(t('wh_wh_col_location'))}</th>
            <th>${esc(t('wh_wh_col_units'))}</th>
            <th>${esc(t('wh_wh_col_skus'))}</th>
            <th>${esc(t('wh_wh_col_capacity'))}</th>
            <th>${esc(t('wh_wh_col_value'))}</th>
            <th>${esc(t('col_status'))}</th>
            <th></th>
        </tr></thead><tbody>${items.map((w) => `
            <tr>
                <td><strong>${esc(w.warehouse_code)}</strong></td>
                <td>${esc(w.name)}</td>
                <td>${esc(typeLabel(w.warehouse_type))}</td>
                <td>${esc(w.store_name || '—')}</td>
                <td>${esc(w.manager_name || '—')}</td>
                <td class="wh-wh-loc">${esc(locationLine(w))}</td>
                <td>${Number(w.total_units || 0).toLocaleString()}</td>
                <td>${Number(w.sku_count || 0).toLocaleString()}</td>
                <td class="wh-wh-cap-cell">${capacityBar(w.capacity_pct)}</td>
                <td>${money(w.stock_value, w.store_currency)}</td>
                <td>${statusBadge(w.status)}</td>
                <td><button type="button" class="wh-btn wh-btn--ghost wh-btn--sm" data-wh-id="${w.id}">${esc(t('wh_wh_details'))}</button></td>
            </tr>`).join('')}</tbody></table></div>`;
        tableWrap.querySelectorAll('[data-wh-id]').forEach((btn) => {
            btn.addEventListener('click', () => openDetail(Number(btn.dataset.whId)));
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
        els.cardsWrap.innerHTML = items.map((w) => `
            <article class="wh-wh-card ${w.status !== 'active' ? 'wh-wh-card--inactive' : ''}">
                <header class="wh-wh-card__head">
                    <div>
                        <code>${esc(w.warehouse_code)}</code>
                        <h4>${esc(w.name)}</h4>
                    </div>
                    ${statusBadge(w.status)}
                </header>
                <p class="wh-wh-card__meta">${esc(typeLabel(w.warehouse_type))} · ${esc(w.store_name || '—')}</p>
                <p class="wh-wh-card__loc">${esc(locationLine(w))}</p>
                <div class="wh-wh-card__metrics">
                    <div><span>${esc(t('wh_wh_col_units'))}</span><strong>${Number(w.total_units || 0).toLocaleString()}</strong></div>
                    <div><span>${esc(t('wh_wh_col_value'))}</span><strong>${money(w.stock_value, w.store_currency)}</strong></div>
                    <div><span>${esc(t('wh_wh_col_skus'))}</span><strong>${Number(w.sku_count || 0).toLocaleString()}</strong></div>
                    <div><span>${esc(t('wh_wh_col_locations'))}</span><strong>${Number(w.location_count || 0).toLocaleString()}</strong></div>
                </div>
                <div class="wh-wh-card__cap">${capacityBar(w.capacity_pct)}</div>
                <footer class="wh-wh-card__foot">
                    <span>${esc(w.manager_name || '—')}</span>
                    <button type="button" class="wh-btn wh-btn--ghost wh-btn--sm" data-wh-id="${w.id}">${esc(t('wh_wh_details'))}</button>
                </footer>
            </article>`).join('');
        els.cardsWrap.querySelectorAll('[data-wh-id]').forEach((btn) => {
            btn.addEventListener('click', () => openDetail(Number(btn.dataset.whId)));
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

    async function openDetail(id) {
        const cached = state.items.find((w) => Number(w.id) === id);
        if (!els.modal) return;
        els.modalTitle.textContent = cached?.name || t('wh_wh_details');
        els.modalSubtitle.textContent = cached
            ? [cached.warehouse_code, typeLabel(cached.warehouse_type), cached.store_name].filter(Boolean).join(' · ')
            : '';
        if (els.modalInvLink) els.modalInvLink.href = `../inventory/warehouse_inventory.php?warehouse_id=${id}`;
        if (els.modalLocLink) els.modalLocLink.href = `locations.php?warehouse_id=${id}`;
        if (els.modalEditLink) els.modalEditLink.href = `edit_warehouse.php?id=${id}`;
        els.modalBody.innerHTML = `<div class="wh-loading">${esc(t('loading'))}</div>`;
        els.modal.classList.add('is-open');
        els.modal.setAttribute('aria-hidden', 'false');
        try {
            const res = await AdminAPI.getWmsWarehouse(id);
            const w = res.data || cached;
            if (!w) throw new Error(t('load_error'));
            els.modalTitle.textContent = w.name || t('wh_wh_details');
            const inv = w.inventory || [];
            const locs = w.locations || [];
            const contact = [w.phone, w.email].filter(Boolean).join(' · ');
            els.modalBody.innerHTML = `
                <dl class="wh-wh-detail-meta">
                    <div><dt>${esc(t('wh_wh_col_code'))}</dt><dd>${esc(w.warehouse_code || '—')}</dd></div>
                    <div><dt>${esc(t('wh_wh_col_type'))}</dt><dd>${esc(typeLabel(w.warehouse_type))}</dd></div>
                    <div><dt>${esc(t('wh_wh_col_branch'))}</dt><dd>${esc(w.store_name || '—')}</dd></div>
                    <div><dt>${esc(t('wh_wh_col_manager'))}</dt><dd>${esc(w.manager_name || '—')}</dd></div>
                    <div><dt>${esc(t('wh_wh_col_units'))}</dt><dd>${Number(w.total_units ?? cached?.total_units ?? 0).toLocaleString()}</dd></div>
                    <div><dt>${esc(t('wh_wh_col_value'))}</dt><dd>${money(w.stock_value ?? cached?.stock_value, w.store_currency || cached?.store_currency)}</dd></div>
                    <div><dt>${esc(t('wh_wh_col_locations'))}</dt><dd>${locs.length || Number(cached?.location_count || 0)}</dd></div>
                    <div><dt>${esc(t('wh_wh_col_skus'))}</dt><dd>${inv.length || Number(cached?.sku_count || 0)}</dd></div>
                </dl>
                <p class="wh-wh-detail-loc"><strong>${esc(t('wh_wh_col_location'))}:</strong> ${esc(locationLine(w))}</p>
                ${contact ? `<p class="wh-wh-detail-contact"><strong>${esc(t('wh_wh_contact'))}:</strong> ${esc(contact)}</p>` : ''}
                ${w.notes ? `<p class="wh-wh-detail-notes"><strong>${esc(t('wh_wh_notes'))}:</strong> ${esc(w.notes)}</p>` : ''}
                ${locs.length ? `<h4 class="wh-wh-detail-heading">${esc(t('wh_wh_link_locations'))}</h4>
                    <ul class="wh-wh-loc-list">${locs.slice(0, 8).map((l) => `<li>${esc(l.location_code || l.name || '—')}</li>`).join('')}</ul>` : ''}`;
        } catch (err) {
            els.modalBody.innerHTML = `<p class="wh-wh-empty-inline">${esc(err.message || t('load_error'))}</p>`;
        }
    }

    function closeDetail() {
        if (!els.modal) return;
        els.modal.classList.remove('is-open');
        els.modal.setAttribute('aria-hidden', 'true');
    }

    async function loadStoreOptions() {
        if (!els.store) return;
        try {
            const res = await AdminAPI.listStores();
            state.stores = res.data || [];
            const cur = els.store.value;
            const urlStore = new URLSearchParams(window.location.search).get('store_id');
            els.store.innerHTML = `<option value="">${esc(t('wh_wh_filter_all_stores'))}</option>`
                + state.stores.map((s) => `<option value="${s.id}">${esc(s.name)}</option>`).join('');
            if (urlStore) els.store.value = urlStore;
            else if (cur) els.store.value = cur;
        } catch { /* optional */ }
    }

    async function load(resetPage = false) {
        if (resetPage) state.page = 1;
        hideError();
        if (els.loading) els.loading.hidden = false;
        setStatsLoading(true);
        try {
            const res = await AdminAPI.getWmsWarehouses(buildParams());
            if (res.status !== 'success') throw new Error(res.message || t('load_error'));
            setMigrationHint(res.module_ready !== false);
            state.items = res.data || [];
            state.total = Number(res.total ?? state.items.length);
            state.summary = res.summary || null;
            renderStats(state.summary);
            renderViews(state.items);
            renderPagination();
            if (els.heroMeta) els.heroMeta.textContent = `${state.total} ${t('records')} · ${t('dash_all_stores')}`;
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
            const res = await AdminAPI.getWmsWarehouses(buildParams(true));
            const items = res.data || [];
            if (!items.length) return;
            const rows = [
                [t('wh_wh_col_code'), t('wh_wh_col_name'), t('wh_wh_col_type'), t('wh_wh_col_branch'),
                    t('wh_wh_col_manager'), t('wh_wh_col_location'), t('wh_wh_col_units'), t('wh_wh_col_skus'),
                    t('wh_wh_col_capacity'), t('wh_wh_col_value'), t('col_status')],
                ...items.map((w) => [
                    w.warehouse_code, w.name, typeLabel(w.warehouse_type), w.store_name, w.manager_name,
                    locationLine(w), w.total_units, w.sku_count,
                    w.capacity_pct != null ? `${w.capacity_pct}%` : '', w.stock_value, w.status,
                ]),
            ];
            exportCsv(`warehouses-${new Date().toISOString().slice(0, 10)}.csv`, rows);
        } catch (err) {
            showError(err.message || t('load_error'));
        }
    }

    els.search?.addEventListener('input', () => {
        clearTimeout(state.searchTimer);
        state.searchTimer = setTimeout(() => load(true), 320);
    });
    els.status?.addEventListener('change', () => load(true));
    els.type?.addEventListener('change', () => load(true));
    els.store?.addEventListener('change', () => load(true));
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
    document.addEventListener('store-switched', () => load(true));

    setViewMode(state.view);
    loadStoreOptions().then(() => load());
});
