<?php
// 마법 .보호 1회 (info2.php · enchant 웹 공용)
// 필요 변수: $두자리닉넴, $정보, $내무기, $내강화, $단위, $쿨타임, $대상닉, $요청보호횟수

$보호입력대상 = trim((string)($대상닉 ?? ''));
$요청보호횟수 = isset($요청보호횟수) ? max(1, (int)$요청보호횟수) : 1;
$닉만지정 = false;

if ($보호입력대상 === '') {
  $랜덤행 = db_select("SELECT name FROM tb_member WHERE status = 0 ORDER BY RAND() LIMIT 1");
  $대상닉 = $랜덤행 ? trim($랜덤행['name'] ?? '') : '';
  if ($대상닉 === '') {
    시전_채팅응답('❌ 보호할 대상 회원이 없어요.');
  }
} else {
  $대상닉 = $보호입력대상;
}

$내무기 = trim($정보['item'] ?? '');
$내강화 = (int)($정보['enhance'] ?? 0);
if ($내무기 !== '🪄마법' && $내무기 !== '🪄 마법') {
  시전_채팅응답('❌ 보호 부여는 마법 무기를 보유한 경우 가능');
}

$시전자_esc = addslashes($두자리닉넴);
$dur_row = db_select("SELECT durability FROM tb_member WHERE name = '{$시전자_esc}' LIMIT 1");
$durability_raw = $dur_row['durability'] ?? null;
$현재내구도 = ($durability_raw !== null) ? (int)$durability_raw : null;

if ($닉만지정 && $내강화 >= 15 && $요청보호횟수 === 1) {
  $요청보호횟수 = (mt_rand(1, 100) <= 30) ? 2 : 1;
}
if ($내강화 >= 10 && $현재내구도 !== null) {
  if ($현재내구도 <= 0) {
    시전_채팅응답("{$내무기} +{$내강화} 보호 사용!\n\n❌ 내구도 0 사용불가.");
  }
  if ($현재내구도 < $요청보호횟수) {
    시전_채팅응답("{$내무기} +{$내강화} 보호 사용!\n\n❌ 내구도 부족. (필요 {$요청보호횟수} · 현재 {$현재내구도})");
  }
}

$보호쿨타임 = 3;
$보호한도 = function_exists('무기_시전한도_3시간') ? 무기_시전한도_3시간($내무기, (int)$내강화) : 0;
if ($내강화 < 1 || $보호한도 <= 0) {
  시전_채팅응답('❌ 마법 1강 이상부터 보호 부여 가능');
}

$자숙위반 = function_exists('자숙_시전보호위반_적용') ? 자숙_시전보호위반_적용($두자리닉넴, '보호', $단위) : ['notice' => ''];
$자숙위반안내 = (string)($자숙위반['notice'] ?? '');
$사용횟수행 = db_select("SELECT protect_used, protect_reset_date, point, IFNULL(extra_uses, 0) AS extra_uses, extra_reset_date FROM tb_member WHERE name = '{$시전자_esc}' LIMIT 1");
$구간시작 = $사용횟수행['protect_reset_date'] ?? null;
$지금 = time();
if ($구간시작 === null || $구간시작 === '' || $지금 >= strtotime($구간시작) + ($보호쿨타임 * 3600)) {
  $구간시작 = date('Y-m-d H:i:s', $지금);
  db_query("UPDATE tb_member SET protect_used = 0, protect_reset_date = '{$구간시작}' WHERE name = '{$시전자_esc}'");
  $사용횟수 = 0;
} else {
  $사용횟수 = (int)($사용횟수행['protect_used'] ?? 0);
}

$과다결제함 = false;
$과다비용 = 0;
$보유냥 = (int)($사용횟수행['point'] ?? 0);
$오늘 = date('Y-m-d');
$리셋일 = isset($사용횟수행['extra_reset_date']) ? trim($사용횟수행['extra_reset_date']) : '';
if ($리셋일 === '' || $리셋일 < $오늘) {
  db_query("UPDATE tb_member SET extra_uses = 0, extra_reset_date = CURDATE() WHERE name = '{$시전자_esc}'");
  $과다횟수 = 0;
} else {
  $과다횟수 = (int)($사용횟수행['extra_uses'] ?? 0);
}

$남은무료한도 = max(0, $보호한도 - $사용횟수);
$필요무료 = min($요청보호횟수, $남은무료한도);
$필요과다 = $요청보호횟수 - $필요무료;
$한도외 = 무기_한도외_상태($내강화, $과다횟수);
$한도외잔여문구 = 무기_한도외_잔여문구($한도외);

