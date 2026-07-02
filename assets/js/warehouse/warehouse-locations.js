/**
 * Warehouse storage locations — zones, aisles, racks, bins
 */
document.addEventListener('DOMContentLoaded', () => {
    const tableWrap = document.getElementById('whLocTableWrap');
    if (!tableWrap) return;

    const { t, esc, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv } = WarehouseUI;
    const canManage = !!window.WH_PAGE?.canManage;
    const VIEW_KEY = 'wh_loc_view_mode';

    const STATUS_KEYS = {
        active: 'wms_status_active',
        inactive: 'wms_status_inactive',
        full: 'wms_status_full',
    };

    const state = {
        page: 1,
        limit: 50,
        total: 0,
        items: [],
        summary: null,
        breakdown: [],
        warehouses: [],
        searchTimer: null,
        view: localStorage.getItem(VIEW_KEY) || 'table',
    };

    const els = {
        warehouse: document.getElementById('whLocWarehouse'),
        search: document.getElementById('whLocSearch'),
        status: document.getElementById('whLocStatus'),
        zone: document.getElementById('whLocZoneFilter'),
        refresh: document.getElementById('whLocRefreshBtn'),
        exportBtn: document.getElementById('whLocExportBtn'),
        newBtn: document.getElementById('whLocNewBtn'),
        heroMeta: document.getElementById('whLocHeroMeta'),
        statTotal: document.getElementById('whLocStatTotal'),
        statActive: document.getElementById('whLocStatActive'),
        statFull: document.getElementById('whLocStatFull'),
        statCapacity: document.getElementById('whLocStatCapacity'),
        statZones: document.getElementById('whLocStatZones'),
        zonePanel: document.getElementById('whLocZonePanel'),
        zoneChips: document.getElementById('whLocZoneChips'),
        loading: document.getElementById('whLocLoading'),
        empty: document.getElementById('whLocEmpty'),
        cardsWrap: document.getElementById('whLocCardsWrap'),
        pagination: document.getElementById('whLocPagination'),
        prev: document.getElementById('whLocPrev'),
        next: document.getElementById('whLocNext'),
        pageMeta: document.getElementById('whLocPageMeta'),
        viewTable: document.getElementById('whLocViewTable'),
        viewCards: document.getElementById('whLocViewCards'),
        modal: document.getElementById('whLocModal'),
        modalClose: document.getElementById('whLocModalClose'),
        form: document.getElementById('whLocForm'),
        formCancel: document.getElementById('whLocFormCancel'),
        formWarehouse: document.getElementById('whLocFormWarehouse'),
        codePreview: document.getElementById('whLocCodePreview'),
    };

    function statusLabel(status) {
        return t(STATUS_KEYS[status] || status) || status || '—';
    }

    function statusBadge(status) {
        const cls = status === 'active' ? 'ok' : (status === 'full' ? 'warn' : 'off');
        return `<span class="cr-badge cr-badge--${cls}">${esc(statusLabel(status))}</span>`;
    }

    function setStatsLoading(loading) {
        document.querySelectorAll('.wh-loc-stat__value').forEach((el) => el.classList.toggle('is-loading', loading));
    }

    function renderStats(summary) {
        const s = summary || {};
        if (els.statTotal) els.statTotal.textContent = String(s.total ?? 0);
        if (els.statActive) els.statActive.textContent = String(s.active ?? 0);
        if (els.statFull) els.statFull.textContent = String(s.full ?? 0);
        if (els.statCapacity) els.statCapacity.textContent = Number(s.capacity_total ?? 0).toLocaleString();
        if (els.statZones) els.statZones.textContent = String(s.zones ?? 0);
        setStatsLoading(false);
    }

    function renderZoneBreakdown(breakdown) {
        if (!els.zonePanel || !els.zoneChips) return;
        const list = breakdown || [];
        if (!list.length) {
            els.zonePanel.hidden = true;
            return;
        }
        els.zonePanel.hidden = false;
        const activeZone = els.zone?.value || 'all';
        els.zoneChips.innerHTML = list.map((z) => {
            const isActive = activeZone === z.zone;
            return `<button type="button" class="wh-loc-zone-chip${isActive ? ' is-active' : ''}" data-zone="${esc(z.zone)}">
                <span>${esc(z.zone)}</span>
                <strong>${z.count}</strong>
            </button>`;
        }).join('');
        els.zoneChips.querySelectorAll('.wh-loc-zone-chip').forEach((btn) => {
            btn.addEventListener('click', () => {
                const zone = btn.dataset.zone || 'all';
                if (els.zone) els.zone.value = zone;
                state.page = 1;
                load();
            });
        });
    }

    function fillZoneFilter(breakdown) {
        if (!els.zone) return;
        const cur = els.zone.value || 'all';
        const opts = [`<option value="all">${esc(t('wh_loc_filter_all_zones'))}</option>`];
        (breakdown || []).forEach((z) => {
            opts.push(`<option value="${esc(z.zone)}"${cur === z.zone ? ' selected' : ''}>${esc(z.zone)} (${z.count})</option>`);
        });
        els.zone.innerHTML = opts.join('');
        if (cur && cur !== 'all' && !(breakdown || []).some((z) => z.zone === cur)) {
            els.zone.value = 'all';
        }
    }

    function placementLabel(row) {
        return [row.zone, row.aisle, row.rack, row.shelf, row.bin].filter(Boolean).join(' · ') || '—';
    }

    function renderTable(items) {
        const wh = els.warehouse?.value?.trim();
        if (!items.length) {
            tableWrap.innerHTML = '';
            if (els.cardsWrap) els.cardsWrap.hidden = true;
            tableWrap.hidden = true;
            if (els.empty) {
                els.empty.hidden = false;
                const msg = els.empty.querySelector('p');
                if (msg) msg.textContent = wh ? t('wh_loc_empty') : t('wh_loc_select_prompt');
            }
            return;
        }
        if (els.empty) els.empty.hidden = true;

        if (state.view === 'cards') {
            tableWrap.hidden = true;
            if (els.cardsWrap) {
                els.cardsWrap.hidden = false;
                els.cardsWrap.innerHTML = items.map((l) => `
<article class="wh-loc-card wh-loc-card--${esc(l.status || 'active')}">
    <header class="wh-loc-card__head">
        <div>
            <code>${esc(l.location_code)}</code>
            <p class="wh-loc-card__place">${esc(placementLabel(l))}</p>
        </div>
        ${statusBadge(l.status)}
    </header>
    <div class="wh-loc-card__metrics">
        <div><span>${esc(t('wms_location_capacity'))}</span><strong>${Number(l.capacity_units || 0).toLocaleString()}</strong></div>
        <div><span>${esc(t('wms_col_zone'))}</span><strong>${esc(l.zone || '—')}</strong></div>
    </div>
</article>`).join('');
            }
            return;
        }

        if (els.cardsWrap) els.cardsWrap.hidden = true;
        tableWrap.hidden = false;
        tableWrap.innerHTML = `<table class="modern-table wh-table">
<thead><tr>
    <th>${esc(t('wms_col_code'))}</th>
    <th>${esc(t('wms_col_zone'))}</th>
    <th>${esc(t('wms_col_aisle'))}</th>
    <th>${esc(t('wms_col_rack'))}</th>
    <th>${esc(t('wms_col_shelf'))}</th>
    <th>${esc(t('wms_col_bin'))}</th>
    <th>${esc(t('wms_location_capacity'))}</th>
    <th>${esc(t('col_status'))}</th>
</tr></thead>
<tbody>${items.map((l) => `<tr>
    <td><strong>${esc(l.location_code)}</strong></td>
    <td>${esc(l.zone || '—')}</td>
    <td>${esc(l.aisle || '—')}</td>
    <td>${esc(l.rack || '—')}</td>
    <td>${esc(l.shelf || '—')}</td>
    <td>${esc(l.bin || '—')}</td>
    <td>${Number(l.capacity_units || 0).toLocaleString()}</td>
    <td>${statusBadge(l.status)}</td>
</tr>`).join('')}</tbody></table>`;
    }

    function renderPagination() {
        if (!els.pagination) return;
        const pages = Math.max(1, Math.ceil(state.total / state.limit));
        const show = state.total > state.limit;
        els.pagination.hidden = !show;
        if (els.prev) els.prev.disabled = state.page <= 1;
        if (els.next) els.next.disabled = state.page >= pages;
        if (els.pageMeta) {
            const from = state.total ? (state.page - 1) * state.limit + 1 : 0;
            const to = Math.min(state.page * state.limit, state.total);
            els.pageMeta.textContent = `${from}–${to} / ${state.total} ${t('records')}`;
        }
    }

    function setViewMode(mode) {
        state.view = mode;
        localStorage.setItem(VIEW_KEY, mode);
        els.viewTable?.classList.toggle('is-active', mode === 'table');
        els.viewCards?.classList.toggle('is-active', mode === 'cards');
        renderTable(state.items);
    }

    function buildParams(forExport = false) {
        const params = {
            limit: forExport ? 10000 : state.limit,
            offset: forExport ? 0 : (state.page - 1) * state.limit,
        };
        const wh = els.warehouse?.value?.trim();
        if (wh) params.warehouse_id = wh;
        const q = els.search?.value?.trim();
        if (q) params.q = q;
        const status = els.status?.value?.trim();
        if (status && status !== 'all') params.status = status;
        const zone = els.zone?.value?.trim();
        if (zone && zone !== 'all') params.zone = zone;
        return params;
    }

    function updateHeroMeta() {
        if (!els.heroMeta) return;
        const whId = els.warehouse?.value?.trim();
        const wh = state.warehouses.find((w) => String(w.id) === String(whId));
        els.heroMeta.textContent = wh
            ? `${wh.name}${wh.warehouse_code ? ` · ${wh.warehouse_code}` : ''}`
            : t('wh_loc_select_prompt');
    }

    async function loadWarehouses() {
        const res = await AdminAPI.getWmsWarehouses({ limit: 200 });
        state.warehouses = res.status === 'success' ? (res.data || []) : [];
        const fill = (selectEl, placeholder) => {
            if (!selectEl) return;
            const cur = selectEl.value || String(window.WH_PAGE?.warehouseId || '');
            selectEl.innerHTML = (placeholder ? `<option value="">${esc(placeholder)}</option>` : '')
                + state.warehouses.map((w) => `<option value="${w.id}">${esc(w.name)}</option>`).join('');
            if (cur && state.warehouses.some((w) => String(w.id) === String(cur))) {
                selectEl.value = cur;
            } else if (!selectEl.value && state.warehouses.length === 1) {
                selectEl.value = String(state.warehouses[0].id);
            }
        };
        fill(els.warehouse, t('wh_select_warehouse'));
        fill(els.formWarehouse, null);
    }

    async function load() {
        hideError();
        const wh = els.warehouse?.value?.trim();
        updateHeroMeta();

        if (!wh) {
            state.items = [];
            state.total = 0;
            state.summary = null;
            state.breakdown = [];
            renderStats({ total: 0, active: 0, full: 0, capacity_total: 0, zones: 0 });
            renderZoneBreakdown([]);
            fillZoneFilter([]);
            renderTable([]);
            renderPagination();
            if (els.loading) els.loading.hidden = true;
            if (els.empty) {
                els.empty.hidden = false;
                els.empty.querySelector('p').textContent = t('wh_loc_select_prompt');
            }
            tableWrap.hidden = true;
            return;
        }

        if (els.loading) els.loading.hidden = false;
        if (els.empty) els.empty.hidden = true;
        setStatsLoading(true);

        try {
            const res = await AdminAPI.getWmsLocations(buildParams());
            if (res.status !== 'success') throw new Error(res.message || t('load_error'));
            setMigrationHint(res.module_ready !== false);
            state.items = res.data || [];
            state.total = res.total ?? state.items.length;
            state.summary = res.summary || null;
            state.breakdown = res.breakdown || [];
            renderStats(state.summary);
            renderZoneBreakdown(state.breakdown);
            fillZoneFilter(state.breakdown);
            renderTable(state.items);
            renderPagination();
            updateLastUpdated();
        } catch (e) {
            showError(e.message || t('load_error'));
            renderTable([]);
        } finally {
            if (els.loading) els.loading.hidden = true;
            setStatsLoading(false);
        }
    }

    function buildCodePreview() {
        const form = els.form;
        if (!form) return 'A';
        const manual = form.location_code?.value?.trim();
        if (manual) return manual;
        const parts = ['zone', 'aisle', 'rack', 'shelf', 'bin']
            .map((name) => form[name]?.value?.trim())
            .filter(Boolean);
        return parts.join('-') || (form.zone?.value?.trim() || 'A');
    }

    function updateCodePreview() {
        if (els.codePreview) els.codePreview.textContent = buildCodePreview();
    }

    function openModal() {
        if (!els.modal) return;
        els.modal.classList.add('is-open');
        els.modal.setAttribute('aria-hidden', 'false');
    }

    function closeModal() {
        if (!els.modal) return;
        els.modal.classList.remove('is-open');
        els.modal.setAttribute('aria-hidden', 'true');
    }

    function openCreateModal() {
        if (!els.form) return;
        els.form.reset();
        const wh = els.warehouse?.value?.trim();
        if (els.formWarehouse && wh) els.formWarehouse.value = wh;
        if (els.form.zone) els.form.zone.value = 'A';
        updateCodePreview();
        openModal();
    }

    async function submitCreate(e) {
        e.preventDefault();
        const form = e.target;
        const payload = {
            warehouse_id: Number(form.warehouse_id?.value),
            zone: form.zone?.value?.trim() || 'A',
            aisle: form.aisle?.value?.trim() || null,
            rack: form.rack?.value?.trim() || null,
            shelf: form.shelf?.value?.trim() || null,
            bin: form.bin?.value?.trim() || null,
            capacity_units: parseInt(form.capacity_units?.value, 10) || 0,
            status: form.status?.value || 'active',
        };
        const code = form.location_code?.value?.trim();
        if (code) payload.location_code = code;

        if (!payload.warehouse_id) {
            showError(t('wh_select_warehouse'));
            return;
        }

        const res = await AdminAPI.createWmsLocation(payload);
        if (res.status !== 'success') {
            showError(res.message || t('error'));
            return;
        }
        closeModal();
        hideError();
        if (els.warehouse && String(els.warehouse.value) !== String(payload.warehouse_id)) {
            els.warehouse.value = String(payload.warehouse_id);
        }
        state.page = 1;
        await load();
    }

    function exportData() {
        if (!state.items.length) return;
        const rows = [
            [t('wms_col_code'), t('wms_col_zone'), t('wms_col_aisle'), t('wms_col_rack'), t('wms_col_shelf'), t('wms_col_bin'), t('wms_location_capacity'), t('col_status')],
            ...state.items.map((l) => [
                l.location_code, l.zone, l.aisle, l.rack, l.shelf, l.bin, l.capacity_units, l.status,
            ]),
        ];
        exportCsv(`warehouse-locations-${new Date().toISOString().slice(0, 10)}.csv`, rows);
    }

    els.refresh?.addEventListener('click', () => { state.page = 1; load(); });
    els.warehouse?.addEventListener('change', () => { state.page = 1; load(); });
    els.status?.addEventListener('change', () => { state.page = 1; load(); });
    els.zone?.addEventListener('change', () => { state.page = 1; load(); });
    els.search?.addEventListener('input', () => {
        clearTimeout(state.searchTimer);
        state.searchTimer = setTimeout(() => { state.page = 1; load(); }, 300);
    });
    els.exportBtn?.addEventListener('click', exportData);
    els.newBtn?.addEventListener('click', openCreateModal);
    els.form?.addEventListener('submit', submitCreate);
    els.formCancel?.addEventListener('click', closeModal);
    els.modalClose?.addEventListener('click', closeModal);
    els.modal?.addEventListener('click', (e) => { if (e.target === els.modal) closeModal(); });
    els.prev?.addEventListener('click', () => { if (state.page > 1) { state.page -= 1; load(); } });
    els.next?.addEventListener('click', () => {
        const pages = Math.ceil(state.total / state.limit);
        if (state.page < pages) { state.page += 1; load(); }
    });
    els.viewTable?.addEventListener('click', () => setViewMode('table'));
    els.viewCards?.addEventListener('click', () => setViewMode('cards'));

    ['zone', 'aisle', 'rack', 'shelf', 'bin', 'location_code'].forEach((name) => {
        els.form?.querySelector(`[name="${name}"]`)?.addEventListener('input', updateCodePreview);
    });

    document.addEventListener('wh:refresh', () => load());

    setViewMode(state.view);
    loadWarehouses().then(() => {
        const urlWh = new URLSearchParams(window.location.search).get('warehouse_id');
        if (urlWh && els.warehouse) els.warehouse.value = urlWh;
        load();
    });
});
