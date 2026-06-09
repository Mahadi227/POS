const CACHE_NAME = 'retailpos-cache-v2';
const DYNAMIC_CACHE = 'retailpos-dynamic-v2';

const ASSETS_TO_CACHE = [
    '../public/pos.html',
    '../public/receipt.html',
    '../public/admin/index.html',
    '../public/admin/inventory.php',
    '../assets/css/pos.css',
    '../assets/css/admin.css',
    '../assets/js/app/pos.js',
    '../assets/js/app/sync.js',
    '../assets/js/admin/dashboard.js',
    '../assets/js/admin/inventory.js',
    'https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600&display=swap',
    'https://fonts.googleapis.com/icon?family=Material+Icons+Round',
    'https://unpkg.com/dexie/dist/dexie.js',
    'https://cdn.jsdelivr.net/npm/chart.js',
    'https://cdn.jsdelivr.net/npm/jsbarcode@3.11.0/dist/JsBarcode.all.min.js'
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                console.log('[Service Worker] Caching App Shell');
                return cache.addAll(ASSETS_TO_CACHE);
            })
    );
    self.skipWaiting();
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys => {
            return Promise.all(keys
                .filter(key => key !== CACHE_NAME && key !== DYNAMIC_CACHE)
                .map(key => caches.delete(key))
            );
        })
    );
    return self.clients.claim();
});

self.addEventListener('fetch', event => {
    // Exclude API calls from standard cache (handled by Sync Engine)
    if (event.request.url.includes('/api/v1/')) {
        event.respondWith(
            fetch(event.request).catch(() => {
                return new Response(JSON.stringify({ status: "offline", error: "Offline mode active" }), {
                    headers: { 'Content-Type': 'application/json' }
                });
            })
        );
        return;
    }

    // Cache-First with Network Fallback for static assets
    event.respondWith(
        caches.match(event.request).then(response => {
            return response || fetch(event.request).then(fetchRes => {
                return caches.open(DYNAMIC_CACHE).then(cache => {
                    // Only cache valid GET requests
                    if (event.request.method === 'GET' && fetchRes.status === 200) {
                        cache.put(event.request.url, fetchRes.clone());
                    }
                    return fetchRes;
                });
            });
        }).catch(() => {
            // Optional: return offline fallback page if HTML is requested
        })
    );
});

// Background Sync Event Listeners
self.addEventListener('sync', event => {
    console.log('[Service Worker] Background Sync Triggered', event.tag);
    if (event.tag === 'sync-offline-sales') {
        event.waitUntil(syncOfflineSales());
    }
});

// Sync Logic Executed by Service Worker
async function syncOfflineSales() {
    console.log('[Service Worker] Attempting to sync offline queue to backend...');
    
    // We notify the clients (the open tabs) to execute the sync script via postMessage,
    // because Dexie and the App state exist in the Window context.
    const clients = await self.clients.matchAll();
    if (clients && clients.length > 0) {
        clients.forEach(client => {
            client.postMessage({ action: 'TRIGGER_BACKGROUND_SYNC' });
        });
    }
}
