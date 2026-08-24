<?php
/**
 * 홍보방 `.수령` (info2 전용)
 * 채굴량(mining_pending) 10냥 이상 → 본방냥(newpoint)
 *
 * 포함 전제: config.php 로드됨, $두자리닉넴·$status·$정보·$단위
 */

if (trim($status) !== '.수령') {
  return;
}

require_once __DIR__ . '/mining_config.inc.php';
require_once __DIR__ . '/mining_storage.inc.php';
require_once __DIR__ . '/mining_sync.inc.php';

$채굴 = mining_claim_to_newpoint($두자리닉넴);
if (!empty($채굴['ok']) && (float)($채굴['claimed'] ?? 0) > 0) {
  echo 전송('✅ ' . (string)($채굴['data'] ?? '채굴냥을 본방냥으로 수령했어요.'));
  exit;
}

$pending_fmt = mining_pending_live_fmt($두자리닉넴);
$pending = mining_pending_live_amount($두자리닉넴);
$min = (float)MINING_CHAT_CLAIM_MIN;

if ($pending > 0 && $pending + 1e-12 < $min) {
  echo 전송('❌ 채굴냥 ' . $pending_fmt . '냥 모음 · ' . mining_fmt_pending($min) . '냥 이상부터 `.수령` 가능해요.');
  exit;
}

echo 전송('❌ 수령할 채굴냥이 없어요.\n(' . mining_fmt_pending($min) . '냥 이상 · `.내무기` 해제 후 채굴)');
exit;
