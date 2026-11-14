'use strict';

/* Cauce · service worker de Web Push y shell offline. */

const SHELL_CACHE = 'cauce-shell-v4';
const SHELL_ASSETS = [
    '/assets/css/app.min.css',
    '/assets/js/app.min.js',
    '/assets/icons/bootstrap-icons.min.css',
    '/assets/img/icon-192.png',
    '/offline.html',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(SHELL_CACHE).then((cache) => cache.addAll(SHELL_ASSETS)).catch(() => undefined)
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(
            keys.filter((key) => key !== SHELL_CACHE).map((key) => caches.delete(key))
        )).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    // Nunca cachear HTML autenticado ni respuestas con datos financieros.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match('/offline.html'))
        );
        return;
    }

    if (url.pathname.startsWith('/assets/')) {
        event.respondWith(
            fetch(request).then((response) => {
                if (response.ok) {
                    const copy = response.clone();
                    caches.open(SHELL_CACHE).then((cache) => cache.put(request, copy));
                }

                return response;
            }).catch(() => caches.match(request))
        );
    }
});

self.addEventListener('push', (event) => {
    let payload = {
        title: 'Cauce',
        body: 'Tienes un aviso nuevo.',
        url: '/notificaciones',
    };

    if (event.data) {
        try {
            payload = Object.assign(payload, event.data.json());
        } catch (error) {
            payload.body = event.data.text();
        }
    }

    event.waitUntil(
        self.registration.showNotification(payload.title, {
            body: payload.body,
            icon: '/assets/img/icon-192.png',
            badge: '/assets/img/icon-192.png',
            data: { url: payload.url || '/notificaciones' },
        })
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = (event.notification.data && event.notification.data.url) || '/notificaciones';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
            for (const client of list) {
                if ('focus' in client) {
                    client.navigate(url);
                    return client.focus();
                }
            }

            return clients.openWindow(url);
        })
    );
});
