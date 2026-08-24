<?php
/**
 * 선물 매수가 1억 고정 — 코드(아이템주식_고정매수가) + DB 목록 조건 보정
 *
 * 미리보기: /page/item_gift_price_fix.php
 * 적용:     /page/item_gift_price_fix.php?run=1
 */
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/api/function.php';
if (is_file($_SERVER['DOCUMENT_ROOT'] . '/api/item_stock_market.inc.php')) {
  include_once $_SERVER['DOCUMENT_ROOT'] . '/api/item_stock_market.inc.php';
}

header('Content-Type: text/html; charset=utf-8');

$do = isset($_GET['run']) && (string)$_GET['run'] === '1';
$목표 = '100000000';
$로그 = [];

$row = @db_select("
  SELECT sname, CAST(buy AS CHAR) AS buy, CAST(percent AS CHAR) AS percent, buystatus
  FROM tb_item
  WHERE sname = '선물'
  LIMIT 1
");

if (empty($row['sname'])) {
  $로그[] = 'tb_item에 선물이 없습니다.';
} else {
  $로그[] = '현재 percent=' . ($row['percent'] ?? '?')
    . ' · buystatus=' . (int)($row['buystatus'] ?? -1)
    . ' · buy컬럼=' . ($row['buy'] ?? '0');

  if ($do) {
    // 거래소 목록 조건: buystatus=0 AND percent>0 (가격은 코드에서 1억 고정)
    $ok = @db_query("
      UPDATE tb_item
      SET buystatus = 0,
          percent = IF(percent > 0, percent, 0.01)
      WHERE sname = '선물'
      LIMIT 1
    ");
    $로그[] = $ok ? 'DB 보정 완료 (buystatus=0, percent 최소 0.01 보장)' : 'DB UPDATE 실패';
    $row = @db_select("
      SELECT sname, CAST(buy AS CHAR) AS buy, CAST(percent AS CHAR) AS percent, buystatus
      FROM tb_item
      WHERE sname = '선물'
      LIMIT 1
    ");
  }

  if (function_exists('아이템주식_매수단가') && !empty($row)) {
    $buy = 아이템주식_매수단가($row);
    $buyable = ($buy !== '0');
    $로그[] = '계산 매수가=' . $buy . ($buyable ? ' (구매가능)' : ' (매수불가)');
    if (function_exists('아이템주식_고정매수가') && 아이템주식_고정매수가('선물') === $목표) {
      $로그[] = '코드 고정가: 선물 = 1억';
    } else {
      $로그[] = '주의: api/item_stock_market.inc.php 의 고정1억 코드가 아직 서버에 없을 수 있습니다.';
    }
  }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <title>선물 매수 1억 보정</title>
  <style>
    body { font-family: sans-serif; max-width: 720px; margin: 2rem auto; line-height: 1.5; padding: 0 16px; }
    code { background: #f3f3f3; padding: 0.1em 0.35em; }
    .ok { color: #0a7; }
    .btn { display: inline-block; margin: 8px 8px 0 0; padding: 10px 16px; background: #2563eb; color: #fff; text-decoration: none; border-radius: 8px; }
  </style>
</head>
<body>
  <h1>선물 매수 1억 보정</h1>
  <p>거래소 매수가를 <strong>1억</strong>으로 고정합니다. (percent 억절사로 매수불가 되던 문제)</p>
  <ul>
    <?php foreach ($로그 as $line) { ?>
      <li><?= htmlspecialchars((string)$line, ENT_QUOTES, 'UTF-8') ?></li>
    <?php } ?>
  </ul>
  <?php if (!$do) { ?>
    <a class="btn" href="?run=1">DB 보정 실행 (buystatus·percent)</a>
  <?php } else { ?>
    <p class="ok">완료. 거래소에서 선물 구매 버튼을 확인해 주세요.</p>
  <?php } ?>
</body>
</html>
