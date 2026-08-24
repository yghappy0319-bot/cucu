<?
/**
 * 기프티콘 마켓 판매가 일괄 갱신 (브라우저·크론)
 * 게임냥(price_nyang) 실시간 갱신 · 선매입 본방냥(price_newpoint)은 등록/수정 시점 고정 (미갱신)
 * 접속 시 판매중·예약중 전 상품을 실시간 가격으로 동기화 (수치 동일해도 price_updated_at 갱신)
 *
 * 브라우저: /shop/_auto_price.php
 * 크론 예: curl -s "https://도메인/shop/_auto_price.php?format=json"
 */
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/api/function.php';
if (!defined('SHOP_SKIP_MAINTENANCE_GATE')) {
    define('SHOP_SKIP_MAINTENANCE_GATE', true); // 가격 동기화 크론은 점검 중에도 동작
}
include_once __DIR__ . '/_shop.php';

$format = isset($_GET['format']) ? strtolower(trim($_GET['format'])) : 'html';
$result = shop_마이그레이션_실행();

if ($format === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok'      => true,
        'msg'     => "판매가 동기화 완료 · {$result['updated']}건 반영 · 겜냥 변경 {$result['updated_nyang']}건 · 본방냥 변경 {$result['updated_newpoint']}건",
        'result'  => $result,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Content-Type: text/html; charset=utf-8');
$changed = $result['changed_items'] ?? [];
$changed_np = $result['changed_items_newpoint'] ?? [];
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>마켓 판매가 자동 갱신</title>
  <link rel="stylesheet" href="/css/style.css">
  <?= shop_stylesheet_tag() ?>
  <style>
    .auto-price-card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 20px;
      box-shadow: var(--shadow);
      margin-bottom: 16px;
    }
    .auto-price-card h1 {
      margin: 0 0 8px;
      font-size: 1.25rem;
      color: var(--accent-dark);
    }
    .auto-price-card p {
      margin: 0 0 16px;
      color: var(--text-secondary);
      line-height: 1.6;
    }
    .auto-price-stats {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 10px;
      margin-bottom: 16px;
    }
    @media (min-width: 640px) {
      .auto-price-stats { grid-template-columns: repeat(4, 1fr); }
    }
    .auto-price-stat {
      background: #fff8fb;
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 12px;
      text-align: center;
    }
    .auto-price-stat strong {
      display: block;
      font-size: 1.05rem;
      color: var(--accent-dark);
      margin-bottom: 4px;
    }
    .auto-price-stat span {
      font-size: 0.78rem;
      color: var(--muted);
      font-weight: 600;
    }
    .auto-price-table-wrap {
      overflow-x: auto;
      border: 1px solid var(--border);
      border-radius: 12px;
    }
    .auto-price-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.86rem;
    }
    .auto-price-table th,
    .auto-price-table td {
      padding: 10px;
      border-bottom: 1px solid var(--border);
      text-align: left;
    }
    .auto-price-table th {
      background: #fff8fb;
      font-weight: 800;
      white-space: nowrap;
    }
    .auto-price-table tr:last-child td { border-bottom: none; }
    .auto-price-ok {
      background: #e8f5e9;
      border: 1px solid #81c784;
      border-radius: 12px;
      padding: 12px 14px;
      color: #2e7d32;
      font-weight: 700;
      margin-bottom: 16px;
    }
    .auto-price-hint {
      font-size: 0.84rem;
      color: var(--muted);
      line-height: 1.55;
    }
    .auto-price-hint code {
      background: #f5f5f5;
      padding: 2px 6px;
      border-radius: 6px;
    }
  </style>
