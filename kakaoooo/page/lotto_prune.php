<?php
/**
 * 로또 오래된 회차 정리 (관리용)
 * URL: /page/lotto_prune.php?run=1
 * 진행 회차 기준 10회차 이전 tb_game_lotto 삭제
 */
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/_chk.php';
if (is_file($_SERVER['DOCUMENT_ROOT'] . '/api/game/lotto_purchase.inc.php')) {
  include_once $_SERVER['DOCUMENT_ROOT'] . '/api/game/lotto_purchase.inc.php';
}

header('Content-Type: text/html; charset=utf-8');

$보관 = isset($_GET['keep']) ? max(1, (int)$_GET['keep']) : 10;
$do = isset($_GET['run']) && (string)$_GET['run'] === '1';

$진행 = function_exists('로또_진행회차') ? 로또_진행회차() : 0;
$삭제기준 = $진행 - $보관;
$min행 = @db_select('SELECT IFNULL(MIN(drow), 0) AS m FROM tb_game_lotto');
$max행 = @db_select('SELECT IFNULL(MAX(drow), 0) AS m FROM tb_game_lotto');
$전체 = @db_select('SELECT COUNT(*) AS c FROM tb_game_lotto');
$오래 = ($삭제기준 > 0)
  ? @db_select("SELECT COUNT(*) AS c FROM tb_game_lotto WHERE drow < {$삭제기준}")
  : ['c' => 0];

$result = null;
if ($do && function_exists('로또_오래된회차_정리')) {
  $result = 로또_오래된회차_정리(0, $보관);
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <title>로또 오래된 회차 정리</title>
  <style>
    body { font-family: sans-serif; max-width: 640px; margin: 2rem auto; line-height: 1.5; }
    code { background: #f3f3f3; padding: 0.1em 0.35em; }
    .ok { color: #0a7; }
  </style>
</head>
<body>
  <h1>로또 오래된 회차 정리</h1>
  <p>진행 회차: <strong><?= (int)$진행 ?></strong> · 보관 <?= (int)$보관 ?>회차 · 삭제 기준 <code>drow &lt; <?= (int)$삭제기준 ?></code></p>
  <ul>
    <li>tb_game_lotto min/max: <?= (int)($min행['m'] ?? 0) ?> ~ <?= (int)($max행['m'] ?? 0) ?></li>
    <li>전체 행: <?= (int)($전체['c'] ?? 0) ?></li>
    <li>삭제 대상: <?= (int)($오래['c'] ?? 0) ?></li>
  </ul>
  <?php if ($result): ?>
    <p class="ok">삭제 완료: <?= (int)$result['삭제건수'] ?>건 (기준회차 <?= (int)$result['기준회차'] ?>)</p>
  <?php else: ?>
    <p><a href="?run=1&amp;keep=<?= (int)$보관 ?>">지금 삭제 실행 (?run=1)</a></p>
  <?php endif; ?>
</body>
</html>
