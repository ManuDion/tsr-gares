const CACHE_NAME = 'tsr-gares-finance-v1';
const OFFLINE_URL = '/offline';

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => cache.addAll([
            OFFLINE_URL,
            '/assets/app.css',
            '/assets/logo-tsr.jpg',
            '/icons/icon-192.png',
            '/icons/icon-512.png',
        ]))
    );
});

self.addEventListener('fetch', event => {
    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request).catch(() => caches.match(OFFLINE_URL))
        );
    }
});
