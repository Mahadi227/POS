/**
 * Client API module admin.
 */
const AdminAPI = (() => {
    const BASE = '../../api/v1/index.php';

    async function request(resource, query = {}, fetchOptions = {}) {
        const params = new URLSearchParams({ request: resource });
        Object.entries(query).forEach(([k, v]) => {
            if (v !== undefined && v !== null && v !== '') params.set(k, String(v));
        });

        const res = await fetch(`${BASE}?${params}`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', ...(fetchOptions.headers || {}) },
            ...fetchOptions,
        });

        const data = await res.json().catch(() => ({
            status: 'error',
            message: 'Réponse serveur invalide',
        }));

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
            const cfg = (typeof window !== 'undefined' && (window.INVENTORY_CONFIG || window.MANAGER_CONFIG || window.POS_CONFIG || window.ADMIN_PAGE)) || {};
            return (cfg.settings && cfg.settings.currency_symbol) || cfg.currency || (cfg.store && cfg.store.currency) || (window.ADMIN_PAGE && window.ADMIN_PAGE.currency) || 'FCFA';
        },

        formatCurrency(amount) {
            return `${Number(amount || 0).toLocaleString('fr-FR')} ${this.getCurrencySymbol()}`;
        },

        formatDate(dateString, options = { dateStyle: 'short', timeStyle: 'short' }) {
            if (!dateString) return '—';
            return new Date(dateString).toLocaleString('fr-FR', options);
        },

        paymentLabel(method) {
            const map = {
                cash: 'Espèces',
                card: 'Carte',
                mobile_money: 'Mobile Money',
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
            const map = {
                completed: 'Complété',
                cancelled: 'Annulé',
                refunded: 'Remboursé',
                pending: 'En attente',
            };
            return map[status] || status || '—';
        },

        statusClass(status) {
            if (status === 'completed') return 'success';
            if (status === 'cancelled' || status === 'refunded') return 'pending';
            return 'pending';
        },

        trendHtml(pct, { positiveIsGood = true } = {}) {
            if (pct === null || pct === undefined) {
                return '<span class="ad-trend ad-trend--neutral">— vs hier</span>';
            }
            const up = pct >= 0;
            const good = positiveIsGood ? up : !up;
            const cls = good ? 'positive' : 'negative';
            const icon = up ? 'trending_up' : 'trending_down';
            const sign = up ? '+' : '';
            return `<span class="ad-trend trend ${cls}"><span class="material-icons-round">${icon}</span>${sign}${pct}% vs hier</span>`;
        },
    };
})();
