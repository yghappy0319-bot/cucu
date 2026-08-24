/* 거래게시판 찜 — 새로고침 없이 토글 */
(function () {
    'use strict';

    function formatCount(n) {
        return Number(n).toLocaleString('ko-KR');
    }

    function parseJsonResponse(res) {
        return res.text().then(function (text) {
            var data = null;
            try {
                data = text ? JSON.parse(text) : null;
            } catch (e) {
                throw new Error('서버 응답을 처리할 수 없습니다.');
            }
            if (!res.ok && data && data.message) {
                throw new Error(data.message);
            }
            return data;
        });
    }

    function setListBtnState(btn, wished) {
        var icon = btn.querySelector('[aria-hidden="true"]') || btn;
        btn.classList.toggle('is-wished', wished);
        btn.setAttribute('aria-pressed', wished ? 'true' : 'false');
        btn.setAttribute('aria-label', wished ? '찜 해제' : '찜하기');
        if (icon !== btn) {
            icon.textContent = wished ? '♥' : '♡';
        } else if (btn.childNodes.length === 1 && btn.childNodes[0].nodeType === 3) {
            btn.textContent = wished ? '♥' : '♡';
        }
    }

    function setDetailBtnState(btn, wished) {
        var icon = btn.querySelector('.co-like-icon');
        btn.classList.toggle('btn-primary', wished);
        btn.classList.toggle('btn-outline', !wished);
        btn.setAttribute('aria-pressed', wished ? 'true' : 'false');
        if (icon) {
            icon.textContent = wished ? '♥' : '♡';
        }
    }

    function updateWishUI(trIdx, wished, count) {
        var root = document;
        var items = root.querySelectorAll('[data-tr-idx="' + trIdx + '"]');
        items.forEach(function (item) {
            item.querySelectorAll('.trade-wish-btn').forEach(function (btn) {
                setListBtnState(btn, wished);
            });
            item.querySelectorAll('.co-like-submit').forEach(function (btn) {
                setDetailBtnState(btn, wished);
            });
            item.querySelectorAll('.co-like-count').forEach(function (el) {
                el.textContent = formatCount(count);
            });
            item.querySelectorAll('.trade-wish-count').forEach(function (el) {
                el.hidden = count < 1;
                if (count > 0) {
                    el.textContent = '♥ ' + formatCount(count);
                }
            });
            item.querySelectorAll('[data-trade-wish-stat]').forEach(function (el) {
                el.hidden = count < 1;
                if (count > 0) {
                    el.textContent = '· 찜 ' + formatCount(count);
                }
            });
            item.querySelectorAll('.co-like-readonly').forEach(function (el) {
                el.textContent = formatCount(count) + '명이 찜했어요';
            });
        });
    }

    function removeMypageWishItem(form) {
        var item = form.closest('.trade-item');
        var grid = form.closest('[data-mypage-wish-grid]');
        if (!item || !grid) {
            return;
        }
        item.remove();

        var totalEl = document.querySelector('[data-mypage-wish-total]');
        if (totalEl) {
            var next = Math.max(0, (parseInt(totalEl.textContent.replace(/[^\d]/g, ''), 10) || 0) - 1);
            totalEl.textContent = formatCount(next);
        }

        if (!grid.querySelector('.trade-item')) {
            grid.remove();
            var empty = document.querySelector('[data-mypage-wish-empty]');
            if (empty) {
                empty.hidden = false;
                return;
            }
            var container = document.querySelector('.mypage-wishes .container');
            if (!container) {
                return;
            }
            var wrap = document.createElement('div');
            wrap.className = 'board-list';
            wrap.setAttribute('data-mypage-wish-empty', '');
            wrap.innerHTML =
                '<div class="board-empty">' +
                '<div class="empty-emoji">♡</div>' +
                '<p>찜한 상품이 없습니다.<br><a href="/trade/trade.php">거래게시판</a>에서 마음에 드는 상품을 찜해 보세요.</p>' +
                '</div>';
            var pagination = container.querySelector('.pagination');
            if (pagination) {
                pagination.remove();
            }
            container.appendChild(wrap);
        }
    }

    function handleWishSubmit(e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }
        if (!form.classList.contains('trade-wish-form') && !form.classList.contains('js-trade-wish-form')) {
            return;
        }

        e.preventDefault();

        var btn = form.querySelector('button[type="submit"]');
        if (btn && btn.disabled) {
            return;
        }
        if (btn) {
            btn.disabled = true;
        }

        var fd = new FormData(form);
        fd.set('ajax', '1');

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
                    throw new Error('서버 응답을 처리할 수 없습니다.');
                }
                if (data.login) {
                    window.location.href = '/login.php?return=' + encodeURIComponent(window.location.pathname + window.location.search);
                    return;
                }
                if (!data.ok) {
                    throw new Error(data.message || '찜 처리에 실패했습니다.');
                }
                var trIdx = parseInt(fd.get('tr_idx'), 10);
                if (!trIdx) {
                    return;
                }
                updateWishUI(trIdx, !!data.wished, Number(data.count) || 0);
                if (!data.wished && form.closest('[data-mypage-wish-form]')) {
                    removeMypageWishItem(form);
                }
            })
            .catch(function (err) {
                window.alert(err && err.message ? err.message : '네트워크 오류가 발생했습니다.');
            })
            .finally(function () {
                if (btn) {
                    btn.disabled = false;
                }
            });
    }

    document.addEventListener('submit', handleWishSubmit);
})();
