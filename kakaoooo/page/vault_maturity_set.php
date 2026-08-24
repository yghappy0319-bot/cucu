<?php
/**
 * 개인금고_만기원금누적 임시 설정
 * URL: /page/vault_maturity_set.php?run=1
 * 기본값: 5해 1천경 = 510000000000000000000
 */
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/_chk.php';
if (is_file($_SERVER['DOCUMENT_ROOT'] . '/api/function.php')) {
  include_once $_SERVER['DOCUMENT_ROOT'] . '/api/function.php';
}

header('Content-Type: text/html; charset=utf-8');

// 5해 + 1000경
$목표 = '510000000000000000000';
if (isset($_GET['amt']) && preg_match('/^\d+$/', (string)$_GET['amt'])) {
  $목표 = ltrim((string)$_GET['amt'], '0') ?: '0';
}
$do = isset($_GET['run']) && (string)$_GET['run'] === '1';

if (function_exists('시세기준_컬럼_보장')) {
  시세기준_컬럼_보장();
} elseif (function_exists('gv_만기원금누적_컬럼보장')) {
  gv_만기원금누적_컬럼보장();
}

$이전행 = @db_select("
  SELECT CONCAT('N', CAST(IFNULL(`개인금고_만기원금누적`, 0) AS CHAR)) AS amt
  FROM config
  LIMIT 1
");
$이전Raw = (string)($이전행['amt'] ?? 'N0');
if (isset($이전Raw[0]) && ($이전Raw[0] === 'N' || $이전Raw[0] === 'n')) {
  $이전Raw = substr($이전Raw, 1);
}
$이전 = function_exists('냥_정수문자열') ? 냥_정수문자열($이전Raw) : (ltrim(preg_replace('/\D/', '', $이전Raw), '0') ?: '0');

$결과 = null;
if ($do) {
  $목표_sql = function_exists('냥_SQL정수') ? 냥_SQL정수($목표) : $목표;
  @db_query("UPDATE config SET `개인금고_만기원금누적` = {$목표_sql} LIMIT 1");
  if (function_exists('시세기준_스냅샷_캐시_초기화')) {
    시세기준_스냅샷_캐시_초기화();
  }
  $이후행 = @db_select("
    SELECT CONCAT('N', CAST(IFNULL(`개인금고_만기원금누적`, 0) AS CHAR)) AS amt
    FROM config
    LIMIT 1
  ");
  $이후Raw = (string)($이후행['amt'] ?? 'N0');
  if (isset($이후Raw[0]) && ($이후Raw[0] === 'N' || $이후Raw[0] === 'n')) {
    $이후Raw = substr($이후Raw, 1);
  }
  $결과 = function_exists('냥_정수문자열') ? 냥_정수문자열($이후Raw) : (ltrim(preg_replace('/\D/', '', $이후Raw), '0') ?: '0');
}

$표시 = function ($n) {
  if (function_exists('랭킹_게임냥표시')) {
    return 랭킹_게임냥표시($n, '');
  }
  return $n;
};
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <title>만기 원금 누적 설정</title>
  <style>
    body { font-family: sans-serif; max-width: 640px; margin: 2rem auto; line-height: 1.5; }
    code { background: #f3f3f3; padding: 0.1em 0.35em; }
    .ok { color: #0a7; }
  </style>
</head>
<body>
  <h1>개인금고_만기원금누적</h1>
  <p>목표: <strong>5해 1천경</strong> (<code><?= htmlspecialchars($목표, ENT_QUOTES, 'UTF-8') ?></code>)</p>
  <p>이전: <?= htmlspecialchars($표시($이전), ENT_QUOTES, 'UTF-8') ?> (<code><?= htmlspecialchars($이전, ENT_QUOTES, 'UTF-8') ?></code>)</p>
  <?php if ($결과 !== null) { ?>
    <p class="ok">적용 후: <?= htmlspecialchars($표시($결과), ENT_QUOTES, 'UTF-8') ?> (<code><?= htmlspecialchars($결과, ENT_QUOTES, 'UTF-8') ?></code>)</p>
  <?php } else { ?>
    <p><a href="?run=1">적용하기 (?run=1)</a></p>
  <?php } ?>
</body>
</html>
