document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('wmsLogRoot');
    const breakdownRoot = document.getElementById('wmsLogBreakdown');
    if (!root || !breakdownRoot) return;

    const { t, esc, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv } = WmsUI;

    let allLogs = [];
    let lastBreakdown = [];

    const ACTION_KEYS = {
        warehouse_created: 'wms_log_warehouse_created',
        warehouse_updated: 'wms_log_warehouse_updated',
        warehouse_deleted: 'wms_log_warehouse_deleted',
        location_created: 'wms_log_location_created',
        transfer_requested: 'wms_log_transfer_requested',
        transfer_approved: 'wms_log_transfer_approved',
        transfer_rejected: 'wms_log_transfer_rejected',
        transfer_received: 'wms_log_transfer_received',
        dispatch_created: 'wms_log_dispatch_created',
        dispatch_out: 'wms_log_dispatch_out',
        request_created: 'wms_log_request_created',
        request_approved: 'wms_log_request_approved',
        request_rejected: 'wms_log_request_rejected',
        batch_created: 'wms_log_batch_created',
        batch_status_updated: 'wms_log_batch_status_updated',
        audit_created: 'wms_log_audit_created',
        audit_submitted: 'wms_log_audit_submitted',
        audit_approved: 'wms_log_audit_approved',
        audit_rejected: 'wms_log_audit_rejected',
        low_stock: 'wms_log_low_stock',
        damaged_stock: 'wms_log_damaged_stock',
        expired_product: 'wms_log_expired_product',
        incoming_delivery: 'wms_log_incoming_delivery',
        purchase_received: 'wms_log_purchase_received',
        warehouse_full: 'wms_log_warehouse_full',
    };

    const ENTITY_KEYS = {
        warehouse: 'wms_entity_warehouse',
        warehouse_location: 'wms_entity_location',
        warehouse_transfer: 'wms_entity_transfer',
        warehouse_dispatch: 'wms_entity_dispatch',
        warehouse_request: 'wms_entity_request',
        batch_tracking: 'wms_entity_batch',
        warehouse_audit: 'wms_entity_audit',
        notification: 'wms_entity_notification',
    };

    function actionLabel(action) {
        if (!action) return '—';
        return t(ACTION_KEYS[action] || action) || action.replace(/_/g, ' ');
    }

    function entityLabel(type) {
        if (!type) return '—';
        return t(ENTITY_KEYS[type] || type) || type.replace(/_/g, ' ');
    }

    function actionBadge(action) {
        const warn = ['rejected', 'deleted', 'damaged', 'expired', 'low_stock'].some((k) => action?.includes(k));
        const ok = ['approved', 'created', 'received'].some((k) => action?.includes(k));
        const cls = warn ? 'off' : (ok ? 'ok' : 'idle');
        return `<span class="cr-badge cr-badge--${cls}">${esc(actionLabel(action))}</span>`;
    }

    function entityCell(row) {
        if (!row.entity_type) return '—';
        const label = entityLabel(row.entity_type);
        return row.entity_id
            ? `${esc(label)} <code class="wms-sku">#${row.entity_id}</code>`
            : esc(label);
    }

    function detailsPreview(row) {
        if (!row.details) return '—';
        try {
            const obj = typeof row.details === 'string' ? JSON.parse(row.details) : row.details;
            if (obj && typeof obj === 'object') {
                const msg = obj.message || obj.status || obj.role;
                if (msg) return esc(String(msg));
                const keys = Object.keys(obj).slice(0, 2);
                return esc(keys.map((k) => `${k}: ${obj[k]}`).join(', '));
            }
        } catch (e) { /* ignore */ }
        const s = String(row.details);
        return esc(s.length > 60 ? `${s.slice(0, 57)}…` : s);
    }

    function filters() {
        return {
            from: document.getElementById('wmsLogDateFrom')?.value,
            to: document.getElementById('wmsLogDateTo')?.value,
            action: document.getElementById('wmsLogAction')?.value || undefined,
            entity_type: document.getElementById('wmsLogEntity')?.value || undefined,
            q: document.getElementById('wmsLogSearch')?.value?.trim(),
        };
    }

    function periodLabel() {
        const f = filters();
        return [f.from, f.to].filter(Boolean).join(' → ') || '—';
    }

    function setStats(summary) {
        const s = summary || {};
        const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
        set('wmsLogTotal', String(s.total ?? 0));
        set('wmsLogToday', String(s.today ?? 0));
        set('wmsLogUsers', String(s.users ?? 0));
        set('wmsLogEntities', String(s.entities ?? 0));
        document.querySelectorAll('.cr-kpi-card').forEach((c) => c.classList.remove('is-loading'));
    }

    function renderBreakdown(items) {
        if (!items.length) {
            breakdownRoot.innerHTML = `<p class="cr-empty">${esc(t('wms_no_data'))}</p>`;
            return;
        }
        breakdownRoot.innerHTML = `<div class="cr-table-wrap"><table class="modern-table wms-log-breakdown-table"><thead><tr>
            <th>${esc(t('wms_col_action'))}</th>
            <th>${esc(t('wms_stat_log_total'))}</th>
        </tr></thead><tbody>${items.map((r) => `<tr>
            <td>${actionBadge(r.action)}</td>
            <td>${Number(r.event_count || 0)}</td>
        </tr>`).join('')}</tbody></table></div>`;
    }

    function renderTable(items) {
        if (!items.length) {
            root.innerHTML = `<p class="cr-empty">${esc(t('wms_no_data'))}</p>`;
            return;
        }
        root.innerHTML = `<div class="cr-table-wrap"><table class="modern-table wms-log-table"><thead><tr>
            <th>${esc(t('col_date'))}</th>
            <th>${esc(t('wms_col_action'))}</th>
            <th>${esc(t('wms_nav_warehouses'))}</th>
            <th>${esc(t('wms_col_user'))}</th>
            <th>${esc(t('wms_col_entity'))}</th>
            <th>${esc(t('wms_col_details'))}</th>
            <th>${esc(t('wms_col_ip'))}</th>
            <th></th>
        </tr></thead><tbody>${items.map((r) => `<tr>
            <td>${esc(AdminAPI.formatDate(r.created_at, { dateStyle: 'short', timeStyle: 'short' }))}</td>
            <td>${actionBadge(r.action)}</td>
            <td>${esc(r.warehouse_name || '—')}</td>
            <td>${esc(r.user_name || '—')}</td>
            <td>${entityCell(r)}</td>
            <td class="wms-log-details-preview">${detailsPreview(r)}</td>
            <td><code class="wms-sku">${esc(r.ip_address || '—')}</code></td>
            <td class="cr-actions">
                <button type="button" class="cr-btn cr-btn--ghost" data-log-view="${r.id}">${esc(t('wms_view_details'))}</button>
            </td>
        </tr>`).join('')}</tbody></table></div>`;

        root.querySelectorAll('[data-log-view]').forEach((btn) => {
            btn.addEventListener('click', () => openDetail(Number(btn.dataset.logView)));
        });
    }

    function buildExportRows(items) {
        return [
            [t('col_date'), t('wms_col_action'), t('wms_nav_warehouses'), t('wms_col_user'),
                t('wms_col_entity'), 'Entity ID', t('wms_col_details'), t('wms_col_ip')],
            ...items.map((r) => [
                r.created_at,
                r.action,
                r.warehouse_name,
                r.user_name,
                r.entity_type,
                r.entity_id,
                r.details,
                r.ip_address,
            ]),
        ];
    }

    function fillActionFilter(actions) {
        const sel = document.getElementById('wmsLogAction');
        if (!sel) return;
        const cur = sel.value;
        const list = actions?.length ? actions : Object.keys(ACTION_KEYS);
        sel.innerHTML = `<option value="">${esc(t('wms_filter_all_actions'))}</option>` +
            list.map((a) => `<option value="${esc(a)}">${esc(actionLabel(a))}</option>`).join('');
        if (cur) sel.value = cur;
    }

    async function loadWarehouses() {
        const res = await AdminAPI.getWmsWarehouses();
        const warehouses = res.status === 'success' ? (res.data || []) : [];
        const sel = document.getElementById('wmsLogWarehouse');
        if (!sel) return;
        const cur = sel.value;
        sel.innerHTML = `<option value="">${esc(t('wms_all_warehouses'))}</option>` +
            warehouses.map((w) => `<option value="${w.id}">${esc(w.name)}</option>`).join('');
        if (cur) sel.value = cur;
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

    async function openDetail(id) {
        const body = document.getElementById('wmsLogDetailBody');
        if (!body) return;
        body.innerHTML = `<div class="cr-loading">${esc(t('loading'))}</div>`;
        openModal('wmsLogDetailModal');
        try {
            const res = await AdminAPI.getWmsLog(id);
            if (res.status !== 'success' || !res.data) throw new Error(res.message || t('load_error'));
            const r = res.data;
            document.getElementById('wmsLogDetailTitle').textContent = actionLabel(r.action);
            const sub = document.getElementById('wmsLogDetailSubtitle');
            if (sub) sub.textContent = [r.warehouse_name, r.user_name].filter(Boolean).join(' · ');
            let detailsHtml = '—';
            if (r.details_parsed) {
                detailsHtml = `<pre class="wms-log-json">${esc(JSON.stringify(r.details_parsed, null, 2))}</pre>`;
            } else if (r.details) {
                detailsHtml = `<pre class="wms-log-json">${esc(String(r.details))}</pre>`;
            }
            body.innerHTML = `
                <dl class="wms-detail-grid">
                    <div><dt>${esc(t('col_date'))}</dt><dd>${esc(AdminAPI.formatDate(r.created_at, { dateStyle: 'short', timeStyle: 'short' }))}</dd></div>
                    <div><dt>${esc(t('wms_col_action'))}</dt><dd>${actionBadge(r.action)}</dd></div>
                    <div><dt>${esc(t('wms_nav_warehouses'))}</dt><dd>${esc(r.warehouse_name || '—')}</dd></div>
                    <div><dt>${esc(t('wms_col_user'))}</dt><dd>${esc(r.user_name || '—')}</dd></div>
                    <div><dt>${esc(t('wms_col_entity'))}</dt><dd>${entityCell(r)}</dd></div>
                    <div><dt>${esc(t('wms_col_ip'))}</dt><dd><code class="wms-sku">${esc(r.ip_address || '—')}</code></dd></div>
                </dl>
                <h4 class="wms-log-details-heading">${esc(t('wms_col_details'))}</h4>
                ${detailsHtml}`;
        } catch (e) {
            body.innerHTML = `<p class="cr-empty">${esc(e.message || t('load_error'))}</p>`;
        }
    }

    async function load() {
        hideError();
        root.innerHTML = `<div class="cr-loading">${esc(t('loading'))}</div>`;
        breakdownRoot.innerHTML = `<div class="cr-loading">${esc(t('loading'))}</div>`;
        document.querySelectorAll('.cr-kpi-card').forEach((c) => c.classList.add('is-loading'));
        try {
            await loadWarehouses();
            const wh = document.getElementById('wmsLogWarehouse')?.value;
            const res = await AdminAPI.getWmsLogs(wh, filters());
            if (res.status !== 'success') throw new Error(res.message || t('load_error'));
            setMigrationHint(res.module_ready !== false);
            fillActionFilter(res.actions);
            allLogs = res.data || [];
            lastBreakdown = res.breakdown || [];
            setStats(res.summary);
            renderBreakdown(lastBreakdown);
            renderTable(allLogs);
            updateLastUpdated();
        } catch (e) {
            showError(e.message || t('load_error'));
            root.innerHTML = `<p class="cr-empty">${esc(e.message || t('load_error'))}</p>`;
            breakdownRoot.innerHTML = '';
            document.querySelectorAll('.cr-kpi-card').forEach((c) => c.classList.remove('is-loading'));
        }
    }

    let searchTimer;
    document.getElementById('wmsLogSearch')?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(load, 350);
    });
    ['wmsLogWarehouse', 'wmsLogAction', 'wmsLogEntity', 'wmsLogDateFrom', 'wmsLogDateTo'].forEach((id) => {
        document.getElementById(id)?.addEventListener('change', load);
    });
    document.getElementById('wmsLogRefresh')?.addEventListener('click', load);
    document.getElementById('wmsLogDetailClose')?.addEventListener('click', () => closeModal('wmsLogDetailModal'));
    document.getElementById('wmsLogExportCsv')?.addEventListener('click', () => {
        if (!allLogs.length) return;
        exportCsv(`wms-logs-${new Date().toISOString().slice(0, 10)}.csv`, buildExportRows(allLogs));
    });
    document.getElementById('wmsLogExportPdf')?.addEventListener('click', async () => {
        if (!allLogs.length || !window.WmsReportExport) return;
        const head = buildExportRows([])[0];
        const rows = buildExportRows(allLogs).slice(1);
        const result = await WmsReportExport.exportPdf({
            title: t('wms_logs_title'),
            periodLabel: periodLabel(),
            generatedLabel: t('last_updated') || 'Generated',
            locale: window.ADMIN_CONFIG?.locale,
            head,
            rows,
            filename: `wms-logs-${new Date().toISOString().slice(0, 10)}.pdf`,
        });
        if (result?.fallback) window.print();
    });

    document.addEventListener('wms:refresh', load);
    load();
});
