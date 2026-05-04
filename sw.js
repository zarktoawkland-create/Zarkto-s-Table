self.addEventListener('install', (e) => {
    self.skipWaiting();
});

self.addEventListener('activate', (e) => {
    e.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', (e) => {
    // 基础的 Service Worker，不缓存，直接放行请求，仅用于满足 PWA 安装要求
    e.respondWith(fetch(e.request));
});