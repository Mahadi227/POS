document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('wmsTableRoot');
    const page = document.body.dataset.wmsPage;
    if (!root || !page) return;

    const { t, esc, money, showError, hideError, updateLastUpdated, exportCsv, loadWarehouseOptions } = WmsUI;

    async function loadInventory() {
        const wh = document.getElementById('wmsWarehouseFilter')?.value;
        if (!wh) return `<p class="cr-empty">${esc(t('wms_select_warehouse'))}</p>`;
        const res = await AdminAPI.getWmsInventory(wh, document.getElementById('wmsSearch')?.value);
        const items = res.data || [];
        if (!items.length) return `<p class="cr-empty">${esc(t('wms_no_data'))}</p>`;
        return `<table class="modern-table"><thead><tr>
            <th>${esc(t('wms_col_product'))}</th><th>SKU</th><th>${esc(t('wms_col_qty'))}</th>
            <th>${esc(t('wms_col_reserved'))}</th><th>${esc(t('wms_col_value'))}</th><th>${esc(t('wms_col_location'))}</th>
        </tr></thead><tbody>${items.map((r) => `<tr>
            <td>${esc(r.product_name)}</td><td>${esc(r.sku)}</td><td>${r.quantity}</td>
            <td>${r.reserved_qty}</td><td>${esc(money(r.stock_value))}</td><td>${esc(r.location_code || '—')}</td>
        </tr>`).join('')}</tbody></table>`;
    }

    async function loadTransfers() {
        const res = await AdminAPI.getWmsTransfers(document.getElementById('wmsFilterStatus')?.value);
        const items = res.data || [];
        if (!items.length) return `<p class="cr-empty">${esc(t('wms_no_data'))}</p>`;
        return `<table class="modern-table"><thead><tr>
            <th>${esc(t('wms_col_transfer'))}</th><th>${esc(t('wms_col_type'))}</th><th>${esc(t('col_status'))}</th><th></th>
        </tr></thead><tbody>${items.map((tr) => `<tr>
            <td>${esc(tr.transfer_number)}</td><td>${esc(tr.transfer_type)}</td><td>${esc(tr.status)}</td>
            <td>${tr.status === 'requested' ? `<button class="cr-btn" data-tapprove="${tr.id}">${esc(t('wms_approve'))}</button> <button class="cr-btn cr-btn--warn" data-treject="${tr.id}">${esc(t('wms_reject'))}</button>` : ''}
            ${tr.status === 'approved' ? `<button class="cr-btn" data-tcomplete="${tr.id}">${esc(t('wms_complete'))}</button>` : ''}</td>
        </tr>`).join('')}</tbody></table>`;
    }

    async function loadReceipts() {
        const res = await AdminAPI.getWmsReceipts();
        const items = res.data || [];
        if (!items.length) return `<p class="cr-empty">${esc(t('wms_no_data'))}</p>`;
        return `<table class="modern-table"><thead><tr>
            <th>${esc(t('wms_col_grn'))}</th><th>${esc(t('wms_nav_warehouses'))}</th><th>${esc(t('wms_col_value'))}</th><th>${esc(t('col_status'))}</th><th></th>
        </tr></thead><tbody>${items.map((r) => `<tr>
            <td>${esc(r.grn_number)}</td><td>${esc(r.warehouse_name)}</td><td>${esc(money(r.total_value))}</td><td>${esc(r.status)}</td>
            <td>${r.status !== 'completed' ? `<button class="cr-btn" data-rcomplete="${r.id}">${esc(t('wms_complete'))}</button>` : ''}</td>
        </tr>`).join('')}</tbody></table>`;
    }

    async function loadDispatches() {
        const res = await AdminAPI.getWmsDispatches();
        const items = res.data || [];
        if (!items.length) return `<p class="cr-empty">${esc(t('wms_no_data'))}</p>`;
        return `<table class="modern-table"><thead><tr>
            <th>${esc(t('wms_col_dispatch'))}</th><th>${esc(t('wms_col_driver'))}</th><th>${esc(t('col_status'))}</th><th></th>
        </tr></thead><tbody>${items.map((d) => `<tr>
            <td>${esc(d.dispatch_number)}</td><td>${esc(d.driver_name || '—')}</td><td>${esc(d.status)}</td>
            <td>${d.status === 'draft' || d.status === 'packed' ? `<button class="cr-btn" data-dispatch="${d.id}">${esc(t('wms_dispatch_btn'))}</button>` : ''}</td>
        </tr>`).join('')}</tbody></table>`;
    }

    async function loadRequests() {
        const res = await AdminAPI.getWmsRequests();
        const items = res.data || [];
        if (!items.length) return `<p class="cr-empty">${esc(t('wms_no_data'))}</p>`;
        return `<table class="modern-table"><thead><tr>
            <th>#</th><th>${esc(t('wms_col_store'))}</th><th>${esc(t('wms_col_priority'))}</th><th>${esc(t('col_status'))}</th><th></th>
        </tr></thead><tbody>${items.map((r) => `<tr>
            <td>${esc(r.request_number)}</td><td>${esc(r.store_name)}</td><td>${esc(r.priority)}</td><td>${esc(r.status)}</td>
            <td>${r.status === 'pending' ? `<button class="cr-btn" data-rapprove="${r.id}">${esc(t('wms_approve'))}</button>` : ''}</td>
        </tr>`).join('')}</tbody></table>`;
    }

    async function loadBatches(expiredOnly = false) {
        const res = await AdminAPI.getWmsBatches(null, expiredOnly ? 'expired' : null);
        const items = res.data || [];
        if (!items.length) return `<p class="cr-empty">${esc(t('wms_no_data'))}</p>`;
        return `<table class="modern-table"><thead><tr>
            <th>${esc(t('wms_col_batch'))}</th><th>${esc(t('wms_col_product'))}</th><th>${esc(t('wms_col_expiry'))}</th><th>Qty</th>
        </tr></thead><tbody>${items.map((b) => `<tr>
            <td>${esc(b.batch_number)}</td><td>${esc(b.product_name)}</td><td>${esc(b.expiry_date || '—')}</td><td>${b.quantity}</td>
        </tr>`).join('')}</tbody></table>`;
    }

    async function loadMovements() {
        const res = await AdminAPI.getWmsMovements(null, {
            from: document.getElementById('wmsDateFrom')?.value,
            to: document.getElementById('wmsDateTo')?.value,
        });
        const items = res.data || [];
        window.__wmsExportRows = [['Date', 'Warehouse', 'Product', 'Type', 'Qty'], ...items.map((m) => [m.created_at, m.warehouse_name, m.product_name, m.movement_type, m.quantity])];
        if (!items.length) return `<p class="cr-empty">${esc(t('wms_no_data'))}</p>`;
        return `<table class="modern-table" id="wmsReportTable"><thead><tr>
            <th>${esc(t('col_date'))}</th><th>Warehouse</th><th>Product</th><th>Type</th><th>Qty</th>
        </tr></thead><tbody>${items.map((m) => `<tr>
            <td>${esc(AdminAPI.formatDate(m.created_at))}</td><td>${esc(m.warehouse_name)}</td><td>${esc(m.product_name)}</td>
            <td>${esc(m.movement_type)}</td><td>${m.quantity}</td></tr>`).join('')}</tbody></table>`;
    }

    async function loadLogs() {
        const res = await AdminAPI.getWmsLogs();
        const items = res.data || [];
        if (!items.length) return `<p class="cr-empty">${esc(t('wms_no_data'))}</p>`;
        return `<table class="modern-table"><thead><tr><th>${esc(t('col_date'))}</th><th>${esc(t('wms_col_action'))}</th><th>Warehouse</th></tr></thead><tbody>
            ${items.map((l) => `<tr><td>${esc(AdminAPI.formatDate(l.created_at))}</td><td>${esc(l.action)}</td><td>${esc(l.warehouse_name || '—')}</td></tr>`).join('')}
        </tbody></table>`;
    }

    async function load() {
        hideError();
        root.innerHTML = `<div class="cr-loading">${esc(t('loading'))}</div>`;
        try {
            let html = '';
            if (page === 'warehouse_inventory') html = await loadInventory();
            else if (page === 'stock_transfers') html = await loadTransfers();
            else if (page === 'goods_receipts') html = await loadReceipts();
            else if (page === 'stock_dispatch') html = await loadDispatches();
            else if (page === 'stock_requests') html = await loadRequests();
            else if (page === 'batch_management') html = await loadBatches(false);
            else if (page === 'expiry_management') html = await loadBatches(true);
            else if (page === 'reports') html = await loadMovements();
            else if (page === 'logs') html = await loadLogs();
            else html = `<p class="cr-empty">${esc(t('wms_no_data'))}</p>`;
            root.innerHTML = `<div class="cr-table-wrap">${html}</div>`;
            bindActions();
            updateLastUpdated();
        } catch (e) {
            showError(e.message);
            root.innerHTML = `<p class="cr-empty">${esc(e.message)}</p>`;
        }
    }

    function bindActions() {
        root.querySelectorAll('[data-tapprove]').forEach((b) => b.addEventListener('click', async () => {
            await AdminAPI.approveWmsTransfer(Number(b.dataset.tapprove)); load();
        }));
        root.querySelectorAll('[data-treject]').forEach((b) => b.addEventListener('click', async () => {
            await AdminAPI.rejectWmsTransfer(Number(b.dataset.treject)); load();
        }));
        root.querySelectorAll('[data-tcomplete]').forEach((b) => b.addEventListener('click', async () => {
            await AdminAPI.completeWmsTransfer(Number(b.dataset.tcomplete)); load();
        }));
        root.querySelectorAll('[data-rcomplete]').forEach((b) => b.addEventListener('click', async () => {
            await AdminAPI.completeWmsReceipt(Number(b.dataset.rcomplete)); load();
        }));
        root.querySelectorAll('[data-dispatch]').forEach((b) => b.addEventListener('click', async () => {
            await AdminAPI.dispatchWmsOut(Number(b.dataset.dispatch)); load();
        }));
        root.querySelectorAll('[data-rapprove]').forEach((b) => b.addEventListener('click', async () => {
            await AdminAPI.approveWmsRequest(Number(b.dataset.rapprove)); load();
        }));
    }

    const whFilter = document.getElementById('wmsWarehouseFilter');
    if (whFilter) loadWarehouseOptions(whFilter).then(() => { if (!whFilter.value && whFilter.options[1]) whFilter.selectedIndex = 1; load(); });
    else load();

    document.getElementById('wmsFilterBtn')?.addEventListener('click', load);
    document.getElementById('wmsSearch')?.addEventListener('change', load);
    whFilter?.addEventListener('change', load);
    document.getElementById('wmsExportCsvBtn')?.addEventListener('click', () => {
        if (window.__wmsExportRows) exportCsv('wms-report.csv', window.__wmsExportRows);
    });
    document.addEventListener('wms:refresh', load);
});
