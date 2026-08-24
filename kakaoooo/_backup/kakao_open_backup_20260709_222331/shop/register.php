<?
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
include_once __DIR__ . '/_shop.php';

$code = shop_코드_해석();
$닉 = '';
$게임냥 = 0;
$로그인 = false;

$회원 = shop_auth($code);
if ($회원) {
    $로그인 = true;
    $닉 = $회원['nick'];
    $게임냥 = $회원['point'];
}
$코드없음 = ($code === '');
$코드없음메시지 = shop_코드없음_메시지();
$만원당냥 = shop_만원당_냥($닉);
$환산기준 = shop_환산_기준_요약($닉);
$판매자_수수료율 = $로그인 ? shop_판매자_market_tax($닉) : (float)SHOP_매입_기본_MARKET_TAX;
$선매입_만원당_본방냥 = $로그인 ? shop_선매입_만원당_newpoint($닉) : 0;
$정산_수수료_퍼센트 = (int)round(SHOP_정산_수수료율 * 100);
$마켓url = '/shop/' . ($code !== '' ? '?code=' . rawurlencode($code) : '');
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>기프티콘 등록</title>
  <link rel="stylesheet" href="/css/style.css">
  <?= shop_stylesheet_tag() ?>
</head>
<body>
<div class="shop-wrap narrow">

  <a href="<?= htmlspecialchars($마켓url, ENT_QUOTES, 'UTF-8') ?>" class="back-link">← 마켓으로</a>

  <header class="page-header">
    <h1>🎫 기프티콘 등록</h1>
    <p>권면가 <?= number_format(SHOP_최소권면가) ?>원 이상 · 이미지는 구매자만 확인 가능</p>
    <? if ($로그인) { ?>
      <div class="nyang-badge">🐱 <span title="<?= number_format($게임냥) ?>냥"><?= shop_냥_표시($게임냥) ?></span> 게임냥 · <?= htmlspecialchars($닉, ENT_QUOTES, 'UTF-8') ?></div>
    <? } elseif ($코드없음) { ?>
      <div class="login-hint">💡 <?= htmlspecialchars($코드없음메시지, ENT_QUOTES, 'UTF-8') ?></div>
    <? } else { ?>
      <div class="login-hint">💡 인증코드가 올바르지 않습니다. 상황실에서 코드를 다시 발급받으세요.</div>
    <? } ?>
  </header>

  <div class="register-card">
    <h2>상품 정보</h2>
    <div class="sub">판매 가격은 권면가에 따라 자동 계산됩니다</div>

    <form id="register-form" onsubmit="submitRegister(event)" <?= !$로그인 ? 'style="opacity:0.55;pointer-events:none"' : '' ?>>
      <div class="form-group">
        <label for="reg-name">상품명</label>
        <input type="text" id="reg-name" name="name" placeholder="예) 메가커피 아메리카노" maxlength="120" required>
      </div>
      <div class="form-group">
        <label for="reg-face">권면가 (원)</label>
        <input type="number" id="reg-face" name="face_value" placeholder="예) 1000" min="<?= SHOP_최소권면가 ?>" max="1000000" step="100" required oninput="updatePricePreview()">
        <div class="form-hint"><?= number_format(SHOP_최소권면가) ?>원 이상만 등록 가능</div>
        <div class="form-notice">여러 장의 기프티콘을 올릴 때는 <strong>합산 가격이 아니라 개당 권면가</strong>를 입력해 주세요.<br>예) 2만원권 3장 → 권면가 <strong>20000</strong> 입력 후 이미지 3장 첨부</div>
      </div>
      <div class="form-group">
        <label for="reg-image">기프티콘 이미지</label>
        <input type="file" id="reg-image" name="images[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple required onchange="onRegisterImagesSelected(this)">
        <div class="form-hint">jpg, png, gif, webp · 파일당 최대 5MB · 최대 10개 · 미리보기 ×로 개별 삭제 가능 (첨부한 이미지마다 동일 상품이 개별 등록됩니다)</div>
        <div class="image-preview-grid" id="reg-image-preview"></div>
      </div>
      <div class="form-group">
        <label for="reg-category">카테고리</label>
        <select id="reg-category" name="category">
          <? foreach (shop_카테고리목록() as $key => $cat) { ?>
          <option value="<?= $key ?>"><?= $cat['icon'] ?> <?= $cat['label'] ?></option>
          <? } ?>
        </select>
      </div>
      <div class="form-group">
        <label for="reg-expire">유효기간</label>
        <input type="date" id="reg-expire" name="expire" required>
      </div>

      <div class="price-preview">
        <div class="price-preview-row">
          <div class="label">판매 가격 (게임냥 · 자동 환산)</div>
          <div class="nyang" id="preview-nyang">0 냥</div>
          <div class="formula" id="preview-formula">본방냥 <?= number_format($환산기준['보유냥_1만원']) ?> × 스왑 <?= shop_냥_표시($환산기준['스왑_1보유당_게임']) ?> = 1만원 <?= shop_냥_표시($만원당냥) ?>게임냥</div>
        </div>
        <div class="price-preview-divider"></div>
        <div class="price-preview-row">
          <div class="label">선매입 본방냥 (자동 환산)</div>
          <div class="nyang prebuy" id="preview-prebuy-np">0 본방냥</div>
          <div class="formula" id="preview-prebuy-formula">1만원 = 시세 스냅샷 본방냥 × <?= shop_market_tax_표시($판매자_수수료율) ?> = <?= number_format($선매입_만원당_본방냥) ?> 본방냥</div>
        </div>
        <div class="price-preview-fee" id="preview-fee-info">
          내 수수료율 — 선매입 <?= shop_market_tax_표시($판매자_수수료율) ?>/만원 · 판매 정산 <?= $정산_수수료_퍼센트 ?>%
        </div>
        <div class="price-preview-divider"></div>
        <div class="price-preview-row">
          <div class="label">등록 합산 · 은총</div>
          <div class="nyang eunchong" id="preview-eunchong">은총 0개 지급 예정</div>
          <div class="formula" id="preview-eunchong-formula">권면가 × 이미지 수 ÷ <?= number_format(SHOP_등록_은총_단위권면가) ?>원 = 은총</div>
        </div>
      </div>

      <h2 style="margin-top:24px">등록 전 안내 및 동의</h2>
      <div class="sub" style="margin-bottom:12px">아래 내용을 확인하고 동의해주세요</div>

      <div class="agree-box">
        <h3>아영이네 마켓 이용 안내</h3>
        <ul>
          <li>판매자가 기프티콘을 등록한 뒤 <strong>본인이 실수로 사용</strong>하는 경우가 있을 수 있습니다.</li>
          <li>판매자는 마켓에 올려둔 기프티콘을 <strong>별도로 관리</strong>하고, 판매 완료된 상품은 직접 사용하지 않기로 합니다.</li>
          <li>판매된 기프티콘임에도 판매자가 사용하여, 구매자가 사용 시 <strong>「이미 사용된 기프티콘」</strong>으로 확인되는 경우가 발생할 수 있습니다.</li>
        </ul>
        <div class="warn">
          ⚠️ 구매자가 실제로 사용하려 했으나 이미 사용 처리된 기프티콘이 확인될 경우,<br>
          해당 <strong>판매자의 게임냥 30%가 패널티로 차감</strong>됩니다.
        </div>
      </div>

      <label class="agree-check">
        <input type="checkbox" id="reg-agree-check">
        <span>위 안내 내용을 확인했으며, 판매된 기프티콘을 별도 관리하고 사용하지 않겠습니다.</span>
      </label>

      <div class="form-actions">
        <a href="<?= htmlspecialchars($마켓url, ENT_QUOTES, 'UTF-8') ?>" class="btn-cancel">취소</a>
        <button type="submit" class="btn-submit" id="btn-submit-register" disabled>등록하기</button>
      </div>
    </form>
  </div>

