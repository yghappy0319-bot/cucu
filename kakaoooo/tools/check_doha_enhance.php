<?php
/**
 * 도하 +19→+20 탈취/역풍 이력 점검 (서버에서 실행)
 *   php /path/to/tools/check_doha_enhance.php
 *   php tools/check_doha_enhance.php 도하
 */
$nick = $argv[1] ?? '도하';
require __DIR__ . '/../lib/_function.php';

$nick_esc = addslashes($nick);

echo "=== {$nick} 현재 무기 ===\n";
$m = db_select("SELECT name, item, enhance FROM tb_member WHERE name = '{$nick_esc}' LIMIT 1");
if (!$m) {
    echo "회원 없음\n";
    exit(1);
}
echo "{$m['name']} · {$m['item']} +{$m['enhance']}\n\n";

echo "=== 도전모드 적중 집계 (주사위 성공 → 탈취/역풍) ===\n";
$sum = db_select("
  SELECT
    SUM(challenge_steal = 1) AS steal_ok,
    SUM(challenge_steal = 0) AS reverse_wind,
    COUNT(*) AS hit_total
  FROM tb_enhance_log
  WHERE nick = '{$nick_esc}'
    AND challenge_mode = 1
    AND result = 'success'
    AND challenge_steal IS NOT NULL
");
$steal = (int)($sum['steal_ok'] ?? 0);
$wind = (int)($sum['reverse_wind'] ?? 0);
$total = (int)($sum['hit_total'] ?? 0);
$pct = $total > 0 ? round(100 * $wind / $total, 1) : 0;
echo "탈취 {$steal} · 역풍 {$wind} · 합계 {$total} · 역풍비율 {$pct}% (설계 51%)\n\n";

echo "=== 최근 도전모드 로그 (최대 20) ===\n";
$rs = db_query("
  SELECT item, enhance_before, enhance_after, result,
         challenge_steal, challenge_target, dice_roll, dice_den,
         eunchong, channel, regdate
  FROM tb_enhance_log
  WHERE nick = '{$nick_esc}' AND challenge_mode = 1
  ORDER BY regdate DESC
  LIMIT 20
");
$i = 0;
while ($rs && $row = db_fetch($rs)) {
    $i++;
    $outcome = '-';
    if ($row['result'] === 'success' && $row['challenge_steal'] !== null) {
        $outcome = ((int)$row['challenge_steal'] === 1) ? '탈취' : '역풍';
    } elseif ($row['result'] !== 'success') {
        $outcome = '미적중';
    }
    $eun = !empty($row['eunchong']) ? '은총' : '-';
    echo sprintf(
        "%2d) %s  +%s→+%s  %s  주사위%s/%s  %s  %s  대상[%s]  %s\n",
        $i,
        $row['regdate'],
        $row['enhance_before'],
        $row['enhance_after'],
        $outcome,
        $row['dice_roll'],
        $row['dice_den'],
        $eun,
        $row['channel'],
        $row['challenge_target'] ?? '',
        $row['item']
    );
}
if ($i === 0) {
    echo "(도전모드 로그 없음)\n";
}

echo "\n=== 본방 역풍 알림 (tb_lotto_info, 최근 10) ===\n";
$rs2 = db_query("
  SELECT msg, regdate
  FROM tb_lotto_info
  WHERE item = '{$nick_esc}' AND msg LIKE '%탈취하려다%'
  ORDER BY regdate DESC
  LIMIT 10
");
$j = 0;
while ($rs2 && $row = db_fetch($rs2)) {
    $j++;
    echo "{$j}) {$row['regdate']}  {$row['msg']}\n";
}
if ($j === 0) {
    echo "(역풍 본방알림 없음)\n";
}
