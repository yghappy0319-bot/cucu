/**
 * 경매 마감 카운트다운 (목록·상세 공통)
 * - 페이지당 setInterval 1개만 사용
 * - 탭 비활성 시 tick 생략, 복귀 시 1회 갱신
 */
(function () {
    'use strict';

    var els = document.querySelectorAll('[data-auction-ends]');
    if (!els.length) {
        return;
    }

    function formatRemain(endsSec) {
        var diff = Math.max(0, endsSec - Math.floor(Date.now() / 1000));
        if (diff <= 0) {
            return '종료됨';
        }
        var d = Math.floor(diff / 86400);
        var h = Math.floor((diff % 86400) / 3600);
        var m = Math.floor((diff % 3600) / 60);
        var s = diff % 60;
        var parts = [];
        if (d > 0) {
            parts.push(d + '일');
        }
        if (d > 0 || h > 0) {
            parts.push(h + '시간');
        }
        parts.push(m + '분');
        parts.push(s + '초');

        return parts.join(' ') + ' 남음';
    }

    function tick() {
        var live = 0;
        for (var i = 0; i < els.length; i++) {
            var el = els[i];
            if (el.getAttribute('data-auction-done') === '1') {
                continue;
            }
            var ends = parseInt(el.getAttribute('data-auction-ends'), 10);
            if (!ends) {
                continue;
            }
            var text = formatRemain(ends);
            el.textContent = text;
            if (text === '종료됨') {
                el.setAttribute('data-auction-done', '1');
                el.classList.add('is-ended');
            } else {
                live++;
            }
        }
        return live;
    }

    var timerId = null;

    function startTimer() {
        if (timerId !== null) {
            return;
        }
        timerId = window.setInterval(function () {
            if (document.hidden) {
                return;
            }
            if (tick() === 0) {
                window.clearInterval(timerId);
                timerId = null;
            }
        }, 1000);
    }

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden && tick() > 0) {
            startTimer();
        }
    });

    if (tick() > 0) {
        startTimer();
    }
})();