</div>

<div class="toast" id="toast"></div>

<? if ($코드없음) { shop_코드_게이트_출력(); } ?>

<script>
  <?= shop_냥_표시_js() ?>

  const IS_LOGIN = <?= $로그인 ? 'true' : 'false' ?>;
  const HAS_CODE = <?= ($code !== '') ? 'true' : 'false' ?>;
  const MY_CODE = <?= json_encode($code, JSON_UNESCAPED_UNICODE) ?>;
  const CODE_REQUIRED_MSG = <?= json_encode($코드없음메시지, JSON_UNESCAPED_UNICODE) ?>;
  const NYANG_PER_10000 = <?= (int)$만원당냥 ?>;
  const NP_PER_10000 = <?= (int)$환산기준['보유냥_1만원'] ?>;
  const SWAP_RATE = <?= (int)$환산기준['스왑_1보유당_게임'] ?>;
  const PREBUY_NP_PER_10000 = <?= (int)$선매입_만원당_본방냥 ?>;
  const MARKET_TAX_PCT = <?= json_encode(shop_market_tax_표시($판매자_수수료율), JSON_UNESCAPED_UNICODE) ?>;
  const MIN_FACE_VALUE = <?= (int)SHOP_최소권면가 ?>;
  const EUNCHONG_UNIT_FACE = <?= (int)SHOP_등록_은총_단위권면가 ?>;
  const MAX_IMAGES = 10;
  const MARKET_URL = <?= json_encode($마켓url, JSON_UNESCAPED_UNICODE) ?>;
  let regPreviewUrls = [];
  let selectedImageFiles = [];

  document.getElementById('reg-expire').min = new Date().toISOString().slice(0, 10);
  updatePricePreview();

  document.getElementById('reg-agree-check').addEventListener('change', function () {
    document.getElementById('btn-submit-register').disabled = !this.checked;
  });

  function calcNyang(faceValue) {
    const v = parseInt(faceValue, 10) || 0;
    if (v <= 0) return 0;
    return Math.round(v / 10000 * NYANG_PER_10000);
  }

  function calcPrebuyNp(faceValue) {
    const v = parseInt(faceValue, 10) || 0;
    if (v <= 0) return 0;
    return Math.floor(v / 10000 * PREBUY_NP_PER_10000);
  }

  function calcRegisterEunchong(faceValue, imageCount) {
    const face = parseInt(faceValue, 10) || 0;
    const count = Math.max(0, parseInt(imageCount, 10) || 0);
    if (face <= 0 || count <= 0 || EUNCHONG_UNIT_FACE < 1) return 0;
    return Math.floor((face * count) / EUNCHONG_UNIT_FACE);
  }

  function updatePricePreview() {
    const face = document.getElementById('reg-face').value;
    const nyang = calcNyang(face);
    const prebuyNp = calcPrebuyNp(face);
    const faceNum = parseInt(face, 10) || 0;
    const imageCount = selectedImageFiles.length;
    const totalFace = faceNum * Math.max(imageCount, 0);
    const eunchong = calcRegisterEunchong(faceNum, imageCount);
    document.getElementById('preview-nyang').textContent = shopFormatNyangShort(nyang) + ' 냥';
    document.getElementById('preview-prebuy-np').textContent = prebuyNp.toLocaleString() + ' 본방냥';
    document.getElementById('preview-eunchong').textContent =
      '은총 ' + eunchong.toLocaleString() + '개 지급 예정';
    if (faceNum > 0 && imageCount > 0) {
      document.getElementById('preview-eunchong-formula').textContent =
        faceNum.toLocaleString() + '원 × ' + imageCount + '장 = ' + totalFace.toLocaleString() + '원'
        + ' ÷ ' + EUNCHONG_UNIT_FACE.toLocaleString() + '원 = 은총 ' + eunchong.toLocaleString() + '개';
    } else if (faceNum > 0) {
      document.getElementById('preview-eunchong-formula').textContent =
        '이미지를 첨부하면 총권면가(' + faceNum.toLocaleString() + '원 × 장수) 기준으로 은총이 계산됩니다';
    } else {
      document.getElementById('preview-eunchong-formula').textContent =
        '권면가 × 이미지 수 ÷ ' + EUNCHONG_UNIT_FACE.toLocaleString() + '원 = 은총';
    }
    if (faceNum > 0) {
      document.getElementById('preview-formula').textContent =
        faceNum.toLocaleString() + '원 = 본방냥 ' + Math.round(faceNum / 10000 * NP_PER_10000).toLocaleString()
        + ' × 스왑 ' + shopFormatNyangShort(SWAP_RATE)
        + ' = ' + shopFormatNyangShort(nyang) + '게임냥';
      document.getElementById('preview-prebuy-formula').textContent =
        faceNum.toLocaleString() + '원 × ' + MARKET_TAX_PCT + '/만원'
        + ' = ' + prebuyNp.toLocaleString() + ' 본방냥 (관리자 선매입 시 선지급)';
    } else {
      document.getElementById('preview-formula').textContent =
        '본방냥 ' + NP_PER_10000.toLocaleString() + ' × 스왑 ' + shopFormatNyangShort(SWAP_RATE)
        + ' = 1만원 ' + shopFormatNyangShort(NYANG_PER_10000) + '게임냥';
      document.getElementById('preview-prebuy-formula').textContent =
        '1만원 = 전체보유냥 × ' + MARKET_TAX_PCT + ' = ' + PREBUY_NP_PER_10000.toLocaleString() + ' 본방냥';
    }
  }

  function clearRegPreviewUrls() {
    regPreviewUrls.forEach(function (url) { URL.revokeObjectURL(url); });
    regPreviewUrls = [];
  }

  function syncRegisterImageInput() {
    const input = document.getElementById('reg-image');
    const dt = new DataTransfer();
    selectedImageFiles.forEach(function (file) {
      dt.items.add(file);
    });
    input.files = dt.files;
  }

  function renderRegisterImagePreviews() {
    const box = document.getElementById('reg-image-preview');
    clearRegPreviewUrls();
    box.innerHTML = '';
    selectedImageFiles.forEach(function (file, idx) {
      const url = URL.createObjectURL(file);
      regPreviewUrls.push(url);
      const wrap = document.createElement('div');
      wrap.className = 'image-preview-item';
      const img = document.createElement('img');
      img.src = url;
      img.alt = '미리보기 ' + (idx + 1);
      const removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.className = 'image-preview-remove';
      removeBtn.setAttribute('aria-label', (idx + 1) + '번 이미지 삭제');
      removeBtn.textContent = '×';
      removeBtn.addEventListener('click', function () {
        removeRegisterImage(idx);
      });
      const label = document.createElement('span');
      label.textContent = (idx + 1);
      wrap.appendChild(img);
      wrap.appendChild(removeBtn);
      wrap.appendChild(label);
      box.appendChild(wrap);
    });
    updatePricePreview();
  }

  function removeRegisterImage(index) {
    if (index < 0 || index >= selectedImageFiles.length) return;
    selectedImageFiles.splice(index, 1);
    syncRegisterImageInput();
    renderRegisterImagePreviews();
  }

  function onRegisterImagesSelected(input) {
    const incoming = input.files ? Array.from(input.files) : [];
    if (!incoming.length) {
      return;
    }
    const merged = selectedImageFiles.concat(incoming);
    if (merged.length > MAX_IMAGES) {
      showToast('기프티콘 이미지는 최대 ' + MAX_IMAGES + '개까지 첨부할 수 있습니다.');
      syncRegisterImageInput();
      return;
    }
    selectedImageFiles = merged;
    syncRegisterImageInput();
    renderRegisterImagePreviews();
  }

  function showToast(msg) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    clearTimeout(t._timer);
    t._timer = setTimeout(() => t.classList.remove('show'), 2800);
  }

  async function submitRegister(e) {
    e.preventDefault();
    if (!HAS_CODE) { showToast(CODE_REQUIRED_MSG); return; }
    if (!IS_LOGIN) { showToast('상황실에서 코드를 다시 발급받으세요.'); return; }
    if (!document.getElementById('reg-agree-check').checked) {
      showToast('안내 내용에 동의해주세요.');
      return;
    }

    const faceVal = parseInt(document.getElementById('reg-face').value, 10) || 0;
    if (faceVal < MIN_FACE_VALUE) {
      showToast('권면가는 ' + MIN_FACE_VALUE.toLocaleString() + '원 이상이어야 합니다.');
      return;
    }
    if (!selectedImageFiles.length) {
      showToast('기프티콘 이미지를 첨부해주세요.');
      return;
    }
    if (selectedImageFiles.length > MAX_IMAGES) {
      showToast('기프티콘 이미지는 최대 ' + MAX_IMAGES + '개까지 첨부할 수 있습니다.');
      return;
    }
    syncRegisterImageInput();

    const btn = document.getElementById('btn-submit-register');
    btn.disabled = true;
    btn.textContent = '등록 중...';

    const fd = new FormData(document.getElementById('register-form'));
    fd.append('code', MY_CODE);

    try {
      const res = await fetch('/shop/_register.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.ok) {
        showToast(data.msg || '등록 완료!');
        setTimeout(() => { location.href = MARKET_URL; }, 900);
      } else {
        showToast(data.msg || '등록 실패');
        btn.disabled = !document.getElementById('reg-agree-check').checked;
        btn.textContent = '등록하기';
      }
    } catch (err) {
      showToast('서버 오류가 발생했습니다.');
      btn.disabled = !document.getElementById('reg-agree-check').checked;
      btn.textContent = '등록하기';
    }
  }
</script>
</body>
</html>
