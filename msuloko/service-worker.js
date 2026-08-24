const SW_VERSION = '3.0';
const CACHE_NAME = 'suloko-offline-v3';
const OFFLINE_URL = '/offline.html';

self.addEventListener('install', (e) => {
  self.skipWaiting();
  e.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => cache.add(OFFLINE_URL))
      .catch(() => undefined)
  );
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(
        keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
      ))
      .then(() => self.clients.claim())
      .then(() => self.clients.matchAll({ type: 'window' }))
      .then((clientList) => Promise.all(
        clientList.map((client) => {
          if (typeof client.navigate === 'function') {
            return client.navigate(client.url).catch(() => undefined);
          }
          return undefined;
        })
      ))
  );
});

self.addEventListener('message', (e) => {
  if (e.data === 'SKIP_WAITING' || (e.data && e.data.type === 'SKIP_WAITING')) {
    self.skipWaiting();
  }
});

self.addEventListener('fetch', (e) => {
  if (e.request.method !== 'GET') {
    return;
  }

  if (e.request.mode !== 'navigate') {
    return;
  }

  e.respondWith(
    fetch(e.request).catch(() =>
      caches.match(OFFLINE_URL).then((cached) => {
        if (cached) {
          return cached;
        }
        return new Response(
          '<!DOCTYPE html><html lang="ko"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>연결할 수 없습니다</title></head><body><p>사이트에 연결할 수 없습니다. 네트워크 상태를 확인한 뒤 다시 시도해 주세요.</p></body></html>',
          { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
        );
      })
    )
  );
});

self.addEventListener('push', (e) => {
  if (!e.data) {
    return;
  }

  const payload = e.data.json();
  if (!payload || !payload.notification) {
    return;
  }

  const resultData = payload.notification;
  e.waitUntil(self.registration.showNotification(resultData.title, {
    body: resultData.body,
    icon: resultData.icon,
    image: resultData.image,
    tag: resultData.tag,
    data: { link: resultData.link },
    badge: resultData.badge
  }));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  const origin = self.location.origin;
  const targetUrl = (event.notification.data && event.notification.data.link)
    || origin + '/';

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
      for (let i = 0; i < clientList.length; i++) {
        const client = clientList[i];
        if ('focus' in client) {
          return client.focus();
        }
      }
      if (clients.openWindow) {
        return clients.openWindow(targetUrl);
      }
    })
  );
});
