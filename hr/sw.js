const CACHE_NAME = 'employee-portal-v2';
const urlsToCache = [
    '/hr/attendance_login.php',
    '/hr/attendance_portal.php',
    '/hr/my_tasks.php',
    '/hr/my_payslip.php',
    '/hr/my_calendar.php',
    '/hr/my_tada.php',
    '/hr/my_advance.php',
    '/assets/style.css',
    '/hr/icons/icon.php?size=192',
    '/hr/icons/icon.php?size=512',
    '/hr/offline.html'
];

// Install service worker
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                console.log('Opened cache');
                return cache.addAll(urlsToCache);
            })
            .catch(err => {
                console.log('Cache failed:', err);
            })
    );
    self.skipWaiting();
});

// Activate service worker
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheName !== CACHE_NAME) {
                        console.log('Deleting old cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
    self.clients.claim();
});

// Fetch strategy: Network first, fallback to cache
self.addEventListener('fetch', event => {
    // Skip non-GET requests
    if (event.request.method !== 'GET') {
        return;
    }

    event.respondWith(
        fetch(event.request)
            .then(response => {
                // Clone the response
                const responseClone = response.clone();

                // Cache the fetched response
                caches.open(CACHE_NAME)
                    .then(cache => {
                        cache.put(event.request, responseClone);
                    });

                return response;
            })
            .catch(() => {
                // If network fails, try cache
                return caches.match(event.request)
                    .then(response => {
                        if (response) {
                            return response;
                        }

                        // Return offline page if available
                        if (event.request.mode === 'navigate') {
                            return caches.match('/hr/offline.html');
                        }
                    });
            })
    );
});

// Handle push notifications
self.addEventListener('push', event => {
    const data = event.data ? event.data.json() : {};
    const options = {
        body: data.body || 'You have a new notification from Employee Portal',
        icon: '/hr/icons/icon.php?size=192',
        badge: '/hr/icons/icon.php?size=72',
        vibrate: [100, 50, 100],
        data: {
            url: data.url || '/hr/attendance_portal.php'
        },
        actions: [
            { action: 'open', title: 'Open' },
            { action: 'dismiss', title: 'Dismiss' }
        ]
    };

    event.waitUntil(
        self.registration.showNotification(data.title || 'Employee Portal', options)
    );
});

// Handle notification click
self.addEventListener('notificationclick', event => {
    event.notification.close();
    if (event.action === 'dismiss') return;
    event.waitUntil(
        clients.openWindow(event.notification.data.url || '/hr/attendance_portal.php')
    );
});
