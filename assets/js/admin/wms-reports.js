document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('wmsRepRoot');
    const breakdownRoot = document.getElementById('wmsRepBreakdown');
    if (!root || !breakdownRoot) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv } = WmsUI;

    let allMovements = [];
    let lastBreakdown = [];
    let lastSummary = null;

    const TYPE_KEYS = {
        purchase: 'wms_mov_purchase',
        sale: 'wms_mov_sale',
        transfer_in: 'wms_mov_transfer_in',
        transfer_out: 'wms_mov_transfer_out',
        return_in: 'wms_mov_return_in',
        return_out: 'wms_mov_return_out',
        adjustment: 'wms_mov_adjustment',
        damaged: 'wms_mov_damaged',
        expired: 'wms_mov_expired',
        lost: 'wms_mov_lost',
        manual: 'wms_mov_manual',
        dispatch_out: 'wms_mov_dispatch_out',
        receipt_in: 'wms_mov_receipt_in',
    };

    function typeLabel(type) {
        return t(TYPE_KEYS[type] || type) || type || '—';
    }

    function typeBadge(type) {
        const inbound = ['purchase', 'receipt_in', 'transfer_in', 'return_in'].includes(type);
        const cls = inbound ? 'ok' : (type === 'adjustment' ? 'warn' : 'idle');
        return `<span class="cr-badge cr-badge--${cls}">${esc(typeLabel(type))}</span>`;
    }

    function qtyCell(qty) {
        const n = Number(qty || 0);
        const cls = n > 0 ? 'wms-var--pos' : (n < 0 ? 'wms-var--neg' : '');
        const sign = n > 0 ? '+' : '';
        return `<span class="wms-variance ${cls}">${sign}${n}</span>`;
    }

    function refCell(row) {
        const parts = [row.reference_type, row.reference_id].filter(Boolean);
        return parts.length ? esc(parts.join(' #')) : '—';
    }

    function filters() {
        return {
            from: document.getElementById('wmsRepDateFrom')?.value,
            to: document.getElementById('wmsRepDateTo')?.value,
            type: document.getElementById('wmsRepType')?.value || 'all',
            q: document.getElementById('wmsRepSearch')?.value?.trim(),
        };
    }

    function periodLabel() {
        const f = filters();
        return [f.from, f.to].filter(Boolean).join(' → ') || '—';
    }

    function setStats(summary) {
        const s = summary || {};
        const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
        set('wmsRepTotal', String(s.total ?? 0));
        set('wmsRepIn', Number(s.stock_in ?? 0).toLocaleString());
        set('wmsRepOut', Number(s.stock_out ?? 0).toLocaleString());
        set('wmsRepValue', money(s.total_value));
        document.querySelectorAll('.cr-kpi-card').forEach((c) => c.classList.remove('is-loading'));
    }

    function renderBreakdown(items) {
        if (!items.length) {
            breakdownRoot.innerHTML = `<p class="cr-empty">${esc(t('wms_no_data'))}</p>`;
            return;
        }
        breakdownRoot.innerHTML = `<div class="cr-table-wrap"><table class="modern-table wms-rep-breakdown-table"><thead><tr>
            <th>${esc(t('wms_col_movement_type'))}</th>
            <th>${esc(t('wms_stat_mov_total'))}</th>
            <th>${esc(t('wms_col_qty'))}</th>
            <th>${esc(t('wms_col_value'))}</th>
        </tr></thead><tbody>${items.map((r) => `<tr>
            <td>${typeBadge(r.movement_type)}</td>
            <td>${Number(r.movement_count || 0)}</td>
            <td>${qtyCell(r.net_qty)}</td>
            <td>${esc(money(r.total_value))}</td>
        </tr>`).join('')}</tbody></table></div>`;
    }

    function renderTable(items) {
        if (!items.length) {
            root.innerHTML = `<p class="cr-empty">${esc(t('wms_no_data'))}</p>`;
            return;
        }
        root.innerHTML = `<div class="cr-table-wrap"><table class="modern-table wms-rep-table"><thead><tr>
            <th>${esc(t('col_date'))}</th>
            <th>${esc(t('wms_nav_warehouses'))}</th>
            <th>${esc(t('wms_col_product'))}</th>
            <th>${esc(t('wms_col_movement_type'))}</th>
            <th>${esc(t('wms_col_qty'))}</th>
            <th>${esc(t('wms_col_balance'))}</th>
            <th>${esc(t('wms_col_value'))}</th>
            <th>${esc(t('wms_col_reference'))}</th>
            <th>${esc(t('wms_col_user'))}</th>
        </tr></thead><tbody>${items.map((r) => `<tr>
            <td>${esc(AdminAPI.formatDate(r.created_at, { dateStyle: 'short', timeStyle: 'short' }))}</td>
            <td>${esc(r.warehouse_name || '—')}</td>
            <td>${esc(r.product_name)}<br><code class="wms-sku">${esc(r.sku || '')}</code></td>
            <td>${typeBadge(r.movement_type)}</td>
            <td>${qtyCell(r.quantity)}</td>
            <td>${Number(r.balance_after || 0)}</td>
            <td>${esc(money(r.stock_value))}</td>
            <td>${refCell(r)}</td>
            <td>${esc(r.created_by_name || '—')}</td>
        </tr>`).join('')}</tbody></table></div>`;
    }

    function buildExportRows(items) {
        return [
            [t('col_date'), t('wms_nav_warehouses'), t('wms_col_product'), 'SKU',
                t('wms_col_movement_type'), t('wms_col_qty'), t('wms_col_balance'),
                t('wms_col_value'), t('wms_col_reference'), t('wms_col_user')],
            ...items.map((r) => [
                r.created_at,
                r.warehouse_name,
                r.product_name,
                r.sku,
                r.movement_type,
                r.quantity,
                r.balance_after,
                r.stock_value,
                [r.reference_type, r.reference_id].filter(Boolean).join(' #'),
                r.created_by_name,
            ]),
        ];
    }

    async function loadWarehouses() {
        const res = await AdminAPI.getWmsWarehouses();
        const warehouses = res.status === 'success' ? (res.data || []) : [];
        const sel = document.getElementById('wmsRepWarehouse');
        if (!sel) return;
        const cur = sel.value;
        sel.innerHTML = `<option value="">${esc(t('wms_all_warehouses'))}</option>` +
            warehouses.map((w) => `<option value="${w.id}">${esc(w.name)}</option>`).join('');
        if (cur) sel.value = cur;
    }

    async function load() {
        hideError();
        root.innerHTML = `<div class="cr-loading">${esc(t('loading'))}</div>`;
        breakdownRoot.innerHTML = `<div class="cr-loading">${esc(t('loading'))}</div>`;
        document.querySelectorAll('.cr-kpi-card').forEach((c) => c.classList.add('is-loading'));
        try {
            await loadWarehouses();
            const wh = document.getElementById('wmsRepWarehouse')?.value;
            const f = filters();
            const res = await AdminAPI.getWmsMovements(wh, f);
            if (res.status !== 'success') throw new Error(res.message || t('load_error'));
            setMigrationHint(res.module_ready !== false);
            allMovements = res.data || [];
            lastBreakdown = res.breakdown || [];
            lastSummary = res.summary || null;
            setStats(lastSummary);
            renderBreakdown(lastBreakdown);
            renderTable(allMovements);
            updateLastUpdated();
        } catch (e) {
            showError(e.message || t('load_error'));
            root.innerHTML = `<p class="cr-empty">${esc(e.message || t('load_error'))}</p>`;
            breakdownRoot.innerHTML = '';
            document.querySelectorAll('.cr-kpi-card').forEach((c) => c.classList.remove('is-loading'));
        }
    }

    let searchTimer;
    document.getElementById('wmsRepSearch')?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(load, 350);
    });
    ['wmsRepWarehouse', 'wmsRepType', 'wmsRepDateFrom', 'wmsRepDateTo'].forEach((id) => {
        document.getElementById(id)?.addEventListener('change', load);
    });
    document.getElementById('wmsRepRefresh')?.addEventListener('click', load);
    document.getElementById('wmsRepExportCsv')?.addEventListener('click', () => {
        if (!allMovements.length) return;
        exportCsv(`wms-movements-${new Date().toISOString().slice(0, 10)}.csv`, buildExportRows(allMovements));
    });
    document.getElementById('wmsRepExportPdf')?.addEventListener('click', async () => {
        if (!allMovements.length || !window.WmsReportExport) return;
        const rows = buildExportRows(allMovements).slice(1);
        const result = await WmsReportExport.exportPdf({
            title: t('wms_reports_title'),
            periodLabel: periodLabel(),
            generatedLabel: t('last_updated') || 'Generated',
            locale: window.ADMIN_CONFIG?.locale,
            head: buildExportRows([])[0] || [],
            rows,
            filename: `wms-movements-${new Date().toISOString().slice(0, 10)}.pdf`,
        });
        if (result?.fallback) window.print();
    });

    document.addEventListener('wms:refresh', load);
    load();
});
