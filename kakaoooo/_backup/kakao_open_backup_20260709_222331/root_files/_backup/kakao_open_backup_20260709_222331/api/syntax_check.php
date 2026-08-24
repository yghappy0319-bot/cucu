<?php
/**
 * 서버 PHP 문법·되돌리기 잔여 오류 점검 (브라우저/curl로 호출)
 * 예) https://your-domain/api/syntax_check.php
 */
header('Content-Type: text/plain; charset=UTF-8');

$root = __DIR__;
$targets = [
  'info1.php',
  'info2.php',
  'info3.php',
  'function.php',
  '_mutual.php',
  '_bootstrap.php',
  'config.php',
  'game/lotto_chat.inc.php',
  'game/lotto_purchase.inc.php',
  'game/match_predict_chat.inc.php',
  'game/odd_even_mutual.inc.php',
];

$brokenPatterns = [
  '단위_괄호_밖_개행' => '/\{\$단위\}"\)\s*\\\\n/u',
  '랭킹_number_format' => '/랭킹_number_format/u',
  'int_문자열_깨짐' => '/\(int\)\s*\.\s*"/u',
];

echo "=== PHP syntax_check ===\n";
echo 'PHP ' . PHP_VERSION . "\n";
echo 'Time ' . date('Y-m-d H:i:s') . "\n\n";

foreach ($targets as $rel) {
  $path = $root . '/' . $rel;
  echo "[{$rel}]\n";
  if (!is_file($path)) {
    echo "  MISSING\n\n";
    continue;
  }

  $out = [];
  $code = 0;
  exec('php -l ' . escapeshellarg($path) . ' 2>&1', $out, $code);
  echo '  ' . ($code === 0 ? 'OK' : 'FAIL') . ': ' . implode("\n  ", $out) . "\n";

  $text = file_get_contents($path);
  foreach ($brokenPatterns as $label => $regex) {
    if (preg_match($regex, $text, $m, PREG_OFFSET_CAPTURE)) {
      $line = substr_count(substr($text, 0, $m[0][1]), "\n") + 1;
      echo "  PATTERN {$label} at line {$line}\n";
    }
  }
  echo "\n";
}

echo "=== bootstrap load test ===\n";
try {
  ob_start();
  require_once $root . '/_bootstrap.php';
  ob_end_clean();
  echo "OK: _bootstrap.php loaded\n";
  echo 'nick_파라미터: ' . (function_exists('nick_파라미터') ? 'yes' : 'no') . "\n";
  echo 'newpoint표시: ' . (function_exists('newpoint표시') ? 'yes' : 'no') . "\n";
  echo '랭킹_게임냥표시: ' . (function_exists('랭킹_게임냥표시') ? 'yes' : 'no') . "\n";
} catch (Throwable $e) {
  ob_end_clean();
  echo 'FAIL: ' . $e->getMessage() . "\n";
}
