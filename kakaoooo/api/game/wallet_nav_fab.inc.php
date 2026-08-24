<?php
/**
 * 가방·게임 페이지 공통 UI
 * - 상단 왼쪽: < 가방 (가방 메인으로)
 * - 우하단 플로팅: 본방 / 홍보 / 가방 / 스왑
 *
 * 사용:
 *   require_once __DIR__ . '/wallet_nav_fab.inc.php';
 *   wallet_nav_fab_render(['code' => $code]);
 *   // 옵션: show_back, show_bag, show_swap, bag_href, swap_href, swap_js, theme
 */

if (!function_exists('wallet_nav_fab_urls')) {
  function wallet_nav_fab_urls(): array {
    global $본방주소;
    $bonbang = __DIR__ . '/../_bonbang.php';
    if (is_file($bonbang)) {
      include_once $bonbang;
    }
    return [
      '본방' => (!empty($본방주소)) ? (string)$본방주소 : 'https://open.kakao.com/o/pIe2XDJi',
      '홍보방' => 'https://open.kakao.com/o/prP2JBJi',
    ];
  }
}

if (!function_exists('wallet_nav_fab_code_query')) {
  function wallet_nav_fab_code_query(string $code): string {
    $code = trim($code);
    if ($code === '') {
      return '';
    }
    if (function_exists('wallet_code_query')) {
      return wallet_code_query($code);
    }
    return '?code=' . rawurlencode($code);
  }
}

if (!function_exists('wallet_nav_fab_본방냥표시')) {
  /** @param mixed $newpoint */
  function wallet_nav_fab_본방냥표시($newpoint): string {
    if (function_exists('newpoint표시')) {
      return (string)newpoint표시($newpoint);
    }
    if (function_exists('wallet_fmt_new')) {
      return (string)wallet_fmt_new($newpoint);
    }
    return number_format((int)floor((float)$newpoint), 0, '.', ',');
  }
}

if (!function_exists('wallet_nav_fab_본방냥조회')) {
  /** code 또는 전달값으로 본방냥 표시 문자열 */
  function wallet_nav_fab_본방냥조회(string $code, $newpoint = null, string $newpointFmt = ''): string {
    $fmt = trim($newpointFmt);
    if ($fmt !== '') {
      return $fmt;
    }
    if ($newpoint !== null && $newpoint !== '') {
      return wallet_nav_fab_본방냥표시($newpoint);
    }
    $code = trim($code);
    if ($code === '' || !function_exists('db_select')) {
      return '';
    }
    $esc = addslashes($code);
    $row = @db_select("SELECT IFNULL(newpoint, 0) AS newpoint FROM tb_member WHERE code = '{$esc}' LIMIT 1");
    if ($row === null || !isset($row['newpoint'])) {
      return '';
    }
    return wallet_nav_fab_본방냥표시($row['newpoint']);
  }
}

