/* RetailPOS Marketing — minimal offline cache */
const CACHE = 'retailpos-mkt-v1';
const ASSETS = ['./', './index.php'];

self.addEventListener('install', (e) => {
    e.waitUntil(caches.open(CACHE).then((c) => c.addAll(ASSETS)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
    e.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', (e) => {
    if (e.request.method !== 'GET') return;
    e.respondWith(
        caches.match(e.request).then((r) => r || fetch(e.request).then((res) => {
            if (res.ok && e.request.url.includes('/assets/')) {
                const clone = res.clone();
                caches.open(CACHE).then((c) => c.put(e.request, clone));
            }
            return res;
        }))
    );
});
