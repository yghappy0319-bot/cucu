<?
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
include_once __DIR__ . '/_shop.php';

$code = shop_코드_해석();
$닉 = '';
$게임냥 = 0;
$본방냥 = 0;
$로그인 = false;

$현재강화 = 0;
$구매최소강화 = shop_마켓구매_최소강화();
$마켓구매가능 = false;
$회원 = shop_auth($code);
if ($회원) {
    $로그인 = true;
    $닉 = $회원['nick'];
    $게임냥 = $회원['point'];
    $본방냥 = (int)floor((float)($회원['newpoint'] ?? 0));
    $현재강화 = (int)($회원['enhance'] ?? 0);
    $마켓구매가능 = $현재강화 >= $구매최소강화;
    require_once dirname(__DIR__) . '/page/_game_quit_guard.php';
    게임포기_페이지가드($닉, $code, '마켓');
}
$관리자 = (bool)($로그인 ? shop_관리자_인증($code) : null);
$선매입가능유저 = $로그인 && shop_선매입_허용($닉);
$코드없음 = ($code === '');
$코드없음메시지 = shop_코드없음_메시지();
$마켓할인 = shop_마켓할인_상세($로그인 ? $닉 : '');
$마켓할인율 = (int)($마켓할인['total_pct'] ?? 0);
$마켓할인라벨 = (string)($마켓할인['label'] ?? '');

$상품목록 = shop_상품목록_조회();
$카테고리 = ['all' => ['label' => '전체', 'icon' => '🎁']] + shop_카테고리목록();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>도하 마켓 · 냥으로 사자</title>
  <link rel="stylesheet" href="/css/style.css?date=<?=time()?>">
  <?= shop_stylesheet_tag() ?>
</head>
<body>

