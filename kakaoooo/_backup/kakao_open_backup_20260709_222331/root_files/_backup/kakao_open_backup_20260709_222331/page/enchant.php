<?php
/**
 * 무기 강화 웹 (info2.php .강화 로직을 UI화)
 * 접속 시 ?code=xxx 로 tb_member.code와 매칭해 인증
 * (탭 락 없음)
 *
 * URL:
 *   /page/enchant.php?code=XXXX
 *
 * 제공 액션 (AJAX JSON):
 *   action=status         — 내 냥/무기/강화/수호 등 조회
 *   action=buy            — 무기 구매 (weapon=랜덤|단소|활|마법)
 *   action=enhance        — 현재 무기 +1 강화 시도
 *   action=enhance_batch  — 강화 연속 (times=1~10, 기본 10, 파손·최대 달성 시 중단)
 *   action=use_suho       — 강화 수호 전환(buy=0) / 구매(buy=1, 냥 1회 +1)
 *   action=use_eunchong   — 은총 1개 사용 (+14강 이상 무기에 한해 5분 버프, 성공분모 1/10)
 *   action=trial_cast     — 맛보기 (단소/활 공격 1회/일, 마법 시전·보호 각 1회/일)
 *   action=history        — 내 강화 이력 (limit, 기본 15)
 */

// ----- 강화 설정 (info2.php와 동기화) -----
$ENCHANT_할인적용 = false;  // 50% 할인
$ENCHANT_테스트모드 = false;
$ENCHANT_첫무기구매비용 = $ENCHANT_테스트모드 ? 100 : 100000;
$ENCHANT_강화최대 = 20;
$ENCHANT_강화비용표 = [
    0 => 10000,   1 => 30000,   2 => 50000,   3 => 70000,   4 => 90000,
    5 => 150000,  6 => 300000,  7 => 450000,  8 => 600000,  9 => 750000,
    10 => 1000000, 11 => 1500000, 12 => 2000000, 13 => 2500000, 14 => 3000000,
    15 => 3500000, 16 => 4000000, 17 => 4500000, 18 => 5000000, 19 => 5500000,
];
$ENCHANT_성공확률표 = [
    0 => 990,
    1 => 900,
    2 => 800,
    3 => 400,
    4 => 600,
    5 => 500,
    6 => 300,
    7 => 200,
    8 => 100,
    9 => 50,
    10 => 30,
    11 => 20,
    12 => 10,
    13 => 5,
    14 => 1,
    15 => 5,
    16 => 1,
    17 => 5,
    18 => 1,
    19 => 1,
];
$ENCHANT_분모가져오기 = function ($단계) {
    if ($단계 === 15) return 10000; // 0.05% (분자 5)
    if ($단계 === 16) return 10000; // 0.01% (분자 1)
    if ($단계 === 17) return 100000; // 0.005% (분자 5)
    if ($단계 === 18) return 100000; // 0.001% (분자 1)
    if ($단계 === 19) return 1000000; // 0.0001% (분자 1)
    return 1000;
};
$ENCHANT_무기맵 = ['단소' => '🪈단소', '활' => '🏹활', '마법' => '🪄마법'];

// ----- 유틸 -----
function enchant_json($data) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function enchant_auth($code) {
    $code = is_string($code) ? trim($code) : '';
    if ($code === '') return null;
    $esc = addslashes($code);
    $row = db_select("SELECT idx, name, point, item, enhance, style, enhance_suho, 은총, 은총개수 FROM tb_member WHERE code = '{$esc}' LIMIT 1");
    if (empty($row['name'])) return null;
    return $row;
}
function enchant_suho_count($nick) {
    $esc = addslashes($nick);
    $r = db_select("SELECT COUNT(*) AS cnt FROM tb_member_item WHERE nick = '{$esc}' AND itemname = '수호' AND status = 0");
    return (int)($r['cnt'] ?? 0);
}
function enchant_cost_next($현재강화, $전역할인, $은총활성 = false, $현재무기 = '', $닉 = '') {
    global $ENCHANT_강화비용표, $ENCHANT_테스트모드;
    $도전모드 = ($현재강화 === 19) ? 강화20_도전모드_정보($현재무기, $현재강화, $닉) : null;
    if ($도전모드) {
        return (int)$도전모드['비용'];
    }
    return 강화비용_산출($현재강화, $ENCHANT_강화비용표, $전역할인, $은총활성, $ENCHANT_테스트모드);
}
function enchant_success_rate_str($현재강화, $은총활성, $현재무기 = '', $닉 = '') {
    global $ENCHANT_성공확률표, $ENCHANT_분모가져오기;
    if ($현재강화 >= 20) return '—';
    $도전모드 = ($현재강화 === 19) ? 강화20_도전모드_정보($현재무기, $현재강화, $닉) : null;
    if ($도전모드) {
        return 강화20_도전모드_성공확률문구($은총활성, $도전모드);
    }
    $분자 = (int)($ENCHANT_성공확률표[$현재강화] ?? 1);
    $분모 = (int)$ENCHANT_분모가져오기($현재강화);
    if ($은총활성) $분모 = (int)max(1, floor($분모 / 10));
    if ($분자 < 1) $분자 = 1;
    if ($분자 > $분모) $분자 = $분모;
    return rtrim(rtrim(number_format(($분자 / $분모) * 100, 6, '.', ''), '0'), '.') . '%';
}
function enchant_fmt_nyang($n) {
    $n = (int)$n;
    if (function_exists('냥축약표시')) {
        return 냥축약표시($n, '냥');
    }
    return number_format($n) . '냥';
}

