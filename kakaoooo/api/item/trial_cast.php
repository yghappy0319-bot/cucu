<?php
/**
 * 무기 +10 맛보기 시전 (웹 enchant.php)
 * - 단소/활: 공격 1회 (한도·내구도·버프타수 차감 없음, 실제 효과 적용)
 * - 마법: 보호 / 버프(시전) 50% 랜덤
 */

if (!function_exists('무기_맛보기_최대')) {
  function 무기_맛보기_최대() {
    return 3;
  }
}

if (!function_exists('무기_맛보기_스키마_확인')) {
  function 무기_맛보기_스키마_확인() {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    $col = @db_select("SHOW COLUMNS FROM tb_member LIKE 'trial_casts'");
    if (empty($col)) {
      @db_query("ALTER TABLE tb_member ADD COLUMN trial_casts INT NOT NULL DEFAULT 0 COMMENT '맛보기 시전 잔여'");
    }
    $initCol = @db_select("SHOW COLUMNS FROM tb_member LIKE 'trial_casts_init'");
    if (empty($initCol)) {
      @db_query("ALTER TABLE tb_member ADD COLUMN trial_casts_init TINYINT NOT NULL DEFAULT 0 COMMENT '맛보기 부여완료'");
    }
    @db_query("
      UPDATE tb_member
      SET trial_casts = 3, trial_casts_init = 1
      WHERE IFNULL(enhance, 0) >= 10
        AND TRIM(IFNULL(item, '')) != ''
        AND IFNULL(trial_casts_init, 0) = 0
    ");
  }
}

if (!function_exists('무기_맛보기_잔여')) {
  function 무기_맛보기_잔여($닉) {
    무기_맛보기_스키마_확인();
    $esc = addslashes(trim((string)$닉));
    if ($esc === '') {
      return 0;
    }
    $row = db_select("SELECT trial_casts, enhance, item FROM tb_member WHERE name = '{$esc}' LIMIT 1");
    if (empty($row)) {
      return 0;
    }
    $잔여 = max(0, (int)($row['trial_casts'] ?? 0));
    if ($잔여 < 1 && (int)($row['enhance'] ?? 0) >= 10 && trim((string)($row['item'] ?? '')) !== '') {
      $init = db_select("SELECT trial_casts_init FROM tb_member WHERE name = '{$esc}' LIMIT 1");
      if ((int)($init['trial_casts_init'] ?? 0) === 0) {
        $max = (int)무기_맛보기_최대();
        db_query("UPDATE tb_member SET trial_casts = {$max}, trial_casts_init = 1 WHERE name = '{$esc}' AND IFNULL(trial_casts_init, 0) = 0");
        $잔여 = $max;
      }
    }
    return $잔여;
  }
}

if (!function_exists('무기_맛보기_부여_10강')) {
  /** +10 최초 달성 시 3회 부여 (이미 부여된 적 있으면 스킵) */
  function 무기_맛보기_부여_10강($닉, $이전강화, $다음강화) {
    if ((int)$이전강화 >= 10 || (int)$다음강화 < 10) {
      return;
    }
    무기_맛보기_스키마_확인();
    $esc = addslashes(trim((string)$닉));
    if ($esc === '') {
      return;
    }
    $max = (int)무기_맛보기_최대();
    db_query("UPDATE tb_member SET trial_casts = {$max}, trial_casts_init = 1 WHERE name = '{$esc}' AND IFNULL(trial_casts_init, 0) = 0");
  }
}

if (!function_exists('무기_맛보기_소비')) {
  function 무기_맛보기_소비($닉) {
    무기_맛보기_스키마_확인();
    $esc = addslashes(trim((string)$닉));
    global $conn;
    $rs = db_query("UPDATE tb_member SET trial_casts = trial_casts - 1 WHERE name = '{$esc}' AND trial_casts > 0");
    if (!$rs || (int)mysqli_affected_rows($conn) < 1) {
      return false;
    }
    return true;
  }
}

if (!function_exists('무기_맛보기_랜덤대상_단소')) {
  function 무기_맛보기_랜덤대상_단소($시전자_esc) {
    $기준 = 300;
    $row = db_select("
      SELECT m.name
      FROM tb_member m
      WHERE m.name != '{$시전자_esc}' AND m.status = 0
        AND m.regdate <= DATE_SUB(NOW(), INTERVAL 3 DAY)
        AND (
          TRIM(IFNULL(m.item, '')) = ''
          OR (IFNULL(m.enhance, 0) >= 10 AND TRIM(IFNULL(m.item, '')) != '')
          OR (
            SELECT " . 버프타_SQL_select_expr('msg', 'tasu') . " FROM tb_msg
            WHERE nickname = m.name AND DATE(regdate) = CURDATE()
          ) >= {$기준}
        )
      ORDER BY RAND()
      LIMIT 1
    ");
    return trim((string)($row['name'] ?? ''));
  }
}

if (!function_exists('무기_맛보기_랜덤대상_활')) {
  function 무기_맛보기_랜덤대상_활($시전자_esc) {
    $row = db_select("
      SELECT m.name
      FROM tb_member m
      INNER JOIN (
        SELECT nickname, " . 버프타_SQL_select_expr('msg', 'tasu') . " AS total_tasu
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
    return trim((string)($row['name'] ?? ''));
  }
}

if (!function_exists('무기_맛보기_랜덤대상_일반')) {
  function 무기_맛보기_랜덤대상_일반($시전자_esc) {
    $row = db_select("
      SELECT name FROM tb_member
      WHERE status = 0 AND regdate <= DATE_SUB(NOW(), INTERVAL 3 DAY)
        AND name != '{$시전자_esc}'
      ORDER BY RAND()
      LIMIT 1
    ");
    return trim((string)($row['name'] ?? ''));
  }
}

if (!function_exists('무기_맛보기_단소_강탈금액')) {
  function 무기_맛보기_단소_강탈금액($강화) {
    $비율표 = [
      10 => 0.000034, 11 => 0.000034, 12 => 0.000034, 13 => 0.000034,
      14 => 0.00017,  15 => 0.00017,  16 => 0.00017,
      17 => 0.00051,  18 => 0.00051,  19 => 0.00051,
      20 => 0.00102,
    ];
    $g = min(20, max(10, (int)$강화));
    $p = (float)($비율표[$g] ?? 0);
    if ($p <= 0 || !function_exists('전체냥기준금액')) {
      return 0;
    }
    return (int)전체냥기준금액($p);
  }
}

if (!function_exists('무기_맛보기_활_흡수타수')) {
  function 무기_맛보기_활_흡수타수($강화) {
    if (function_exists('활_흡수타수')) {
      $v = 활_흡수타수($강화);
      return ($v === null) ? 0 : (int)$v;
    }
    $g = (int)$강화;
    if ($g < 1) return 0;
    if ($g <= 10) return 1;
    if ($g <= 20) return rand(1, 2);
    if ($g <= 30) return rand(2, 3);
    if ($g <= 40) return rand(3, 4);
    if ($g <= 50) return rand(5, 6);
    $extra = (int)floor(($g - 41) / 10);
    return rand(5 + ($extra * 2), 6 + ($extra * 2));
  }
}

if (!function_exists('무기_맛보기_공격_단소')) {
  function 무기_맛보기_공격_단소($시전자닉, $내강화) {
    $시전자_esc = addslashes($시전자닉);
    $대상닉 = 무기_맛보기_랜덤대상_단소($시전자_esc);
    if ($대상닉 === '') {
      return ['ok' => false, 'data' => '❌ 맛보기 공격 대상이 없어요.'];
    }
    $대상_esc = addslashes($대상닉);
    $대상정보 = db_select("SELECT protect, regdate FROM tb_member WHERE name = '{$대상_esc}' LIMIT 1");
    if (!$대상정보) {
      return ['ok' => false, 'data' => '❌ 대상을 찾을 수 없어요.'];
    }
    if (function_exists('신입_공격면역') && 신입_공격면역($대상정보['regdate'] ?? '')) {
      return ['ok' => false, 'data' => '❌ 신입 회원은 맛보기 대상에서 제외돼요.'];
    }

    $보호수 = (int)($대상정보['protect'] ?? 0);
    if ($보호수 >= 1) {
      $차감 = (mt_rand(1, 100) <= 10) ? min(3, $보호수) : 1;
      db_query("UPDATE tb_member SET protect = GREATEST(protect - {$차감}, 0) WHERE name = '{$대상_esc}'");
      db_query("INSERT INTO tb_damege SET 공격자 = '{$시전자_esc}', 피해자 = '{$대상_esc}', 유형 = '맛보기단소', 피해 = '0', regdate = NOW()");
      return [
        'ok' => true,
        'cast_type' => 'attack',
        'weapon_kind' => 'danso',
        'target' => $대상닉,
        'blocked' => true,
        'amount' => 0,
        'amount_label' => '냥',
        'data' => "🎯 맛보기 공격 (단소)\n대상: {$대상닉}\n🛡️ 보호로 막혔어요.",
      ];
    }

    $냥값 = 무기_맛보기_단소_강탈금액($내강화);
    $상대행 = db_select("SELECT point FROM tb_member WHERE name = '{$대상_esc}' LIMIT 1");
    $상대보유 = (int)($상대행['point'] ?? 0);
    $실제 = min($냥값, max(0, $상대보유));
    if ($실제 > 0) {
      db_query("UPDATE tb_member SET point = point - {$실제} WHERE name = '{$대상_esc}'");
      db_query("UPDATE tb_member SET point = point + {$실제} WHERE name = '{$시전자_esc}'");
    }
    db_query("INSERT INTO tb_damege SET 공격자 = '{$시전자_esc}', 피해자 = '{$대상_esc}', 유형 = '맛보기단소', 피해 = '{$실제}', regdate = NOW()");

    $금액표시 = function_exists('냥축약표시') ? 냥축약표시($실제, '냥') : (number_format($실제) . '냥');
    return [
      'ok' => true,
      'cast_type' => 'attack',
      'weapon_kind' => 'danso',
      'target' => $대상닉,
      'blocked' => false,
      'amount' => $실제,
      'amount_fmt' => $금액표시,
      'amount_label' => '냥',
      'data' => "🎯 맛보기 공격 (단소)\n대상: {$대상닉}\n강탈: {$금액표시}",
    ];
  }
}

if (!function_exists('무기_맛보기_공격_활')) {
  function 무기_맛보기_공격_활($시전자닉, $내강화) {
    $시전자_esc = addslashes($시전자닉);
    $대상닉 = 무기_맛보기_랜덤대상_활($시전자_esc);
    if ($대상닉 === '') {
      return ['ok' => false, 'data' => '❌ 맛보기 공격 대상이 없어요.\n(버프 300타 이상 · 신입 3일 제외)'];
    }
    $대상_esc = addslashes($대상닉);
    $대상정보 = db_select("SELECT protect, regdate FROM tb_member WHERE name = '{$대상_esc}' LIMIT 1");
    if (!$대상정보) {
      return ['ok' => false, 'data' => '❌ 대상을 찾을 수 없어요.'];
    }
    if (function_exists('신입_공격면역') && 신입_공격면역($대상정보['regdate'] ?? '')) {
      return ['ok' => false, 'data' => '❌ 신입 회원은 맛보기 대상에서 제외돼요.'];
    }

    $보호수 = (int)($대상정보['protect'] ?? 0);
    if ($보호수 >= 1) {
      $차감 = (mt_rand(1, 100) <= 10) ? min(3, $보호수) : 1;
      db_query("UPDATE tb_member SET protect = GREATEST(protect - {$차감}, 0) WHERE name = '{$대상_esc}'");
      db_query("INSERT INTO tb_damege SET 공격자 = '{$시전자_esc}', 피해자 = '{$대상_esc}', 유형 = '맛보기활', 피해 = '0', regdate = NOW()");
      return [
        'ok' => true,
        'cast_type' => 'attack',
        'weapon_kind' => 'bow',
        'target' => $대상닉,
        'blocked' => true,
        'amount' => 0,
        'amount_label' => '타',
        'data' => "🎯 맛보기 공격 (활)\n대상: {$대상닉}\n🛡️ 보호로 막혔어요.",
      ];
    }

    $타수행 = db_select("SELECT " . 버프타_SQL_select_expr('msg', 'tasu') . " AS total_tasu FROM tb_msg WHERE nickname = '{$대상_esc}' AND DATE(regdate) = CURDATE()");
    $대상타수 = (int)($타수행['total_tasu'] ?? 0);
    if ($대상타수 <= 0) {
      return ['ok' => false, 'data' => '❌ 대상의 버프 타수가 없어요.'];
    }
    $흡수 = min(무기_맛보기_활_흡수타수($내강화), $대상타수);
    db_query("INSERT INTO tb_msg (nickname, msg, tasu, regdate) VALUES ('{$대상_esc}', '', -{$흡수}, NOW())");
    db_query("INSERT INTO tb_msg (nickname, msg, tasu, regdate) VALUES ('{$시전자_esc}', '', {$흡수}, NOW())");
    db_query("INSERT INTO tb_damege SET 공격자 = '{$시전자_esc}', 피해자 = '{$대상_esc}', 유형 = '맛보기활', 피해 = '{$흡수}', regdate = NOW()");

    return [
      'ok' => true,
      'cast_type' => 'attack',
      'weapon_kind' => 'bow',
      'target' => $대상닉,
      'blocked' => false,
      'amount' => $흡수,
      'amount_label' => '타',
      'data' => "🎯 맛보기 공격 (활)\n대상: {$대상닉}\n흡수: {$흡수}타",
    ];
  }
}

if (!function_exists('무기_맛보기_마법_보호')) {
  function 무기_맛보기_마법_보호($시전자닉, $내강화) {
    $시전자_esc = addslashes($시전자닉);
    $내강화 = (int)$내강화;
    $대상닉 = 무기_맛보기_랜덤대상_일반($시전자_esc);
    if ($대상닉 === '') {
      return ['ok' => false, 'data' => '❌ 맛보기 보호 대상이 없어요.'];
    }
    $대상_esc = addslashes($대상닉);
    $지급결과 = function_exists('마법_보호_한도별_지급합계')
      ? 마법_보호_한도별_지급합계($내강화, 1)
      : ['amount' => max(1, $내강화), 'crits' => 0];
    $지급보호 = max(0, (int)$지급결과['amount']);
    $크리 = ((int)($지급결과['crits'] ?? 0) > 0);
    db_query("UPDATE tb_member SET protect = IFNULL(protect, 0) + {$지급보호} WHERE name = '{$대상_esc}'");
    $행 = db_select("SELECT protect FROM tb_member WHERE name = '{$대상_esc}' LIMIT 1");
    $새보호 = (int)($행['protect'] ?? 0);
    db_query("INSERT INTO tb_damege SET 공격자 = '{$시전자_esc}', 피해자 = '{$대상_esc}', 유형 = '맛보기보호', 피해 = '{$지급보호}', regdate = NOW()");
    $머리 = $크리 ? '💥크리' : '🛡️';
    return [
      'ok' => true,
      'cast_type' => 'protect',
      'weapon_kind' => 'magic',
      'target' => $대상닉,
      'protect_after' => $새보호,
      'data' => "{$머리} 맛보기 보호 (마법)\n대상: {$대상닉}\n보호 +{$지급보호} (현재 {$새보호})",
    ];
  }
}

if (!function_exists('무기_맛보기_마법_버프')) {
  function 무기_맛보기_마법_버프($시전자닉, $내강화) {
    $시전자_esc = addslashes($시전자닉);
    $대상닉 = 무기_맛보기_랜덤대상_일반($시전자_esc);
    if ($대상닉 === '') {
      return ['ok' => false, 'data' => '❌ 맛보기 버프 대상이 없어요.'];
    }
    $대상_esc = addslashes($대상닉);
    $마법지속초 = ((int)$내강화 <= 18) ? 3600 : 10800;
    $시전문구 = ($마법지속초 >= 10800) ? '3시간 마법(타수2배)' : '1시간 마법(타수2배)';
    $기존 = db_select("SELECT enddate FROM tb_item_use WHERE nickname = '{$대상_esc}' AND item = '마법' LIMIT 1");
    $지금_str = date('Y-m-d H:i:s');
    if (!empty($기존['enddate']) && $기존['enddate'] > $지금_str) {
      $유효 = date('Y-m-d H:i:s', strtotime($기존['enddate']) + $마법지속초);
      db_query("UPDATE tb_item_use SET enddate = '{$유효}', regdate = NOW() WHERE nickname = '{$대상_esc}' AND item = '마법'");
    } else {
      $유효 = date('Y-m-d H:i:s', time() + $마법지속초);
      db_query("INSERT INTO tb_item_use SET nickname = '{$대상_esc}', item = '마법', enddate = '{$유효}', regdate = NOW()");
    }
    db_query("INSERT INTO tb_damege SET 공격자 = '{$시전자_esc}', 피해자 = '{$대상_esc}', 유형 = '맛보기마법', 피해 = '마법', regdate = NOW()");
    $종료 = date('m-d H:i', strtotime($유효));
    return [
      'ok' => true,
      'cast_type' => 'buff',
      'weapon_kind' => 'magic',
      'target' => $대상닉,
      'buff_until' => $종료,
      'data' => "✨ 맛보기 버프 (마법)\n대상: {$대상닉}\n{$시전문구} · 종료 {$종료}",
    ];
  }
}

if (!function_exists('무기_맛보기_시전_실행')) {
  /**
   * @return array{ok:bool, data?:string, cast_type?:string, target?:string, trial_left?:int, point?:int, point_fmt?:string}
   */
  function 무기_맛보기_시전_실행($시전자닉, $내무기, $내강화, $스타일 = '') {
    $시전자닉 = trim((string)$시전자닉);
    $내무기 = trim((string)$내무기);
    $내강화 = (int)$내강화;

    if ($내강화 < 10) {
      return ['ok' => false, 'data' => '❌ +10강 이상부터 맛보기 시전이 가능해요.'];
    }
    if ($내무기 === '') {
      return ['ok' => false, 'data' => '❌ 무기가 없어요.'];
    }
    $잔여 = 무기_맛보기_잔여($시전자닉);
    if ($잔여 < 1) {
      return ['ok' => false, 'data' => '❌ 맛보기 시전 횟수를 모두 사용했어요.'];
    }

    if ($내무기 === '🪈단소') {
      $결과 = 무기_맛보기_공격_단소($시전자닉, $내강화);
    } elseif ($내무기 === '🏹활' || $내무기 === '🏹 활') {
      $결과 = 무기_맛보기_공격_활($시전자닉, $내강화);
    } elseif ($내무기 === '🪄마법' || $내무기 === '🪄 마법') {
      $결과 = (mt_rand(1, 2) === 1)
        ? 무기_맛보기_마법_보호($시전자닉, $내강화)
        : 무기_맛보기_마법_버프($시전자닉, $내강화);
    } else {
      return ['ok' => false, 'data' => '❌ 단소·활·마법만 맛보기 시전이 가능해요.'];
    }

    if (empty($결과['ok'])) {
      return $결과;
    }
    if (!무기_맛보기_소비($시전자닉)) {
      return ['ok' => false, 'data' => '❌ 맛보기 시전 처리에 실패했어요.'];
    }

    $남은 = 무기_맛보기_잔여($시전자닉);
    $스타일표 = trim((string)$스타일);
    $무기표 = ($스타일표 !== '' ? $스타일표 . ' ' : '') . $내무기 . " +{$내강화}";
    $결과['data'] = "👅 맛보기 시전 · {$무기표}\n\n" . ($결과['data'] ?? '') . "\n\n(맛보기 {$남은}/" . 무기_맛보기_최대() . "회 남음)";
    $결과['trial_left'] = $남은;
    $결과['trial_max'] = (int)무기_맛보기_최대();
    $결과['type'] = 'trial_cast';

    $esc = addslashes($시전자닉);
    $포인트행 = db_select("SELECT point FROM tb_member WHERE name = '{$esc}' LIMIT 1");
    $결과['point'] = (int)($포인트행['point'] ?? 0);
    if (function_exists('냥축약표시')) {
      $결과['point_fmt'] = 냥축약표시($결과['point'], '냥');
    }

    return $결과;
  }
}
