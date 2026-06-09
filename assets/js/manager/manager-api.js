/**
 * Manager supervision — API client
 */
const ManagerAPI = (() => {
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
            message: 'Réponse invalide',
        }));

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
        getApprovals(type) {
            return request(type ? `manager/approvals/${type}` : 'manager/approvals');
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
        getAuditTrail() {
            return request('manager/audit');
        },
        formatCurrency(amount) {
            return `${Number(amount || 0).toLocaleString('fr-FR')} FCFA`;
        },
        formatDate(value) {
            if (!value) return '—';
            return new Date(value).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' });
        },
    };
})();
