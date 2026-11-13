/*
 * Service worker van BarberCuts (docs/BOUWOPDRACHT.md §10).
 * Cachet alleen de app-shell en statische bestanden. Pagina's en afspraakdata komen altijd van het netwerk;
 * zonder verbinding verschijnt de offline-pagina.
 */
const CACHE = 'barbercuts-static-v3';
const SHELL = ['/offline.html', '/icons/icon-192.png', '/icons/icon-512.png'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    // Pagina's: altijd het netwerk, met de offline-pagina als terugval. Nooit pagina's of data bewaren.
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match('/offline.html')));

        return;
    }

    // Statische bestanden met een hash in de naam (Vite) en iconen: eerst de cache.
    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/')) {
        event.respondWith(
            caches.match(request).then((cached) => cached ?? fetch(request).then((response) => {
                if (response.ok) {
                    const copy = response.clone();
                    caches.open(CACHE).then((cache) => cache.put(request, copy));
                }

                return response;
            })),
        );
    }
});

// Web push (§7.6): toon de melding en open de afspraak of agenda bij een tik.
self.addEventListener('push', (event) => {
    let payload = {};

    try {
        payload = event.data ? event.data.json() : {};
    } catch (error) {
        payload = { title: 'BarberCuts', body: event.data ? event.data.text() : '' };
    }

    event.waitUntil(self.registration.showNotification(payload.title || 'BarberCuts', {
        body: payload.body || '',
        icon: payload.icon || '/icons/icon-192.png',
        badge: '/icons/icon-192.png',
        tag: payload.tag,
        data: payload.data || {},
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = new URL((event.notification.data && event.notification.data.url) || '/', self.location.origin).href;

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
            const open = clients.find((client) => 'focus' in client);

            return open ? open.navigate(target).then((client) => client && client.focus()) : self.clients.openWindow(target);
        }),
    );
});
