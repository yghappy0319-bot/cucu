<?
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
include_once __DIR__ . '/_shop.php';

$code = shop_코드_해석();
$상품id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$닉 = '';
$게임냥 = 0;
$로그인 = false;
$상품 = null;

$회원 = shop_auth($code);
$관리자 = false;
$수정가능 = false;
if ($회원) {
    $로그인 = true;
    $닉 = $회원['nick'];
    $게임냥 = $회원['point'];
    $관리자 = (bool)shop_관리자_인증($code);
    $수정가능 = shop_수정_허용($닉);
    if ($상품id > 0) {
        $상품 = shop_판매상품_단건($상품id, $닉, $관리자 || $수정가능);
    }
}
$판매자닉 = ($상품 && trim((string)($상품['seller'] ?? '')) !== '') ? trim((string)$상품['seller']) : $닉;
$관리자타인수정 = ($관리자 || $수정가능) && $상품 && $판매자닉 !== $닉;
$삭제버튼표시 = $상품 && $수정가능 && shop_삭제_허용($닉);
$코드없음 = ($code === '');
$코드없음메시지 = shop_코드없음_메시지();
$만원당냥 = shop_만원당_냥($판매자닉);
$환산기준 = shop_환산_기준_요약($판매자닉);
$판매자_수수료율 = $상품 ? shop_판매자_market_tax($판매자닉) : ($로그인 ? shop_판매자_market_tax($닉) : (float)SHOP_매입_기본_MARKET_TAX);
$선매입_만원당_본방냥 = $상품 ? shop_선매입_만원당_newpoint($판매자닉) : ($로그인 ? shop_선매입_만원당_newpoint($닉) : 0);
$정산_수수료_퍼센트 = (int)round(SHOP_정산_수수료율 * 100);
$마켓url = '/shop/' . ($code !== '' ? '?' . 'code=' . rawurlencode($code) : '');
$판매내역url = '/shop/history.php?' . ($code !== '' ? 'code=' . rawurlencode($code) . '&' : '') . 'tab=sell';
$돌아가기url = $관리자타인수정 ? $마켓url : $판매내역url;
$돌아가기라벨 = $관리자타인수정 ? '마켓으로' : '판매내역으로';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>기프티콘 수정</title>
  <link rel="stylesheet" href="/css/style.css">
  <?= shop_stylesheet_tag() ?>
