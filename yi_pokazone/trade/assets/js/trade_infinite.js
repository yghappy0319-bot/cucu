/* 거래게시판 무한 스크롤 */
(function () {
    'use strict';

    var root = document.querySelector('[data-trade-infinite]');
    if (!root) {
        return;
    }

    var grid = root.querySelector('[data-trade-grid]');
    var sentinel = root.querySelector('[data-trade-sentinel]');
    var statusEl = root.querySelector('[data-trade-load-status]');
    if (!grid || !sentinel) {
        return;
    }

    var nextPage = parseInt(root.getAttribute('data-next-page') || '2', 10);
    var hasMore = root.getAttribute('data-has-more') === '1';
    var loading = false;
    var baseParams = {};

    try {
        baseParams = JSON.parse(root.getAttribute('data-query') || '{}') || {};
    } catch (e) {
        baseParams = {};
    }

    function setStatus(text, isError) {
        if (!statusEl) {
            return;
        }
        statusEl.hidden = !text;
        statusEl.textContent = text || '';
        statusEl.classList.toggle('is-error', !!isError);
    }

    function buildUrl(page) {
        var params = new URLSearchParams();
        Object.keys(baseParams).forEach(function (key) {
            var val = baseParams[key];
            if (val === '' || val === null || typeof val === 'undefined') {
                return;
            }
            params.set(key, String(val));
        });
        params.set('p', String(page));
        return '/trade/trade_list_api.php?' + params.toString();
    }

    function loadMore() {
        if (!hasMore || loading) {
            return;
        }
        loading = true;
        root.classList.add('is-loading');
        setStatus('불러오는 중…', false);

        fetch(buildUrl(nextPage), {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
        })
            .then(function (res) {
                return res.text().then(function (text) {
                    var data = null;
                    try {
                        data = text ? JSON.parse(text) : null;
                    } catch (err) {
                        throw new Error('서버 응답을 처리할 수 없습니다.');
                    }
                    if (!res.ok && data && data.error) {
                        throw new Error(data.error);
                    }
                    return data;
                });
            })
            .then(function (data) {
                if (!data || typeof data !== 'object') {
                    throw new Error('서버 응답을 처리할 수 없습니다.');
                }
                if (data.login) {
                    window.location.href =
                        '/login.php?return=' +
                        encodeURIComponent(window.location.pathname + window.location.search);
                    return;
                }
                if (!data.ok) {
                    throw new Error(data.error || '목록을 불러오지 못했습니다.');
                }

                if (data.html) {
                    var wrap = document.createElement('div');
                    wrap.innerHTML = data.html;
                    var nodes = Array.prototype.slice.call(wrap.children);
                    nodes.forEach(function (node) {
                        grid.appendChild(node);
                    });
                }

                hasMore = !!data.has_more;
                nextPage = (parseInt(data.page, 10) || nextPage) + 1;
                root.setAttribute('data-has-more', hasMore ? '1' : '0');
                root.setAttribute('data-next-page', String(nextPage));

                if (!hasMore) {
                    setStatus('', false);
                    if (observer) {
                        observer.disconnect();
                    }
                    sentinel.hidden = true;
                } else {
                    setStatus('', false);
                }
            })
            .catch(function (err) {
                setStatus(
                    (err && err.message ? err.message : '네트워크 오류가 발생했습니다.') +
                        ' 스크롤하면 다시 시도합니다.',
                    true
                );
            })
            .finally(function () {
                loading = false;
                root.classList.remove('is-loading');
            });
    }

    var observer = null;
    if ('IntersectionObserver' in window) {
        observer = new IntersectionObserver(
            function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        loadMore();
                    }
                });
            },
            {
                root: null,
                rootMargin: '400px 0px',
                threshold: 0,
            }
        );
        if (hasMore) {
            observer.observe(sentinel);
        } else {
            sentinel.hidden = true;
        }
    } else {
        // 구형 브라우저: 스크롤 폴백
        window.addEventListener(
            'scroll',
            function () {
                if (!hasMore || loading) {
                    return;
                }
                var rect = sentinel.getBoundingClientRect();
                if (rect.top < window.innerHeight + 400) {
                    loadMore();
                }
            },
            { passive: true }
        );
    }
})();
