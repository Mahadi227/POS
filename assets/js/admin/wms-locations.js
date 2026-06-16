document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('wmsLocRoot');
    if (!root) return;

    const { t, esc, hideError, showError, setMigrationHint, updateLastUpdated } = WmsUI;
    const canManage = !!window.ADMIN_PAGE?.canManage;

    let allLocations = [];
    let warehouses = [];

    const STATUS_KEYS = {
        active: 'wms_status_active',
        inactive: 'wms_status_inactive',
        full: 'wms_status_full',
    };

    function statusLabel(status) {
        return t(STATUS_KEYS[status] || status) || status || '—';
    }

    function statusBadge(status) {
        const cls = status === 'active' ? 'ok' : (status === 'full' ? 'warn' : 'off');
        return `<span class="cr-badge cr-badge--${cls}">${esc(statusLabel(status))}</span>`;
    }

    function setStats(items) {
        const active = items.filter((l) => l.status === 'active').length;
        const capacity = items.reduce((sum, l) => sum + Number(l.capacity_units || 0), 0);
        const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
        set('wmsLocTotal', String(items.length));
        set('wmsLocActive', String(active));
        set('wmsLocCapacity', String(capacity));
        document.querySelectorAll('.cr-kpi-card').forEach((c) => c.classList.remove('is-loading'));
    }

    function applyClientFilter(items) {
        const q = (document.getElementById('wmsLocSearch')?.value || '').trim().toLowerCase();
        if (!q) return items;
        return items.filter((l) => {
            const hay = [l.zone, l.aisle, l.rack, l.shelf, l.bin, l.location_code, l.status].join(' ').toLowerCase();
            return hay.includes(q);
        });
    }

    function renderTable(items) {
        const list = applyClientFilter(items);
        if (!list.length) {
            root.innerHTML = `<p class="cr-empty">${esc(t('wms_no_data'))}</p>`;
            return;
        }
        root.innerHTML = `<div class="cr-table-wrap"><table class="modern-table"><thead><tr>
            <th>${esc(t('wms_col_code'))}</th>
            <th>${esc(t('wms_col_zone'))}</th>
            <th>${esc(t('wms_col_aisle'))}</th>
            <th>${esc(t('wms_col_rack'))}</th>
            <th>${esc(t('wms_col_shelf'))}</th>
            <th>${esc(t('wms_col_bin'))}</th>
            <th>${esc(t('wms_location_capacity'))}</th>
            <th>${esc(t('col_status'))}</th>
        </tr></thead><tbody>${list.map((l) => `<tr>
            <td><strong>${esc(l.location_code)}</strong></td>
            <td>${esc(l.zone || '—')}</td>
            <td>${esc(l.aisle || '—')}</td>
            <td>${esc(l.rack || '—')}</td>
            <td>${esc(l.shelf || '—')}</td>
            <td>${esc(l.bin || '—')}</td>
            <td>${Number(l.capacity_units || 0)}</td>
            <td>${statusBadge(l.status)}</td>
        </tr>`).join('')}</tbody></table></div>`;
    }

    async function loadWarehouses() {
        const res = await AdminAPI.getWmsWarehouses();
        warehouses = res.status === 'success' ? (res.data || []) : [];
        const fill = (sel, placeholder) => {
            if (!sel) return;
            const cur = sel.value;
            sel.innerHTML = (placeholder ? `<option value="">${esc(placeholder)}</option>` : '') +
                warehouses.map((w) => `<option value="${w.id}">${esc(w.name)}</option>`).join('');
            if (cur) sel.value = cur;
        };
        fill(document.getElementById('wmsLocWarehouse'), t('wms_select_warehouse'));
        fill(document.getElementById('wmsLocFormWarehouse'), null);
    }

    function buildCodePreview() {
        const form = document.getElementById('wmsLocCreateForm');
        if (!form) return 'A';
        const manual = form.location_code?.value?.trim();
        if (manual) return manual;
        const parts = ['zone', 'aisle', 'rack', 'shelf', 'bin']
            .map((name) => form[name]?.value?.trim())
            .filter(Boolean);
        return parts.join('-') || (form.zone?.value?.trim() || 'A');
    }

    function updateCodePreview() {
        const el = document.getElementById('wmsLocCodePreview');
        if (el) el.textContent = buildCodePreview();
    }

    async function load() {
        hideError();
        const wh = document.getElementById('wmsLocWarehouse')?.value;
        if (!wh) {
            allLocations = [];
            setStats([]);
            root.innerHTML = `<p class="cr-empty">${esc(t('wms_select_warehouse'))}</p>`;
            return;
        }
        root.innerHTML = `<div class="cr-loading">${esc(t('loading'))}</div>`;
        document.querySelectorAll('.cr-kpi-card').forEach((c) => c.classList.add('is-loading'));
        try {
            const res = await AdminAPI.getWmsLocations(wh);
            if (res.status !== 'success') throw new Error(res.message || t('load_error'));
            setMigrationHint(res.module_ready !== false);
            allLocations = res.data || [];
            setStats(allLocations);
            renderTable(allLocations);
            updateLastUpdated();
        } catch (e) {
            showError(e.message || t('load_error'));
            root.innerHTML = `<p class="cr-empty">${esc(e.message || t('load_error'))}</p>`;
            document.querySelectorAll('.cr-kpi-card').forEach((c) => c.classList.remove('is-loading'));
        }
    }

    function openModal() {
        const el = document.getElementById('wmsLocCreateModal');
        if (el) {
            el.classList.add('is-open');
            el.setAttribute('aria-hidden', 'false');
        }
    }

    function closeModal() {
        const el = document.getElementById('wmsLocCreateModal');
        if (el) {
            el.classList.remove('is-open');
            el.setAttribute('aria-hidden', 'true');
        }
    }

    function openCreateModal() {
        const form = document.getElementById('wmsLocCreateForm');
        if (!form) return;
        form.reset();
        const wh = document.getElementById('wmsLocWarehouse')?.value;
        const formWh = document.getElementById('wmsLocFormWarehouse');
        if (formWh && wh) formWh.value = wh;
        if (form.zone) form.zone.value = 'A';
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
            showError(t('wms_select_warehouse'));
            return;
        }

        const res = await AdminAPI.createWmsLocation(payload);
        if (res.status !== 'success') {
            showError(res.message || t('error'));
            return;
        }
        closeModal();
        hideError();
        const listWh = document.getElementById('wmsLocWarehouse');
        if (listWh && String(listWh.value) !== String(payload.warehouse_id)) {
            listWh.value = String(payload.warehouse_id);
        }
        await load();
    }

    document.getElementById('wmsLocRefresh')?.addEventListener('click', load);
    document.getElementById('wmsLocWarehouse')?.addEventListener('change', load);
    document.getElementById('wmsLocSearch')?.addEventListener('input', () => renderTable(allLocations));
    document.getElementById('wmsLocNewBtn')?.addEventListener('click', openCreateModal);
    document.getElementById('wmsLocCreateForm')?.addEventListener('submit', submitCreate);

    ['wmsLocCreateClose', 'wmsLocCreateCancel'].forEach((id) => {
        document.getElementById(id)?.addEventListener('click', closeModal);
    });
    document.getElementById('wmsLocCreateModal')?.addEventListener('click', (e) => {
        if (e.target.id === 'wmsLocCreateModal') closeModal();
    });

    ['zone', 'aisle', 'rack', 'shelf', 'bin', 'location_code'].forEach((name) => {
        document.querySelector(`#wmsLocCreateForm [name="${name}"]`)?.addEventListener('input', updateCodePreview);
    });

    document.addEventListener('wms:refresh', load);

    loadWarehouses().then(() => {
        const wh = document.getElementById('wmsLocWarehouse');
        if (wh && !wh.value && wh.options.length > 1) wh.selectedIndex = 1;
        load();
    });
});
