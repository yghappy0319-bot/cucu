<?
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
include_once __DIR__ . '/_shop.php';

$code = shop_코드_해석();
$코드없음 = ($code === '');
$코드없음메시지 = shop_코드없음_메시지();
$tab = isset($_GET['tab']) ? trim($_GET['tab']) : 'buy';
$전체판매_열람 = shop_전체판매_열람가능($code);
$허용탭 = ['buy', 'sell'];
if ($전체판매_열람) {
    $허용탭[] = 'friends';
}
if (!in_array($tab, $허용탭, true)) {
    $tab = 'buy';
}

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

$구매목록 = $로그인 ? shop_구매목록_조회($닉) : [];
$구매통계 = $로그인 ? shop_구매통계($닉) : ['count' => 0, 'total_won' => 0, 'total_nyang' => 0];
$판매목록 = $로그인 ? shop_판매목록_조회($닉) : [];
$정산대기_겜 = $로그인 ? shop_정산대기_합계($닉, 'point') : 0;
$정산대기_본 = $로그인 ? shop_정산대기_합계($닉, 'newpoint') : 0;
$정산건수_겜 = $로그인 ? shop_정산대기_건수($닉, 'point') : 0;
$정산건수_본 = $로그인 ? shop_정산대기_건수($닉, 'newpoint') : 0;
$정산실지급_겜 = shop_정산_실지급액($정산대기_겜);
$정산소멸_겜 = shop_정산_소멸액($정산대기_겜);
$정산실지급_본 = shop_정산_실지급액($정산대기_본);
$정산소멸_본 = shop_정산_소멸액($정산대기_본);
$판매통계 = $로그인 ? shop_판매통계($닉) : ['count' => 0, 'total_won' => 0, 'total_nyang' => 0, 'settled_nyang' => 0];
$전체판매목록 = ($로그인 && $전체판매_열람) ? shop_전체판매목록_조회() : [];
$전체판매통계 = ($로그인 && $전체판매_열람) ? shop_전체판매통계() : ['count' => 0, 'total_won' => 0, 'total_nyang' => 0, 'seller_cnt' => 0];
$전체판매자목록 = [];
if (!empty($전체판매목록)) {
    $판매자집합 = [];
    foreach ($전체판매목록 as $행) {
        $판매자 = trim((string)($행['seller'] ?? ''));
        if ($판매자 !== '') {
            $판매자집합[$판매자] = true;
        }
    }
    $전체판매자목록 = array_keys($판매자집합);
    sort($전체판매자목록, SORT_STRING);
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>아영이네 마켓 · 내역</title>
  <link rel="stylesheet" href="/css/style.css">
  <?= shop_stylesheet_tag() ?>
</head>
<body>
<div class="shop-wrap">

  <header class="shop-header">
    <h1>📋 구매 · 판매 내역</h1>
    <? if ($로그인) { ?>
      <div class="nyang-badge">🐱 <span title="<?= number_format($게임냥) ?>냥"><?= shop_냥_표시($게임냥) ?></span> 게임냥</div>
      <div class="nyang-badge nyang-badge--np">💜 <span title="<?= number_format($본방냥) ?>냥"><?= shop_매입_지급_표시($본방냥) ?></span> 본방냥</div>
    <? } else { ?>
      <div class="login-hint">
        <? if ($코드없음) { ?>
        💡 <?= htmlspecialchars($코드없음메시지, ENT_QUOTES, 'UTF-8') ?>
        <? } else { ?>
        💡 인증코드가 올바르지 않습니다. 상황실에서 코드를 다시 발급받으세요.
        <? } ?>
      </div>
    <? } ?>
  </header>

  <?= shop_nav_html($code, $tab === 'sell' ? 'sell' : ($tab === 'friends' ? 'sell' : 'buy')) ?>

  <? if ($로그인) { ?>

  <div class="history-tabs<?= $전체판매_열람 ? ' history-tabs--3' : '' ?>">
    <button type="button" class="<?= $tab === 'buy' ? 'active' : '' ?>" onclick="goTab('buy')">🛍️ 구매내역</button>
    <button type="button" class="<?= $tab === 'sell' ? 'active' : '' ?>" onclick="goTab('sell')">💰 판매내역</button>
    <? if ($전체판매_열람) { ?>
    <button type="button" class="<?= $tab === 'friends' ? 'active' : '' ?>" onclick="goTab('friends')">👥 친구 전체</button>
    <? } ?>
  </div>

  <div class="panel <?= $tab === 'buy' ? 'active' : '' ?>" id="panel-buy">
    <div class="sell-stats">
      <div class="sell-stat">
        <strong><?= number_format($구매통계['count']) ?>건</strong>
        <span>총 구매</span>
      </div>
      <div class="sell-stat">
        <strong><?= number_format($구매통계['total_won']) ?>원</strong>
        <span>구매금액 합계</span>
      </div>
      <div class="sell-stat">
        <strong title="<?= number_format($구매통계['total_nyang']) ?>냥"><?= shop_냥_표시($구매통계['total_nyang']) ?></strong>
        <span>구매냥 합계</span>
      </div>
    </div>

    <? if (empty($구매목록)) { ?>
      <div class="empty-state"><div class="emoji">🛍️</div>구매 내역이 없어요</div>
    <? } else { ?>
      <div class="history-list">
        <? foreach ($구매목록 as $item) { ?>
        <div class="history-item history-item--buy" id="buy-item-<?= (int)$item['id'] ?>">
          <div>
            <div class="title"><?= $item['emoji'] ?> <?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?></div>
            <div class="sub">
              <?= number_format($item['face_value']) ?>원권 ·
              <? if (($item['buy_currency'] ?? 'point') === 'newpoint') { ?>
              <span title="<?= number_format((int)$item['price_np']) ?>냥">본냥 <?= shop_매입_지급_표시((int)$item['price_np']) ?>냥</span>
              <? } else { ?>
              <span title="<?= shop_냥_전체표시($item['price']) ?>냥">겜냥 <?= shop_냥_표시($item['price']) ?>냥</span>
              <? } ?><br>
              판매자 <?= htmlspecialchars($item['seller'], ENT_QUOTES, 'UTF-8') ?>
              <? if ($item['sold_at']) { ?> · <?= htmlspecialchars(substr($item['sold_at'], 0, 16), ENT_QUOTES, 'UTF-8') ?><? } ?><br>
              <? if (!empty($item['used'])) { ?>
                <span class="badge-used">사용완료<?= !empty($item['used_at']) ? ' · ' . htmlspecialchars(substr($item['used_at'], 0, 16), ENT_QUOTES, 'UTF-8') : '' ?></span>
              <? } else { ?>
                <span class="badge-sale">미사용</span>
              <? } ?>
            </div>
            <div class="gift-code-box<?= trim((string)$item['code_text']) !== '' ? ' is-ready' : '' ?>" id="gift-code-box-<?= (int)$item['id'] ?>">
              <div class="gift-code-label">추출된 번호</div>
              <div class="gift-code-value" id="gift-code-value-<?= (int)$item['id'] ?>"><?= trim((string)$item['code_text']) !== '' ? htmlspecialchars($item['code_text'], ENT_QUOTES, 'UTF-8') : '자동 추출 대기 중' ?></div>
            </div>
            <? if ($item['has_image']) { ?>
            <button
              type="button"
              class="btn-retry-detect"
              onclick="retryDetectGiftCode(<?= (int)$item['id'] ?>)"
            >번호 재추출</button>
            <? } ?>
          </div>
          <? if ($item['has_image']) { ?>
          <div class="history-item-actions history-item-actions--buy">
            <button
              type="button"
              class="btn-copy-code<?= trim((string)$item['code_text']) !== '' ? '' : ' is-hidden' ?>"
              id="btn-copy-code-<?= (int)$item['id'] ?>"
              data-code="<?= htmlspecialchars(trim((string)$item['code_text']), ENT_QUOTES, 'UTF-8') ?>"
              onclick="copyGiftCodeFromButton(this)"
            >번호 복사</button>
            <button
              type="button"
              class="btn-view-image"
              data-id="<?= (int)$item['id'] ?>"
              data-used="<?= !empty($item['used']) ? '1' : '0' ?>"
              data-code-text="<?= htmlspecialchars(trim((string)$item['code_text']), ENT_QUOTES, 'UTF-8') ?>"
              onclick="viewGiftImage(this)"
            >이미지 보기</button>
          </div>
          <? } ?>
        </div>
        <? } ?>
      </div>
    <? } ?>
  </div>

  <div class="panel <?= $tab === 'sell' ? 'active' : '' ?>" id="panel-sell">
    <div class="sell-stats">
      <div class="sell-stat">
        <strong><?= number_format($판매통계['count']) ?>건</strong>
        <span>총 판매</span>
      </div>
      <div class="sell-stat">
        <strong><?= number_format($판매통계['total_won']) ?>원</strong>
        <span>권면가 합계</span>
      </div>
      <div class="sell-stat">
        <strong title="<?= number_format($판매통계['total_nyang']) ?>냥"><?= shop_냥_표시($판매통계['total_nyang']) ?></strong>
        <span>판매 냥 합계</span>
      </div>
    </div>

    <div class="settle-grid">
      <div class="settle-box settle-box--game">
        <div class="label">게임냥 정산 대기</div>
        <div class="amount" id="pending-amount-point" title="<?= number_format($정산대기_겜) ?> 게임냥"><?= shop_냥_표시($정산대기_겜) ?> 게임냥</div>
        <div class="payout">
          실 수령 예정: <strong title="<?= number_format($정산실지급_겜) ?> 게임냥"><?= shop_냥_표시($정산실지급_겜) ?> 게임냥</strong>
          <? if ($정산소멸_겜 > 0) { ?>
          <span style="color:var(--muted)" title="<?= number_format($정산소멸_겜) ?>냥">(10% 수수료 <?= shop_냥_표시($정산소멸_겜) ?>냥 소멸)</span>
          <? } ?>
        </div>
        <div class="hint"><?= $정산건수_겜 ?>건 · 겜냥 구매 건 정산</div>
        <button type="button" class="btn-settle btn-settle--game" id="btn-settle-point" onclick="doSettle('point')" <?= $정산대기_겜 < 1 ? 'disabled' : '' ?>>💰 게임냥 정산 받기</button>
      </div>

      <div class="settle-box settle-box--np">
        <div class="label">본방냥 정산 대기</div>
        <div class="amount" id="pending-amount-newpoint" title="<?= number_format($정산대기_본) ?> 본방냥"><?= shop_매입_지급_표시($정산대기_본) ?> 본방냥</div>
        <div class="payout">
          실 수령 예정: <strong title="<?= number_format($정산실지급_본) ?> 본방냥"><?= shop_매입_지급_표시($정산실지급_본) ?> 본방냥</strong>
          <? if ($정산소멸_본 > 0) { ?>
          <span style="color:var(--muted)" title="<?= number_format($정산소멸_본) ?>냥">(10% 수수료 <?= shop_매입_지급_표시($정산소멸_본) ?>냥 소멸)</span>
          <? } ?>
        </div>
        <div class="hint"><?= $정산건수_본 ?>건 · 본냥 구매 건 정산</div>
        <button type="button" class="btn-settle btn-settle--np" id="btn-settle-newpoint" onclick="doSettle('newpoint')" <?= $정산대기_본 < 1 ? 'disabled' : '' ?>>💜 본방냥 정산 받기</button>
      </div>
    </div>

    <? if (empty($판매목록)) { ?>
      <div class="empty-state"><div class="emoji">💰</div>등록·판매 내역이 없어요</div>
    <? } else { ?>
      <div class="history-list">
        <? foreach ($판매목록 as $item) {
            $판매중 = ($item['status'] === 'sale');
            $예약중 = ($item['status'] === 'reserved');
        ?>
        <div class="history-item">
          <div>
            <div class="title"><?= $item['emoji'] ?> <?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?></div>
            <div class="sub">
              <?= number_format($item['face_value']) ?>원권 ·
              <? if (($item['buy_currency'] ?? 'point') === 'newpoint') { ?>
              <span title="<?= number_format((int)$item['price_np']) ?>냥">본냥 <?= shop_매입_지급_표시((int)$item['price_np']) ?>냥</span>
              <? } else { ?>
              <span title="<?= shop_냥_전체표시($item['price']) ?>냥">겜냥 <?= shop_냥_표시($item['price']) ?>냥</span>
              <? } ?>
              · 유효 ~<?= htmlspecialchars($item['expire'], ENT_QUOTES, 'UTF-8') ?><br>
              <? if ($판매중) { ?>
                등록 <?= $item['regdate'] ? htmlspecialchars(substr($item['regdate'], 0, 16), ENT_QUOTES, 'UTF-8') : '' ?><br>
                <? if (!empty($item['prebuy'])) { ?>
                <span class="badge-prebuy">매입됨·판매중</span>
                <? } else { ?>
                <span class="badge-sale">판매중</span>
                <? } ?>
              <? } elseif ($예약중) { ?>
                등록 <?= $item['regdate'] ? htmlspecialchars(substr($item['regdate'], 0, 16), ENT_QUOTES, 'UTF-8') : '' ?><br>
                <span class="badge-reserved">예약중</span>
              <? } else { ?>
                구매자 <?= htmlspecialchars($item['buyer'], ENT_QUOTES, 'UTF-8') ?>
                <? if ($item['sold_at']) { ?> · <?= htmlspecialchars(substr($item['sold_at'], 0, 16), ENT_QUOTES, 'UTF-8') ?><? } ?><br>
                <? if ($item['settled']) { ?>
                  <span class="badge-settled">정산완료</span>
                <? } elseif (!empty($item['prebuy'])) { ?>
                  <span class="badge-prebuy">선매입·정산완료</span>
                <? } elseif (($item['buy_currency'] ?? 'point') === 'newpoint') { ?>
                  <span class="badge-pending badge-pending--np">본냥 정산대기</span>
                <? } else { ?>
                  <span class="badge-pending">겜냥 정산대기</span>
                <? } ?>
              <? } ?>
            </div>
          </div>
          <? if ($판매중) { ?>
          <div class="history-item-actions">
            <? if (empty($item['prebuy'])) { ?>
            <a href="<?= htmlspecialchars(shop_edit_url($code, $item['id']), ENT_QUOTES, 'UTF-8') ?>" class="btn-edit">수정</a>
            <? } ?>
            <button
              type="button"
              class="btn-delete"
              data-id="<?= (int)$item['id'] ?>"
              data-name="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>"
              onclick="openDeleteModal(this)"
            >삭제</button>
          </div>
          <? } ?>
        </div>
        <? } ?>
      </div>
    <? } ?>
  </div>

  <? if ($전체판매_열람) { ?>
  <div class="panel <?= $tab === 'friends' ? 'active' : '' ?>" id="panel-friends">
    <div class="sell-stats">
      <div class="sell-stat">
        <strong><?= number_format($전체판매통계['count']) ?>건</strong>
        <span>전체 등록·판매</span>
      </div>
      <div class="sell-stat">
        <strong><?= number_format($전체판매통계['seller_cnt']) ?>명</strong>
        <span>판매자 수</span>
      </div>
      <div class="sell-stat">
        <strong title="<?= number_format($전체판매통계['total_nyang']) ?>냥"><?= shop_냥_표시($전체판매통계['total_nyang']) ?></strong>
        <span>판매완료 냥 합계</span>
      </div>
    </div>

    <? if (empty($전체판매목록)) { ?>
      <div class="empty-state"><div class="emoji">👥</div>친구들의 판매 내역이 없어요</div>
    <? } else { ?>
      <div class="friends-filter">
        <div class="friends-filter-row">
          <label class="friends-filter-label" for="friends-seller-search">등록자 검색</label>
          <div class="friends-filter-controls">
            <input
              type="search"
              id="friends-seller-search"
              class="friends-filter-input"
              placeholder="닉네임 입력"
              list="friends-seller-list"
              autocomplete="off"
              oninput="filterFriendsBySeller()"
            >
            <datalist id="friends-seller-list">
              <? foreach ($전체판매자목록 as $판매자) { ?>
              <option value="<?= htmlspecialchars($판매자, ENT_QUOTES, 'UTF-8') ?>"></option>
              <? } ?>
            </datalist>
            <select id="friends-seller-select" class="friends-filter-select" onchange="onFriendsSellerSelect(this)" aria-label="등록자 선택">
              <option value="">전체</option>
              <? foreach ($전체판매자목록 as $판매자) { ?>
              <option value="<?= htmlspecialchars($판매자, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($판매자, ENT_QUOTES, 'UTF-8') ?></option>
              <? } ?>
            </select>
            <button type="button" class="friends-filter-clear" id="friends-seller-clear" onclick="clearFriendsSellerFilter()" hidden>초기화</button>
          </div>
        </div>
        <div class="friends-filter-count" id="friends-filter-count" aria-live="polite"></div>
      </div>
      <div class="history-list" id="friends-history-list">
        <? foreach ($전체판매목록 as $item) {
            $판매중 = ($item['status'] === 'sale');
            $예약중 = ($item['status'] === 'reserved');
            $코드있음 = trim((string)($item['code_text'] ?? '')) !== '';
            $이미지있음 = !empty($item['has_image']);
        ?>
        <div
          class="history-item history-item--friends"
          data-id="<?= (int)$item['id'] ?>"
          data-seller="<?= htmlspecialchars($item['seller'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
          data-has-image="<?= $이미지있음 ? '1' : '0' ?>"
        >
          <div>
            <div class="title"><?= $item['emoji'] ?> <?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?></div>
            <div class="sub">
              판매자 <strong><?= htmlspecialchars($item['seller'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong><br>
              <?= number_format($item['face_value']) ?>원권 ·
              <? if (($item['buy_currency'] ?? 'point') === 'newpoint') { ?>
              <span title="<?= number_format((int)$item['price_np']) ?>냥">본냥 <?= shop_매입_지급_표시((int)$item['price_np']) ?>냥</span>
              <? } else { ?>
              <span title="<?= shop_냥_전체표시($item['price']) ?>냥">겜냥 <?= shop_냥_표시($item['price']) ?>냥</span>
              <? } ?>
              · 유효 ~<?= htmlspecialchars($item['expire'], ENT_QUOTES, 'UTF-8') ?><br>
              <? if ($판매중) { ?>
                등록 <?= $item['regdate'] ? htmlspecialchars(substr($item['regdate'], 0, 16), ENT_QUOTES, 'UTF-8') : '' ?><br>
                <? if (!empty($item['prebuy'])) { ?>
                <span class="badge-prebuy">매입됨·판매중</span>
                <? } else { ?>
                <span class="badge-sale">판매중</span>
                <? } ?>
              <? } elseif ($예약중) { ?>
                등록 <?= $item['regdate'] ? htmlspecialchars(substr($item['regdate'], 0, 16), ENT_QUOTES, 'UTF-8') : '' ?><br>
                <span class="badge-reserved">예약중</span>
              <? } else { ?>
                구매자 <?= htmlspecialchars($item['buyer'], ENT_QUOTES, 'UTF-8') ?>
                <? if ($item['sold_at']) { ?> · <?= htmlspecialchars(substr($item['sold_at'], 0, 16), ENT_QUOTES, 'UTF-8') ?><? } ?><br>
                <? if ($item['settled']) { ?>
                  <span class="badge-settled">정산완료</span>
                <? } elseif (!empty($item['prebuy'])) { ?>
                  <span class="badge-prebuy">선매입·정산완료</span>
                <? } elseif (($item['buy_currency'] ?? 'point') === 'newpoint') { ?>
                  <span class="badge-pending badge-pending--np">본냥 정산대기</span>
                <? } else { ?>
                  <span class="badge-pending">겜냥 정산대기</span>
                <? } ?>
              <? } ?>
            </div>
            <? if ($이미지있음 || $코드있음) { ?>
            <div class="gift-code-box<?= $코드있음 ? ' is-ready' : '' ?>" id="gift-code-box-friend-<?= (int)$item['id'] ?>">
              <div class="gift-code-label">추출된 번호</div>
              <div class="gift-code-value" id="gift-code-value-friend-<?= (int)$item['id'] ?>"><?= $코드있음 ? htmlspecialchars($item['code_text'], ENT_QUOTES, 'UTF-8') : '자동 추출 대기 중' ?></div>
            </div>
            <button
              type="button"
              class="btn-copy-code btn-copy-code--friends<?= $코드있음 ? '' : ' is-hidden' ?>"
              id="btn-copy-code-friend-<?= (int)$item['id'] ?>"
              data-code="<?= htmlspecialchars($item['code_text'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
              onclick="copyGiftCodeFromButton(this)"
            >번호 복사</button>
            <? if ($이미지있음) { ?>
            <button
              type="button"
              class="btn-retry-detect btn-retry-detect--friends"
              onclick="retryDetectGiftCode(<?= (int)$item['id'] ?>)"
            >번호 재추출</button>
            <? } ?>
            <? } ?>
          </div>
          <? if ($이미지있음) { ?>
          <button
            type="button"
            class="history-thumb-btn"
            onclick="viewFriendGiftImage(<?= (int)$item['id'] ?>)"
            aria-label="기프티콘 이미지 보기"
          >
            <img
              class="history-thumb-img"
              src="/shop/_image.php?id=<?= (int)$item['id'] ?>&code=<?= rawurlencode($code) ?>"
              alt="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>"
              loading="lazy"
            >
          </button>
          <? } ?>
        </div>
        <? } ?>
      </div>
      <div class="empty-state friends-filter-empty" id="friends-filter-empty" hidden>
        <div class="emoji">🔍</div>해당 등록자의 판매 내역이 없어요
      </div>
    <? } ?>
  </div>
  <? } ?>

  <? } ?>

</div>

<div class="modal-overlay" id="gift-image-modal" onclick="if(event.target.id==='gift-image-modal')closeGiftImageModal()">
  <div class="modal-box modal-box--image">
    <h3 style="margin:0 0 8px">🎫 기프티콘</h3>
    <p id="gift-modal-subtitle" style="margin:0;font-size:0.85rem;color:#888">구매자 본인만 볼 수 있는 이미지입니다</p>
    <div class="gift-modal-buyer-only">
      <div class="gift-image-status" id="gift-image-status"></div>
      <div class="gift-code-box gift-code-box--modal" id="gift-modal-code-box">
        <div class="gift-code-label">추출된 번호</div>
        <div class="gift-code-value" id="gift-modal-code-value">자동 추출 대기 중</div>
      </div>
    </div>
    <div class="gift-image-scroll">
      <img id="gift-image-view" src="" alt="기프티콘">
    </div>
    <div class="modal-actions">
      <button type="button" class="btn-copy-code gift-modal-buyer-only" id="btn-copy-modal-code" onclick="copyCurrentGiftCode()">번호 복사</button>
      <button type="button" class="btn-retry-detect" id="btn-retry-detect" onclick="retryCurrentGiftCode()">번호 재추출</button>
      <a href="#" class="btn-download-image" id="btn-download-image" download>다운로드</a>
      <button type="button" class="btn-confirm btn-use-complete gift-modal-buyer-only" id="btn-use-complete" onclick="markGiftUsed()">사용완료</button>
      <button type="button" class="btn-cancel" onclick="closeGiftImageModal()">닫기</button>
    </div>
  </div>
</div>

<div class="modal-overlay top-layer" id="delete-modal" onclick="closeDeleteModalOnBg(event)">
  <div class="modal-box">
    <h3>상품 삭제</h3>
    <div class="quick-buy-text">
      <div class="name" id="delete-item-name"></div>
      <div class="hint">마켓에서 내리면 복구할 수 없어요.<br>정말 삭제할까요?</div>
    </div>
    <div class="modal-actions">
      <button type="button" class="btn-cancel" onclick="closeDeleteModal()">취소</button>
      <button type="button" class="btn-confirm btn-delete-confirm" id="btn-confirm-delete" onclick="confirmDeleteSaleItem()">삭제하기</button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<? if ($코드없음) { shop_코드_게이트_출력(); } ?>

<script src="https://cdn.jsdelivr.net/npm/@zxing/browser@0.1.5/umd/index.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>
<script>
  const MY_CODE = <?= json_encode($code, JSON_UNESCAPED_UNICODE) ?>;
  const FRIENDS_TAB_ENABLED = <?= $전체판매_열람 ? 'true' : 'false' ?>;
  let pendingDeleteId = 0;
  let currentGiftImageId = 0;
  let currentGiftFriendsMode = false;
  let currentGiftUsed = false;
  let currentGiftCodeText = '';
  let barcodeDetectorInstance = null;
  let barcodeDetectorReady = false;
  let zxingReader = null;
  let tesseractWorker = null;
  const detectedGiftIds = new Set();
  const barcodeRectCache = new Map();
  const OCR_CONFIDENT_SCORE = 32;
  const OCR_PASSES_NORMAL = [
    { mode: 'adaptive', threshold: 175, psm: '7' },
    { mode: 'binary', threshold: 175, psm: '7' },
  ];
  const OCR_PASSES_EXTENDED = [
    { mode: 'adaptive', threshold: 175, psm: '7' },
    { mode: 'binary', threshold: 165, psm: '7' },
    { mode: 'binary', threshold: 175, psm: '8' },
    { mode: 'contrast', threshold: 175, psm: '7' },
  ];

  function goTab(tab) {
    const q = MY_CODE ? '?code=' + encodeURIComponent(MY_CODE) + '&tab=' + tab : '?tab=' + tab;
    location.href = '/shop/history.php' + q;
  }

  function filterFriendsBySeller() {
    const input = document.getElementById('friends-seller-search');
    const select = document.getElementById('friends-seller-select');
    const clearBtn = document.getElementById('friends-seller-clear');
    const countEl = document.getElementById('friends-filter-count');
    const emptyEl = document.getElementById('friends-filter-empty');
    const listEl = document.getElementById('friends-history-list');
    if (!input) return;

    const q = (input.value || '').trim().toLowerCase();
    if (select && select.value !== input.value.trim()) {
      select.value = '';
      for (const opt of select.options) {
        if (opt.value === input.value.trim()) {
          select.value = opt.value;
          break;
        }
      }
    }

    const items = document.querySelectorAll('#panel-friends .history-item--friends');
    let visible = 0;
    items.forEach(el => {
      const seller = (el.dataset.seller || '').toLowerCase();
      const show = !q || seller.includes(q);
      el.style.display = show ? '' : 'none';
      if (show) visible++;
    });

    if (clearBtn) clearBtn.hidden = !q;
    if (countEl) {
      countEl.textContent = q
        ? visible.toLocaleString() + '건 표시 중'
        : '';
    }
    if (emptyEl && listEl) {
      const showEmpty = q && visible === 0;
      emptyEl.hidden = !showEmpty;
      listEl.hidden = showEmpty;
    }
  }

  function onFriendsSellerSelect(sel) {
    const input = document.getElementById('friends-seller-search');
    if (input) input.value = sel.value;
    filterFriendsBySeller();
  }

  function clearFriendsSellerFilter() {
    const input = document.getElementById('friends-seller-search');
    const select = document.getElementById('friends-seller-select');
    if (input) input.value = '';
    if (select) select.value = '';
    filterFriendsBySeller();
    if (input) input.focus();
  }

  function viewGiftImage(btn) {
    if (!MY_CODE) { showToast('인증코드가 필요합니다.'); return; }
    const id = parseInt(btn.dataset.id, 10) || 0;
    if (id < 1) { showToast('이미지를 찾을 수 없습니다.'); return; }
    currentGiftFriendsMode = false;
    currentGiftImageId = id;
    currentGiftUsed = btn.dataset.used === '1';
    currentGiftCodeText = btn.dataset.codeText || '';
    document.getElementById('gift-image-view').src = getGiftImageUrl(id);
    document.getElementById('btn-download-image').href = getGiftImageUrl(id, true);
    setGiftModalFriendsMode(false);
    syncGiftImageModalState();
    document.getElementById('gift-image-modal').classList.add('open');
    if (currentGiftCodeText) updateGiftCodeUI(id, currentGiftCodeText);
    else autoDetectGiftCode(id, { silent: false });
  }

  function viewFriendGiftImage(id) {
    if (!MY_CODE || !FRIENDS_TAB_ENABLED) {
      showToast('이미지를 볼 수 있는 권한이 없습니다.');
      return;
    }
    id = parseInt(id, 10) || 0;
    if (id < 1) {
      showToast('이미지를 찾을 수 없습니다.');
      return;
    }
    currentGiftFriendsMode = true;
    currentGiftImageId = id;
    currentGiftUsed = false;
    currentGiftCodeText = '';
    document.getElementById('gift-image-view').src = getGiftImageUrl(id);
    document.getElementById('btn-download-image').href = getGiftImageUrl(id, true);
    setGiftModalFriendsMode(true);
    document.getElementById('gift-image-modal').classList.add('open');
    const valueEl = document.getElementById('gift-code-value-friend-' + id);
    const existing = valueEl ? valueEl.textContent.trim() : '';
    if (existing && existing !== '자동 추출 대기 중') {
      currentGiftCodeText = existing;
    } else {
      autoDetectGiftCode(id, { silent: true });
    }
  }

  function setGiftModalFriendsMode(friends) {
    const modal = document.getElementById('gift-image-modal');
    modal.classList.toggle('is-friends-view', friends);
    modal.classList.remove('is-image-zoomed');
    const subtitle = document.getElementById('gift-modal-subtitle');
    if (subtitle) {
      subtitle.textContent = friends
        ? '이미지를 눌러 크게 볼 수 있습니다'
        : '구매자 본인만 볼 수 있는 이미지입니다';
    }
  }

  function closeGiftImageModal() {
    document.getElementById('gift-image-modal').classList.remove('open');
    document.getElementById('gift-image-view').src = '';
    document.getElementById('btn-download-image').href = '#';
    currentGiftImageId = 0;
    currentGiftFriendsMode = false;
    currentGiftUsed = false;
    currentGiftCodeText = '';
    setGiftModalFriendsMode(false);
  }

  function syncGiftImageModalState() {
    const status = document.getElementById('gift-image-status');
    const btn = document.getElementById('btn-use-complete');
    const copyBtn = document.getElementById('btn-copy-modal-code');
    const codeBox = document.getElementById('gift-modal-code-box');
    const codeValue = document.getElementById('gift-modal-code-value');
    if (currentGiftUsed) {
      status.textContent = '이 기프티콘은 이미 사용완료 처리되었어요.';
      status.className = 'gift-image-status is-used';
      btn.disabled = true;
      btn.textContent = '사용완료됨';
    } else {
      status.textContent = '실제 사용을 마쳤다면 사용완료로 표시해 둘 수 있어요.';
      status.className = 'gift-image-status';
      btn.disabled = false;
      btn.textContent = '사용완료';
    }
    if (currentGiftCodeText) {
      codeValue.textContent = currentGiftCodeText;
      codeBox.classList.add('is-ready');
      copyBtn.disabled = false;
    } else {
      codeValue.textContent = barcodeDetectorSupported() ? '자동 추출 대기 중' : '이 기기에서는 자동 추출을 지원하지 않아요.';
      codeBox.classList.remove('is-ready');
      copyBtn.disabled = true;
    }
  }

  function getGiftImageUrl(id, download = false) {
    let url = '/shop/_image.php?id=' + id + '&code=' + encodeURIComponent(MY_CODE);
    if (download) url += '&download=1';
    else url += '&_=' + Date.now();
    return url;
  }

  function barcodeDetectorSupported() {
    return typeof window.BarcodeDetector !== 'undefined';
  }

  async function getBarcodeDetector() {
    if (barcodeDetectorReady) return barcodeDetectorInstance;
    barcodeDetectorReady = true;
    if (!barcodeDetectorSupported()) return null;
    try {
      barcodeDetectorInstance = new BarcodeDetector({
        formats: ['code_128', 'code_39', 'codabar', 'ean_13', 'ean_8', 'upc_a', 'upc_e', 'itf']
      });
    } catch (e) {
      barcodeDetectorInstance = null;
    }
    return barcodeDetectorInstance;
  }

  async function loadImageElement(src) {
    return await new Promise((resolve, reject) => {
      const img = new Image();
      img.onload = () => resolve(img);
      img.onerror = reject;
      img.src = src;
    });
  }

  function normalizeDetectedCode(text) {
    return String(text || '').replace(/\s+/g, '');
  }

  function isBarcodeLabelCode(value) {
    const v = normalizeDetectedCode(value).toUpperCase();
    if (!/^[A-Z0-9]{6,16}$/.test(v)) return false;
    if (!/[A-Z]/.test(v) || !/[0-9]/.test(v)) return false;
    return true;
  }

  function isLikelyOrderNumber(value) {
    const v = normalizeDetectedCode(value);
    return /^[0-9]{8,14}$/.test(v);
  }

  function collectOCRCandidates(text, options = {}) {
    const belowBarcode = !!options.belowBarcode;
    const raw = String(text || '').toUpperCase();
    const lines = raw
      .split(/\n+/)
      .map(line => line.replace(/[^A-Z0-9 -]/g, ' ').replace(/\s+/g, ' ').trim())
      .filter(Boolean);

    const candidates = new Set();
    const addCandidate = (value) => {
      const compact = normalizeDetectedCode(value).toUpperCase();
      if (!/^[A-Z0-9-]{4,24}$/.test(compact)) return;
      if (belowBarcode && isLikelyOrderNumber(compact)) return;
      if (compact.length >= (belowBarcode ? 6 : 6)) candidates.add(compact);
    };

    lines.forEach(line => {
      addCandidate(line);

      const grouped = line.match(/[A-Z0-9]{2,}(?:\s+[A-Z0-9]{1,})+/g) || [];
      grouped.forEach(part => addCandidate(part));

      const compact = line.replace(/\s+/g, '');
      if (compact) addCandidate(compact);

      const mixedMatches = compact.match(/[A-Z0-9-]{6,24}/g) || [];
      mixedMatches.forEach(match => addCandidate(match));
    });

    return Array.from(candidates);
  }

  function isLetterHeavyCouponCode(value) {
    const v = normalizeDetectedCode(value).toUpperCase();
    if (!/^[A-Z0-9]{6,20}$/.test(v)) return false;
    const letters = (v.match(/[A-Z]/g) || []).length;
    return letters >= 6 && letters >= Math.floor(v.length * 0.55);
  }

  function scoreOCRCandidate(value, options = {}) {
    const belowBarcode = !!options.belowBarcode;
    const v = normalizeDetectedCode(value).toUpperCase();
    if (!v) return -1;

    let score = 0;
    const digitCount = (v.match(/[0-9]/g) || []).length;
    const letterCount = (v.match(/[A-Z]/g) || []).length;

    if (belowBarcode && isBarcodeLabelCode(v)) score += 20;
    if (belowBarcode && /^[A-Z]{3,5}[0-9]{0,2}[A-Z]{2,4}[0-9]{0,2}[A-Z0-9]{1,4}$/.test(v)) score += 12;
    if (belowBarcode && isLikelyOrderNumber(v)) score -= 20;

    if (/^[0-9]{10,24}$/.test(v)) score += belowBarcode ? 2 : 12;
    else if (/^[0-9]+$/.test(v)) score += belowBarcode ? 0 : 9;
    if (/^[A-Z0-9-]+$/.test(v)) score += 7;
    if (/[0-9]/.test(v)) score += 4;
    if (/[A-Z]/.test(v)) score += 3;
    if (v.length >= 8 && v.length <= 14) score += 8;
    else if (v.length >= 6 && v.length <= 24) score += 4;
    if (isLetterHeavyCouponCode(v)) score += 10;
    if (/^[A-Z]{4,}[0-9]{0,3}$/.test(v) && v.length >= 8) score += 6;
    if (/^(KAKAO|BASKIN|STARBUCKS|GS25|CU|7ELEVEN|BAEMIN)/.test(v)) score -= 8;
    if (digitCount >= 4 && letterCount === 0) score += belowBarcode ? -6 : 3;
    else if (letterCount >= 4) score += 4;
    else score -= 3;
    return score;
  }

  function preprocessOCRCanvas(sourceCanvas, mode = 'binary', threshold = 175) {
    if (!sourceCanvas) return null;
    const processed = document.createElement('canvas');
    processed.width = sourceCanvas.width;
    processed.height = sourceCanvas.height;
    const pctx = processed.getContext('2d', { willReadFrequently: true });
    if (!pctx) return null;
    pctx.drawImage(sourceCanvas, 0, 0);

    const imageData = pctx.getImageData(0, 0, processed.width, processed.height);
    const data = imageData.data;
    let sum = 0;
    for (let i = 0; i < data.length; i += 4) {
      sum += data[i] * 0.299 + data[i + 1] * 0.587 + data[i + 2] * 0.114;
    }
    const mean = sum / Math.max(1, data.length / 4);
    const adaptiveThreshold = Math.max(120, Math.min(205, Math.floor(mean * 0.9)));

    for (let i = 0; i < data.length; i += 4) {
      const gray = data[i] * 0.299 + data[i + 1] * 0.587 + data[i + 2] * 0.114;
      let value = gray;
      if (mode === 'binary') {
        value = gray > threshold ? 255 : 0;
      } else if (mode === 'adaptive') {
        value = gray > adaptiveThreshold ? 255 : 0;
      } else if (mode === 'contrast') {
        value = Math.max(0, Math.min(255, (gray - 110) * 2.2));
      }
      data[i] = value;
      data[i + 1] = value;
      data[i + 2] = value;
    }
    pctx.putImageData(imageData, 0, 0);
    return processed;
  }

  function expandOCRConfusions(value) {
    const base = normalizeDetectedCode(value).toUpperCase();
    if (!base) return [];
    const variants = new Set([base]);
    const swaps = [
      ['0', 'O'], ['O', '0'],
      ['1', 'I'], ['I', '1'],
      ['5', 'S'], ['S', '5'],
      ['8', 'B'], ['B', '8'],
      ['2', 'Z'], ['Z', '2'],
      ['6', 'G'], ['G', '6'],
    ];
    swaps.forEach(([from, to]) => {
      if (base.includes(from)) variants.add(base.split(from).join(to));
    });
    return Array.from(variants).filter(v => /^[A-Z0-9]{6,16}$/.test(v));
  }

  function collectOCRCandidatesFromTesseract(result, options = {}) {
    const found = new Set();
    const addText = (text) => {
      collectOCRCandidates(text, options).forEach(candidate => found.add(candidate));
    };

    if (result && result.data) {
      addText(result.data.text || '');
      const lines = result.data.lines || [];
      lines.forEach(line => {
        if (!line || !line.text) return;
        if (typeof line.confidence === 'number' && line.confidence < 45) return;
        addText(line.text);
      });
      const words = result.data.words || [];
      words.forEach(word => {
        if (!word || !word.text) return;
        if (typeof word.confidence === 'number' && word.confidence < 50) return;
        addText(word.text);
      });
    }

    const expanded = new Set();
    found.forEach(candidate => {
      expanded.add(candidate);
      expandOCRConfusions(candidate).forEach(variant => expanded.add(variant));
    });
    return Array.from(expanded);
  }

  function buildOCRCanvasFromRect(img, rect, scale = 3) {
    const srcW = img.naturalWidth || img.width;
    const srcH = img.naturalHeight || img.height;
    const cropX = Math.max(0, Math.floor(rect.x));
    const cropY = Math.max(0, Math.floor(rect.y));
    const cropW = Math.max(1, Math.min(srcW - cropX, Math.floor(rect.width)));
    const cropH = Math.max(1, Math.min(srcH - cropY, Math.floor(rect.height)));

    const canvas = document.createElement('canvas');
    canvas.width = Math.max(1, Math.floor(cropW * scale));
    canvas.height = Math.max(1, Math.floor(cropH * scale));
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    if (!ctx) return null;

    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(img, cropX, cropY, cropW, cropH, 0, 0, canvas.width, canvas.height);
    return canvas;
  }

  function findBarcodeBand(img) {
    const srcW = img.naturalWidth || img.width;
    const srcH = img.naturalHeight || img.height;
    const canvas = document.createElement('canvas');
    canvas.width = srcW;
    canvas.height = srcH;
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    if (!ctx) return null;
    ctx.drawImage(img, 0, 0);

    const data = ctx.getImageData(0, 0, srcW, srcH).data;
    const yStart = Math.floor(srcH * 0.18);
    const yEnd = Math.floor(srcH * 0.72);
    const xStart = Math.floor(srcW * 0.08);
    const xEnd = Math.floor(srcW * 0.92);
    const step = Math.max(1, Math.floor(srcH / 180));

    let bestY = -1;
    let bestTransitions = 0;
    for (let y = yStart; y < yEnd; y += step) {
      let transitions = 0;
      let prevDark = null;
      for (let x = xStart; x < xEnd; x++) {
        const i = (y * srcW + x) * 4;
        const gray = data[i] * 0.299 + data[i + 1] * 0.587 + data[i + 2] * 0.114;
        const dark = gray < 140;
        if (prevDark !== null && dark !== prevDark) transitions++;
        prevDark = dark;
      }
      if (transitions > bestTransitions) {
        bestTransitions = transitions;
        bestY = y;
      }
    }

    if (bestY < 0 || bestTransitions < 40) return null;

    let top = bestY;
    let bottom = bestY;
    const bandThreshold = Math.floor(bestTransitions * 0.45);
    for (let y = bestY; y >= yStart; y--) {
      let transitions = 0;
      let prevDark = null;
      for (let x = xStart; x < xEnd; x++) {
        const i = (y * srcW + x) * 4;
        const gray = data[i] * 0.299 + data[i + 1] * 0.587 + data[i + 2] * 0.114;
        const dark = gray < 140;
        if (prevDark !== null && dark !== prevDark) transitions++;
        prevDark = dark;
      }
      if (transitions >= bandThreshold) top = y;
      else break;
    }
    for (let y = bestY; y < yEnd; y++) {
      let transitions = 0;
      let prevDark = null;
      for (let x = xStart; x < xEnd; x++) {
        const i = (y * srcW + x) * 4;
        const gray = data[i] * 0.299 + data[i + 1] * 0.587 + data[i + 2] * 0.114;
        const dark = gray < 140;
        if (prevDark !== null && dark !== prevDark) transitions++;
        prevDark = dark;
      }
      if (transitions >= bandThreshold) bottom = y;
      else break;
    }

    return {
      x: xStart,
      y: top,
      width: xEnd - xStart,
      height: Math.max(8, bottom - top + 1),
      bottom: bottom + 1,
    };
  }

  async function locateBarcodeRect(img, cacheKey = '') {
    if (cacheKey && barcodeRectCache.has(cacheKey)) {
      return barcodeRectCache.get(cacheKey);
    }

    const srcW = img.naturalWidth || img.width;
    const srcH = img.naturalHeight || img.height;
    let rect = null;

    const detector = await getBarcodeDetector();
    if (detector) {
      try {
        const bitmap = await createImageBitmap(img);
        const results = await detector.detect(bitmap);
        if (bitmap && typeof bitmap.close === 'function') bitmap.close();
        if (results && results.length) {
          const box = results[0].boundingBox;
          if (box && box.width > 0 && box.height > 0) {
            rect = {
              x: Math.max(0, box.x - box.width * 0.04),
              y: Math.max(0, box.y),
              width: Math.min(srcW, box.width * 1.08),
              height: box.height,
              bottom: box.y + box.height,
            };
          }
        }
      } catch (e) {}
    }

    if (!rect) {
      rect = findBarcodeBand(img);
    }

    if (cacheKey && rect) {
      barcodeRectCache.set(cacheKey, rect);
    }
    return rect;
  }

  function buildBarcodeLabelStrip(img, barcode, cfg) {
    const srcW = img.naturalWidth || img.width;
    const srcH = img.naturalHeight || img.height;
    const gap = Math.max(2, Math.floor(barcode.height * cfg.gapMul));
    const stripTop = Math.max(0, Math.min(srcH - 8, Math.floor((barcode.bottom ?? (barcode.y + barcode.height)) + gap + cfg.yOffset)));
    const stripHeight = Math.max(12, Math.floor(Math.max(srcH * 0.045, barcode.height * 0.85)));
    const rect = cfg.wide
      ? { x: Math.floor(srcW * 0.05), y: stripTop, width: Math.floor(srcW * 0.9), height: stripHeight }
      : {
          x: Math.max(0, Math.floor(barcode.x)),
          y: stripTop,
          width: Math.min(srcW, Math.floor(barcode.width)),
          height: stripHeight,
        };
    return buildOCRCanvasFromRect(img, rect, cfg.scale);
  }

  async function runOCRPasses(canvas, options = {}) {
    const worker = await getTesseractWorker();
    if (!worker || !canvas) return { bestValue: '', bestScore: -1, confident: false };

    const passes = options.quick
      ? [{ mode: 'adaptive', threshold: 175, psm: '7' }]
      : (options.extended ? OCR_PASSES_EXTENDED : OCR_PASSES_NORMAL);

    const voteMap = new Map();
    const addVote = (candidate) => {
      const score = scoreOCRCandidate(candidate, options);
      if (score < 0) return;
      const prev = voteMap.get(candidate) || { totalScore: 0, votes: 0 };
      voteMap.set(candidate, {
        totalScore: prev.totalScore + score,
        votes: prev.votes + 1,
      });
    };

    let bestValue = '';
    let bestScore = -1;

    for (const pass of passes) {
      const processed = preprocessOCRCanvas(canvas, pass.mode, pass.threshold);
      if (!processed) continue;
      try {
        await worker.setParameters({
          tessedit_char_whitelist: 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789',
          tessedit_pageseg_mode: pass.psm,
        });
        const result = await worker.recognize(processed);
        const candidates = options.extended
          ? collectOCRCandidatesFromTesseract(result, options)
          : collectOCRCandidates(result && result.data ? result.data.text : '', options);
        candidates.forEach(addVote);
      } catch (e) {}

      voteMap.forEach((meta, value) => {
        const combined = meta.totalScore + meta.votes * (options.belowBarcode ? 5 : 2);
        if (combined > bestScore) {
          bestScore = combined;
          bestValue = value;
        }
      });

      if (bestScore >= OCR_CONFIDENT_SCORE && isBarcodeLabelCode(bestValue)) {
        return { bestValue, bestScore, confident: true };
      }
    }

    try {
      await worker.setParameters({
        tessedit_char_whitelist: 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789',
        tessedit_pageseg_mode: '7',
      });
    } catch (e) {}

    return { bestValue, bestScore, confident: bestScore >= OCR_CONFIDENT_SCORE };
  }

  async function detectTextBelowBarcode(img, options = {}) {
    const quick = !!options.quick;
    const extended = !!options.extended;
    const cacheKey = options.cacheKey || '';
    const barcode = await locateBarcodeRect(img, cacheKey);
    if (!barcode) return '';

    const stripConfigs = quick
      ? [{ gapMul: 0.12, yOffset: 0, scale: 4, wide: false }]
      : extended
        ? [
            { gapMul: 0.10, yOffset: 0, scale: 4, wide: false },
            { gapMul: 0.14, yOffset: 0, scale: 4.5, wide: true },
            { gapMul: 0.12, yOffset: -4, scale: 4, wide: false },
            { gapMul: 0.12, yOffset: 4, scale: 4, wide: false },
          ]
        : [
            { gapMul: 0.12, yOffset: 0, scale: 4, wide: false },
            { gapMul: 0.10, yOffset: 0, scale: 3.5, wide: true },
          ];

    const ocrOptions = { belowBarcode: true, extended, quick };
    let bestValue = '';
    let bestScore = -1;

    for (const cfg of stripConfigs) {
      const canvas = buildBarcodeLabelStrip(img, barcode, cfg);
      if (!canvas) continue;
      const result = await runOCRPasses(canvas, ocrOptions);
      if (result.bestScore > bestScore) {
        bestScore = result.bestScore;
        bestValue = result.bestValue;
      }
      if (result.confident && isBarcodeLabelCode(bestValue)) {
        return normalizeDetectedCode(bestValue);
      }
    }

    const minScore = isBarcodeLabelCode(bestValue) ? 8 : (extended ? 10 : 12);
    return bestScore >= minScore ? normalizeDetectedCode(bestValue) : '';
  }

  async function detectGiftCodeFromImage(id, options = {}) {
    const img = await loadImageElement(getGiftImageUrl(id));
    const cacheKey = 'gift-' + id;
    const belowOpts = {
      extended: !!options.force,
      quick: !!options.quick,
      cacheKey,
    };

    try {
      const belowBarcode = await detectTextBelowBarcode(img, belowOpts);
      if (belowBarcode) return belowBarcode;
    } catch (e) {}

    if (options.force) return '';

    if (!options.quick) {
      try {
        const value = await detectGiftCodeWithOCR(img);
        if (value) return value;
      } catch (e) {}
    }

    const detector = await getBarcodeDetector();
    if (detector) {
      try {
        const bitmap = await createImageBitmap(img);
        const results = await detector.detect(bitmap);
        if (bitmap && typeof bitmap.close === 'function') bitmap.close();
        if (results && results.length) {
          for (const row of results) {
            const value = normalizeDetectedCode(row.rawValue || '');
            if (value && !isLikelyOrderNumber(value)) return value;
          }
        }
      } catch (e) {}
    }

    try {
      const value = await detectGiftCodeWithZXing(img);
      if (value && !isLikelyOrderNumber(value)) return value;
    } catch (e) {}

    return '';
  }

  function getZXingReader() {
    if (zxingReader) return zxingReader;
    if (!window.ZXingBrowser || !window.ZXingBrowser.BrowserMultiFormatReader) return null;
    zxingReader = new window.ZXingBrowser.BrowserMultiFormatReader();
    return zxingReader;
  }

  async function detectGiftCodeWithZXing(img) {
    const reader = getZXingReader();
    if (!reader) return '';

    async function decodeCanvas(canvas) {
      try {
        const result = await reader.decodeFromCanvas(canvas);
        return normalizeDetectedCode(result && result.text ? result.text : '');
      } catch (e) {
        return '';
      }
    }

    try {
      const result = await reader.decodeFromImageElement(img);
      const value = normalizeDetectedCode(result && result.text ? result.text : '');
      if (value) return value;
    } catch (e) {}

    const srcW = img.naturalWidth || img.width;
    const srcH = img.naturalHeight || img.height;
    const cropRegions = [
      [0.18, 0.42],
      [0.28, 0.30],
      [0.34, 0.22],
      [0.40, 0.18],
      [0.25, 0.55],
    ];

    for (const [topRatio, heightRatio] of cropRegions) {
      try {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d', { willReadFrequently: true });
        if (!ctx) continue;
        const cropTop = Math.max(0, Math.floor(srcH * topRatio));
        const cropHeight = Math.max(1, Math.min(srcH - cropTop, Math.floor(srcH * heightRatio)));
        canvas.width = srcW;
        canvas.height = cropHeight;
        ctx.drawImage(img, 0, cropTop, srcW, cropHeight, 0, 0, srcW, cropHeight);
        const value = await decodeCanvas(canvas);
        if (value) return value;
      } catch (e) {}
    }

    return '';
  }

  async function getTesseractWorker() {
    if (tesseractWorker) return tesseractWorker;
    if (!window.Tesseract || !window.Tesseract.createWorker) return null;
    tesseractWorker = await window.Tesseract.createWorker('eng');
    try {
      await tesseractWorker.setParameters({
        tessedit_char_whitelist: 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789',
        tessedit_pageseg_mode: '7',
      });
    } catch (e) {}
    return tesseractWorker;
  }

  async function detectGiftCodeWithOCR(img) {
    const crops = [
      [0.36, 0.12, 4.0, 170],
      [0.42, 0.12, 3.8, 175],
      [0.48, 0.10, 3.2, 180],
    ];

    let bestValue = '';
    let bestScore = -1;

    for (const [start, height, scale, threshold] of crops) {
      const canvas = buildOCRCanvas(img, start, height, scale, threshold);
      if (!canvas) continue;
      const result = await runOCRPasses(canvas, { belowBarcode: false, quick: true });
      if (result.bestScore > bestScore) {
        bestScore = result.bestScore;
        bestValue = result.bestValue;
      }
    }

    if (isLikelyOrderNumber(bestValue) && !isBarcodeLabelCode(bestValue)) {
      return '';
    }

    const minScore = isBarcodeLabelCode(bestValue) || isLetterHeavyCouponCode(bestValue) ? 6 : 8;
    return bestScore >= minScore ? normalizeDetectedCode(bestValue) : '';
  }

  function buildOCRCanvas(img, startRatio, heightRatio, scale = 2, threshold = 180) {
    const srcW = img.naturalWidth || img.width;
    const srcH = img.naturalHeight || img.height;
    const cropY = Math.max(0, Math.floor(srcH * startRatio));
    const cropH = Math.max(1, Math.min(srcH - cropY, Math.floor(srcH * heightRatio)));

    const canvas = document.createElement('canvas');
    canvas.width = Math.max(1, Math.floor(srcW * scale));
    canvas.height = Math.max(1, Math.floor(cropH * scale));
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    if (!ctx) return null;

    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(img, 0, cropY, srcW, cropH, 0, 0, canvas.width, canvas.height);

    const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
    const data = imageData.data;
    for (let i = 0; i < data.length; i += 4) {
      const gray = data[i] * 0.299 + data[i + 1] * 0.587 + data[i + 2] * 0.114;
      const value = gray > threshold ? 255 : 0;
      data[i] = value;
      data[i + 1] = value;
      data[i + 2] = value;
      data[i + 3] = 255;
    }
    ctx.putImageData(imageData, 0, 0);
    return canvas;
  }

  function updateGiftCodeUI(id, codeText) {
    const normalized = normalizeDetectedCode(codeText);
    if (!normalized) return;

    ['', 'friend-'].forEach(prefix => {
      const valueEl = document.getElementById('gift-code-value-' + prefix + id);
      const boxEl = document.getElementById('gift-code-box-' + prefix + id);
      const copyBtn = document.getElementById('btn-copy-code-' + prefix + id);
      if (valueEl) valueEl.textContent = normalized;
      if (boxEl) boxEl.classList.add('is-ready');
      if (copyBtn) {
        copyBtn.dataset.code = normalized;
        copyBtn.classList.remove('is-hidden');
      }
    });

    const viewBtn = document.querySelector('.btn-view-image[data-id="' + id + '"]');
    if (viewBtn) viewBtn.dataset.codeText = normalized;

    if (currentGiftImageId === id) {
      currentGiftCodeText = normalized;
      syncGiftImageModalState();
    }
  }

  async function saveDetectedGiftCode(id, codeText) {
    const fd = new FormData();
    fd.append('code', MY_CODE);
    fd.append('id', id);
    fd.append('code_text', codeText);
    const res = await fetch('/shop/_save_code_text.php', { method: 'POST', body: fd });
    return await res.json();
  }

  async function autoDetectGiftCode(id, options = {}) {
    if (!MY_CODE || id < 1) return;
    const force = !!options.force;
    if (!force && detectedGiftIds.has(id)) return;
    if (force) detectedGiftIds.delete(id);
    detectedGiftIds.add(id);

    const silent = !!options.silent;
    if (currentGiftImageId === id && (!currentGiftCodeText || force)) {
      const modalValue = document.getElementById('gift-modal-code-value');
      if (modalValue) modalValue.textContent = force ? '번호 재추출 중...' : '번호 자동 추출 중...';
    }

    const codeText = await detectGiftCodeFromImage(id, {
      force: !!options.force,
      quick: !!options.silent && !options.force,
    });
    if (!codeText) {
      detectedGiftIds.delete(id);
      if (currentGiftImageId === id && !silent) {
        const modalValue = document.getElementById('gift-modal-code-value');
        if (modalValue) modalValue.textContent = '번호를 자동으로 찾지 못했어요.';
      }
      if (!silent || force) {
        showToast('번호를 찾지 못했어요. 다시 시도해 보세요.');
      }
      return;
    }

    updateGiftCodeUI(id, codeText);
    try {
      const data = await saveDetectedGiftCode(id, codeText);
      if (data.ok && data.code_text) updateGiftCodeUI(id, data.code_text);
      if (force) showToast('번호를 다시 추출했어요.');
    } catch (e) {
      if (force) showToast('번호 저장 중 오류가 발생했습니다.');
    }
  }

  async function retryDetectGiftCode(id) {
    if (!MY_CODE || id < 1) {
      showToast('인증코드가 필요합니다.');
      return;
    }

    barcodeRectCache.delete('gift-' + id);

    ['', 'friend-'].forEach(prefix => {
      const valueEl = document.getElementById('gift-code-value-' + prefix + id);
      if (valueEl) valueEl.textContent = '번호 재추출 중...';
      const boxEl = document.getElementById('gift-code-box-' + prefix + id);
      if (boxEl) boxEl.classList.remove('is-ready');
      const copyBtn = document.getElementById('btn-copy-code-' + prefix + id);
      if (copyBtn) copyBtn.classList.add('is-hidden');
    });

    if (currentGiftImageId === id) {
      currentGiftCodeText = '';
      const modalValue = document.getElementById('gift-modal-code-value');
      const modalBox = document.getElementById('gift-modal-code-box');
      if (modalValue) modalValue.textContent = '번호 재추출 중...';
      if (modalBox) modalBox.classList.remove('is-ready');
    }

    await autoDetectGiftCode(id, { silent: false, force: true });
  }

  function retryCurrentGiftCode() {
    if (currentGiftImageId > 0) {
      retryDetectGiftCode(currentGiftImageId);
      return;
    }
    showToast('재추출할 이미지가 없습니다.');
  }

  async function copyText(text) {
    const value = normalizeDetectedCode(text);
    if (!value) {
      showToast('복사할 번호가 없습니다.');
      return;
    }
    try {
      if (navigator.clipboard && navigator.clipboard.writeText) {
        await navigator.clipboard.writeText(value);
      } else {
        throw new Error('clipboard unsupported');
      }
      showToast('번호를 복사했어요.');
    } catch (e) {
      const area = document.createElement('textarea');
      area.value = value;
      area.style.position = 'fixed';
      area.style.opacity = '0';
      document.body.appendChild(area);
      area.focus();
      area.select();
      let ok = false;
      try {
        ok = document.execCommand('copy');
      } catch (err) {}
      document.body.removeChild(area);
      showToast(ok ? '번호를 복사했어요.' : '복사에 실패했어요.');
    }
  }

  function copyGiftCodeFromButton(btn) {
    copyText(btn.dataset.code || '');
  }

  function copyCurrentGiftCode() {
    copyText(currentGiftCodeText);
  }

  async function markGiftUsed() {
    if (!MY_CODE || currentGiftImageId < 1) { showToast('상품 정보를 찾을 수 없습니다.'); return; }
    if (currentGiftUsed) { showToast('이미 사용완료 처리된 기프티콘입니다.'); return; }

    const btn = document.getElementById('btn-use-complete');
    btn.disabled = true;
    btn.textContent = '처리 중...';

    const fd = new FormData();
    fd.append('code', MY_CODE);
    fd.append('id', currentGiftImageId);

    try {
      const res = await fetch('/shop/_use_complete.php', { method: 'POST', body: fd });
      const data = await res.json();
      showToast(data.msg || (data.ok ? '사용완료 처리되었습니다.' : '처리 실패'));
      if (data.ok) {
        currentGiftUsed = true;
        syncGiftImageModalState();
        setTimeout(() => location.reload(), 700);
      } else {
        btn.disabled = false;
        btn.textContent = '사용완료';
      }
    } catch (e) {
      btn.disabled = false;
      btn.textContent = '사용완료';
      showToast('서버 오류');
    }
  }

  function openDeleteModal(btn) {
    if (!MY_CODE) { showToast('인증코드가 필요합니다.'); return; }
    pendingDeleteId = parseInt(btn.dataset.id, 10) || 0;
    if (pendingDeleteId < 1) { showToast('삭제할 상품을 찾을 수 없습니다.'); return; }
    const name = btn.dataset.name || '';
    document.getElementById('delete-item-name').textContent = name ? `「${name}」` : '이 상품';
    document.getElementById('btn-confirm-delete').disabled = false;
    document.getElementById('btn-confirm-delete').textContent = '삭제하기';
    document.getElementById('delete-modal').classList.add('open');
  }

  function closeDeleteModal() {
    pendingDeleteId = 0;
    document.getElementById('delete-modal').classList.remove('open');
  }

  function closeDeleteModalOnBg(e) {
    if (e.target.id === 'delete-modal') closeDeleteModal();
  }

  async function confirmDeleteSaleItem() {
    if (!MY_CODE || pendingDeleteId < 1) { showToast('삭제할 상품을 찾을 수 없습니다.'); return; }

    const btn = document.getElementById('btn-confirm-delete');
    btn.disabled = true;
    btn.textContent = '삭제 중...';

    const fd = new FormData();
    fd.append('code', MY_CODE);
    fd.append('id', pendingDeleteId);
    try {
      const res = await fetch('/shop/_delete.php', { method: 'POST', body: fd });
      const data = await res.json();
      closeDeleteModal();
      showToast(data.msg || (data.ok ? '삭제되었습니다.' : '삭제 실패'));
      if (data.ok) setTimeout(() => location.reload(), 900);
      else {
        btn.disabled = false;
        btn.textContent = '삭제하기';
      }
    } catch (e) {
      closeDeleteModal();
      showToast('서버 오류');
      btn.disabled = false;
      btn.textContent = '삭제하기';
    }
  }

  async function doSettle(settleType) {
    settleType = (settleType === 'newpoint') ? 'newpoint' : 'point';
    const btnId = settleType === 'newpoint' ? 'btn-settle-newpoint' : 'btn-settle-point';
    const btn = document.getElementById(btnId);
    const defaultText = settleType === 'newpoint' ? '💜 본방냥 정산 받기' : '💰 게임냥 정산 받기';
    btn.disabled = true;
    btn.textContent = '정산 중...';
    const fd = new FormData();
    fd.append('code', MY_CODE);
    fd.append('settle_type', settleType);
    try {
      const res = await fetch('/shop/_settle.php', { method: 'POST', body: fd });
      const data = await res.json();
      showToast(data.msg || (data.ok ? '정산 완료' : '정산 실패'));
      if (data.ok) setTimeout(() => location.reload(), 900);
      else { btn.disabled = false; btn.textContent = defaultText; }
    } catch (e) {
      showToast('서버 오류');
      btn.disabled = false;
      btn.textContent = defaultText;
    }
  }

  function showToast(msg) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    clearTimeout(t._timer);
    t._timer = setTimeout(() => t.classList.remove('show'), 2800);
  }

  document.querySelectorAll('.btn-view-image').forEach(btn => {
    const id = parseInt(btn.dataset.id, 10) || 0;
    if (id > 0 && !(btn.dataset.codeText || '').trim()) {
      autoDetectGiftCode(id, { silent: true });
    }
  });

  if (FRIENDS_TAB_ENABLED) {
    document.querySelectorAll('#panel-friends .history-item--friends[data-has-image="1"]').forEach(item => {
      const id = parseInt(item.dataset.id, 10) || 0;
      if (id < 1) return;
      const valueEl = document.getElementById('gift-code-value-friend-' + id);
      const existing = valueEl ? valueEl.textContent.trim() : '';
      if (existing && existing !== '자동 추출 대기 중') return;
      autoDetectGiftCode(id, { silent: true });
    });
  }

  document.getElementById('gift-image-view').addEventListener('click', function() {
    if (!currentGiftFriendsMode) return;
    document.getElementById('gift-image-modal').classList.toggle('is-image-zoomed');
  });
</script>
</body>
</html>
