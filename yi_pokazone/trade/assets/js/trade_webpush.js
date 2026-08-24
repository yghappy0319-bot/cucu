/**
 * 거래 메시지 Web Push (VAPID) — [data-pz-webpush] + .pz-webpush-toggle
 * window.__WEB_PUSH_VAPID__, window.__PZ_PUBLIC_PREFIX__
 */
(function () {
    'use strict';

    var vapid = typeof window.__WEB_PUSH_VAPID__ === 'string' ? window.__WEB_PUSH_VAPID__ : '';
    if (!vapid) return;
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;

    var roots = document.querySelectorAll('[data-pz-webpush]');
    if (!roots.length) return;

    var prefix = typeof window.__PZ_PUBLIC_PREFIX__ === 'string' && window.__PZ_PUBLIC_PREFIX__ !== ''
        ? '/' + window.__PZ_PUBLIC_PREFIX__.replace(/^\/+|\/+$/g, '')
        : '';

    var SESSION_PWA_AUTO = 'pz_webpush_pwa_auto';
    var DISMISS_KEY = 'pz_webpush_banner_dismiss';

    function pzPath(path) {
        if (!path || path.charAt(0) !== '/') {
            path = '/' + (path || '');
        }
        return prefix + path;
    }

    function isStandaloneDisplayMode() {
        try {
            if (window.matchMedia('(display-mode: standalone)').matches) return true;
            if (window.matchMedia('(display-mode: minimal-ui)').matches) return true;
        } catch (e) { /* ignore */ }
        if (typeof window.navigator !== 'undefined' && window.navigator.standalone === true) return true;
        return false;
    }

    function urlBase64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var raw = atob(base64);
        var out = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
        return out;
    }

    function parseWebpushApiResponse(r) {
        return r.text().then(function (text) {
            var j = null;
            try {
                j = JSON.parse(text);
            } catch (e) {
                j = null;
            }
            if (!j || typeof j !== 'object') {
                return {
                    ok: false,
                    error: '서버 응답을 해석할 수 없습니다 (HTTP ' + r.status + '). 로그인·경로(webpush_api.php)를 확인해 주세요.',
                };
            }
            return j;
        });
    }

    function apiSubscribe(subscription) {
        return fetch(pzPath('/page/webpush_api.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({
                action: 'subscribe',
                subscription: subscription.toJSON(),
            }),
        }).then(parseWebpushApiResponse);
    }

    function apiUnsubscribe(endpoint) {
        return fetch(pzPath('/page/webpush_api.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ action: 'unsubscribe', endpoint: endpoint }),
        }).then(parseWebpushApiResponse);
    }

    function subscribeFlow() {
        if (Notification.permission === 'denied') {
            window.alert('브라우저에서 알림이 차단되어 있습니다. 주소창의 사이트 설정에서 알림을 허용해 주세요.');
            return Promise.reject(new Error('denied'));
        }
        return navigator.serviceWorker.register(pzPath('/sw.js')).then(function (reg) {
            return reg.pushManager.getSubscription().then(function (existing) {
                var clear = Promise.resolve();
                if (existing) {
                    clear = existing.unsubscribe().catch(function () {});
                }
                return clear.then(function () {
                    return reg.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: urlBase64ToUint8Array(vapid),
                    });
                });
            });
        }).then(function (sub) {
            return apiSubscribe(sub);
        });
    }

    function setStatus(el, text) {
        if (el) el.textContent = text;
    }

    function statusBroadcast(rootsList, text) {
        rootsList.forEach(function (root) {
            var s = root.querySelector('.pz-webpush-status');
            setStatus(s, text);
        });
    }

    function applyToggleState(toggle, subscribed, denied) {
        if (!toggle) return;
        toggle._pzIgnore = true;
        toggle.checked = !!(subscribed && !denied);
        toggle.disabled = !!denied;
        toggle.setAttribute('aria-checked', toggle.checked ? 'true' : 'false');
        toggle._pzIgnore = false;
    }

    function setAllTogglesBusy(rootsList, busy) {
        rootsList.forEach(function (root) {
            var t = root.querySelector('.pz-webpush-toggle');
            if (!t || Notification.permission === 'denied') return;
            if (busy) {
                t.disabled = true;
            }
        });
    }

    /**
     * @returns {Promise<{ subscribed: boolean, denied: boolean }>}
     */
    function syncSubscriptionState(toggle, statusEl) {
        if (Notification.permission === 'denied') {
            applyToggleState(toggle, false, true);
            setStatus(statusEl, '이 브라우저에서 이 사이트 알림이 차단되어 있습니다. 주소창 자물쇠 → 사이트 설정에서 알림을 허용해 주세요.');
            return Promise.resolve({ subscribed: false, denied: true });
        }
        return navigator.serviceWorker.register(pzPath('/sw.js')).then(function (reg) {
            return reg.pushManager.getSubscription();
        }).then(function (sub) {
            var subscribed = !!sub;
            applyToggleState(toggle, subscribed, false);
            if (subscribed) {
                setStatus(statusEl, '이 브라우저에서 알림을 받고 있습니다.');
            } else if (Notification.permission === 'granted') {
                setStatus(statusEl, '아래 스위치를 켜면 이 기기로 거래 메시지 알림을 받습니다.');
            } else {
                setStatus(statusEl, '알림을 받으려면 스위치를 켜고 브라우저에서 알림을 허용해 주세요.');
            }
            return { subscribed: subscribed, denied: false };
        }).catch(function () {
            applyToggleState(toggle, false, false);
            setStatus(statusEl, '서비스 워커를 불러오지 못했습니다. HTTPS 접속·sw.js 경로를 확인해 주세요.');
            return { subscribed: false, denied: false };
        });
    }

    function updateBannerVisibility(root, subscribed, denied) {
        if (!root.hasAttribute('data-pz-webpush-banner')) return;
        var dismissed = sessionStorage.getItem(DISMISS_KEY) === '1';
        if (subscribed || denied || dismissed) {
            root.setAttribute('hidden', '');
        } else {
            root.removeAttribute('hidden');
        }
    }

    function syncAllRoots(rootsList) {
        var promises = [];
        rootsList.forEach(function (root) {
            var toggle = root.querySelector('.pz-webpush-toggle');
            var statusEl = root.querySelector('.pz-webpush-status');
            if (!toggle) return;
            promises.push(
                syncSubscriptionState(toggle, statusEl).then(function (state) {
                    updateBannerVisibility(root, state.subscribed, state.denied);
                    return state;
                })
            );
        });
        return Promise.all(promises);
    }

    function pickStatusRoot(rootsList) {
        var i;
        for (i = 0; i < rootsList.length; i++) {
            if (rootsList[i].hasAttribute('data-pz-webpush-banner')) return rootsList[i];
        }
        return rootsList[0];
    }

    function tryPwaAutoSubscribe(rootsList) {
        if (!isStandaloneDisplayMode()) return Promise.resolve();
        if (sessionStorage.getItem(SESSION_PWA_AUTO) === '1') return Promise.resolve();
        if (Notification.permission !== 'default') return Promise.resolve();

        var sr = pickStatusRoot(rootsList);
        var statusEl = sr ? sr.querySelector('.pz-webpush-status') : null;

        return navigator.serviceWorker.register(pzPath('/sw.js')).then(function (reg) {
            return reg.pushManager.getSubscription();
        }).then(function (sub) {
            if (sub) return Promise.resolve();
            sessionStorage.setItem(SESSION_PWA_AUTO, '1');
            setStatus(statusEl, '앱 알림을 위해 권한이 필요합니다…');
            return Notification.requestPermission().then(function (perm) {
                if (perm !== 'granted') {
                    return syncAllRoots(rootsList);
                }
                return subscribeFlow().then(function (j) {
                    return syncAllRoots(rootsList).then(function () {
                        if (j && j.ok) {
                            statusBroadcast(rootsList, '앱 알림이 켜졌습니다. 새 거래 메시지를 알려 드립니다.');
                        } else if (j && !j.ok) {
                            statusBroadcast(rootsList, j.error || '등록에 실패했습니다.');
                        }
                    });
                });
            });
        }).catch(function () {
            return syncAllRoots(rootsList);
        });
    }

    function runSubscribeFromToggle(rootsList) {
        statusBroadcast(rootsList, '등록 중…');
        setAllTogglesBusy(rootsList, true);
        var permPromise = Notification.permission === 'granted'
            ? Promise.resolve('granted')
            : Notification.requestPermission();
        return permPromise
            .then(function (perm) {
                if (perm !== 'granted') {
                    statusBroadcast(rootsList, perm === 'denied' ? '알림이 거부되었습니다.' : '알림이 허용되지 않았습니다.');
                    return syncAllRoots(rootsList);
                }
                return subscribeFlow().then(function (j) {
                    if (!j.ok) {
                        statusBroadcast(rootsList, j.error || '등록에 실패했습니다.');
                    }
                    return syncAllRoots(rootsList);
                });
            })
            .catch(function () {
                statusBroadcast(rootsList, '등록 중 오류가 났습니다. HTTPS·서비스 워커·경로(하위 폴더 설치)를 확인해 주세요.');
                return syncAllRoots(rootsList);
            });
    }

    function runUnsubscribeFromToggle(rootsList) {
        statusBroadcast(rootsList, '해제 중…');
        setAllTogglesBusy(rootsList, true);
        return navigator.serviceWorker
            .register(pzPath('/sw.js'))
            .then(function (reg) {
                return reg.pushManager.getSubscription();
            })
            .then(function (sub) {
                if (!sub) return Promise.resolve({ ok: true });
                var ep = sub.endpoint;
                return apiUnsubscribe(ep).then(function () {
                    return sub.unsubscribe();
                });
            })
            .catch(function () {
                statusBroadcast(rootsList, '해제 중 오류가 났습니다.');
            })
            .then(function () {
                return syncAllRoots(rootsList);
            });
    }

    var rootsArr = Array.prototype.slice.call(roots);

    rootsArr.forEach(function (root) {
        var toggle = root.querySelector('.pz-webpush-toggle');
        var dismissBtn = root.querySelector('.pz-webpush-dismiss');

        if (!toggle) return;

        if (dismissBtn) {
            dismissBtn.addEventListener('click', function () {
                sessionStorage.setItem(DISMISS_KEY, '1');
                root.setAttribute('hidden', '');
            });
        }

        toggle.addEventListener('change', function () {
            if (toggle._pzIgnore) return;
            if (toggle.checked) {
                runSubscribeFromToggle(rootsArr);
            } else {
                runUnsubscribeFromToggle(rootsArr);
            }
        });
    });

    syncAllRoots(rootsArr).then(function () {
        return tryPwaAutoSubscribe(rootsArr);
    });
})();
