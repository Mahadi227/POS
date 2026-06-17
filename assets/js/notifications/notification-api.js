/**
 * Notification API client
 */
window.NotificationAPI = (() => {
    const base = () => window.NOTIF_API?.base || window.ADMIN_API?.base || '../../api/v1/index.php';

    async function request(path, options = {}, query = {}) {
        const params = new URLSearchParams({ request: `notifications/${path}`, ...query });
        const url = `${base()}?${params}`;
        const res = await fetch(url, {
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', ...(options.headers || {}) },
            ...options,
        });
        return res.json();
    }

    return {
        list(filters = {}) {
            return request('list', {}, filters);
        },
        unreadCount() {
            return request('unread-count');
        },
        markRead(ids) {
            return request('mark-read', { method: 'POST', body: JSON.stringify({ ids }) });
        },
        markAllRead() {
            return request('mark-all-read', { method: 'POST', body: JSON.stringify({}) });
        },
        archive(ids, archive = true) {
            return request('archive', { method: 'POST', body: JSON.stringify({ ids, archive }) });
        },
        pin(id, pinned) {
            return request('pin', { method: 'POST', body: JSON.stringify({ id, pinned }) });
        },
        delete(ids) {
            return request('delete', { method: 'POST', body: JSON.stringify({ ids }) });
        },
        meta() {
            return request('meta');
        },
        analytics() {
            return request('analytics');
        },
        sync(local) {
            return request('sync', { method: 'POST', body: JSON.stringify({ local }) });
        },
        preferences(data) {
            if (data) {
                return request('preferences', { method: 'POST', body: JSON.stringify(data) });
            }
            return request('preferences');
        },
    };
})();
