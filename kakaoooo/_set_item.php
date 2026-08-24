<?php
include_once $_SERVER['DOCUMENT_ROOT']."/lib/_function.php";
include_once $_SERVER['DOCUMENT_ROOT']."/api/item_bag.inc.php";

if (!is_array($midx ?? null) || $midx === []) {
  echo 0;
  exit;
}

$item = trim((string)($item ?? ''));
$cnts = (int)($cnts ?? 0);
if ($item === '' || $cnts < 1) {
  echo 0;
  exit;
}

if (function_exists('item_bag_ensure_schema')) {
  item_bag_ensure_schema();
}

$ok = true;
foreach ($midx as $mid) {
  $mid = (int)$mid;
  if ($mid < 1) {
    continue;
  }
  $mem = db_select("SELECT name FROM tb_member WHERE idx = {$mid} LIMIT 1");
  $nick = trim((string)($mem['name'] ?? ''));
  if ($nick === '') {
    $ok = false;
    continue;
  }
  $r = item_bag_add($mid, $nick, $item, $cnts);
  if (empty($r['ok'])) {
    $ok = false;
  }
}

echo $ok ? 1 : 0;
