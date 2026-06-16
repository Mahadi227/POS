// IndexedDB helper for caching translations for offline use
(function(){
    const DB_NAME = 'pos_i18n_v1';
    const STORE = 'translations';
    let db;
    function open(){
        return new Promise((res, rej)=>{
            const r = indexedDB.open(DB_NAME, 1);
            r.onupgradeneeded = e => { e.target.result.createObjectStore(STORE); };
            r.onsuccess = e => { db = e.target.result; res(db); };
            r.onerror = e => rej(e);
        });
    }
    async function put(key, value){
        if(!db) await open();
        return new Promise((res, rej)=>{
            const tx = db.transaction(STORE,'readwrite');
            tx.objectStore(STORE).put(value, key);
            tx.oncomplete = ()=>res(true);
            tx.onerror = e=>rej(e);
        });
    }
    async function get(key){
        if(!db) await open();
        return new Promise((res, rej)=>{
            const tx = db.transaction(STORE,'readonly');
            const req = tx.objectStore(STORE).get(key);
            req.onsuccess = ()=>res(req.result);
            req.onerror = e=>rej(e);
        });
    }

    window.I18NStorage = { open, put, get };
})();
