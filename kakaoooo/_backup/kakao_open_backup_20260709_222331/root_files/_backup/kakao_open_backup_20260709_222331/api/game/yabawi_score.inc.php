<?php
/**
 * 야바위 진행 현황 `.점수` / `．점수` (본방·홍보방 공통, tb_run_member 조회만)
 * 포함 전제: config.php 로드됨, $status·$설정 사용 가능
 */

if (strpos($status, '.점수') === false && strpos($status, '．점수') === false) {
  return;
}

if (!empty($설정['야바위강제마감'])) {
  $nowHM = date('H:i');
  $nowTs = strtotime(date('Y-m-d') . ' ' . $nowHM);
  $closeTs = strtotime(date('Y-m-d') . ' ' . $설정['야바위강제마감']);

  if ($nowTs !== false && $closeTs !== false && $nowTs > $closeTs) {
    db_query("UPDATE tb_run_member SET cnt = 3, sort = 3, regdate = NOW() WHERE status = 1 AND sort < 3");
  }
}

db_query("UPDATE tb_run_member SET cnt = 3, sort = 3, regdate = NOW() WHERE status = 1 AND sort < 3 AND 신청일시 IS NOT NULL AND 신청일시 <= NOW()");

$sql = "select * from tb_run_member order by cnt desc, regdate asc ";
$result = db_query($sql);
$msg = "야바위 참가자\n\n";

$야바위강제마감시각 = '';
if (!empty($설정['야바위강제마감'])) {
  $야바위강제마감시각 = trim((string)$설정['야바위강제마감']);
}

$point = 0;
for ($i = 0; $row = db_fetch($result); $i++) {
  $point += $row['point'];
  $메달 = ($i === 0 && (int)($row['cnt'] ?? 0) > 0) ? '🥇' : '';
  $msg .= ($row['sort'] == 3 ? '마감) ' : $row['sort'] . '회 ') . $row['name'] . ' ' . $row['cnt'] . '점' . $메달 . "\n";

  if ((int)($row['status'] ?? 0) === 1 && (int)($row['sort'] ?? 0) < 3) {
    $미입력안내 = '  └ ⏱ ';
    $dl_raw = isset($row['신청일시']) ? trim((string)$row['신청일시']) : '';
    $deadlineTs = ($dl_raw !== '' && $dl_raw !== '0000-00-00 00:00:00') ? strtotime($dl_raw) : false;
    if ($deadlineTs) {
      $미입력안내 .= '주사위 마감 ' . date('n/j H:i', $deadlineTs);
    } else {
      $마감기준 = isset($설정['야바위게임시작']) ? trim((string)$설정['야바위게임시작']) : '';
      $마감기준Ts = ($마감기준 !== '') ? strtotime($마감기준) : false;
      if ($마감기준Ts) {
        $미입력안내 .= '+30 ' . date('n/j H:i', $마감기준Ts + 1800);
      } else {
        $미입력안내 .= '+30분';
      }
    }
    if ($야바위강제마감시각 !== '') {
      $미입력안내 .= ' · 강제 ' . $야바위강제마감시각;
    }
    $미입력안내 .= ' 이후 .점수시 종료';
    $msg .= $미입력안내 . "\n";
  }
}

$첫신청자 = db_select("select * from tb_run_member order by idx asc limit 1");
if (!empty($첫신청자['idx'])) {
  $점수단위 = isset($단위) ? (string)$단위 : '냥';
  $msg .= "\n참여금 " . 야바위_금액표시((int)$첫신청자['point'], $점수단위);
}

$마감자 = db_query("select * from tb_run_member order by idx asc limit 2");
$names = [];
while ($row = db_fetch($마감자)) {
  $names[] = $row['name'];
}
$msg .= "\n마감가능자 " . implode(', ', $names);
$x2확률 = min(100, max(0, (int)$i));
$msg .= "\n현재 x2확률 {$x2확률}%";
$msg .= "\n야바위 강제마감: " . ($설정['야바위강제마감'] ?? '');
$msg .= "\n\n➡️ 참여방법\nㅅㅊ 신청 · `ㄷㄹ` 1회(3연속)";

echo 전송($msg);
exit;
