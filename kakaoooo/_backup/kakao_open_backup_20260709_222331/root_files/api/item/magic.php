<?php
// 마법 시전: .시전 / .시전 30 / .시전 닉네임 / .시전 닉네임 30
// 1~18강 1시간(연장 +1h), 19강~ 3시간(+3h). 0강 시전 불가.
$시전자_esc = addslashes($두자리닉넴);
$시전횟수 = isset($시전횟수) ? max(1, (int)$시전횟수) : 1;

$마법한도 = 0;
$아이템명 = null;
$시전문구 = '';
$마법지속초 = 0;
if ($내무기 === '🪄마법' || $내무기 === '🪄 마법') {
  $마법한도 = function_exists('무기_시전한도_3시간') ? 무기_시전한도_3시간($내무기, (int)$내강화) : 0;
  if ($마법한도 > 0) {
    $아이템명 = '마법';
    if ($내강화 <= 18) {
      $시전문구 = '1시간 마법(타수2배)';
      $마법지속초 = 3600;
    } else {
      $시전문구 = '3시간 마법(타수2배)';
      $마법지속초 = 10800;
    }
  }
}

if ($아이템명 === null || $마법한도 <= 0) {
  $대상문구 = ($대상닉 !== '' && $대상닉 !== null) ? " → {$대상닉}에게 적용 (1강 이상 시전 가능)" : '';
  시전_채팅응답("⚔️ [ {$두자리닉넴} ] {$내무기} +{$내강화}{$대상문구}");
  exit;
}

// 대상 1명 선정 (연속 시전 시 동일 대상)
if ($대상닉 === '' || $대상닉 === null) {
  $랜덤행 = db_select("SELECT name FROM tb_member WHERE status = 0 AND regdate <= DATE_SUB(NOW(), INTERVAL 3 DAY) ORDER BY RAND() LIMIT 1");
  $대상닉 = $랜덤행 ? trim($랜덤행['name'] ?? '') : $두자리닉넴;
  if ($대상닉 === '') {
    $대상닉 = $두자리닉넴;
  }
}
$대상_esc = addslashes($대상닉);
$대상문구 = ($대상닉 === $두자리닉넴) ? '본인' : $대상닉;

if ($대상닉 !== $두자리닉넴) {
  $대상가입행 = db_select("SELECT regdate FROM tb_member WHERE name = '{$대상_esc}' LIMIT 1");
  if (신입_공격면역($대상가입행['regdate'] ?? '')) {
    시전_채팅응답("❌ {$대상닉}은(는) 가입 3일 미만(신입)이라 마법을 걸 수 없어요.");
    exit;
  }
}

$성공횟수 = 0;
$과다결제총액 = 0;
$한도차단멘트 = '';
$종료시간표시 = '';
$자숙패널티 = ['횟수' => 0, '게임냥' => 0, '보유냥' => 0, '마지막끝' => ''];

