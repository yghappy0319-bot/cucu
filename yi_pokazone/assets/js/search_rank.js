/* ==========================================================================
   Pokazone - 실시간 인기 검색어
   ========================================================================== */

(function () {
    'use strict';

    var REFRESH_MS = 60000;
    var COLLAPSE_VISIBLE = 5;
    var apiUrl = '/page/search_rank_api.php';

    function initSearchRank() {
        document.querySelectorAll('[data-search-rank-root]').forEach(function (root) {
            bindRoot(root);
        });
    }

    function bindRoot(root) {
        var form = root.querySelector('form[role="search"], form.search-box, form.hero-search, form.search-page-form');
        var input = form ? form.querySelector('input[type="search"], input[name="q"]') : null;
        var dropdown = root.querySelector('[data-search-rank-dropdown]');
        var list = root.querySelector('[data-search-rank-list]');
        var updatedEl = root.querySelector('[data-search-rank-updated]');
        var moreBtn = root.querySelector('[data-search-rank-more]');
        var canCollapse = root.getAttribute('data-search-rank-collapse') === '1' && !!moreBtn;

        if (!list) return;

        var refreshTimer = null;
        var isExpanded = false;

        function setUpdatedLabel(iso) {
            if (!updatedEl) return;
            if (!iso) {
                updatedEl.textContent = '';
                return;
            }
            try {
                var d = new Date(iso);
                var hh = String(d.getHours()).padStart(2, '0');
                var mm = String(d.getMinutes()).padStart(2, '0');
                updatedEl.textContent = hh + ':' + mm + ' 기준';
            } catch (e) {
                updatedEl.textContent = '';
            }
        }

        function applyCollapseState() {
            if (!canCollapse) return;
            var itemCount = list.querySelectorAll('.search-rank-item:not(.search-rank-empty)').length;
            var shouldCollapse = !isExpanded && itemCount > COLLAPSE_VISIBLE;
            list.classList.toggle('is-collapsed', shouldCollapse);
            if (shouldCollapse) {
                moreBtn.removeAttribute('hidden');
            } else {
                moreBtn.setAttribute('hidden', '');
            }
        }

        function renderItems(items) {
            if (!Array.isArray(items) || !items.length) {
                list.innerHTML = '<li class="search-rank-item search-rank-empty"><span class="search-rank-keyword">아직 집계된 검색어가 없습니다.</span></li>';
                applyCollapseState();
                return;
            }
            list.innerHTML = items.map(function (item) {
                var rank = Number(item.rank) || 0;
                var kw = String(item.keyword || '');
                var trend = String(item.trend || 'same');
                var trendDelta = Number(item.trend_delta) || 0;
                var trendLabel = String(item.trend_label || '');
                var url = String(item.url || ('/page/search.php?q=' + encodeURIComponent(kw)));
                var topClass = rank <= 3 ? ' is-top' : '';
                var trendHtml = '';
                if (!trendLabel && trend === 'new') {
                    trendLabel = 'NEW';
                } else if (!trendLabel && trend === 'up' && trendDelta > 0) {
                    trendLabel = '\u25B2' + trendDelta;
                } else if (!trendLabel && trend === 'down' && trendDelta > 0) {
                    trendLabel = '\u25BC' + trendDelta;
                }
                if (trendLabel) {
                    var trendAria = trend === 'new'
                        ? '신규 진입'
                        : (trend === 'up'
                            ? trendDelta + '단계 상승'
                            : (trend === 'down' ? trendDelta + '단계 하락' : trendLabel));
                    trendHtml = '<span class="search-rank-trend search-rank-trend--' + escapeAttr(trend) +
                        '" aria-label="' + escapeAttr(trendAria) + '">' + escapeHtml(trendLabel) + '</span>';
                }
                return (
                    '<li class="search-rank-item">' +
                    '<a href="' + escapeAttr(url) + '" class="search-rank-link">' +
                    '<span class="search-rank-num' + topClass + '" aria-hidden="true">' + rank + '</span>' +
                    '<span class="search-rank-keyword">' + escapeHtml(kw) + '</span>' +
                    trendHtml +
                    '</a></li>'
                );
            }).join('');
            applyCollapseState();
        }

        function refresh() {
            fetch(apiUrl + '?limit=10', {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            })
                .then(function (res) {
                    return res.json();
                })
                .then(function (data) {
                    if (!data || !data.ok) return;
                    renderItems(data.items || []);
                    setUpdatedLabel(data.updated_at);
                })
                .catch(function () {});
        }

        function openDropdown() {
            if (!dropdown) return;
            dropdown.removeAttribute('hidden');
            refresh();
            if (refreshTimer) clearInterval(refreshTimer);
            refreshTimer = setInterval(refresh, REFRESH_MS);
        }

        function closeDropdown() {
            if (!dropdown) return;
            dropdown.setAttribute('hidden', '');
            if (refreshTimer) {
                clearInterval(refreshTimer);
                refreshTimer = null;
            }
        }

        if (dropdown && input) {
            input.addEventListener('focus', openDropdown);
            input.addEventListener('click', openDropdown);

            document.addEventListener('click', function (e) {
                if (!root.contains(e.target)) {
                    closeDropdown();
                }
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') closeDropdown();
            });
        }

        if (canCollapse) {
            moreBtn.addEventListener('click', function () {
                isExpanded = true;
                applyCollapseState();
            });
            applyCollapseState();
        }

        if (root.getAttribute('data-search-rank-auto-refresh') === '1') {
            refresh();
            setInterval(refresh, REFRESH_MS);
        }
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function escapeAttr(str) {
        return escapeHtml(str).replace(/'/g, '&#39;');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSearchRank);
    } else {
        initSearchRank();
    }
})();
