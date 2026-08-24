<?php
exit;
// 포크 시전: 10강 이상부터 가능. 3시간 구간당 시전가능횟수 10강 2회, 11강 2회, 12강 3회, 13강 5회, 14강 7회, 15강 10회(이 중 지목 시전 최대 2회, 한도 외 냥 사용 시 지목 2회 제한 없음). 9강은 시전 제외. 15강은 1~3개 뺏기, 나머지 1개.
// 한도 초과 사용 시 냥 차감(30만→40만→50만→60만…). 자정에 extra_uses 초기화. tb_member에 extra_uses INT DEFAULT 0, extra_reset_date DATE NULL 필요 (무기 공통).
$시전자_esc = addslashes($두자리닉넴);
$시전자_midx = (int)($정보['idx'] ?? 0);
$보호막 = 85;

// 내구도 체크: 강화 +10 이상 무기 시전 시 durability 1 감소, 0 이하면 시전 불가
$dur_row = db_select("SELECT durability FROM tb_member WHERE name = '{$시전자_esc}' LIMIT 1");
$durability_raw = $dur_row['durability'] ?? null;
$현재내구도 = ($durability_raw !== null) ? (int)$durability_raw : null;
if ($내강화 >= 10 && $현재내구도 !== null) {
  if ($현재내구도 <= 0) {
    echo 전송("{$내무기} +{$내강화} 시전!\n\n❌ 내구도 0 사용불가.");
    exit;
  }
  db_query("UPDATE tb_member SET durability = GREATEST(durability - 1, 0) WHERE name = '{$시전자_esc}'");
}
// 강화별 3시간 구간당 시전가능횟수: 10강 2회, 11강 2회, 12강 3회, 13강 5회, 14강 7회, 15강 10회 (9강 시전 제외)
// 강화별 시전확률: 10강 70%, 11강 75%, 12강 80%, 13강 85%, 14강 90%, 15강 95% (실패 시에도 한도 소모)
$시전한도 = array(10 => 3, 11 => 4, 12 => 5, 13 => 6, 14 => 9, 15 => 15);
$시전확률_arr = array(10 => 65, 11 => 70, 12 => 75, 13 => 80, 14 => 85, 15 => 90);
$최대시전 = isset($시전한도[$내강화]) ? (int)$시전한도[$내강화] : 0;
$시전확률 = isset($시전확률_arr[$내강화]) ? (int)$시전확률_arr[$내강화] : 0;

$뺏을개수 = 0;
if ($내강화 >= 15) {
  $뺏을개수 = rand(1, 3);
} elseif ($내강화 >= 10 && $내강화 <= 14) {
  $뺏을개수 = 1;
}

