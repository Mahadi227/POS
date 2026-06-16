document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('wmsExpRoot');
    if (!root) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv } = WmsUI;
    const canManage = !!window.ADMIN_PAGE?.canManage;

    let allItems = [];
    let detailId = null;

    const STATUS_KEYS = {
        active: 'wms_status_active',
        expired: 'wms_status_expired',
        recalled: 'wms_status_recalled',
        depleted: 'wms_status_depleted',
    };

    function statusLabel(status) {
        return t(STATUS_KEYS[status] || status) || status || '—';
    }

    function statusBadge(status) {
        const cls = status === 'active' ? 'ok' : (status === 'recalled' || status === 'expired' ? 'off' : 'warn');
        return `<span class="cr-badge cr-badge--${cls}">${esc(statusLabel(status))}</span>`;
    }

    function formatDate(val) {
        if (!val) return '—';
        return AdminAPI.formatDate(val, { dateStyle: 'short' });
    }

    function urgencyBadge(row) {
        const days = row.days_to_expiry != null ? Number(row.days_to_expiry) : null;
        if (days == null) return '—';
        if (days < 0 || row.status === 'expired') {
            return `<span class="cr-badge cr-badge--off">${esc(t('wms_urgency_expired'))}</span>`;
        }
        if (days <= 7) {
            return `<span class="cr-badge cr-badge--off">${esc(t('wms_urgency_critical'))}</span>`;
        }
        if (days <= 30) {
            return `<span class="cr-badge cr-badge--warn">${esc(t('wms_urgency_warning'))}</span>`;
        }
        return `<span class="cr-badge cr-badge--ok">${days}d</span>`;
    }

    function expiryCell(row) {
        const exp = row.expiry_date;
        if (!exp) return '—';
        const days = row.days_to_expiry != null ? Number(row.days_to_expiry) : null;
        let cls = '';
        if (days != null) {
            if (days < 0) cls = 'wms-expiry--past';
            else if (days <= 30) cls = 'wms-expiry--soon';
        }
        const hint = days != null ? ` <small class="wms-expiry-days">(${days}d)</small>` : '';
        return `<span class="wms-expiry ${cls}">${esc(formatDate(exp))}${hint}</span>`;
    }

    function getDays() {
        return Number(document.getElementById('wmsExpPeriod')?.value || 30);
    }

    function setStats(summary) {
        const s = summary || {};
        const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
        set('wmsExpSoon', String(s.expiring_soon ?? 0));
        set('wmsExpPast', String(s.past_expiry ?? 0));
        set('wmsExpUnits', Number(s.units_at_risk ?? 0).toLocaleString());
        set('wmsExpValue', money(s.value_at_risk));
        document.querySelectorAll('.cr-kpi-card').forEach((c) => c.classList.remove('is-loading'));
    }

    function renderTable(items) {
        if (!items.length) {
            root.innerHTML = `<p class="cr-empty">${esc(t('wms_no_data'))}</p>`;
            return;
        }
        root.innerHTML = `<div class="cr-table-wrap"><table class="modern-table wms-exp-table"><thead><tr>
            <th>${esc(t('wms_col_batch'))}</th>
            <th>${esc(t('wms_col_product'))}</th>
            <th>${esc(t('wms_nav_warehouses'))}</th>
            <th>${esc(t('wms_col_qty'))}</th>
            <th>${esc(t('wms_col_value'))}</th>
            <th>${esc(t('wms_col_expiry'))}</th>
            <th>${esc(t('wms_days_to_expiry'))}</th>
            <th>${esc(t('col_status'))}</th>
            <th></th>
        </tr></thead><tbody>${items.map((r) => `<tr class="${Number(r.days_to_expiry) < 0 ? 'wms-exp-row--past' : (Number(r.days_to_expiry) <= 7 ? 'wms-exp-row--critical' : '')}">
            <td><strong>${esc(r.batch_number)}</strong></td>
            <td>${esc(r.product_name)}<br><code class="wms-sku">${esc(r.sku || '')}</code></td>
            <td>${esc(r.warehouse_name || '—')}</td>
            <td>${Number(r.quantity || 0)}</td>
            <td>${esc(money(r.stock_value))}</td>
            <td>${expiryCell(r)}</td>
            <td>${urgencyBadge(r)}</td>
            <td>${statusBadge(r.status)}</td>
            <td class="cr-actions">
                <button type="button" class="cr-btn cr-btn--ghost" data-exp-view="${r.id}">${esc(t('wms_view_details'))}</button>
            </td>
        </tr>`).join('')}</tbody></table></div>`;

        root.querySelectorAll('[data-exp-view]').forEach((btn) => {
            btn.addEventListener('click', () => openDetail(Number(btn.dataset.expView)));
        });
    }

    function buildExportRows(items) {
        return [
            [t('wms_col_batch'), t('wms_col_product'), 'SKU', t('wms_nav_warehouses'),
                t('wms_col_qty'), t('wms_col_value'), t('wms_col_expiry'), t('wms_days_to_expiry'), t('col_status')],
            ...items.map((r) => [
                r.batch_number,
                r.product_name,
                r.sku,
                r.warehouse_name,
                r.quantity,
                r.stock_value,
                r.expiry_date,
                r.days_to_expiry,
                r.status,
            ]),
        ];
    }

    function openModal(id) {
        const el = document.getElementById(id);
        if (el) {
            el.classList.add('is-open');
            el.setAttribute('aria-hidden', 'false');
        }
    }

    function closeModal(id) {
        const el = document.getElementById(id);
        if (el) {
            el.classList.remove('is-open');
            el.setAttribute('aria-hidden', 'true');
        }
    }

    async function loadWarehouses() {
        const res = await AdminAPI.getWmsWarehouses();
        const warehouses = res.status === 'success' ? (res.data || []) : [];
        const sel = document.getElementById('wmsExpWarehouse');
        if (!sel) return;
        const cur = sel.value;
        sel.innerHTML = `<option value="">${esc(t('wms_all_warehouses'))}</option>` +
            warehouses.map((w) => `<option value="${w.id}">${esc(w.name)}</option>`).join('');
        if (cur) sel.value = cur;
    }

    async function load() {
        hideError();
        root.innerHTML = `<div class="cr-loading">${esc(t('loading'))}</div>`;
        document.querySelectorAll('.cr-kpi-card').forEach((c) => c.classList.add('is-loading'));
        try {
            await loadWarehouses();
            const wh = document.getElementById('wmsExpWarehouse')?.value;
            const filter = document.getElementById('wmsExpFilter')?.value || 'at_risk';
            const days = getDays();
            const q = document.getElementById('wmsExpSearch')?.value?.trim();
            const res = await AdminAPI.getWmsExpiry(wh, filter, days, q);
            if (res.status !== 'success') throw new Error(res.message || t('load_error'));
            setMigrationHint(res.module_ready !== false);
            allItems = res.data || [];
            setStats(res.summary);
            renderTable(allItems);
            updateLastUpdated();
        } catch (e) {
            showError(e.message || t('load_error'));
            root.innerHTML = `<p class="cr-empty">${esc(e.message || t('load_error'))}</p>`;
            document.querySelectorAll('.cr-kpi-card').forEach((c) => c.classList.remove('is-loading'));
        }
    }

    function updateDetailActions(row) {
        const footer = document.getElementById('wmsExpDetailActions');
        if (!footer) return;
        footer.hidden = !(canManage && row && row.status === 'active');
    }

    async function openDetail(id) {
        detailId = id;
        const body = document.getElementById('wmsExpDetailBody');
        if (!body) return;
        body.innerHTML = `<div class="cr-loading">${esc(t('loading'))}</div>`;
        updateDetailActions(null);
        openModal('wmsExpDetailModal');
        try {
            const res = await AdminAPI.getWmsBatch(id);
            if (res.status !== 'success' || !res.data) throw new Error(res.message || t('load_error'));
            const r = res.data;
            document.getElementById('wmsExpDetailTitle').textContent = r.batch_number || t('wms_expiry_details');
            const sub = document.getElementById('wmsExpDetailSubtitle');
            if (sub) sub.textContent = [r.product_name, r.sku, r.warehouse_name].filter(Boolean).join(' · ');
            const days = r.days_to_expiry != null ? Number(r.days_to_expiry) : null;
            body.innerHTML = `
                <dl class="wms-detail-grid">
                    <div><dt>${esc(t('col_status'))}</dt><dd>${statusBadge(r.status)} ${urgencyBadge(r)}</dd></div>
                    <div><dt>${esc(t('wms_col_product'))}</dt><dd>${esc(r.product_name)} <code class="wms-sku">${esc(r.sku || '')}</code></dd></div>
                    <div><dt>${esc(t('wms_nav_warehouses'))}</dt><dd>${esc(r.warehouse_name || '—')}</dd></div>
                    <div><dt>${esc(t('wms_col_qty'))}</dt><dd>${Number(r.quantity || 0)}</dd></div>
                    <div><dt>${esc(t('wms_unit_cost'))}</dt><dd>${esc(money(r.unit_cost))}</dd></div>
                    <div><dt>${esc(t('wms_col_value'))}</dt><dd>${esc(money(r.stock_value))}</dd></div>
                    <div><dt>${esc(t('wms_col_mfg'))}</dt><dd>${esc(formatDate(r.manufacturing_date))}</dd></div>
                    <div><dt>${esc(t('wms_col_expiry'))}</dt><dd>${expiryCell(r)}</dd></div>
                    <div><dt>${esc(t('wms_days_to_expiry'))}</dt><dd>${days != null ? `${days} ${t('wms_days_short') || 'd'}` : '—'}</dd></div>
                    <div><dt>${esc(t('wms_col_barcode'))}</dt><dd>${esc(r.barcode || r.product_barcode || '—')}</dd></div>
                    <div><dt>${esc(t('wms_col_serial'))}</dt><dd>${esc(r.serial_number || '—')}</dd></div>
                    <div><dt>${esc(t('col_date'))}</dt><dd>${esc(r.created_at ? AdminAPI.formatDate(r.created_at, { dateStyle: 'short', timeStyle: 'short' }) : '—')}</dd></div>
                </dl>`;
            updateDetailActions(r);
        } catch (e) {
            body.innerHTML = `<p class="cr-empty">${esc(e.message || t('load_error'))}</p>`;
        }
    }

    async function updateStatus(status) {
        if (!detailId) return;
        const confirmKeys = {
            recalled: 'wms_confirm_recall',
            depleted: 'wms_confirm_deplete',
            expired: 'wms_confirm_mark_expired',
        };
        if (!window.confirm(t(confirmKeys[status] || 'error'))) return;
        try {
            const res = await AdminAPI.updateWmsBatchStatus(detailId, status);
            if (res.status !== 'success') throw new Error(res.message || t('error'));
            closeModal('wmsExpDetailModal');
            await load();
        } catch (e) {
            showError(e.message || t('error'));
        }
    }

    let searchTimer;
    document.getElementById('wmsExpSearch')?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(load, 350);
    });
    document.getElementById('wmsExpWarehouse')?.addEventListener('change', load);
    document.getElementById('wmsExpPeriod')?.addEventListener('change', load);
    document.getElementById('wmsExpFilter')?.addEventListener('change', load);
    document.getElementById('wmsExpRefresh')?.addEventListener('click', load);
    document.getElementById('wmsExpExport')?.addEventListener('click', () => {
        if (!allItems.length) return;
        exportCsv(`wms-expiry-${new Date().toISOString().slice(0, 10)}.csv`, buildExportRows(allItems));
    });
    document.getElementById('wmsExpDetailClose')?.addEventListener('click', () => closeModal('wmsExpDetailModal'));
    document.getElementById('wmsExpExpiredBtn')?.addEventListener('click', () => updateStatus('expired'));
    document.getElementById('wmsExpRecallBtn')?.addEventListener('click', () => updateStatus('recalled'));
    document.getElementById('wmsExpDepleteBtn')?.addEventListener('click', () => updateStatus('depleted'));

    document.addEventListener('wms:refresh', load);
    load();
});
