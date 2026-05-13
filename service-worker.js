// ====================== SERVICE WORKER - NOTEAPP PRO ======================
const CACHE_NAME = 'noteapp-v1.7';   // Tăng version khi update lớn

// ====================== INSTALL ======================
self.addEventListener('install', event => {
    console.log('[SW] Installing Service Worker v1.7...');
    self.skipWaiting();
});

// ====================== ACTIVATE ======================
self.addEventListener('activate', event => {
    console.log('[SW] Activating...');

    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheName !== CACHE_NAME) {
                        console.log('[SW] Deleting old cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );

    self.clients.claim();
});

// ====================== FETCH ======================
self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);

    // Bỏ qua WebSocket
    if (url.protocol === 'ws:' || url.protocol === 'wss:') {
        return;
    }

    // Chỉ xử lý request cùng origin
    if (url.origin !== location.origin) {
        return;
    }

    // ==================== API & PHP - NETWORK FIRST ====================
    if (url.pathname.startsWith('/api/') || url.pathname.endsWith('.php')) {
        
        event.respondWith(
            fetch(event.request)
                .then(response => {
                    // Clone response để cache nếu cần sau này
                    return response;
                })
                .catch(error => {
                    console.warn('[SW] API Fetch Failed (Offline):', error);
                    
                    // Trả về response JSON offline để frontend biết
                    return new Response(
                        JSON.stringify({
                            success: false,
                            message: 'Bạn đang offline. Một số tính năng có thể không hoạt động.',
                            offline: true
                        }),
                        {
                            status: 503,
                            statusText: 'Service Unavailable',
                            headers: { 'Content-Type': 'application/json' }
                        }
                    );
                })
        );
        return;
    }

    // ==================== STATIC ASSETS - CACHE FIRST + NETWORK UPDATE ====================
    const isStatic = 
        url.pathname.endsWith('.css') ||
        url.pathname.endsWith('.js') ||
        url.pathname.endsWith('.png') ||
        url.pathname.endsWith('.jpg') ||
        url.pathname.endsWith('.jpeg') ||
        url.pathname.endsWith('.webp') ||
        url.pathname.endsWith('.svg') ||
        url.pathname.endsWith('.ico') ||
        url.pathname.endsWith('.json') ||
        url.pathname.endsWith('.manifest');

    if (isStatic) {
        event.respondWith(
            caches.open(CACHE_NAME).then(cache => {
                return cache.match(event.request).then(cachedResponse => {
                    const fetchPromise = fetch(event.request)
                        .then(networkResponse => {
                            if (networkResponse && networkResponse.status === 200) {
                                cache.put(event.request, networkResponse.clone());
                            }
                            return networkResponse;
                        })
                        .catch(() => cachedResponse); // Fallback to cache

                    return cachedResponse || fetchPromise;
                });
            })
        );
        return;
    }

    // Các request khác: Network Only
    event.respondWith(fetch(event.request));
});

// ====================== BACKGROUND SYNC ======================
self.addEventListener('sync', event => {
    if (event.tag === 'sync-notes') {
        console.log('[SW] Background Sync: sync-notes triggered');
        // Có thể implement sync logic sau khi có API sync chuyên dụng
        event.waitUntil(
            // TODO: Sync offline notes khi có kết nối
            Promise.resolve()
        );
    }
});

// ====================== PUSH NOTIFICATION (tương lai) ======================
self.addEventListener('push', event => {
    console.log('[SW] Push notification received');
});

console.log('[SW] NoteApp Service Worker v1.7 loaded successfully!');