/**
 * Accounting offline queue — IndexedDB cache + sync
 */
window.AccountingOffline = (() => {
    const DB_NAME = 'retailpos_accounting';
    const DB_VERSION = 1;
    const STORE = 'pending_entries';

    function openDb() {
        return new Promise((resolve, reject) => {
            if (!window.indexedDB) {
                reject(new Error('IndexedDB unavailable'));
                return;
            }
            const req = indexedDB.open(DB_NAME, DB_VERSION);
            req.onupgradeneeded = () => {
                const db = req.result;
                if (!db.objectStoreNames.contains(STORE)) {
                    db.createObjectStore(STORE, { keyPath: 'local_uuid' });
                }
            };
            req.onsuccess = () => resolve(req.result);
            req.onerror = () => reject(req.error);
        });
    }

    async function enqueue(action, payload) {
        const local_uuid = crypto.randomUUID?.() || `acc-${Date.now()}-${Math.random().toString(36).slice(2)}`;
        const entry = { local_uuid, action, payload, created_at: new Date().toISOString() };
        const db = await openDb();
        await new Promise((resolve, reject) => {
            const tx = db.transaction(STORE, 'readwrite');
            tx.objectStore(STORE).put(entry);
            tx.oncomplete = resolve;
            tx.onerror = () => reject(tx.error);
        });
        return local_uuid;
    }

    async function listPending() {
        const db = await openDb();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(STORE, 'readonly');
            const req = tx.objectStore(STORE).getAll();
            req.onsuccess = () => resolve(req.result || []);
            req.onerror = () => reject(req.error);
        });
    }

    async function remove(localUuid) {
        const db = await openDb();
        await new Promise((resolve, reject) => {
            const tx = db.transaction(STORE, 'readwrite');
            tx.objectStore(STORE).delete(localUuid);
            tx.oncomplete = resolve;
            tx.onerror = () => reject(tx.error);
        });
    }

    async function syncAll() {
        if (!navigator.onLine || !window.AdminAPI?.syncAccountingOffline) return { synced: 0 };
        const pending = await listPending();
        if (!pending.length) return { synced: 0 };
        const res = await AdminAPI.syncAccountingOffline(pending.map(({ action, payload, local_uuid }) => ({ action, payload, local_uuid })));
        if (res.status === 'success') {
            for (const item of pending) {
                await remove(item.local_uuid);
            }
        }
        return res;
    }

    window.addEventListener('online', () => { syncAll().catch(console.warn); });

    return { enqueue, listPending, syncAll };
})();
