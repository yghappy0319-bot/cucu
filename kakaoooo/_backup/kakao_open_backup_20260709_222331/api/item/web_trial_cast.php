<?php
/**
 * 무기 강화 웹 — 하루 맛보기 시전
 * - 단소/활: 공격 1회/일
 * - 마법: 시전·보호 각 1회/일
 * 한도·내구도·본인 버프타수/냥 비용 없음 · 대상 효과는 실제 적용
 */

if (!function_exists('web_trial_cast_스키마_확인')) {
  function web_trial_cast_스키마_확인() {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    $col = @db_select("SHOW COLUMNS FROM tb_member LIKE 'web_trial_cast_date'");
    if (empty($col)) {
      @db_query("ALTER TABLE tb_member ADD COLUMN web_trial_cast_date DATE NULL DEFAULT NULL COMMENT '웹 맛보기 시전/공격일'");
    }
    $colProtect = @db_select("SHOW COLUMNS FROM tb_member LIKE 'web_trial_protect_date'");
    if (empty($colProtect)) {
      @db_query("ALTER TABLE tb_member ADD COLUMN web_trial_protect_date DATE NULL DEFAULT NULL COMMENT '웹 맛보기 보호일'");
    }
  }
}

if (!function_exists('web_trial_cast_status')) {
  function web_trial_cast_status($닉) {
    web_trial_cast_스키마_확인();
    $esc = addslashes(trim((string)$닉));
    if ($esc === '') {
      return [
        'cast_available' => 0,
        'cast_used_today' => 0,
        'protect_available' => 0,
        'protect_used_today' => 0,
        'available' => 0,
        'used_today' => 0,
      ];
    }
    $row = db_select("SELECT web_trial_cast_date, web_trial_protect_date FROM tb_member WHERE name = '{$esc}' LIMIT 1");
    $오늘 = date('Y-m-d');
    $castUsed = (!empty($row['web_trial_cast_date']) && (string)$row['web_trial_cast_date'] === $오늘);
    $protectUsed = (!empty($row['web_trial_protect_date']) && (string)$row['web_trial_protect_date'] === $오늘);
    return [
      'cast_available' => $castUsed ? 0 : 1,
      'cast_used_today' => $castUsed ? 1 : 0,
      'protect_available' => $protectUsed ? 0 : 1,
      'protect_used_today' => $protectUsed ? 1 : 0,
      'available' => $castUsed ? 0 : 1,
      'used_today' => $castUsed ? 1 : 0,
    ];
  }
}

if (!function_exists('web_trial_cast_소비')) {
  function web_trial_cast_소비($닉, $kind = 'cast') {
    web_trial_cast_스키마_확인();
    global $conn;
    $esc = addslashes(trim((string)$닉));
    $오늘 = date('Y-m-d');
    $kind = strtolower(trim((string)$kind));
    $col = ($kind === 'protect' || $kind === '보호') ? 'web_trial_protect_date' : 'web_trial_cast_date';
    $rs = db_query("UPDATE tb_member SET {$col} = '{$오늘}' WHERE name = '{$esc}' AND ({$col} IS NULL OR {$col} < '{$오늘}')");
    return $rs && isset($conn) && (int)mysqli_affected_rows($conn) > 0;
  }
}

if (!function_exists('web_trial_cast_단소_강탈금액')) {
  function web_trial_cast_단소_강탈금액($강화) {
    if ((int)$강화 < 10) {
      return 0;
    }
    return (int)전체냥기준금액(0.0001);
  }
}

if (!function_exists('web_trial_cast_활_흡수타수')) {
  function web_trial_cast_활_흡수타수($강화) {
    $g = (int)$강화;
    if ($g >= 20) return rand(15, 30);
    if ($g >= 19) return rand(11, 20);
    if ($g >= 18) return rand(9, 15);
    if ($g >= 17) return rand(7, 12);
    if ($g >= 16) return rand(5, 9);
    if ($g >= 15) return rand(1, 7);
    if ($g >= 14) return rand(1, 6);
    if ($g >= 13) return rand(1, 4);
    if ($g >= 12) return rand(1, 4);
    if ($g >= 11) return rand(1, 2);
    if ($g >= 10) return rand(1, 2);
    return 0;
  }
}

