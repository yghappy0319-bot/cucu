(function () {
    'use strict';

    var modal = document.getElementById('auction-win-modal');
    if (!modal) {
        return;
    }

    var auIdx = modal.getAttribute('data-au-idx') || '';
    var storageKey = 'auction_win_dismiss_' + auIdx;

    function openModal() {
        if (sessionStorage.getItem(storageKey) === '1') {
            return;
        }
        modal.removeAttribute('hidden');
        modal.setAttribute('aria-hidden', 'false');
        document.documentElement.classList.add('auction-win-modal-open');
    }

    function closeModal(persist) {
        modal.setAttribute('hidden', '');
        modal.setAttribute('aria-hidden', 'true');
        document.documentElement.classList.remove('auction-win-modal-open');
        if (persist) {
            sessionStorage.setItem(storageKey, '1');
        }
    }

    modal.querySelectorAll('[data-auction-win-dismiss]').forEach(function (el) {
        el.addEventListener('click', function () {
            closeModal(true);
        });
    });

    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape' && !modal.hasAttribute('hidden')) {
            closeModal(true);
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', openModal);
    } else {
        openModal();
    }
})();