</head>
<body>
<div class="shop-wrap narrow">

  <a href="<?= htmlspecialchars($돌아가기url, ENT_QUOTES, 'UTF-8') ?>" class="back-link">← <?= htmlspecialchars($돌아가기라벨, ENT_QUOTES, 'UTF-8') ?></a>

  <header class="page-header">
    <h1>✏️ 기프티콘 수정</h1>
    <? if ($관리자타인수정) { ?>
    <p>관리자 · <strong><?= htmlspecialchars($판매자닉, ENT_QUOTES, 'UTF-8') ?></strong>님 상품 수정</p>
    <? } else { ?>
    <p>판매중인 상품만 수정할 수 있어요</p>
    <? } ?>
    <? if ($로그인) { ?>
      <div class="nyang-badge">🐱 <span title="<?= number_format($게임냥) ?>냥"><?= shop_냥_표시($게임냥) ?></span> 게임냥 · <?= htmlspecialchars($닉, ENT_QUOTES, 'UTF-8') ?></div>
    <? } elseif ($코드없음) { ?>
      <div class="login-hint">💡 <?= htmlspecialchars($코드없음메시지, ENT_QUOTES, 'UTF-8') ?></div>
    <? } else { ?>
      <div class="login-hint">💡 인증코드가 올바르지 않습니다. 연구실에서 코드를 다시 발급받으세요.</div>
    <? } ?>
  </header>

  <? if ($로그인 && !$상품) { ?>
  <div class="register-card">
    <div class="empty-state">판매중인 상품을 찾을 수 없어요.<br>이미 판매됐거나 삭제된 상품일 수 있습니다.</div>
    <div class="form-actions" style="margin-top:16px">
      <a href="<?= htmlspecialchars($돌아가기url, ENT_QUOTES, 'UTF-8') ?>" class="btn-cancel"><?= htmlspecialchars($돌아가기라벨, ENT_QUOTES, 'UTF-8') ?></a>
    </div>
  </div>
  <? } elseif ($로그인 && $상품 && !$수정가능) { ?>
  <div class="register-card">
    <div class="empty-state"><?= htmlspecialchars(shop_수정삭제_문의문구(), ENT_QUOTES, 'UTF-8') ?></div>
    <div class="form-actions" style="margin-top:16px">
      <a href="<?= htmlspecialchars($돌아가기url, ENT_QUOTES, 'UTF-8') ?>" class="btn-cancel"><?= htmlspecialchars($돌아가기라벨, ENT_QUOTES, 'UTF-8') ?></a>
    </div>
  </div>
  <? } elseif ($상품) { ?>
  <div class="register-card">
    <h2>상품 정보</h2>
    <div class="sub">판매 가격은 권면가에 따라 자동 계산됩니다</div>

    <form id="edit-form" onsubmit="submitEdit(event)">
      <input type="hidden" name="id" value="<?= (int)$상품['id'] ?>">
      <div class="form-group">
        <label for="edit-name">상품명</label>
        <input type="text" id="edit-name" name="name" value="<?= htmlspecialchars($상품['name'], ENT_QUOTES, 'UTF-8') ?>" maxlength="120" required>
      </div>
      <div class="form-group">
        <label for="edit-face">권면가 (원)</label>
        <input type="number" id="edit-face" name="face_value" value="<?= (int)$상품['face_value'] ?>" min="<?= SHOP_최소권면가 ?>" max="1000000" step="100" required oninput="updatePricePreview()">
        <div class="form-hint"><?= number_format(SHOP_최소권면가) ?>원 이상만 등록 가능</div>
      </div>
      <div class="form-group">
        <label for="edit-image">기프티콘 이미지</label>
        <input type="file" id="edit-image" name="image" accept="image/jpeg,image/png,image/gif,image/webp" onchange="previewEditImage(this)">
        <div class="form-hint">변경하지 않으면 기존 이미지가 유지됩니다 · jpg, png, gif, webp · 최대 5MB</div>
        <div class="image-preview-box" id="edit-image-preview">
          <? if ($상품['has_image']) { ?>
          <img id="edit-image-preview-img" src="/shop/_image.php?id=<?= (int)$상품['id'] ?>&code=<?= rawurlencode($code) ?>" alt="현재 이미지">
          <? } else { ?>
          <img id="edit-image-preview-img" src="" alt="미리보기" style="display:none">
          <? } ?>
        </div>
      </div>
      <div class="form-group">
        <label for="edit-category">카테고리</label>
        <select id="edit-category" name="category">
          <? foreach (shop_카테고리목록() as $key => $cat) { ?>
          <option value="<?= $key ?>"<?= $상품['category'] === $key ? ' selected' : '' ?>><?= $cat['icon'] ?> <?= $cat['label'] ?></option>
          <? } ?>
        </select>
      </div>
      <div class="form-group">
        <label for="edit-expire">유효기간</label>
        <input type="date" id="edit-expire" name="expire" value="<?= htmlspecialchars($상품['expire'], ENT_QUOTES, 'UTF-8') ?>" required>
      </div>

      <div class="price-preview">
        <div class="price-preview-row">
          <div class="label">판매 가격 (게임냥 · 자동 환산 · <?= shop_market_tax_표시(shop_판매등록_market_tax()) ?>)</div>
          <div class="nyang" id="preview-nyang">0 냥</div>
        </div>
        <div class="price-preview-divider"></div>
        <div class="price-preview-row">
          <div class="label">선매입 본방냥 (등록/수정 시 고정)</div>
          <div class="nyang prebuy" id="preview-prebuy-np">0 본방냥</div>
          <div class="formula" id="preview-prebuy-formula">1만원 = 실시간 본방냥 × <?= shop_market_tax_표시($판매자_수수료율) ?> = <?= number_format($선매입_만원당_본방냥) ?> 본방냥 · 수정 시 재기록</div>
        </div>
        <div class="price-preview-fee" id="preview-fee-info">
          <? if ($관리자타인수정) { ?>
          판매등록 게임냥 <?= shop_market_tax_표시(shop_판매등록_market_tax()) ?> 고정 · 판매자 선매입 <?= shop_market_tax_표시($판매자_수수료율) ?>/만원(수정가 고정) · 게임냥 구매 시 판매자 <?= (int)round(SHOP_겜냥구매_판매자환급율 * 100) ?>% 즉시 환급 · 나머지 소멸
          <? } else { ?>
          판매등록 게임냥 <?= shop_market_tax_표시(shop_판매등록_market_tax()) ?> 고정 · 선매입 <?= shop_market_tax_표시($판매자_수수료율) ?>/만원(수정가 고정) · 게임냥 구매 시 판매자 <?= (int)round(SHOP_겜냥구매_판매자환급율 * 100) ?>% 즉시 환급 · 나머지 소멸
          <? } ?>
        </div>
      </div>

      <div class="form-actions">
        <a href="<?= htmlspecialchars($돌아가기url, ENT_QUOTES, 'UTF-8') ?>" class="btn-cancel">취소</a>
        <button type="submit" class="btn-submit" id="btn-submit-edit">수정하기</button>
      </div>
      <? if ($삭제버튼표시) { ?>
      <button type="button" class="btn-delete btn-delete-full" onclick="deleteEditItem()">삭제하기</button>
      <? } ?>
    </form>
  </div>
  <? } ?>

