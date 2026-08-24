<?
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
include_once __DIR__ . '/_shop.php';

$code = shop_코드_해석();
$닉 = '';
$게임냥 = 0;
$본방냥 = 0;
$로그인 = false;

$회원 = shop_auth($code);
if ($회원) {
    $로그인 = true;
    $닉 = $회원['nick'];
    $게임냥 = $회원['point'];
    $본방냥 = (int)floor((float)($회원['newpoint'] ?? 0));
}
$관리자 = (bool)($로그인 ? shop_관리자_인증($code) : null);
$코드없음 = ($code === '');
$코드없음메시지 = shop_코드없음_메시지();

$상품목록 = shop_상품목록_조회();

$판매중수 = 0;
$총거래가 = 0;
foreach ($상품목록 as $p) {
    if ($p['status'] === 'sale') $판매중수++;
    $총거래가 += (int)$p['price'];
}

$카테고리 = ['all' => ['label' => '전체', 'icon' => '🎁']] + shop_카테고리목록();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>아영이네 마켓 · 냥으로 사자</title>
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
          <h1>아영이네 마켓</h1>
          <p>친구들의 기프티콘을 냥으로 거래해요</p>
        </div>
      </div>
      <div class="user-bar">
        <? if ($로그인) { ?>
          <div class="nyang-badge">🐱 <span title="<?= number_format($게임냥) ?>냥"><?= shop_냥_표시($게임냥) ?></span> 게임냥</div>
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
        💡 인증코드가 올바르지 않습니다. 상황실에서 코드를 다시 발급받으세요.
        <? } ?>
      </div>
    <? } else { ?>
      <div class="login-hint login-hint--success">
        ✅ <strong><?= htmlspecialchars($닉, ENT_QUOTES, 'UTF-8') ?></strong>님 환영해요! 기프티콘을 등록하거나 냥으로 구매할 수 있어요.
        <? if ($관리자) { ?>
        <span class="admin-mode-badge">👑 관리자 모드</span>
        <? } ?>
      </div>
    <? } ?>
  </header>

  <?= shop_nav_html($code, 'market') ?>

  <div class="stats-bar">
    <div class="stat-chip">
      <strong id="stat-total"><?= count($상품목록) ?></strong>
      전체 등록
    </div>
    <div class="stat-chip">
      <strong id="stat-sale"><?= $판매중수 ?></strong>
      구매 가능
    </div>
    <div class="stat-chip">
      <strong title="<?= number_format($총거래가) ?>냥"><?= shop_냥_표시($총거래가) ?></strong>
      총 거래가(냥)
    </div>
  </div>

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
        $매입가능 = $관리자 && $판매중 && (!$내상품 || shop_매입_본인상품_허용($닉)) && !$매입됨;
        $관리자수정가능 = $관리자 && $판매중 && !$내상품 && (!$매입됨 || shop_선매입_수정_허용($닉));
    ?>
    <article
      class="product-card<?= !$판매중 ? ' sold-out' : '' ?>"
      data-id="<?= $상품['id'] ?>"
      data-category="<?= $상품['category'] ?>"
      data-brand="<?= htmlspecialchars($상품['brand'], ENT_QUOTES, 'UTF-8') ?>"
      data-name="<?= htmlspecialchars($상품['name'], ENT_QUOTES, 'UTF-8') ?>"
      data-price="<?= $상품['price'] ?>"
      data-price-np="<?= (int)$상품['price_np'] ?>"
      data-face-value="<?= (int)$상품['face_value'] ?>"
      data-seller="<?= htmlspecialchars($상품['seller'], ENT_QUOTES, 'UTF-8') ?>"
      data-market-tax="<?= htmlspecialchars((string)$상품['market_tax'], ENT_QUOTES, 'UTF-8') ?>"
      data-prebuy-np-per-10000="<?= (int)$상품['prebuy_np_per_10000'] ?>"
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
          <span class="product-face"><?= number_format((int)$상품['face_value']) ?>원권 · 유효 ~<?= $상품['expire'] ?></span>
        </div>
        <div class="product-footer<?= $매입가능 ? ' product-footer--admin' : '' ?>">
          <div class="product-price">
            <div class="product-price-row product-price-row--game">
              <span class="product-price-label">겜냥</span>
              <span class="product-price-amount" title="<?= shop_냥_전체표시($상품['price']) ?>냥"><?= shop_냥_표시($상품['price']) ?></span><span class="product-price-unit">냥</span>
            </div>
            <div class="product-price-row product-price-row--np">
              <span class="product-price-label">선매입시 본냥</span>
              <span class="product-price-amount" title="<?= number_format((int)$상품['price_np']) ?>냥"><?= shop_매입_지급_표시((int)$상품['price_np']) ?></span><span class="product-price-unit">냥</span>
            </div>
            <? if (!empty($상품['price_updated_fmt'])) { ?>
            <div class="product-price-updated">가격 갱신 <?= htmlspecialchars($상품['price_updated_fmt'], ENT_QUOTES, 'UTF-8') ?></div>
            <? } ?>
          </div>
          <button
            type="button"
            class="btn-buy"
            <?= (!$판매중 || $내상품) ? 'disabled' : '' ?>
            onclick="openBuyModal(this.closest('.product-card'))"
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
      <div class="hint" id="quick-prebuy-hint">판매자에게 본방냥이 선지급됩니다.<br>이후 실구매 시 구매자 게임냥은 소멸 처리됩니다.</div>
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
  const HAS_CODE = <?= ($code !== '') ? 'true' : 'false' ?>;
  const MY_CODE = <?= json_encode($code, JSON_UNESCAPED_UNICODE) ?>;
  const CODE_REQUIRED_MSG = <?= json_encode($코드없음메시지, JSON_UNESCAPED_UNICODE) ?>;
  let MY_NYANG = '<?= shop_냥_값($게임냥) ?>';
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
      showToast('인증코드가 올바르지 않습니다. 상황실에서 코드를 다시 발급받으세요.');
      return false;
    }
    return true;
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

  function openBuyModal(card) {
    if (!requireShopCode()) return;
    if (card.dataset.mine === '1') {
      showToast('내가 등록한 상품은 구매할 수 없어요!');
      return;
    }
    selectedProduct = card.dataset;
    selectedPayType = 'point';

    document.getElementById('quick-buy-name').textContent = selectedProduct.emoji + ' ' + selectedProduct.name;
    const gamePrice = selectedProduct.price || '0';
    document.getElementById('quick-buy-prices').innerHTML =
      '<div class="buy-price-line buy-price-line--game">겜냥 <strong title="' + shopFormatNyangShort(gamePrice) + '냥">' + shopFormatNyangShort(gamePrice) + '</strong> 냥</div>';

    const hint = document.getElementById('quick-buy-hint');
    if (selectedProduct.prebuy === '1') {
      hint.innerHTML = '선매입 상품입니다. 결제한 겜냥은 <strong>소멸</strong> 처리됩니다.<br>구매 후 <strong>구매내역</strong>에서 기프티콘 이미지를 확인할 수 있어요.';
    } else {
      hint.innerHTML = '게임냥으로 결제하면 판매자에게 <strong>게임냥</strong> 정산됩니다.<br>구매 후 <strong>구매내역</strong>에서 기프티콘 이미지를 확인할 수 있어요.';
    }

    const btnPoint = document.getElementById('btn-buy-point');
    btnPoint.disabled = shopNyCompare(MY_NYANG, gamePrice) < 0;
    btnPoint.textContent = shopNyCompare(MY_NYANG, gamePrice) < 0
      ? '겜냥 부족 (' + shopFormatNyangShort(gamePrice) + ' 필요)'
      : '겜냥으로 구매하기';

    document.getElementById('quick-buy-modal').classList.add('open');
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
    selectedPayType = 'point';

    const price = selectedProduct.price || '0';

    if (shopNyCompare(MY_NYANG, price) < 0) {
      showToast('게임냥이 부족합니다. (필요: ' + shopFormatNyangShort(price) + '게임냥)');
      return;
    }

    const btn = document.getElementById('btn-buy-point');
    btn.disabled = true;
    btn.textContent = '처리 중...';

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
        showToast(data.msg || '구매 완료!');
        setTimeout(() => location.reload(), 1000);
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
    if (!IS_ADMIN) {
      showToast('관리자만 매입할 수 있습니다.');
      return;
    }
    if (!requireShopCode()) return;
    if (card.dataset.prebuy === '1') {
      showToast('이미 매입된 상품입니다.');
      return;
    }
    selectedPrebuy = card.dataset;
    const faceValue = Number(selectedPrebuy.faceValue || 0);
    const marketTax = selectedPrebuy.marketTax || '<?= SHOP_매입_기본_MARKET_TAX ?>';
    const payout = calcPrebuyPayout(card);
    let payText = '판매자 ' + selectedPrebuy.seller + '님 선지급 ' + payout.toLocaleString() + ' 본방냥';
    payText += ' (권면가 ' + faceValue.toLocaleString() + '원 · ' + marketTax + '%/만원)';
    document.getElementById('quick-prebuy-name').textContent = selectedPrebuy.emoji + ' ' + selectedPrebuy.name;
    document.getElementById('quick-prebuy-pay').textContent = payText;
    document.getElementById('quick-prebuy-hint').innerHTML =
      '판매자 <strong>' + selectedPrebuy.seller + '</strong>님에게 <strong>' + payout.toLocaleString() + ' 본방냥</strong> 선지급<br>이후 실구매 시 구매자 게임냥은 소멸 처리됩니다.';
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

</body>
</html>
