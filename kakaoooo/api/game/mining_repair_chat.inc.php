<?php
/**
 * `.채굴수리` / `.채굴수리 N` — 홍보방(info2)
 */

$입력 = trim((string)$status);
if (!preg_match('/^\.채굴수리(?:\s+(\d+))?\s*$/u', $입력, $m)) {
  return;
}

require_once __DIR__ . '/mining_config.inc.php';
require_once __DIR__ . '/mining_storage.inc.php';
require_once __DIR__ . '/mining_sync.inc.php';
require_once __DIR__ . '/mining_durability.inc.php';

$points = isset($m[1]) && $m[1] !== '' ? (int)$m[1] : (int)MINING_DURABILITY_REPAIR_STEP;

$result = mining_repair_execute($두자리닉넴, $points);
if (!empty($result['ok'])) {
  echo 전송((string)($result['data'] ?? '수리 완료'));
  exit;
}

echo 전송('❌ ' . (string)($result['data'] ?? '수리에 실패했어요.'));
exit;
