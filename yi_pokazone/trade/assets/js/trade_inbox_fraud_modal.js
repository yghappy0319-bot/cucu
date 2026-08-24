/**
 * 거래 메시지함 사기 예방 모달 — 쿠키로 숨김 기간 적용 (기본 1일, 7일간 닫기 시 7일)
 */
(function () {
    var COOKIE = 'pz_trade_inbox_fraud_ack';
    var DAY = 86400;

    function getCookie(name) {
        var esc = name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        var m = document.cookie.match(new RegExp('(?:^|; )' + esc + '=([^;]*)'));
        return m ? decodeURIComponent(m[1]) : '';
    }

    function setAckCookie(maxAgeSec) {
        document.cookie =
            COOKIE +
            '=1; path=/; max-age=' +
            String(maxAgeSec | 0) +
            '; SameSite=Lax';
    }

    function closeModal(modal, escClose, remember) {
        document.removeEventListener('keydown', escClose);
        modal.setAttribute('hidden', '');
        modal.setAttribute('aria-hidden', 'true');
        document.documentElement.classList.remove('trade-chat-fraud-modal-open');
        if (remember) {
            var weekCb = document.getElementById('trade-chat-fraud-week');
            var sec = weekCb && weekCb.checked ? 7 * DAY : DAY;
            setAckCookie(sec);
        }
    }

    function run() {
        var modal = document.getElementById('trade-chat-fraud-modal');
        if (!modal) return;
        if (getCookie(COOKIE) === '1') return;

        modal.removeAttribute('hidden');
        modal.setAttribute('aria-hidden', 'false');
        document.documentElement.classList.add('trade-chat-fraud-modal-open');

        var okBtn = document.getElementById('trade-chat-fraud-ok');

        function escClose(ev) {
            if (ev.key === 'Escape' && !modal.hasAttribute('hidden')) {
                closeModal(modal, escClose, false);
            }
        }

        if (okBtn) {
            okBtn.addEventListener('click', function () {
                closeModal(modal, escClose, true);
            });
            setTimeout(function () {
                try {
                    okBtn.focus();
                } catch (e) {}
            }, 80);
        }

        modal.addEventListener('click', function (e) {
            if (e.target && e.target.getAttribute && e.target.getAttribute('data-fraud-dismiss') === '1') {
                closeModal(modal, escClose, false);
            }
        });

        document.addEventListener('keydown', escClose);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
})();