/** 강화 UI 갱신용 상태 (status / enhance 응답 공용) */
function enchant_status_payload($닉, $회원 = null) {
    global $ENCHANT_할인적용;
    $닉_esc = addslashes($닉);
    if (!is_array($회원) || empty($회원['name'])) {
        $회원 = db_select("SELECT name, point, item, enhance, style, enhance_suho, 은총, 은총개수 FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
    }
    if (empty($회원['name'])) {
        return [];
    }
    $현재강화 = (int)($회원['enhance'] ?? 0);
    $현재무기 = trim((string)($회원['item'] ?? ''));
    $은총활성 = 강화_은총_활성($회원['은총'] ?? '');
    $내구도 = ($현재무기 !== '' && $현재강화 >= 10) ? (int)무기_최대내구도($현재무기, $현재강화) : 0;
    $수호시세 = 강화수호_회당비용();
    $은총_end = !empty($회원['은총']) ? (string)$회원['은총'] : '';
    $은총_left_sec = $은총활성 ? max(0, strtotime($회원['은총']) - time()) : 0;
    $다음비용 = enchant_cost_next($현재강화, $ENCHANT_할인적용, $은총활성, $현재무기, $닉);
    $맛보기상태 = web_trial_cast_status($닉);
    $point = (int)($회원['point'] ?? 0);
    return [
        'point'        => $point,
        'point_fmt'    => enchant_fmt_nyang($point),
        'item'         => $현재무기,
        'enhance'      => $현재강화,
        'style'        => trim((string)($회원['style'] ?? '')),
        'enhance_suho' => (int)($회원['enhance_suho'] ?? 0),
        '은총개수'     => (int)($회원['은총개수'] ?? 0),
        '은총활성'     => $은총활성 ? 1 : 0,
        '은총_end'     => $은총_end,
        '은총_left_sec'=> $은총_left_sec,
        'suho_item'    => enchant_suho_count($닉),
        'suho_price'   => $수호시세,
        'suho_price_fmt' => enchant_fmt_nyang($수호시세),
        'cost_next'    => $다음비용,
        'cost_next_fmt'=> enchant_fmt_nyang($다음비용),
        'success_rate' => enchant_success_rate_str($현재강화, $은총활성, $현재무기, $닉),
        'durability'   => $내구도,
        'trial_cast_available' => (int)$맛보기상태['cast_available'],
        'trial_protect_available' => (int)$맛보기상태['protect_available'],
    ];
}

/**
 * DB 최신 기준 강화 1회. 단일/연속(배치) 공용.
 * @return array{ok: bool, data?: string, body?: array}
 */
function enchant_perform_enhance_attempt($닉, $닉_esc) {
    global $ENCHANT_강화최대, $ENCHANT_할인적용, $ENCHANT_성공확률표, $ENCHANT_분모가져오기;
    $회원 = db_select("SELECT name, point, item, enhance, enhance_suho, style, 은총, enhance19_used_rolls FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
    if (empty($회원['name'])) {
        return ['ok' => false, 'data' => '❌ 회원을 찾을 수 없어요.'];
    }
    // config.php include 후 $닉 이 덮어써질 수 있어 DB 조회 결과를 항상 사용
    $강화주체닉 = trim((string)($회원['name'] ?? $닉));
    $은총활성 = 강화_은총_활성($회원['은총'] ?? '');

    $현재무기 = trim((string)($회원['item'] ?? ''));
    $현재강화 = (int)($회원['enhance'] ?? 0);

    if ($현재무기 === '') {
        return ['ok' => false, 'data' => '❌ 보유 무기가 없어요. 먼저 무기를 구매하세요.'];
    }
    if ($현재강화 >= $ENCHANT_강화최대) {
        return ['ok' => false, 'data' => "⚔️ {$현재무기} 이미 최대 강화 +{$ENCHANT_강화최대} 입니다."];
    }

    $도전모드 = ($현재강화 === 19) ? 강화20_도전모드_정보($현재무기, $현재강화, $강화주체닉) : null;
    $보유자_esc = $도전모드 ? addslashes($도전모드['보유자닉']) : '';

    $강화비용 = $도전모드
        ? (int)$도전모드['비용']
        : enchant_cost_next($현재강화, $ENCHANT_할인적용, $은총활성, $현재무기, $강화주체닉);
    $보유냥 = (int)($회원['point'] ?? 0);
    if ($보유냥 < $강화비용) {
        return ['ok' => false, 'data' => '❌ 강화에 필요한 냥이 부족해요. (' . enchant_fmt_nyang($강화비용) . ')'];
    }

    $자숙위반 = function_exists('자숙_강화위반_적용') ? 자숙_강화위반_적용($강화주체닉, '냥') : ['notice' => ''];
    $자숙위반안내 = (string)($자숙위반['notice'] ?? '');
    $자숙차감 = (($자숙위반['deduct_from'] ?? 'point') === 'newpoint') ? 0 : (int)($자숙위반['deduct'] ?? 0);

    $분자 = (int)($ENCHANT_성공확률표[$현재강화] ?? 1);
    $분모 = (int)$ENCHANT_분모가져오기($현재강화);
    if ($도전모드) {
        $분자 = 1;
        $분모 = 강화20_도전모드_성공분모($은총활성, $도전모드);
    } elseif ($은총활성) {
        $분모 = (int)max(1, floor($분모 / 10));
    }
    if ($분자 < 1) {
        $분자 = 1;
    }
    if ($분자 > $분모) {
        $분자 = $분모;
    }
    $강화19_사용목록 = [];
    if ($도전모드) {
        $주사위 = rand(1, $분모);
    } elseif ($현재강화 === 19) {
        강화19_사용주사위_날짜맞춤($닉_esc, $회원['enhance19_used_rolls'] ?? '');
        $강화19_사용목록 = 강화19_사용주사위_목록($회원['enhance19_used_rolls'] ?? '');
        $주사위 = 강화19_시도주사위($분모, $강화19_사용목록, $강화주체닉);
    } else {
        $주사위 = rand(1, $분모);
    }
    $성공 = ($주사위 <= $분자);
    $성공확률_문구 = $도전모드
        ? 강화20_도전모드_성공확률문구($은총활성, $도전모드)
        : rtrim(rtrim(number_format(($분자 / $분모) * 100, 6, '.', ''), '0'), '.') . '%';
    $도전안내 = $도전모드 ? "\n👑 +20 [{$도전모드['보유자닉']}] 탈취 도전" : '';
    $보상안내 = '';

    $rs = db_query("UPDATE tb_member SET point = point - {$강화비용} WHERE name = '{$닉_esc}'");
    if (!$rs) {
        return ['ok' => false, 'data' => '❌ 강화 처리 실패.'];
    }
    if ($도전모드) {
        $보상금 = 강화20_도전_보유자보상지급($보유자_esc, (int)$도전모드['보유자보상']);
        $소멸금 = (int)$도전모드['소멸'];
        $보상안내 = "\n💰 [{$도전모드['보유자닉']}] +" . enchant_fmt_nyang($보상금) . " · 소멸 " . enchant_fmt_nyang($소멸금);
    }

    if ($성공) {
        if ($현재강화 === 19 && !$도전모드) {
            강화19_사용주사위_초기화($닉_esc);
        }
        $탈취안내 = '';
        $역풍발생 = false;
        if ($도전모드) {
            $도전자결과 = 강화20_도전_성공시_도전자강화();
            $다음강화 = (int)$도전자결과['enhance'];
            if (!empty($도전자결과['탈취성공'])) {
                강화20_도전_성공시_보유자하향($도전모드['보유자닉'], $보유자_esc, $현재무기);
                $탈취안내 = "\n👑 +20 탈취! [{$도전모드['보유자닉']}] +20 → +19";
            } else {
                $역풍발생 = true;
                강화20_도전_역풍_본방알림($강화주체닉, $도전모드['보유자닉'], $현재무기);
                $탈취안내 = "\n⚡ 역풍! +20 탈취 실패(" . 강화20_도전_역풍확률_pct() . "%) · 도전자 +19 → +18";
            }
        } else {
            $다음강화 = $현재강화 + 1;
        }
        db_query("UPDATE tb_member SET `enhance` = {$다음강화}, 강화성공시간 = now() WHERE name = '{$닉_esc}'");

        if ($다음강화 >= 10) {
            db_query("UPDATE tb_member SET magic_used = 0, magic_window = NOW() WHERE name = '{$닉_esc}'");
            $현재아이템_trim = trim($현재무기);
            if ($현재아이템_trim === '🪄마법' || $현재아이템_trim === '🪄 마법') {
                db_query("UPDATE tb_member SET protect_used = 0, protect_reset_date = NOW() WHERE name = '{$닉_esc}'");
            }
            $내구도 = 무기_최대내구도($현재아이템_trim, $다음강화);
            if ($내구도 > 0) {
                $기존행 = db_select("SELECT durability FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
                $기존내구도 = isset($기존행['durability']) && $기존행['durability'] !== null ? (int)$기존행['durability'] : 0;
                if ($기존내구도 < $내구도) {
                    db_query("UPDATE tb_member SET durability = {$내구도} WHERE name = '{$닉_esc}'");
                }
            }
        }
        if ($다음강화 >= 15 && empty($역풍발생)) {
            $강화주체닉 = trim((string)($회원['name'] ?? $강화주체닉));
            $강화주체_esc = addslashes($강화주체닉);
            $로그 = addslashes("⚔️ [ {$강화주체닉} ] 강화 성공\n{$현재무기} +{$다음강화}");
            db_query("INSERT INTO tb_lotto_info SET status=0, msg='{$로그}', leverage=0, item='{$강화주체_esc}', regdate=NOW()");
        }

        if (function_exists('강화_이력_기록')) {
            강화_이력_기록([
                'nick'             => $강화주체닉,
                'channel'          => 'web',
                'item'             => $현재무기,
                'style'            => trim((string)($회원['style'] ?? '')),
                'enhance_before'   => $현재강화,
                'enhance_after'    => $다음강화,
                'result'           => 'success',
                'cost'             => $강화비용,
                'dice'             => $주사위,
                'dice_num'         => $분자,
                'dice_den'         => $분모,
                'rate'             => $성공확률_문구,
                'eunchong'         => $은총활성,
                'challenge_mode'   => (bool)$도전모드,
                'challenge_target' => $도전모드 ? ($도전모드['보유자닉'] ?? '') : '',
                'challenge_steal'  => $도전모드 ? !empty($도전자결과['탈취성공']) : null,
            ]);
        }

        return ['ok' => true, 'body' => [
            'ok'       => true,
            'type'     => 'enhance',
            'result'   => 'success',
            'data'     => $자숙위반안내 . "⚔️ 강화 성공! (주사위 {$주사위})\n{$현재무기} +{$현재강화} → +{$다음강화}{$도전안내}{$탈취안내}{$보상안내}\n(확률 {$성공확률_문구} · " . enchant_fmt_nyang($강화비용) . " 차감)",
            'item'     => $현재무기,
            'enhance'  => $다음강화,
            'point'    => $보유냥 - $자숙차감 - $강화비용,
            'point_fmt'=> enchant_fmt_nyang($보유냥 - $자숙차감 - $강화비용),
            'cost'     => $강화비용,
            'cost_fmt' => enchant_fmt_nyang($강화비용),
            'rate'     => $성공확률_문구,
            'dice'     => $주사위,
        ]];
    }
    if ($현재강화 === 19 && !$도전모드) {
        $강화19_사용목록[] = $주사위;
        강화19_사용주사위_저장($닉_esc, $강화19_사용목록);
    }
    $수호방지 = function_exists('강화실패_수호방지_적용')
        ? 강화실패_수호방지_적용($강화주체닉)
        : null;
    if ($수호방지 !== null) {
        $남은수호 = (int)$수호방지['enhance_suho'];
        $수호문구 = (string)($수호방지['msg_suffix'] ?? '');
        if (function_exists('강화_이력_기록')) {
            강화_이력_기록([
                'nick'           => $강화주체닉,
                'channel'        => 'web',
                'item'           => $현재무기,
                'style'          => trim((string)($회원['style'] ?? '')),
                'enhance_before' => $현재강화,
                'enhance_after'  => $현재강화,
                'result'         => 'fail_protect',
                'cost'           => $강화비용,
                'dice'           => $주사위,
                'dice_num'       => $분자,
                'dice_den'       => $분모,
                'rate'           => $성공확률_문구,
                'eunchong'       => $은총활성,
                'challenge_mode' => (bool)$도전모드,
                'challenge_target' => $도전모드 ? ($도전모드['보유자닉'] ?? '') : '',
                'suho_used'      => true,
                'suho_left'      => $남은수호,
            ]);
        }
        $아이템표시 = (int)($수호방지['suho_item_left'] ?? 0);
        return ['ok' => true, 'body' => [
            'ok'       => true,
            'type'     => 'enhance',
            'result'   => 'fail_protect',
            'data'     => $자숙위반안내 . "👼 강화 실패! 파손 방지{$수호문구}\n{$현재무기} +{$현재강화} 유지 · 남은 수호 {$남은수호}회{$도전안내}{$보상안내}\n(확률 {$성공확률_문구} · " . enchant_fmt_nyang($강화비용) . " 차감)",
            'item'         => $현재무기,
            'enhance'      => $현재강화,
            'enhance_suho' => $남은수호,
            'suho_item'    => $아이템표시,
            'suho_item_used' => !empty($수호방지['suho_item_used']) ? 1 : 0,
            'point'        => $보유냥 - $자숙차감 - $강화비용,
            'point_fmt'    => enchant_fmt_nyang($보유냥 - $자숙차감 - $강화비용),
            'cost'         => $강화비용,
            'cost_fmt'     => enchant_fmt_nyang($강화비용),
            'rate'         => $성공확률_문구,
            'dice'         => $주사위,
        ]];
    }
    if ($현재강화 === 19 && !$도전모드) {
        강화19_사용주사위_초기화($닉_esc);
    }
    db_query("UPDATE tb_member SET `item` = NULL, `enhance` = 0, `style` = '' WHERE name = '{$닉_esc}'");
    if (function_exists('강화_이력_기록')) {
        강화_이력_기록([
            'nick'           => $강화주체닉,
            'channel'        => 'web',
            'item'           => $현재무기,
            'style'          => trim((string)($회원['style'] ?? '')),
            'enhance_before' => $현재강화,
            'enhance_after'  => 0,
            'result'         => 'fail_break',
            'cost'           => $강화비용,
            'dice'           => $주사위,
            'dice_num'       => $분자,
            'dice_den'       => $분모,
            'rate'           => $성공확률_문구,
            'eunchong'       => $은총활성,
            'challenge_mode' => (bool)$도전모드,
            'challenge_target' => $도전모드 ? ($도전모드['보유자닉'] ?? '') : '',
        ]);
    }
    return ['ok' => true, 'body' => [
        'ok'       => true,
        'type'     => 'enhance',
        'result'   => 'fail_break',
        'need_suho' => 1,
        'data'     => $자숙위반안내 . "💥 강화 실패! 무기 파손… (주사위 {$주사위})\n{$현재무기} +{$현재강화} 소멸{$도전안내}{$보상안내}\n(확률 {$성공확률_문구} · " . enchant_fmt_nyang($강화비용) . " 차감)\n\n👼 파손 방지가 없어요. 아래에서 수호를 구매하거나 강화 수호로 전환하세요.",
        'item'     => '',
        'enhance'  => 0,
        'suho_item' => enchant_suho_count($닉),
        'point'    => $보유냥 - $자숙차감 - $강화비용,
        'point_fmt'=> enchant_fmt_nyang($보유냥 - $자숙차감 - $강화비용),
        'cost'     => $강화비용,
        'cost_fmt' => enchant_fmt_nyang($강화비용),
        'rate'     => $성공확률_문구,
        'dice'     => $주사위,
    ]];
}

/**
 * 배치 강화 — 메모리 시뮬레이션 후 DB 1~2회 반영 (채굴 100회 강화와 동일 패턴)
 * @return array{ok: bool, data?: string, body?: array}
 */
function enchant_batch_suho_consume(&$enhance_suho, array &$suho_item_ids, array &$used_suho_idxs) {
    if ($enhance_suho >= 1) {
        $enhance_suho--;
        return [
            'enhance_suho' => $enhance_suho,
            'suho_item_used' => false,
            'suho_item_left' => count($suho_item_ids),
            'msg_suffix' => $enhance_suho > 0 ? " (수호 {$enhance_suho}회 남음)" : '',
        ];
    }
    if (!empty($suho_item_ids)) {
        $idx = (int)array_shift($suho_item_ids);
        if ($idx > 0) {
            $used_suho_idxs[] = $idx;
        }
        $left = count($suho_item_ids);
        return [
            'enhance_suho' => $enhance_suho,
            'suho_item_used' => true,
            'suho_item_left' => $left,
            'msg_suffix' => ' (수호 아이템 1개 소모' . ($left > 0 ? " · {$left}개 남음" : '') . ')',
        ];
    }
    return null;
}

function enchant_batch_commit_member($닉_esc, array $st, $start_point, $total_spent, array $used_suho_idxs, $enhance19_save, array $enhance19_rolls) {
    global $conn;

    $sets = [];
    if ($total_spent > 0) {
        $sets[] = "point = point - {$total_spent}";
    }
    $sets[] = '`enhance` = ' . (int)$st['enhance'];
    if ($st['item'] === '') {
        $sets[] = '`item` = NULL';
        $sets[] = "`style` = ''";
    } else {
        $sets[] = "`item` = '" . addslashes($st['item']) . "'";
        $sets[] = "`style` = '" . addslashes($st['style']) . "'";
    }
    $sets[] = 'enhance_suho = ' . (int)$st['enhance_suho'];
    if ((int)$st['durability'] > 0) {
        $sets[] = 'durability = ' . (int)$st['durability'];
    }
    if (!empty($st['had_success'])) {
        $sets[] = '강화성공시간 = NOW()';
    }
    if (!empty($st['magic_reset'])) {
        $sets[] = 'magic_used = 0';
        $sets[] = 'magic_window = NOW()';
    }
    if (!empty($st['protect_reset'])) {
        $sets[] = 'protect_used = 0';
        $sets[] = 'protect_reset_date = NOW()';
    }
    if ($enhance19_save === 'clear') {
        $sets[] = 'enhance19_used_rolls = NULL';
    } elseif ($enhance19_save === 'save') {
        $payload = [
            'date'  => function_exists('강화19_오늘날짜') ? 강화19_오늘날짜() : date('Y-m-d'),
            'rolls' => function_exists('강화19_사용주사위_정규화') ? 강화19_사용주사위_정규화($enhance19_rolls) : $enhance19_rolls,
        ];
        sort($payload['rolls'], SORT_NUMERIC);
        $json = addslashes(json_encode($payload, JSON_UNESCAPED_UNICODE));
        $sets[] = "enhance19_used_rolls = '{$json}'";
    }

    $where = ["name = '{$닉_esc}'"];
    if ($total_spent > 0) {
        $where[] = "point >= {$total_spent}";
    }

    db_query('UPDATE tb_member SET ' . implode(', ', $sets) . ' WHERE ' . implode(' AND ', $where) . ' LIMIT 1');
    $applied = ($conn instanceof mysqli) ? ((int)mysqli_affected_rows($conn) > 0) : true;
    if (!$applied) {
        return false;
    }

    if (!empty($used_suho_idxs)) {
        $ids = implode(',', array_map('intval', $used_suho_idxs));
        db_query("UPDATE tb_member_item SET status = 1, usedate = NOW() WHERE idx IN ({$ids}) AND nick = '{$닉_esc}'");
        if (function_exists('아이템사용_시세하락')) {
            아이템사용_시세하락('수호', count($used_suho_idxs));
        }
    }

    return true;
}

function enchant_perform_enhance_batch($닉, $닉_esc, $times) {
    global $ENCHANT_강화최대, $ENCHANT_할인적용, $ENCHANT_성공확률표, $ENCHANT_분모가져오기;

    $times = max(1, min(10, (int)$times));
    $회원 = db_select("SELECT name, point, item, enhance, enhance_suho, style, 은총, enhance19_used_rolls, durability FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
    if (empty($회원['name'])) {
        return ['ok' => false, 'data' => '❌ 회원을 찾을 수 없어요.'];
    }

    $강화주체닉 = trim((string)($회원['name'] ?? $닉));
    $은총활성 = 강화_은총_활성($회원['은총'] ?? '');
    $스타일문구 = trim((string)($회원['style'] ?? ''));

    $st = [
        'point'          => (int)($회원['point'] ?? 0),
        'item'           => trim((string)($회원['item'] ?? '')),
        'enhance'        => (int)($회원['enhance'] ?? 0),
        'style'          => $스타일문구,
        'enhance_suho'   => (int)($회원['enhance_suho'] ?? 0),
        'durability'     => isset($회원['durability']) && $회원['durability'] !== null ? (int)$회원['durability'] : 0,
        'magic_reset'    => false,
        'protect_reset'  => false,
        'had_success'    => false,
    ];
    $start_point = $st['point'];
    $enhance19_rolls = 강화19_사용주사위_목록($회원['enhance19_used_rolls'] ?? '');
    $enhance19_save = null;

    $suho_rows = [];
    $rs = db_query("SELECT idx FROM tb_member_item WHERE nick = '{$닉_esc}' AND itemname = '수호' AND status = 0 ORDER BY idx ASC");
    if ($rs) {
        while ($row = mysqli_fetch_assoc($rs)) {
            $suho_rows[] = (int)$row['idx'];
        }
    }
    $suho_item_ids = $suho_rows;
    $used_suho_idxs = [];

    if ($st['item'] === '') {
        return ['ok' => false, 'data' => '❌ 보유 무기가 없어요. 먼저 무기를 구매하세요.'];
    }
    if ($st['enhance'] >= $ENCHANT_강화최대) {
        return ['ok' => false, 'data' => "⚔️ {$st['item']} 이미 최대 강화 +{$ENCHANT_강화최대} 입니다."];
    }

    $자숙위반안내 = '';
    if (function_exists('자숙_강화위반_적용')) {
        $자숙위반 = 자숙_강화위반_적용($강화주체닉, '냥');
        $자숙위반안내 = (string)($자숙위반['notice'] ?? '');
    }

    $lines = [];
    $totalCost = 0;
    $n_ok = 0;
    $n_prot = 0;
    $lastBody = null;
    $history_pending = [];
    $lotto_pending = [];

    for ($i = 0; $i < $times; $i++) {
        $현재무기 = $st['item'];
        $현재강화 = $st['enhance'];

        if ($현재무기 === '') {
            if ($i === 0) {
                return ['ok' => false, 'data' => '❌ 보유 무기가 없어요.'];
            }
            $lines[] = '── ' . ($i + 1) . '회차: 중단 — 무기 없음 (누적 ' . enchant_fmt_nyang($totalCost) . ' 사용)';
            break;
        }
        if ($현재강화 >= $ENCHANT_강화최대) {
            $lines[] = '── ' . ($i + 1) . '회차: 중단 — 최대 강화 달성 (누적 ' . enchant_fmt_nyang($totalCost) . ' 사용)';
            break;
        }

        $도전모드 = ($현재강화 === 19) ? 강화20_도전모드_정보($현재무기, $현재강화, $강화주체닉) : null;
        if ($도전모드) {
            if ($i > 0) {
                if (!enchant_batch_commit_member($닉_esc, $st, $start_point, $totalCost, $used_suho_idxs, $enhance19_save, $enhance19_rolls)) {
                    return ['ok' => false, 'data' => '❌ 강화 저장에 실패했어요.'];
                }
                foreach ($history_pending as $h) {
                    if (function_exists('강화_이력_기록')) {
                        강화_이력_기록($h);
                    }
                }
                foreach ($lotto_pending as $lotto) {
                    db_query("INSERT INTO tb_lotto_info SET status=0, msg='{$lotto['msg']}', leverage=0, item='{$lotto['item']}', regdate=NOW()");
                }
                $history_pending = [];
                $lotto_pending = [];
            }
            for ($j = $i; $j < $times; $j++) {
                $r = enchant_perform_enhance_attempt($닉, $닉_esc);
                if (!$r['ok']) {
                    if ($j === $i && $i === 0) {
                        return $r;
                    }
                    $lines[] = '── ' . ($j + 1) . '회차: 중단 — ' . $r['data'] . ' (누적 ' . enchant_fmt_nyang($totalCost) . ' 사용)';
                    break;
                }
                $b = $r['body'];
                $lastBody = $b;
                $c = (int)($b['cost'] ?? 0);
                $totalCost += $c;
                $lines[] = '━━ ' . ($j + 1) . "/{$times} ━━\n" . $b['data'];
                if (($b['result'] ?? '') === 'success') {
                    $n_ok++;
                } elseif (($b['result'] ?? '') === 'fail_protect') {
                    $n_prot++;
                }
                if (($b['result'] ?? '') === 'fail_break') {
                    break;
                }
                if (($b['result'] ?? '') === 'success' && (int)($b['enhance'] ?? 0) >= (int)$ENCHANT_강화최대) {
                    break;
                }
            }
            if ($lastBody === null) {
                return ['ok' => false, 'data' => '❌ 강화를 진행하지 못했어요.'];
            }
            $head = "📋 연속 강화 요약  성공 {$n_ok} · 수호소모 {$n_prot} (누적 " . enchant_fmt_nyang($totalCost) . ")\n\n";
            $out = $lastBody;
            $out['ok'] = true;
            $out['type'] = 'enhance_batch';
            $out['data'] = $head . implode("\n\n", $lines);
            $out['result'] = $lastBody['result'];
            if (($lastBody['result'] ?? '') === 'fail_break') {
                $out['need_suho'] = 1;
            }
            $out['total_batch_cost'] = $totalCost;
            $out['cost'] = $totalCost;
            return ['ok' => true, 'body' => $out];
        }

        $강화비용 = enchant_cost_next($현재강화, $ENCHANT_할인적용, $은총활성, $현재무기, $강화주체닉);
        if ($st['point'] < $강화비용) {
            if ($i === 0) {
                return ['ok' => false, 'data' => '❌ 강화에 필요한 냥이 부족해요. (' . enchant_fmt_nyang($강화비용) . ')'];
            }
            $lines[] = '── ' . ($i + 1) . '회차: 중단 — 냥 부족 (누적 ' . enchant_fmt_nyang($totalCost) . ' 사용)';
            break;
        }

        $분자 = (int)($ENCHANT_성공확률표[$현재강화] ?? 1);
        $분모 = (int)$ENCHANT_분모가져오기($현재강화);
        if ($은총활성) {
            $분모 = (int)max(1, floor($분모 / 10));
        }
        if ($분자 < 1) {
            $분자 = 1;
        }
        if ($분자 > $분모) {
            $분자 = $분모;
        }

        if ($현재강화 === 19) {
            $주사위 = 강화19_시도주사위($분모, $enhance19_rolls, $강화주체닉);
        } else {
            $주사위 = rand(1, $분모);
        }
        $성공 = ($주사위 <= $분자);
        $성공확률_문구 = rtrim(rtrim(number_format(($분자 / $분모) * 100, 6, '.', ''), '0'), '.') . '%';

        $st['point'] -= $강화비용;
        $totalCost += $강화비용;

        if ($성공) {
            if ($현재강화 === 19) {
                $enhance19_rolls = [];
                $enhance19_save = 'clear';
            }
            $다음강화 = $현재강화 + 1;
            $st['enhance'] = $다음강화;
            $st['had_success'] = true;
            $n_ok++;

            if ($다음강화 >= 10) {
                $st['magic_reset'] = true;
                $현재아이템_trim = trim($현재무기);
                if ($현재아이템_trim === '🪄마법' || $현재아이템_trim === '🪄 마법') {
                    $st['protect_reset'] = true;
                }
                $내구도 = (int)무기_최대내구도($현재아이템_trim, $다음강화);
                if ($내구도 > 0 && $st['durability'] < $내구도) {
                    $st['durability'] = $내구도;
                }
            }
            if ($다음강화 >= 15) {
                $강화주체_esc = addslashes($강화주체닉);
                $lotto_pending[] = [
                    'msg'  => addslashes("⚔️ [ {$강화주체닉} ] 강화 성공\n{$현재무기} +{$다음강화}"),
                    'item' => $강화주체_esc,
                ];
            }

            $회차메시지 = $자숙위반안내 . "⚔️ 강화 성공! (주사위 {$주사위})\n{$현재무기} +{$현재강화} → +{$다음강화}\n(확률 {$성공확률_문구} · " . enchant_fmt_nyang($강화비용) . ' 차감)';
            $lastBody = [
                'ok' => true, 'type' => 'enhance', 'result' => 'success', 'data' => $회차메시지,
                'item' => $현재무기, 'enhance' => $다음강화,
                'point' => $st['point'], 'point_fmt' => enchant_fmt_nyang($st['point']),
                'cost' => $강화비용, 'cost_fmt' => enchant_fmt_nyang($강화비용),
                'rate' => $성공확률_문구, 'dice' => $주사위,
            ];
            $history_pending[] = [
                'nick' => $강화주체닉, 'channel' => 'web', 'item' => $현재무기, 'style' => $스타일문구,
                'enhance_before' => $현재강화, 'enhance_after' => $다음강화, 'result' => 'success',
                'cost' => $강화비용, 'dice' => $주사위, 'dice_num' => $분자, 'dice_den' => $분모,
                'rate' => $성공확률_문구, 'eunchong' => $은총활성,
            ];
            $lines[] = '━━ ' . ($i + 1) . "/{$times} ━━\n" . $회차메시지;

            if ($다음강화 >= $ENCHANT_강화최대) {
                break;
            }
            continue;
        }

        if ($현재강화 === 19) {
            $enhance19_rolls[] = $주사위;
            $enhance19_save = 'save';
        }

        $수호방지 = enchant_batch_suho_consume($st['enhance_suho'], $suho_item_ids, $used_suho_idxs);
        if ($수호방지 !== null) {
            $n_prot++;
            $남은수호 = (int)$수호방지['enhance_suho'];
            $수호문구 = (string)($수호방지['msg_suffix'] ?? '');
            $회차메시지 = $자숙위반안내 . "👼 강화 실패! 파손 방지{$수호문구}\n{$현재무기} +{$현재강화} 유지 · 남은 수호 {$남은수호}회\n(확률 {$성공확률_문구} · " . enchant_fmt_nyang($강화비용) . ' 차감)';
            $lastBody = [
                'ok' => true, 'type' => 'enhance', 'result' => 'fail_protect', 'data' => $회차메시지,
                'item' => $현재무기, 'enhance' => $현재강화, 'enhance_suho' => $남은수호,
                'suho_item' => (int)($수호방지['suho_item_left'] ?? count($suho_item_ids)),
                'point' => $st['point'], 'point_fmt' => enchant_fmt_nyang($st['point']),
                'cost' => $강화비용, 'cost_fmt' => enchant_fmt_nyang($강화비용),
                'rate' => $성공확률_문구, 'dice' => $주사위,
            ];
            $history_pending[] = [
                'nick' => $강화주체닉, 'channel' => 'web', 'item' => $현재무기, 'style' => $스타일문구,
                'enhance_before' => $현재강화, 'enhance_after' => $현재강화, 'result' => 'fail_protect',
                'cost' => $강화비용, 'dice' => $주사위, 'dice_num' => $분자, 'dice_den' => $분모,
                'rate' => $성공확률_문구, 'eunchong' => $은총활성, 'suho_used' => true, 'suho_left' => $남은수호,
            ];
            $lines[] = '━━ ' . ($i + 1) . "/{$times} ━━\n" . $회차메시지;
            continue;
        }

        if ($현재강화 === 19) {
            $enhance19_rolls = [];
            $enhance19_save = 'clear';
        }
        $st['item'] = '';
        $st['style'] = '';
        $st['enhance'] = 0;
        $회차메시지 = $자숙위반안내 . "💥 강화 실패! 무기 파손… (주사위 {$주사위})\n{$현재무기} +{$현재강화} 소멸\n(확률 {$성공확률_문구} · " . enchant_fmt_nyang($강화비용) . " 차감)\n\n👼 파손 방지가 없어요. 아래에서 수호를 구매하거나 강화 수호로 전환하세요.";
        $lastBody = [
            'ok' => true, 'type' => 'enhance', 'result' => 'fail_break', 'need_suho' => 1, 'data' => $회차메시지,
            'item' => '', 'enhance' => 0,
            'suho_item' => count($suho_item_ids),
            'point' => $st['point'], 'point_fmt' => enchant_fmt_nyang($st['point']),
            'cost' => $강화비용, 'cost_fmt' => enchant_fmt_nyang($강화비용),
            'rate' => $성공확률_문구, 'dice' => $주사위,
        ];
        $history_pending[] = [
            'nick' => $강화주체닉, 'channel' => 'web', 'item' => $현재무기, 'style' => $스타일문구,
            'enhance_before' => $현재강화, 'enhance_after' => 0, 'result' => 'fail_break',
            'cost' => $강화비용, 'dice' => $주사위, 'dice_num' => $분자, 'dice_den' => $분모,
            'rate' => $성공확률_문구, 'eunchong' => $은총활성,
        ];
        $lines[] = '━━ ' . ($i + 1) . "/{$times} ━━\n" . $회차메시지;
        break;
    }

    if ($lastBody === null) {
        return ['ok' => false, 'data' => '❌ 강화를 진행하지 못했어요.'];
    }

    if ($totalCost > 0 || $enhance19_save !== null || !empty($used_suho_idxs) || $st['enhance'] !== (int)($회원['enhance'] ?? 0) || $st['item'] !== trim((string)($회원['item'] ?? ''))) {
        if (!enchant_batch_commit_member($닉_esc, $st, $start_point, $totalCost, $used_suho_idxs, $enhance19_save, $enhance19_rolls)) {
            return ['ok' => false, 'data' => '❌ 강화 저장에 실패했어요. 냥·무기 상태를 확인해주세요.'];
        }
    }

    foreach ($history_pending as $h) {
        if (function_exists('강화_이력_기록')) {
            강화_이력_기록($h);
        }
    }
    foreach ($lotto_pending as $lotto) {
        db_query("INSERT INTO tb_lotto_info SET status=0, msg='{$lotto['msg']}', leverage=0, item='{$lotto['item']}', regdate=NOW()");
    }

    $head = "📋 연속 강화 요약  성공 {$n_ok} · 수호소모 {$n_prot} (누적 " . enchant_fmt_nyang($totalCost) . ")\n\n";
    $out = $lastBody;
    $out['ok'] = true;
    $out['type'] = 'enhance_batch';
    $out['data'] = $head . implode("\n\n", $lines);
    $out['result'] = $lastBody['result'];
    if (($lastBody['result'] ?? '') === 'fail_break') {
        $out['need_suho'] = 1;
    }
    $out['enhance_suho'] = $st['enhance_suho'];
    $out['suho_item'] = count($suho_item_ids);
    $out['point'] = $st['point'];
    $out['point_fmt'] = enchant_fmt_nyang($st['point']);
    $out['item'] = $st['item'];
    $out['enhance'] = $st['enhance'];
    $out['total_batch_cost'] = $totalCost;
    $out['cost'] = $totalCost;

    return ['ok' => true, 'body' => $out];
}

// ----- 공통 요청값 -----
$req_action = isset($_REQUEST['action']) ? trim($_REQUEST['action']) : '';
$req_code   = isset($_REQUEST['code']) ? trim($_REQUEST['code']) : '';

// ----- 코드 인증이 필요한 액션들 (탭 락 없음) -----
$ACTIONS_NEED_AUTH = ['status', 'buy', 'enhance', 'enhance_batch', 'use_suho', 'use_eunchong', 'trial_cast', 'history'];
if (in_array($req_action, $ACTIONS_NEED_AUTH, true)) {
    if ($req_code === '') enchant_json(['ok' => false, 'data' => '초대 코드가 필요합니다.']);

    include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
    $회원 = enchant_auth($req_code);
    if (!$회원) enchant_json(['ok' => false, 'data' => '유효하지 않은 초대 코드입니다.']);

    $닉 = trim($회원['name']);
    $닉_esc = addslashes($닉);

    $은총활성 = !empty($회원['은총']) && (strtotime($회원['은총']) > time());

    // config.php는 $두자리닉넴 + 계급() 필요 → 반드시 api/function.php 먼저 include
    include_once $_SERVER['DOCUMENT_ROOT'] . '/api/function.php';
    include_once $_SERVER['DOCUMENT_ROOT'] . '/api/item/web_trial_cast.php';
    $두자리닉넴 = getTwoCharNick($닉);
    include_once $_SERVER['DOCUMENT_ROOT'] . '/api/config.php';
    // config.php 관리자 목록 루프가 $닉 을 마지막 관리자로 덮어씀 → code 인증 회원으로 복원
    $닉 = trim($회원['name']);
    $닉_esc = addslashes($닉);
    $두자리닉넴 = getTwoCharNick($닉);

    // ===== 상태 조회 =====
    if ($req_action === 'status') {
        enchant_json(array_merge(['ok' => true, 'name' => $닉], enchant_status_payload($닉, $회원)));
    }

    // ===== 맛보기 시전 (하루 1회) =====
    if ($req_action === 'trial_cast') {
        $cast_kind = isset($_REQUEST['cast_kind']) ? trim((string)$_REQUEST['cast_kind']) : '';
        $결과 = web_trial_cast_execute($닉, $회원, $cast_kind);
        if (empty($결과['ok'])) {
            enchant_json(['ok' => false, 'data' => $결과['data'] ?? '❌ 맛보기 실패']);
        }
        enchant_json($결과);
    }

    // ===== 무기 구매 =====
    if ($req_action === 'buy') {
        $weapon = isset($_REQUEST['weapon']) ? trim($_REQUEST['weapon']) : '';
        $현재무기 = trim((string)($회원['item'] ?? ''));
        if ($현재무기 !== '') {
            enchant_json(['ok' => false, 'data' => '이미 보유한 무기가 있어요. 강화를 진행하거나 파손 시 재구매해주세요.']);
        }

        $지정구매 = ($weapon !== '' && $weapon !== '랜덤' && isset($ENCHANT_무기맵[$weapon]));
        if ($weapon !== '' && $weapon !== '랜덤' && !$지정구매) {
            enchant_json(['ok' => false, 'data' => '무기 종류를 확인해주세요. (랜덤/단소/활/마법)']);
        }
        $구매비용 = $ENCHANT_첫무기구매비용;

        $보유냥 = (int)($회원['point'] ?? 0);
        if ($보유냥 < $구매비용) {
            enchant_json(['ok' => false, 'data' => '❌ 구매에 필요한 냥이 부족해요. (필요: ' . enchant_fmt_nyang($구매비용) . ')']);
        }

        $자숙위반 = function_exists('자숙_강화위반_적용') ? 자숙_강화위반_적용($닉, '냥') : ['notice' => '', 'deduct' => 0, 'deduct_from' => ''];
        $자숙위반안내 = (string)($자숙위반['notice'] ?? '');
        $자숙차감 = (($자숙위반['deduct_from'] ?? 'point') === 'newpoint') ? 0 : (int)($자숙위반['deduct'] ?? 0);

        if ($지정구매) {
            $선택무기 = $ENCHANT_무기맵[$weapon];
        } else {
            $무기목록 = array_values($ENCHANT_무기맵);
            $선택무기 = $무기목록[array_rand($무기목록)];
        }
        $무기_esc = addslashes($선택무기);

        $rs = db_query("UPDATE tb_member SET point = point - {$구매비용}, `item` = '{$무기_esc}', `enhance` = 0 WHERE name = '{$닉_esc}'");
        if (!$rs) {
            enchant_json(['ok' => false, 'data' => '❌ 구매 처리 실패']);
        }
        미션완료_기록_if_new($두자리닉넴, '일방', '무기구매');
        enchant_json([
            'ok'       => true,
            'type'     => 'buy',
            'data'     => $자숙위반안내 . "⚔️ {$선택무기} 구매완료! (" . enchant_fmt_nyang($구매비용) . " 차감)",
            'item'     => $선택무기,
            'enhance'  => 0,
            'point'    => $보유냥 - $자숙차감 - $구매비용,
            'point_fmt'=> enchant_fmt_nyang($보유냥 - $자숙차감 - $구매비용),
            'cost'     => $구매비용,
            'cost_fmt' => enchant_fmt_nyang($구매비용),
        ]);
    }

    // ===== 강화 이력 =====
    if ($req_action === 'history') {
        $limit = isset($_REQUEST['limit']) ? (int)$_REQUEST['limit'] : 15;
        $rows = function_exists('강화_이력_조회') ? 강화_이력_조회($닉, $limit) : [];
        foreach ($rows as $i => $row) {
            $rows[$i]['cost_fmt'] = enchant_fmt_nyang((int)($row['cost'] ?? 0));
        }
        enchant_json(['ok' => true, 'type' => 'history', 'items' => $rows]);
    }

    // ===== 강화 시도 (1회) =====
    if ($req_action === 'enhance') {
        $r = enchant_perform_enhance_attempt($닉, $닉_esc);
        if (!$r['ok']) {
            enchant_json(['ok' => false, 'data' => $r['data']]);
        }
        enchant_json(array_merge($r['body'], enchant_status_payload($닉)));
    }

    // ===== 강화 연속 (최대 10회, 파손·최대강·냥부족 시 중단) =====
    if ($req_action === 'enhance_batch') {
        $times = isset($_REQUEST['times']) ? (int)$_REQUEST['times'] : 10;
        if ($times < 1) {
            $times = 1;
        }
        if ($times > 10) {
            $times = 10;
        }
        $r = enchant_perform_enhance_batch($닉, $닉_esc, $times);
        if (!$r['ok']) {
            enchant_json(['ok' => false, 'data' => $r['data']]);
        }
        enchant_json(array_merge($r['body'], enchant_status_payload($닉)));
    }

    // ===== 강화 수호 구매/전환 =====
    if ($req_action === 'use_suho') {
        $count = isset($_REQUEST['count']) ? (int)$_REQUEST['count'] : 0;
        if ($count < 1) {
            $count = 1;
        }
        if ($count > 100) {
            enchant_json(['ok' => false, 'data' => '❌ 한 번에 최대 100회까지만 처리할 수 있어요.']);
        }

        $buy = isset($_REQUEST['buy']) && (string)$_REQUEST['buy'] !== '0';
        $냥고정 = $buy ? 1 : null;
        $결과 = 강화수호_냥적용($닉, $count, '냥', $냥고정);
        if (empty($결과['ok'])) {
            enchant_json(['ok' => false, 'data' => $결과['msg'] ?? '❌ 강화 수호 처리에 실패했어요.']);
        }

        enchant_json([
            'ok'           => true,
            'type'         => 'use_suho',
            'data'         => $결과['msg'],
            'enhance_suho' => (int)($결과['enhance_suho'] ?? 0),
            'point'        => (int)($결과['point'] ?? 0),
            'point_fmt'    => enchant_fmt_nyang((int)($결과['point'] ?? 0)),
            'suho_item'    => enchant_suho_count($닉),
            'suho_price'   => 강화수호_회당비용(),
            'suho_price_fmt' => enchant_fmt_nyang(강화수호_회당비용()),
        ]);
    }

    // ===== 은총 사용 (info2.php .은총과 동일) =====
    if ($req_action === 'use_eunchong') {
        $보유은총 = (int)($회원['은총개수'] ?? 0);
        if ($보유은총 < 1) {
            enchant_json(['ok' => false, 'data' => "❌ {$닉} 은총 보유 개수가 부족합니다."]);
        }

        $현재무기 = trim((string)($회원['item'] ?? ''));
        $현재강화 = (int)($회원['enhance'] ?? 0);
        if ($현재무기 === '' || $현재강화 < 14) {
            enchant_json(['ok' => false, 'data' => '❌ 은총은 +14강 이상 무기부터 사용 가능합니다.']);
        }

        // .강화와 동일한 단계별 기본 성공분모
        $기본성공분모 = (int)$ENCHANT_분모가져오기($현재강화);
        $은총성공분모 = (int)max(1, floor($기본성공분모 / 10));
        $분모완화배수 = (int)max(1, floor($기본성공분모 / $은총성공분모));

        // 은총개수 -1, 은총 만료시각 = 기존이 미래면 +5분, 아니면 NOW()+5분
        $rs = db_query("UPDATE tb_member SET 은총개수 = GREATEST(IFNULL(은총개수, 0) - 1, 0), 은총 = CASE WHEN 은총 IS NOT NULL AND 은총 > NOW() THEN DATE_ADD(은총, INTERVAL 5 MINUTE) ELSE DATE_ADD(NOW(), INTERVAL 5 MINUTE) END WHERE name = '{$닉_esc}'");
        if (!$rs) {
            enchant_json(['ok' => false, 'data' => '❌ 은총 적용에 실패했습니다.']);
        }

        $남은은총 = $보유은총 - 1;
        $적용후 = db_select("SELECT 은총 FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
        $은총_end = !empty($적용후['은총']) ? (string)$적용후['은총'] : '';
        $은총종료_표시 = $은총_end !== '' ? date('Y-m-d H:i:s', strtotime($은총_end)) : '-';
        $은총_left_sec = $은총_end !== '' ? max(0, strtotime($은총_end) - time()) : 0;

        $다음강화 = min($ENCHANT_강화최대, $현재강화 + 1);
        $msg = "✨ 은총 적용! (+{$현재강화}→+{$다음강화} 기준)\n";
        $msg .= "성공분모 " . number_format($기본성공분모) . " → " . number_format($은총성공분모) . " ({$분모완화배수}배 완화)\n";
        $msg .= "은총 종료시각: {$은총종료_표시}\n";
        $msg .= "지금부터 버프: 강화확률 상승 + 강화비용 50% 할인 (남은 은총 {$남은은총}개)";

        enchant_json([
            'ok'            => true,
            'type'          => 'use_eunchong',
            'data'          => $msg,
            '은총개수'      => $남은은총,
            '은총_end'      => $은총_end,
            '은총_left_sec' => $은총_left_sec,
            '은총활성'      => ($은총_left_sec > 0) ? 1 : 0,
            'success_rate'  => enchant_success_rate_str($현재강화, true, $현재무기, $닉),
            'cost_next'     => enchant_cost_next($현재강화, $ENCHANT_할인적용, true, $현재무기, $닉),
            'eunchong_discount' => 1,
        ]);
    }
}

// ===================== 페이지 출력 =====================
$enchant_code = isset($_GET['code']) ? trim($_GET['code']) : '';
$enchant_name = '';
$enchant_point = 0;
$enchant_item = '';
$enchant_enhance = 0;
$enchant_style = '';
$enchant_enhance_suho = 0;
$enchant_suho_item = 0;
$enchant_suho_price = 0;
$enchant_cost_next = 0;
$enchant_rate_str = '—';
$enchant_need_code = false;
$enchant_은총활성 = false;
$enchant_은총개수 = 0;
$enchant_은총_end = '';
$enchant_은총_left_sec = 0;
$enchant_durability = 0;
$enchant_point_fmt = '';
$enchant_cost_next_fmt = '';
$enchant_suho_price_fmt = '';
$enchant_buy_cost_fmt = '';
$enchant_trial_cast_available = 0;
$enchant_trial_protect_available = 0;

if ($enchant_code === '') {
    $enchant_need_code = true;
} else {
    include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
    include_once $_SERVER['DOCUMENT_ROOT'] . '/api/function.php';
    include_once $_SERVER['DOCUMENT_ROOT'] . '/api/item/web_trial_cast.php';
    $m = enchant_auth($enchant_code);
    if (!$m) {
        $enchant_need_code = true;
    } else {
        $enchant_name = trim($m['name']);
        $enchant_point = (int)($m['point'] ?? 0);
        $enchant_item = trim((string)($m['item'] ?? ''));
        $enchant_enhance = (int)($m['enhance'] ?? 0);
        $enchant_style = trim((string)($m['style'] ?? ''));
        $enchant_enhance_suho = (int)($m['enhance_suho'] ?? 0);
        $enchant_suho_item = enchant_suho_count($enchant_name);
        $enchant_suho_price = 강화수호_회당비용();
        // config.php는 $두자리닉넴 필요 → function.php 먼저 include
        $두자리닉넴 = getTwoCharNick($enchant_name);
        include_once $_SERVER['DOCUMENT_ROOT'] . '/api/config.php';
        $enchant_은총활성 = 강화_은총_활성($m['은총'] ?? '');
        $enchant_은총개수 = (int)($m['은총개수'] ?? 0);
        $enchant_은총_end = !empty($m['은총']) ? (string)$m['은총'] : '';
        $enchant_은총_left_sec = $enchant_은총활성 ? max(0, strtotime($m['은총']) - time()) : 0;
        $enchant_cost_next = enchant_cost_next($enchant_enhance, $ENCHANT_할인적용, $enchant_은총활성, $enchant_item, $enchant_name);
        $enchant_rate_str = enchant_success_rate_str($enchant_enhance, $enchant_은총활성, $enchant_item, $enchant_name);
        if ($enchant_item !== '' && $enchant_enhance >= 10) {
            $enchant_durability = (int)무기_최대내구도($enchant_item, $enchant_enhance);
        }
        $enchant_point_fmt = enchant_fmt_nyang($enchant_point);
        $enchant_cost_next_fmt = enchant_fmt_nyang($enchant_cost_next);
        $enchant_suho_price_fmt = enchant_fmt_nyang($enchant_suho_price);
        $enchant_buy_cost_fmt = enchant_fmt_nyang($ENCHANT_첫무기구매비용);
        if ($enchant_item !== '' && $enchant_enhance >= 10) {
            $trialSt = web_trial_cast_status($enchant_name);
            $enchant_trial_cast_available = (int)$trialSt['cast_available'];
            $enchant_trial_protect_available = (int)$trialSt['protect_available'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#1a0a2e">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>무기 강화 · 웹</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Black+Han+Sans&family=Noto+Sans+KR:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #1a0a2e;
            --card: #16213e;
            --accent: #e94560;
            --gold: #ffd700;
            --blue: #4dabf7;
            --green: #51cf66;
            --text: #eaeaea;
            --muted: #a0a0a0;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { -webkit-text-size-adjust: 100%; }
        body {
            min-height: 100vh;
            min-height: -webkit-fill-available;
            background: var(--bg);
            background-image:
                radial-gradient(ellipse at 50% 0%, rgba(233,69,96,0.15) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 80%, rgba(255,215,0,0.08) 0%, transparent 40%);
            font-family: 'Noto Sans KR', sans-serif;
            color: var(--text);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            padding: 24px;
            padding-left: max(24px, env(safe-area-inset-left));
            padding-right: max(24px, env(safe-area-inset-right));
            padding-bottom: max(24px, env(safe-area-inset-bottom));
        }
        .wrap {
            width: 100%;
            max-width: 460px;
            background: var(--card);
            border-radius: 24px;
            padding: 28px 24px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4), 0 0 0 1px rgba(255,255,255,0.06);
        }
        h1 {
            font-family: 'Black Han Sans', sans-serif;
            font-size: 2.1rem;
            text-align: center;
            margin-bottom: 4px;
            background: linear-gradient(135deg, var(--gold), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .sub {
            text-align: center;
            color: var(--muted);
            font-size: 0.85rem;
            margin-bottom: 22px;
        }
        .card {
            background: rgba(0,0,0,0.25);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 14px;
            padding: 16px;
            margin-bottom: 14px;
        }
        .card h3 {
            font-size: 0.95rem;
            margin-bottom: 10px;
            color: var(--gold);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }
        .stat {
            background: rgba(255,255,255,0.04);
            border-radius: 10px;
            padding: 10px 12px;
        }
        .stat .label {
            font-size: 0.75rem;
            color: var(--muted);
            margin-bottom: 4px;
        }
        .stat .value {
            font-size: 1rem;
            font-weight: 700;
            word-break: break-all;
        }
        .stat .value.gold { color: var(--gold); }
        .stat .value.accent { color: var(--accent); }
        .stat .value.blue { color: var(--blue); }
        .stat .value.green { color: var(--green); }

        .info-card { padding: 12px 14px; }
        .info-card h3 { margin-bottom: 6px; font-size: 0.9rem; }
        .info-compact { display: flex; flex-direction: column; gap: 5px; }
        .info-line {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 10px;
            font-size: 0.82rem;
            line-height: 1.3;
        }
        .info-line .il { color: var(--muted); flex-shrink: 0; font-size: 0.74rem; }
        .info-line .iv {
            font-weight: 700;
            text-align: right;
            word-break: break-all;
            font-size: 0.88rem;
        }
        .info-line .iv.gold { color: var(--gold); font-size: 0.84rem; }
        .info-line .iv.accent { color: var(--accent); }
        .info-line .iv.blue { color: var(--blue); }
        .info-line .iv.green { color: var(--green); }
        .info-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 4px 10px;
            padding: 6px 8px;
            background: rgba(255,255,255,0.04);
            border-radius: 8px;
        }
        .info-chip {
            display: inline-flex;
            align-items: baseline;
            gap: 3px;
            font-size: 0.78rem;
            white-space: nowrap;
        }
        .info-chip .il { color: var(--muted); font-size: 0.7rem; }
        .info-chip .iv { font-weight: 700; font-size: 0.82rem; }
        .info-chip .iv.accent { color: var(--accent); }
        .info-chip .iv.blue { color: var(--blue); }
        .info-chip .iv.green { color: var(--green); }

        .weapon-display {
            text-align: center;
            padding: 10px 12px;
            background: linear-gradient(135deg, rgba(255,215,0,0.08), rgba(233,69,96,0.08));
            border: 1px solid rgba(255,215,0,0.2);
            border-radius: 12px;
            margin-bottom: 10px;
        }
        .weapon-display .emoji {
            font-size: 1.85rem;
            line-height: 1;
            margin-bottom: 2px;
        }
        .weapon-display .weapon-line {
            display: flex;
            flex-direction: row;
            flex-wrap: wrap;
            align-items: baseline;
            justify-content: center;
            gap: 0 8px;
            max-width: 100%;
        }
        .weapon-display .name {
            font-family: 'Black Han Sans', sans-serif;
            font-size: 1.05rem;
            color: var(--gold);
        }
        .weapon-display .level {
            font-size: 0.95rem;
            font-weight: 800;
            color: var(--accent);
        }
        .weapon-display .style {
            display: inline-block;
            font-size: 0.78rem;
            color: var(--muted);
            margin: 0;
        }
        .weapon-display.empty {
            opacity: 0.75;
        }
        .weapon-display.empty .name {
            color: var(--muted);
        }

        .btn-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
        }
        .btn-row.c2 { grid-template-columns: repeat(2, 1fr); }
        .btn {
            padding: 12px 10px;
            min-height: 46px;
            font-family: 'Noto Sans KR', sans-serif;
            font-size: 0.88rem;
            font-weight: 700;
            color: #fff;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: transform 0.08s, box-shadow 0.2s, opacity 0.15s;
            -webkit-tap-highlight-color: transparent;
            touch-action: manipulation;
        }
        .btn:active { transform: translateY(1px); }
        .btn:disabled { opacity: 0.45; cursor: not-allowed; transform: none; }
        .btn-buy-random { background: linear-gradient(145deg, #4dabf7, #3b8fd1); box-shadow: 0 4px 12px rgba(77,171,247,0.3); }
        .btn-buy { background: linear-gradient(145deg, #2d3561, #20264a); border: 1px solid rgba(255,255,255,0.12); }
        .btn-enhance {
            grid-column: 1 / -1;
            background: linear-gradient(145deg, var(--accent), #c73e54);
            box-shadow: 0 4px 14px rgba(233,69,96,0.35);
            font-size: 1rem;
            min-height: 52px;
        }
        .enhance-btns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-top: 12px;
        }
        .enhance-btns .btn-enhance { grid-column: auto; }
        .btn-cast {
            background: linear-gradient(135deg, #dc2626, #ea580c);
            color: #fff;
        }
        .btn-cast.magic {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
        }
        .btn-protect {
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            color: #fff;
        }
        .cast-btns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-top: 8px;
        }
        .cast-btns.single {
            grid-template-columns: 1fr;
        }
        .btn-cast:disabled,
        .btn-protect:disabled { opacity: 0.45; }
        .btn-trial {
            background: linear-gradient(135deg, #a855f7, #ec4899);
            color: #fff;
            font-size: 0.88rem;
        }
        .btn-trial:disabled { opacity: 0.45; }
        .trial-section {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px dashed rgba(255,255,255,0.12);
        }
        .trial-section h4 {
            margin: 0 0 8px;
            font-size: 0.88rem;
            color: var(--gold);
            font-weight: 700;
        }
        .btn-suho {
            background: linear-gradient(145deg, #ffd700, #c9a400);
            color: #1a0a2e;
            box-shadow: 0 4px 12px rgba(255,215,0,0.3);
        }
        .btn-buy-suho {
            background: linear-gradient(145deg, #74c0fc, #339af0);
            color: #fff;
            box-shadow: 0 4px 12px rgba(51,154,240,0.28);
            font-size: 0.88rem;
            min-height: 44px;
        }
        .btn-eunchong {
            background: linear-gradient(145deg, #51cf66, #2f9e44);
            color: #fff;
            box-shadow: 0 4px 12px rgba(81,207,102,0.35);
            min-height: 48px;
            font-size: 0.95rem;
        }

        /* 아이템 탭 */
        .item-tabs { margin-bottom: 14px; }
        .tab-bar {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        .tab-btn {
            position: relative;
            padding: 12px 10px;
            min-height: 46px;
            font-family: 'Noto Sans KR', sans-serif;
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--text);
            background: rgba(0,0,0,0.25);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            cursor: pointer;
            transition: background 0.15s, border-color 0.15s, transform 0.08s;
            -webkit-tap-highlight-color: transparent;
            touch-action: manipulation;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .tab-btn:hover { background: rgba(255,255,255,0.04); }
        .tab-btn:active { transform: translateY(1px); }
        .tab-btn.active {
            background: linear-gradient(145deg, rgba(255,215,0,0.12), rgba(233,69,96,0.12));
            border-color: rgba(255,215,0,0.3);
            color: var(--gold);
        }
        .tab-count {
            display: inline-block;
            padding: 1px 8px;
            border-radius: 999px;
            background: rgba(255,255,255,0.08);
            font-size: 0.78rem;
            font-weight: 700;
            min-width: 24px;
        }
        .tab-count.blue { color: var(--blue); }
        .tab-count.green { color: var(--green); }
        .tab-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--green);
            box-shadow: 0 0 8px var(--green);
            margin-left: 2px;
            animation: tab-pulse 1.2s ease-in-out infinite;
        }
        @keyframes tab-pulse {
            0%,100% { opacity: 1; }
            50% { opacity: 0.4; }
        }
        .tab-panel {
            display: none;
            margin-top: 10px;
            padding: 16px;
            background: rgba(0,0,0,0.25);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 14px;
            animation: tab-slide 0.18s ease-out;
        }
        .tab-panel.active { display: block; }
        @keyframes tab-slide {
            from { opacity: 0; transform: translateY(-4px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .suho-row {
            display: flex;
            gap: 8px;
        }
        .suho-row input {
            flex: 1;
            padding: 12px 14px;
            border: 2px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            background: rgba(0,0,0,0.25);
            color: var(--text);
            font-size: 16px;
            text-align: center;
            -webkit-appearance: none;
            appearance: none;
        }
        .suho-row input:focus { outline: none; border-color: var(--gold); }
        .suho-row .btn-suho { flex: 0 0 auto; padding-left: 18px; padding-right: 18px; }

        .result {
            margin-top: 14px;
            padding: 14px 16px;
            border-radius: 12px;
            background: rgba(0,0,0,0.35);
            border: 1px solid rgba(255,255,255,0.08);
            font-size: 0.9rem;
            line-height: 1.6;
            white-space: pre-wrap;
            word-break: break-word;
            box-sizing: border-box;
            height: 7.5rem;
            max-height: 7.5rem;
            overflow-x: hidden;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            display: none;
        }
        .result.show { display: block; }
        .result.success { border-color: rgba(255,215,0,0.4); color: #ffd700; animation: sparkle 0.6s ease-out; }
        .result.fail { border-color: rgba(233,69,96,0.4); color: #ff8c9e; }
        .result.info { border-color: rgba(77,171,247,0.3); color: var(--blue); }
        @keyframes sparkle {
            0%,100% { box-shadow: 0 0 0 0 rgba(255,215,0,0); }
            50% { box-shadow: 0 0 24px 4px rgba(255,215,0,0.45); }
        }

        .hint { font-size: 0.78rem; color: var(--muted); margin-top: 6px; line-height: 1.5; }
        .history-list { display: flex; flex-direction: column; gap: 8px; max-height: 280px; overflow-y: auto; }
        .history-item {
            background: rgba(255,255,255,0.04);
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 0.82rem;
            line-height: 1.45;
        }
        .history-item .hi-top { display: flex; justify-content: space-between; gap: 8px; margin-bottom: 4px; }
        .history-item .hi-result { font-weight: 700; }
        .history-item .hi-result.success { color: #86efac; }
        .history-item .hi-result.fail_protect { color: #93c5fd; }
        .history-item .hi-result.fail_break { color: #fca5a5; }
        .history-item .hi-time { color: var(--muted); font-size: 0.75rem; white-space: nowrap; }
        .history-item .hi-sub { color: var(--muted); font-size: 0.76rem; }
        .history-empty { color: var(--muted); font-size: 0.82rem; text-align: center; padding: 12px 0; }
        .history-card { padding-bottom: 16px; }
        .history-toggle {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 0;
            margin: 0;
            background: none;
            border: none;
            cursor: pointer;
            color: inherit;
            font: inherit;
            text-align: left;
        }
        .history-toggle:hover .history-toggle-title { color: #ffe566; }
        .history-toggle-title {
            font-size: 0.95rem;
            color: var(--gold);
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
            transition: color 0.15s;
        }
        .history-chevron {
            flex-shrink: 0;
            color: var(--muted);
            font-size: 0.85rem;
            line-height: 1;
            transition: transform 0.2s ease;
        }
        .history-card.open .history-chevron { transform: rotate(180deg); }
        .history-body { margin-top: 10px; }
        .history-body[hidden] { display: none !important; }
        .bigmsg { text-align: center; padding: 24px 12px; color: var(--muted); font-size: 0.95rem; line-height: 1.6; }
        .bigmsg strong { color: var(--accent); }

        .cost-line { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 4px; font-size: 0.85rem; color: var(--muted); }
        .cost-line b { color: var(--text); font-weight: 700; }
        .cost-line b.gold { color: var(--gold); }
        .cost-line b.accent { color: var(--accent); }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 0.72rem;
            background: rgba(255,215,0,0.15);
            color: var(--gold);
            margin-left: 6px;
        }
        .badge.warn { background: rgba(233,69,96,0.15); color: var(--accent); }
        .badge.info { background: rgba(77,171,247,0.15); color: var(--blue); }
        @media (max-width: 480px) {
            body { padding: 16px; }
            .wrap { padding: 22px 18px; border-radius: 20px; }
            h1 { font-size: 1.8rem; }
            .btn-row { grid-template-columns: repeat(2, 1fr); }
            .btn-row.specific { grid-template-columns: repeat(3, 1fr); }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <h1>무기 강화</h1>
        <p class="sub">무기를 구매하고 강화해보세요</p>

        <?php if ($enchant_need_code) { ?>
            <div class="bigmsg">
                <p style="font-size:2rem;margin-bottom:10px;">🔒</p>
                <strong>코드를 부여받으세요.</strong><br>
                올바른 초대 코드로 접속해야 이용할 수 있어요.<br>
                <span style="opacity:0.7;">예) /page/enchant.php?code=XXXX</span>
            </div>
        <?php } else { ?>

        <!-- 회원 정보 카드 -->
        <div class="card info-card">
            <h3>🎒 내 정보
                <?php if ($enchant_은총활성) { ?><span class="badge info">은총 활성</span><?php } ?>
            </h3>
            <div class="info-compact">
                <div class="info-line">
                    <span class="il">닉네임</span>
                    <span class="iv"><?php echo htmlspecialchars($enchant_name, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="info-line">
                    <span class="il">보유 냥</span>
                    <span class="iv gold" id="stPoint"><?php echo htmlspecialchars($enchant_point_fmt, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="info-chips">
                    <span class="info-chip">
                        <span class="il">파손방지</span>
                        <span class="iv accent" id="stSuho"><?php echo number_format($enchant_enhance_suho); ?>회</span>
                    </span>
                    <span class="info-chip">
                        <span class="il">수호</span>
                        <span class="iv blue" id="stSuhoItem"><?php echo number_format($enchant_suho_item); ?>개</span>
                    </span>
                    <span class="info-chip">
                        <span class="il">은총</span>
                        <span class="iv green" id="stEunchongItem"><?php echo number_format($enchant_은총개수); ?>개</span>
                    </span>
                </div>
                <div class="info-line" id="stEunchongBuffBox" <?php if (!$enchant_은총활성) echo 'style="display:none;"'; ?>>
                    <span class="il">은총 버프</span>
                    <span class="iv green" id="stEunchongBuff"
                        data-end="<?php echo htmlspecialchars($enchant_은총_end, ENT_QUOTES, 'UTF-8'); ?>"
                        data-left="<?php echo (int)$enchant_은총_left_sec; ?>">—</span>
                </div>
            </div>
        </div>

        <!-- 현재 무기 -->
        <?php if ($enchant_item !== '') { ?>
        <div class="weapon-display" id="weaponBox">
            <div class="emoji" id="weaponEmoji"><?php echo htmlspecialchars(mb_substr($enchant_item, 0, 1), ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="weapon-line">
                <?php if ($enchant_style !== '') { ?>
                <div class="style" id="weaponStyle"><?php echo htmlspecialchars($enchant_style, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php } ?>
                <div class="name"><span id="weaponName"><?php echo htmlspecialchars(mb_substr($enchant_item, 1), ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="level" id="weaponLevel">+<?php echo (int)$enchant_enhance; ?></span>
                </div>
            </div>
        </div>
        <?php } else { ?>
        <div class="weapon-display empty" id="weaponBox">
            <div class="emoji" id="weaponEmoji">⚔️</div>
            <div class="weapon-line">
                <div class="name" id="weaponName">무기 없음 · 구매해주세요</div>
            </div>
        </div>
        <?php } ?>

        <!-- 무기 구매 -->
        <div class="card" id="buyCard" <?php if ($enchant_item !== '') echo 'style="display:none;"'; ?>>
            <h3>🛒 무기 구매</h3>
            <div class="cost-line">
                <span>무기 구매 (단소/활/마법·랜덤)</span><b class="gold"><?php echo htmlspecialchars($enchant_buy_cost_fmt, ENT_QUOTES, 'UTF-8'); ?></b>
            </div>
            <div class="btn-row specific" style="margin-top:10px;">
                <button type="button" class="btn btn-buy-random" data-weapon="랜덤" style="grid-column:1/-1;">🎲 랜덤 <?php echo htmlspecialchars($enchant_buy_cost_fmt, ENT_QUOTES, 'UTF-8'); ?></button>
            </div>
            <div class="btn-row specific" style="margin-top:8px;">
                <button type="button" class="btn btn-buy" data-weapon="단소">🪈 단소</button>
                <button type="button" class="btn btn-buy" data-weapon="활">🏹 활</button>
                <button type="button" class="btn btn-buy" data-weapon="마법">🪄 마법</button>
            </div>
            <div class="hint">무기 구매는 보유 무기가 없을 때만 가능합니다.</div>
        </div>

        <!-- 결과 (강화/수호/은총·구매 메시지) -->
        <div class="result" id="result" role="status"></div>

        <!-- 강화 -->
        <div class="card" id="enhanceCard" <?php if ($enchant_item === '') echo 'style="display:none;"'; ?>>
            <h3>⚒️ 무기 강화
                <?php if ($ENCHANT_할인적용) { ?><span class="badge">50% 할인중</span><?php } ?>
                <span class="badge info" id="buffEunchongBadge" <?php if (!$enchant_은총활성) echo 'style="display:none;"'; ?>>✨ 은총 (확률↑ · 비용 50%)</span>
            </h3>
            <div class="cost-line">
                <span>다음 단계</span>
                <b class="accent" id="levNext">+<?php echo (int)$enchant_enhance; ?> → +<?php echo min($ENCHANT_강화최대, (int)$enchant_enhance + 1); ?></b>
            </div>
            <div class="cost-line">
                <span>소요 비용</span>
                <b class="gold" id="costNext"><?php echo htmlspecialchars($enchant_cost_next_fmt, ENT_QUOTES, 'UTF-8'); ?></b>
            </div>
            <div class="cost-line">
                <span>성공 확률</span>
                <b class="gold" id="rateNext"><?php echo htmlspecialchars($enchant_rate_str, ENT_QUOTES, 'UTF-8'); ?></b>
            </div>
            <?php if ($enchant_durability > 0) { ?>
            <div class="cost-line">
                <span>예상 최대 내구도</span>
                <b id="durability"><?php echo (int)$enchant_durability; ?></b>
            </div>
            <?php } ?>
            <div class="enhance-btns">
                <button type="button" class="btn btn-enhance" id="btnEnhance">⚔️ 강화 시도</button>
                <button type="button" class="btn btn-enhance" id="btnEnhance10">⚔️ 10회 연속</button>
            </div>
        </div>

        <!-- 아이템 사용 (탭) -->
        <div class="item-tabs">
            <div class="tab-bar">
                <button type="button" class="tab-btn" data-tab="suho">
                    👼 수호 <span class="tab-count blue" id="tabSuhoCount"><?php echo number_format($enchant_suho_item); ?></span>
                </button>
                <button type="button" class="tab-btn" data-tab="eunchong">
                    ✨ 은총 <span class="tab-count green" id="tabEunchongCount"><?php echo number_format($enchant_은총개수); ?></span>
                    <?php if ($enchant_은총활성) { ?><span class="tab-dot" id="tabEunchongDot" title="버프 중"></span><?php } else { ?><span class="tab-dot" id="tabEunchongDot" style="display:none;" title="버프 중"></span><?php } ?>
                </button>
            </div>

            <!-- 수호 패널 -->
            <div class="tab-panel" data-panel="suho">
                <div class="cost-line" id="suhoProtectLine">
                    <span>파손방지 누적</span>
                    <b class="accent" id="suhoStackMini"><?php echo number_format($enchant_enhance_suho); ?>회</b>
                </div>
                <div id="suhoUseSection" <?php if ($enchant_suho_item < 1) echo 'style="display:none;"'; ?>>
                    <div class="cost-line">
                        <span>수호 아이템 보유</span>
                        <b class="blue" id="suhoItemMini"><?php echo number_format($enchant_suho_item); ?>개 보유</b>
                    </div>
                    <div class="cost-line">
                        <span>1개당 +1~+3 누적 (랜덤) · 실패 시 아이템 1개 자동 소모</span>
                    </div>
                    <div class="suho-row" style="margin-top:10px;">
                        <input type="number" id="suhoCount" min="1" max="999" placeholder="사용 개수" value="1" inputmode="numeric">
                        <button type="button" class="btn btn-suho" id="btnUseSuho">👼 강화 수호로 전환</button>
                    </div>
                </div>
                <div id="suhoBuySection">
                    <div class="cost-line" id="suhoBuyAlert" <?php if ($enchant_suho_item > 0 || $enchant_enhance_suho > 0) echo 'style="display:none;"'; ?>>
                        <span style="color:var(--accent);">⚠️ 파손 방지 없음 — 강화 수호 구매 필요</span>
                    </div>
                    <div class="cost-line">
                        <span>강화 수호 1회 비용 (냥 · 1회 +1 누적)</span>
                        <b class="blue" id="suhoPriceMini"><?php echo htmlspecialchars($enchant_suho_price_fmt, ENT_QUOTES, 'UTF-8'); ?></b>
                    </div>
                    <div class="btn-row c2" style="margin-top:10px;">
                        <button type="button" class="btn btn-buy-suho" data-buy-enhance-suho="1">👼 강화 수호 1개 구매</button>
                        <button type="button" class="btn btn-buy-suho" data-buy-enhance-suho="100">👼 100개 구매</button>
                    </div>
                    <div class="cost-line" style="margin-top:8px;">
                        <span>보유 수호 아이템 있으면 우선 사용 · 부족분만 냥 차감</span>
                    </div>
                </div>
                <div class="hint">실패 시: 파손방지 누적 → 수호 아이템 순으로 자동 사용 · 둘 다 없으면 무기 파손</div>
            </div>

            <!-- 은총 패널 -->
            <div class="tab-panel" data-panel="eunchong">
                <div class="cost-line">
                    <span>버프: 성공분모 1/10 + 강화비용 50% (5분, 연장 가능)</span>
                </div>
                <div class="cost-line">
                    <span>보유 은총</span>
                    <b class="green" id="eunchongItemMini"><?php echo number_format($enchant_은총개수); ?>개</b>
                </div>
                <div class="cost-line" id="eunchongBuffLine" <?php if (!$enchant_은총활성) echo 'style="display:none;"'; ?>>
                    <span>버프 남은 시간</span>
                    <b class="green" id="eunchongBuffText">—</b>
                </div>
                <div class="btn-row" style="margin-top:10px;">
                    <button type="button" class="btn btn-eunchong" id="btnUseEunchong" style="grid-column:1/-1;">✨ 은총 사용 (이어쓰면 +5분 연장)</button>
                </div>
                <div class="hint">+14강 이상 무기만 사용 가능 · 이미 버프 중이면 현재 종료시각에서 +5분 연장</div>
            </div>
        </div>

        <!-- +10 맛보기 -->
        <?php
        $enchant_is_attack = ($enchant_item === '🪈단소' || $enchant_item === '🏹활' || $enchant_item === '🏹 활');
        $enchant_is_magic = ($enchant_item === '🪄마법' || $enchant_item === '🪄 마법');
        $enchant_show_cast = ($enchant_item !== '' && (int)$enchant_enhance >= 10 && ($enchant_is_attack || $enchant_is_magic));
        ?>
        <div class="card history-card" id="castCard" <?php if (!$enchant_show_cast) echo 'style="display:none;"'; ?>>
            <button type="button" class="history-toggle" id="castToggle" aria-expanded="false" aria-controls="castBody">
                <span class="history-toggle-title" id="castCardTitle"><?php echo $enchant_is_magic ? '✨ 마법 맛보기' : '⚔️ 무기 공격 맛보기'; ?></span>
                <span class="history-chevron" aria-hidden="true">▼</span>
            </button>
            <div class="history-body" id="castBody" hidden>
            <?php if ($enchant_is_magic) { ?>
            <div class="cost-line">
                <span>맛보기 시전 (하루 1회)</span>
                <b class="blue" id="trialCastStatus"><?php echo $enchant_trial_cast_available ? '가능' : '사용 완료'; ?></b>
            </div>
            <div class="cost-line" id="trialProtectStatusRow">
                <span>맛보기 보호 (하루 1회)</span>
                <b class="blue" id="trialProtectStatus"><?php echo $enchant_trial_protect_available ? '가능' : '사용 완료'; ?></b>
            </div>
            <?php } else { ?>
            <div class="cost-line">
                <span>오늘 맛보기 (하루 1회)</span>
                <b class="blue" id="trialCastStatus"><?php echo $enchant_trial_cast_available ? '가능' : '사용 완료'; ?></b>
            </div>
            <div class="cost-line" id="trialProtectStatusRow" style="display:none;">
                <span>맛보기 보호 (하루 1회)</span>
                <b class="blue" id="trialProtectStatus">—</b>
            </div>
            <?php } ?>
            <div class="cast-btns<?php echo $enchant_is_magic ? '' : ' single'; ?>" id="trialBtnRow">
                <button type="button" class="btn btn-trial" id="btnTrialCast">
                    <?php echo $enchant_is_magic ? '👅 맛보기 시전' : '👅 맛보기 공격'; ?>
                </button>
                <button type="button" class="btn btn-trial" id="btnTrialProtect"<?php if (!$enchant_is_magic) echo ' style="display:none;"'; ?>>👅 맛보기 보호</button>
            </div>
            <div class="hint" id="trialHint"><?php
                echo $enchant_is_magic
                    ? '시전·보호 각 하루 1회 · 한도·내구도·본인 비용 없음'
                    : '한도·내구도·본인 비용 없음 · 대상 효과는 실제 적용';
            ?></div>
            </div>
        </div>

        <div class="card history-card" id="historyCard">
            <button type="button" class="history-toggle" id="historyToggle" aria-expanded="false" aria-controls="historyBody">
                <span class="history-toggle-title">📜 강화 이력 <span class="badge info">최근 15건</span></span>
                <span class="history-chevron" aria-hidden="true">▼</span>
            </button>
            <div class="history-body" id="historyBody" hidden>
                <div class="history-list" id="historyList">
                    <div class="history-empty">펼치면 최근 기록을 불러옵니다.</div>
                </div>
            </div>
        </div>

        <div style="text-align:center;margin-top:14px;">
            <button type="button" class="btn" id="btnRefresh" style="background:transparent;border:1px solid rgba(255,255,255,0.15);color:var(--muted);font-weight:400;min-height:36px;padding:6px 14px;">새로고침</button>
        </div>

        <?php } // end has code ?>
    </div>

    <?php if (!$enchant_need_code) { ?>
    <script>
    (function() {
        var CODE = <?php echo json_encode($enchant_code, JSON_UNESCAPED_UNICODE); ?>;
        var BASE = location.pathname;

        var $ = function(id) { return document.getElementById(id); };
        var state = {
            point: <?php echo (int)$enchant_point; ?>,
            point_fmt: <?php echo json_encode($enchant_point_fmt, JSON_UNESCAPED_UNICODE); ?>,
            item: <?php echo json_encode($enchant_item, JSON_UNESCAPED_UNICODE); ?>,
            enhance: <?php echo (int)$enchant_enhance; ?>,
            enhance_suho: <?php echo (int)$enchant_enhance_suho; ?>,
            suho_item: <?php echo (int)$enchant_suho_item; ?>,
            suho_price: <?php echo (int)$enchant_suho_price; ?>,
            suho_price_fmt: <?php echo json_encode($enchant_suho_price_fmt, JSON_UNESCAPED_UNICODE); ?>,
            cost_next: <?php echo (int)$enchant_cost_next; ?>,
            cost_next_fmt: <?php echo json_encode($enchant_cost_next_fmt, JSON_UNESCAPED_UNICODE); ?>,
            success_rate: <?php echo json_encode($enchant_rate_str, JSON_UNESCAPED_UNICODE); ?>,
            max_level: <?php echo (int)$ENCHANT_강화최대; ?>,
            style: <?php echo json_encode($enchant_style, JSON_UNESCAPED_UNICODE); ?>,
            durability: <?php echo (int)$enchant_durability; ?>,
            은총활성: <?php echo $enchant_은총활성 ? 1 : 0; ?>,
            은총개수: <?php echo (int)$enchant_은총개수; ?>,
            은총_end: <?php echo json_encode($enchant_은총_end, JSON_UNESCAPED_UNICODE); ?>,
            은총_left_sec: <?php echo (int)$enchant_은총_left_sec; ?>,
            trial_cast_available: <?php echo (int)$enchant_trial_cast_available; ?>,
            trial_protect_available: <?php echo (int)$enchant_trial_protect_available; ?>
        };

        function fmt(n) { return (n || 0).toLocaleString('ko-KR'); }
        function fmtNyang(fmt, n) {
            if (fmt) return fmt;
            return fmt(n || 0) + '냥';
        }

        function showResult(text, kind) {
            var r = $('result');
            r.className = 'result show ' + (kind || 'info');
            r.textContent = text;
        }

        function ajax(params, onDone) {
            params.code = CODE;
            var body = Object.keys(params).map(function(k) {
                return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
            }).join('&');
            fetch(BASE, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body,
                credentials: 'same-origin'
            }).then(function(r) { return r.json(); })
            .then(function(j) { onDone(j); })
            .catch(function(e) { onDone({ ok: false, data: '통신 오류' }); });
        }

        function refreshStatus() {
            ajax({ action: 'status' }, function(j) {
                if (!j.ok) { showResult(j.data || '상태 조회 실패', 'fail'); return; }
                applyStatusFromResponse(j);
                renderAll();
            });
        }

        function fmtSec(s) {
            s = Math.max(0, parseInt(s || 0, 10));
            var m = Math.floor(s / 60);
            var r = s % 60;
            return m + '분 ' + (r < 10 ? '0' : '') + r + '초';
        }
        function renderEunchong() {
            var active = state.은총활성 && state.은총_left_sec > 0;
            var cnt = state.은총개수 || 0;
            var itemEl = $('stEunchongItem');
            if (itemEl) itemEl.textContent = fmt(cnt) + '개';
            var mini = $('eunchongItemMini');
            if (mini) mini.textContent = fmt(cnt) + '개';
            var tabCnt = $('tabEunchongCount');
            if (tabCnt) tabCnt.textContent = fmt(cnt);
            var dot = $('tabEunchongDot');
            if (dot) dot.style.display = active ? '' : 'none';

            var buffBox = $('stEunchongBuffBox');
            var buffEl = $('stEunchongBuff');
            var badge = $('buffEunchongBadge');
            var buffLine = $('eunchongBuffLine');
            var buffText = $('eunchongBuffText');
            if (active) {
                if (buffBox) buffBox.style.display = '';
                if (buffEl) buffEl.textContent = fmtSec(state.은총_left_sec);
                if (badge) badge.style.display = '';
                if (buffLine) buffLine.style.display = '';
                if (buffText) buffText.textContent = fmtSec(state.은총_left_sec);
            } else {
                if (buffBox) buffBox.style.display = 'none';
                if (badge) badge.style.display = 'none';
                if (buffLine) buffLine.style.display = 'none';
            }
            // 은총 사용 버튼 상태
            var btnE = $('btnUseEunchong');
            if (btnE) {
                var canUse = cnt >= 1 && state.item && state.enhance >= 14 && state.enhance < state.max_level;
                btnE.disabled = !canUse;
                if (cnt < 1) btnE.textContent = '✨ 은총 없음';
                else if (!state.item || state.enhance < 14) btnE.textContent = '✨ +14강 이상 무기만 사용 가능';
                else if (state.enhance >= state.max_level) btnE.textContent = '✨ 최대 강화 달성';
                else btnE.textContent = '✨ 은총 사용 (이어쓰면 +5분 연장)';
            }
        }

        function setActiveTab(name) {
            var btns = document.querySelectorAll('.tab-btn');
            var panels = document.querySelectorAll('.tab-panel');
            btns.forEach(function(b) {
                b.classList.toggle('active', b.getAttribute('data-tab') === name);
            });
            panels.forEach(function(p) {
                p.classList.toggle('active', p.getAttribute('data-panel') === name);
            });
        }
        function toggleTab(name) {
            var btn = document.querySelector('.tab-btn[data-tab="' + name + '"]');
            if (btn && btn.classList.contains('active')) {
                // 같은 탭 다시 누르면 닫기
                setActiveTab(null);
            } else {
                setActiveTab(name);
            }
        }

        function renderWeapon() {
            var box = $('weaponBox');
            var em = $('weaponEmoji');
            var nm = $('weaponName');
            var lv = $('weaponLevel');
            var st = $('weaponStyle');
            if (state.item) {
                var emoji = Array.from(state.item)[0] || '⚔️';
                var name = state.item.substring(emoji.length);
                box.classList.remove('empty');
                em.textContent = emoji;
                nm.innerHTML = '';
                nm.appendChild(document.createTextNode(name + ' '));
                if (!lv) {
                    lv = document.createElement('span');
                    lv.id = 'weaponLevel';
                    lv.className = 'level';
                    nm.appendChild(lv);
                } else {
                    nm.appendChild(lv);
                }
                lv.textContent = '+' + state.enhance;
                if (state.style) {
                    if (!st) {
                        st = document.createElement('div');
                        st.id = 'weaponStyle';
                        st.className = 'style';
                        var line = box.querySelector('.weapon-line');
                        var nameEl = line && line.querySelector('.name');
                        if (line && nameEl) line.insertBefore(st, nameEl);
                        else if (line) line.insertBefore(st, line.firstChild);
                        else box.appendChild(st);
                    }
                    st.textContent = state.style;
                    st.style.display = '';
                } else if (st) {
                    st.style.display = 'none';
                }
                $('buyCard').style.display = 'none';
                $('enhanceCard').style.display = '';
            } else {
                box.classList.add('empty');
                em.textContent = '⚔️';
                nm.textContent = '무기 없음 · 구매해주세요';
                if (st) st.style.display = 'none';
                $('buyCard').style.display = '';
                $('enhanceCard').style.display = 'none';
            }
        }

        function renderHistory(items) {
            var box = $('historyList');
            if (!box) return;
            if (!items || !items.length) {
                box.innerHTML = '<div class="history-empty">기록이 없습니다.</div>';
                return;
            }
            var labels = { success: '⚔️ 성공', fail_protect: '👼 실패·수호', fail_break: '💥 실패·파손' };
            box.innerHTML = items.map(function(row) {
                var result = row.result || '';
                var label = labels[result] || result;
                var weapon = row.item || '(무기)';
                if (row.style) weapon = row.style + ' ' + weapon;
                var before = parseInt(row.enhance_before, 10) || 0;
                var after = parseInt(row.enhance_after, 10) || 0;
                var change = result === 'success' ? ('+' + before + ' → +' + after)
                    : (result === 'fail_protect' ? ('+' + before + ' 유지') : ('+' + before + ' 파손'));
                var time = row.regdate ? String(row.regdate).substring(5, 16).replace('T', ' ') : '';
                var extra = [];
                if (row.challenge_mode === '1' || row.challenge_mode === 1) {
                    extra.push('+20도전' + (row.challenge_target ? '[' + row.challenge_target + ']' : ''));
                }
                if (row.eunchong === '1' || row.eunchong === 1) extra.push('은총');
                if (row.suho_used === '1' || row.suho_used === 1) extra.push('수호소모');
                var sub = '주사위 ' + (row.dice_roll || 0) + ' · ' + fmtNyang(row.cost_fmt, row.cost || 0);
                if (extra.length) sub += ' · ' + extra.join(' · ');
                return '<div class="history-item">'
                    + '<div class="hi-top"><span class="hi-result ' + result + '">' + label + '</span><span class="hi-time">' + time + '</span></div>'
                    + '<div>' + weapon + ' ' + change + '</div>'
                    + '<div class="hi-sub">' + sub + '</div>'
                    + '</div>';
            }).join('');
        }

        var historyOpen = false;
        var castOpen = false;
        var enhancing = false;

        function setCastOpen(open) {
            castOpen = !!open;
            var card = $('castCard');
            var toggle = $('castToggle');
            var body = $('castBody');
            if (card) card.classList.toggle('open', castOpen);
            if (toggle) toggle.setAttribute('aria-expanded', castOpen ? 'true' : 'false');
            if (body) body.hidden = !castOpen;
        }

        function toggleCast() {
            setCastOpen(!castOpen);
        }

        function setHistoryOpen(open) {
            historyOpen = !!open;
            var card = $('historyCard');
            var toggle = $('historyToggle');
            var body = $('historyBody');
            if (card) card.classList.toggle('open', historyOpen);
            if (toggle) toggle.setAttribute('aria-expanded', historyOpen ? 'true' : 'false');
            if (body) body.hidden = !historyOpen;
            if (historyOpen) {
                var box = $('historyList');
                if (box && !box.querySelector('.history-item')) {
                    box.innerHTML = '<div class="history-empty">불러오는 중…</div>';
                }
                loadHistory();
            }
        }

        function toggleHistory() {
            setHistoryOpen(!historyOpen);
        }

        function loadHistory() {
            ajax({ action: 'history', limit: 15 }, function(j) {
                if (!j.ok) {
                    renderHistory([]);
                    return;
                }
                renderHistory(j.items || []);
            });
        }

        function isAttackWeapon(item) {
            return item === '🪈단소' || item === '🏹활' || item === '🏹 활';
        }
        function isMagicWeapon(item) {
            return item === '🪄마법' || item === '🪄 마법';
        }

        function renderTrialCast() {
            var box = $('castCard');
            var title = $('castCardTitle');
            var castStatus = $('trialCastStatus');
            var protectStatus = $('trialProtectStatus');
            var protectStatusRow = $('trialProtectStatusRow');
            var btnTrialCast = $('btnTrialCast');
            var btnTrialProtect = $('btnTrialProtect');
            var trialRow = $('trialBtnRow');
            var hint = $('trialHint');
            if (!box) return;
            var isAttack = isAttackWeapon(state.item);
            var isMagic = isMagicWeapon(state.item);
            var show = state.item && state.enhance >= 10 && (isAttack || isMagic);
            box.style.display = show ? '' : 'none';
            if (!show) return;

            if (title) title.textContent = isMagic ? '✨ 마법 맛보기' : '⚔️ 무기 공격 맛보기';
            var castAvailable = !!state.trial_cast_available;
            var protectAvailable = !!state.trial_protect_available;

            if (isMagic) {
                if (protectStatusRow) protectStatusRow.style.display = '';
                if (castStatus && castStatus.parentElement) {
                    castStatus.parentElement.querySelector('span').textContent = '맛보기 시전 (하루 1회)';
                }
                if (castStatus) castStatus.textContent = castAvailable ? '가능' : '사용 완료';
                if (protectStatus) protectStatus.textContent = protectAvailable ? '가능' : '사용 완료';
            } else {
                if (protectStatusRow) protectStatusRow.style.display = 'none';
                if (castStatus && castStatus.parentElement) {
                    castStatus.parentElement.querySelector('span').textContent = '오늘 맛보기 (하루 1회)';
                }
                if (castStatus) castStatus.textContent = castAvailable ? '가능' : '사용 완료';
            }

            if (hint) {
                hint.textContent = isMagic
                    ? '시전·보호 각 하루 1회 · 한도·내구도·본인 비용 없음'
                    : '한도·내구도·본인 비용 없음 · 대상 효과는 실제 적용';
            }
            if (trialRow) trialRow.className = 'cast-btns' + (isMagic ? '' : ' single');
            if (btnTrialProtect) btnTrialProtect.style.display = isMagic ? '' : 'none';
            if (btnTrialCast) {
                btnTrialCast.disabled = !castAvailable;
                btnTrialCast.textContent = isMagic ? '👅 맛보기 시전' : '👅 맛보기 공격';
            }
            if (btnTrialProtect) btnTrialProtect.disabled = !protectAvailable;
        }

        function renderSuhoPanel() {
            var stack = state.enhance_suho || 0;
            var items = state.suho_item || 0;
            var stackMini = $('suhoStackMini');
            if (stackMini) stackMini.textContent = fmt(stack) + '회';
            var useSec = $('suhoUseSection');
            var buyAlert = $('suhoBuyAlert');
            if (useSec) useSec.style.display = items > 0 ? '' : 'none';
            if (buyAlert) buyAlert.style.display = (items < 1 && stack < 1) ? '' : 'none';
            var mini = $('suhoItemMini');
            if (mini) mini.textContent = fmt(items) + '개 보유';
            var priceMini = $('suhoPriceMini');
            if (priceMini) priceMini.textContent = fmtNyang(state.suho_price_fmt, state.suho_price || 0);
            var tabSuho = $('tabSuhoCount');
            if (tabSuho) tabSuho.textContent = fmt(items);
        }

        function applyStatusFromResponse(j) {
            if (typeof j.point !== 'undefined') {
                state.point = j.point;
                state.point_fmt = j.point_fmt || state.point_fmt;
            }
            if (typeof j.item !== 'undefined') state.item = j.item;
            if (typeof j.enhance !== 'undefined') state.enhance = j.enhance;
            if (typeof j.style !== 'undefined') state.style = j.style;
            if (typeof j.enhance_suho !== 'undefined') state.enhance_suho = j.enhance_suho;
            if (typeof j.suho_item !== 'undefined') state.suho_item = j.suho_item;
            if (typeof j.suho_price !== 'undefined') state.suho_price = j.suho_price;
            if (typeof j.suho_price_fmt !== 'undefined') state.suho_price_fmt = j.suho_price_fmt;
            if (typeof j.cost_next !== 'undefined') state.cost_next = j.cost_next;
            if (typeof j.cost_next_fmt !== 'undefined') state.cost_next_fmt = j.cost_next_fmt;
            if (typeof j.success_rate !== 'undefined') state.success_rate = j.success_rate;
            if (typeof j.durability !== 'undefined') state.durability = j.durability;
            if (typeof j.은총활성 !== 'undefined') state.은총활성 = j.은총활성;
            if (typeof j.은총개수 !== 'undefined') state.은총개수 = j.은총개수;
            if (typeof j.은총_end !== 'undefined') state.은총_end = j.은총_end;
            if (typeof j.은총_left_sec !== 'undefined') state.은총_left_sec = j.은총_left_sec;
            if (typeof j.trial_cast_available !== 'undefined') state.trial_cast_available = j.trial_cast_available;
            if (typeof j.trial_protect_available !== 'undefined') state.trial_protect_available = j.trial_protect_available;
        }

        function setEnhanceButtonsBusy(busy) {
            var btnE = $('btnEnhance');
            var btnE10 = $('btnEnhance10');
            if (busy) {
                if (btnE) btnE.disabled = true;
                if (btnE10) btnE10.disabled = true;
                return;
            }
            var maxed = state.enhance >= state.max_level;
            if (btnE) btnE.disabled = maxed;
            if (btnE10) btnE10.disabled = maxed;
        }

        function openSuhoBuyPanel() {
            setActiveTab('suho');
            var tabs = document.querySelector('.item-tabs');
            if (tabs && tabs.scrollIntoView) {
                tabs.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
            renderSuhoPanel();
        }

        function applyEnhanceResult(j) {
            applyStatusFromResponse(j);
            var kind = 'info';
            if (j.result === 'success') kind = 'success';
            else if (j.result === 'fail_protect') kind = 'info';
            else if (j.result === 'fail_break') kind = 'fail';
            showResult(j.data, kind);
            if (j.result === 'fail_break' || j.need_suho) {
                openSuhoBuyPanel();
            }
            renderAll();
        }

        function renderAll() {
            $('stPoint').textContent = fmtNyang(state.point_fmt, state.point);
            $('stSuho').textContent = fmt(state.enhance_suho) + '회';
            $('stSuhoItem').textContent = fmt(state.suho_item) + '개';
            renderSuhoPanel();
            renderWeapon();
            if (state.item) {
                var next = Math.min(state.max_level, state.enhance + 1);
                $('levNext').textContent = '+' + state.enhance + ' → +' + next;
                $('costNext').textContent = fmtNyang(state.cost_next_fmt, state.cost_next);
                $('rateNext').textContent = state.success_rate || '—';
                var dEl = $('durability');
                if (state.durability > 0) {
                    if (!dEl) {
                        var costBlock = $('enhanceCard');
                        var row = document.createElement('div');
                        row.className = 'cost-line';
                        row.innerHTML = '<span>예상 최대 내구도</span><b id="durability">' + state.durability + '</b>';
                        costBlock.insertBefore(row, costBlock.querySelector('.enhance-btns'));
                    } else {
                        dEl.textContent = state.durability;
                        dEl.parentElement.style.display = '';
                    }
                } else if (dEl) {
                    dEl.parentElement.style.display = 'none';
                }
                var btnE = $('btnEnhance');
                var btnE10 = $('btnEnhance10');
                if (btnE) btnE.disabled = (state.enhance >= state.max_level);
                if (btnE10) btnE10.disabled = (state.enhance >= state.max_level);
                if (state.enhance >= state.max_level) {
                    if (btnE) btnE.textContent = '⚔️ 최대 강화 달성';
                    if (btnE10) btnE10.textContent = '—';
                } else {
                    if (btnE) btnE.textContent = '⚔️ 강화 시도 (' + fmtNyang(state.cost_next_fmt, state.cost_next) + ')';
                    if (btnE10) btnE10.textContent = '⚔️ 10회 연속';
                }
            }
            renderEunchong();
            renderTrialCast();
            if (historyOpen) loadHistory();
        }

        function doTrialCast(castKind) {
            castKind = castKind || 'cast';
            var btnTrialCast = $('btnTrialCast');
            var btnTrialProtect = $('btnTrialProtect');
            if (btnTrialCast) btnTrialCast.disabled = true;
            if (btnTrialProtect) btnTrialProtect.disabled = true;
            showResult(castKind === 'protect' ? '맛보기 보호 중...' : '맛보기 시전 중...', 'info');
            ajax({ action: 'trial_cast', cast_kind: castKind }, function(j) {
                if (!j.ok) {
                    showResult(j.data || '맛보기 실패', 'fail');
                    renderTrialCast();
                    return;
                }
                if (typeof j.trial_cast_available !== 'undefined') state.trial_cast_available = j.trial_cast_available;
                if (typeof j.trial_protect_available !== 'undefined') state.trial_protect_available = j.trial_protect_available;
                if (typeof j.point !== 'undefined') {
                    state.point = j.point;
                    state.point_fmt = j.point_fmt || state.point_fmt;
                }
                var kind = j.cast_kind === 'attack' && !j.blocked ? 'success' : 'info';
                showResult(j.data, kind);
                renderAll();
            });
        }

        function doBuy(weapon) {
            var btns = document.querySelectorAll('#buyCard .btn');
            btns.forEach(function(b) { b.disabled = true; });
            showResult('구매 중...', 'info');
            ajax({ action: 'buy', weapon: weapon }, function(j) {
                btns.forEach(function(b) { b.disabled = false; });
                if (!j.ok) { showResult(j.data || '구매 실패', 'fail'); return; }
                showResult(j.data, 'success');
                refreshStatus();
            });
        }

        function doEnhance() {
            if (!canRunEnhanceNow()) return;
            enhancing = true;
            setEnhanceButtonsBusy(true);
            showResult('강화 중...', 'info');
            ajax({ action: 'enhance' }, function(j) {
                if (!j.ok) {
                    enhancing = false;
                    showResult(j.data || '강화 실패', 'fail');
                    setEnhanceButtonsBusy(false);
                    return;
                }
                applyEnhanceResult(j);
                enhancing = false;
            });
        }

        function doEnhanceBatch() {
            if (!canRunEnhanceBatchNow()) return;
            enhancing = true;
            setEnhanceButtonsBusy(true);
            showResult('10회 연속 강화 중...', 'info');
            ajax({ action: 'enhance_batch', times: 10 }, function(j) {
                if (!j.ok) {
                    enhancing = false;
                    showResult(j.data || '강화 실패', 'fail');
                    setEnhanceButtonsBusy(false);
                    return;
                }
                applyEnhanceResult(j);
                enhancing = false;
            });
        }

        function doBuyEnhanceSuho(qty) {
            var btns = document.querySelectorAll('[data-buy-enhance-suho]');
            btns.forEach(function(b) { b.disabled = true; });
            showResult('강화 수호 ' + qty + '회 구매 중...', 'info');
            ajax({ action: 'use_suho', count: qty, buy: 1 }, function(j) {
                btns.forEach(function(b) { b.disabled = false; });
                if (!j.ok) { showResult(j.data || '강화 수호 구매 실패', 'fail'); return; }
                if (typeof j.enhance_suho !== 'undefined') state.enhance_suho = j.enhance_suho;
                if (typeof j.suho_item !== 'undefined') state.suho_item = j.suho_item;
                if (typeof j.suho_price !== 'undefined') state.suho_price = j.suho_price;
                if (typeof j.suho_price_fmt !== 'undefined') state.suho_price_fmt = j.suho_price_fmt;
                if (typeof j.point !== 'undefined') {
                    state.point = j.point;
                    state.point_fmt = j.point_fmt || state.point_fmt;
                }
                showResult(j.data, 'success');
                renderAll();
            });
        }

        function doUseSuho() {
            var n = parseInt($('suhoCount').value, 10);
            if (!n || n < 1) { showResult('1 이상의 숫자를 입력하세요.', 'fail'); return; }
            var b = $('btnUseSuho');
            b.disabled = true;
            showResult('수호 사용 중...', 'info');
            ajax({ action: 'use_suho', count: n }, function(j) {
                b.disabled = false;
                if (!j.ok) { showResult(j.data || '수호 사용 실패', 'fail'); return; }
                if (typeof j.enhance_suho !== 'undefined') state.enhance_suho = j.enhance_suho;
                if (typeof j.suho_item !== 'undefined') state.suho_item = j.suho_item;
                if (typeof j.suho_price !== 'undefined') state.suho_price = j.suho_price;
                if (typeof j.suho_price_fmt !== 'undefined') state.suho_price_fmt = j.suho_price_fmt;
                if (typeof j.point !== 'undefined') {
                    state.point = j.point;
                    state.point_fmt = j.point_fmt || state.point_fmt;
                }
                showResult(j.data, 'success');
                renderAll();
            });
        }

        function doUseEunchong() {
            var b = $('btnUseEunchong');
            if (!b || b.disabled) return;
            b.disabled = true;
            showResult('은총 사용 중...', 'info');
            ajax({ action: 'use_eunchong' }, function(j) {
                if (!j.ok) { showResult(j.data || '은총 사용 실패', 'fail'); b.disabled = false; return; }
                showResult(j.data, 'success');
                refreshStatus();
            });
        }

        // 이벤트 바인딩
        document.querySelectorAll('.btn-buy, .btn-buy-random').forEach(function(b) {
            b.addEventListener('click', function() {
                doBuy(b.getAttribute('data-weapon'));
            });
        });
        var be = $('btnEnhance');
        if (be) be.addEventListener('click', doEnhance);
        var be10 = $('btnEnhance10');
        if (be10) be10.addEventListener('click', doEnhanceBatch);
        var btt = $('btnTrialCast');
        if (btt) btt.addEventListener('click', function() { doTrialCast('cast'); });
        var btp = $('btnTrialProtect');
        if (btp) btp.addEventListener('click', function() { doTrialCast('protect'); });
        $('btnUseSuho').addEventListener('click', doUseSuho);
        document.querySelectorAll('[data-buy-enhance-suho]').forEach(function(b) {
            b.addEventListener('click', function() {
                doBuyEnhanceSuho(parseInt(b.getAttribute('data-buy-enhance-suho'), 10));
            });
        });
        var bEun = $('btnUseEunchong');
        if (bEun) bEun.addEventListener('click', doUseEunchong);
        $('btnRefresh').addEventListener('click', refreshStatus);
        var ht = $('historyToggle');
        if (ht) ht.addEventListener('click', toggleHistory);
        var ct = $('castToggle');
        if (ct) ct.addEventListener('click', toggleCast);
        document.querySelectorAll('.tab-btn').forEach(function(b) {
            b.addEventListener('click', function() {
                toggleTab(b.getAttribute('data-tab'));
            });
        });

        function isEnhanceShortcutBlocked() {
            var el = document.activeElement;
            if (!el) return false;
            var tag = el.tagName ? el.tagName.toUpperCase() : '';
            if (tag === 'TEXTAREA' || tag === 'SELECT') return true;
            if (tag === 'INPUT') {
                var ty = (el.type || '').toLowerCase();
                if (ty === 'text' || ty === 'password' || ty === 'search' || ty === 'number' || ty === 'email' || ty === 'tel') {
                    return true;
                }
            }
            return !!el.isContentEditable;
        }

        function isEnhanceCardVisible() {
            var card = $('enhanceCard');
            return !!(card && card.offsetParent !== null);
        }

        function canRunEnhanceNow() {
            if (enhancing) return false;
            if (!isEnhanceCardVisible()) return false;
            if (state.enhance >= state.max_level) return false;
            var btn = $('btnEnhance');
            return !!(btn && !btn.disabled);
        }

        function canRunEnhanceBatchNow() {
            if (enhancing) return false;
            if (!isEnhanceCardVisible()) return false;
            if (state.enhance >= state.max_level) return false;
            var btn = $('btnEnhance10');
            return !!(btn && !btn.disabled);
        }

        document.addEventListener('keydown', function(e) {
            if (e.altKey || e.ctrlKey || e.metaKey) return;
            if (isEnhanceShortcutBlocked()) return;
            var key = e.key;
            var isOne = key === '1' || e.code === 'Digit1' || e.code === 'Numpad1';
            var isTwo = key === '2' || e.code === 'Digit2' || e.code === 'Numpad2';
            if (!isOne && !isTwo) return;
            if (isOne && canRunEnhanceNow()) {
                e.preventDefault();
                doEnhance();
            } else if (isTwo && canRunEnhanceBatchNow()) {
                e.preventDefault();
                doEnhanceBatch();
            }
        });

        // 은총 버프 카운트다운 (1초마다)
        setInterval(function() {
            if (state.은총_left_sec > 0) {
                state.은총_left_sec -= 1;
                if (state.은총_left_sec <= 0) {
                    state.은총_left_sec = 0;
                    state.은총활성 = 0;
                    renderEunchong();
                    // 버프 종료 시 상태 새로고침 (성공률 등)
                    refreshStatus();
                } else {
                    renderEunchong();
                }
            }
        }, 1000);
        // 초기 상태 반영
        renderAll();
    })();
    </script>
    <?php } ?>
</body>
</html>
