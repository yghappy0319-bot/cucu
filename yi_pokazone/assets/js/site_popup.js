/**
 * 사이트 레이어 팝업
 * - localStorage 로 N일간 숨김
 * - 복수 팝업은 순차 표시
 */
(function () {
  'use strict';

  var list = Array.isArray(window.__SITE_POPUPS__) ? window.__SITE_POPUPS__.slice() : [];
  if (!list.length) return;

  var root = document.getElementById('site-popup-root');
  if (!root) return;

  var STORAGE_PREFIX = 'pz_popup_hide_';
  var queue = [];
  var current = null;

  function hideKey(id) {
    return STORAGE_PREFIX + String(id);
  }

  function isHidden(id) {
    try {
      var raw = localStorage.getItem(hideKey(id));
      if (!raw) return false;
      var until = parseInt(raw, 10);
      if (!until || Date.now() > until) {
        localStorage.removeItem(hideKey(id));
        return false;
      }
      return true;
    } catch (e) {
      return false;
    }
  }

  function setHidden(id, days) {
    try {
      var ms = Math.max(1, days || 1) * 24 * 60 * 60 * 1000;
      localStorage.setItem(hideKey(id), String(Date.now() + ms));
    } catch (e) {}
  }

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function closeCurrent(saveHide) {
    if (!current) return;
    if (saveHide) {
      var chk = root.querySelector('[data-popup-hide]');
      if (chk && chk.checked) {
        setHidden(current.id, current.hide_days || 1);
      }
    }
    root.hidden = true;
    root.innerHTML = '';
    document.body.classList.remove('is-site-popup-open');
    current = null;
    showNext();
  }

  function showNext() {
    if (!queue.length) return;
    current = queue.shift();
    render(current);
  }

  function render(p) {
    var w = Math.max(240, Math.min(900, p.width || 400));
    var hideLabel = (p.hide_days || 1) === 1
      ? '오늘 하루 보지 않기'
      : (p.hide_days + '일간 보지 않기');

    var media = '';
    if (p.image) {
      var img = '<img src="' + esc(p.image) + '" alt="' + esc(p.title) + '">';
      if (p.link) {
        media = '<a class="site-popup__media" href="' + esc(p.link) + '">' + img + '</a>';
      } else {
        media = '<div class="site-popup__media">' + img + '</div>';
      }
    }

    var body = '';
    if (p.content) {
      body = '<div class="site-popup__body">' + p.content + '</div>';
    }

    var linkBtn = '';
    if (p.link && !p.image) {
      linkBtn = '<a class="site-popup__link btn btn-primary btn-sm" href="' + esc(p.link) + '">자세히 보기</a>';
    }

    root.innerHTML =
      '<div class="site-popup" role="dialog" aria-modal="true" aria-labelledby="site-popup-title">' +
        '<div class="site-popup__backdrop" data-popup-close="1"></div>' +
        '<div class="site-popup__box" style="max-width:' + w + 'px">' +
          '<button type="button" class="site-popup__x" data-popup-close="1" aria-label="닫기">×</button>' +
          '<h2 id="site-popup-title" class="site-popup__title">' + esc(p.title) + '</h2>' +
          media +
          body +
          '<div class="site-popup__foot">' +
            '<label class="site-popup__hide">' +
              '<input type="checkbox" data-popup-hide value="1">' +
              '<span>' + esc(hideLabel) + '</span>' +
            '</label>' +
            '<div class="site-popup__actions">' +
              linkBtn +
              '<button type="button" class="btn btn-outline btn-sm" data-popup-close="1">닫기</button>' +
            '</div>' +
          '</div>' +
        '</div>' +
      '</div>';

    root.hidden = false;
    document.body.classList.add('is-site-popup-open');
  }

  root.addEventListener('click', function (e) {
    var t = e.target;
    if (!t) return;
    if (t.closest && t.closest('[data-popup-close]')) {
      e.preventDefault();
      closeCurrent(true);
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && current) {
      closeCurrent(true);
    }
  });

  list.forEach(function (p) {
    if (!p || !p.id) return;
    if (isHidden(p.id)) return;
    if (!p.image && !p.content) return;
    queue.push(p);
  });

  if (queue.length) {
    // 살짝 지연해 페이지 페인트 후 표시
    setTimeout(showNext, 350);
  }
})();
