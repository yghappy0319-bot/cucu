/**
 * 경매 상세 — 입찰 AJAX + 실시간 폴링
 */
(function () {
    'use strict';

    var root = document.querySelector('[data-auction-live]');
    if (!root) {
        return;
    }

    var auIdx = parseInt(root.getAttribute('data-au-idx'), 10);
    if (!auIdx) {
        return;
    }

    function pzUrl(path) {
        if (!path || path.charAt(0) !== '/') {
            path = '/' + (path || '');
        }
        var pre = (window.__PZ_PUBLIC_PREFIX__ || '').replace(/^\/+|\/+$/g, '');
        return (pre ? '/' + pre : '') + path;
    }

    var apiUrl = root.getAttribute('data-auction-api') || pzUrl('/auction/auction_api.php');

    var lastAbIdx = parseInt(root.getAttribute('data-last-ab-idx') || '0', 10) || 0;
    var pollMs = 6000;
    var pollTimer = null;
    var fetching = false;

    var elPrice = root.querySelector('[data-auction-current-price]');
    var elBidCount = root.querySelector('[data-auction-bid-count]');
    var elBidsList = root.querySelector('[data-auction-bids-list]');
    var elBidsCount = root.querySelector('[data-auction-bids-count]');
    var elRemain = root.querySelector('[data-auction-remain]');
    var elMinHelp = root.querySelector('[data-auction-min-help]');
    var elAmount = document.getElementById('ab_amount');
    var form = root.querySelector('.auction-bid-form');
    var toastEl = null;

    function fmt(n) {
        return Number(n).toLocaleString('ko-KR');
    }

    function showToast(msg, isErr) {
        if (!toastEl) {
            toastEl = document.createElement('div');
            toastEl.className = 'auction-live-toast';
            toastEl.setAttribute('role', 'status');
            root.appendChild(toastEl);
        }
        toastEl.textContent = msg;
        toastEl.className = 'auction-live-toast' + (isErr ? ' is-error' : ' is-ok');
        toastEl.hidden = false;
        clearTimeout(showToast._t);
        showToast._t = setTimeout(function () {
            toastEl.hidden = true;
        }, 3200);
    }

    function parseJsonResponse(r) {
        return r.text().then(function (text) {
            var trimmed = (text || '').trim();
            if (!trimmed) {
                throw new Error('서버 응답이 비어 있습니다. (HTTP ' + r.status + ')');
            }
            try {
                return JSON.parse(trimmed);
            } catch (e) {
                if (trimmed.indexOf('<script') !== -1 && trimmed.indexOf('alert(') !== -1) {
                    throw new Error('입찰 API가 JSON이 아닌 HTML을 반환했습니다. 서버에 proc/auction_bid_proc.php 최신본이 올라갔는지 확인해 주세요.');
                }
                throw new Error('서버 응답 형식 오류 (HTTP ' + r.status + ')');
            }
        });
    }

    function flashPrice() {
        if (elPrice) {
            elPrice.classList.remove('is-live-flash');
            void elPrice.offsetWidth;
            elPrice.classList.add('is-live-flash');
        }
    }

    function scrollBidsListToTop() {
        if (!elBidsList) {
            return;
        }
        requestAnimationFrame(function () {
            elBidsList.scrollTop = 0;
        });
    }

    function applySnapshot(snap, highlightNew, scrollBidsToTop) {
        if (!snap) {
            return;
        }

        var newLast = parseInt(snap.last_ab_idx, 10) || 0;
        var hadNew = highlightNew && newLast > 0 && newLast !== lastAbIdx;

        if (elPrice) {
            elPrice.textContent = fmt(snap.current_price);
        }
        if (elBidCount) {
            elBidCount.textContent = fmt(snap.bid_count) + '회';
        }
        if (elBidsCount) {
            elBidsCount.textContent = snap.bid_count_label || '';
        }
        if (elBidsList && typeof snap.bids_html === 'string') {
            elBidsList.innerHTML = snap.bids_html;
            if (hadNew) {
                var top = elBidsList.querySelector('.auction-bid-list__item');
                if (top) {
                    top.classList.add('is-live-new');
                }
            }
            if (scrollBidsToTop) {
                scrollBidsListToTop();
            }
        }
        if (elRemain && snap.remain_text) {
            elRemain.textContent = snap.remain_text;
        }
        if (elAmount && snap.min_bid) {
            var min = parseInt(snap.min_bid, 10);
            elAmount.min = String(min);
            elAmount.step = String(snap.bid_step || 1000);
            var cur = parseInt(elAmount.value, 10);
            if (isNaN(cur) || cur < min) {
                elAmount.value = String(min);
            }
        }
        if (elMinHelp && snap.min_bid) {
            elMinHelp.innerHTML = '최소 입찰가 <strong>₩' + fmt(snap.min_bid) + '</strong>';
        }

        root.querySelectorAll('[data-bid-quick]').forEach(function (btn, i) {
            var min = parseInt(snap.min_bid, 10) || 0;
            var step = parseInt(snap.bid_step, 10) || 1000;
            var mult = [0, 1, 2, 5][i] || 0;
            if (min > 0) {
                btn.setAttribute('data-bid-quick', String(min + step * mult));
            }
        });

        if (hadNew) {
            flashPrice();
        }
        lastAbIdx = newLast;
        root.setAttribute('data-last-ab-idx', String(lastAbIdx));

        if (snap.is_ended) {
            stopPoll();
            if (root.getAttribute('data-auction-ended') !== '1') {
                window.location.reload();
                return;
            }
            root.setAttribute('data-auction-ended', '1');
        }
    }

    function fetchSnapshot(highlightNew) {
        if (fetching) {
            return Promise.resolve();
        }
        fetching = true;
        var url = apiUrl + (apiUrl.indexOf('?') >= 0 ? '&' : '?') + 'au_idx=' + encodeURIComponent(String(auIdx));
        return fetch(url, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        })
            .then(parseJsonResponse)
            .then(function (data) {
                if (data && data.ok && data.snapshot) {
                    applySnapshot(data.snapshot, highlightNew);
                }
            })
            .catch(function () { /* ignore poll errors */ })
            .finally(function () {
                fetching = false;
            });
    }

    function startPoll() {
        if (root.getAttribute('data-auction-ended') === '1') {
            return;
        }
        stopPoll();
        pollTimer = setInterval(function () {
            fetchSnapshot(true);
        }, pollMs);
    }

    function stopPoll() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    document.querySelectorAll('[data-bid-quick]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!elAmount) {
                return;
            }
            var v = parseInt(btn.getAttribute('data-bid-quick'), 10);
            if (!isNaN(v)) {
                elAmount.value = String(v);
                elAmount.focus();
            }
        });
    });

    document.querySelectorAll('[data-auction-buy-now]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            if (!confirm('즉시구매가로 즉시 낙찰됩니다. 계속하시겠습니까?')) {
                e.preventDefault();
            }
        });
    });

    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var submitter = e.submitter;
            var fd = new FormData(form);
            if (submitter && submitter.name === 'action') {
                fd.set('action', submitter.value);
            }
            fd.set('ajax', '1');

            var btn = form.querySelector('.auction-bid-submit');
            if (btn) {
                btn.disabled = true;
            }

            fetch(form.getAttribute('action') || form.action, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
            })
                .then(parseJsonResponse)
                .then(function (data) {
                    if (!data || typeof data !== 'object') {
                        showToast('서버 응답을 처리할 수 없습니다.', true);
                        return;
                    }
                    if (data.redirect) {
                        window.location.href = data.redirect;
                        return;
                    }
                    if (!data.ok) {
                        showToast(data.message || '입찰에 실패했습니다.', true);
                        return;
                    }
                    showToast(data.message || '입찰했습니다.', false);
                    if (data.snapshot) {
                        applySnapshot(data.snapshot, true, true);
                    }
                    if (data.reload) {
                        setTimeout(function () {
                            window.location.reload();
                        }, 800);
                    }
                })
                .catch(function (err) {
                    showToast(err && err.message ? err.message : '네트워크 오류가 발생했습니다.', true);
                })
                .finally(function () {
                    if (btn) {
                        btn.disabled = false;
                    }
                });
        });
    }

    startPoll();
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            stopPoll();
        } else {
            fetchSnapshot(true).then(startPoll);
        }
    });
})();