<div class="shop-wrap">

  <header class="shop-header">
    <div class="shop-header-top">
      <div class="shop-logo">
        <div class="icon">🎫</div>
        <div>
          <h1>도하 마켓</h1>
          <p>친구들의 기프티콘을 냥으로 거래해요</p>
        </div>
      </div>
      <div class="user-bar">
        <? if ($로그인) { ?>
          <div class="nyang-badge">🐱 <span title="<?= htmlspecialchars(shop_냥_전체표시($게임냥), ENT_QUOTES, 'UTF-8') ?>냥"><?= shop_냥_표시($게임냥) ?></span> 게임냥</div>
          <div class="nyang-badge nyang-badge--np">💜 <span title="<?= number_format($본방냥) ?>냥"><?= shop_매입_지급_표시($본방냥) ?></span> 본방냥</div>
        <? } ?>
        <a href="<?= htmlspecialchars(shop_register_url($code), ENT_QUOTES, 'UTF-8') ?>" class="btn-register" onclick="return goRegister(event)">+ 등록하기</a>
      </div>
    </div>
    <? if (!$로그인) { ?>
      <div class="login-hint">
        <? if ($코드없음) { ?>
        💡 <?= htmlspecialchars($코드없음메시지, ENT_QUOTES, 'UTF-8') ?>
        <? } else { ?>
        💡 인증코드가 올바르지 않습니다. 연구실에서 코드를 다시 발급받으세요.
        <? } ?>
      </div>
    <? } else { ?>
      <div class="login-hint login-hint--success">
        ✅ <strong><?= htmlspecialchars($닉, ENT_QUOTES, 'UTF-8') ?></strong>님 환영해요! 기프티콘을 등록하거나 냥으로 구매할 수 있어요.
        <? if ($마켓구매가능) { ?>
        <br>🛒 마켓 구매 가능 · 현재 무기 <strong>+<?= (int)$현재강화 ?></strong> (최소 +<?= (int)$구매최소강화 ?>)
        <? } else { ?>
        <br>🔒 마켓 구매는 무기 <strong>+<?= (int)$구매최소강화 ?></strong> 이상만 가능해요. (현재 +<?= (int)$현재강화 ?>)
        <? } ?>
        <? if ($마켓할인율 > 0) { ?>
        <br>🏷 <?= htmlspecialchars($마켓할인라벨 !== '' ? $마켓할인라벨 : ('마켓 ' . $마켓할인율 . '% 할인'), ENT_QUOTES, 'UTF-8') ?> 적용 중
        <? } ?>
        <? if ($관리자) { ?>
        <span class="admin-mode-badge">👑 관리자 모드</span>
        <? } ?>
      </div>
    <? } ?>
  </header>

  <?= shop_nav_html($code, 'market') ?>

  <div class="shop-toolbar">
    <div class="category-tabs" id="category-tabs">
      <? foreach ($카테고리 as $key => $cat) { ?>
        <button type="button" class="cat-btn<?= $key === 'all' ? ' active' : '' ?>" data-cat="<?= $key ?>" onclick="setCategory('<?= $key ?>')">
          <?= $cat['icon'] ?> <?= $cat['label'] ?>
        </button>
      <? } ?>
    </div>
    <div class="toolbar-footer">
      <label class="sold-filter">
        <input type="checkbox" id="show-sold" onchange="setShowSold(this.checked)">
        <span>판매완료 보기</span>
      </label>
      <button type="button" class="view-mode-btn" id="view-mode-btn" onclick="toggleViewMode()" aria-pressed="false">
        <span id="view-mode-icon" aria-hidden="true">☰</span>
        <span id="view-mode-label">리스트로 보기</span>
      </button>
    </div>
  </div>

  <div class="product-grid" id="product-grid">
    <? if (empty($상품목록)) { ?>
    <div class="empty-state" id="empty-state">
      <div class="emoji">🎫</div>
      등록된 기프티콘이 없어요<br>위 <strong>등록하기</strong>로 첫 상품을 올려보세요!
    </div>
    <? } ?>
    <? foreach ($상품목록 as $상품) {
        $판매중 = ($상품['status'] === 'sale');
        $예약중 = ($상품['status'] === 'reserved');
        $판매완료 = ($상품['status'] === 'sold');
        $내상품 = $로그인 && $상품['seller'] === $닉;
        $매입됨 = !empty($상품['prebuy']);
        $매입가능 = $선매입가능유저 && $판매중 && (!$내상품 || shop_매입_본인상품_허용($닉)) && !$매입됨;
        $관리자수정가능 = shop_수정_허용($닉) && $판매중 && !$내상품;
        // 선매입시 본냥: 민호·해당 상품 판매자만 표시
        $선매입본냥표시 = $선매입가능유저 || $내상품;
        $결제원가 = shop_냥_값($상품['price']);
        $결제가 = $결제원가;
        if ($마켓할인율 > 0) {
            $결제가 = shop_금괴할인적용($결제원가, $마켓할인율);
            if (shop_냥_비교($결제가, '1') < 0) {
                $결제가 = '1';
            }
        }
    ?>
    <article
      class="product-card<?= !$판매중 ? ' sold-out' : '' ?>"
      data-id="<?= $상품['id'] ?>"
      data-category="<?= $상품['category'] ?>"
      data-brand="<?= htmlspecialchars($상품['brand'], ENT_QUOTES, 'UTF-8') ?>"
      data-name="<?= htmlspecialchars($상품['name'], ENT_QUOTES, 'UTF-8') ?>"
      data-price="<?= htmlspecialchars($결제원가, ENT_QUOTES, 'UTF-8') ?>"
      data-price-pay="<?= htmlspecialchars($결제가, ENT_QUOTES, 'UTF-8') ?>"
      data-discount-pct="<?= (int)$마켓할인율 ?>"
      <? if ($선매입본냥표시) { ?>data-price-np="<?= (int)$상품['price_np'] ?>"<? } ?>
      data-face-value="<?= (int)$상품['face_value'] ?>"
      data-seller="<?= htmlspecialchars($상품['seller'], ENT_QUOTES, 'UTF-8') ?>"
      data-market-tax="<?= htmlspecialchars((string)$상품['market_tax'], ENT_QUOTES, 'UTF-8') ?>"
      <? if ($선매입본냥표시) { ?>data-prebuy-np-per-10000="<?= (int)$상품['prebuy_np_per_10000'] ?>"<? } ?>
      data-mine="<?= $내상품 ? '1' : '0' ?>"
      data-expire="<?= $상품['expire'] ?>"
      data-status="<?= $상품['status'] ?>"
      data-prebuy="<?= $매입됨 ? '1' : '0' ?>"
      data-emoji="<?= $상품['emoji'] ?>"
    >
      <div class="product-thumb">
        <?= $상품['emoji'] ?>
        <? if ($예약중) { ?>
          <span class="product-badge reserved">예약중</span>
        <? } elseif ($판매완료) { ?>
          <span class="product-badge sold">판매완료</span>
        <? } elseif ($매입됨) { ?>
          <span class="product-badge prebuy">매입됨</span>
        <? } ?>
        <? if ($마켓할인율 > 0 && $판매중) { ?>
          <span class="product-badge discount">−<?= (int)$마켓할인율 ?>%</span>
        <? } ?>
      </div>
      <div class="product-body">
        <? if (trim((string)$상품['brand']) !== '') { ?>
        <div class="product-brand"><?= htmlspecialchars($상품['brand'], ENT_QUOTES, 'UTF-8') ?></div>
        <? } ?>
        <div class="product-title-row">
          <h2 class="product-name"><?= htmlspecialchars($상품['name'], ENT_QUOTES, 'UTF-8') ?></h2>
          <? if ($관리자수정가능) { ?>
          <a
            href="<?= htmlspecialchars(shop_edit_url($code, $상품['id']), ENT_QUOTES, 'UTF-8') ?>"
            class="btn-admin-edit"
            title="관리자 수정"
            aria-label="관리자 수정"
          >✏️</a>
          <? } ?>
        </div>
        <div class="product-meta">
          <? if ($관리자 && trim((string)$상품['seller']) !== '') { ?>
          <span class="product-seller"><?= htmlspecialchars($상품['seller'], ENT_QUOTES, 'UTF-8') ?></span>
          <? } ?>
          <span class="product-face"><?= number_format((int)$상품['face_value']) ?>원권 · <?= htmlspecialchars(shop_유통기한_표시($상품['expire']), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="product-footer<?= $매입가능 ? ' product-footer--admin' : '' ?>">
          <div class="product-price">
            <div class="product-price-row product-price-row--game">
              <span class="product-price-label">겜냥</span>
              <? if ($마켓할인율 > 0 && shop_냥_비교($결제원가, $결제가) > 0) { ?>
              <span class="product-price-list" title="<?= shop_냥_전체표시($결제원가) ?>냥"><?= shop_냥_표시($결제원가) ?></span>
              <? } ?>
              <span class="product-price-amount" title="<?= shop_냥_전체표시($결제가) ?>냥"><?= shop_냥_표시($결제가) ?></span><span class="product-price-unit">냥</span>
            </div>
            <? if ($마켓할인율 > 0) { ?>
            <div class="product-price-discount">마켓 −<?= (int)$마켓할인율 ?>%<?= !empty($마켓할인['gem_pct']) ? ' · 겜냥미션' : '' ?></div>
            <? } ?>
            <? if ($선매입본냥표시) { ?>
            <div class="product-price-row product-price-row--np">
              <span class="product-price-label">선매입시 본냥</span>
              <span class="product-price-amount" title="<?= number_format((int)$상품['price_np']) ?>냥"><?= shop_매입_지급_표시((int)$상품['price_np']) ?></span><span class="product-price-unit">냥</span>
            </div>
            <? } ?>
            <? if (!empty($상품['price_updated_fmt'])) { ?>
            <div class="product-price-updated">가격 갱신 <?= htmlspecialchars($상품['price_updated_fmt'], ENT_QUOTES, 'UTF-8') ?></div>
            <? } ?>
          </div>
          <button
            type="button"
            class="btn-buy"
            <?= (!$판매중 || $내상품) ? 'disabled' : '' ?>
            onclick="<?= $내상품 ? 'return false;' : 'openBuyModal(this.closest(\'.product-card\'))' ?>"
          ><?= $내상품 ? '내 상품' : ($판매중 ? '구매하기' : ($예약중 ? '예약중' : '판매완료')) ?></button>
          <? if ($매입가능) { ?>
          <div class="product-actions">
          <button
            type="button"
            class="btn-prebuy"
            onclick="quickPrebuy(this.closest('.product-card'))"
          >관리자 매입</button>
          </div>
          <? } ?>
        </div>
      </div>
    </article>
    <? } ?>
  </div>

</div>

<!-- 구매 수단 선택 -->
<div class="modal-overlay top-layer" id="quick-buy-modal" onclick="closeQuickBuyOnBg(event)">
  <div class="modal-box modal-box--buy">
    <div class="modal-buy-header">
      <h3>구매하기</h3>
    </div>
    <div class="modal-buy-body">
      <div class="quick-buy-text">
        <div class="name" id="quick-buy-name"></div>
        <div class="buy-price-lines" id="quick-buy-prices"></div>
        <div class="hint" id="quick-buy-hint">게임냥으로 결제합니다.</div>
      </div>
      <div class="buy-choice-actions">
        <button type="button" class="btn-buy-choice btn-buy-choice--game" id="btn-buy-point" onclick="confirmQuickBuy('point')">겜냥으로 구매하기</button>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn-cancel" onclick="closeQuickBuyModal()">취소</button>
      </div>
    </div>
  </div>
</div>

<!-- 매입 확인 (관리자) -->
<div class="modal-overlay top-layer" id="quick-prebuy-modal" onclick="closeQuickPrebuyOnBg(event)">
  <div class="modal-box">
    <h3 style="margin:0 0 4px;text-align:center">선매입 확인</h3>
    <div class="quick-buy-text">
      <div class="name" id="quick-prebuy-name"></div>
      <div class="deduct" id="quick-prebuy-pay"></div>
      <div class="hint" id="quick-prebuy-hint">판매자에게 본방냥이 선지급되고, 권면가 1만원당 은총 1개가 지급됩니다.<br>이후 실구매 시 구매자 게임냥은 소멸 처리됩니다.</div>
    </div>
    <div class="modal-actions">
      <button type="button" class="btn-cancel" onclick="closeQuickPrebuyModal()">취소</button>
      <button type="button" class="btn-confirm btn-confirm--prebuy" id="btn-quick-prebuy" onclick="confirmQuickPrebuy()">매입하기</button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<? if ($코드없음) { shop_코드_게이트_출력(); } ?>

<script>
  <?= shop_냥_표시_js() ?>

  const IS_LOGIN = <?= $로그인 ? 'true' : 'false' ?>;
  const IS_ADMIN = <?= $관리자 ? 'true' : 'false' ?>;
  const IS_PREBUY_ADMIN = <?= $선매입가능유저 ? 'true' : 'false' ?>;
  const HAS_CODE = <?= ($code !== '') ? 'true' : 'false' ?>;
  const MY_CODE = <?= json_encode($code, JSON_UNESCAPED_UNICODE) ?>;
  const CODE_REQUIRED_MSG = <?= json_encode($코드없음메시지, JSON_UNESCAPED_UNICODE) ?>;
  const MY_ENHANCE = <?= (int)$현재강화 ?>;
  const MIN_BUY_ENHANCE = <?= (int)$구매최소강화 ?>;
  const CAN_MARKET_BUY = <?= $마켓구매가능 ? 'true' : 'false' ?>;
  let MARKET_DISCOUNT_PCT = <?= (int)$마켓할인율 ?>;
  let MARKET_DISCOUNT_LABEL = <?= json_encode($마켓할인라벨, JSON_UNESCAPED_UNICODE) ?>;
  let MARKET_GEM_DISCOUNT_PCT = <?= (int)($마켓할인['gem_pct'] ?? 0) ?>;
  let MARKET_BAR_DISCOUNT_PCT = <?= (int)($마켓할인['bar_pct'] ?? 0) ?>;
  let MY_NYANG = <?= json_encode((string)shop_냥_값($게임냥), JSON_UNESCAPED_UNICODE) ?>;
  let MY_NEWPOINT = <?= (int)$본방냥 ?>;
  let currentCat = 'all';
  let showSoldOnly = false;
  let selectedProduct = null;
  let selectedPayType = 'point';
  let selectedPrebuy = null;
  const VIEW_MODE_KEY = 'shop_market_view_mode';
  let viewMode = 'grid';

  function initViewMode() {
    try {
      const saved = localStorage.getItem(VIEW_MODE_KEY);
      if (saved === 'list') setViewMode('list', false);
    } catch (e) {}
  }

  function toggleViewMode() {
    setViewMode(viewMode === 'grid' ? 'list' : 'grid', true);
  }

  function setViewMode(mode, save) {
    viewMode = mode === 'list' ? 'list' : 'grid';
    const grid = document.getElementById('product-grid');
    const label = document.getElementById('view-mode-label');
    const icon = document.getElementById('view-mode-icon');
    const btn = document.getElementById('view-mode-btn');
    const isList = viewMode === 'list';

    if (grid) grid.classList.toggle('view-list', isList);
    if (label) label.textContent = isList ? '카드로 보기' : '리스트로 보기';
    if (icon) icon.textContent = isList ? '▦' : '☰';
    if (btn) {
      btn.classList.toggle('is-list', isList);
      btn.setAttribute('aria-pressed', isList ? 'true' : 'false');
    }
    if (save) {
      try {
        localStorage.setItem(VIEW_MODE_KEY, viewMode);
      } catch (e) {}
    }
  }

  initViewMode();

  function requireShopCode() {
    if (!HAS_CODE) {
      showToast(CODE_REQUIRED_MSG);
      return false;
    }
    if (!IS_LOGIN) {
      showToast('인증코드가 올바르지 않습니다. 연구실에서 코드를 다시 발급받으세요.');
      return false;
    }
    return true;
  }

  function requireMarketBuyEnhance() {
    if (CAN_MARKET_BUY) return true;
    showToast('무기 +' + MIN_BUY_ENHANCE + ' 이상만 마켓 구매가 가능해요. (현재 +' + MY_ENHANCE + ')');
    return false;
  }

  function goRegister(e) {
    if (!requireShopCode()) {
      e.preventDefault();
      return false;
    }
    return true;
  }

  function setShowSold(checked) {
    showSoldOnly = !!checked;
    filterByCategory();
  }

  function setCategory(cat) {
    currentCat = cat;
    document.querySelectorAll('.cat-btn').forEach(btn => {
      btn.classList.toggle('active', btn.dataset.cat === cat);
    });
    filterByCategory();
  }

  function filterByCategory() {
    const cards = document.querySelectorAll('.product-card');
    let visible = 0;

    cards.forEach(card => {
      const status = card.dataset.status || '';
      const matchCat = currentCat === 'all' || card.dataset.category === currentCat;
      const matchSold = showSoldOnly ? (status === 'sold') : (status !== 'sold');
      const show = matchCat && matchSold;
      card.style.display = show ? '' : 'none';
      if (show) visible++;
    });

    let empty = document.getElementById('empty-state');
    if (visible === 0) {
      if (cards.length > 0) {
        if (!empty) {
          empty = document.createElement('div');
          empty.id = 'empty-state';
          empty.className = 'empty-state';
          document.getElementById('product-grid').appendChild(empty);
        }
        empty.innerHTML = showSoldOnly
          ? '<div class="emoji">✅</div>판매완료된 상품이 없어요'
          : '<div class="emoji">🎫</div>이 카테고리에 상품이 없어요';
      }
    } else if (empty && cards.length > 0) {
      empty.remove();
    }
  }

  filterByCategory();

  function applyMarketPrices(items) {
    if (!Array.isArray(items)) return;
    items.forEach(function (item) {
      const card = document.querySelector('.product-card[data-id="' + item.id + '"]');
      if (!card) return;
      const price = String(item.price || '0');
      const pricePay = String(item.price_pay || price);
      const discPct = Number(item.discount_pct != null ? item.discount_pct : MARKET_DISCOUNT_PCT) || 0;
      card.dataset.price = price;
      card.dataset.pricePay = pricePay;
      card.dataset.discountPct = String(discPct);
      if (item.market_tax != null) card.dataset.marketTax = String(item.market_tax);

      const priceBox = card.querySelector('.product-price');
      const gameRow = card.querySelector('.product-price-row--game');
      if (gameRow) {
        let listEl = gameRow.querySelector('.product-price-list');
        const amountEl = gameRow.querySelector('.product-price-amount');
        if (discPct > 0 && shopNyCompare(price, pricePay) > 0) {
          if (!listEl) {
            listEl = document.createElement('span');
            listEl.className = 'product-price-list';
            if (amountEl) gameRow.insertBefore(listEl, amountEl);
          }
          listEl.textContent = shopFormatNyangShort(price);
          listEl.setAttribute('title', shopFormatNyangShort(price) + '냥');
          listEl.style.display = '';
        } else if (listEl) {
          listEl.style.display = 'none';
        }
        if (amountEl) {
          amountEl.textContent = shopFormatNyangShort(pricePay);
          amountEl.setAttribute('title', shopFormatNyangShort(pricePay) + '냥');
        }
      }
      if (priceBox) {
        let discEl = priceBox.querySelector('.product-price-discount');
        if (discPct > 0) {
          if (!discEl) {
            discEl = document.createElement('div');
            discEl.className = 'product-price-discount';
            const firstRow = priceBox.querySelector('.product-price-row--game');
            if (firstRow && firstRow.nextSibling) {
              priceBox.insertBefore(discEl, firstRow.nextSibling);
            } else {
              priceBox.appendChild(discEl);
            }
          }
          const gemPct = Number(item.gem_discount_pct != null ? item.gem_discount_pct : MARKET_GEM_DISCOUNT_PCT) || 0;
          discEl.textContent = '마켓 −' + discPct + '%' + (gemPct > 0 ? ' · 겜냥미션' : '');
          discEl.style.display = '';
        } else if (discEl) {
          discEl.style.display = 'none';
        }
      }
      const thumb = card.querySelector('.product-thumb');
      if (thumb) {
        let badge = thumb.querySelector('.product-badge.discount');
        const status = card.dataset.status || '';
        if (discPct > 0 && status === 'sale') {
          if (!badge) {
            badge = document.createElement('span');
            badge.className = 'product-badge discount';
            thumb.appendChild(badge);
          }
          badge.textContent = '−' + discPct + '%';
          badge.style.display = '';
        } else if (badge) {
          badge.style.display = 'none';
        }
      }
    });
  }

  async function fetchLiveQuote(gifticonId, syncAll) {
    const fd = new FormData();
    fd.append('code', MY_CODE);
    fd.append('gifticon_id', String(gifticonId));
    if (syncAll) fd.append('sync', '1');
    const res = await fetch('/shop/_quote.php', { method: 'POST', body: fd });
    return await res.json();
  }

  function renderBuyModalPrices(quote) {
    if (!selectedProduct || !quote || !quote.ok) return;
    if (quote.self_buy || selectedProduct.mine === '1') {
      document.getElementById('quick-buy-prices').innerHTML = '<div class="buy-price-line" style="color:#c0392b;">내가 올린 상품은 다시 구매할 수 없습니다.</div>';
      const btnPoint = document.getElementById('btn-buy-point');
      btnPoint.disabled = true;
      btnPoint.textContent = '내 상품';
      return;
    }
    const listPrice = String(quote.list_nyang || '0');
    const gamePrice = String(quote.pay_nyang || listPrice);
    const discPct = Number(quote.discount_pct || 0) || 0;
    const discLabel = String(quote.discount_label || '');

    selectedProduct.price = listPrice;
    selectedProduct.pricePay = gamePrice;
    if (discPct > 0) {
      MARKET_DISCOUNT_PCT = discPct;
      MARKET_GEM_DISCOUNT_PCT = Number(quote.gem_discount_pct || 0) || 0;
      MARKET_BAR_DISCOUNT_PCT = Number(quote.bar_discount_pct || 0) || 0;
      if (discLabel) MARKET_DISCOUNT_LABEL = discLabel;
    }

    let priceHtml = '<div class="buy-price-line buy-price-line--game">겜냥 <strong title="' + shopFormatNyangShort(gamePrice) + '냥">' + shopFormatNyangShort(gamePrice) + '</strong> 냥';
    if (discPct > 0 && shopNyCompare(listPrice, gamePrice) > 0) {
      priceHtml += ' <span class="buy-price-was" title="' + shopFormatNyangShort(listPrice) + '냥">' + shopFormatNyangShort(listPrice) + '</span>';
      priceHtml += ' <span class="buy-price-off">−' + discPct + '%</span>';
    } else {
      priceHtml += ' <span style="font-size:0.8em;opacity:.65">· 실시간 시세</span>';
    }
    priceHtml += '</div>';
    if (discPct > 0) {
      priceHtml += '<div class="buy-price-discount-note">' + (discLabel || ('마켓 ' + discPct + '% 할인 적용')) + '</div>';
    }

    document.getElementById('quick-buy-prices').innerHTML = priceHtml;
    const btnPoint = document.getElementById('btn-buy-point');
    btnPoint.disabled = shopNyCompare(MY_NYANG, gamePrice) < 0;
    btnPoint.textContent = shopNyCompare(MY_NYANG, gamePrice) < 0
      ? '겜냥 부족 (' + shopFormatNyangShort(gamePrice) + ' 필요)'
      : '겜냥으로 구매하기';
  }

  async function openBuyModal(card) {
    if (!requireShopCode()) return;
    if (!requireMarketBuyEnhance()) return;
    if (card && card.dataset && card.dataset.mine === '1') {
      showToast('내가 올린 상품은 다시 구매할 수 없습니다.');
      return;
    }
    selectedProduct = Object.assign({}, card.dataset);
    selectedPayType = 'point';

    document.getElementById('quick-buy-name').textContent = selectedProduct.emoji + ' ' + selectedProduct.name;
    document.getElementById('quick-buy-prices').innerHTML = '<div class="buy-price-line">시세 확인 중…</div>';
    const hint = document.getElementById('quick-buy-hint');
    if (selectedProduct.prebuy === '1') {
      hint.innerHTML = '선매입 상품입니다. 결제한 겜냥은 <strong>소멸</strong> 처리됩니다.<br>구매 후 <strong>구매내역</strong>에서 기프티콘 이미지를 확인할 수 있어요.';
    } else {
      hint.innerHTML = '게임냥으로 결제하면 구매액의 <strong>30%</strong>가 판매자에게 즉시 환급되고, <strong>나머지 70%는 소멸</strong>됩니다.<br>구매 후 <strong>구매내역</strong>에서 기프티콘 이미지를 확인할 수 있어요.';
    }

    const btnPoint = document.getElementById('btn-buy-point');
    btnPoint.disabled = true;
    btnPoint.textContent = '시세 확인 중…';
    document.getElementById('quick-buy-modal').classList.add('open');

    try {
      const quote = await fetchLiveQuote(selectedProduct.id, true);
      if (!selectedProduct || String(selectedProduct.id) !== String(quote.gifticon_id || selectedProduct.id)) return;
      if (!quote.ok) {
        document.getElementById('quick-buy-prices').innerHTML = '<div class="buy-price-line" style="color:#c0392b;">' + (quote.msg || '시세 조회 실패') + '</div>';
        btnPoint.disabled = true;
        btnPoint.textContent = '시세 조회 실패';
        return;
      }
      if (quote.self_buy) {
        document.getElementById('quick-buy-prices').innerHTML = '<div class="buy-price-line" style="color:#c0392b;">내가 올린 상품은 다시 구매할 수 없습니다.</div>';
        btnPoint.disabled = true;
        btnPoint.textContent = '내 상품';
        return;
      }
      if (Array.isArray(quote.market_prices)) {
        applyMarketPrices(quote.market_prices);
      }
      renderBuyModalPrices(quote);
    } catch (err) {
      document.getElementById('quick-buy-prices').innerHTML = '<div class="buy-price-line" style="color:#c0392b;">시세 조회 중 오류</div>';
      btnPoint.disabled = true;
      btnPoint.textContent = '시세 조회 실패';
    }
  }

  function closeQuickBuyModal() {
    document.getElementById('quick-buy-modal').classList.remove('open');
    selectedProduct = null;
    selectedPayType = 'point';
    const btnPoint = document.getElementById('btn-buy-point');
    if (btnPoint) {
      btnPoint.disabled = false;
      btnPoint.textContent = '겜냥으로 구매하기';
    }
  }

  function closeQuickBuyOnBg(e) {
    if (e.target.id === 'quick-buy-modal') closeQuickBuyModal();
  }

  async function confirmQuickBuy(payType) {
    if (!selectedProduct) return;
    if (!requireMarketBuyEnhance()) return;
    if (selectedProduct.mine === '1') {
      showToast('내가 올린 상품은 다시 구매할 수 없습니다.');
      return;
    }
    selectedPayType = 'point';

    const btn = document.getElementById('btn-buy-point');
    btn.disabled = true;
    btn.textContent = '시세 확인 중…';

    let quote;
    try {
      quote = await fetchLiveQuote(selectedProduct.id, false);
    } catch (err) {
      btn.disabled = false;
      btn.textContent = '겜냥으로 구매하기';
      showToast('시세 조회에 실패했습니다. 다시 시도해주세요.');
      return;
    }
    if (!quote || !quote.ok) {
      btn.disabled = false;
      btn.textContent = '겜냥으로 구매하기';
      showToast((quote && quote.msg) || '시세 조회 실패');
      return;
    }
    if (quote.self_buy) {
      btn.disabled = true;
      btn.textContent = '내 상품';
      showToast('내가 올린 상품은 다시 구매할 수 없습니다.');
      return;
    }
    renderBuyModalPrices(quote);

    const price = String(quote.pay_nyang || '0');
    if (shopNyCompare(MY_NYANG, price) < 0) {
      btn.disabled = true;
      btn.textContent = '겜냥 부족 (' + shopFormatNyangShort(price) + ' 필요)';
      showToast('게임냥이 부족합니다. (필요: ' + shopFormatNyangShort(price) + '게임냥)');
      return;
    }

    btn.textContent = '처리 중…';

    const fd = new FormData();
    fd.append('code', MY_CODE);
    fd.append('gifticon_id', selectedProduct.id);
    fd.append('pay_type', 'point');

    try {
      const res = await fetch('/shop/_buy.php', { method: 'POST', body: fd });
      const data = await res.json();
      closeQuickBuyModal();
      if (data.ok) {
        if (data.point != null) MY_NYANG = String(data.point);
        const gameBadge = document.querySelector('.nyang-badge:not(.nyang-badge--np) span');
        if (gameBadge) gameBadge.textContent = shopFormatNyangShort(MY_NYANG);
        if (Array.isArray(data.market_prices)) {
          applyMarketPrices(data.market_prices);
        }
        const soldCard = document.querySelector('.product-card[data-id="' + data.gifticon_id + '"]');
        if (soldCard) {
          soldCard.dataset.status = 'sold';
          soldCard.classList.add('sold-out');
          const buyBtn = soldCard.querySelector('.btn-buy');
          if (buyBtn) {
            buyBtn.disabled = true;
            buyBtn.textContent = '판매완료';
          }
        }
        filterByCategory();
        showToast(data.msg || '구매 완료!');
      } else {
        showToast(data.msg || '구매 실패');
      }
    } catch (err) {
      closeQuickBuyModal();
      showToast('서버 오류가 발생했습니다.');
    }
  }

  function calcPrebuyPayout(card) {
    const fromSnapshot = Number(card.dataset.priceNp || 0);
    if (fromSnapshot > 0) {
      return fromSnapshot;
    }
    const faceValue = Number(card.dataset.faceValue || 0);
    const prebuyNpPer10000 = Number(card.dataset.prebuyNpPer10000 || 0);
    return Math.floor(faceValue / 10000 * prebuyNpPer10000);
  }

  function quickPrebuy(card) {
    if (!IS_PREBUY_ADMIN) {
      showToast('민호만 선매입할 수 있습니다.');
      return;
    }
    if (!requireShopCode()) return;
    if (card.dataset.prebuy === '1') {
      showToast('이미 매입된 상품입니다.');
      return;
    }
    selectedPrebuy = card.dataset;
    const faceValue = Number(selectedPrebuy.faceValue || 0);
    const payout = calcPrebuyPayout(card);
    let payText = '판매자 ' + selectedPrebuy.seller + '님 선지급 ' + payout.toLocaleString() + ' 본방냥';
    payText += ' (등록 시 선매입가 · 권면가 ' + faceValue.toLocaleString() + '원)';
    document.getElementById('quick-prebuy-name').textContent = selectedPrebuy.emoji + ' ' + selectedPrebuy.name;
    document.getElementById('quick-prebuy-pay').textContent = payText;
    const eunchong = Math.floor(faceValue / 10000);
    document.getElementById('quick-prebuy-hint').innerHTML =
      '판매자 <strong>' + selectedPrebuy.seller + '</strong>님에게 <strong>' + payout.toLocaleString() + ' 본방냥</strong> 선지급'
      + (eunchong > 0 ? ' · <strong>은총 ' + eunchong.toLocaleString() + '개</strong>' : '')
      + '<br>(판매등록 당시 금액 · 시세 변동 없음)<br>이후 실구매 시 구매자 게임냥은 소멸 처리됩니다.';
    const btn = document.getElementById('btn-quick-prebuy');
    btn.disabled = false;
    btn.textContent = '매입하기';
    document.getElementById('quick-prebuy-modal').classList.add('open');
  }

  function closeQuickPrebuyModal() {
    document.getElementById('quick-prebuy-modal').classList.remove('open');
    selectedPrebuy = null;
  }

  function closeQuickPrebuyOnBg(e) {
    if (e.target.id === 'quick-prebuy-modal') closeQuickPrebuyModal();
  }

  async function confirmQuickPrebuy() {
    if (!selectedPrebuy) return;
    if (!requireShopCode()) return;
    const btn = document.getElementById('btn-quick-prebuy');
    btn.disabled = true;
    btn.textContent = '처리 중...';

    const fd = new FormData();
    fd.append('code', MY_CODE);
    fd.append('gifticon_id', selectedPrebuy.id);

    try {
      const res = await fetch('/shop/_prebuy.php', { method: 'POST', body: fd });
      const data = await res.json();
      closeQuickPrebuyModal();
      if (data.ok) {
        showToast(data.msg || '매입 완료!');
        setTimeout(() => location.reload(), 1000);
      } else {
        showToast(data.msg || '매입 실패');
        btn.disabled = false;
        btn.textContent = '매입하기';
      }
    } catch (err) {
      closeQuickPrebuyModal();
      showToast('서버 오류가 발생했습니다.');
    }
  }

  function showToast(msg) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    clearTimeout(t._timer);
    t._timer = setTimeout(() => t.classList.remove('show'), 2800);
  }
</script>

<?php
if ($code !== '') {
  require_once dirname(__DIR__) . '/api/game/wallet_nav_fab.inc.php';
  wallet_nav_fab_render(['code' => $code]);
}
?>
</body>
</html>
