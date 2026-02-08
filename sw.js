const CACHE_NAME = 'erp-admin-v1';
const urlsToCache = [
    '/login.php',
    '/index.php',
    '/ceo_dashboard.php',
    '/sales_orders/index.php',
    '/invoices/index.php',
    '/purchase/index.php',
    '/inventory/index.php',
    '/work_orders/index.php',
    '/hr/dashboard.php',
    '/assets/style.css',
    '/icons/icon.php?size=192',
    '/icons/icon.php?size=512',
    '/offline.html'
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(urlsToCache))
            .catch(err => console.log('Cache failed:', err))
    );
    self.skipWaiting();
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheName !== CACHE_NAME) {
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
    self.clients.claim();
});

self.addEventListener('fetch', event => {
    if (event.request.method !== 'GET') return;

    event.respondWith(
        fetch(event.request)
            .then(response => {
                const responseClone = response.clone();
                caches.open(CACHE_NAME).then(cache => cache.put(event.request, responseClone));
                return response;
            })
            .catch(() => {
                return caches.match(event.request).then(response => {
                    if (response) return response;
                    if (event.request.mode === 'navigate') {
                        return caches.match('/offline.html');
                    }
                });
            })
    );
});

self.addEventListener('push', event => {
    const data = event.data ? event.data.json() : {};
    event.waitUntil(
        self.registration.showNotification(data.title || 'ERP System', {
            body: data.body || 'You have a new update',
            icon: '/icons/icon.php?size=192',
            badge: '/icons/icon.php?size=72',
            vibrate: [100, 50, 100],
            data: { url: data.url || '/index.php' }
        })
    );
});

self.addEventListener('notificationclick', event => {
    event.notification.close();
    if (event.action === 'dismiss') return;
    event.waitUntil(
        clients.openWindow(event.notification.data.url || '/index.php')
    );
});
