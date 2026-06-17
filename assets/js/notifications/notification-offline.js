/**
 * IndexedDB offline notification cache
 */
window.NotificationOffline = (() => {
    const DB = 'retailpos_notifications';
    const STORE = 'inbox';
    const VER = 1;

    function openDb() {
        return new Promise((resolve, reject) => {
            const req = indexedDB.open(DB, VER);
            req.onupgradeneeded = () => {
                const db = req.result;
                if (!db.objectStoreNames.contains(STORE)) {
                    db.createObjectStore(STORE, { keyPath: 'uuid' });
                }
            };
            req.onsuccess = () => resolve(req.result);
            req.onerror = () => reject(req.error);
        });
    }

    async function saveAll(items) {
        const db = await openDb();
        const tx = db.transaction(STORE, 'readwrite');
        items.forEach((item) => tx.objectStore(STORE).put(item));
        return new Promise((res, rej) => {
            tx.oncomplete = () => res();
            tx.onerror = () => rej(tx.error);
        });
    }

    async function getAll() {
        const db = await openDb();
        return new Promise((resolve, reject) => {
            const req = db.transaction(STORE, 'readonly').objectStore(STORE).getAll();
            req.onsuccess = () => resolve(req.result || []);
            req.onerror = () => reject(req.error);
        });
    }

    async function syncWithServer() {
        if (!navigator.onLine || typeof NotificationAPI?.sync !== 'function') return null;
        const local = await getAll();
        const res = await NotificationAPI.sync(local.map((n) => ({
            uuid: n.uuid,
            is_read: n.is_read,
        })));
        if (res.status === 'success' && res.data) {
            await saveAll(res.data);
        }
        return res;
    }

    window.addEventListener('online', () => syncWithServer());

    return { saveAll, getAll, syncWithServer };
})();
