<?php
// 단소 시전: 대상 조건 — 신입(가입 3일 미만) 제외. 무기 없어도 공격 가능. +10 미만·무기 보유 시 버프 300타 이상 필요.
// 냥 강탈 = +10 이상 시전 시 전체 게임냥의 0.0001% (강화 무관). 시전 1회당 본인 버프 타수 -1.
// 게임냥 0이면 본방냥 10 자동스왑만 · 다음 .시전 때 게임냥 있으면 강탈.
// 배치(.시전 N) 시작 시 게임냥 0이면 해당 배치 전체 스왑만(중간에 쌓여도 강탈 없음).
// 한도 외 시전(+1~): 게임냥 차감 없음, 버프 타수 -1만 (일일 한도·+20 무제한은 기존과 동일).
$단소_강탈금액 = function ($강화) {
  if ((int)$강화 < 10) {
    return 0;
  }
  return (int)전체냥기준금액(0.0001);
};
$단소_버프타수공격기준 = 300;
$단소_버프타_하한 = 100; // 시전 1회당 -1타 — 버프 타수가 이 값 미만으로 떨어지면 불가
$시전자_esc = addslashes($두자리닉넴);
$시전횟수 = isset($시전횟수) ? max(1, (int)$시전횟수) : 1;
$보호막 = 95;
$크리티컬막 = 10;
$구간당시전한도 = function_exists('무기_시전한도_3시간') ? 무기_시전한도_3시간($내무기, (int)$내강화) : 0;

if ($구간당시전한도 <= 0) {
  시전_채팅응답("✨ +{$내강화} {$스타일} {$내무기} ✨\n\n🎯 랜덤 대상으로 시전 (1강 이상부터 시전 가능)");
  exit;
}

