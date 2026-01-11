const CACHE_NAME = 'bakespaze-pwa-v5';
const PRECACHE_URLS = [
  '/',
  '/manifest.webmanifest',
  '/css/dashboard.css',
  '/js/dashboard.js',
  '/js/chat.js',
  '/js/push.js',
  '/images/logo.svg',
  '/images/logo.png',
  '/images/icon-180.png',
  '/images/icon-192.png',
  '/images/icon-512.png',
  '/images/tasklist-icon.svg'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => cache.addAll(PRECACHE_URLS))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.map((key) => {
        if (key !== CACHE_NAME) {
          return caches.delete(key);
        }
        return Promise.resolve();
      }))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET') {
    return;
  }

  const accept = event.request.headers.get('accept') || '';
  if (event.request.mode === 'navigate' || accept.includes('text/html')) {
    event.respondWith(fetch(event.request));
    return;
  }

  event.respondWith(
    fetch(event.request)
      .then((response) => {
        const copy = response.clone();
        caches.open(CACHE_NAME).then((cache) => cache.put(event.request, copy));
        return response;
      })
      .catch(() => caches.match(event.request))
  );
});

self.addEventListener('push', (event) => {
  const data = event.data ? event.data.json() : {};
  const title = data.title || 'Notification';
  const options = {
    body: data.body || '',
    icon: data.icon || '/images/icon-192.png',
    data: data.data || {},
  };
  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const payload = event.notification.data || {};
  const url = payload.url || '/';
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
      const client = clientList[0];
      if (client) {
        client.focus();
        client.postMessage({ type: 'notification-click', payload });
        return;
      }
      const nextUrl = new URL(url, self.location.origin);
      if (payload.type) nextUrl.searchParams.set('notify_type', payload.type);
      if (payload.task_id) nextUrl.searchParams.set('task_id', payload.task_id);
      if (payload.workspace_id) nextUrl.searchParams.set('workspace_id', payload.workspace_id);
      if (event.notification.title) nextUrl.searchParams.set('notify_title', event.notification.title);
      if (event.notification.body) nextUrl.searchParams.set('notify_body', event.notification.body);
      return clients.openWindow(nextUrl.toString());
    })
  );
});
