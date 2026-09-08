/* NaaraSim service worker — self-hosted web push (no third-party service).
   Shows OS notifications from our own VAPID-signed pushes and focuses/opens the
   right in-app page when the user taps one. */

self.addEventListener('push', function (event) {
    var data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        data = { title: 'NaaraSim', body: event.data ? event.data.text() : '' };
    }

    var title = data.title || 'NaaraSim';
    var options = {
        body: data.body || '',
        icon: data.icon || '/favicon.ico',
        badge: data.icon || '/favicon.ico',
        data: { url: data.url || '/notifications' },
        // Coalesce a burst into one entry per destination so we never spam.
        tag: data.url || 'naarasim',
        renotify: true,
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    var url = (event.notification.data && event.notification.data.url) || '/notifications';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
            for (var i = 0; i < clientList.length; i++) {
                var client = clientList[i];
                if ('focus' in client) {
                    client.navigate(url);
                    return client.focus();
                }
            }
            if (self.clients.openWindow) {
                return self.clients.openWindow(url);
            }
        })
    );
});

/* ---------------------------------------------------------------------------
   Install-shell support (App Export §1) — added alongside the push logic above,
   which is left untouched. This gives the installed PWA / native WebView an
   offline fallback WITHOUT caching dynamic Livewire HTML (which would break
   CSRF + component state). Strategy: network-first for navigations, falling
   back to a tiny cached /offline page only when the network is unreachable.
--------------------------------------------------------------------------- */
var NX_SHELL_CACHE = 'naara-shell-v1';
var NX_OFFLINE_URL = '/offline';

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(NX_SHELL_CACHE).then(function (cache) {
            return cache.add(NX_OFFLINE_URL).catch(function () { /* offline page optional */ });
        })
    );
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(keys.map(function (k) {
                if (k !== NX_SHELL_CACHE) { return caches.delete(k); }
            }));
        }).then(function () { return self.clients.claim(); })
    );
});

self.addEventListener('fetch', function (event) {
    var req = event.request;
    // Only handle top-level navigations; never touch API/asset/POST traffic.
    if (req.method !== 'GET' || req.mode !== 'navigate') {
        return;
    }
    event.respondWith(
        fetch(req).catch(function () {
            return caches.match(NX_OFFLINE_URL).then(function (res) {
                return res || new Response('You are offline.', { headers: { 'Content-Type': 'text/plain' } });
            });
        })
    );
});
