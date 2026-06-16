/**
 * POS sync heartbeat — keeps branch online status accurate on all cashier pages.
 */
(() => {
    if (window.__cashierSyncHeartbeat) return;
    window.__cashierSyncHeartbeat = true;

    const storeId = window.POS_CONFIG?.store?.id ?? window.CASHIER_CONTEXT?.storeId ?? null;
    if (!storeId || typeof CashierAPI?.syncHeartbeat !== 'function') return;

    async function sendHeartbeat() {
        const payload = {
            store_id: storeId,
            pending_count: 0,
            is_online: navigator.onLine,
            page: (window.location.pathname || '').split('/').pop() || '',
        };
        try {
            if (window.db?.pending_sales) {
                payload.pending_count = await window.db.pending_sales.count();
            }
        } catch {
            /* Dexie not available on this page */
        }
        try {
            await CashierAPI.syncHeartbeat(payload);
        } catch (e) {
            console.warn('sync heartbeat', e);
        }
    }

    window.addEventListener('online', sendHeartbeat);
    window.addEventListener('offline', sendHeartbeat);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') sendHeartbeat();
    });

    sendHeartbeat();
    setInterval(sendHeartbeat, 60000);
})();
