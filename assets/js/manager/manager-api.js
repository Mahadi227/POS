/**
 * Manager supervision — API client
 */
const ManagerAPI = (() => {
    function apiBase() {
        return window.MANAGER_CONFIG?.api?.base || '../../api/v1/index.php';
    }

    async function request(resource, query = {}, fetchOptions = {}) {
        const params = new URLSearchParams({ request: resource });
        Object.entries(query).forEach(([key, value]) => {
            if (value !== undefined && value !== null && value !== '') {
                params.set(key, String(value));
            }
        });

        const base = apiBase();
        const url = base.includes('?') ? `${base}&${params}` : `${base}?${params}`;

        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', ...(fetchOptions.headers || {}) },
            ...fetchOptions,
        });

        const raw = await response.text();
        let data;
        try {
            data = raw ? JSON.parse(raw) : {};
        } catch {
            console.error('ManagerAPI invalid JSON:', raw.slice(0, 300));
            data = {
                status: 'error',
                message: 'Réponse invalide',
            };
        }

        if (!response.ok && data.status !== 'error') {
            data.status = 'error';
            data.message = data.message || `Erreur HTTP ${response.status}`;
        }

        return data;
    }

    return {
        getDashboard() {
            return request('manager/dashboard');
        },
        getLiveRegisters() {
            return request('manager/supervision/live');
        },
        getShifts() {
            return request('manager/supervision/shifts');
        },
        getTeamPerformance(params = {}) {
            const query = typeof params === 'string' ? { period: params } : { ...params };
            return request('manager/supervision/team', query);
        },
        getApprovals(type) {
            const query = type ? { type } : {};
            return request('manager/approvals', query);
        },
        approve(id, note) {
            return request(`manager/approvals/approve/${id}`, {}, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ note }),
            });
        },
        reject(id, note) {
            return request(`manager/approvals/reject/${id}`, {}, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ note }),
            });
        },
        getAuditTrail(params = {}) {
            return request('manager/reports/audit-trail', { ...params });
        },
        getInventoryAlerts(filter = 'all') {
            return request('manager/operations/inventory', { filter });
        },
        getSalesReview(params = {}) {
            return request('manager/operations/sales-review', { ...params });
        },
        getCashReconciliation(filter = 'open') {
            return request('manager/operations/cash-reconciliation', { filter });
        },
        getDailySummary(date) {
            return request('manager/reports/daily-summary', { date: date || undefined });
        },
        formatCurrency(amount) {
            const locale = window.MANAGER_CONFIG?.locale || 'fr-FR';
            const currency = window.MANAGER_CONFIG?.store?.currency || 'FCFA';
            return `${Number(amount || 0).toLocaleString(locale)} ${currency}`;
        },
        formatDate(value) {
            if (!value) return '—';
            const locale = window.MANAGER_CONFIG?.locale || 'fr-FR';
            return new Date(value).toLocaleString(locale, { dateStyle: 'short', timeStyle: 'short' });
        },
        formatRelative(value) {
            if (!value) return '—';
            const locale = window.MANAGER_CONFIG?.locale || 'fr-FR';
            const diffMs = Date.now() - new Date(value).getTime();
            const mins = Math.floor(diffMs / 60000);
            if (mins < 1) return locale.startsWith('fr') ? 'À l\'instant' : 'Just now';
            if (mins < 60) return locale.startsWith('fr') ? `Il y a ${mins} min` : `${mins} min ago`;
            const hours = Math.floor(mins / 60);
            if (hours < 24) return locale.startsWith('fr') ? `Il y a ${hours} h` : `${hours}h ago`;
            return this.formatDate(value);
        },
    };
})();