if (!function_exists('wallet_nav_fab_styles_once')) {
  function wallet_nav_fab_styles_once(): void {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    echo <<<'CSS'
<style id="wallet-nav-fab-css">
.wn-fab {
  position: fixed;
  right: max(14px, env(safe-area-inset-right));
  bottom: max(18px, env(safe-area-inset-bottom));
  z-index: 90;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 10px;
  pointer-events: none;
}
.wn-fab-panel {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 8px;
  pointer-events: auto;
}
.wn-fab-panel[hidden] { display: none !important; }
.wn-fab-item,
.wn-fab-toggle {
  pointer-events: auto;
  appearance: none;
  border: 1px solid rgba(255,255,255,0.16);
  background: linear-gradient(160deg, rgba(36, 48, 64, 0.96), rgba(22, 28, 38, 0.98));
  color: #e8e4dc;
  box-shadow: 0 10px 28px rgba(0,0,0,0.35);
  cursor: pointer;
  text-decoration: none;
  font-family: inherit;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
  -webkit-tap-highlight-color: transparent;
}
.wn-fab-item:hover,
.wn-fab-toggle:hover {
  transform: translateY(-1px);
  box-shadow: 0 12px 30px rgba(0,0,0,0.42);
}
.wn-fab-item {
  min-height: 42px;
  padding: 0 14px 0 10px;
  border-radius: 999px;
  font-size: 0.82rem;
  font-weight: 700;
}
.wn-fab-item:disabled {
  opacity: 0.45;
  cursor: not-allowed;
  transform: none;
}
.wn-fab-ico { font-size: 1.05rem; line-height: 1; }
.wn-fab-np {
  font-size: 0.7rem;
  font-weight: 700;
  color: #6ee7b7;
  letter-spacing: -0.02em;
  max-width: 7.5em;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
body.theme-white .wn-fab-np,
.wn-fab.theme-light .wn-fab-np {
  color: #059669;
}
.wn-fab-toggle {
  width: 54px;
  height: 54px;
  border-radius: 50%;
  background: linear-gradient(160deg, rgba(110, 231, 183, 0.28), rgba(36, 48, 64, 0.96));
  border-color: rgba(110, 231, 183, 0.45);
}
.wn-fab-toggle[aria-expanded="true"] {
  background: linear-gradient(160deg, rgba(110, 231, 183, 0.4), rgba(36, 48, 64, 0.98));
}
.wn-fab-toggle-ico { font-size: 1.35rem; line-height: 1; }
.wn-fab--indigo .wn-fab-toggle {
  background: linear-gradient(160deg, rgba(129, 140, 248, 0.4), rgba(45, 53, 97, 0.96));
  border-color: rgba(165, 180, 252, 0.5);
}
.wn-fab--indigo .wn-fab-item,
.wn-fab--indigo .wn-fab-toggle {
  background: linear-gradient(160deg, rgba(45, 53, 97, 0.96), rgba(24, 28, 52, 0.98));
}
.wn-fab--indigo .wn-fab-toggle[aria-expanded="true"] {
  background: linear-gradient(160deg, rgba(129, 140, 248, 0.55), rgba(45, 53, 97, 0.98));
}
body.theme-white .wn-fab-item,
body.theme-white .wn-fab-toggle,
.wn-fab.theme-light .wn-fab-item,
.wn-fab.theme-light .wn-fab-toggle {
  color: #1e293b;
  background: linear-gradient(160deg, #f8fafc, #e2e8f0);
  border-color: rgba(15,23,42,0.12);
  box-shadow: 0 8px 20px rgba(15,23,42,0.12);
}
body.theme-white .wn-fab-toggle,
.wn-fab.theme-light .wn-fab-toggle {
  background: linear-gradient(160deg, #d1fae5, #e2e8f0);
  border-color: rgba(16, 185, 129, 0.4);
}
.wn-promo-modal {
  display: none;
  position: fixed;
  inset: 0;
  z-index: 110;
  background: rgba(0,0,0,0.65);
  align-items: center;
  justify-content: center;
  padding: 16px;
}
.wn-promo-modal.open { display: flex; }
.wn-promo-box {
  width: 100%;
  max-width: 340px;
  background: #1a1f35;
  border: 1px solid rgba(255,255,255,0.12);
  border-radius: 14px;
  padding: 18px 16px 14px;
  color: #e8e4dc;
  box-shadow: 0 16px 40px rgba(0,0,0,0.45);
}
.wn-promo-box h3 { margin: 0 0 10px; font-size: 1.05rem; }
.wn-promo-box p { margin: 0 0 8px; font-size: 0.86rem; line-height: 1.5; color: #cbd5e1; }
.wn-promo-box ul { margin: 0 0 14px; padding-left: 1.1em; font-size: 0.84rem; line-height: 1.55; color: #cbd5e1; }
.wn-promo-box .emph { color: #6ee7b7; font-weight: 700; }
.wn-promo-actions { display: flex; gap: 8px; }
.wn-promo-actions button {
  flex: 1;
  min-height: 42px;
  border-radius: 10px;
  border: 1px solid rgba(255,255,255,0.12);
  font-family: inherit;
  font-weight: 700;
  cursor: pointer;
}
.wn-promo-actions .btn-cancel { background: rgba(255,255,255,0.06); color: #e2e8f0; }
.wn-promo-actions .btn-go { background: linear-gradient(145deg, #34d399, #059669); color: #052e16; border-color: transparent; }
body.theme-white .wn-promo-box {
  background: #fff;
  color: #0f172a;
  border-color: rgba(15,23,42,0.1);
}
body.theme-white .wn-promo-box p,
body.theme-white .wn-promo-box ul { color: #475569; }
body.theme-white .wn-promo-box .emph { color: #059669; }
.wn-back-bag {
  position: fixed;
  top: max(10px, env(safe-area-inset-top));
  left: max(10px, env(safe-area-inset-left));
  z-index: 96;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  min-height: 34px;
  padding: 6px 12px 6px 10px;
  border-radius: 999px;
  border: 1px solid rgba(255,255,255,0.16);
  background: rgba(22, 28, 38, 0.88);
  color: #e8e4dc;
  text-decoration: none;
  font-size: 13px;
  font-weight: 700;
  font-family: inherit;
  letter-spacing: -0.02em;
  box-shadow: 0 6px 18px rgba(0,0,0,0.28);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  -webkit-tap-highlight-color: transparent;
}
.wn-back-bag:active { transform: scale(0.97); }
.wn-back-bag .wn-back-chevron {
  font-size: 15px;
  line-height: 1;
  opacity: 0.9;
}
.wn-back-bag.theme-light,
body.theme-white .wn-back-bag {
  background: rgba(255,255,255,0.92);
  color: #0f172a;
  border-color: rgba(15,23,42,0.12);
  box-shadow: 0 6px 18px rgba(15,23,42,0.12);
}
</style>
CSS;
  }
}

if (!function_exists('wallet_nav_fab_render')) {
  /**
   * @param array{
   *   code?:string,
   *   show_back?:bool,
   *   show_bag?:bool,
   *   show_swap?:bool,
   *   bag_href?:string,
   *   swap_href?:string,
   *   swap_js?:string,
   *   newpoint?:mixed,
   *   newpoint_fmt?:string,
   *   theme?:string,
   *   variant?:string
   * } $opts
   */
  function wallet_nav_fab_render(array $opts = []): void {
    if (!empty($GLOBALS['wallet_nav_fab_rendered'])) {
      return;
    }
    $GLOBALS['wallet_nav_fab_rendered'] = true;

    $code = trim((string)($opts['code'] ?? ''));
    $q = wallet_nav_fab_code_query($code);
    $urls = wallet_nav_fab_urls();
    $bon = htmlspecialchars((string)$urls['본방'], ENT_QUOTES, 'UTF-8');
    $promo = htmlspecialchars((string)$urls['홍보방'], ENT_QUOTES, 'UTF-8');

    $showBack = !array_key_exists('show_back', $opts) || !empty($opts['show_back']);
    $showBag = !array_key_exists('show_bag', $opts) || !empty($opts['show_bag']);
    $showSwap = !array_key_exists('show_swap', $opts) || !empty($opts['show_swap']);

    $bagHref = trim((string)($opts['bag_href'] ?? ''));
    if ($bagHref === '') {
      $bagHref = '/page/wallet.php' . $q;
    }
    $swapHref = trim((string)($opts['swap_href'] ?? ''));
    if ($swapHref === '') {
      $swapHref = '/page/wallet_swap.php' . $q;
    }
    $swapJs = trim((string)($opts['swap_js'] ?? ''));
    $theme = trim((string)($opts['theme'] ?? ''));
    $variant = trim((string)($opts['variant'] ?? ''));
    $fabClass = 'wn-fab';
    if ($theme === 'light') {
      $fabClass .= ' theme-light';
    }
    if ($variant === 'indigo') {
      $fabClass .= ' wn-fab--indigo';
    }

    $npFmt = wallet_nav_fab_본방냥조회(
      $code,
      $opts['newpoint'] ?? null,
      (string)($opts['newpoint_fmt'] ?? '')
    );
    $npEsc = htmlspecialchars($npFmt, ENT_QUOTES, 'UTF-8');
    $swapTitle = $npFmt !== ''
      ? ('본방냥 ' . $npFmt . ' · 스왑')
      : '본방냥→게임냥 스왑';
    $swapTitleEsc = htmlspecialchars($swapTitle, ENT_QUOTES, 'UTF-8');

    $bagHrefEsc = htmlspecialchars($bagHref, ENT_QUOTES, 'UTF-8');
    $swapHrefEsc = htmlspecialchars($swapHref, ENT_QUOTES, 'UTF-8');
    $swapJsEsc = htmlspecialchars($swapJs, ENT_QUOTES, 'UTF-8');

    $backClass = 'wn-back-bag';
    if ($theme === 'light') {
      $backClass .= ' theme-light';
    }

    wallet_nav_fab_styles_once();
    if ($showBack) { ?>
<a class="<?php echo htmlspecialchars($backClass, ENT_QUOTES, 'UTF-8'); ?>" href="<?php echo $bagHrefEsc; ?>" title="가방 메인으로">
  <span class="wn-back-chevron" aria-hidden="true">&lt;</span>
  <span>가방</span>
</a>
    <?php } ?>
<div class="<?php echo htmlspecialchars($fabClass, ENT_QUOTES, 'UTF-8'); ?>" id="wnFab">
  <div class="wn-fab-panel" id="wnFabPanel" hidden>
    <a class="wn-fab-item" href="<?php echo $bon; ?>" target="_blank" rel="noopener noreferrer" title="본방가기">
      <span class="wn-fab-ico" aria-hidden="true">🏠</span><span>본방</span>
    </a>
    <button type="button" class="wn-fab-item" id="wnFabPromo" data-href="<?php echo $promo; ?>" title="홍보방가기">
      <span class="wn-fab-ico" aria-hidden="true">📢</span><span>홍보</span>
    </button>
    <?php if ($showBag) { ?>
    <a class="wn-fab-item" href="<?php echo $bagHrefEsc; ?>" title="가방으로">
      <span class="wn-fab-ico" aria-hidden="true">🎒</span><span>가방</span>
    </a>
    <?php } ?>
    <?php if ($showSwap) {
      if ($swapJs !== '') { ?>
    <button type="button" class="wn-fab-item" id="wnFabSwap" data-swap-js="<?php echo $swapJsEsc; ?>" title="<?php echo $swapTitleEsc; ?>">
      <span class="wn-fab-ico" aria-hidden="true">💱</span><span>스왑</span><?php if ($npEsc !== '') { ?><span class="wn-fab-np" id="wnFabNp"><?php echo $npEsc; ?></span><?php } ?>
    </button>
      <?php } else { ?>
    <a class="wn-fab-item" href="<?php echo $swapHrefEsc; ?>" title="<?php echo $swapTitleEsc; ?>">
      <span class="wn-fab-ico" aria-hidden="true">💱</span><span>스왑</span><?php if ($npEsc !== '') { ?><span class="wn-fab-np" id="wnFabNp"><?php echo $npEsc; ?></span><?php } ?>
    </a>
      <?php }
    } ?>
  </div>
  <button type="button" class="wn-fab-toggle" id="wnFabToggle" aria-expanded="false" aria-controls="wnFabPanel" title="바로가기">
    <span class="wn-fab-toggle-ico" aria-hidden="true">💬</span>
  </button>
</div>
<div class="wn-promo-modal" id="wnPromoModal">
  <div class="wn-promo-box" onclick="event.stopPropagation()">
    <h3>📢 홍보방 안내</h3>
    <p>홍보방에 들어오는 친구들을 <span class="emph">본방으로 유도</span>하는 게 목적이지만,<br>홍보방에서 우리방 <span class="emph">게임 관련 이야기</span>를 할 수 있는 방이에요.</p>
    <ul>
      <li><span class="emph">본방</span>에서는 게임 이야기 금지</li>
      <li>홍보방 입장 시 <span class="emph">본방닉 2자리</span>로 입장할 것</li>
      <li>그래야 게임 내역이 연동돼요</li>
    </ul>
    <div class="wn-promo-actions">
      <button type="button" class="btn-cancel" id="wnPromoCancel">닫기</button>
      <button type="button" class="btn-go" id="wnPromoGo">홍보방 가기</button>
    </div>
  </div>
</div>
<script>
(function() {
  if (window.__wnFabInited) return;
  window.__wnFabInited = true;
  var fab = document.getElementById('wnFab');
  var toggle = document.getElementById('wnFabToggle');
  var panel = document.getElementById('wnFabPanel');
  var promoBtn = document.getElementById('wnFabPromo');
  var promoModal = document.getElementById('wnPromoModal');
  var promoCancel = document.getElementById('wnPromoCancel');
  var promoGo = document.getElementById('wnPromoGo');
  var swapBtn = document.getElementById('wnFabSwap');
  if (!fab || !toggle || !panel) return;
  function setOpen(open) {
    panel.hidden = !open;
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  }
  toggle.addEventListener('click', function(e) {
    e.stopPropagation();
    setOpen(panel.hidden);
  });
  document.addEventListener('click', function(e) {
    if (panel.hidden) return;
    if (fab.contains(e.target)) return;
    setOpen(false);
  });
  function openPromo() { if (promoModal) promoModal.classList.add('open'); }
  function closePromo() { if (promoModal) promoModal.classList.remove('open'); }
  function goPromo() {
    var href = promoBtn ? (promoBtn.getAttribute('data-href') || '') : '';
    if (!href) return;
    var w = window.open(href, '_blank');
    if (w) w.opener = null;
    closePromo();
  }
  if (promoBtn) promoBtn.addEventListener('click', openPromo);
  if (promoCancel) promoCancel.addEventListener('click', closePromo);
  if (promoGo) promoGo.addEventListener('click', goPromo);
  if (promoModal) {
    promoModal.addEventListener('click', function(e) {
      if (e.target === promoModal) closePromo();
    });
  }
  if (swapBtn) {
    swapBtn.addEventListener('click', function() {
      var fn = swapBtn.getAttribute('data-swap-js') || '';
      if (fn && typeof window[fn] === 'function') {
        window[fn]();
        return;
      }
      if (fn && fn.indexOf('.') >= 0) {
        try {
          var parts = fn.split('.');
          var ctx = window;
          for (var i = 0; i < parts.length; i++) {
            ctx = ctx[parts[i]];
            if (!ctx) return;
          }
          if (typeof ctx === 'function') ctx();
        } catch (err) {}
      }
    });
  }
})();
</script>
    <?php
  }
}
