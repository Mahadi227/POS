/**
 * Warehouse portal — offline queue (IndexedDB) + sync
 */
window.WarehouseOffline = (() => {
    const DB = 'warehouse_portal_offline';
    const STORE = 'queue';
    const VERSION = 1;

    function openDb() {
        return new Promise((resolve, reject) => {
            const req = indexedDB.open(DB, VERSION);
            req.onupgradeneeded = () => {
                const db = req.result;
                if (!db.objectStoreNames.contains(STORE)) {
                    db.createObjectStore(STORE, { keyPath: 'id', autoIncrement: true });
                }
            };
            req.onsuccess = () => resolve(req.result);
            req.onerror = () => reject(req.error);
        });
    }

    async function enqueue(action, payload) {
        const db = await openDb();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(STORE, 'readwrite');
            tx.objectStore(STORE).add({
                action,
                payload,
                created_at: new Date().toISOString(),
                status: 'pending',
            });
            tx.oncomplete = () => resolve(true);
            tx.onerror = () => reject(tx.error);
        });
    }

    async function listPending() {
        const db = await openDb();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(STORE, 'readonly');
            const req = tx.objectStore(STORE).getAll();
            req.onsuccess = () => resolve((req.result || []).filter((r) => r.status === 'pending'));
            req.onerror = () => reject(req.error);
        });
    }

    async function status() {
        const pending = await listPending();
        return { pending: pending.length, online: navigator.onLine };
    }

    async function sync() {
        if (!navigator.onLine) return { synced: 0, failed: 0 };
        const pending = await listPending();
        let synced = 0;
        let failed = 0;
        for (const item of pending) {
            try {
                const res = await fetch(`${window.WH_CONFIG?.api?.base}?request=wms/${item.action}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify(item.payload),
                });
                const json = await res.json();
                if (json.status === 'success') synced += 1;
                else failed += 1;
            } catch (_) {
                failed += 1;
            }
        }
        return { synced, failed };
    }

    window.addEventListener('online', () => sync());

    return { enqueue, listPending, status, sync };
})();
