<?php
// 활 시전: 대상 조건 — 신입(가입 3일 미만) 제외 · 오늘 버프 300타 이상. 무기 없어도 공격 가능.
// .시전 30 / .시전 닉네임 30 → 동일 대상에게 연속 시전
$시전자_esc = addslashes($두자리닉넴);
$시전횟수 = isset($시전횟수) ? max(1, (int)$시전횟수) : 1;
$보호막 = 95;
$크리티컬막 = 10;
$시전한도 = function_exists('무기_시전한도_3시간') ? 무기_시전한도_3시간($내무기, (int)$내강화) : 0;

if ($시전한도 <= 0) {
  시전_채팅응답("✨ +{$내강화} {$스타일} {$내무기} ✨\n\n🎯  (1강 이상부터 시전 가능)");
  exit;
}

$활_타수랜덤 = function ($강화) {
  if ($강화 >= 20) return rand(15, 30);
  if ($강화 >= 19) return rand(11, 20);
  if ($강화 >= 18) return rand(9, 15);
  if ($강화 >= 17) return rand(7, 12);
  if ($강화 >= 16) return rand(5, 9);
  if ($강화 >= 15) return rand(1, 7);
  if ($강화 >= 14) return rand(1, 6);
  if ($강화 >= 13) return rand(1, 4);
  if ($강화 >= 12) return rand(1, 4);
  if ($강화 >= 11) return rand(1, 2);
  if ($강화 >= 10) return rand(1, 2);
  return null;
};

$is지목시전 = ($내강화 >= 18 && !empty($대상닉));
$지목횟수지정 = !empty($지목횟수지정);