for ($n = 0; $n < $시전횟수; $n++) {
  $dur_row = db_select("SELECT durability FROM tb_member WHERE name = '{$시전자_esc}' LIMIT 1");
  $현재내구도 = ($dur_row['durability'] ?? null) !== null ? (int)$dur_row['durability'] : null;
  if ($내강화 >= 10 && $현재내구도 !== null) {
    if ($현재내구도 <= 0) {
      $한도차단멘트 = "{$내무기} +{$내강화} 시전!\n\n❌ 내구도 0 사용불가.";
      break;
    }
    db_query("UPDATE tb_member SET durability = GREATEST(durability - 1, 0) WHERE name = '{$시전자_esc}'");
  }

  자숙_시전보호_패널티_누적($자숙패널티, $두자리닉넴, '시전', isset($단위) ? $단위 : '냥');

  $마법_rs = db_query("SELECT magic_used, magic_window, point, IFNULL(extra_uses, 0) AS extra_uses, extra_reset_date FROM tb_member WHERE name = '{$시전자_esc}' LIMIT 1");
  $마법행 = $마법_rs ? db_assoc($마법_rs) : null;
  $마법사용 = 0;
  $구간시작 = null;
  $보유냥 = 0;
  $과다횟수 = 0;
  if (is_array($마법행)) {
    $마법사용 = (int)($마법행['magic_used'] ?? 0);
    $구간시작 = $마법행['magic_window'] ?? null;
    $보유냥 = (int)($마법행['point'] ?? 0);
    $오늘 = date('Y-m-d');
    $리셋일 = trim($마법행['extra_reset_date'] ?? '');
    if ($리셋일 === '' || $리셋일 < $오늘) {
      db_query("UPDATE tb_member SET extra_uses = 0, extra_reset_date = CURDATE() WHERE name = '{$시전자_esc}'");
      $과다횟수 = 0;
    } else {
      $과다횟수 = (int)($마법행['extra_uses'] ?? 0);
    }
  }

  $지금 = time();
  if ($구간시작 === null || $구간시작 === '' || $지금 >= strtotime($구간시작) + ($쿨타임 * 3600)) {
    $마법사용 = 0;
    db_query("UPDATE tb_member SET magic_used = 0, magic_window = NOW() WHERE name = '{$시전자_esc}'");
  }
  if ($마법사용 > $마법한도) {
    db_query("UPDATE tb_member SET magic_used = {$마법한도}, magic_window = NOW() WHERE name = '{$시전자_esc}'");
    $마법사용 = $마법한도;
  }

  $과다결제함 = false;
  $과다비용 = 0;
  $한도외 = 무기_한도외_상태($내강화, $과다횟수);
  $한도외잔여문구 = 무기_한도외_잔여문구($한도외);

  if ($마법사용 >= $마법한도 && !empty($한도외['allow_pay'])) {
    $과다비용 = 무기_한도외_시전비용($과다횟수);
    if ($보유냥 < $과다비용) {
      $다음리셋 = $구간시작 ? strtotime($구간시작) + ($쿨타임 * 3600) : $지금 + ($쿨타임 * 3600);
      $남은분 = (int)ceil(($다음리셋 - $지금) / 60);
      $한도차단멘트 = "{$내무기} +{$내강화} 시전!❌ 한도 소진.\n한도 외 사용 시 ".냥축약표시($과다비용)." 필요 (보유 ".냥축약표시($보유냥)."){$한도외잔여문구}. {$남은분}분 후 한도 리셋.";
      break;
    }
    db_query("UPDATE tb_member SET point = point - {$과다비용}, extra_uses = IFNULL(extra_uses, 0) + 1 WHERE name = '{$시전자_esc}'");
    $과다결제함 = true;
    $과다결제총액 += $과다비용;
  }

  if ($마법사용 >= $마법한도 && !$과다결제함) {
    $다음리셋 = $구간시작 ? strtotime($구간시작) + ($쿨타임 * 3600) : $지금 + ($쿨타임 * 3600);
    $남은분 = (int)ceil(($다음리셋 - $지금) / 60);
    if (!empty($한도외['exhausted'])) {
      $한도차단멘트 = "{$내무기} +{$내강화} 시전!❌ 한도 소진.\n오늘 한도 외 ".(int)$한도외['max']."회 소진(지정 시전 포함·자정 리셋). {$남은분}분 후 한도 리셋.";
    } elseif (!empty($한도외['allow_pay'])) {
      $다음과다비용 = 무기_한도외_시전비용($과다횟수);
      $한도차단멘트 = "{$내무기} +{$내강화} 시전!❌ 한도 소진.\n한도 외 사용 가능: ".냥축약표시($다음과다비용)." 필요{$한도외잔여문구}. {$남은분}분 후 한도 리셋.";
    } else {
      $한도차단멘트 = "{$내무기} +{$내강화} 시전!❌ 한도 소진.\n한도 외 사용은 1강부터 가능. {$남은분}분 후 한도 리셋.";
    }
    break;
  }

  $기존아이템_rs = db_query("SELECT enddate FROM tb_item_use WHERE nickname = '{$대상_esc}' AND item = '{$아이템명}' LIMIT 1");
  $기존아이템 = $기존아이템_rs ? db_assoc($기존아이템_rs) : null;
  $지금_str = date('Y-m-d H:i:s', time());
  if (is_array($기존아이템) && !empty($기존아이템['enddate']) && $기존아이템['enddate'] > $지금_str) {
    $유효시간 = date('Y-m-d H:i:s', strtotime($기존아이템['enddate']) + $마법지속초);
  } else {
    $유효시간 = date('Y-m-d H:i:s', time() + $마법지속초);
  }
  if (is_array($기존아이템)) {
    db_query("UPDATE tb_item_use SET enddate = '{$유효시간}', regdate = NOW() WHERE nickname = '{$대상_esc}' AND item = '{$아이템명}'");
  } else {
    db_query("INSERT INTO tb_item_use SET nickname = '{$대상_esc}', item = '{$아이템명}', enddate = '{$유효시간}', regdate = NOW()");
  }

  if (!$과다결제함) {
    db_query("UPDATE tb_member SET magic_used = magic_used + 1 WHERE name = '{$시전자_esc}'");
  }

  db_query("INSERT INTO tb_damege SET 공격자 = '{$시전자_esc}', 피해자 = '{$대상_esc}', 유형 = '마법', 피해 = '{$아이템명}', regdate = NOW()");
  $종료시간표시 = date('m-d H:i', strtotime($유효시간));
  $성공횟수++;
}

if ($성공횟수 === 0) {
  시전_채팅응답($한도차단멘트 !== '' ? $한도차단멘트 : "{$내무기} +{$내강화} 시전!\n\n❌ 시전할 수 없습니다.");
  exit;
}

$마법_rs = db_query("SELECT magic_used FROM tb_member WHERE name = '{$시전자_esc}' LIMIT 1");
$마법행 = $마법_rs ? db_assoc($마법_rs) : null;
$마법사용_현재 = (int)($마법행['magic_used'] ?? 0);
$마법남은 = max(0, $마법한도 - $마법사용_현재);

$결과멘트 = 자숙_시전보호_패널티_요약문구($자숙패널티, '시전', isset($단위) ? $단위 : '냥');
$결과멘트 .= "✨ +{$내강화} {$스타일} {$내무기} ✨\n\n🎯 {$대상문구} ({$시전문구})";
if ($시전횟수 > 1) {
  $결과멘트 .= "\n🔁 {$시전횟수}회 시전 · 성공 {$성공횟수}회";
}
$결과멘트 .= "\n종료 {$종료시간표시} · 남은 횟수 {$마법남은}/{$마법한도}";
if ($과다결제총액 > 0) {
  $결과멘트 .= "\n(한도 외 총 ".냥축약표시($과다결제총액)." 차감)";
}
if ($한도차단멘트 !== '' && $성공횟수 < $시전횟수) {
  $결과멘트 .= "\n⚠️ 한도/제한으로 {$성공횟수}회만 진행됨";
}
시전_채팅응답($결과멘트);
exit;
