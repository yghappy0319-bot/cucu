<?php
/**
 * 은총조각 10개당 은총 1개 교환 (홍보방·관리방)
 * 예) .은총교환 · .은총교환 3
 */

$은총교환입력 = trim((string)$status);
if (!preg_match('/^\.은총교환(?:\s+(\d+))?\s*$/u', $은총교환입력, $은총교환m)) {
  return;
}

require_once __DIR__ . '/mining_config.inc.php';
require_once __DIR__ . '/mining_storage.inc.php';
require_once __DIR__ . '/mining_ore.inc.php';
if (is_file(__DIR__ . '/../item_bag_enhance.inc.php')) {
  require_once __DIR__ . '/../item_bag_enhance.inc.php';
}

$want = null;
if (isset($은총교환m[1]) && $은총교환m[1] !== '') {
  $want = max(1, (int)$은총교환m[1]);
}

$결과 = mining_ore_exchange_to_eunchong($두자리닉넴, $want);
echo 전송((string)($결과['msg'] ?? '❌ 교환에 실패했어요.'));
exit;
