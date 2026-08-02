const CACHE_VERSION = 'z-coc-shell-v1';
const APP_SHELL = [
    './',
    './index.html',
    './Library/index.html',
    './Workshop/index.html',
    './404.html',
    './manifest.json',
    './favicon.svg'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_VERSION)
            .then((cache) => cache.addAll(APP_SHELL))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys.filter((key) => key !== CACHE_VERSION).map((key) => caches.delete(key))
            ))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET' || request.headers.has('range')) return;

    const url = new URL(request.url);
    if (url.origin === self.location.origin && url.pathname.endsWith('.php')) return;

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    if (response.ok) {
                        caches.open(CACHE_VERSION).then((cache) => cache.put(request, response.clone()));
                    }
                    return response;
                })
                .catch(async () => (
                    await caches.match(request)
                    || await caches.match('./index.html')
                ))
        );
        return;
    }

    const cacheableDestination = ['script', 'style', 'font', 'image', 'audio'].includes(request.destination);
    if (!cacheableDestination) return;

    event.respondWith(
        caches.match(request).then((cached) => {
            const network = fetch(request).then((response) => {
                if (response.ok || response.type === 'opaque') {
                    caches.open(CACHE_VERSION).then((cache) => cache.put(request, response.clone()));
                }
                return response;
            });
            return cached || network;
        })
    );
});