if (!function_exists('web_trial_cast_랜덤대상_단소')) {
  function web_trial_cast_랜덤대상_단소($시전자_esc) {
    $행 = db_select("
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
          ) >= 300
        )
      ORDER BY RAND()
      LIMIT 1
    ");
    return trim((string)($행['name'] ?? ''));
  }
}

if (!function_exists('web_trial_cast_랜덤대상_활')) {
  function web_trial_cast_랜덤대상_활($시전자_esc) {
    $행 = db_select("
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
    return trim((string)($행['name'] ?? ''));
  }
}

if (!function_exists('web_trial_cast_랜덤대상_일반')) {
  function web_trial_cast_랜덤대상_일반($시전자_esc) {
    $행 = db_select("
      SELECT name FROM tb_member
      WHERE status = 0 AND regdate <= DATE_SUB(NOW(), INTERVAL 3 DAY)
      ORDER BY RAND()
      LIMIT 1
    ");
    return trim((string)($행['name'] ?? ''));
  }
}

if (!function_exists('web_trial_cast_공격_단소')) {
  function web_trial_cast_공격_단소($시전자닉, $내강화) {
    $시전자_esc = addslashes($시전자닉);
    $대상닉 = web_trial_cast_랜덤대상_단소($시전자_esc);
    if ($대상닉 === '') {
      return ['ok' => false, 'data' => '❌ 맛보기 공격 대상이 없어요.'];
    }
    $대상_esc = addslashes($대상닉);
    $대상정보 = db_select("SELECT protect, regdate FROM tb_member WHERE name = '{$대상_esc}' LIMIT 1");
    if (empty($대상정보)) {
      return ['ok' => false, 'data' => '❌ 대상 회원을 찾을 수 없어요.'];
    }
    if (function_exists('신입_공격면역') && 신입_공격면역($대상정보['regdate'] ?? '')) {
      return ['ok' => false, 'data' => '❌ 신입 회원은 맛보기 대상에서 제외돼요.'];
    }

    $보호수 = (int)($대상정보['protect'] ?? 0);
    if ($보호수 >= 1 && mt_rand(1, 100) <= 95) {
      db_query("INSERT INTO tb_damege SET 공격자 = '{$시전자_esc}', 피해자 = '{$대상_esc}', 유형 = '맛보기단소', 피해 = '0', regdate = NOW()");
      return [
        'ok' => true,
        'cast_kind' => 'attack',
        'blocked' => true,
        'target' => $대상닉,
        'data' => "🎯 맛보기 공격 (단소)\n대상: {$대상닉}\n🛡️ 보호로 막혔어요.",
      ];
    }

    $냥값 = web_trial_cast_단소_강탈금액($내강화);
    $상대행 = db_select("SELECT point, newpoint FROM tb_member WHERE name = '{$대상_esc}' LIMIT 1");
    $상대보유 = (int)($상대행['point'] ?? 0);
    $스왑됨 = false;
    $실제 = 0;

    if ($냥값 > 0) {
      if ($상대보유 <= 0) {
        if (!function_exists('스왑_본방_단소_자동스왑')) {
          require_once __DIR__ . '/../game/swap.inc.php';
        }
        $스왑결과 = 스왑_본방_단소_자동스왑($대상닉);
        $스왑됨 = !empty($스왑결과['ok']);
      } else {
        $실제 = min($냥값, $상대보유);
        if ($실제 > 0) {
          db_query("UPDATE tb_member SET point = point - {$실제} WHERE name = '{$대상_esc}'");
          db_query("UPDATE tb_member SET point = point + {$실제} WHERE name = '{$시전자_esc}'");
        }
      }
    }
    db_query("INSERT INTO tb_damege SET 공격자 = '{$시전자_esc}', 피해자 = '{$대상_esc}', 유형 = '맛보기단소', 피해 = '{$실제}', regdate = NOW()");

    if ($스왑됨) {
      $결과문구 = "🎯 맛보기 공격 (단소)\n대상: {$대상닉}\n💱 본방냥 자동스왑";
    } elseif ($실제 > 0) {
      $금액표시 = function_exists('냥축약표시') ? 냥축약표시($실제) : number_format($실제) . '냥';
      $결과문구 = "🎯 맛보기 공격 (단소)\n대상: {$대상닉}\n강탈: {$금액표시}";
    } else {
      $결과문구 = "🎯 맛보기 공격 (단소)\n대상: {$대상닉}\n피해 없음";
    }
    return [
      'ok' => true,
      'cast_kind' => 'attack',
      'blocked' => false,
      'target' => $대상닉,
      'amount' => $실제,
      'data' => $결과문구,
    ];
  }
}

if (!function_exists('web_trial_cast_공격_활')) {
  function web_trial_cast_공격_활($시전자닉, $내강화) {
    $시전자_esc = addslashes($시전자닉);
    $대상닉 = web_trial_cast_랜덤대상_활($시전자_esc);
    if ($대상닉 === '') {
      return ['ok' => false, 'data' => '❌ 맛보기 공격 대상이 없어요.\n(버프 300타 이상 · 신입 3일 제외)'];
    }
    $대상_esc = addslashes($대상닉);
    $대상정보 = db_select("SELECT protect, regdate FROM tb_member WHERE name = '{$대상_esc}' LIMIT 1");
    if (empty($대상정보)) {
      return ['ok' => false, 'data' => '❌ 대상 회원을 찾을 수 없어요.'];
    }
    if (function_exists('신입_공격면역') && 신입_공격면역($대상정보['regdate'] ?? '')) {
      return ['ok' => false, 'data' => '❌ 신입 회원은 맛보기 대상에서 제외돼요.'];
    }

    $보호수 = (int)($대상정보['protect'] ?? 0);
    if ($보호수 >= 1 && mt_rand(1, 100) <= 95) {
      db_query("INSERT INTO tb_damege SET 공격자 = '{$시전자_esc}', 피해자 = '{$대상_esc}', 유형 = '맛보기활', 피해 = '0', regdate = NOW()");
      return [
        'ok' => true,
        'cast_kind' => 'attack',
        'blocked' => true,
        'target' => $대상닉,
        'data' => "🎯 맛보기 공격 (활)\n대상: {$대상닉}\n🛡️ 보호로 막혔어요.",
      ];
    }

    $타수행 = db_select("SELECT COALESCE(SUM(tasu), 0) AS total_tasu FROM tb_msg WHERE nickname = '{$대상_esc}' AND DATE(regdate) = CURDATE()");
    $대상타수 = (int)($타수행['total_tasu'] ?? 0);
    if ($대상타수 <= 0) {
      return ['ok' => false, 'data' => '❌ 대상의 오늘 버프 타수가 없어요.'];
    }

    $흡수 = min(web_trial_cast_활_흡수타수($내강화), $대상타수);
    db_query("INSERT INTO tb_msg (nickname, msg, tasu, regdate) VALUES ('{$대상_esc}', '', -{$흡수}, NOW())");
    db_query("INSERT INTO tb_msg (nickname, msg, tasu, regdate) VALUES ('{$시전자_esc}', '', {$흡수}, NOW())");
    db_query("INSERT INTO tb_damege SET 공격자 = '{$시전자_esc}', 피해자 = '{$대상_esc}', 유형 = '맛보기활', 피해 = '{$흡수}', regdate = NOW()");

    return [
      'ok' => true,
      'cast_kind' => 'attack',
      'blocked' => false,
      'target' => $대상닉,
      'amount' => $흡수,
      'data' => "🎯 맛보기 공격 (활)\n대상: {$대상닉}\n흡수: {$흡수}타",
    ];
  }
}

if (!function_exists('web_trial_cast_마법_보호')) {
  function web_trial_cast_마법_보호($시전자닉) {
    $시전자_esc = addslashes($시전자닉);
    $대상닉 = web_trial_cast_랜덤대상_일반($시전자_esc);
    if ($대상닉 === '') {
      return ['ok' => false, 'data' => '❌ 맛보기 보호 대상이 없어요.'];
    }
    $대상_esc = addslashes($대상닉);
    $멤버 = db_select("SELECT idx, protect, regdate FROM tb_member WHERE name = '{$대상_esc}' LIMIT 1");
    if (empty($멤버['idx'])) {
      return ['ok' => false, 'data' => '❌ 대상 회원을 찾을 수 없어요.'];
    }
    if (function_exists('신입_공격면역') && 신입_공격면역($멤버['regdate'] ?? '')) {
      return ['ok' => false, 'data' => '❌ 신입 회원은 맛보기 대상에서 제외돼요.'];
    }

    $새보호 = (int)($멤버['protect'] ?? 0) + 1;
    db_query("UPDATE tb_member SET protect = {$새보호} WHERE name = '{$대상_esc}'");
    db_query("INSERT INTO tb_damege SET 공격자 = '{$시전자_esc}', 피해자 = '{$대상_esc}', 유형 = '맛보기보호', 피해 = '1', regdate = NOW()");

    return [
      'ok' => true,
      'cast_kind' => 'protect',
      'target' => $대상닉,
      'data' => "🛡️ 맛보기 보호 (마법)\n대상: {$대상닉}\n보호 +1 (현재 {$새보호})",
    ];
  }
}

if (!function_exists('web_trial_cast_마법_버프')) {
  function web_trial_cast_마법_버프($시전자닉, $내강화) {
    $시전자_esc = addslashes($시전자닉);
    $대상닉 = web_trial_cast_랜덤대상_일반($시전자_esc);
    if ($대상닉 === '') {
      return ['ok' => false, 'data' => '❌ 맛보기 버프 대상이 없어요.'];
    }
    $대상_esc = addslashes($대상닉);
    $가입행 = db_select("SELECT regdate FROM tb_member WHERE name = '{$대상_esc}' LIMIT 1");
    if (function_exists('신입_공격면역') && 신입_공격면역($가입행['regdate'] ?? '')) {
      return ['ok' => false, 'data' => '❌ 신입 회원은 맛보기 대상에서 제외돼요.'];
    }

    if ($내강화 <= 18) {
      $시전문구 = '1시간 마법(타수2배)';
      $마법지속초 = 3600;
    } else {
      $시전문구 = '3시간 마법(타수2배)';
      $마법지속초 = 10800;
    }

    $아이템명 = '마법';
    $기존 = db_select("SELECT enddate FROM tb_item_use WHERE nickname = '{$대상_esc}' AND item = '{$아이템명}' LIMIT 1");
    $지금_str = date('Y-m-d H:i:s');
    if (!empty($기존['enddate']) && $기존['enddate'] > $지금_str) {
      $유효시간 = date('Y-m-d H:i:s', strtotime($기존['enddate']) + $마법지속초);
    } else {
      $유효시간 = date('Y-m-d H:i:s', time() + $마법지속초);
    }
    if (!empty($기존)) {
      db_query("UPDATE tb_item_use SET enddate = '{$유효시간}', regdate = NOW() WHERE nickname = '{$대상_esc}' AND item = '{$아이템명}'");
    } else {
      db_query("INSERT INTO tb_item_use SET nickname = '{$대상_esc}', item = '{$아이템명}', enddate = '{$유효시간}', regdate = NOW()");
    }
    db_query("INSERT INTO tb_damege SET 공격자 = '{$시전자_esc}', 피해자 = '{$대상_esc}', 유형 = '맛보기마법', 피해 = '마법', regdate = NOW()");

    $종료 = date('m-d H:i', strtotime($유효시간));
    $대상문구 = ($대상닉 === $시전자닉) ? '본인' : $대상닉;
    return [
      'ok' => true,
      'cast_kind' => 'buff',
      'target' => $대상닉,
      'data' => "✨ 맛보기 버프 (마법)\n대상: {$대상문구}\n{$시전문구} · 종료 {$종료}",
    ];
  }
}

if (!function_exists('web_trial_cast_execute')) {
  function web_trial_cast_execute($닉, array $회원, $cast_kind = 'cast') {
    $닉 = trim((string)$닉);
    $esc = addslashes($닉);
    $내무기 = trim((string)($회원['item'] ?? ''));
    $내강화 = (int)($회원['enhance'] ?? 0);
    $스타일 = trim((string)($회원['style'] ?? ''));

    if ($내무기 === '') {
      return ['ok' => false, 'data' => '❌ 보유 무기가 없어요.'];
    }
    if ($내강화 < 10) {
      return ['ok' => false, 'data' => '❌ +10강 이상부터 맛보기가 가능해요.'];
    }

    $상태 = web_trial_cast_status($닉);
    $kind = strtolower(trim((string)$cast_kind));
    $isMagic = ($내무기 === '🪄마법' || $내무기 === '🪄 마법');
    $isProtect = ($kind === 'protect' || $kind === '보호');

    if ($isMagic && $isProtect) {
      if (empty($상태['protect_available'])) {
        return ['ok' => false, 'data' => '❌ 오늘 맛보기 보호를 이미 사용했어요.\n내일 다시 이용할 수 있어요.'];
      }
    } elseif (empty($상태['cast_available'])) {
      $문구 = $isMagic ? '맛보기 시전' : '맛보기 공격';
      return ['ok' => false, 'data' => "❌ 오늘 {$문구}을(를) 이미 사용했어요.\n내일 다시 이용할 수 있어요."];
    }

    if ($내무기 === '🪈단소') {
      $결과 = web_trial_cast_공격_단소($닉, $내강화);
    } elseif ($내무기 === '🏹활' || $내무기 === '🏹 활') {
      $결과 = web_trial_cast_공격_활($닉, $내강화);
    } elseif ($isMagic) {
      if ($isProtect) {
        $결과 = web_trial_cast_마법_보호($닉);
      } elseif ($kind === '' || $kind === 'cast' || $kind === 'buff' || $kind === '시전') {
        $결과 = web_trial_cast_마법_버프($닉, $내강화);
      } else {
        return ['ok' => false, 'data' => '❌ 맛보기 종류를 확인해주세요. (시전/보호)'];
      }
    } else {
      return ['ok' => false, 'data' => '❌ 단소·활·마법만 맛보기가 가능해요.'];
    }

    if (empty($결과['ok'])) {
      return $결과;
    }

    $소비종류 = ($isMagic && $isProtect) ? 'protect' : 'cast';
    if (!web_trial_cast_소비($닉, $소비종류)) {
      return ['ok' => false, 'data' => '❌ 맛보기 사용 처리에 실패했어요.'];
    }

    $무기표 = trim($스타일 . ' ' . $내무기);
    $완료문구 = '오늘 맛보기 사용 완료 · 내일 다시 가능';
    if ($isMagic && $isProtect) {
      $완료문구 = '오늘 맛보기 보호 사용 완료 · 내일 다시 가능';
    } elseif ($isMagic) {
      $완료문구 = '오늘 맛보기 시전 사용 완료 · 내일 다시 가능';
    } elseif ($내무기 === '🪈단소' || $내무기 === '🏹활' || $내무기 === '🏹 활') {
      $완료문구 = '오늘 맛보기 공격 사용 완료 · 내일 다시 가능';
    }
    $결과['data'] = "👅 맛보기 · {$무기표}\n\n" . ($결과['data'] ?? '') . "\n\n({$완료문구})";
    $결과['type'] = 'trial_cast';

    $새상태 = web_trial_cast_status($닉);
    $결과['trial_cast_available'] = (int)$새상태['cast_available'];
    $결과['trial_protect_available'] = (int)$새상태['protect_available'];
    $결과['trial_available'] = (int)$새상태['cast_available'];
    $결과['trial_used_today'] = (int)$새상태['cast_used_today'];

    $포인트행 = db_select("SELECT point FROM tb_member WHERE name = '{$esc}' LIMIT 1");
    $결과['point'] = (int)($포인트행['point'] ?? 0);
    if (function_exists('냥축약표시')) {
      $결과['point_fmt'] = 냥축약표시($결과['point'], '냥');
    }
    return $결과;
  }
}
