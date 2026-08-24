(function () {
    'use strict';

    var STORAGE_KEY = 'pz_admin_nav_groups';

    function readState() {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return {};
            var parsed = JSON.parse(raw);
            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch (e) {
            return {};
        }
    }

    function writeState(state) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
        } catch (e) {}
    }

    function setExpanded(groupEl, expanded) {
        var btn = groupEl.querySelector('.ad-nav__group-toggle');
        groupEl.classList.toggle('is-collapsed', !expanded);
        if (btn) {
            btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        }
    }

    function initAdminNav() {
        var groups = document.querySelectorAll('.ad-nav__item--group[data-ad-nav-group]');
        if (!groups.length) return;

        var saved = readState();

        groups.forEach(function (groupEl) {
            var id = groupEl.getAttribute('data-ad-nav-group') || '';
            if (!id) return;

            var isActive = groupEl.getAttribute('data-ad-nav-active') === '1';
            if (!isActive && Object.prototype.hasOwnProperty.call(saved, id)) {
                setExpanded(groupEl, !!saved[id]);
            }

            var btn = groupEl.querySelector('.ad-nav__group-toggle');
            if (!btn) return;

            btn.addEventListener('click', function () {
                var expanded = groupEl.classList.contains('is-collapsed');
                setExpanded(groupEl, expanded);
                saved[id] = expanded;
                writeState(saved);
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAdminNav);
    } else {
        initAdminNav();
    }
})();
