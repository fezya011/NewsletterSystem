const CACHE_NAME = 'newsletter-push-v1';

self.addEventListener('install', function(event) {
    console.log('[Newsletter SW] Installing...');
    self.skipWaiting();
});

self.addEventListener('activate', function(event) {
    console.log('[Newsletter SW] Activated');
    event.waitUntil(clients.claim());
});

// Обработка push уведомлений
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

// Обработка кликов по уведомлениям
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
                // Проверяем, открыта ли уже страница
                for (let i = 0; i < clientList.length; i++) {
                    const client = clientList[i];
                    if (client.url === urlToOpen && 'focus' in client) {
                        return client.focus();
                    }
                }
                // Если нет - открываем новую
                if (clients.openWindow) {
                    return clients.openWindow(urlToOpen);
                }
            })
        );
    }
});

// Обработка закрытия уведомлений
self.addEventListener('notificationclose', function(event) {
    console.log('[Newsletter SW] Notification closed');
});

// Кэширование для офлайн режима (опционально)
self.addEventListener('fetch', function(event) {
    // Можно добавить кэширование статики
    if (event.request.url.includes('/newsletter/')) {
        event.respondWith(
            caches.match(event.request)
                .then(function(response) {
                    return response || fetch(event.request);
                })
        );
    }
});