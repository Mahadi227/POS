// assets/js/service-worker.js
// Caches POS app shell and static assets

const CACHE_NAME = 'retailpos-cashier-v1';
const ASSETS_TO_CACHE = [
    '../../public/pos/index.html',
    '../../assets/css/pos.css',
    '../../assets/js/pos/app.js',
    '../../assets/js/pos/cart.js',
    '../../assets/js/pos/barcode.js',
    '../../assets/js/pos/checkout.js',
    '../../assets/js/pos/receipt.js',
    '../../assets/js/pos/offline-sync.js',
    '../../offline/fallback.html'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
        .then((cache) => {
            console.log('Opened cache');
            return cache.addAll(ASSETS_TO_CACHE);
        })
    );
});

self.addEventListener('fetch', (event) => {
    // Only intercept navigation & static assets, ignore API requests
    if (event.request.url.includes('/api/v1/')) {
        return; // API requests are handled by offline-sync.js
    }

    event.respondWith(
        caches.match(event.request)
            .then((response) => {
                // Cache hit - return response
                if (response) {
                    return response;
                }
                
                return fetch(event.request).catch(() => {
                    // If network fails and it's a navigation request, show fallback
                    if (event.request.mode === 'navigate') {
                        return caches.match('../../offline/fallback.html');
                    }
                });
            }
        )
    );
});

// Clean up old caches
self.addEventListener('activate', (event) => {
    const cacheWhitelist = [CACHE_NAME];
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheWhitelist.indexOf(cacheName) === -1) {
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
});
