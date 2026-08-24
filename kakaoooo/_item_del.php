<?php
include_once $_SERVER['DOCUMENT_ROOT']."/lib/_function.php";
include_once $_SERVER['DOCUMENT_ROOT']."/api/item_bag.inc.php";

$midx = (int)($midx ?? 0);
$item = trim((string)($item ?? ($itemname ?? '')));

// 구 API: idx(로우) — 레거시 호환
if ($midx < 1 && !empty($idx)) {
  $idx = (int)$idx;
  $row = db_select("SELECT midx, itemname, nick FROM tb_member_item WHERE idx = {$idx} LIMIT 1");
  if (!empty($row['midx'])) {
    $midx = (int)$row['midx'];
    $item = trim((string)($row['itemname'] ?? ''));
    if ($item !== '') {
      $nick = trim((string)($row['nick'] ?? ''));
      if ($nick === '') {
        $mem = db_select("SELECT name FROM tb_member WHERE idx = {$midx} LIMIT 1");
        $nick = trim((string)($mem['name'] ?? ''));
      }
      $r = item_bag_sub($midx, $nick, $item, 1);
      if (!empty($r['ok'])) {
        @db_query("UPDATE tb_member_item SET status = 1, usedate = NOW() WHERE idx = {$idx} LIMIT 1");
        echo 1;
        exit;
      }
    }
  }
  echo 0;
  exit;
}

if ($midx < 1 || $item === '') {
  echo 0;
  exit;
}

$mem = db_select("SELECT name FROM tb_member WHERE idx = {$midx} LIMIT 1");
$nick = trim((string)($mem['name'] ?? ''));
$r = item_bag_sub($midx, $nick, $item, 1);
echo !empty($r['ok']) ? 1 : 0;