if ($필요과다 > 0) {
  if (empty($한도외['allow_pay'])) {
    $대상_esc = addslashes($대상닉);
    $대상정보 = db_select("SELECT protect FROM tb_member WHERE name = '{$대상_esc}' LIMIT 1");
    $대상보호 = (int)($대상정보['protect'] ?? 0);
    $다음리셋 = strtotime($구간시작) + ($보호쿨타임 * 3600);
    $남은분 = (int)ceil(($다음리셋 - $지금) / 60);
    if (!empty($한도외['exhausted'])) {
      시전_채팅응답("❌ {$대상닉} 보호 {$대상보호} 한도 소진.\n오늘 한도 외 ".(int)$한도외['max']."회 소진(자정 리셋).\n다음 {$남은분}분 후 무료 한도 리셋.");
    }
    시전_채팅응답("❌ {$대상닉} 보호 {$대상보호} 한도 소진.\n한도 외 사용은 1강부터 가능.\n다음 {$남은분}분 후 무료 한도 리셋.");
  }
  if (empty($한도외['unlimited']) && $필요과다 > (int)($한도외['remain'] ?? 0)) {
    $대상_esc = addslashes($대상닉);
    $대상정보 = db_select("SELECT protect FROM tb_member WHERE name = '{$대상_esc}' LIMIT 1");
    $대상보호 = (int)($대상정보['protect'] ?? 0);
    $다음리셋 = strtotime($구간시작) + ($보호쿨타임 * 3600);
    $남은분 = (int)ceil(($다음리셋 - $지금) / 60);
    시전_채팅응답("❌ {$대상닉} 보호 {$대상보호} 한도 소진.\n오늘 한도 외 최대 ".(int)$한도외['max']."회 중 이미 ".(int)$과다횟수."회 사용, 추가로 {$필요과다}회는 불가능해요.\n다음 {$남은분}분 후 무료 한도 리셋.");
  }
  if ($필요과다 > 0) {
    $총과다비용 = 무기_한도외_시전총비용($과다횟수, $필요과다);
    if ($보유냥 < $총과다비용) {
      $대상_esc = addslashes($대상닉);
      $대상정보 = db_select("SELECT protect FROM tb_member WHERE name = '{$대상_esc}' LIMIT 1");
      $대상보호 = (int)($대상정보['protect'] ?? 0);
      $다음리셋 = strtotime($구간시작) + ($보호쿨타임 * 3600);
      $남은분 = (int)ceil(($다음리셋 - $지금) / 60);
      시전_채팅응답("❌ {$대상닉} 보호 {$대상보호} 한도 소진.\n한도 외 {$필요과다}회 사용 시 총 ".냥축약표시($총과다비용)." 필요 (보유 ".냥축약표시($보유냥)."){$한도외잔여문구}.\n다음 {$남은분}분 후 무료 한도 리셋.");
    }
    db_query("UPDATE tb_member SET point = point - {$총과다비용}, extra_uses = IFNULL(extra_uses, 0) + {$필요과다} WHERE name = '{$시전자_esc}'");
    $과다비용 = $총과다비용;
    $과다결제함 = true;
    $과다횟수 += $필요과다;
  }
} elseif ($요청보호횟수 > 0 && $남은무료한도 <= 0) {
  $대상_esc = addslashes($대상닉);
  $대상정보 = db_select("SELECT protect FROM tb_member WHERE name = '{$대상_esc}' LIMIT 1");
  $대상보호 = (int)($대상정보['protect'] ?? 0);
  $다음리셋 = strtotime($구간시작) + ($보호쿨타임 * 3600);
  $남은분 = (int)ceil(($다음리셋 - $지금) / 60);
  시전_채팅응답("❌ {$대상닉} 보호 {$대상보호} 한도 소진.\n다음 {$남은분}분 후 무료 한도 리셋.");
}

$대상_esc = addslashes($대상닉);
$멤버 = db_select("SELECT idx, protect FROM tb_member WHERE name = '{$대상_esc}' LIMIT 1");
if (empty($멤버['idx'])) {
  시전_채팅응답("❌ {$대상닉} 회원을 찾을 수 없어요.");
}
$새보호 = (int)($멤버['protect'] ?? 0) + $요청보호횟수;
db_query("UPDATE tb_member SET protect = {$새보호} WHERE name = '{$대상_esc}'");
if ($내강화 >= 10 && $현재내구도 !== null) {
  db_query("UPDATE tb_member SET durability = GREATEST(IFNULL(durability, 0) - {$요청보호횟수}, 0) WHERE name = '{$시전자_esc}'");
}
if ($필요무료 > 0) {
  db_query("UPDATE tb_member SET protect_used = protect_used + {$필요무료} WHERE name = '{$시전자_esc}'");
  $사용횟수 += $필요무료;
}
$남은횟수 = max(0, $보호한도 - $사용횟수);

db_query("INSERT INTO tb_damege SET 공격자 = '{$시전자_esc}', 피해자 = '{$대상_esc}', 유형 = '보호', 피해 = '{$요청보호횟수}', regdate = NOW()");
$결과멘트 = $자숙위반안내."🛡️ {$대상닉} 보호 +{$요청보호횟수} (현재 {$새보호})\n(남은 무료 횟수 {$남은횟수}/{$보호한도})";
if ($과다결제함) {
  $한도외사용문구 = function_exists('무기_한도외_사용표시문구') ? 무기_한도외_사용표시문구($내강화, $과다횟수) : '';
  $결과멘트 .= "\n(한도 외 ".냥축약표시($과다비용)." 차감{$한도외사용문구})";
}
시전_채팅응답($결과멘트);
