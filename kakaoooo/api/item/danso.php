<?php
// 단소 시전: 대상 조건 — 신입(가입 3일 미만) 제외. 무기 없어도 공격 가능. +10 미만·무기 보유 시 버프 300타 이상 필요.
// 한도: 1시간당 강화×2. 냥·버프타 차감 없음.
// 보호가 있으면 냥 강탈·강제스왑 없음(보호만 차감). 무보호일 때만 냥 강탈 (0냥+무기/채굴이면 본방냥 10% 강제스왑만).
$단소_버프타수공격기준 = 300;
$시전자_esc = addslashes($두자리닉넴);
$시전횟수 = isset($시전횟수) ? max(1, (int)$시전횟수) : 1;
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
  $대상정보 = db_select("SELECT protect, enhance, item, regdate, IFNULL(게임포기, 0) AS 게임포기 FROM tb_member WHERE name = '{$대상_esc}' LIMIT 1");
  if (!$대상정보) {
    시전_채팅응답("❌ {$대상닉} 회원을 찾을 수 없어요.");
    exit;
  }
  if ((int)($대상정보['게임포기'] ?? 0) === 1
    || (function_exists('게임포기_바로가기숨김인가') && 게임포기_바로가기숨김인가($대상닉))) {
    시전_채팅응답("❌ {$대상닉}은(는) 게임포기 중이라 시전 대상에서 제외돼요.\n(타수·냥 차감 없음)");
    exit;
  }
} else {
  if (function_exists('tb_member_게임포기_컬럼_보장')) {
    tb_member_게임포기_컬럼_보장();
  }
  $랜덤 = db_select("
    SELECT m.name
    FROM tb_member m
    WHERE m.name != '{$시전자_esc}' AND m.status = 0
      AND IFNULL(m.게임포기, 0) = 0
      AND m.regdate <= DATE_SUB(NOW(), INTERVAL 3 DAY)
      AND (
        TRIM(IFNULL(m.item, '')) = ''
        OR (IFNULL(m.enhance, 0) >= 10 AND TRIM(IFNULL(m.item, '')) != '')
        OR (
          SELECT " . 버프타_SQL_select_expr('msg', 'tasu') . " FROM tb_msg
          WHERE nickname = m.name AND DATE(regdate) = CURDATE()
        ) >= {$단소_버프타수공격기준}
      )
    ORDER BY RAND()
    LIMIT 1
  ");
  if (empty($랜덤['name'])) {
    시전_채팅응답("{$내무기} +{$내강화} 시전!\n\n❌ 공격 가능한 대상이 없어요.\n(신입 3일 제외 · 게임포기 제외 · 무기 없음 포함 · +10 이상 · 버프 {$단소_버프타수공격기준}타 이상)");
    exit;
  }
  $대상닉 = $랜덤['name'];
  $대상_esc = addslashes($대상닉);
  $대상정보 = db_select("SELECT protect, enhance, item, regdate, IFNULL(게임포기, 0) AS 게임포기 FROM tb_member WHERE name = '{$대상_esc}' LIMIT 1");
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
  $대상타수행 = db_select("SELECT " . 버프타_SQL_select_expr('msg', 'tasu') . " AS total_tasu FROM tb_msg WHERE nickname = '{$대상_esc}' AND DATE(regdate) = CURDATE()");
  $대상버프타수 = (int)($대상타수행['total_tasu'] ?? 0);
  if ($대상버프타수 < $단소_버프타수공격기준) {
    시전_채팅응답("❌ {$대상닉}은(는) 무기 +10 미만이고 버프 타수 {$대상버프타수}타({$단소_버프타수공격기준}타 미만)라 공격할 수 없어요.");
    exit;
  }
}

$총뺏은냥 = '0';
$성공횟수 = 0;
$방어횟수 = 0;
$반사횟수 = 0;
$총반사타수 = 0;
$크리티컬방어횟수 = 0;
$마지막방어_크리티컬 = false;
$마지막방어_차감보호 = 0;
$마지막방어_남은보호 = 0;
$총차감보호 = 0;
$한도차단멘트 = '';
$구간첫시전 = true;
$자동스왑문구목록 = [];
$희귀보너스문구목록 = [];
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

  $쿨시간 = isset($쿨타임) ? (int)$쿨타임 : 1;
  if ($쿨시간 <= 0) $쿨시간 = 1;
  $구간 = 무기_시전구간_적용($두자리닉넴, $쿨시간, true);
  $단소사용 = (int)($구간['used'] ?? 0);
  $구간시작 = $구간['window'] ?? null;
  $지금 = time();

  if ($단소사용 >= $구간당시전한도) {
    $다음리셋 = (int)($구간['reset_at'] ?? 0);
    if ($다음리셋 <= 0) {
      $다음리셋 = $구간시작 ? strtotime($구간시작) + ($쿨시간 * 3600) : $지금 + ($쿨시간 * 3600);
    }
    $남은분 = (int)ceil(($다음리셋 - $지금) / 60);
    $한도차단멘트 = "{$내무기} +{$내강화} 시전! ❌ 한도 소진.\n1시간 {$구간당시전한도}회까지. {$남은분}분 후 초기화.";
    break;
  }

  if ($is지목시전) {
    $쿨시간 = (int)$쿨타임;
    if ($쿨시간 <= 0) $쿨시간 = 1;
    $지목행 = db_select("SELECT COUNT(*) AS cnt FROM tb_damege WHERE 공격자 = '{$시전자_esc}' AND 유형 = '단소지목' AND regdate >= DATE_SUB(NOW(), INTERVAL {$쿨시간} HOUR)");
    $지목사용 = (int)($지목행['cnt'] ?? 0);
    if ($지목사용 >= 2) {
      $한도차단멘트 = "🪈 단소 지목 시전은 1시간에 2회까지만 가능해요.\n지목 없이 '.시전'으로 랜덤 시전은 남은 횟수 내에서 가능해요.";
      break;
    }
  }

  $대상정보 = db_select("SELECT protect, enhance, item FROM tb_member WHERE name = '{$대상_esc}' LIMIT 1");

  $보호수 = (int)($대상정보['protect'] ?? 0);
  if ($보호수 >= 1) {
    $크리티컬 = (mt_rand(1, 100) <= $크리티컬막);
    $차감보호 = function_exists('무기_시전_보호차감량')
      ? 무기_시전_보호차감량($내강화, $보호수, $크리티컬)
      : (function ($강, $보호, $크) {
          $강 = max(0, (int)$강);
          $보호 = max(0, (int)$보호);
          if ($보호 <= 0 || $강 <= 0) return 0;
          $기본 = $크 ? ($강 * 2) : mt_rand($강, $강 * 2);
          return min($기본, $보호);
        })($내강화, $보호수, $크리티컬);
    $차감보호 = max(0, (int)$차감보호);
    db_query("UPDATE tb_member SET protect = GREATEST(protect - {$차감보호}, 0) WHERE name = '{$대상_esc}'");
    db_query("UPDATE tb_member SET magic_used = magic_used + 1 WHERE name = '{$시전자_esc}'");
    if ($크리티컬) {
      $크리티컬방어횟수++;
    }
    $마지막방어_크리티컬 = $크리티컬;
    $마지막방어_차감보호 = $차감보호;
    $마지막방어_남은보호 = max($보호수 - $차감보호, 0);
    $총차감보호 += $차감보호;
    $방어횟수++;
    if (function_exists('단소_시전_희귀팟보너스')) {
      $희귀 = 단소_시전_희귀팟보너스($두자리닉넴);
      if (!empty($희귀['ok']) && !empty($희귀['msg'])) {
        $희귀보너스문구목록[] = (string)$희귀['msg'];
      }
    }
    continue;
  }

  // 방어자 무기 강화 기준 반사 — 상대에게 타수 +10 지급 · 강탈 없음
  $대상강화_반사 = (int)($대상정보['enhance'] ?? 0);
  if (function_exists('무기_반사판정') && 무기_반사판정($대상강화_반사)) {
    db_query("UPDATE tb_member SET magic_used = magic_used + 1 WHERE name = '{$시전자_esc}'");
    $반사지급타수 = 10;
    db_query("INSERT INTO tb_msg (nickname, msg, tasu, regdate) VALUES ('{$대상_esc}', '', {$반사지급타수}, NOW())");
    $총반사타수 += $반사지급타수;
    $반사횟수++;
    $단소유형 = $is지목시전 ? '단소지목반사' : '단소반사';
    db_query("INSERT INTO tb_damege SET 공격자 = '{$시전자_esc}', 피해자 = '{$대상_esc}', 유형 = '{$단소유형}', 피해 = '{$반사지급타수}', regdate = NOW()");
    if (function_exists('단소_시전_희귀팟보너스')) {
      $희귀 = 단소_시전_희귀팟보너스($두자리닉넴);
      if (!empty($희귀['ok']) && !empty($희귀['msg'])) {
        $희귀보너스문구목록[] = (string)$희귀['msg'];
      }
    }
    continue;
  }

  $냥값 = function_exists('단소_강탈금액')
    ? (function_exists('냥_정수문자열') ? 냥_정수문자열(단소_강탈금액($내강화)) : (ltrim(preg_replace('/\D/', '', (string)단소_강탈금액($내강화)), '0') ?: '0'))
    : '0';
  $상대행 = db_select("
    SELECT
      CONCAT('N', CAST(IFNULL(point, 0) AS CHAR)) AS point,
      CAST(IFNULL(newpoint, 0) AS CHAR) AS newpoint,
      TRIM(IFNULL(item, '')) AS item
    FROM tb_member
    WHERE name = '{$대상_esc}'
    LIMIT 1
  ");
  $상대보유 = function_exists('냥_금액원문_정규화')
    ? 냥_금액원문_정규화($상대행['point'] ?? 'N0')
    : 냥_정수문자열($상대행['point'] ?? '0');
  if (isset($상대보유[0]) && $상대보유[0] === '-') {
    $상대보유 = '0';
  }
  $실제뺏은양 = '0';

  $냥값양수 = ($냥값 !== '0' && !(function_exists('bccomp') && bccomp($냥값, '0', 0) <= 0));
  $상대보유양수 = ($상대보유 !== '0' && !(function_exists('bccomp') && bccomp($상대보유, '0', 0) <= 0));

  // 상대 게임냥 0 + (무기|채굴) 보유 → 본방냥 10% 강제 스왑만 (이번 턴 강탈 없음)
  if ($냥값양수 && !$상대보유양수) {
    $무기보유 = trim((string)($상대행['item'] ?? '')) !== '';
    $채굴보유 = false;
    $채굴행 = @db_select("SELECT IFNULL(mining_tool, 0) AS lv FROM tb_member_mining WHERE nick = '{$대상_esc}' LIMIT 1");
    if ($채굴행 && (int)($채굴행['lv'] ?? 0) > 0) {
      $채굴보유 = true;
    }
    if ($무기보유 || $채굴보유) {
      if (!function_exists('스왑_본방_단소_자동스왑')) {
        $swapPath = __DIR__ . '/../game/swap.inc.php';
        if (is_file($swapPath)) {
          require_once $swapPath;
        }
      }
      if (function_exists('스왑_본방_단소_자동스왑')) {
        $스왑결과 = 스왑_본방_단소_자동스왑($대상닉);
        if (!empty($스왑결과['ok'])) {
          $자동스왑문구목록[] = (string)($스왑결과['msg'] ?? '');
        }
      }
    }
    $실제뺏은양 = '0';
  } elseif ($냥값양수 && $상대보유양수) {
    if (function_exists('bccomp') && bccomp($냥값, $상대보유, 0) > 0) {
      $실제뺏은양 = $상대보유;
    } else {
      $실제뺏은양 = $냥값;
    }
    if ($실제뺏은양 !== '0' && !(function_exists('bccomp') && bccomp($실제뺏은양, '0', 0) <= 0)) {
      $뺏sql = function_exists('냥_SQL정수') ? 냥_SQL정수($실제뺏은양) : preg_replace('/\D/', '', $실제뺏은양);
      db_query("UPDATE tb_member SET point = point - {$뺏sql} WHERE name = '{$대상_esc}' AND point >= {$뺏sql} LIMIT 1");
      global $conn;
      $차감ok = ($conn instanceof mysqli) ? ((int)mysqli_affected_rows($conn) > 0) : true;
      if ($차감ok) {
        db_query("UPDATE tb_member SET point = point + {$뺏sql} WHERE name = '{$시전자_esc}'");
        $총뺏은냥 = function_exists('냥_금액_문자열합')
          ? 냥_금액_문자열합((string)$총뺏은냥, (string)$실제뺏은양)
          : (function_exists('bcadd') ? bcadd((string)$총뺏은냥, (string)$실제뺏은양, 0) : (string)((int)$총뺏은냥 + (int)$실제뺏은양));
      } else {
        $실제뺏은양 = '0';
      }
    }
  } else {
    $실제뺏은양 = '0';
  }

  db_query("UPDATE tb_member SET magic_used = magic_used + 1 WHERE name = '{$시전자_esc}'");

  $단소유형 = $is지목시전 ? '단소지목' : '단소';
  db_query("INSERT INTO tb_damege SET 공격자 = '{$시전자_esc}', 피해자 = '{$대상_esc}', 유형 = '{$단소유형}', 피해 = '{$실제뺏은양}', regdate = NOW()");

  $성공횟수++;
  if (function_exists('단소_시전_희귀팟보너스')) {
    $희귀 = 단소_시전_희귀팟보너스($두자리닉넴);
    if (!empty($희귀['ok']) && !empty($희귀['msg'])) {
      $희귀보너스문구목록[] = (string)$희귀['msg'];
    }
  }
}

$실행횟수 = $성공횟수 + $방어횟수 + $반사횟수;
if ($실행횟수 === 0) {
  시전_채팅응답($한도차단멘트 !== '' ? $한도차단멘트 : "{$내무기} +{$내강화} 시전!\n\n❌ 시전할 수 없습니다.");
  exit;
}

$멘트 = 자숙_시전보호_패널티_요약문구($자숙패널티, '시전', isset($단위) ? $단위 : '냥');
$멘트 .= "✨ +{$내강화} {$스타일} {$내무기} → {$대상닉}";
$요약 = [];
if ($시전횟수 > 1) {
  $요약[] = "{$시전횟수}회(✓{$성공횟수}·🛡{$방어횟수}" . ($반사횟수 > 0 ? "·🪞{$반사횟수}" : '') . ")";
} elseif ($반사횟수 > 0) {
  $요약[] = "🪞반사{$반사횟수}";
}
if ($방어횟수 > 0) {
  if ($시전횟수 === 1 && $성공횟수 === 0 && $반사횟수 === 0) {
    $멘트 .= $마지막방어_크리티컬
      ? "\n💥크리 {$마지막방어_차감보호}·남{$마지막방어_남은보호}"
      : "\n🛡방어 {$마지막방어_차감보호}·남{$마지막방어_남은보호}";
  } else {
    $방어요약 = "🛡{$방어횟수}·보호-{$총차감보호}";
    if ($크리티컬방어횟수 > 0) {
      $방어요약 .= "·크{$크리티컬방어횟수}";
    }
    $요약[] = $방어요약;
  }
}
if ($총반사타수 > 0) {
  $요약[] = "🪞타수+{$총반사타수}";
}
$총뺏은양수 = ($총뺏은냥 !== '0' && $총뺏은냥 !== 0
  && !(function_exists('bccomp') && bccomp((string)$총뺏은냥, '0', 0) <= 0));
if ($총뺏은양수) {
  $강탈표시 = function_exists('게임냥_안전표시')
    ? 게임냥_안전표시($총뺏은냥, '냥')
    : (function_exists('냥축약표시')
      ? 냥축약표시($총뺏은냥)
      : ((function_exists('냥_숫자콤마') ? 냥_숫자콤마($총뺏은냥) : $총뺏은냥) . '냥'));
  $요약[] = "{$강탈표시} 강탈";
} elseif ($성공횟수 > 0 && $자동스왑문구목록 === []) {
  $요약[] = '강탈없음';
}
if ($요약 !== []) {
  $멘트 .= "\n" . implode(' · ', $요약);
}
$자동스왑문구목록 = array_values(array_filter(array_map('trim', $자동스왑문구목록)));
if ($자동스왑문구목록 !== []) {
  $자동스왑문구목록 = array_values(array_unique($자동스왑문구목록));
  $멘트 .= "\n" . implode("\n", $자동스왑문구목록);
}
if ($한도차단멘트 !== '' && $실행횟수 < $시전횟수) {
  $멘트 .= "\n⚠️ {$실행횟수}회만 진행";
}
$희귀보너스문구목록 = array_values(array_unique(array_filter(array_map('trim', $희귀보너스문구목록))));
if ($희귀보너스문구목록 !== []) {
  $멘트 .= "\n" . implode("\n", $희귀보너스문구목록);
}
시전_채팅응답($멘트);
exit;
