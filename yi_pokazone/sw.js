/* Pokazone PWA — Chrome/Android 설치 가능 조건용 최소 Service Worker */
(function () {
    'use strict';

    var CACHE_NAME = 'pokazone-shell-v10';

    self.addEventListener('install', function (event) {
        event.waitUntil(
            caches.open(CACHE_NAME).then(function (cache) {
                return cache.addAll([
                    '/',
                    '/assets/css/style.css',
                    '/assets/js/main.js',
                    '/assets/img/favicon.svg',
                    '/assets/img/favicon-48.png',
                    '/favicon.ico',
                ]).catch(function () {
                    /* 일부 경로 404·CORS 시에도 설치는 진행 */
                    return Promise.resolve();
                });
            }).then(function () {
                return self.skipWaiting();
            })
        );
    });

    self.addEventListener('activate', function (event) {
        event.waitUntil(
            caches.keys().then(function (keys) {
                return Promise.all(
                    keys.filter(function (k) { return k !== CACHE_NAME; }).map(function (k) {
                        return caches.delete(k);
                    })
                );
            }).then(function () {
                return self.clients.claim();
            })
        );
    });

    /* 네트워크 우선, 실패 시 캐시(오프라인 시 이전 방문 페이지 일부 표시) */
    self.addEventListener('fetch', function (event) {
        var req = event.request;
        if (req.method !== 'GET' || req.url.indexOf(self.location.origin) !== 0) {
            return;
        }
        var path = new URL(req.url).pathname;
        if (path.indexOf('/admin') === 0 || path.indexOf('/proc') === 0) {
            return;
        }
        event.respondWith(
            fetch(req)
                .then(function (res) {
                    /* 304·opaqueredirect 등은 cache.put 시 깨진 응답이 쌓일 수 있음 → 200 basic 만 저장 */
                    if (res.status === 200 && res.type === 'basic') {
                        var copy = res.clone();
                        caches.open(CACHE_NAME).then(function (cache) {
                            cache.put(req, copy);
                        });
                    }
                    return res;
                })
                .catch(function () {
                    return caches.match(req).then(function (hit) {
                        if (hit) {
                            return hit;
                        }
                        /* 문서 네비게이션만 홈 셸로 폴백. CSS/JS 등에 HTML을 넘기면 스타일이 통째로 빠짐 */
                        if (req.mode === 'navigate' || req.destination === 'document') {
                            return caches.match('/');
                        }
                        return new Response('', {
                            status: 503,
                            statusText: 'Offline',
                            headers: { 'Content-Type': 'text/plain; charset=UTF-8' },
                        });
                    });
                })
        );
    });

    /* Web Push — 거래 메시지 알림 */
    self.addEventListener('push', function (event) {
        var data = { title: 'Pokazone', body: '', url: '/', tag: '' };
        if (event.data) {
            try {
                var j = event.data.json();
                if (j && typeof j === 'object') {
                    if (j.title) data.title = j.title;
                    if (j.body) data.body = j.body;
                    if (j.url) data.url = j.url;
                    if (j.tag) data.tag = String(j.tag);
                }
            } catch (e) { /* ignore */ }
        }
        /* 고정 tag 는 같은 알림만 갱신되어 소리·헤드업이 안 날 수 있음 → 건별 고유 tag */
        var notifyTag = data.tag !== '' ? data.tag : ('pz-' + Date.now() + '-' + Math.random().toString(36).slice(2, 10));
        /* PC: OS 가 소리·배너를 억제하는 경우 많음(설정에서 브라우저 알림 허용 필요).
           requireInteraction 은 큰 화면에서 알림이 사용자가 닫기 전까지 유지되어 데스크푸에서 더 잘 보임 */
        var opts = {
            body: data.body,
            icon: '/assets/img/pwa-192.png',
            /* Android 상태창: 단색(흰색) 실루엣 PNG 필요 — 컬러 pwa 아이콘은 네모로 보임 */
            badge: '/assets/img/notification-badge-96.png',
            data: { url: data.url },
            tag: notifyTag,
            renotify: true,
            silent: false,
            requireInteraction: true,
            vibrate: [180, 80, 180],
        };
        event.waitUntil(
            self.registration.showNotification(data.title, opts)
        );
    });

    self.addEventListener('notificationclick', function (event) {
        event.notification.close();
        var url = (event.notification.data && event.notification.data.url) ? event.notification.data.url : '/trade/trade_messages.php';
        event.waitUntil(
            self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) {
                var i;
                for (i = 0; i < list.length; i++) {
                    if (list[i].url && url && list[i].url.indexOf(url.split('?')[0]) !== -1) {
                        list[i].focus();
                        return;
                    }
                }
                if (self.clients.openWindow) {
                    return self.clients.openWindow(url);
                }
            })
        );
    });
})();
