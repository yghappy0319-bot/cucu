<?php
/**
 * 일회용: 아영 한도외(extra_uses) 초기화
 * 업로드 후 브라우저/CLI 1회 실행 → 삭제 권장
 *
 * CLI: php api/_once_reset_extra_uses.php
 * WEB: /api/_once_reset_extra_uses.php?key=extra-reset-20260814
 */
$key = 'extra-reset-20260814';
$isCli = (PHP_SAPI === 'cli');
if (!$isCli) {
  $got = (string)($_GET['key'] ?? '');
  if (!hash_equals($key, $got)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "forbidden\n";
    exit;
  }
}

require_once __DIR__ . '/_bootstrap.php';
if (!function_exists('db_query')) {
  fwrite($isCli ? STDERR : STDOUT, "db bootstrap failed\n");
  exit(1);
}

$nicks = ['아영'];
$lines = [];
$hasProtectExtra = false;
$col = @db_select("SHOW COLUMNS FROM tb_member LIKE 'protect_extra_uses'");
if (!empty($col)) {
  $hasProtectExtra = true;
}

foreach ($nicks as $nick) {
  $esc = addslashes($nick);
  $row = db_select("SELECT name, IFNULL(extra_uses, 0) AS extra_uses FROM tb_member WHERE name = '{$esc}' LIMIT 1");
  if (empty($row['name'])) {
    $lines[] = "❌ {$nick}: 회원 없음";
    continue;
  }
  $prev = (int)($row['extra_uses'] ?? 0);
  db_query("UPDATE tb_member SET extra_uses = 0, extra_reset_date = CURDATE() WHERE name = '{$esc}' LIMIT 1");
  if ($hasProtectExtra) {
    db_query("UPDATE tb_member SET protect_extra_uses = 0, protect_extra_reset_date = CURDATE() WHERE name = '{$esc}' LIMIT 1");
  }
  $lines[] = "✅ {$nick}: extra_uses {$prev} → 0";
}

$out = "한도외 초기화\n" . implode("\n", $lines) . "\n";
if (!$isCli) {
  header('Content-Type: text/plain; charset=utf-8');
}
echo $out;
