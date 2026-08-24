/* ==========================================================================
   Pokazone - Main Script
   ========================================================================== */

(function () {
    'use strict';

    initMobileMenu();

    var pokazoneMainRan = false;
    function runPokazoneMain() {
        if (pokazoneMainRan) return;
        pokazoneMainRan = true;
        initCategoryChips();
        initLikeButtons();
        initCommunityReplyToggle();
        initSmoothScroll();
        initShareLinkButtons();
        initServiceWorker();
        initFooterAccordion();
        initGLightbox();
        initSwiper();
        initAOS();
        initSitePresenceHeartbeat();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', runPokazoneMain);
    } else {
        runPokazoneMain();
    }

    /** AOS 스크롤 애니메이션 (요소에 data-aos 속성) — 라이브러리 미로드 시 무시 */
    function initAOS() {
        if (typeof AOS === 'undefined') return;
        if (!document.querySelector('[data-aos]')) return;
        try {
            AOS.init({
                duration: 600,
                easing: 'ease-out-cubic',
                once: true,
                offset: 48,
            });
        } catch (e) {}
    }

    /** Swiper (.swiper + .swiper-wrapper 구조) — 라이브러리 미로드·마크업 없으면 무시 */
    function initSwiper() {
        if (typeof Swiper === 'undefined') return;
        var list = document.querySelectorAll('.swiper');
        if (!list.length) return;
        list.forEach(function (el) {
            if (el.dataset && el.dataset.swiperSuppress === '1') return;
            if (!el.querySelector('.swiper-wrapper')) return;
            if (el.swiper) return;
            try {
                new Swiper(el, {
                    loop: false,
                    slidesPerView: 1,
                    spaceBetween: 16,
                    watchOverflow: true,
                });
            } catch (e) {}
        });
    }

    /** 이미지 라이트박스 (<a href="큰이미지.jpg" class="glightbox">) — 라이브러리 미로드 시 무시 */
    function initGLightbox() {
        var G =
            typeof window !== 'undefined' && typeof window.GLightbox === 'function'
                ? window.GLightbox
                : null;
        if (!G) return;
        if (!document.querySelector('.glightbox')) return;
        try {
            G({
                selector: '.glightbox',
                touchNavigation: true,
                loop: false,
            });
        } catch (e) {}
    }

    // Mobile menu — 바깥 닫기만 document capture · 토글은 버튼에 직접 연결(iOS 등 안정화)
    function initMobileMenu() {
        if (window.__pzMobileMenuBound) return;
        window.__pzMobileMenuBound = true;

        function navEl() {
            return (
                document.getElementById('mainNav') ||
                document.querySelector('header.site-header nav.nav') ||
                document.querySelector('header nav.nav')
            );
        }

        function toggleEl() {
            return (
                document.getElementById('menuToggle') ||
                document.querySelector('header.site-header button.menu-toggle[type="button"]') ||
                document.querySelector('header button.menu-toggle') ||
                document.querySelector('button.menu-toggle[type="button"]')
            );
        }

        /**
         * 모바일 메뉴 디버그 (콘솔)
         * - URL: ?pz_debug_nav=1
         * - sessionStorage: pz_debug_nav = 1
         * - localStorage: pz_debug_nav = 1
         */
        function pzNavDebug(msg, detail) {
            try {
                var q = '';
                try {
                    q = typeof window.URLSearchParams !== 'undefined'
                        ? new URLSearchParams(window.location.search || '').get('pz_debug_nav')
                        : '';
                } catch (e0) {}
                var on = q === '1';
                try {
                    if (!on && window.sessionStorage && window.sessionStorage.getItem('pz_debug_nav') === '1') {
                        on = true;
                    }
                } catch (e1) {}
                try {
                    if (!on && window.localStorage && window.localStorage.getItem('pz_debug_nav') === '1') {
                        on = true;
                    }
                } catch (e1b) {}
                if (!on) return;
                if (detail !== undefined) console.log('[pz-nav]', msg, detail);
                else console.log('[pz-nav]', msg);
            } catch (e2) {}
        }

        var ignoreOutsideUntil = 0;

        function setOpen(open) {
            var nav = navEl();
            var toggle = toggleEl();
            if (!nav || !toggle) {
                pzNavDebug('setOpen skipped — nav or toggle missing', { nav: !!nav, toggle: !!toggle });
                return;
            }
            nav.classList.toggle('is-open', open);
            document.body.classList.toggle('menu-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) {
                ignoreOutsideUntil = Date.now() + 520;
            }
            pzNavDebug('menu ' + (open ? 'opened' : 'closed'), {
                vw: typeof window.innerWidth === 'number' ? window.innerWidth : null,
            });
        }

        /** 버튼 직결보다 문서 위임(capture)이 레이어·터치 환경에서 더 안정적 */
        var lastFlipTs = 0;
        function flipMenu() {
            var now = Date.now();
            if (now - lastFlipTs < 380) {
                pzNavDebug('flip ignored (연속 입력 완충)');
                return;
            }
            lastFlipTs = now;
            var n = navEl();
            if (!n) return;
            setOpen(!n.classList.contains('is-open'));
        }

        function targetIsMenuToggle(ev) {
            var t = ev.target;
            if (!t || typeof t.closest !== 'function') return false;
            return !!t.closest('#menuToggle');
        }

        /** window 캡처: document 에 먼저 붙은 스크립트보다 선행해 메뉴 토글을 처리 */
        window.addEventListener(
            'pointerup',
            function (ev) {
                if (!ev.isPrimary) return;
                if (ev.pointerType === 'mouse' && ev.button !== 0) return;
                if (!targetIsMenuToggle(ev)) return;
                if (ev.pointerType === 'touch' || ev.pointerType === 'pen') {
                    try {
                        ev.preventDefault();
                    } catch (pe) {}
                }
                try {
                    ev.stopPropagation();
                } catch (se) {}
                pzNavDebug('menuToggle pointerup → flip');
                flipMenu();
            },
            true
        );

        window.addEventListener(
            'click',
            function (ev) {
                if (!targetIsMenuToggle(ev)) return;
                try {
                    ev.preventDefault();
                } catch (pe) {}
                try {
                    ev.stopPropagation();
                } catch (se) {}
                pzNavDebug('menuToggle click → flip');
                flipMenu();
            },
            true
        );

        function warnIfToggleMissing() {
            function tick() {
                if (toggleEl() && navEl()) return;
                try {
                    console.warn(
                        '[pz-nav] #menuToggle 또는 #mainNav 요소가 이 페이지에 없습니다. 헤더를 포함했는지 확인하세요.'
                    );
                } catch (e) {}
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function () {
                    requestAnimationFrame(tick);
                });
            } else {
                requestAnimationFrame(tick);
            }
        }
        warnIfToggleMissing();

        document.addEventListener(
            'click',
            function (e) {
                var nav = navEl();
                if (!nav || !nav.classList.contains('is-open')) return;
                var a = e.target.closest ? e.target.closest('a') : null;
                if (!a || !nav.contains(a)) return;
                setOpen(false);
            },
            false
        );

        document.addEventListener(
            'click',
            function (e) {
                var nav = navEl();
                var toggle = toggleEl();
                if (!nav || !toggle) return;

                /* 열린 상태에서 패널·버튼 밖을 눌렀을 때만 닫음 (토글은 버튼 핸들러에서 처리) */
                if (!nav.classList.contains('is-open')) return;
                if (Date.now() < ignoreOutsideUntil) return;
                if (toggle.contains(e.target) || nav.contains(e.target)) return;
                pzNavDebug('outside click → close');
                setOpen(false);
            },
            true
        );

        document.addEventListener('keydown', function (e) {
            var nav = navEl();
            if (e.key !== 'Escape' || !nav || !nav.classList.contains('is-open')) return;
            setOpen(false);
        });

        window.addEventListener('resize', function () {
            var nav = navEl();
            if (!nav || !nav.classList.contains('is-open')) return;
            if (window.innerWidth > 768) setOpen(false);
        });

        pzNavDebug(
            'initMobileMenu 등록 완료(메뉴 토글: document capture 위임)'
        );
    }

    // Category chips active state
    function initCategoryChips() {
        const groups = document.querySelectorAll('[data-chip-group]');
        groups.forEach(function (group) {
            const chips = group.querySelectorAll('.chip');
            chips.forEach(function (chip) {
                chip.addEventListener('click', function () {
                    chips.forEach(function (c) { c.classList.remove('is-active'); });
                    chip.classList.add('is-active');
                });
            });
        });
    }

    // Card like button toggle
    function initLikeButtons() {
        document.querySelectorAll('.like').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                btn.classList.toggle('is-liked');
                btn.style.color = btn.classList.contains('is-liked') ? '#ef4444' : '';
                btn.textContent = btn.classList.contains('is-liked') ? '♥' : '♡';
            });
        });
    }

    // 커뮤니티 댓글 답글 입력 토글
    function initCommunityReplyToggle() {
        document.querySelectorAll('.js-reply-toggle').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-target');
                if (!id) return;
                var el = document.getElementById(id);
                if (!el) return;
                var open = el.hasAttribute('hidden');
                document.querySelectorAll('.co-comment-reply-wrap').forEach(function (w) {
                    w.setAttribute('hidden', '');
                });
                if (open) {
                    el.removeAttribute('hidden');
                    var ta = el.querySelector('textarea');
                    if (ta) ta.focus();
                }
            });
        });
    }

    // Smooth scroll for anchor links
    function initSmoothScroll() {
        document.querySelectorAll('a[href^="#"]').forEach(function (a) {
            a.addEventListener('click', function (e) {
                const id = a.getAttribute('href');
                if (id === '#' || id.length < 2) return;
                const target = document.querySelector(id);
                if (!target) return;
                e.preventDefault();
                const top = target.getBoundingClientRect().top + window.pageYOffset - 72;
                window.scrollTo({ top: top, behavior: 'smooth' });
            });
        });
    }

    function copyTextToClipboard(text, onOk, onFail) {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(onOk).catch(function () {
                fallbackCopyText(text, onOk, onFail);
            });
        } else {
            fallbackCopyText(text, onOk, onFail);
        }
    }

    function fallbackCopyText(text, onOk, onFail) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        try {
            var ok = document.execCommand('copy');
            document.body.removeChild(ta);
            if (ok) onOk(); else onFail();
        } catch (e) {
            document.body.removeChild(ta);
            onFail();
        }
    }

    /** 모바일: 푸터 링크 접기 / 데스크푸: 항상 펼침 */
    function initFooterAccordion() {
        var list = document.querySelectorAll('details.footer-col--acc');
        if (!list.length) return;
        var mq = window.matchMedia('(min-width: 769px)');
        function sync() {
            var open = mq.matches;
            list.forEach(function (d) {
                d.open = open;
            });
        }
        sync();
        if (typeof mq.addEventListener === 'function') {
            mq.addEventListener('change', sync);
        } else if (typeof mq.addListener === 'function') {
            mq.addListener(sync);
        }
    }

    /** HTTPS(또는 localhost)에서만 등록 — 하위 경로 설치 시 sw 경로·scope 을 맞춤 (Web Push 와 동일) */
    function initServiceWorker() {
        if (!('serviceWorker' in navigator)) return;
        var isLocal = /^localhost$|^127\.0\.0\.1$/.test(location.hostname);
        if (location.protocol !== 'https:' && !isLocal) return;
        var pre = '';
        if (typeof window.__PZ_PUBLIC_PREFIX__ === 'string' && window.__PZ_PUBLIC_PREFIX__ !== '') {
            pre = '/' + window.__PZ_PUBLIC_PREFIX__.replace(/^\/+|\/+$/g, '');
        }
        var swUrl = pre + '/sw.js';
        var swScope = pre === '' ? '/' : pre + '/';
        window.addEventListener('load', function () {
            navigator.serviceWorker.register(swUrl, { scope: swScope }).catch(function () {});
        });
    }

    /** 같은 페이지에 머무는 접속자 — 2분마다 heartbeat */
    function initSitePresenceHeartbeat() {
        var ping = function () {
            if (document.hidden) return;
            fetch('/page/site_presence_api.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            }).catch(function () {});
        };
        setInterval(ping, 120000);
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) ping();
        });
    }

    // [data-share-url] — 링크 복사 (거래/커뮤니티 글 보기 등)
    function initShareLinkButtons() {
        document.querySelectorAll('[data-share-url]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var url = btn.getAttribute('data-share-url');
                if (!url) return;
                var label = btn.getAttribute('data-share-label') || '공유하기';
                copyTextToClipboard(
                    url,
                    function () {
                        btn.textContent = '링크 복사됨';
                        setTimeout(function () {
                            btn.textContent = label;
                        }, 2000);
                    },
                    function () {
                        window.alert('링크를 복사할 수 없습니다. 아래 주소를 직접 복사해 주세요.\n\n' + url);
                    }
                );
            });
        });
    }
})();
