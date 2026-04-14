/**
 * YourJannah — Service Worker (PWA)
 *
 * Network-first for API, cache-first for static assets, offline fallback.
 * Handles push notifications for mosque announcements and prayer reminders.
 */

const CACHE_NAME = 'ynj-v1';

const SHELL_URLS = [
    '/',
    '/offline',
    '/wp-content/plugins/yn-jannah/assets/icons/icon-192.png',
    '/wp-content/plugins/yn-jannah/assets/icons/icon-512.png',
    '/manifest.json',
];

/* ------------------------------------------------------------------ */
/*  Install — pre-cache the app shell                                 */
/* ------------------------------------------------------------------ */

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(SHELL_URLS).catch((err) => {
                // Non-critical: some URLs may not exist yet during development.
                console.warn('[SW] Pre-cache partial failure:', err);
            });
        })
    );
    // Activate immediately without waiting for existing clients to close.
    self.skipWaiting();
});

/* ------------------------------------------------------------------ */
/*  Activate — clean up old caches                                    */
/* ------------------------------------------------------------------ */

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys
                    .filter((key) => key !== CACHE_NAME)
                    .map((key) => caches.delete(key))
            );
        })
    );
    // Take control of all open clients immediately.
    self.clients.claim();
});

/* ------------------------------------------------------------------ */
/*  Fetch — network-first for API, cache-first for assets             */
/* ------------------------------------------------------------------ */

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Only handle same-origin GET requests.
    if (request.method !== 'GET') return;
    if (url.origin !== self.location.origin) return;

    // API calls: network-first with no caching.
    if (url.pathname.startsWith('/wp-json/')) {
        event.respondWith(networkFirst(request));
        return;
    }

    // Static assets (images, fonts, CSS, JS): cache-first.
    if (isStaticAsset(url.pathname)) {
        event.respondWith(cacheFirst(request));
        return;
    }

    // Navigation / HTML pages: network-first with offline fallback.
    if (request.mode === 'navigate' || request.headers.get('accept')?.includes('text/html')) {
        event.respondWith(networkFirstWithOffline(request));
        return;
    }

    // Default: network-first.
    event.respondWith(networkFirst(request));
});

/**
 * Network-first strategy.
 */
async function networkFirst(request) {
    try {
        const response = await fetch(request);
        return response;
    } catch (err) {
        const cached = await caches.match(request);
        return cached || new Response('Offline', { status: 503, statusText: 'Service Unavailable' });
    }
}

/**
 * Network-first with offline page fallback for navigation requests.
 */
async function networkFirstWithOffline(request) {
    try {
        const response = await fetch(request);
        // Cache successful HTML responses for offline use.
        if (response.ok) {
            const clone = response.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
        }
        return response;
    } catch (err) {
        const cached = await caches.match(request);
        if (cached) return cached;

        // Serve the offline fallback page.
        const offline = await caches.match('/offline');
        if (offline) return offline;

        return new Response(
            '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Offline</title></head><body style="font-family:Inter,system-ui,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#FAFAF8;color:#1a1a1a;text-align:center;padding:24px;"><div><h1 style="color:#1c4644;margin-bottom:12px;">You\'re Offline</h1><p style="color:#6b7280;">Please check your connection and try again.</p></div></body></html>',
            { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } }
        );
    }
}

/**
 * Cache-first strategy (for static assets).
 */
async function cacheFirst(request) {
    const cached = await caches.match(request);
    if (cached) return cached;

    try {
        const response = await fetch(request);
        if (response.ok) {
            const clone = response.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
        }
        return response;
    } catch (err) {
        return new Response('', { status: 503 });
    }
}

/**
 * Check if a URL path is a static asset.
 */
function isStaticAsset(pathname) {
    return /\.(js|css|png|jpg|jpeg|gif|svg|webp|woff2?|ttf|eot|ico)(\?.*)?$/i.test(pathname);
}

/* ------------------------------------------------------------------ */
/*  Push — show notifications from push data                          */
/* ------------------------------------------------------------------ */

self.addEventListener('push', (event) => {
    let data = {
        title: 'YourJannah',
        body: 'You have a new notification.',
        icon: '/wp-content/plugins/yn-jannah/assets/icons/icon-192.png',
        url: '/',
    };

    if (event.data) {
        try {
            const parsed = event.data.json();
            data = { ...data, ...parsed };
        } catch (err) {
            // If not JSON, use text as body.
            data.body = event.data.text() || data.body;
        }
    }

    const options = {
        body: data.body,
        icon: data.icon,
        badge: '/wp-content/plugins/yn-jannah/assets/icons/icon-192.png',
        vibrate: [100, 50, 100],
        data: {
            url: data.url,
        },
        actions: [
            { action: 'open', title: 'Open' },
            { action: 'dismiss', title: 'Dismiss' },
        ],
    };

    event.waitUntil(
        self.registration.showNotification(data.title, options)
    );
});

/* ------------------------------------------------------------------ */
/*  Notification Click — open URL                                     */
/* ------------------------------------------------------------------ */

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    if (event.action === 'dismiss') return;

    const url = event.notification.data?.url || '/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
            // If there is an existing window on the same origin, focus it and navigate.
            for (const client of windowClients) {
                if (new URL(client.url).origin === self.location.origin && 'focus' in client) {
                    client.focus();
                    client.navigate(url);
                    return;
                }
            }
            // Otherwise open a new window.
            return clients.openWindow(url);
        })
    );
});
