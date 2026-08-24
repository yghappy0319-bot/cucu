<?
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
include_once __DIR__ . '/_shop.php';

$code = shop_코드_해석();
$코드없음 = ($code === '');
$코드없음메시지 = shop_코드없음_메시지();
$회원 = shop_관리자_인증($code);
$관리자 = (bool)$회원;

$게임만원당 = shop_만원당_냥();
$보유만원당 = shop_선매입_만원당_newpoint();
$스왑율 = shop_스왑_1보유냥당_게임냥();
$마이그레이션목록 = $관리자 ? shop_마이그레이션_대상조회() : [];
$변경건수 = 0;
foreach ($마이그레이션목록 as $row) {
    if ($row['changed']) {
        $변경건수++;
    }
}
$마켓url = '/shop/' . ($code !== '' ? '?code=' . rawurlencode($code) : '');
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>기프티콘 가격 마이그레이션</title>
  <link rel="stylesheet" href="/css/style.css">
  <?= shop_stylesheet_tag() ?>
  <style>
    .migrate-summary {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 10px;
      margin-bottom: 16px;
    }
    @media (min-width: 600px) {
      .migrate-summary { grid-template-columns: repeat(4, 1fr); }
    }
    .migrate-stat {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 14px 12px;
      text-align: center;
      box-shadow: var(--shadow);
    }
    .migrate-stat strong {
      display: block;
      font-size: 1.1rem;
      color: var(--accent-dark);
      margin-bottom: 4px;
    }
    .migrate-stat span {
      font-size: 0.8rem;
      color: var(--muted);
      font-weight: 600;
    }
    .migrate-table-wrap {
      overflow-x: auto;
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 14px;
      box-shadow: var(--shadow);
      margin-bottom: 16px;
    }
    .migrate-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.88rem;
    }
    .migrate-table th,
    .migrate-table td {
      padding: 12px 10px;
      border-bottom: 1px solid var(--border);
      text-align: left;
      vertical-align: top;
    }
    .migrate-table th {
      background: #fff8fb;
      font-weight: 800;
      color: var(--text-secondary);
      white-space: nowrap;
    }
    .migrate-table tr:last-child td { border-bottom: none; }
    .price-old { color: var(--muted); text-decoration: line-through; }
    .price-new { color: var(--accent-dark); font-weight: 800; }
    .price-same { color: #2e7d32; font-weight: 700; }
    .diff-up { color: #c62828; font-weight: 700; }
    .diff-down { color: #1565c0; font-weight: 700; }
    .btn-migrate {
      width: 100%;
      padding: 16px;
      background: #6a1b9a;
      color: #fff;
      border: none;
      border-radius: 14px;
      font-size: 1.05rem;
      font-family: inherit;
      font-weight: 800;
      cursor: pointer;
    }
    .btn-migrate:disabled { background: #c7c7cc; cursor: not-allowed; }
    .warn-box {
      background: #fff3e0;
      border: 1px solid #ffb74d;
      border-radius: 14px;
      padding: 14px 16px;
      margin-bottom: 16px;
      font-size: 0.9rem;
      line-height: 1.6;
      color: #bf360c;
    }
  </style>
</head>
<body>
<div class="shop-wrap">

  <a href="<?= htmlspecialchars($마켓url, ENT_QUOTES, 'UTF-8') ?>" class="back-link">← 마켓으로</a>

  <header class="page-header">
    <h1>⚙️ 게임냥 가격 마이그레이션</h1>
    <p>판매중·예약중 상품의 판매가를 게임냥 기준으로 다시 계산합니다</p>
    <? if ($관리자) { ?>
      <div class="nyang-badge">👑 <?= htmlspecialchars($회원['nick'], ENT_QUOTES, 'UTF-8') ?> · 관리자</div>
    <? } elseif ($코드없음) { ?>
      <div class="login-hint">💡 <?= htmlspecialchars($코드없음메시지, ENT_QUOTES, 'UTF-8') ?></div>
    <? } else { ?>
      <div class="login-hint">💡 관리자 계정으로 접속해야 실행할 수 있습니다.</div>
    <? } ?>
  </header>

  <? if ($관리자) { ?>

  <div class="rate-banner">
    💰 환산 기준<br>
    <strong>본방냥(선매입)</strong> 1만원 = <?= number_format($보유만원당) ?>냥 (시세 스냅샷 × <?= (float)SHOP_매입_기본_MARKET_TAX ?>%)<br>
    <strong>스왑</strong> 1보유냥 = <?= number_format($스왑율) ?>게임냥<br>
    <strong>게임냥 판매가</strong> 1만원 = <?= shop_냥_표시($게임만원당) ?>게임냥
  </div>

  <div class="warn-box">
    판매 완료된 상품은 변경하지 않습니다.<br>
    마이그레이션은 <strong>판매중·예약중</strong> 상품의 <code>price_nyang</code>을
    <strong>본방냥 <?= (float)SHOP_매입_기본_MARKET_TAX ?>% × 스왑환율</strong> 기준 게임냥으로 다시 계산합니다.
  </div>

  <div class="migrate-summary">
    <div class="migrate-stat">
      <strong><?= count($마이그레이션목록) ?>건</strong>
      <span>대상 상품</span>
    </div>
    <div class="migrate-stat">
      <strong><?= number_format($변경건수) ?>건</strong>
      <span>가격 변경 예정</span>
    </div>
    <div class="migrate-stat">
      <strong><?= number_format(count($마이그레이션목록) - $변경건수) ?>건</strong>
      <span>변경 없음</span>
    </div>
    <div class="migrate-stat">
      <strong><?= number_format($게임만원당) ?></strong>
      <span>게임냥/1만원</span>
    </div>
  </div>

  <? if (empty($마이그레이션목록)) { ?>
    <div class="empty-state"><div class="emoji">✅</div>마이그레이션할 판매중 상품이 없어요</div>
  <? } else { ?>
    <div class="migrate-table-wrap">
      <table class="migrate-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>상품</th>
            <th>판매자</th>
            <th>권면가</th>
            <th>현재가</th>
            <th>게임냥 환산가</th>
            <th>차이</th>
            <th>상태</th>
          </tr>
        </thead>
        <tbody>
          <? foreach ($마이그레이션목록 as $row) { ?>
          <tr>
            <td><?= (int)$row['id'] ?></td>
            <td><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($row['seller'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= number_format($row['face_value']) ?>원</td>
            <td class="price-old"><?= number_format($row['old_price']) ?>냥</td>
            <td class="<?= $row['changed'] ? 'price-new' : 'price-same' ?>"><?= number_format($row['new_price']) ?>냥</td>
            <td class="<?= $row['diff'] > 0 ? 'diff-up' : ($row['diff'] < 0 ? 'diff-down' : 'price-same') ?>">
              <? if ($row['diff'] === 0) { ?>
                동일
              <? } else { ?>
                <?= $row['diff'] > 0 ? '+' : '' ?><?= number_format($row['diff']) ?>
              <? } ?>
            </td>
            <td><?= $row['status'] === 'sale' ? '판매중' : '예약중' ?></td>
          </tr>
          <? } ?>
        </tbody>
      </table>
    </div>

    <button
      type="button"
      class="btn-migrate"
      id="btn-migrate"
      onclick="runMigration()"
      <?= $변경건수 < 1 ? 'disabled' : '' ?>
    ><?= $변경건수 < 1 ? '변경할 상품 없음' : $변경건수 . '건 가격 마이그레이션 실행' ?></button>
  <? } ?>

  <? } ?>

</div>

<div class="toast" id="toast"></div>

<? if ($코드없음) { shop_코드_게이트_출력(); } ?>

<script>
  const MY_CODE = <?= json_encode($code, JSON_UNESCAPED_UNICODE) ?>;
  const IS_ADMIN = <?= $관리자 ? 'true' : 'false' ?>;

  async function runMigration() {
    if (!IS_ADMIN || !MY_CODE) {
      showToast('관리자만 실행할 수 있습니다.');
      return;
    }
    if (!confirm('판매중·예약중 상품 가격을 게임냥 기준으로 일괄 변경할까요?\n이 작업은 되돌릴 수 없습니다.')) {
      return;
    }

    const btn = document.getElementById('btn-migrate');
    btn.disabled = true;
    btn.textContent = '마이그레이션 중...';

    const fd = new FormData();
    fd.append('code', MY_CODE);

    try {
      const res = await fetch('/shop/_migrate.php', { method: 'POST', body: fd });
      const data = await res.json();
      showToast(data.msg || (data.ok ? '완료' : '실패'));
      if (data.ok) {
        setTimeout(() => location.reload(), 1000);
      } else {
        btn.disabled = false;
        btn.textContent = '다시 실행';
      }
    } catch (e) {
      showToast('서버 오류');
      btn.disabled = false;
      btn.textContent = '다시 실행';
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
