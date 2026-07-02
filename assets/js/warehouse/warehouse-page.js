/**
 * Warehouse portal — generic module page loader (WMS API)
 */
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('whModuleRoot');
    if (!root) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv, loadWarehouseOptions } = WarehouseUI;
    const endpoint = root.dataset.whEndpoint || 'inventory';
    const readOnly = !!window.WH_PAGE?.readOnly;

    const els = {
        warehouse: document.getElementById('whPageWarehouse'),
        search: document.getElementById('whPageSearch'),
        exportBtn: document.getElementById('whPageExport'),
        printBtn: document.getElementById('whPagePrint'),
        refreshBtn: document.getElementById('whPageRefresh'),
    };

    async function fetchData() {
        const wh = els.warehouse?.value || window.WH_PAGE?.warehouseId || '';
        const q = els.search?.value?.trim() || '';
        const params = { warehouse_id: wh, q: q || undefined };

        switch (endpoint) {
            case 'inventory':
                return AdminAPI.getWmsInventory(wh, q, 'all');
            case 'receipts':
                return AdminAPI.getWmsReceipts(wh, 'all');
            case 'dispatches':
                return AdminAPI.getWmsDispatches(wh, 'all', q);
            case 'transfers':
                return AdminAPI.getWmsTransfers(wh, 'all', q);
            case 'movements':
                return AdminAPI.getWmsMovements(wh, { q });
            case 'batches':
                return AdminAPI.getWmsBatches(wh, 'all', q, 90);
            case 'audits':
                return AdminAPI.getWmsAudits(wh, 'all', q);
            default:
                return AdminAPI.getWarehousePortalModule(root.dataset.whModule, endpoint, params);
        }
    }

    function columnsForEndpoint() {
        return {
            inventory: ['Product', 'SKU', 'Qty', 'Location', 'Value'],
            receipts: ['GRN', 'Status', 'Items', 'Value', 'Date'],
            dispatches: ['Dispatch', 'Status', 'Items', 'Destination', 'Date'],
            transfers: ['Transfer', 'Status', 'From', 'To', 'Date'],
            movements: ['Type', 'Product', 'Qty', 'Reference', 'Date'],
            batches: ['Batch', 'Product', 'Qty', 'Expiry', 'Status'],
            audits: ['Audit', 'Type', 'Status', 'Items', 'Date'],
        }[endpoint] || ['ID', 'Details'];
    }

    function rowFromItem(item) {
        switch (endpoint) {
            case 'inventory':
                return [item.product_name, item.sku, item.quantity, item.location_code || '—', money(item.stock_value || 0)];
            case 'receipts':
                return [item.grn_number, item.status, item.total_items, money(item.total_value), item.received_at];
            case 'dispatches':
                return [item.dispatch_number, item.status, item.total_items, item.to_store_name || item.to_warehouse_name || '—', item.created_at];
            case 'transfers':
                return [item.transfer_number, item.status, item.from_warehouse_name, item.to_warehouse_name, item.created_at];
            case 'movements':
                return [item.movement_type, item.product_name, item.quantity, item.reference_number || '—', item.created_at];
            case 'batches':
                return [item.batch_number, item.product_name, item.quantity, item.expiry_date, item.status];
            case 'audits':
                return [item.audit_number, item.audit_type, item.status, item.total_items, item.created_at];
            default:
                return [item.id, JSON.stringify(item).slice(0, 80)];
        }
    }

    function renderTable(rows) {
        if (!rows.length) {
            root.innerHTML = `<div class="acc-empty"><span class="material-icons-round">inventory_2</span><p>${esc(t('no_data'))}</p></div>`;
            return;
        }
        const cols = columnsForEndpoint();
        root.innerHTML = `
            ${readOnly ? `<p class="wh-readonly-badge">${esc(t('wh_readonly_mode'))}</p>` : ''}
            <div class="wh-table-wrap">
                <table class="modern-table wh-table">
                    <thead><tr>${cols.map((c) => `<th>${esc(c)}</th>`).join('')}</tr></thead>
                    <tbody>${rows.map((item) => `<tr>${rowFromItem(item).map((c) => `<td>${esc(String(c ?? '—'))}</td>`).join('')}</tr>`).join('')}</tbody>
                </table>
            </div>
            <div class="wh-cards">${rows.slice(0, 20).map((item) => {
                const cells = rowFromItem(item);
                return `<article class="wh-card"><h4>${esc(String(cells[0]))}</h4><p>${esc(cells.slice(1).join(' · '))}</p></article>`;
            }).join('')}</div>`;
        root.dataset.exportRows = JSON.stringify(rows);
    }

    async function load() {
        hideError();
        root.innerHTML = `<div class="acc-loading">${esc(t('loading'))}</div>`;
        try {
            const res = await fetchData();
            if (res.status !== 'success') throw new Error(res.message);
            setMigrationHint(res.module_ready ?? true);
            renderTable(res.data || []);
            updateLastUpdated();
        } catch (e) {
            showError(e.message || t('load_error'));
            root.innerHTML = `<div class="acc-empty"><p>${esc(e.message)}</p></div>`;
        }
    }

    els.exportBtn?.addEventListener('click', () => {
        try {
            const rows = JSON.parse(root.dataset.exportRows || '[]');
            exportCsv(`warehouse-${endpoint}.csv`, [columnsForEndpoint(), ...rows.map(rowFromItem)]);
        } catch (_) { /* noop */ }
    });
    els.printBtn?.addEventListener('click', () => window.print());
    els.refreshBtn?.addEventListener('click', load);
    els.warehouse?.addEventListener('change', load);
    els.search?.addEventListener('input', () => { clearTimeout(window._whSearchTimer); window._whSearchTimer = setTimeout(load, 350); });
    document.addEventListener('wh:refresh', load);

    loadWarehouseOptions(els.warehouse).then(load);
});