// 대상 1명 선정 (연속 시전 시 동일 대상 유지)
if ($is지목시전) {
  $대상_esc = addslashes($대상닉);
  $대상정보 = db_select("SELECT protect, enhance, item, regdate FROM tb_member WHERE name = '{$대상_esc}' LIMIT 1");
  if (!$대상정보) {
    시전_채팅응답("❌ {$대상닉} 회원을 찾을 수 없어요.");
    exit;
  }
} else {
  $랜덤 = db_select("
    SELECT m.name
    FROM tb_member m
    INNER JOIN (
      SELECT nickname, COALESCE(SUM(tasu), 0) AS total_tasu
      FROM tb_msg
      WHERE DATE(regdate) = CURDATE()
      GROUP BY nickname
      HAVING total_tasu >= 300
    ) t ON m.name = t.nickname
    WHERE m.name != '{$시전자_esc}' AND m.status = 0
      AND m.regdate <= DATE_SUB(NOW(), INTERVAL 3 DAY)
    ORDER BY RAND()
    LIMIT 1
  ");
  if (empty($랜덤['name'])) {
    시전_채팅응답("{$내무기} +{$내강화} 시전!\n\n❌ 공격 가능한 대상이 없어요.\n(신입 3일 제외 · 버프 300타 이상)");
    exit;
  }
  $대상닉 = $랜덤['name'];
  $대상_esc = addslashes($대상닉);
  $대상정보 = db_select("SELECT protect, enhance, item, regdate FROM tb_member WHERE name = '{$대상_esc}' LIMIT 1");
}

if (신입_공격면역($대상정보['regdate'] ?? '')) {
  시전_채팅응답("❌ {$대상닉}은(는) 가입 3일 미만(신입)이라 공격할 수 없어요.");
  exit;
}

$상대총합행 = db_select("SELECT COALESCE(SUM(tasu), 0) AS total_tasu FROM tb_msg WHERE nickname = '{$대상_esc}' AND DATE(regdate) = CURDATE()");
$대상현재타수 = (int)($상대총합행['total_tasu'] ?? 0);
if ($대상현재타수 < 300) {
  시전_채팅응답("❌ {$대상닉}은(는) 오늘 버프 타수 300타 미만이라 공격할 수 없어요.");
  exit;
}

$총흡수타수 = 0;
$성공횟수 = 0;
$방어횟수 = 0;
$크리티컬방어횟수 = 0;
$마지막방어_크리티컬 = false;
$마지막방어_차감보호 = 0;
$마지막방어_남은보호 = 0;
$과다결제총액 = 0;
$한도차단멘트 = '';
$구간첫시전 = true;
$보호뚫림있음 = false;
$자숙패널티 = ['횟수' => 0, '게임냥' => 0, '보유냥' => 0, '마지막끝' => ''];

for ($n = 0; $n < $시전횟수; $n++) {
  // 내구도: 시전 1회당 1 차감
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

  $활_rs = db_query("SELECT magic_used, magic_window, point, IFNULL(extra_uses, 0) AS extra_uses, extra_reset_date FROM tb_member WHERE name = '{$시전자_esc}' LIMIT 1");
  $활행 = $활_rs ? db_assoc($활_rs) : null;
  $활사용 = 0;
  $구간시작 = null;
  $보유냥 = 0;
  $과다횟수 = 0;
  if (is_array($활행)) {
    $활사용 = (int)($활행['magic_used'] ?? 0);
    $구간시작 = $활행['magic_window'] ?? null;
    $보유냥 = (int)($활행['point'] ?? 0);
    $오늘 = date('Y-m-d');
    $리셋일 = trim($활행['extra_reset_date'] ?? '');
    if ($리셋일 === '' || $리셋일 < $오늘) {
      db_query("UPDATE tb_member SET extra_uses = 0, extra_reset_date = CURDATE() WHERE name = '{$시전자_esc}'");
      $과다횟수 = 0;
    } else {
      $과다횟수 = (int)($활행['extra_uses'] ?? 0);
    }
  }

  $지금 = time();
  if ($구간시작 === null || $구간시작 === '' || $지금 >= strtotime($구간시작) + ($쿨타임 * 3600)) {
    $활사용 = 0;
    db_query("UPDATE tb_member SET magic_used = 0, magic_window = NOW() WHERE name = '{$시전자_esc}'");
  }
  if ($활사용 > $시전한도) {
    db_query("UPDATE tb_member SET magic_used = {$시전한도}, magic_window = NOW() WHERE name = '{$시전자_esc}'");
    $활사용 = $시전한도;
  }

  $과다결제함 = false;
  $과다비용 = 0;
  $한도외 = 무기_한도외_상태($내강화, $과다횟수);
  $한도외잔여문구 = 무기_한도외_잔여문구($한도외);

  if ($활사용 >= $시전한도 && !empty($한도외['allow_pay'])) {
    $과다비용 = 무기_한도외_시전비용($과다횟수);
    if ($보유냥 < $과다비용) {
      $다음리셋 = $구간시작 ? strtotime($구간시작) + ($쿨타임 * 3600) : $지금 + ($쿨타임 * 3600);
      $남은분 = (int)ceil(($다음리셋 - $지금) / 60);
      $한도차단멘트 = "{$내무기} +{$내강화} 시전!❌ 한도 소진.\n한도 외 사용 시 ".냥축약표시($과다비용)." 필요 (보유 ".냥축약표시($보유냥)."){$한도외잔여문구}. {$남은분}분 후 무료 한도 리셋.";
      break;
    }
    db_query("UPDATE tb_member SET point = point - {$과다비용}, extra_uses = IFNULL(extra_uses, 0) + 1 WHERE name = '{$시전자_esc}'");
    $과다결제함 = true;
    $과다결제총액 += $과다비용;
  }

  if ($활사용 >= $시전한도 && !$과다결제함) {
    $다음리셋 = $구간시작 ? strtotime($구간시작) + ($쿨타임 * 3600) : $지금 + ($쿨타임 * 3600);
    $남은분 = (int)ceil(($다음리셋 - $지금) / 60);
    if (!empty($한도외['exhausted'])) {
      $한도차단멘트 = "{$내무기} +{$내강화} 시전!❌ 한도 소진.\n오늘 한도 외 ".(int)$한도외['max']."회 소진(지정 시전 포함·자정 리셋). {$남은분}분 후 무료 한도 리셋.";
    } elseif (!empty($한도외['allow_pay'])) {
      $다음과다비용 = 무기_한도외_시전비용($과다횟수);
      $한도차단멘트 = "{$내무기} +{$내강화} 시전!❌ 한도 소진.\n한도 외 사용 가능: ".냥축약표시($다음과다비용)." 필요{$한도외잔여문구}. {$남은분}분 후 무료 한도 리셋.";
    } else {
      $한도차단멘트 = "{$내무기} +{$내강화} 시전!❌ 한도 소진.\n한도 외 사용은 1강부터 가능. {$남은분}분 후 무료 한도 리셋.";
    }
    break;
  }

  if ($is지목시전 && !$과다결제함) {
    $쿨시간 = (int)$쿨타임;
    if ($쿨시간 <= 0) $쿨시간 = 3;
    $지목행 = db_select("SELECT COUNT(*) AS cnt FROM tb_damege WHERE 공격자 = '{$시전자_esc}' AND 유형 = '활지목' AND regdate >= DATE_SUB(NOW(), INTERVAL {$쿨시간} HOUR)");
    $지목사용 = (int)($지목행['cnt'] ?? 0);
    if ($지목사용 >= 2) {
      $한도차단멘트 = "🏹 +15 활 지목 시전은 3시간에 2회까지만 가능해요.\n지목 없이 '.시전'으로 랜덤 시전은 남은 횟수 내에서 가능해요.\n(한도 소진 후 냥으로 추가 사용 시에는 지목 제한 없음)";
      break;
    }
  }

  $대상정보 = db_select("SELECT protect, enhance, item FROM tb_member WHERE name = '{$대상_esc}' LIMIT 1");

  $보호수 = (int)($대상정보['protect'] ?? 0);
  if ($보호수 >= 1) {
    $보호랜덤 = mt_rand(1, 100);
    if ($보호랜덤 <= $보호막) {
      $차감보호 = 1;
      if ($보호랜덤 <= $크리티컬막) {
        $차감보호 = min(3, $보호수);
      }
      db_query("UPDATE tb_member SET protect = GREATEST(protect - {$차감보호}, 0) WHERE name = '{$대상_esc}'");
      if (!$과다결제함) {
        db_query("UPDATE tb_member SET magic_used = magic_used + 1 WHERE name = '{$시전자_esc}'");
      }
      $크리티컬 = ($보호랜덤 <= $크리티컬막);
      if ($크리티컬) {
        $크리티컬방어횟수++;
      }
      $마지막방어_크리티컬 = $크리티컬;
      $마지막방어_차감보호 = $차감보호;
      $마지막방어_남은보호 = max($보호수 - $차감보호, 0);
      $방어횟수++;
      continue;
    }
    $보호뚫림있음 = true;
  }

  $상대총합행 = db_select("SELECT COALESCE(SUM(tasu), 0) AS total_tasu FROM tb_msg WHERE nickname = '{$대상_esc}' AND DATE(regdate) = CURDATE()");
  $대상현재타수 = (int)($상대총합행['total_tasu'] ?? 0);
  if ($대상현재타수 <= 0) {
    break;
  }

  $tasu값 = $활_타수랜덤($내강화);
  $실제뺀타수 = min($tasu값, $대상현재타수);
  db_query("INSERT INTO tb_msg (nickname, msg, tasu, regdate) VALUES ('{$대상_esc}', '', -{$실제뺀타수}, NOW())");
  db_query("INSERT INTO tb_msg (nickname, msg, tasu, regdate) VALUES ('{$시전자_esc}', '', {$실제뺀타수}, NOW())");
  $총흡수타수 += $실제뺀타수;

  if (!$과다결제함) {
    db_query("UPDATE tb_member SET magic_used = magic_used + 1 WHERE name = '{$시전자_esc}'");
  }

  $활유형 = $is지목시전 ? '활지목' : '활';
  db_query("INSERT INTO tb_damege SET 공격자 = '{$시전자_esc}', 피해자 = '{$대상_esc}', 유형 = '{$활유형}', 피해 = '{$실제뺀타수}', regdate = NOW()");

  // if ($실제뺀타수 > 0 && $구간첫시전) {
  //   $활멘트 = addslashes("✨ [ {$두자리닉넴} ] +{$내강화} {$스타일} {$내무기} 시전중\n🎯 피해자: {$대상닉} ({$실제뺀타수}타)");
  //   db_query("
  //     INSERT INTO tb_lotto_info
  //     SET status = 0,
  //         msg = '{$활멘트}',
  //         leverage = 0,
  //         item = '{$대상_esc}',
  //         regdate = NOW()
  //   ");
  //   $구간첫시전 = false;
  // }

  $성공횟수++;
}

$실행횟수 = $성공횟수 + $방어횟수;
if ($실행횟수 === 0) {
  시전_채팅응답($한도차단멘트 !== '' ? $한도차단멘트 : "{$내무기} +{$내강화} 시전!\n\n❌ 시전할 수 없습니다.");
  exit;
}

$멘트 = 자숙_시전보호_패널티_요약문구($자숙패널티, '시전', isset($단위) ? $단위 : '냥');
$멘트 .= "✨ +{$내강화} {$스타일} {$내무기} ✨ 🎯 {$대상닉}";
if ($시전횟수 > 1) {
  $멘트 .= "\n🔁 {$시전횟수}회 시전 (성공 {$성공횟수}·방어 {$방어횟수})";
}
if ($방어횟수 > 0) {
  if ($시전횟수 === 1 && $성공횟수 === 0) {
    if ($마지막방어_크리티컬) {
      $멘트 .= "\n🛡️ 크리티컬 방어! (보호 {$마지막방어_차감보호}개 소모, 남은 보호 {$마지막방어_남은보호})";
    } else {
      $멘트 .= "\n🛡️ 방어!";
    }
  } else {
    $멘트 .= "\n🛡️ 보호로 {$방어횟수}회 방어";
    if ($크리티컬방어횟수 > 0) {
      $멘트 .= " (크리티컬 {$크리티컬방어횟수}회)";
    }
  }
}
if ($총흡수타수 > 0) {
  $멘트 .= "\n".($보호뚫림있음 ? "🕳️ 보호 뚫림 {$총흡수타수}타 흡수!" : "{$총흡수타수}타 흡수!");
}
if ($과다결제총액 > 0) {
  $멘트 .= "\n(한도 외 총 ".냥축약표시($과다결제총액)." 차감)";
}
if ($한도차단멘트 !== '' && $실행횟수 < $시전횟수) {
  $멘트 .= "\n⚠️ 한도/제한으로 {$실행횟수}회만 진행됨";
}
시전_채팅응답($멘트);
exit;
