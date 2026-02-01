const CACHE_NAME = 'attendance-app-v1';
const urlsToCache = [
    '/hr/attendance_login.php',
    '/hr/attendance_portal.php',
    '/assets/style.css',
    '/hr/icons/icon-192.png',
    '/hr/icons/icon-512.png'
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

// Handle push notifications (for future use)
self.addEventListener('push', event => {
    const options = {
        body: event.data ? event.data.text() : 'Time to mark your attendance!',
        icon: '/hr/icons/icon-192.png',
        badge: '/hr/icons/icon-72.png',
        vibrate: [100, 50, 100],
        data: {
            url: '/hr/attendance_portal.php'
        }
    };

    event.waitUntil(
        self.registration.showNotification('Attendance Reminder', options)
    );
});

// Handle notification click
self.addEventListener('notificationclick', event => {
    event.notification.close();
    event.waitUntil(
        clients.openWindow(event.notification.data.url || '/hr/attendance_portal.php')
    );
});
