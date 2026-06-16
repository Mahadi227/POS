/**
 * IndexedDB offline queue for cash register movements
 */
window.CashRegisterOffline = (() => {
    const DB_NAME = 'RetailPOS_CashRegisters';
    const STORE = 'pending_movements';

    function openDb() {
        return new Promise((resolve, reject) => {
            const req = indexedDB.open(DB_NAME, 1);
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

    async function queue(item) {
        const db = await openDb();
        const payload = {
            local_uuid: crypto.randomUUID(),
            created_at: new Date().toISOString(),
            sync_status: 'pending',
            ...item,
        };
        await new Promise((resolve, reject) => {
            const tx = db.transaction(STORE, 'readwrite');
            tx.objectStore(STORE).put(payload);
            tx.oncomplete = () => resolve();
            tx.onerror = () => reject(tx.error);
        });
        return payload.local_uuid;
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

    async function clearSynced(ids) {
        const db = await openDb();
        const tx = db.transaction(STORE, 'readwrite');
        const store = tx.objectStore(STORE);
        ids.forEach((id) => store.delete(id));
        return new Promise((resolve) => { tx.oncomplete = () => resolve(); });
    }

    async function sync() {
        if (!navigator.onLine || typeof AdminAPI === 'undefined') return;
        const pending = await listPending();
        if (!pending.length) return;
        const res = await AdminAPI.syncCashRegisterOffline(pending);
        if (res.status === 'success') {
            await clearSynced(pending.map((p) => p.local_uuid));
        }
    }

    window.addEventListener('online', () => { sync().catch(console.error); });

    return { queue, listPending, sync };
})();