$is지목시전 = ($내강화 >= 15 && !empty($대상닉));
$지목횟수지정 = !empty($지목횟수지정);

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
    WHERE m.name != '{$시전자_esc}' AND m.status = 0
      AND m.regdate <= DATE_SUB(NOW(), INTERVAL 3 DAY)
      AND (
        TRIM(IFNULL(m.item, '')) = ''
        OR (IFNULL(m.enhance, 0) >= 10 AND TRIM(IFNULL(m.item, '')) != '')
        OR (
          SELECT COALESCE(SUM(tasu), 0) FROM tb_msg
          WHERE nickname = m.name AND DATE(regdate) = CURDATE()
        ) >= {$단소_버프타수공격기준}
      )
    ORDER BY RAND()
    LIMIT 1
  ");
  if (empty($랜덤['name'])) {
    시전_채팅응답("{$내무기} +{$내강화} 시전!\n\n❌ 공격 가능한 대상이 없어요.\n(신입 3일 제외 · 무기 없음 포함 · +10 이상 · 버프 {$단소_버프타수공격기준}타 이상)");
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

$대상강화 = (int)($대상정보['enhance'] ?? 0);
$대상무기 = trim((string)($대상정보['item'] ?? ''));
$무기없음 = ($대상무기 === '');
$무기조건충족 = ($대상강화 >= 10 && !$무기없음);
if (!$무기없음 && !$무기조건충족) {
  $대상타수행 = db_select("SELECT COALESCE(SUM(tasu), 0) AS total_tasu FROM tb_msg WHERE nickname = '{$대상_esc}' AND DATE(regdate) = CURDATE()");
  $대상버프타수 = (int)($대상타수행['total_tasu'] ?? 0);
  if ($대상버프타수 < $단소_버프타수공격기준) {
    시전_채팅응답("❌ {$대상닉}은(는) 무기 +10 미만이고 버프 타수 {$대상버프타수}타({$단소_버프타수공격기준}타 미만)라 공격할 수 없어요.");
    exit;
  }
}

$총뺏은냥 = 0;
$총자동스왑횟수 = 0;
$총차감타수 = 0;
$성공횟수 = 0;
$방어횟수 = 0;
$크리티컬방어횟수 = 0;
$마지막방어_크리티컬 = false;
$마지막방어_차감보호 = 0;
$마지막방어_남은보호 = 0;
$한도외시전횟수 = 0;
$한도차단멘트 = '';
$구간첫시전 = true;
$보호뚫림있음 = false;
$자숙패널티 = ['횟수' => 0, '게임냥' => 0, '보유냥' => 0, '마지막끝' => ''];
$버프타행 = db_select("SELECT COALESCE(SUM(tasu), 0) AS total FROM tb_msg WHERE nickname = '{$시전자_esc}' AND DATE(regdate) = CURDATE()");
$현재버프타 = (int)($버프타행['total'] ?? 0);
if ($현재버프타 <= $단소_버프타_하한) {
  시전_채팅응답("❌ 단소 시전 불가\n오늘 버프 타수 {$현재버프타}타 · 시전 후 100타 미만이 되는 시전은 할 수 없어요.\n(시전 1회당 버프 타수 -1)");
  exit;
}
$단소_시전타수차감 = function () use ($시전자_esc, &$총차감타수, &$현재버프타) {
  db_query("INSERT INTO tb_msg (nickname, msg, tasu, regdate) VALUES ('{$시전자_esc}', '', -1, NOW())");
  $총차감타수++;
  $현재버프타--;
};

$배치시작행 = db_select("SELECT point FROM tb_member WHERE name = '{$대상_esc}' LIMIT 1");
$배치시작_상대게임냥 = (int)($배치시작행['point'] ?? 0);
$배치_스왑만 = ($배치시작_상대게임냥 <= 0);

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

  if ($현재버프타 <= $단소_버프타_하한) {
    $한도차단멘트 = "🪈 단소 시전 중단\n❌ 버프 타수가 100타 이하로 떨어지는 시전은 불가해요.\n현재 버프 타수: {$현재버프타}타";
    break;
  }

  $단소_rs = db_query("SELECT magic_used, magic_window, IFNULL(extra_uses, 0) AS extra_uses, extra_reset_date FROM tb_member WHERE name = '{$시전자_esc}' LIMIT 1");
  $단소행 = $단소_rs ? db_assoc($단소_rs) : null;
  $단소사용 = 0;
  $구간시작 = null;
  $과다횟수 = 0;
  if (is_array($단소행)) {
    $단소사용 = (int)($단소행['magic_used'] ?? 0);
    $구간시작 = $단소행['magic_window'] ?? null;
    $오늘 = date('Y-m-d');
    $리셋일 = trim($단소행['extra_reset_date'] ?? '');
    if ($리셋일 === '' || $리셋일 < $오늘) {
      db_query("UPDATE tb_member SET extra_uses = 0, extra_reset_date = CURDATE() WHERE name = '{$시전자_esc}'");
      $과다횟수 = 0;
    } else {
      $과다횟수 = (int)($단소행['extra_uses'] ?? 0);
    }
  }

  $지금 = time();
  if ($구간시작 === null || $구간시작 === '' || $지금 >= strtotime($구간시작) + ($쿨타임 * 3600)) {
    $단소사용 = 0;
    db_query("UPDATE tb_member SET magic_used = 0, magic_window = NOW() WHERE name = '{$시전자_esc}'");
  }
  if ($단소사용 > $구간당시전한도) {
    db_query("UPDATE tb_member SET magic_used = {$구간당시전한도}, magic_window = NOW() WHERE name = '{$시전자_esc}'");
    $단소사용 = $구간당시전한도;
  }

  $한도외시전 = false;
  $한도외 = 무기_한도외_상태($내강화, $과다횟수);

  if ($단소사용 >= $구간당시전한도) {
    $다음리셋 = $구간시작 ? strtotime($구간시작) + ($쿨타임 * 3600) : $지금 + ($쿨타임 * 3600);
    $남은분 = (int)ceil(($다음리셋 - $지금) / 60);
    if (empty($한도외['allow_pay']) && empty($한도외['unlimited'])) {
      if (!empty($한도외['exhausted'])) {
        $한도차단멘트 = "{$내무기} +{$내강화} 시전! ❌ 한도 소진.\n오늘 한도 외 ".(int)$한도외['max']."회 소진(지정 시전 포함·자정 리셋). {$남은분}분 후 무료 한도 리셋.";
      } else {
        $한도차단멘트 = "{$내무기} +{$내강화} 시전! ❌ 한도 소진.\n한도 외 사용은 1강부터 가능. {$남은분}분 후 무료 한도 리셋.";
      }
      break;
    }
    if (!empty($한도외['exhausted'])) {
      $한도차단멘트 = "{$내무기} +{$내강화} 시전! ❌ 한도 소진.\n오늘 한도 외 ".(int)$한도외['max']."회 소진(지정 시전 포함·자정 리셋). {$남은분}분 후 무료 한도 리셋.";
      break;
    }
    db_query("UPDATE tb_member SET extra_uses = IFNULL(extra_uses, 0) + 1 WHERE name = '{$시전자_esc}'");
    $한도외시전 = true;
    $한도외시전횟수++;
  }

  $과다결제함 = $한도외시전;

  if ($is지목시전 && !$과다결제함) {
    $쿨시간 = (int)$쿨타임;
    if ($쿨시간 <= 0) $쿨시간 = 3;
    $지목행 = db_select("SELECT COUNT(*) AS cnt FROM tb_damege WHERE 공격자 = '{$시전자_esc}' AND 유형 = '단소지목' AND regdate >= DATE_SUB(NOW(), INTERVAL {$쿨시간} HOUR)");
    $지목사용 = (int)($지목행['cnt'] ?? 0);
    if ($지목사용 >= 2) {
      $한도차단멘트 = "🪈 +15 단소 지목 시전은 3시간에 2회까지만 가능해요.\n지목 없이 '.시전'으로 랜덤 시전은 남은 횟수 내에서 가능해요.\n(한도 외 시전 시에는 지목 제한 없음)";
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
      $단소_시전타수차감();
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

  $냥값 = $단소_강탈금액($내강화);
  $상대행 = db_select("SELECT point, newpoint FROM tb_member WHERE name = '{$대상_esc}' LIMIT 1");
  $상대보유 = (int)($상대행['point'] ?? 0);
  $실제뺏은양 = 0;

  if ($냥값 > 0) {
    if ($배치_스왑만 || $상대보유 <= 0) {
      if (!function_exists('스왑_본방_단소_자동스왑')) {
        require_once __DIR__ . '/../game/swap.inc.php';
      }
      $스왑결과 = 스왑_본방_단소_자동스왑($대상닉);
      if (!empty($스왑결과['ok'])) {
        $총자동스왑횟수++;
      }
    } else {
      $실제뺏은양 = min($냥값, $상대보유);
      if ($실제뺏은양 > 0) {
        db_query("UPDATE tb_member SET point = point - {$실제뺏은양} WHERE name = '{$대상_esc}'");
        db_query("UPDATE tb_member SET point = point + {$실제뺏은양} WHERE name = '{$시전자_esc}'");
        $총뺏은냥 += $실제뺏은양;
      }
    }
  }

  if (!$과다결제함) {
    db_query("UPDATE tb_member SET magic_used = magic_used + 1 WHERE name = '{$시전자_esc}'");
  }
  $단소_시전타수차감();

  $단소유형 = $is지목시전 ? '단소지목' : '단소';
  db_query("INSERT INTO tb_damege SET 공격자 = '{$시전자_esc}', 피해자 = '{$대상_esc}', 유형 = '{$단소유형}', 피해 = '{$실제뺏은양}', regdate = NOW()");

  // if ($실제뺏은양 > 0 && $구간첫시전) {
  //   $단소멘트 = addslashes("✨ [ {$두자리닉넴} ] +{$내강화} {$스타일} {$내무기} 시전중\n🎯 피해자: {$대상닉} ({$실제뺏은양}냥)");
  //   db_query("
  //     INSERT INTO tb_lotto_info
  //     SET status = 0,
  //         msg = '{$단소멘트}',
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
if ($총자동스왑횟수 > 0) {
  $멘트 .= "\n💱 {$대상닉} 본방냥 자동스왑 {$총자동스왑횟수}회";
}
if ($총뺏은냥 > 0) {
  $멘트 .= "\n".($보호뚫림있음 ? "🕳️ 보호를 뚫고 총 ".number_format($총뺏은냥) . "냥 강탈!" : "총 ".number_format($총뺏은냥) . "냥 강탈!");
}
if ($총차감타수 > 0) {
  $멘트 .= "\n📉 본인 버프 타수 -{$총차감타수}타";
}
if ($한도외시전횟수 > 0) {
  $과다최종행 = db_select("SELECT IFNULL(extra_uses, 0) AS extra_uses, extra_reset_date FROM tb_member WHERE name = '{$시전자_esc}' LIMIT 1");
  $과다최종 = (int)($과다최종행['extra_uses'] ?? 0);
  $과다리셋일 = trim((string)($과다최종행['extra_reset_date'] ?? ''));
  if ($과다리셋일 === '' || $과다리셋일 < date('Y-m-d')) {
    $과다최종 = 0;
  }
  $한도외최종 = 무기_한도외_상태($내강화, $과다최종);
  $멘트 .= "\n(한도 외 {$한도외시전횟수}회 · 버프 타수만 차감";
  if (!empty($한도외최종['unlimited'])) {
    $멘트 .= " · 오늘 {$과다최종}회 사용·무제한)";
  } elseif ((int)($한도외최종['max'] ?? 0) > 0) {
    $멘트 .= " · 오늘 한도 외 남은 ".(int)$한도외최종['remain']."회)";
  } else {
    $멘트 .= ')';
  }
}
if ($한도차단멘트 !== '' && $실행횟수 < $시전횟수) {
  $멘트 .= "\n⚠️ 한도/제한으로 {$실행횟수}회만 진행됨";
}
시전_채팅응답($멘트);
exit;
