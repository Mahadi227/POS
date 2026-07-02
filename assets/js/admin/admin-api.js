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
            const cfg = (typeof window !== 'undefined' && (window.WH_CONFIG || window.INVENTORY_CONFIG || window.MANAGER_CONFIG || window.POS_CONFIG || window.ADMIN_CONFIG || window.ADMIN_PAGE)) || {};
            return cfg.currency
                || (cfg.settings && cfg.settings.currency_symbol)
                || (cfg.store && cfg.store.currency)
                || (window.ADMIN_PAGE && window.ADMIN_PAGE.currency)
                || 'FCFA';
        },

        getLocale() {
            const cfg = (typeof window !== 'undefined' && (window.WH_CONFIG || window.ADMIN_CONFIG || window.INVENTORY_CONFIG || window.MANAGER_CONFIG || window.POS_CONFIG)) || {};
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

        updateCategory(id, data) {
            return this.inventory(`categories/${id}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
            });
        },

        deleteCategory(id) {
            return this.inventory(`categories/${id}`, { method: 'DELETE' });
        },

        importProducts(data) {
            return this.inventory('import', {
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

        updateSale(id, data) {
            return request(`sales/${id}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
            });
        },

        cancelSale(id) {
            return this.updateSale(id, { status: 'cancelled' });
        },

        deleteSale(id) {
            return request(`sales/${id}`, { method: 'DELETE' });
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
            const q = {};
            if (query.type && query.type !== 'all') q.type = query.type;
            if (query.from) q.from = query.from;
            if (query.to) q.to = query.to;
            if (query.q) q.q = query.q;
            if (query.register_id) q.register_id = query.register_id;
            return request('cash-registers/movements', q);
        },
        getCashReconciliations(params = {}) {
            const q = {};
            if (params.status && params.status !== 'all') q.status = params.status;
            if (params.from) q.from = params.from;
            if (params.to) q.to = params.to;
            if (params.q) q.q = params.q;
            if (params.limit) q.limit = params.limit;
            return request('cash-registers/reconciliation', q);
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
        getCashTransfers(params = {}) {
            if (typeof params === 'string' || params === null) {
                params = params ? { status: params } : {};
            }
            const q = {};
            if (params.status && params.status !== 'all') q.status = params.status;
            if (params.from) q.from = params.from;
            if (params.to) q.to = params.to;
            if (params.q) q.q = params.q;
            return request('cash-registers/transfers', q);
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
        getCashRegisterSessions(params = {}) {
            if (typeof params === 'string' || params === null) {
                params = params ? { status: params } : {};
            }
            const q = {};
            if (params.status && params.status !== 'all') q.status = params.status;
            if (params.shift_type && params.shift_type !== 'all') q.shift_type = params.shift_type;
            if (params.from) q.from = params.from;
            if (params.to) q.to = params.to;
            if (params.q) q.q = params.q;
            return request('cash-registers/sessions', q);
        },
        getCashRegisterHistory(query = {}) {
            return request('cash-registers/history', query);
        },
        getCashRegisterAnalytics(period = 'month') {
            return request('cash-registers/analytics', { period });
        },
        getCashRegisterLogs(params = {}) {
            const q = {};
            if (params.from) q.from = params.from;
            if (params.to) q.to = params.to;
            if (params.action && params.action !== 'all') q.action = params.action;
            if (params.q) q.q = params.q;
            if (params.limit) q.limit = params.limit;
            return request('cash-registers/logs', q);
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

        getAccountingDashboard(query = {}) {
            return request('accounting/dashboard', query);
        },
        getAccounting(path, query = {}) {
            return request('accounting/' + path, query);
        },
        postAccounting(path, data = {}, subPath = '') {
            const resource = subPath ? `accounting/${path}/${subPath}` : `accounting/${path}`;
            return request(resource, {}, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
            });
        },
        syncAccountingOffline(items) {
            return request('accounting/sync', {}, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ items }),
            });
        },

        getWmsDashboard() {
            return request('wms/dashboard');
        },
        getWarehousePortalDashboard(period = 'week', query = {}) {
            const q = { period, ...query };
            return request('warehouse/dashboard', q);
        },
        getWarehousePortalCalendar(year, month, types = '') {
            const q = { year, month };
            if (types) q.types = types;
            return request('warehouse/calendar', q);
        },
        warehousePortalSearch(q) {
            return request('warehouse/search', { q });
        },
        getWarehousePortalModule(module, endpoint, params = {}) {
            return request('warehouse/module', { module, endpoint, ...params });
        },
        getWarehouseProfile(params = {}) {
            return request('warehouse/profile', params);
        },
        updateWarehouseProfile(data) {
            return request('warehouse/profile', {}, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': data.csrf_token || '' },
                body: JSON.stringify(data),
            });
        },
        updateWarehouseProfilePassword(data) {
            return request('warehouse/profile/password', {}, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': data.csrf_token || '' },
                body: JSON.stringify(data),
            });
        },
        updateWarehouseProfilePreferences(data) {
            return request('warehouse/profile/preferences', {}, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': data.csrf_token || '' },
                body: JSON.stringify(data),
            });
        },
        updateWarehouseProfileNotifications(data) {
            return request('warehouse/profile/notifications', {}, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': data.csrf_token || '' },
                body: JSON.stringify(data),
            });
        },
        getWarehouseProfileLoginHistory(params = {}) {
            return request('warehouse/profile/login-history', params);
        },
        getWarehouseProfileActivities(params = {}) {
            return request('warehouse/profile/activities', params);
        },
        logoutWarehouseOtherDevices(data = {}) {
            return request('warehouse/profile/logout-devices', {}, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': data.csrf_token || '' },
                body: JSON.stringify(data),
            });
        },
        async uploadWarehouseProfileAvatar(file, csrfToken) {
            const fd = new FormData();
            fd.append('avatar', file);
            const base = apiBase();
            const params = new URLSearchParams({ request: 'warehouse/profile/avatar' });
            const url = base.includes('?') ? `${base}&${params}` : `${base}?${params}`;
            const res = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-CSRF-Token': csrfToken || '' },
                body: fd,
            });
            const text = await res.text();
            try { return text ? JSON.parse(text) : {}; } catch { return { status: 'error', message: 'Invalid response' }; }
        },
        deleteWarehouseProfileAvatar(csrfToken) {
            return request('warehouse/profile/avatar', { csrf_token: csrfToken }, {
                method: 'DELETE',
                headers: { 'X-CSRF-Token': csrfToken || '' },
            });
        },
        getWarehouseSettings(warehouseId, params = {}) {
            return request('warehouse/settings', { warehouse_id: warehouseId, ...params });
        },
        saveWarehouseSettings(warehouseId, data) {
            return request('warehouse/settings', { warehouse_id: warehouseId }, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': data.csrf_token || '' },
                body: JSON.stringify(data),
            });
        },
        resetWarehouseSettings(warehouseId, section, csrfToken) {
            return request('warehouse/settings/reset', { warehouse_id: warehouseId }, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken || '' },
                body: JSON.stringify({ section, csrf_token: csrfToken }),
            });
        },
        validateWarehouseSettings(warehouseId, data) {
            return request('warehouse/settings/validate', { warehouse_id: warehouseId }, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
            });
        },
        getWarehouseSettingsAudit(warehouseId, params = {}) {
            return request('warehouse/settings/audit', { warehouse_id: warehouseId, ...params });
        },
        getWarehouseHelp(params = {}) {
            return request('warehouse/help', params);
        },
        searchWarehouseHelp(q, params = {}) {
            return request('warehouse/help/search', { q, ...params });
        },
        getWarehouseHelpArticle(slug, params = {}) {
            return request('warehouse/help/article', { slug, ...params });
        },
        getWarehouseHelpManual(slug, params = {}) {
            return request('warehouse/help/manual', { slug, ...params });
        },
        async createWarehouseHelpTicket(formData, csrfToken) {
            const base = apiBase();
            const params = new URLSearchParams({ request: 'warehouse/help/ticket' });
            const url = base.includes('?') ? `${base}&${params}` : `${base}?${params}`;
            const res = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-CSRF-Token': csrfToken || '' },
                body: formData,
            });
            const text = await res.text();
            try { return text ? JSON.parse(text) : {}; } catch { return { status: 'error', message: 'Invalid response' }; }
        },
        getWmsAnalytics(period = 'month') {
            return request('wms/analytics', { period });
        },
        getWmsWarehousePerformance(params = {}) {
            return request('wms/warehouse-performance', params);
        },
        getWmsInventoryValuation(params = {}) {
            const q = { ...params };
            Object.keys(q).forEach((k) => {
                if (q[k] === '' || q[k] == null || q[k] === 'all') delete q[k];
            });
            return request('wms/inventory-valuation', q);
        },
        getWmsDamageReport(params = {}) {
            const q = { ...params };
            Object.keys(q).forEach((k) => {
                if (q[k] === '' || q[k] == null || q[k] === 'all') delete q[k];
            });
            return request('wms/damage-report', q);
        },
        getWmsExpiryReport(params = {}) {
            const q = { ...params };
            Object.keys(q).forEach((k) => {
                if (q[k] === '' || q[k] == null || q[k] === 'all') delete q[k];
            });
            return request('wms/expiry-report', q);
        },
        getWmsWarehouses(params = {}) {
            const q = { ...params };
            if (q.status === 'all') delete q.status;
            if (q.type === 'all') delete q.type;
            if (!q.q) delete q.q;
            if (!q.store_id) delete q.store_id;
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
        getWmsProducts(params = {}) {
            return request('wms/products', params);
        },
        getWmsProduct(id, params = {}) {
            return request(`wms/products/${id}`, params);
        },
        getWmsStockLevels(params = {}) {
            return request('wms/stock-levels', params);
        },
        getWmsLocations(params = {}) {
            const q = typeof params === 'object' && params !== null ? { ...params } : { warehouse_id: params };
            if (q.status === 'all') delete q.status;
            if (q.zone === 'all') delete q.zone;
            if (!q.q) delete q.q;
            return request('wms/locations', q);
        },
        createWmsLocation(data) {
            return request('wms/locations', {}, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
        },
        getWmsMovements(warehouseId, query = {}) {
            return request('wms/movements', { warehouse_id: warehouseId || undefined, ...query });
        },
        getWmsAdjustments(params = {}) {
            return request('wms/adjustments', params);
        },
        createWmsAdjustment(data) {
            return request('wms/adjustments', {}, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
        },
        getWmsStoreNetwork(params = {}) {
            return request('wms/store-network', params);
        },
        getWmsStoreNetworkWarehouses(storeId) {
            return request(`wms/store-network/${storeId}`);
        },
        getWmsTransfers(warehouseIdOrParams, status, q) {
            const params = typeof warehouseIdOrParams === 'object' && warehouseIdOrParams !== null
                ? warehouseIdOrParams
                : {
                    warehouse_id: warehouseIdOrParams || undefined,
                    status: status && status !== 'all' ? status : undefined,
                    q: q || undefined,
                };
            return request('wms/transfers', params);
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
        getWmsReceipts(params = {}) {
            const q = typeof params === 'object' && params !== null ? { ...params } : { warehouse_id: params, status: arguments[1] };
            if (!q.warehouse_id) delete q.warehouse_id;
            if (!q.status || q.status === 'all') delete q.status;
            if (!q.scope) delete q.scope;
            if (!q.q) delete q.q;
            if (!q.date_from) delete q.date_from;
            if (!q.date_to) delete q.date_to;
            return request('wms/receipts', q);
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
        inspectWmsReceipt(id) {
            return request(`wms/receipts/inspect/${id}`, {}, { method: 'POST' });
        },
        acceptWmsReceipt(id) {
            return request(`wms/receipts/accept/${id}`, {}, { method: 'POST' });
        },
        rejectWmsReceipt(id) {
            return request(`wms/receipts/reject/${id}`, {}, { method: 'POST' });
        },
        saveWmsReceiptInspection(id, data) {
            return request(`wms/receipts/inspection/${id}`, {}, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
            });
        },
        getWmsPurchaseOrders(params = {}) {
            const q = typeof params === 'object' && params !== null ? { ...params } : {};
            if (!q.warehouse_id) delete q.warehouse_id;
            if (!q.status || q.status === 'all') delete q.status;
            if (!q.q) delete q.q;
            return request('wms/purchase-orders', q);
        },
        getWmsPurchaseOrder(id) {
            return request(`wms/purchase-orders/${id}`);
        },
        createWmsPurchaseOrder(data) {
            return request('wms/purchase-orders', {}, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
        },
        submitWmsPurchaseOrder(id) {
            return request(`wms/purchase-orders/submit/${id}`, {}, { method: 'POST' });
        },
        approveWmsPurchaseOrder(id) {
            return request(`wms/purchase-orders/approve/${id}`, {}, { method: 'POST' });
        },
        cancelWmsPurchaseOrder(id) {
            return request(`wms/purchase-orders/cancel/${id}`, {}, { method: 'POST' });
        },
        receiveWmsPurchaseOrder(id) {
            return request(`wms/purchase-orders/receive/${id}`, {}, { method: 'POST' });
        },
        getWmsDispatches(warehouseIdOrParams, status, q) {
            const params = typeof warehouseIdOrParams === 'object' && warehouseIdOrParams !== null
                ? { ...warehouseIdOrParams }
                : {
                    warehouse_id: warehouseIdOrParams || undefined,
                    status: status || undefined,
                    q: q || undefined,
                };
            if (!params.warehouse_id) delete params.warehouse_id;
            if (!params.status || params.status === 'all') delete params.status;
            if (!params.q) delete params.q;
            if (!params.date_from) delete params.date_from;
            if (!params.date_to) delete params.date_to;
            if (!params.scope) delete params.scope;
            if (!params.limit) delete params.limit;
            if (!params.offset && params.offset !== 0) delete params.offset;
            if (!params.days) delete params.days;
            return request('wms/dispatches', params);
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
        updateWmsDispatchStatus(id, status) {
            return request(`wms/dispatches/status/${id}`, {}, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ status }),
            });
        },
        getWmsRequests(warehouseIdOrParams, storeId, status, q) {
            const params = typeof warehouseIdOrParams === 'object' && warehouseIdOrParams !== null
                ? warehouseIdOrParams
                : {
                    warehouse_id: warehouseIdOrParams || undefined,
                    store_id: storeId || undefined,
                    status: status || undefined,
                    q: q || undefined,
                };
            return request('wms/requests', params);
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
        getWmsBatches(warehouseIdOrParams, status, q, days) {
            const params = typeof warehouseIdOrParams === 'object' && warehouseIdOrParams !== null
                ? warehouseIdOrParams
                : {
                    warehouse_id: warehouseIdOrParams || undefined,
                    status: status || undefined,
                    q: q || undefined,
                    days: days || undefined,
                };
            return request('wms/batches', params);
        },
        getWmsExpiry(warehouseIdOrParams, status, days, q) {
            const params = typeof warehouseIdOrParams === 'object' && warehouseIdOrParams !== null
                ? warehouseIdOrParams
                : {
                    warehouse_id: warehouseIdOrParams || undefined,
                    status: status || undefined,
                    days: days || undefined,
                    q: q || undefined,
                };
            return request('wms/batches', { ...params, scope: 'expiry' });
        },
        getWmsInventoryReport(params = {}) {
            const q = { ...params };
            Object.keys(q).forEach((k) => {
                if (q[k] === '' || q[k] == null || q[k] === 'all') delete q[k];
            });
            return request('wms/inventory-report', q);
        },
        postWmsInventoryReportAudit(data) {
            return request('wms/inventory-report/audit', {}, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
            });
        },
        postWmsInventoryReportSchedule(data) {
            return request('wms/inventory-report/schedule', {}, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
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
        getWmsAudits(warehouseIdOrParams, status, q, auditType) {
            const params = typeof warehouseIdOrParams === 'object' && warehouseIdOrParams !== null
                ? { ...warehouseIdOrParams }
                : {
                    warehouse_id: warehouseIdOrParams || undefined,
                    status: status || undefined,
                    q: q || undefined,
                    audit_type: auditType || undefined,
                };
            if (!params.warehouse_id) delete params.warehouse_id;
            if (!params.status || params.status === 'all') delete params.status;
            if (!params.q) delete params.q;
            if (!params.audit_type || params.audit_type === 'all') delete params.audit_type;
            return request('wms/audits', params);
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
            const q = typeof warehouseId === 'object' && warehouseId !== null
                ? { ...warehouseId }
                : { warehouse_id: warehouseId || undefined, ...query };
            if (!q.warehouse_id) delete q.warehouse_id;
            if (!q.q) delete q.q;
            if (q.action === '' || q.action === 'all') delete q.action;
            if (q.entity_type === '' || q.entity_type === 'all') delete q.entity_type;
            return request('wms/logs', q);
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

        getWmsSyncMonitor() {
            return request('wms/sync/monitor');
        },

        getWmsSyncWarehouses() {
            return request('wms/sync/warehouses');
        },

        getWmsSyncPending(warehouseId) {
            return request('wms/sync/pending', warehouseId ? { warehouse_id: warehouseId } : {});
        },

        getWmsSyncConflicts(warehouseId) {
            return request('wms/sync/conflicts', warehouseId ? { warehouse_id: warehouseId } : {});
        },

        resolveWmsSyncItem(id, data) {
            return request(`wms/sync/resolve/${id}`, {}, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
            });
        },

        getNotifications(query = {}) {
            return request('notifications/list', query);
        },
        getNotificationUnreadCount() {
            return request('notifications/unread-count');
        },
        markNotificationsRead(ids) {
            return request('notifications/mark-read', {}, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ids }),
            });
        },
        markAllNotificationsRead() {
            return request('notifications/mark-all-read', {}, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({}),
            });
        },
        getNotificationMeta() {
            return request('notifications/meta');
        },
        archiveNotifications(ids, archive = true) {
            return request('notifications/archive', {}, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ids, archive }),
            });
        },
        pinNotification(id, pinned) {
            return request('notifications/pin', {}, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id, pinned }),
            });
        },

        getWarehouseNotifications(query = {}) {
            return request('warehouse/notifications/list', query);
        },
        getWarehouseNotificationUnreadCount() {
            return request('warehouse/notifications/unread-count');
        },
        getWarehouseNotificationMeta() {
            return request('warehouse/notifications/meta');
        },
        markWarehouseNotificationsRead(ids) {
            return request('warehouse/notifications/mark-read', {}, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ids }),
            });
        },
        markAllWarehouseNotificationsRead() {
            return request('warehouse/notifications/mark-all-read', {}, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({}),
            });
        },
        archiveWarehouseNotifications(ids, archive = true) {
            return request('warehouse/notifications/archive', {}, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ids, archive }),
            });
        },
        pinWarehouseNotification(id, pinned) {
            return request('warehouse/notifications/pin', {}, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id, pinned }),
            });
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
