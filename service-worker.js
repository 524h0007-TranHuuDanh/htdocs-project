// ====================== SERVICE WORKER - NOTEAPP PRO ======================
const CACHE_NAME = 'noteapp-v1.6';
// ====================== INSTALL ======================
self.addEventListener('install', event => {
    console.log('[SW] Installing...');
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
                        console.log('[SW] Removing old cache:', cacheName);
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

    // Chỉ xử lý GET
    if (event.request.method !== 'GET') {
        return;
    }

    const url = new URL(event.request.url);

    // Chỉ xử lý localhost
    if (url.origin !== location.origin) {
        return;
    }

    // Bỏ qua websocket
    if (
        url.protocol === 'ws:' ||
        url.protocol === 'wss:'
    ) {
        return;
    }

    // API + PHP = network only
    if (
        url.pathname.startsWith('/api/') ||
        url.pathname.endsWith('.php')
    ) {

        event.respondWith(
            fetch(event.request)
                .catch(error => {

                    console.error('[SW] API Fetch Failed:', error);

                    return new Response(
                        JSON.stringify({
                            success: false,
                            offline: true
                        }),
                        {
                            status: 503,
                            headers: {
                                'Content-Type': 'application/json'
                            }
                        }
                    );

                })
        );

        return;
    }

    // Static assets
    const isStatic =
        url.pathname.endsWith('.css') ||
        url.pathname.endsWith('.js') ||
        url.pathname.endsWith('.png') ||
        url.pathname.endsWith('.jpg') ||
        url.pathname.endsWith('.svg') ||
        url.pathname.endsWith('.webp') ||
        url.pathname.endsWith('.ico');

    if (isStatic) {

        event.respondWith(

            caches.open(CACHE_NAME).then(cache => {

                return cache.match(event.request)
                    .then(cached => {

                        return fetch(event.request)
                            .then(response => {

                                if (response.status === 200) {
                                    cache.put(
                                        event.request,
                                        response.clone()
                                    );
                                }

                                return response;

                            })
                            .catch(() => {

                                return cached || Response.error();

                            });

                    });

            })

        );

        return;
    }

});

// ====================== BACKGROUND SYNC ======================
self.addEventListener('sync', event => {

    if (event.tag === 'sync-notes') {
        console.log('[SW] Background sync...');
    }

});

console.log('[SW] NoteApp Service Worker loaded!');