</head>
<body>
<div class="shop-wrap narrow">
  <a href="/shop/" class="back-link">← 마켓으로</a>

  <div class="auto-price-card">
    <h1>📈 마켓 판매가 자동 갱신</h1>
    <p>게임냥 판매가만 실시간 총량으로 갱신합니다.<br>
      선매입 본방냥(<code>price_newpoint</code>)은 판매등록·수정 당시 금액으로 고정됩니다.<br>
      실행 시각 <?= htmlspecialchars($result['ran_at'], ENT_QUOTES, 'UTF-8') ?>
    </p>

    <div class="auto-price-ok">
      ✅ <?= (int)($result['updated'] ?? 0) ?>건 실시간 동기화 · 겜냥 변경 <?= (int)($result['updated_nyang'] ?? 0) ?>건 · 본방냥 변경 <?= (int)($result['updated_newpoint'] ?? 0) ?>건 · 대상 <?= (int)$result['total'] ?>건
    </div>

    <div class="auto-price-stats">
    <div class="auto-price-stat">
      <strong><?= number_format((float)($result['live_newpoint'] ?? 0)) ?></strong>
      <span>실시간 본방냥</span>
    </div>
    <div class="auto-price-stat">
      <strong><?= shop_냥_표시((int)($result['live_point'] ?? 0)) ?></strong>
      <span>실시간 게임냥</span>
    </div>
    <div class="auto-price-stat">
      <strong><?= number_format((float)($result['snapshot_newpoint'] ?? 0)) ?></strong>
      <span>스냅샷 본방냥</span>
    </div>
    <div class="auto-price-stat">
      <strong><?= number_format((int)($result['newpoint_per_10000'] ?? 0)) ?></strong>
      <span>선매입/1만원</span>
    </div>
    <div class="auto-price-stat">
      <strong><?= number_format((int)($result['realtime_newpoint_per_10000'] ?? 0)) ?></strong>
      <span>실시간 본방/1만원</span>
    </div>
    <div class="auto-price-stat">
      <strong><?= shop_냥_표시((int)($result['swap_per_newpoint'] ?? 0)) ?></strong>
      <span>스왑(1보유당)</span>
    </div>
    <div class="auto-price-stat">
      <strong><?= shop_냥_표시((int)$result['nyang_per_10000']) ?></strong>
      <span>게임냥/1만원</span>
    </div>
    <div class="auto-price-stat">
      <strong><?= number_format((int)($result['updated'] ?? 0)) ?></strong>
      <span>동기화 반영</span>
    </div>
    <div class="auto-price-stat">
      <strong><?= number_format((int)($result['updated_nyang'] ?? 0)) ?></strong>
      <span>겜냥 변경</span>
    </div>
    <div class="auto-price-stat">
      <strong><?= number_format((int)($result['updated_newpoint'] ?? 0)) ?></strong>
      <span>본방냥 변경</span>
    </div>
    </div>

    <p class="auto-price-hint">
      <strong>게임냥(판매등록)</strong>: 1만원 = 실시간 본방 × <strong>고정 <?= shop_market_tax_표시(shop_판매등록_market_tax()) ?></strong> × 스왑 = <?= shop_냥_표시((int)$result['nyang_per_10000']) ?>게임냥 (개인 market_tax 미적용)<br>
      <strong>선매입 본방냥</strong>: 판매등록·수정 당시 <code>price_newpoint</code> 고정 (자동시세에서 변경하지 않음)<br>
      크론 등록 예: <code>curl -s "https://도메인/shop/_auto_price.php?format=json"</code>
    </p>
  </div>

  <? if (!empty($changed)) { ?>
  <div class="auto-price-card">
    <h1>변경된 상품 · 게임냥</h1>
    <div class="auto-price-table-wrap">
      <table class="auto-price-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>상품</th>
            <th>판매자</th>
            <th>권면가</th>
            <th>이전가</th>
            <th>갱신가</th>
          </tr>
        </thead>
        <tbody>
          <? foreach ($changed as $row) { ?>
          <tr>
            <td><?= (int)$row['id'] ?></td>
            <td><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($row['seller'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= number_format((int)$row['face_value']) ?>원</td>
            <td><?= number_format((int)$row['old_price']) ?>냥</td>
            <td><strong><?= number_format((int)$row['new_price']) ?>냥</strong></td>
          </tr>
          <? } ?>
        </tbody>
      </table>
    </div>
  </div>
  <? } ?>

  <? if (!empty($changed_np)) { ?>
  <div class="auto-price-card">
    <h1>변경된 상품 · 본방냥</h1>
    <div class="auto-price-table-wrap">
      <table class="auto-price-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>상품</th>
            <th>판매자</th>
            <th>권면가</th>
            <th>이전 본방냥</th>
            <th>갱신 본방냥</th>
          </tr>
        </thead>
        <tbody>
          <? foreach ($changed_np as $row) { ?>
          <tr>
            <td><?= (int)$row['id'] ?></td>
            <td><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($row['seller'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= number_format((int)$row['face_value']) ?>원</td>
            <td><?= shop_매입_지급_표시((int)$row['old_price']) ?></td>
            <td><strong><?= shop_매입_지급_표시((int)$row['new_price']) ?></strong></td>
          </tr>
          <? } ?>
        </tbody>
      </table>
    </div>
  </div>
  <? } ?>

  <? if (empty($changed) && empty($changed_np)) { if ((int)$result['total'] < 1) { ?>
  <div class="empty-state"><div class="emoji">✅</div>갱신할 판매중 상품이 없습니다</div>
  <? } else { ?>
  <div class="empty-state"><div class="emoji">✅</div><?= (int)($result['updated'] ?? 0) ?>건 모두 실시간 기준으로 동기화되었습니다 (가격 수치 변경 없음 · 갱신 시각 갱신됨)</div>
  <? } } ?>
</div>
</body>
</html>