</div>

<div class="toast" id="toast"></div>

<? if ($코드없음) { shop_코드_게이트_출력(); } ?>

<script>
  <?= shop_냥_표시_js() ?>

  const IS_LOGIN = <?= $로그인 ? 'true' : 'false' ?>;
  const HAS_CODE = <?= ($code !== '') ? 'true' : 'false' ?>;
  const MY_CODE = <?= json_encode($code, JSON_UNESCAPED_UNICODE) ?>;
  const CODE_REQUIRED_MSG = <?= json_encode($코드없음메시지, JSON_UNESCAPED_UNICODE) ?>;
  const NYANG_PER_10000 = <?= shop_js_냥수($만원당냥) ?>;
  const NP_PER_10000 = <?= shop_js_냥수($환산기준['보유냥_1만원']) ?>;
  const SWAP_RATE = <?= shop_js_냥수($환산기준['스왑_1보유당_게임']) ?>;
  const PREBUY_NP_PER_10000 = <?= shop_js_냥수($선매입_만원당_본방냥) ?>;
  const MARKET_TAX_PCT = <?= json_encode(shop_market_tax_표시($판매자_수수료율), JSON_UNESCAPED_UNICODE) ?>;
  const SALE_TAX_PCT = <?= json_encode(shop_market_tax_표시(shop_판매등록_market_tax()), JSON_UNESCAPED_UNICODE) ?>;
  const MIN_FACE_VALUE = <?= (int)SHOP_최소권면가 ?>;
  const SELL_HISTORY_URL = <?= json_encode($판매내역url, JSON_UNESCAPED_UNICODE) ?>;
  const RETURN_URL = <?= json_encode($돌아가기url, JSON_UNESCAPED_UNICODE) ?>;
  const EDIT_ITEM_ID = <?= $상품 ? (int)$상품['id'] : 0 ?>;
  const EDIT_ITEM_NAME = <?= json_encode($상품 ? $상품['name'] : '', JSON_UNESCAPED_UNICODE) ?>;

  const expireInput = document.getElementById('edit-expire');
  if (expireInput) {
    expireInput.min = new Date().toISOString().slice(0, 10);
    updatePricePreview();
  }

  function calcNyang(faceValue) {
    return shopFaceToNyang(faceValue, NYANG_PER_10000);
  }

  function calcPrebuyNp(faceValue) {
    return shopFaceToNyang(faceValue, PREBUY_NP_PER_10000);
  }

  function updatePricePreview() {
    const faceEl = document.getElementById('edit-face');
    if (!faceEl) return;
    const face = faceEl.value;
    const nyang = calcNyang(face);
    const prebuyNp = calcPrebuyNp(face);
    const faceNum = parseInt(face, 10) || 0;
    document.getElementById('preview-nyang').textContent = shopFormatNyangShort(nyang) + ' 냥';
    document.getElementById('preview-prebuy-np').textContent = Number(prebuyNp).toLocaleString() + ' 본방냥';
    if (faceNum > 0) {
      document.getElementById('preview-prebuy-formula').textContent =
        faceNum.toLocaleString() + '원 × ' + MARKET_TAX_PCT + '/만원'
        + ' = ' + Number(prebuyNp).toLocaleString() + ' 본방냥 (수정 시 재기록 · 선매입 선지급)';
    } else {
      document.getElementById('preview-prebuy-formula').textContent =
        '1만원 = 실시간 본방냥 × ' + MARKET_TAX_PCT + ' = ' + Number(PREBUY_NP_PER_10000).toLocaleString() + ' 본방냥 · 수정 시 재기록';
    }
  }

  function previewEditImage(input) {
    const img = document.getElementById('edit-image-preview-img');
    if (!img) return;
    if (!input.files || !input.files[0]) return;
    img.style.display = '';
    img.src = URL.createObjectURL(input.files[0]);
  }

  function showToast(msg) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    clearTimeout(t._timer);
    t._timer = setTimeout(() => t.classList.remove('show'), 2800);
  }

  async function deleteEditItem() {
    if (!HAS_CODE) { showToast(CODE_REQUIRED_MSG); return; }
    if (!IS_LOGIN || EDIT_ITEM_ID < 1) { showToast('삭제할 상품을 찾을 수 없습니다.'); return; }
    const label = EDIT_ITEM_NAME ? `「${EDIT_ITEM_NAME}」` : '이 상품';
    if (!confirm(`${label}을(를) 마켓에서 삭제할까요?\n삭제하면 복구할 수 없습니다.`)) return;

    const fd = new FormData();
    fd.append('code', MY_CODE);
    fd.append('id', EDIT_ITEM_ID);
    try {
      const res = await fetch('/shop/_delete.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.ok) {
        showToast(data.msg || '삭제되었습니다.');
        setTimeout(() => { location.href = RETURN_URL; }, 900);
      } else {
        showToast(data.msg || '삭제 실패');
      }
    } catch (err) {
      showToast('서버 오류가 발생했습니다.');
    }
  }

  async function submitEdit(e) {
    e.preventDefault();
    if (!HAS_CODE) { showToast(CODE_REQUIRED_MSG); return; }
    if (!IS_LOGIN) { showToast('연구실에서 코드를 다시 발급받으세요.'); return; }

    const faceVal = parseInt(document.getElementById('edit-face').value, 10) || 0;
    if (faceVal < MIN_FACE_VALUE) {
      showToast('권면가는 ' + MIN_FACE_VALUE.toLocaleString() + '원 이상이어야 합니다.');
      return;
    }

    const btn = document.getElementById('btn-submit-edit');
    btn.disabled = true;
    btn.textContent = '수정 중...';

    const fd = new FormData(document.getElementById('edit-form'));
    fd.append('code', MY_CODE);

    try {
      const res = await fetch('/shop/_update.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.ok) {
        showToast(data.msg || '수정 완료!');
        setTimeout(() => { location.href = RETURN_URL; }, 900);
      } else {
        showToast(data.msg || '수정 실패');
        btn.disabled = false;
        btn.textContent = '수정하기';
      }
    } catch (err) {
      showToast('서버 오류가 발생했습니다.');
      btn.disabled = false;
      btn.textContent = '수정하기';
    }
  }
</script>
</body>
</html>
