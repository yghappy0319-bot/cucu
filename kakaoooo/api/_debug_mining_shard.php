<?php
/**
 * 은총조각 수령 진단 — 확인 후 삭제
 * 예: /api/_debug_mining_shard.php?key=shardchk&nick=우서
 */
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/game/mining_ore.inc.php';

header('Content-Type: text/plain; charset=utf-8');
if ((string)($_GET['key'] ?? '') !== 'shardchk') {
  http_response_code(403);
  echo "forbidden\n";
  exit;
}

$nick = trim((string)($_GET['nick'] ?? '우서'));
$nick_esc = addslashes($nick);
$lines = [];
$lines[] = "nick={$nick}";
$lines[] = "now=" . date('Y-m-d H:i:s');
$lines[] = "MAX_SHARDS=" . (defined('MINING_ORE_EUNCHONG_SHARDS') ? (int)MINING_ORE_EUNCHONG_SHARDS : '?');

$tbl = defined('MINING_TABLE') ? MINING_TABLE : 'tb_member_mining';
$m = @db_select("
  SELECT nick,
         IFNULL(mining_eunchong_shard, 0) AS shard,
         CAST(IFNULL(mining_pending, 0) AS CHAR) AS pending,
         mining_sync_at
  FROM `{$tbl}`
  WHERE nick = '{$nick_esc}'
  LIMIT 1
");
if (!$m) {
  $lines[] = "member_mining=ROW_NOT_FOUND";
} else {
  $lines[] = "shard={$m['shard']} pending={$m['pending']} sync_at={$m['mining_sync_at']}";
}

$lines[] = "";
$lines[] = "=== tb_mining_ore_find (은총조각 최근 20) ===";
$rs = @db_query("
  SELECT idx, ore_key, qty, status, found_at, expire_at, claimed_at,
         TIMESTAMPDIFF(SECOND, NOW(), expire_at) AS expire_in_sec
  FROM tb_mining_ore_find
  WHERE nick = '{$nick_esc}'
    AND ore_key = 'eunchong_shard'
  ORDER BY idx DESC
  LIMIT 20
");
if ($rs) {
  while ($r = db_fetch($rs)) {
    $lines[] = sprintf(
      "#%s %s qty=%s found=%s expire=%s claimed=%s expire_in=%ss",
      $r['idx'],
      $r['status'],
      $r['qty'],
      $r['found_at'],
      $r['expire_at'],
      $r['claimed_at'] ?? '-',
      $r['expire_in_sec']
    );
  }
} else {
  $lines[] = "(query fail)";
}

$lines[] = "";
$lines[] = "=== tb_mining_ore_log (은총조각 최근 30) ===";
$rs2 = @db_query("
  SELECT idx, find_idx, event, qty, shard_after, memo, regdate
  FROM tb_mining_ore_log
  WHERE nick = '{$nick_esc}'
    AND ore_key = 'eunchong_shard'
  ORDER BY idx DESC
  LIMIT 30
");
if ($rs2) {
  while ($r = db_fetch($rs2)) {
    $lines[] = sprintf(
      "#%s find=%s %s qty=%s shard_after=%s memo=%s at=%s",
      $r['idx'],
      $r['find_idx'] ?? '-',
      $r['event'],
      $r['qty'],
      $r['shard_after'] ?? '-',
      $r['memo'] ?? '',
      $r['regdate']
    );
  }
}

$lines[] = "";
$lines[] = "=== 해석 ===";
$lines[] = "- find=pending + expire_in>0 : 아직 터치 가능 (수령 안 된 상태)";
$lines[] = "- find=claimed + log claimed 있음 : 수령 처리됨 (조각 수는 shard_after / 현재 shard)";
$lines[] = "- find=expired : 1시간 지나 소멸";
$lines[] = "- claimed 인데 shard 안 늘면: 이미 MAX(500) 가득 → 수령은 되나 조각 +0";

echo implode("\n", $lines) . "\n";
