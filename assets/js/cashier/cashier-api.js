/**
 * Client API caissier — appels centralisés avec session (credentials).
 */
const CashierAPI = (() => {
    const BASE = '../../api/v1/index.php';

    async function request(resource, query = {}, fetchOptions = {}) {
        const params = new URLSearchParams({ request: resource });
        Object.entries(query).forEach(([key, value]) => {
            if (value !== undefined && value !== null && value !== '') {
                params.set(key, String(value));
            }
        });

        const response = await fetch(`${BASE}?${params}`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', ...(fetchOptions.headers || {}) },
            ...fetchOptions,
        });

        const data = await response.json().catch(() => ({
            status: 'error',
            message: 'Réponse invalide du serveur',
        }));

        if (!response.ok && data.status !== 'error') {
            data.status = 'error';
            data.message = data.message || `Erreur HTTP ${response.status}`;
        }

        return data;
    }

    return {
        getDashboardStats() {
            return request('cashier/dashboard');
        },

        getContext() {
            return request('cashier/context');
        },

        getSales({ today = false, limit = 100 } = {}) {
            return request('sales', { today: today ? '1' : undefined, limit });
        },

        getSale(id) {
            return request(`sales/${id}`);
        },

        findByReceipt(receiptNo) {
            return request(`sales/receipt/${encodeURIComponent(receiptNo)}`);
        },

        getPosBootstrap() {
            return request('cashier/pos-bootstrap');
        },

        getProducts() {
            return request('inventory/products');
        },

        getCategories() {
            return request('inventory/categories');
        },

        scanBarcode(code) {
            return request(`inventory/scan/${encodeURIComponent(code)}`);
        },

        createSale(payload) {
            return request('sales', {}, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
        },

        processReturn(payload) {
            return request('cashier/return', {}, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
        },

        getProfile() {
            return request('cashier/profile');
        },

        updateProfile(payload) {
            return request('cashier/profile', {}, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
        },

        getCustomers({ q = '', limit = 200 } = {}) {
            return request('cashier/customers', { q: q || undefined, limit });
        },

        createCustomer(payload) {
            return request('cashier/customers', {}, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
        },

        updateCustomer(id, payload) {
            return request(`cashier/customers/${id}`, {}, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
        },

        deleteCustomer(id) {
            return request(`cashier/customers/${id}`, {}, { method: 'DELETE' });
        },

        formatCurrency(amount) {
            const cfg = (typeof window !== 'undefined' && window.POS_CONFIG) ? window.POS_CONFIG : {};
            const sym = (cfg.settings && cfg.settings.currency_symbol) || (cfg.store && cfg.store.currency) || 'FCFA';
            return `${Number(amount || 0).toLocaleString('fr-FR')} ${sym}`;
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
                split: 'Paiement mixte',
            };
            return map[method] || method || '—';
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
    };
})();