if ($뺏을개수 > 0 && $최대시전 > 0) {
  $과다결제함 = false;
  $과다비용 = 0;

  // 10~15강 공통: 3시간 구간당 시전 횟수 한도
  $포크_rs = db_query("SELECT magic_used, magic_window, point, IFNULL(extra_uses, 0) AS extra_uses, extra_reset_date FROM tb_member WHERE name = '{$시전자_esc}' LIMIT 1");
  $포크행 = $포크_rs ? db_assoc($포크_rs) : null;
  $포크사용 = 0;
  $구간시작 = null;
  $보유냥 = 0;
  $과다횟수 = 0;
  if (is_array($포크행)) {
    $포크사용 = (int)(isset($포크행['magic_used']) ? $포크행['magic_used'] : 0);
    $구간시작 = isset($포크행['magic_window']) ? $포크행['magic_window'] : null;
    $보유냥 = (int)(isset($포크행['point']) ? $포크행['point'] : 0);
    $오늘 = date('Y-m-d');
    $리셋일 = isset($포크행['extra_reset_date']) ? trim($포크행['extra_reset_date']) : '';
    if ($리셋일 === '' || $리셋일 < $오늘) {
      db_query("UPDATE tb_member SET extra_uses = 0, extra_reset_date = CURDATE() WHERE name = '{$시전자_esc}'");
      $과다횟수 = 0;
    } else {
      $과다횟수 = (int)(isset($포크행['extra_uses']) ? $포크행['extra_uses'] : 0);
    }
  }
  $지금 = time();
  if ($구간시작 === null || $구간시작 === '' || $지금 >= strtotime($구간시작) + ($쿨타임 * 3600)) {
    $포크사용 = 0;
    db_query("UPDATE tb_member SET magic_used = 0, magic_window = NOW() WHERE name = '{$시전자_esc}'");
  }
  if ($포크사용 > $최대시전) {
    db_query("UPDATE tb_member SET magic_used = {$최대시전}, magic_window = NOW() WHERE name = '{$시전자_esc}'");
    $포크사용 = $최대시전;
  }
  $과다사용 = ($포크사용 >= $최대시전);
  if ($과다사용) {
    $과다비용 = 300000 + ($과다횟수 * 100000);
    if ($보유냥 < $과다비용) {
      $다음리셋 = $구간시작 ? strtotime($구간시작) + ($쿨타임 * 3600) : $지금 + ($쿨타임 * 3600);
      $남은분 = (int)ceil(($다음리셋 - $지금) / 60);
      echo 전송("{$내무기} +{$내강화} 시전!\n\n❌ {$최대시전}회 한도 소진.\n한도 외 사용 시 ".number_format($과다비용)."냥 필요 (보유 ".number_format($보유냥)."냥). 약 {$남은분}분 후 무료 한도 리셋.");
      exit;
    }
    db_query("UPDATE tb_member SET point = point - {$과다비용}, extra_uses = IFNULL(extra_uses, 0) + 1 WHERE name = '{$시전자_esc}'");
    $과다결제함 = true;
  }
  if ($포크사용 >= $최대시전 && !$과다사용) {
    $다음리셋 = $구간시작 ? strtotime($구간시작) + ($쿨타임 * 3600) : $지금 + ($쿨타임 * 3600);
    $남은분 = (int)ceil(($다음리셋 - $지금) / 60);
    echo 전송("{$내무기} +{$내강화} 시전!\n\n❌ {$최대시전}회 한도 소진.\n다음 구간까지 약 {$남은분}분 남음.");
    exit;
  }

  // 시전확률 판정 (실패 시에도 한도 소모, 단 과다결제 사용 시에는 magic_used 미증가)
  if (rand(1, 100) > $시전확률) {
    if (!$과다결제함) {
      db_query("UPDATE tb_member SET magic_used = magic_used + 1 WHERE name = '{$시전자_esc}'");
    }
    echo 전송("✨ +{$내강화} {$스타일} {$내무기} ✨\n\n❌ 시전 실패! (확률 {$시전확률}%)");
    exit;
  }

  // 15강 지목 시전 횟수 제한 (3시간 구간당 최대 2회, 한도 외 냥 결제 시에는 제한 없음)
  if ($내강화 >= 15 && !empty($대상닉) && !$과다결제함) {
    $쿨시간 = (int)$쿨타임;
    if ($쿨시간 <= 0) $쿨시간 = 3;
    $지목행 = db_select("SELECT COUNT(*) AS cnt FROM tb_damege WHERE 공격자 = '{$시전자_esc}' AND 유형 = '포크지목' AND regdate >= DATE_SUB(NOW(), INTERVAL {$쿨시간} HOUR)");
    $지목사용 = (int)($지목행['cnt'] ?? 0);
    if ($지목사용 >= 2) {
      echo 전송("🔱 +15 포크 지목 시전은 3시간에 2회까지만 가능해요.\n지목 없이 '.시전'으로 랜덤 시전은 남은 횟수 내에서 가능해요.\n(한도 소진 후 냥으로 추가 사용 시에는 지목 제한 없음)");
      exit;
    }
  }

  // 대상 선택: 15강에서 닉네임을 지정하면 지목, 그 외에는 본인 제외 랜덤 1명
  if ($내강화 >= 15 && !empty($대상닉)) {
    $대상_esc = addslashes($대상닉);
    $대상정보 = db_select("SELECT protect, regdate FROM tb_member WHERE name = '{$대상_esc}' LIMIT 1");
    if (!$대상정보) {
      echo 전송("❌ {$대상닉} 회원을 찾을 수 없어요.");
      exit;
    }
  } else {
    $랜덤 = db_select("SELECT name FROM tb_member WHERE name != '{$시전자_esc}' and status = 0 ORDER BY RAND() LIMIT 1");
    if (empty($랜덤['name'])) {
      echo 전송("{$내무기} +{$내강화} 시전!\n\n❌ 뺏을 대상이 없음");
      exit;
    }
    $대상닉 = $랜덤['name'];
    $대상_esc = addslashes($대상닉);
    // 보호 수치 및 가입일 확인 (가입 2일 미만이면 불발 처리)
    $대상정보 = db_select("SELECT protect, regdate FROM tb_member WHERE name = '{$대상_esc}' LIMIT 1");
  }

  // 보호 수치 및 가입일 확인 (가입 2일 미만이면 불발 처리)
  $보호수 = (int)($대상정보['protect'] ?? 0);
  $가입일 = $대상정보['regdate'] ?? null;
  if ($가입일 !== null && $가입일 !== '' && strtotime($가입일) > strtotime('-2 days')) {
    //db_query("UPDATE tb_member SET magic_used = magic_used + 1 WHERE name = '{$시전자_esc}'");
    echo 전송("✨ +{$내강화} {$스타일} {$내무기} ✨\n\n🎯 대상: {$대상닉}\n❌ 불발! (가입 2일 미만 회원은 아이템 강탈 불가)");
    exit;
  }
  
  // 보호 수치 1회 방어 (90% 확률로 발동, 10%는 뚫림)
  $보호뚫음 = ($보호수 >= 1);
  if ($보호수 >= 1) {
    $보호랜덤 = mt_rand(1, 100);
    if ($보호랜덤 <= $보호막) {
      // 10% 크리티컬 방어: 보호 3개까지 소모
      $차감보호 = 1;
      $크리티컬 = false;
      if ($보호랜덤 <= 10) {
        $차감보호 = min(3, $보호수);
        $크리티컬 = true;
      }
      db_query("UPDATE tb_member SET protect = GREATEST(protect - {$차감보호}, 0) WHERE name = '{$대상_esc}'");
      //db_query("UPDATE tb_member SET magic_used = magic_used + 1 WHERE name = '{$시전자_esc}'");
      $남은보호 = max($보호수 - $차감보호, 0);
      if ($크리티컬) {
        echo 전송("✨ +{$내강화} {$스타일} {$내무기} ✨\n\n🎯 대상: {$대상닉}\n🛡️ 크리티컬 방어! (보호 {$차감보호}개 소모, 남은 보호 {$남은보호})");
      } else {
        echo 전송("✨ +{$내강화} {$스타일} {$내무기} ✨\n\n🎯 대상: {$대상닉}\n🛡️ 보호로 1회 방어! (남은 보호 {$남은보호})");
      }
      exit;
    }
  }
  
  $대상아이템 = db_query("SELECT idx, itemname FROM tb_member_item WHERE nick = '{$대상_esc}' AND status = 0 ORDER BY RAND() LIMIT {$뺏을개수}");
  $뺏은목록 = array();
  if ($대상아이템 && mysqli_num_rows($대상아이템) > 0) {
    while ($row = db_fetch($대상아이템)) {
      $뺏은목록[] = $row;
    }
  }
  if (empty($뺏은목록)) {
    if (!$과다결제함) {
      db_query("UPDATE tb_member SET magic_used = magic_used + 1 WHERE name = '{$시전자_esc}'");
    }
    echo 전송("✨ +{$내강화} {$스타일} {$내무기} ✨\n\n🎯 대상: {$대상닉}\n❌ 뺏을 수 있는 아이템이 없습니다.");
    exit;
  }
  foreach ($뺏은목록 as $row) {
    db_query("UPDATE tb_member_item SET nick = '{$시전자_esc}', midx = {$시전자_midx} WHERE idx = {$row['idx']}");
  }
  // 시전 구간(3시간) 동안 첫 피해자만 맨트로그에 기록
  if (!empty($뺏은목록) && $포크사용 === 0) {
    $포크아이템문구 = implode(', ', array_map(function ($r) { return $r['itemname']; }, $뺏은목록));
    $포크멘트 = addslashes("✨ [ {$두자리닉넴} ] +{$내강화} {$스타일} {$내무기} 시전중\n🎯 피해자: {$대상닉}\n📦 탈취 아이템: {$포크아이템문구}");
    db_query("
      INSERT INTO tb_lotto_info
      SET status = 0,
          msg = '{$포크멘트}',
          leverage = 0,
          item = '{$대상_esc}',
          regdate = NOW()
    ");
  }
  if (!$과다결제함) {
    db_query("UPDATE tb_member SET magic_used = magic_used + 1 WHERE name = '{$시전자_esc}'");
  }
  $아이템문구 = implode(', ', array_map(function ($r) { return $r['itemname']; }, $뺏은목록));
  $개수 = count($뺏은목록);
  $강탈멘트 = $보호뚫음 ? "🕳️ 보호를 뚫고 {$개수}개 강탈! ({$아이템문구})" : "📦 {$개수}개 강탈! ({$아이템문구})";
  if ($과다결제함) {
    $강탈멘트 .= "\n(한도 외 ".number_format($과다비용)."냥 차감)";
  }
  $포크유형 = ($내강화 >= 15 && !empty($대상닉)) ? '포크지목' : '포크';
  db_query("insert into tb_damege set 공격자 = '{$시전자_esc}', 피해자 = '{$대상_esc}', 유형 = '{$포크유형}', 피해 = '{$아이템문구}', regdate = now() ");
  echo 전송("✨ +{$내강화} {$스타일} {$내무기} ✨\n\n🎯 대상: {$대상닉}\n{$강탈멘트}");
  exit;
}

$효과문구 = ($내강화 >= 10) ? '' : ' (10강 이상부터 시전 가능)';
echo 전송("✨ +{$내강화} {$스타일} {$내무기} ✨\n\n🎯 랜덤 대상으로 시전{$효과문구}");
