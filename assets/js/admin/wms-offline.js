/**
 * WMS offline sync queue (IndexedDB)
 */
const WmsOffline = (() => {
    const DB = 'retailpos_wms';
    const STORE = 'sync_queue';
    let db;

    function open() {
        return new Promise((resolve, reject) => {
            if (db) { resolve(db); return; }
            const req = indexedDB.open(DB, 1);
            req.onupgradeneeded = () => req.result.createObjectStore(STORE, { keyPath: 'local_uuid' });
            req.onsuccess = () => { db = req.result; resolve(db); };
            req.onerror = () => reject(req.error);
        });
    }

    async function enqueue(item) {
        const database = await open();
        item.local_uuid = item.local_uuid || crypto.randomUUID();
        item.created_at = new Date().toISOString();
        database.transaction(STORE, 'readwrite').objectStore(STORE).put(item);
        return item.local_uuid;
    }

    async function drain() {
        if (!navigator.onLine || typeof AdminAPI?.syncWmsOffline !== 'function') return;
        const database = await open();
        const all = await new Promise((res, rej) => {
            const r = database.transaction(STORE, 'readonly').objectStore(STORE).getAll();
            r.onsuccess = () => res(r.result || []);
            r.onerror = () => rej(r.error);
        });
        if (!all.length) return;
        const result = await AdminAPI.syncWmsOffline(all);
        if (result.status === 'success') {
            const tx = database.transaction(STORE, 'readwrite');
            all.forEach((item) => tx.objectStore(STORE).delete(item.local_uuid));
        }
    }

    window.addEventListener('online', drain);
    document.addEventListener('DOMContentLoaded', () => {
        const prefs = JSON.parse(localStorage.getItem('wms_settings') || '{}');
        if (prefs.offline !== false) setInterval(drain, 60000);
    });

    return { enqueue, drain };
})();
