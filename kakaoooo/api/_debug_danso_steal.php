<?php
/**
 * 단소 강탈 진단 — 브라우저/CLI에서 1회 확인 후 삭제
 * 예: /api/_debug_danso_steal.php?key=danso34&enhance=34
 */
require_once __DIR__ . '/_bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');
$key = (string)($_GET['key'] ?? '');
if ($key !== 'danso34') {
  http_response_code(403);
  echo "forbidden\n";
  exit;
}

$enhance = (int)($_GET['enhance'] ?? 34);
$band = (int)floor($enhance / 10);
if ($band > 10) {
  $band = 10;
}

$lines = [];
$lines[] = "enhance=+{$enhance} band={$band} pct=" . ($band * 0.0005) . "%";
$lines[] = "bcmath=" . (function_exists('bcmul') ? 'yes' : 'no');

if (function_exists('시세기준_실시간합계')) {
  $live = 시세기준_실시간합계();
  $lines[] = "시세기준_실시간합계.게임냥=" . ($live['게임냥'] ?? '?') . " (len=" . strlen((string)($live['게임냥'] ?? '')) . ")";
}
if (function_exists('시세기준_게임냥_문자열')) {
  $s = 시세기준_게임냥_문자열();
  $lines[] = "시세기준_게임냥_문자열={$s} (len=" . strlen($s) . ")";
}
if (function_exists('계급_총게임냥')) {
  $s = 계급_총게임냥();
  $lines[] = "계급_총게임냥={$s} (len=" . strlen($s) . ")";
}
if (function_exists('무기복구_전체게임냥_확보')) {
  $s = 무기복구_전체게임냥_확보();
  $lines[] = "무기복구_전체게임냥_확보={$s} (len=" . strlen($s) . ")";
}
if (function_exists('단소_강탈금액')) {
  $amt = 단소_강탈금액($enhance);
  $lines[] = "단소_강탈금액={$amt} (len=" . strlen((string)$amt) . ")";
}
// 기대값(문자열): ceil(total*band/200000)
if (function_exists('냥_금액_문자열곱') && function_exists('냥_문자열나눗셈올림') && function_exists('무기복구_전체게임냥_확보')) {
  $t = 냥_정수문자열(무기복구_전체게임냥_확보());
  $expect = 냥_문자열나눗셈올림(냥_금액_문자열곱($t, (string)$band), '200000');
  $lines[] = "기대_문자열계산={$expect} (len=" . strlen($expect) . ")";
}
if (function_exists('전체냥기준금액')) {
  $amt = 전체냥기준금액($band * 0.0005);
  $lines[] = "전체냥기준금액=" . (is_scalar($amt) ? $amt : json_encode($amt));
}

// raw SQL sample
$row = @db_select("
  SELECT
    CONCAT('N', CAST(COALESCE(SUM(GREATEST(CAST(IFNULL(point, 0) AS DECIMAL(65,0)), 0)), 0) AS CHAR)) AS as_decimal,
    CONCAT('N', CAST(COALESCE(SUM(IFNULL(point, 0)), 0) AS CHAR)) AS as_raw_sum,
    CONCAT('N', CAST(IFNULL((SELECT point FROM tb_member WHERE IFNULL(point,0)>0 ORDER BY point+0 DESC LIMIT 1), 0) AS CHAR)) AS top1_char
  FROM tb_member
");
if (is_array($row)) {
  foreach ($row as $k => $v) {
    $lines[] = "sql.{$k}={$v}";
    if (function_exists('냥_금액원문_정규화')) {
      $lines[] = "  ->정규화=" . 냥_금액원문_정규화($v);
    }
  }
}

// sci-notation parse self-test
if (function_exists('냥_금액원문_정규화')) {
  $lines[] = "selftest N1.23e+5 => " . 냥_금액원문_정규화('N1.23e+5') . " (expect 123000)";
  $lines[] = "selftest 1.5e+20 => " . 냥_금액원문_정규화('1.5e+20');
}

echo implode("\n", $lines) . "\n";
