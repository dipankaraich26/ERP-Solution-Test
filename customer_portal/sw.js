const CACHE_NAME = 'customer-portal-v1';
const urlsToCache = [
    '/customer_portal/login.php',
    '/customer_portal/my_portal.php',
    '/customer_portal/my_orders.php',
    '/customer_portal/my_quotations.php',
    '/customer_portal/my_proforma.php',
    '/customer_portal/my_invoices.php',
    '/customer_portal/my_ledger.php',
    '/customer_portal/my_order_tracking.php',
    '/customer_portal/my_dockets.php',
    '/customer_portal/my_catalog.php',
    '/customer_portal/my_eway_bills.php',
    '/assets/style.css',
    '/customer_portal/icons/icon.php?size=192',
    '/customer_portal/icons/icon.php?size=512',
    '/customer_portal/offline.html'
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
                        return caches.match('/customer_portal/offline.html');
                    }
                });
            })
    );
});

self.addEventListener('push', event => {
    const data = event.data ? event.data.json() : {};
    event.waitUntil(
        self.registration.showNotification(data.title || 'Customer Portal', {
            body: data.body || 'You have a new update',
            icon: '/customer_portal/icons/icon.php?size=192',
            badge: '/customer_portal/icons/icon.php?size=72',
            vibrate: [100, 50, 100],
            data: { url: data.url || '/customer_portal/my_portal.php' }
        })
    );
});

self.addEventListener('notificationclick', event => {
    event.notification.close();
    if (event.action === 'dismiss') return;
    event.waitUntil(
        clients.openWindow(event.notification.data.url || '/customer_portal/my_portal.php')
    );
});
