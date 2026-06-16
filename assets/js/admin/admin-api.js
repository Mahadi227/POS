/**
 * Client API module admin.
 */
const AdminAPI = (() => {
    function apiBase() {
        return window.ADMIN_CONFIG?.api?.base || '../../api/v1/index.php';
    }

    async function request(resource, query = {}, fetchOptions = {}) {
        const params = new URLSearchParams({ request: resource });
        Object.entries(query).forEach(([k, v]) => {
            if (v !== undefined && v !== null && v !== '') params.set(k, String(v));
        });

        const base = apiBase();
        const url = base.includes('?') ? `${base}&${params}` : `${base}?${params}`;

        const res = await fetch(url, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', ...(fetchOptions.headers || {}) },
            ...fetchOptions,
        });

        const text = await res.text();
        let data;
        try {
            data = text ? JSON.parse(text) : {};
        } catch {
            console.error('AdminAPI invalid JSON', text.slice(0, 300));
            return {
                status: 'error',
                message: 'Réponse serveur invalide',
            };
        }

        if (!res.ok && data.status !== 'error') {
            data.status = 'error';
            data.message = data.message || `HTTP ${res.status}`;
        }
        return data;
    }

    return {
        request,

        getDashboard() {
            return request('dashboard');
        },

        getReports(query = {}) {
            return request('reports', query);
        },

        getSyncMonitor() {
            return request('sync/monitor');
        },

        getSyncBranches() {
            return request('sync/branches');
        },

        getSyncQueue() {
            return request('sync/queue');
        },

        getSyncFailed() {
            return request('sync/failed');
        },

        getSyncConflicts() {
            return request('sync/conflicts');
        },

        retrySyncQueueItem(id) {
            return request(`sync/queue/${id}/retry`, {}, { method: 'POST' });
        },

        resolveSyncConflict(id, data) {
            return request(`sync/conflicts/${id}/resolve`, {}, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
            });
        },

        syncHeartbeat(data) {
            return request('sync/heartbeat', {}, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
            });
        },

        reportSyncFailure(data) {
            return request('sync/report', {}, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
            });
        },

        getStoreHealth() {
            return request('stores/health');
        },

        getCurrencySymbol() {
            const cfg = (typeof window !== 'undefined' && (window.INVENTORY_CONFIG || window.MANAGER_CONFIG || window.POS_CONFIG || window.ADMIN_CONFIG || window.ADMIN_PAGE)) || {};
            return (cfg.settings && cfg.settings.currency_symbol) || cfg.currency || (cfg.store && cfg.store.currency) || (window.ADMIN_PAGE && window.ADMIN_PAGE.currency) || 'FCFA';
        },

        getLocale() {
            const cfg = (typeof window !== 'undefined' && (window.ADMIN_CONFIG || window.INVENTORY_CONFIG || window.MANAGER_CONFIG || window.POS_CONFIG)) || {};
            if (cfg.locale) return cfg.locale;
            return cfg.lang === 'fr' ? 'fr-FR' : 'en-US';
        },

        formatCurrency(amount) {
            return `${Number(amount || 0).toLocaleString(this.getLocale())} ${this.getCurrencySymbol()}`;
        },

        formatDate(dateString, options = { dateStyle: 'short', timeStyle: 'short' }) {
            if (!dateString) return '—';
            return new Date(dateString).toLocaleString(this.getLocale(), options);
        },

        paymentLabel(method) {
            const i18n = (typeof window !== 'undefined' && window.ADMIN_I18N) || {};
            const map = {
                cash: i18n.pay_cash || 'Cash',
                card: i18n.pay_card || 'Card',
                mobile_money: i18n.pay_mobile_money || 'Mobile Money',
            };
            return map[method] || method || '—';
        },

        async inventory(path = '', fetchOptions = {}) {
            const resource = path ? `inventory/${path}` : 'inventory';
            return request(resource, {}, fetchOptions);
        },

        getInventoryStats() {
            return this.inventory('stats');
        },

        getInventoryProducts() {
            return this.inventory('products');
        },

        getInventoryCategories() {
            return this.inventory('categories');
        },

        getInventoryLedger(query = {}) {
            return request('inventory/ledger', query);
        },

        getExpiredProducts(query = {}) {
            return request('inventory/expired', query);
        },

        getInventoryMovements(query = {}) {
            return request('inventory/movements', query);
        },

        getInventoryReports(query = {}) {
            return request('inventory/reports', query);
        },

        getInventoryAnalytics(query = {}) {
            return request('inventory/analytics', query);
        },

        scanBarcode(code) {
            return this.inventory(`scan/${encodeURIComponent(code)}`);
        },

        createProduct(data) {
            return this.inventory('products', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
            });
        },

        updateProduct(id, data) {
            return this.inventory(`products/${id}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
            });
        },

        deleteProduct(id) {
            return this.inventory(`products/${id}`, { method: 'DELETE' });
        },

        createCategory(data) {
            return this.inventory('categories', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
            });
        },

        adjustStock(data) {
            return this.inventory('adjust', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
            });
        },

        getSales(query = {}) {
            return request('sales', query);
        },

        getSalesStats() {
            return request('sales/stats');
        },

        getSale(id) {
            return request(`sales/${id}`);
        },

        listStores() {
            return request('stores');
        },

        getStoreContext() {
            return request('stores/context');
        },

        getStore(id) {
            return request(`stores/${id}`);
        },

        switchStore(data) {
            return request('stores/switch', {}, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
            });
        },

        createStore(data) {
            return request('stores', {}, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
            });
        },

        updateStore(id, data) {
            return request(`stores/${id}`, {}, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
            });
        },

        deleteStore(id) {
            return request(`stores/${id}`, {}, { method: 'DELETE' });
        },

        listTransfers(query = {}) {
            return request('stores/transfers', query);
        },

        getTransferStats() {
            return request('stores/transfers/stats');
        },

        getTransferProducts(storeId, q = '') {
            return request('stores/transfers/products', { store_id: storeId, q });
        },

        getTransfer(id) {
            return request(`stores/transfers/${id}`);
        },

        createTransfer(data) {
            return request('stores/transfers', {}, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
            });
        },

        updateTransfer(id, data) {
            return request(`stores/transfers/${id}`, {}, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
            });
        },

        getUsers(query = {}) {
            return request('users', query);
        },

        getUser(id) {
            return request(`users/${id}`);
        },

        createUser(data) {
            return request('users', {}, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
            });
        },

        updateUser(id, data) {
            return request(`users/${id}`, {}, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
            });
        },

        suspendUser(id) {
            return request(`users/${id}/suspend`, {}, { method: 'PUT' });
        },

        activateUser(id) {
            return request(`users/${id}/activate`, {}, { method: 'PUT' });
        },

        resetUserPassword(id, password) {
            return request(`users/${id}/reset-password`, {}, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ password }),
            });
        },

        getUserActivity(query = {}) {
            return request('users/activity', query);
        },

        getRoles() {
            return request('users/roles');
        },

        getPermissions() {
            return request('users/permissions');
        },

        getRolePermissions(roleId) {
            return request('users/role-permissions', { role_id: roleId });
        },

        updateRolePermissions(roleId, permissionIds) {
            return request('users/role-permissions', {}, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ role_id: roleId, permission_ids: permissionIds }),
            });
        },

        roleLabel(slug) {
            const map = {
                super_admin: 'Super Admin',
                admin: 'Administrateur',
                manager: 'Manager',
                cashier: 'Caissier',
                staff: 'Staff',
            };
            return map[slug] || slug;
        },

        statusLabel(status) {
            const i18n = (typeof window !== 'undefined' && window.ADMIN_I18N) || {};
            const map = {
                completed: i18n.status_completed || 'Completed',
                cancelled: i18n.status_cancelled || 'Cancelled',
                refunded: i18n.status_refunded || 'Refunded',
                pending: i18n.status_pending || 'Pending',
            };
            return map[status] || status || '—';
        },

        statusClass(status) {
            if (status === 'completed') return 'success';
            if (status === 'cancelled' || status === 'refunded') return 'pending';
            return 'pending';
        },

        getCashRegisterDashboard() {
            return request('cash-registers/dashboard');
        },
        getCashRegisters(status) {
            return request('cash-registers/registers', status ? { status } : {});
        },
        getCashRegister(id) {
            return request(`cash-registers/registers/${id}`);
        },
        createCashRegister(data) {
            return request('cash-registers/registers', {}, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
            });
        },
        updateCashRegister(id, data) {
            return request(`cash-registers/registers/${id}`, {}, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
            });
        },
        deleteCashRegister(id) {
            return request(`cash-registers/registers/${id}`, {}, { method: 'DELETE' });
        },
        openCashRegisterSession(registerId, data) {
            return request(`cash-registers/registers/open/${registerId}`, {}, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
            });
        },
        closeCashSession(sessionId, data) {
            return request(`cash-registers/sessions/close/${sessionId}`, {}, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
            });
        },
        getCashMovements(query = {}) {
            return request('cash-registers/movements', query);
        },
        getCashReconciliations(status) {
            return request('cash-registers/reconciliation', status ? { status } : {});
        },
        approveCashReconciliation(id, note) {
            return request(`cash-registers/reconciliation/approve/${id}`, {}, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ note }),
            });
        },
        rejectCashReconciliation(id, note) {
            return request(`cash-registers/reconciliation/reject/${id}`, {}, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ note }),
            });
        },
        getCashTransfers(status) {
            return request('cash-registers/transfers', status ? { status } : {});
        },
        createCashTransfer(data) {
            return request('cash-registers/transfers', {}, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
            });
        },
        approveCashTransfer(id) {
            return request(`cash-registers/transfers/approve/${id}`, {}, { method: 'POST' });
        },
        completeCashTransfer(id) {
            return request(`cash-registers/transfers/complete/${id}`, {}, { method: 'POST' });
        },
        getCashRegisterSessions(status) {
            return request('cash-registers/sessions', status ? { status } : {});
        },
        getCashRegisterHistory(query = {}) {
            return request('cash-registers/history', query);
        },
        getCashRegisterAnalytics(period = 'month') {
            return request('cash-registers/analytics', { period });
        },
        getCashRegisterLogs() {
            return request('cash-registers/logs');
        },
        getCashRegisterNotifications(since) {
            return request('cash-registers/notifications', since ? { since } : {});
        },
        syncCashRegisterOffline(items) {
            return request('cash-registers/sync', {}, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ items }),
            });
        },

        getWmsDashboard() {
            return request('wms/dashboard');
        },
        getWmsAnalytics(period = 'month') {
            return request('wms/analytics', { period });
        },
        getWmsWarehouses(params = {}) {
            const q = {};
            if (params.status && params.status !== 'all') q.status = params.status;
            if (params.q) q.q = params.q;
            return request('wms/warehouses', q);
        },
        getWmsWarehouse(id) {
            return request(`wms/warehouses/${id}`);
        },
        createWmsWarehouse(data) {
            return request('wms/warehouses', {}, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
        },
        updateWmsWarehouse(id, data) {
            return request(`wms/warehouses/${id}`, {}, { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
        },
        deleteWmsWarehouse(id) {
            return request(`wms/warehouses/${id}`, {}, { method: 'DELETE' });
        },
        getWmsInventory(warehouseId, q, filter) {
            return request('wms/inventory', {
                warehouse_id: warehouseId,
                q: q || undefined,
                filter: filter && filter !== 'all' ? filter : undefined,
            });
        },
        getWmsInventoryItem(warehouseId, productId) {
            return request(`wms/inventory/${productId}`, { warehouse_id: warehouseId });
        },
        getWmsLocations(warehouseId) {
            return request('wms/locations', { warehouse_id: warehouseId });
        },
        createWmsLocation(data) {
            return request('wms/locations', {}, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
        },
        getWmsMovements(warehouseId, query = {}) {
            return request('wms/movements', { warehouse_id: warehouseId || undefined, ...query });
        },
        getWmsTransfers(warehouseId, status, q) {
            return request('wms/transfers', {
                warehouse_id: warehouseId || undefined,
                status: status && status !== 'all' ? status : undefined,
                q: q || undefined,
            });
        },
        getWmsTransfer(id) {
            return request(`wms/transfers/${id}`);
        },
        createWmsTransfer(data) {
            return request('wms/transfers', {}, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
        },
        approveWmsTransfer(id) {
            return request(`wms/transfers/approve/${id}`, {}, { method: 'POST' });
        },
        completeWmsTransfer(id) {
            return request(`wms/transfers/complete/${id}`, {}, { method: 'POST' });
        },
        rejectWmsTransfer(id) {
            return request(`wms/transfers/reject/${id}`, {}, { method: 'POST' });
        },
        getWmsReceipts(warehouseId, status) {
            return request('wms/receipts', { warehouse_id: warehouseId || undefined, status: status || undefined });
        },
        getWmsReceipt(id) {
            return request(`wms/receipts/${id}`);
        },
        createWmsReceipt(data) {
            return request('wms/receipts', {}, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
        },
        completeWmsReceipt(id) {
            return request(`wms/receipts/complete/${id}`, {}, { method: 'POST' });
        },
        getWmsDispatches(warehouseId, status, q) {
            return request('wms/dispatches', {
                warehouse_id: warehouseId || undefined,
                status: status || undefined,
                q: q || undefined,
            });
        },
        getWmsDispatch(id) {
            return request(`wms/dispatches/${id}`);
        },
        createWmsDispatch(data) {
            return request('wms/dispatches', {}, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
        },
        dispatchWmsOut(id) {
            return request(`wms/dispatches/dispatch/${id}`, {}, { method: 'POST' });
        },
        getWmsRequests(warehouseId, storeId, status, q) {
            return request('wms/requests', {
                warehouse_id: warehouseId || undefined,
                store_id: storeId || undefined,
                status: status || undefined,
                q: q || undefined,
            });
        },
        getWmsRequest(id) {
            return request(`wms/requests/${id}`);
        },
        createWmsRequest(data) {
            return request('wms/requests', {}, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
        },
        approveWmsRequest(id, role = 'manager') {
            return request(`wms/requests/approve/${id}`, {}, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ role }) });
        },
        rejectWmsRequest(id) {
            return request(`wms/requests/reject/${id}`, {}, { method: 'POST' });
        },
        getWmsBatches(warehouseId, status, q, days) {
            return request('wms/batches', {
                warehouse_id: warehouseId || undefined,
                status: status || undefined,
                q: q || undefined,
                days: days || undefined,
            });
        },
        getWmsExpiry(warehouseId, status, days, q) {
            return request('wms/batches', {
                warehouse_id: warehouseId || undefined,
                status: status || undefined,
                days: days || undefined,
                scope: 'expiry',
                q: q || undefined,
            });
        },
        getWmsBatch(id) {
            return request(`wms/batches/${id}`);
        },
        createWmsBatch(data) {
            return request('wms/batches', {}, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
        },
        updateWmsBatchStatus(id, status) {
            return request(`wms/batches/status/${id}`, {}, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ status }) });
        },
        getWmsAudits(warehouseId, status, q, auditType) {
            return request('wms/audits', {
                warehouse_id: warehouseId || undefined,
                status: status || undefined,
                q: q || undefined,
                audit_type: auditType || undefined,
            });
        },
        getWmsAudit(id) {
            return request(`wms/audits/${id}`);
        },
        createWmsAudit(data) {
            return request('wms/audits', {}, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
        },
        submitWmsAudit(id) {
            return request(`wms/audits/submit/${id}`, {}, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({}) });
        },
        approveWmsAudit(id) {
            return request(`wms/audits/approve/${id}`, {}, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({}) });
        },
        rejectWmsAudit(id) {
            return request(`wms/audits/reject/${id}`, {}, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({}) });
        },
        getWmsLogs(warehouseId, query = {}) {
            return request('wms/logs', { warehouse_id: warehouseId || undefined, ...query });
        },
        getWmsLog(id) {
            return request(`wms/logs/${id}`);
        },
        getWmsNotifications(since) {
            return request('wms/notifications', since ? { since } : {});
        },
        syncWmsOffline(items) {
            return request('wms/sync', {}, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ items }) });
        },

        trendHtml(pct, { positiveIsGood = true } = {}) {
            const i18n = (typeof window !== 'undefined' && window.ADMIN_I18N) || {};
            const vsLabel = i18n.vs_yesterday || 'vs yesterday';
            const neutralLabel = i18n.trend_neutral || `— ${vsLabel}`;
            if (pct === null || pct === undefined) {
                return `<span class="ad-trend ad-trend--neutral">${neutralLabel}</span>`;
            }
            const up = pct >= 0;
            const good = positiveIsGood ? up : !up;
            const cls = good ? 'positive' : 'negative';
            const icon = up ? 'trending_up' : 'trending_down';
            const sign = up ? '+' : '';
            return `<span class="ad-trend trend ${cls}"><span class="material-icons-round">${icon}</span>${sign}${pct}% ${vsLabel}</span>`;
        },
    };
})();
