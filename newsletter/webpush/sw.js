const CACHE_NAME = 'newsletter-push-v1';

self.addEventListener('install', function(event) {
    console.log('[Newsletter SW] Installing...');
    self.skipWaiting();
});

self.addEventListener('activate', function(event) {
    console.log('[Newsletter SW] Activated');
    event.waitUntil(clients.claim());
});

// processing push notifications
self.addEventListener('push', function(event) {
    console.log('[Newsletter SW] Push received');

    let data = {
        title: 'Newsletter',
        body: 'New content available',
        icon: '/favicon.ico',
        badge: '/badge.png',
        url: '/'
    };

    if (event.data) {
        try {
            const parsed = event.data.json();
            data = { ...data, ...parsed };
        } catch (e) {
            data.body = event.data.text();
        }
    }

    const options = {
        body: data.body,
        icon: data.icon,
        badge: data.badge,
        vibrate: [200, 100, 200],
        data: {
            url: data.url,
            dateOfArrival: Date.now()
        },
        actions: [
            {
                action: 'open',
                title: 'Read more'
            },
            {
                action: 'close',
                title: 'Dismiss'
            }
        ]
    };

    event.waitUntil(
        self.registration.showNotification(data.title, options)
    );
});

// processing clicks on notifications
self.addEventListener('notificationclick', function(event) {
    console.log('[Newsletter SW] Notification clicked:', event.action);

    event.notification.close();

    if (event.action === 'open') {
        const urlToOpen = event.notification.data.url || '/';

        event.waitUntil(
            clients.matchAll({
                type: 'window',
                includeUncontrolled: true
            }).then(function(clientList) {
                // checking if the page is already open
                for (let i = 0; i < clientList.length; i++) {
                    const client = clientList[i];
                    if (client.url === urlToOpen && 'focus' in client) {
                        return client.focus();
                    }
                }
                // if not, we open a new one.
                if (clients.openWindow) {
                    return clients.openWindow(urlToOpen);
                }
            })
        );
    }
});

// handling notification closures
self.addEventListener('notificationclose', function(event) {
    console.log('[Newsletter SW] Notification closed');
});

// caching for offline mode (optional)
self.addEventListener('fetch', function(event) {
    // you can add static caching
    if (event.request.url.includes('/newsletter/')) {
        event.respondWith(
            caches.match(event.request)
                .then(function(response) {
                    return response || fetch(event.request);
                })
        );
    }
});