<?php
/**
 * 홀짝 도전 게임 웹 (DB·정산·채팅 tb_odd_even_state 공유)
 * 접속 시 ?code=xxx 로 tb_member.code 와 매칭해 인증
 *
 * URL:
 *   /api/game/odd_even_web.php?code=XXXX
 *   호환: /page/game.php?code=XXXX
 *
 * 제공 액션 (AJAX JSON):
 *   action=status   — 내 상태 · 연승 · 진행 중 판 · 타인 점유(room_lock)
 *   action=bet      — .도전 금액 (서버가 1·2·3 선정, 배팅 선차감)
 *   action=pick     — 홀/짝 선택 (결과 정산)
 *   action=cancel   — 대기 중(전액 환급·수수료 없음) 또는 연승 포기(2연승~)
 *   action=set_mode — (폐지) 하드모드 고정 · 요청 시 hard만 저장
 *   action=swap_np  — 본방냥 전액 → 게임냥 (5% 삭제 · 95% 환율 교환)
 *   action=claim_payback — 웹 페이백 누적분 게임냥 수령
 *   action=donate_hard5 — 3연승 이상 당첨금 10% → config.후원모금함 적립
 *   action=claim_donate_pool — 후원모금함 수령 (민호만)
 */

// ----- 홀짝 설정 -----
$GAME_최소배팅 = 1000000;
$GAME_최대배팅 = 100000000000; // 1000억
$GAME_고액최소자산 = 50000000000000; // 50조 이상이면 최대 배팅 상향
$GAME_고액최대배팅 = 1000000000000; // 1조
$GAME_타임아웃초 = 1800; // 30분
$GAME_연승최대 = 5;
require_once __DIR__ . '/odd_even_guards.php';
require_once __DIR__ . '/odd_even_settle.inc.php';
require_once __DIR__ . '/odd_even_payback.inc.php';
require_once __DIR__ . '/odd_even_donate.inc.php';

/**
 * 웹 진행 모드 (채팅 info2/mutual 도 닉별 multi — 웹·채팅 동시 진행 가능)
 *   'single' — 방 전체 1명만 진행·연승 (타인 room_lock)
 *   'multi'  — 닉별 동시 진행 (타인 대기 없음, 승리 시 타인 연승 유지)
 */
$GAME_웹_진행모드 = 'multi';

// ----- 유틸 -----
function game_web_is_multi_room() {
    global $GAME_웹_진행모드;
    return strtolower(trim((string)$GAME_웹_진행모드)) === 'multi';
}
function game_json($data) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
/** 게임냥 잔액 → 정수 문자열 (부호 유지 · PHP_INT_MAX 초과 보존) */
function game_point_str($v): string {
    $raw = trim((string)$v);
    $neg = (isset($raw[0]) && $raw[0] === '-');
    if (function_exists('냥_정수문자열')) {
        $abs = 냥_정수문자열($v);
    } else {
        $abs = ltrim(preg_replace('/[^\d]/', '', $raw), '0') ?: '0';
    }
    if ($abs === '0') {
        return '0';
    }
    return $neg ? ('-' . $abs) : $abs;
}
function game_auth($code) {
    $code = is_string($code) ? trim($code) : '';
    if ($code === '') return null;
    $esc = addslashes($code);
    // CAST AS CHAR: DECIMAL/BIGINT 큰 값을 PHP int로 깨뜨리지 않음
    $row = db_select("SELECT idx, name, CAST(point AS CHAR) AS point, num FROM tb_member WHERE code = '{$esc}' LIMIT 1");
    if (empty($row['name'])) return null;
    return $row;
}

/** 방 프로필색표 1~45 hex (page/color.php 와 동일) */
function game_profile_palette() {
    return [
        '#FAE100', '#F14F4A', '#EC7F5A', '#EE9830', '#8DBC30',
        '#4AA366', '#4EA698', '#4EA5B2', '#4D9DD8', '#4469A0',
        '#735FA7', '#9158B6', '#D35497', '#D7456A', '#FBF28C',
        '#EA9A93', '#ED9D8E', '#ECB273', '#B3C270', '#7FCF90',
        '#89CAC2', '#96C7CF', '#7EB9DB', '#88A2D4', '#AC9CDA',
        '#B785CB', '#E480B6', '#EB9EAE', '#F8F5D5', '#F8DAD8',
        '#F7D7C7', '#EFDBB7', '#E7F5BA', '#B9EBC2', '#CCEFF1',
        '#C6EEEE', '#C5E1EF', '#CADCEA', '#D8D9ED', '#E0D0EB',
        '#F7D5E6', '#F9E0E5', '#ffffff', '#C3C3C3', '#000000',
    ];
}

/** tb_member.num → 프로필 hex */
function game_profile_hex($num) {
    $palette = game_profile_palette();
    $n = (int)$num;
    if ($n < 1) {
        return $palette[0];
    }
    if ($n <= count($palette)) {
        return $palette[$n - 1];
    }
    return $palette[($n - 1) % count($palette)];
}
function game_배수($streak, $모드 = null) {
    return 홀짝도전_배수($streak, $모드);
}
function game_state_row($닉_esc) {
    $r = 홀짝_판_조회($닉_esc);
    if (!is_array($r)) {
        return [];
    }
    $r['nick'] = (string)$닉_esc;
    return $r;
}
/** @return string 정수 문자열 — (int) 캐스팅 금지(922경 잘림) */
function game_min_bet($행, $가진냥) {
    if (function_exists('홀짝_연승바닥배팅')) {
        return 홀짝_연승바닥배팅($가진냥, $행['streak_max_bet'] ?? 0);
    }
    return 홀짝_기본배팅_문자열($가진냥);
}
function game_max_bet($가진냥) {
    return 홀짝_최대배팅_문자열($가진냥);
}

/** multi 모드: 이미 읽은 행으로 본인 연승 타임아웃만 정리 (추가 SELECT 없음) */
function game_web_apply_own_streak_timeout_inline($닉_esc, $두자리닉넴, array &$행, $GAME_타임아웃초) {
    $streak = (int)($행['streak'] ?? 0);
    if ($streak <= 0 || empty($행['updated_at'])) {
        return;
    }
    $경과 = time() - (int)strtotime($행['updated_at']);
    if ($경과 < (int)$GAME_타임아웃초) {
        return;
    }
    $이전맥스배 = 홀짝_냥($행['streak_max_bet'] ?? 0);
    if ($이전맥스배 !== '0') {
        홀짝_연승포기_수수료처리($닉_esc, $두자리닉넴, $이전맥스배);
    }
    홀짝_연승포기_오퍼_컬럼확보();
    db_query("UPDATE tb_odd_even_state SET streak = 0, streak_max_bet = 0, giveup_offer = 0 WHERE nick = '{$닉_esc}' LIMIT 1");
    if (function_exists('홀짝_하드후원_오퍼_클리어')) {
        홀짝_하드후원_오퍼_클리어($닉_esc);
    }
    $행['streak'] = 0;
    $행['streak_max_bet'] = 0;
}

/** 냥 금액 → 해·경·조·억·만·원까지 (조에서 끊지 않음) */
function game_금액_축약_짧게($n) {
    if (function_exists('랭킹_게임냥표시')) {
        $t = 랭킹_게임냥표시($n, '');
        return ($t === '' || $t === '0냥') ? '0' : $t;
    }
    return game_금액_축약($n);
}

/** 냥 금액 → 해·경·조·억·만·원 축약 */
function game_금액_축약($n) {
    $raw = trim((string)$n);
    $neg = (isset($raw[0]) && $raw[0] === '-');
    if (function_exists('랭킹_게임냥표시')) {
        $txt = 랭킹_게임냥표시($n, '');
        if ($txt !== '' && $txt !== '0') {
            // 랭킹이 이미 부호 처리
            return $txt;
        }
    }
    $abs = function_exists('냥_정수문자열')
      ? 냥_정수문자열($n)
      : (ltrim(preg_replace('/[^\d]/', '', $raw), '0') ?: '0');
    if (function_exists('냥_경조억_축약문구')) {
        $txt = 냥_경조억_축약문구($abs, '', '');
        if ($txt !== '0' && $txt !== '') {
            return ($neg && strpos($txt, '-') !== 0 ? '-' : '') . $txt;
        }
    }
    return ($neg ? '-' : '') . ($abs === '0' ? '0' : $abs);
}

/** 내 정보 보유냥 표시 — 해·경·조·억·만·원까지 */
function game_보유냥_표시($n) {
    if (function_exists('랭킹_게임냥표시')) {
        return 랭킹_게임냥표시($n, '');
    }
    return game_금액_축약($n);
}

/**
 * multi 모드: 본인 pending·연승 타임아웃만 정리 (타인 상태는 건드리지 않음).
 */
function game_web_expire_own_timed_out_states($두자리닉넴, $GAME_타임아웃초) {
    $만료초 = (int)$GAME_타임아웃초;
    $닉_esc = addslashes($두자리닉넴);

    $진행행 = @db_select("
      SELECT pending_at, CAST(pending_bet AS CHAR) AS pending_bet, pending_answer,
             TIMESTAMPDIFF(SECOND, pending_at, NOW()) AS elapsed_sec
      FROM tb_odd_even_state
      WHERE nick = '{$닉_esc}' AND pending_bet > 0
      LIMIT 1
    ");
    if ($진행행 && (int)($진행행['elapsed_sec'] ?? 0) >= $만료초) {
        $만료환 = 홀짝_냥($진행행['pending_bet'] ?? 0);
        if ($만료환 !== '0') {
            홀짝_타임아웃_환급처리($닉_esc, $두자리닉넴, $만료환);
        }
        if (function_exists('홀짝_배팅취소_봉인복구')) {
            홀짝_배팅취소_봉인복구($닉_esc, $진행행['pending_answer'] ?? 0);
        }
        db_query("UPDATE tb_odd_even_state SET pending_bet = 0, pending_answer = NULL, pending_win_mult = 0, pending_lose_mult = 0, pending_at = NULL WHERE nick = '{$닉_esc}' LIMIT 1");
    }

    $연승행 = @db_select("
      SELECT CAST(streak_max_bet AS CHAR) AS streak_max_bet,
             TIMESTAMPDIFF(SECOND, updated_at, NOW()) AS elapsed_sec
      FROM tb_odd_even_state
      WHERE nick = '{$닉_esc}' AND streak > 0
      LIMIT 1
    ");
    if ($연승행 && (int)($연승행['elapsed_sec'] ?? 0) >= $만료초) {
        $이전맥스배 = 홀짝_냥($연승행['streak_max_bet'] ?? 0);
        if ($이전맥스배 !== '0') {
            홀짝_연승포기_수수료처리($닉_esc, $두자리닉넴, $이전맥스배);
        }
        홀짝_연승포기_오퍼_컬럼확보();
        db_query("UPDATE tb_odd_even_state SET streak = 0, streak_max_bet = 0, giveup_offer = 0 WHERE nick = '{$닉_esc}' LIMIT 1");
        if (function_exists('홀짝_하드후원_오퍼_클리어')) {
            홀짝_하드후원_오퍼_클리어($닉_esc);
        }
    }
}

/**
 * single 모드: 만료된 pending·연승 상태 정리(UPDATE·지급로그). 무거워서 status 폴링마다 돌리지 않음.
 */
function game_web_expire_timed_out_states($두자리닉넴, $GAME_타임아웃초) {
    $만료초 = (int)$GAME_타임아웃초;

    $진행행 = @db_select("
      SELECT nick, pending_at, CAST(pending_bet AS CHAR) AS pending_bet, pending_answer,
             TIMESTAMPDIFF(SECOND, pending_at, NOW()) AS elapsed_sec
      FROM tb_odd_even_state
      WHERE pending_bet > 0
      ORDER BY pending_at ASC
      LIMIT 1
    ");
    if ($진행행 && trim((string)($진행행['nick'] ?? '')) !== '') {
        $진행중닉 = trim((string)$진행행['nick']);
        $경과초 = (int)($진행행['elapsed_sec'] ?? 0);

        if ($진행중닉 !== $두자리닉넴) {
            if ($경과초 >= $만료초) {
                $진행중닉_esc = addslashes($진행중닉);
                $만료환 = 홀짝_냥($진행행['pending_bet'] ?? 0);
                if ($만료환 !== '0') {
                    홀짝_타임아웃_환급처리($진행중닉_esc, $진행중닉, $만료환);
                }
                if (function_exists('홀짝_배팅취소_봉인복구')) {
                    홀짝_배팅취소_봉인복구($진행중닉_esc, $진행행['pending_answer'] ?? 0);
                }
                db_query("UPDATE tb_odd_even_state SET pending_bet = 0, pending_answer = NULL, pending_win_mult = 0, pending_lose_mult = 0, pending_at = NULL WHERE nick = '{$진행중닉_esc}' LIMIT 1");
            }
        } elseif ($경과초 >= $만료초) {
            $닉_esc = addslashes($두자리닉넴);
            $만료환 = 홀짝_냥($진행행['pending_bet'] ?? 0);
            if ($만료환 !== '0') {
                홀짝_타임아웃_환급처리($닉_esc, $두자리닉넴, $만료환);
            }
            if (function_exists('홀짝_배팅취소_봉인복구')) {
                홀짝_배팅취소_봉인복구($닉_esc, $진행행['pending_answer'] ?? 0);
            }
            db_query("UPDATE tb_odd_even_state SET pending_bet = 0, pending_answer = NULL, pending_win_mult = 0, pending_lose_mult = 0, pending_at = NULL WHERE nick = '{$닉_esc}' LIMIT 1");
        }
    }

    $연승행 = @db_select("
      SELECT nick, streak, CAST(streak_max_bet AS CHAR) AS streak_max_bet, updated_at,
             TIMESTAMPDIFF(SECOND, updated_at, NOW()) AS elapsed_sec
      FROM tb_odd_even_state
      WHERE streak > 0
      ORDER BY streak DESC, updated_at DESC
      LIMIT 1
    ");
    if ($연승행 && trim((string)($연승행['nick'] ?? '')) !== '') {
        $연승닉 = trim((string)$연승행['nick']);
        if ($연승닉 !== $두자리닉넴) {
            $연승경과초 = (int)($연승행['elapsed_sec'] ?? 0);
            if ($연승경과초 >= $만료초) {
                $연승닉_esc = addslashes($연승닉);
                $이전맥스배 = 홀짝_냥($연승행['streak_max_bet'] ?? 0);
                if ($이전맥스배 !== '0') {
                    홀짝_연승포기_수수료처리($연승닉_esc, $연승닉, $이전맥스배);
                }
                홀짝_연승포기_오퍼_컬럼확보();
                db_query("UPDATE tb_odd_even_state SET streak = 0, streak_max_bet = 0, giveup_offer = 0 WHERE nick = '{$연승닉_esc}' LIMIT 1");
                if (function_exists('홀짝_하드후원_오퍼_클리어')) {
                    홀짝_하드후원_오퍼_클리어($연승닉_esc);
                }
            }
        }
    }
}

/**
 * 타인 점유(room_lock)만 조회 — SELECT 2회, 폴링용 경량.
 *
 * @return array{room_lock: array|null}
 */
function game_web_room_lock_status($두자리닉넴, $GAME_타임아웃초) {
    if (game_web_is_multi_room()) {
        return ['room_lock' => null];
    }
    $만료초 = (int)$GAME_타임아웃초;
    $room_lock = null;

    $진행확인 = @db_select("
      SELECT nick,
             TIMESTAMPDIFF(SECOND, pending_at, NOW()) AS elapsed_sec
      FROM tb_odd_even_state
      WHERE pending_bet > 0
      ORDER BY pending_at ASC
      LIMIT 1
    ");
    if ($진행확인 && trim((string)($진행확인['nick'] ?? '')) !== '') {
        $잠금닉 = trim((string)$진행확인['nick']);
        $경과2 = (int)($진행확인['elapsed_sec'] ?? 0);
        if ($잠금닉 !== $두자리닉넴 && $경과2 < $만료초) {
            $room_lock = [
                'type'     => 'pending',
                'nick'     => $잠금닉,
                'left_sec' => max(0, $만료초 - $경과2),
            ];
        }
    }

    if ($room_lock === null) {
        $연승확인 = @db_select("
          SELECT nick, streak,
                 TIMESTAMPDIFF(SECOND, updated_at, NOW()) AS elapsed_sec
          FROM tb_odd_even_state
          WHERE streak > 0
          ORDER BY streak DESC, updated_at DESC
          LIMIT 1
        ");
        if ($연승확인 && trim((string)($연승확인['nick'] ?? '')) !== '') {
            $잠금닉 = trim((string)$연승확인['nick']);
            $연경과 = (int)($연승확인['elapsed_sec'] ?? 0);
            if ($잠금닉 !== $두자리닉넴 && $연경과 < $만료초) {
                $room_lock = [
                    'type'     => 'streak',
                    'nick'     => $잠금닉,
                    'streak'   => 홀짝_연승_정규화((int)($연승확인['streak'] ?? 0)),
                    'left_sec' => max(0, $만료초 - $연경과),
                ];
            }
        }
    }

    return ['room_lock' => $room_lock];
}

/**
 * 채팅 단일방과 동일: 타임아웃 정리(선택) + room_lock.
 *
 * @param bool $with_expire true=배팅·페이지로드·주기 full_sync, false=가벼운 status 폴링
 */
function game_web_sync_global_room($두자리닉넴, $GAME_타임아웃초, $with_expire = true) {
    if ($with_expire) {
        if (game_web_is_multi_room()) {
            game_web_expire_own_timed_out_states($두자리닉넴, $GAME_타임아웃초);
        } else {
            game_web_expire_timed_out_states($두자리닉넴, $GAME_타임아웃초);
        }
    }
    return game_web_room_lock_status($두자리닉넴, $GAME_타임아웃초);
}

/** tb_odd_even_log에서 최근부터 연속 승리만 따라가 현재 연승에 해당하는 사용자 픽(홀·짝)을 시간 순 배열로 */
/** 정산 직후 status와 동일한 연승·배수·픽 궤적 필드 (웹 즉시 반영용) */
function game_web_outcome_state_fields($닉_esc, $req_code, $streak후, $streak_max_bet = null, $streak_won = null, array $opts = []) {
    $streak후 = 홀짝_연승_정규화($streak후);
    if ($streak_max_bet === null) {
        $행 = game_state_row($닉_esc);
        $streak_max_bet = 홀짝_냥($행['streak_max_bet'] ?? 0);
    } else {
        $streak_max_bet = 홀짝_냥($streak_max_bet);
    }
    if (isset($opts['point']) && $opts['point'] !== '' && $opts['point'] !== null) {
        $표시포인트 = game_point_str($opts['point']);
    } else {
        $pt = @db_select("SELECT CAST(point AS CHAR) AS point FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
        $표시포인트 = is_array($pt) ? game_point_str($pt['point'] ?? 0) : '0';
    }
    if (isset($opts['odds_mode'])) {
        $모드 = 홀짝_모드_웹적용($opts['odds_mode'], $표시포인트);
    } else {
        $모드 = 홀짝_모드_웹읽기($닉_esc, $표시포인트);
    }
    $배 = game_배수($streak후, $모드);
    $out = [
        'point'          => $표시포인트,
        'streak'         => $streak후,
        'streak_after'   => $streak후,
        'streak_max_bet' => $streak_max_bet,
        'min_bet'        => game_min_bet(['streak_max_bet' => $streak_max_bet], $표시포인트),
        'max_bet'        => game_max_bet($표시포인트),
        'win_mult'       => $배['win'],
        'lose_mult'      => $배['lose'],
        'odds_mode'      => $모드,
    ];
    if (empty($opts['skip_streak_picks'])) {
        $out['streak_picks'] = game_streak_pick_labels($닉_esc, $streak후);
    }
    if ($streak_won !== null) {
        $out['streak_won'] = (int)$streak_won;
    }
    $out['payback_pool'] = 홀짝_웹_페이백_조회($닉_esc);
    $out['hard_donate_offer'] = function_exists('홀짝_하드후원_오퍼_표시용')
        ? 홀짝_하드후원_오퍼_표시용($닉_esc, $streak후)
        : 홀짝_하드후원_오퍼_조회($닉_esc);
    $out['giveup_offer'] = 홀짝_연승포기_오퍼_연승동기화($닉_esc, $streak후);
    return $out;
}

/** 이지/하드 3연승 이상 승리 시 후원 오퍼 저장 · 패/무/연승끊 시 클리어 */
function game_web_hard_donate_on_complete(array &$정산, $닉_esc) {
    if (empty($정산['ok'])) {
        return;
    }
    $결과 = (string)($정산['result'] ?? '');
    if ($결과 === 'lose' || $결과 === 'push') {
        홀짝_하드후원_오퍼_클리어($닉_esc);
        $정산['hard_donate_offer'] = '0';
        return;
    }
    if ($결과 !== 'win') {
        return;
    }
    $streak_won = (int)($정산['streak_won'] ?? 0);
    $streak_after = (int)($정산['streak_after'] ?? 0);
    // 3연승 미만: 잔여 오퍼 제거. 5연승 완주(streak_after=0)는 streak_won 기준.
    $활성연승 = !empty($정산['streak_completed']) ? $streak_won : max($streak_won, $streak_after);
    if ($활성연승 < 3) {
        // 1~2연승 구간에는 버튼 비표시 (이전 잔여 오퍼도 제거)
        홀짝_하드후원_오퍼_클리어($닉_esc);
        $정산['hard_donate_offer'] = '0';
        return;
    }
    $기준 = $정산['win_amount'] ?? 0;
    if (홀짝_하드후원_금액문자열($기준) === '0') {
        return;
    }
    홀짝_하드후원_오퍼_저장($닉_esc, $기준);
    $정산['hard_donate_offer'] = 홀짝_하드후원_오퍼_조회($닉_esc);
    if (!empty($정산['streak_completed'])) {
        $정산['hard_donate_force_show'] = 1;
    }
}

function game_streak_pick_labels($닉_esc, $streak) {
    $streak = 홀짝_연승_정규화($streak);
    if ($streak <= 0) {
        return [];
    }
    $limit = min(64, max($streak + 2, $streak * 3));
    $rs = @db_query("SELECT user_pick, result FROM tb_odd_even_log WHERE nick = '{$닉_esc}' ORDER BY idx DESC LIMIT {$limit}");
    if (!$rs) {
        return [];
    }
    $labels_newest_first = [];
    while ($row = @mysqli_fetch_assoc($rs)) {
        if (($row['result'] ?? '') !== 'win') {
            break;
        }
        $up = (int)($row['user_pick'] ?? 0);
        $labels_newest_first[] = ($up === 1) ? '홀' : '짝';
    }
    $chrono = array_reverse($labels_newest_first);
    $n = count($chrono);
    if ($n > $streak) {
        $chrono = array_slice($chrono, -$streak);
    }
    return $chrono;
}

// ----- 공통 요청값 -----
$req_action = isset($_REQUEST['action']) ? trim($_REQUEST['action']) : '';
$req_code   = isset($_REQUEST['code']) ? trim($_REQUEST['code']) : '';

$ACTIONS_NEED_AUTH = ['status', 'bet', 'bet_pick', 'pick', 'cancel', 'set_mode', 'swap_np', 'claim_payback', 'donate_hard5', 'claim_donate_pool'];
if (in_array($req_action, $ACTIONS_NEED_AUTH, true)) {
    if ($req_code === '') game_json(['ok' => false, 'data' => '초대 코드가 필요합니다.']);

    include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
    include_once $_SERVER['DOCUMENT_ROOT'] . '/api/function.php';

    $회원 = game_auth($req_code);
    if (!$회원) game_json(['ok' => false, 'data' => '유효하지 않은 초대 코드입니다.']);

    $닉 = trim($회원['name']);
    $두자리닉넴 = getTwoCharNick($닉);
    if ($두자리닉넴 === '') game_json(['ok' => false, 'data' => '닉네임을 확인할 수 없습니다.']);
    $닉_esc = addslashes($두자리닉넴);

    // ===== 상태 조회 =====
    if ($req_action === 'status') {
        $status_full_sync = !empty($_REQUEST['full_sync']);
        $sync_status = game_web_sync_global_room($두자리닉넴, $GAME_타임아웃초, $status_full_sync);
        $행 = game_state_row($닉_esc);
        $pt_now = @db_select("SELECT CAST(point AS CHAR) AS point, newpoint FROM tb_member WHERE code = '" . addslashes($req_code) . "' LIMIT 1");
        $표시포인트 = is_array($pt_now) ? game_point_str($pt_now['point'] ?? 0) : game_point_str($회원['point'] ?? 0);
        $표시본방냥 = is_array($pt_now) ? round((float)($pt_now['newpoint'] ?? 0), 1) : 0;

        $pending_bet = 홀짝_냥($행['pending_bet'] ?? 0);
        $pending_at_ts = !empty($행['pending_at']) ? strtotime($행['pending_at']) : 0;
        $남은초 = 0;
        $pending_expired = 0;
        if ($pending_bet !== '0' && $pending_at_ts > 0) {
            $남은초 = max(0, ($pending_at_ts + $GAME_타임아웃초) - time());
            if ($남은초 <= 0) $pending_expired = 1;
        }

        $streak = 홀짝_연승_읽기_및_복구($닉_esc, (int)($행['streak'] ?? 0));
        $odds_mode = 홀짝_모드_웹읽기($닉_esc, $표시포인트);
        $행['streak_max_bet'] = 홀짝_연승최고배팅_정리($닉_esc, $표시포인트, $행['streak_max_bet'] ?? 0);
        $바닥 = game_min_bet($행, $표시포인트);
        $배 = game_배수($streak, $odds_mode);
        $퀵정보 = function_exists('홀짝_회원_무기퀵정보')
          ? 홀짝_회원_무기퀵정보($req_code, true)
          : ['item' => '', 'enhance' => 0, 'quick_pcts' => [1.0], 'can_allin' => false, 'fixed_bet' => ''];
        game_json([
            'ok'              => true,
            'name'            => $닉,
            'point'           => $표시포인트,
            'newpoint'        => $표시본방냥,
            'streak'          => $streak,
            'streak_max'      => $GAME_연승최대,
            'streak_max_bet'  => 홀짝_냥($행['streak_max_bet'] ?? 0),
            'streak_picks'    => game_streak_pick_labels($닉_esc, $streak),
            'pending_bet'     => $pending_bet,
            'pending_left'    => $남은초,
            'pending_expired' => $pending_expired,
            'min_bet'         => $바닥,
            'max_bet'         => game_max_bet($표시포인트),
            'win_mult'        => $배['win'],
            'lose_mult'       => $배['lose'],
            'odds_mode'       => $odds_mode,
            'hard_mode_forced' => 홀짝_웹_하드모드_강제($표시포인트),
            'room_lock'       => $sync_status['room_lock'],
            'web_room_mode'   => game_web_is_multi_room() ? 'multi' : 'single',
            'timeout_sec'     => $GAME_타임아웃초,
            'payback_pool'    => 홀짝_웹_페이백_조회($닉_esc),
            'hard_donate_offer' => function_exists('홀짝_하드후원_오퍼_표시용')
                ? 홀짝_하드후원_오퍼_표시용($닉_esc, $streak)
                : 홀짝_하드후원_오퍼_조회($닉_esc),
            'donate_pool'     => 홀짝_하드후원_모금함_조회(),
            'can_claim_donate_pool' => 홀짝_하드후원_수령가능($두자리닉넴),
            'giveup_offer'    => 홀짝_연승포기_오퍼_연승동기화($닉_esc, $streak),
            'weapon_item'     => (string)($퀵정보['item'] ?? ''),
            'weapon_enhance'  => (int)($퀵정보['enhance'] ?? 0),
            'weapon_type'     => (int)($퀵정보['weapon_type'] ?? 0),
            'weapon_max'      => (int)($퀵정보['weapon_max'] ?? 0),
            'quick_pcts'      => (is_array($퀵정보['quick_pcts'] ?? null) && $퀵정보['quick_pcts'] !== [])
                ? $퀵정보['quick_pcts']
                : [1.0],
            'can_allin'       => !empty($퀵정보['can_allin']),
            'fixed_bet'       => '',
        ]);
    }

    // ===== 3연승+ 후원 (웹 전용 · 당첨금 10% → config.후원모금함) =====
    if ($req_action === 'donate_hard5') {
        $행 = game_state_row($닉_esc);
        if (홀짝_냥($행['pending_bet'] ?? 0) !== '0') {
            game_json(['ok' => false, 'data' => '⏳ 진행 중인 판이 있을 때는 후원할 수 없어요.']);
        }
        $결과 = 홀짝_하드후원_실행($두자리닉넴, $닉_esc);
        if (empty($결과['ok'])) {
            game_json(['ok' => false, 'data' => $결과['data'] ?? '❌ 후원에 실패했어요.', 'hard_donate_offer' => 홀짝_하드후원_오퍼_조회($닉_esc), 'donate_pool' => 홀짝_하드후원_모금함_조회()]);
        }
        $point_now = game_point_str($결과['point'] ?? 0);
        $행2 = game_state_row($닉_esc);
        $streak = 홀짝_연승_읽기_및_복구($닉_esc, (int)($행2['streak'] ?? 0));
        $odds_mode = 홀짝_모드_웹읽기($닉_esc, $point_now);
        $배 = game_배수($streak, $odds_mode);
        game_json([
            'ok' => true,
            'type' => 'donate_hard5',
            'result' => 'donate',
            'data' => $결과['data'],
            'amount' => (string)($결과['amount'] ?? '0'),
            'amount_display' => (string)($결과['amount_display'] ?? ''),
            'receiver' => (string)($결과['receiver'] ?? 홀짝_하드후원_수신닉()),
            'point' => $point_now,
            'hard_donate_offer' => '0',
            'donate_pool' => (string)($결과['donate_pool'] ?? 홀짝_하드후원_모금함_조회()),
            'can_claim_donate_pool' => 홀짝_하드후원_수령가능($두자리닉넴),
            'streak' => $streak,
            'min_bet' => game_min_bet($행2, $point_now),
            'max_bet' => game_max_bet($point_now),
            'win_mult' => $배['win'],
            'lose_mult' => $배['lose'],
            'odds_mode' => $odds_mode,
            'hard_mode_forced' => 홀짝_웹_하드모드_강제($point_now),
        ]);
    }

    // ===== 후원모금함 수령 (민호만) =====
    if ($req_action === 'claim_donate_pool') {
        $결과 = 홀짝_하드후원_모금함_수령($두자리닉넴);
        if (empty($결과['ok'])) {
            game_json([
                'ok' => false,
                'data' => $결과['data'] ?? '❌ 후원모금함 수령에 실패했어요.',
                'donate_pool' => (string)($결과['donate_pool'] ?? 홀짝_하드후원_모금함_조회()),
                'can_claim_donate_pool' => 홀짝_하드후원_수령가능($두자리닉넴),
            ]);
        }
        $point_now = game_point_str($결과['point'] ?? 0);
        $행2 = game_state_row($닉_esc);
        $streak = 홀짝_연승_읽기_및_복구($닉_esc, (int)($행2['streak'] ?? 0));
        $odds_mode = 홀짝_모드_웹읽기($닉_esc, $point_now);
        $배 = game_배수($streak, $odds_mode);
        game_json([
            'ok' => true,
            'type' => 'claim_donate_pool',
            'result' => 'claim_donate',
            'data' => $결과['data'],
            'amount' => (string)($결과['amount'] ?? '0'),
            'amount_display' => (string)($결과['amount_display'] ?? ''),
            'point' => $point_now,
            'donate_pool' => '0',
            'can_claim_donate_pool' => true,
            'streak' => $streak,
            'min_bet' => game_min_bet($행2, $point_now),
            'max_bet' => game_max_bet($point_now),
            'win_mult' => $배['win'],
            'lose_mult' => $배['lose'],
            'odds_mode' => $odds_mode,
            'hard_mode_forced' => 홀짝_웹_하드모드_강제($point_now),
        ]);
    }

    // ===== 페이백 수령 (웹 전용) =====
    if ($req_action === 'claim_payback') {
        $행 = game_state_row($닉_esc);
        if (홀짝_냥($행['pending_bet'] ?? 0) !== '0') {
            game_json(['ok' => false, 'data' => '⏳ 진행 중인 판이 있을 때는 페이백을 받을 수 없어요.']);
        }
        $결과 = 홀짝_웹_페이백_수령($닉_esc, $두자리닉넴);
        if (empty($결과['ok'])) {
            game_json(['ok' => false, 'data' => $결과['data'] ?? '❌ 페이백 수령에 실패했어요.']);
        }
        $streak = 홀짝_연승_읽기_및_복구($닉_esc, (int)($행['streak'] ?? 0));
        $페이백후냥 = game_point_str($결과['point'] ?? 0);
        $odds_mode = 홀짝_모드_웹읽기($닉_esc, $페이백후냥);
        $바닥 = game_min_bet($행, $페이백후냥);
        $배 = game_배수($streak, $odds_mode);
        game_json([
            'ok'           => true,
            'type'         => 'claim_payback',
            'data'         => $결과['data'],
            'amount'       => (string)($결과['amount'] ?? '0'),
            'point'        => $페이백후냥,
            'payback_pool' => '0',
            'streak'       => $streak,
            'min_bet'      => $바닥,
            'max_bet'      => game_max_bet($페이백후냥),
            'win_mult'     => $배['win'],
            'lose_mult'    => $배['lose'],
            'odds_mode'    => $odds_mode,
            'hard_mode_forced' => 홀짝_웹_하드모드_강제($페이백후냥),
        ]);
    }

    // ===== 본방냥 → 게임냥 스왑 (홀짝 웹 전용 · 5% 삭제) =====
    if ($req_action === 'swap_np') {
        include_once __DIR__ . '/swap.inc.php';
        $행 = game_state_row($닉_esc);
        if (홀짝_냥($행['pending_bet'] ?? 0) !== '0') {
            game_json(['ok' => false, 'data' => '⏳ 진행 중인 판이 있을 때는 스왑할 수 없어요.']);
        }
        $결과 = 스왑_홀짝_본방_실행_데이터($두자리닉넴);
        if (empty($결과['ok'])) {
            game_json(['ok' => false, 'data' => $결과['data'] ?? '❌ 스왑에 실패했어요.']);
        }
        $게임냥 = game_point_str($결과['point'] ?? 0);
        $행2 = game_state_row($닉_esc);
        $바닥 = game_min_bet($행2, $게임냥);
        $odds_mode = 홀짝_모드_웹읽기($닉_esc, $게임냥);
        $streak = 홀짝_연승_읽기_및_복구($닉_esc, (int)($행2['streak'] ?? 0));
        $배 = game_배수($streak, $odds_mode);
        game_json([
            'ok'       => true,
            'type'     => 'swap_np',
            'data'     => $결과['data'],
            'point'    => $게임냥,
            'newpoint' => (float)($결과['newpoint'] ?? 0),
            'min_bet'  => $바닥,
            'max_bet'  => game_max_bet($게임냥),
            '지급_pt'  => (int)($결과['지급_pt'] ?? 0),
            'odds_mode' => $odds_mode,
            'win_mult' => $배['win'],
            'lose_mult' => $배['lose'],
            'hard_mode_forced' => 홀짝_웹_하드모드_강제($게임냥),
        ]);
    }

    // ===== 모드 선택 폐지 — 하드 고정 =====
    if ($req_action === 'set_mode') {
        $행 = game_state_row($닉_esc);
        if (홀짝_냥($행['pending_bet'] ?? 0) !== '0') {
            game_json(['ok' => false, 'data' => '⏳ 진행 중인 판이 있을 때는 모드를 바꿀 수 없어요.']);
        }
        홀짝_모드_저장($닉_esc, 'hard');
        game_json([
            'ok' => true,
            'type' => 'set_mode',
            'odds_mode' => 'hard',
            'data' => "✅ 하드모드 고정\n불리한 확률 · 패배 +1 · 연승 승리 10% 수수료(금고5%·로또5%) · 연승 30·20·25·15·10% x2~x4 · 패배 0~8% 2배차감(첫판0·연승2·4·6·8%) · 연승 포기 최고배팅 10%(금고50%·로또50%)",
        ]);
    }

    // ===== 배팅 (.도전 금액) =====
    if ($req_action === 'bet') {
        require_once __DIR__ . '/odd_even_guards.php';
        $가진냥 = game_point_str($회원['point'] ?? 0);
        $배팅 = 홀짝_냥($_REQUEST['amount'] ?? 0);
        if ($배팅 === '0') {
            $배팅 = 홀짝_기본배팅_문자열($가진냥);
        }
        $배팅Str = $배팅;
        if (isset($가진냥[0]) && $가진냥[0] === '-') game_json(['ok' => false, 'data' => '❌ 가진 냥이 마이너스면 도전 불가']);

        if (!홀짝_미션_생타100_달성($두자리닉넴)) {
            game_json([
                'ok' => false,
                'data' => '❌ 오늘 미션 100타 달성자만 홀짝 도전이 가능합니다.',
            ]);
        }
        $신용금지 = 홀짝_신용회복_홀짝_금지문구($두자리닉넴);
        if ($신용금지 !== null) {
            game_json(['ok' => false, 'data' => $신용금지]);
        }
        $게임금지 = 게임제한_홀짝_금지문구($두자리닉넴);
        if ($게임금지 !== null) {
            game_json(['ok' => false, 'data' => $게임금지]);
        }

        $행_pre = game_state_row($닉_esc);
        $행_pre['streak_max_bet'] = 홀짝_연승최고배팅_정리($닉_esc, $가진냥, $행_pre['streak_max_bet'] ?? 0);
        $기본배팅 = 홀짝_기본배팅_문자열($가진냥);
        $바닥배팅 = game_min_bet($행_pre, $가진냥);
        $고정퀵 = function_exists('홀짝_고정퀵배팅인가') && 홀짝_고정퀵배팅인가($배팅);
        if (!$고정퀵 && 홀짝_냥_비교($배팅, $바닥배팅) < 0) {
            if (홀짝_냥_비교($바닥배팅, $기본배팅) > 0) {
                game_json(['ok' => false, 'data' => '❌ 이번 연승 최소 ' . 홀짝_금액표시($바닥배팅, '냥') . ' (최고 배팅 이상만 가능 · 더 높은 금액은 OK)']);
            } else {
                game_json(['ok' => false, 'data' => '❌ 최소 ' . 홀짝_금액표시($기본배팅, '냥')]);
            }
        }
        $최대배팅 = game_max_bet($가진냥);
        if (홀짝_냥_비교($배팅, $최대배팅) > 0) {
            game_json(['ok' => false, 'data' => '❌ 최대 ' . 홀짝_금액표시($최대배팅, '냥')]);
        }

        if (!game_web_is_multi_room()) {
            $sync_bet = game_web_sync_global_room($두자리닉넴, $GAME_타임아웃초);
            $타인잠금 = $sync_bet['room_lock'];
            if ($타인잠금 !== null) {
                $남은분 = max(1, (int)ceil(((int)($타인잠금['left_sec'] ?? 0)) / 60));
                if (($타인잠금['type'] ?? '') === 'pending') {
                    game_json(['ok' => false, 'data' => '⏳ ' . $타인잠금['nick'] . '님 진행 중 (타임아웃까지 약 ' . $남은분 . '분)']);
                }
                game_json(['ok' => false, 'data' => '⏳ ' . $타인잠금['nick'] . '님 연승 중 (타임아웃까지 약 ' . $남은분 . '분)']);
            }
        } else {
            game_web_expire_own_timed_out_states($두자리닉넴, $GAME_타임아웃초);
        }

        $행 = game_state_row($닉_esc);

        // 본인 미완료 판만 검사·타임아웃 정리
        $내pending = 홀짝_냥($행['pending_bet'] ?? 0);
        if ($내pending !== '0') {
            $만료 = strtotime($행['pending_at'] ?? '') + $GAME_타임아웃초;
            if ($만료 >= time()) {
                game_json(['ok' => false, 'data' => '⏳ 진행 중인 판이 있어요. `홀`/`짝` 또는 취소를 먼저 진행해주세요.']);
            }
            홀짝_타임아웃_환급처리($닉_esc, $두자리닉넴, $내pending);
            if (function_exists('홀짝_배팅취소_봉인복구')) {
                홀짝_배팅취소_봉인복구($닉_esc, $행['pending_answer'] ?? 0);
            }
            db_query("UPDATE tb_odd_even_state SET pending_bet = 0, pending_answer = NULL, pending_win_mult = 0, pending_lose_mult = 0, pending_at = NULL WHERE nick = '{$닉_esc}' LIMIT 1");
        }

        $streak = 홀짝_연승_읽기_및_복구($닉_esc, (int)($행['streak'] ?? 0));
        $bet_odds_mode = 홀짝_모드_웹읽기($닉_esc, $가진냥, $행);
        $배 = game_배수($streak, $bet_odds_mode);

        $정답 = function_exists('홀짝_봉인_소진') ? 홀짝_봉인_소진($닉_esc) : 홀짝_닉_정답_선정($닉_esc);

        $누적맥스 = 홀짝_냥($행_pre['streak_max_bet'] ?? ($행['streak_max_bet'] ?? 0));
        if (!홀짝_배팅_저장($닉_esc, $streak, $누적맥스, $배팅, $정답, (int)$배['win'], (int)$배['lose'])) {
            game_json(['ok' => false, 'data' => '❌ 배팅 저장에 실패했어요. 잠시 후 다시 시도해 주세요.']);
        }

        $자숙위반 = 자숙_홀짝위반_적용($두자리닉넴, '냥');
        $자숙위반안내 = (string)($자숙위반['notice'] ?? '');

        $배팅sql = 홀짝_냥_sql($배팅);
        db_query("UPDATE tb_member SET point = point - {$배팅sql} WHERE name = '{$닉_esc}' LIMIT 1");
        지급로그('홀짝도전-배팅', $두자리닉넴, '', 0, $배팅);

        $msg  = $자숙위반안내;
        $msg .= "🎲 " . ($streak <= 0 ? '첫 도전' : "{$streak}연승 도전") . " · " . 홀짝_금액표시($배팅, '냥') . "\n";
        $msg .= "승 ×{$배['win']} → +" . 홀짝_금액표시(홀짝_냥_곱($배팅, (string)(int)$배['win']), '') . "\n";
        $msg .= "패 ×{$배['lose']} → -" . 홀짝_금액표시(홀짝_냥_곱($배팅, (string)(int)$배['lose']), '') . "\n";
        $msg .= "👉 `홀` / `짝` 선택!";

        game_json([
            'ok'             => true,
            'type'           => 'bet',
            'data'           => $msg,
            'bet'            => $배팅,
            'streak'         => $streak,
            'win_mult'       => $배['win'],
            'lose_mult'      => $배['lose'],
            'point'          => 홀짝_냥_차(game_point_str($회원['point'] ?? 0), $배팅),
            'pending_bet'    => $배팅,
            'pending_left'   => $GAME_타임아웃초,
            'payback_pool'   => 홀짝_웹_페이백_조회($닉_esc),
        ]);
    }

    // ===== 배팅+선택 단건 처리 (웹 최적화) =====
    if ($req_action === 'bet_pick') {
        $배팅 = 홀짝_냥($_REQUEST['amount'] ?? 0);
        $pick_raw = isset($_REQUEST['pick']) ? trim($_REQUEST['pick']) : '';
        if ($pick_raw !== '홀' && $pick_raw !== '짝') {
            game_json(['ok' => false, 'data' => '`홀` 또는 `짝`을 선택해주세요.']);
        }
        $가진냥 = game_point_str($회원['point'] ?? 0);
        if ($배팅 === '0') {
            $배팅 = 홀짝_기본배팅_문자열($가진냥);
        }
        $배팅Str = $배팅;
        if (isset($가진냥[0]) && $가진냥[0] === '-') game_json(['ok' => false, 'data' => '❌ 가진 냥이 마이너스면 도전 불가']);
        if (!홀짝_미션_생타100_달성($두자리닉넴)) {
            game_json(['ok' => false, 'data' => '❌ 오늘 미션 100타 달성자만 홀짝 도전이 가능합니다.']);
        }
        $신용금지 = 홀짝_신용회복_홀짝_금지문구($두자리닉넴);
        if ($신용금지 !== null) {
            game_json(['ok' => false, 'data' => $신용금지]);
        }
        $게임금지 = 게임제한_홀짝_금지문구($두자리닉넴);
        if ($게임금지 !== null) {
            game_json(['ok' => false, 'data' => $게임금지]);
        }

        $최대배팅 = game_max_bet($가진냥);
        if (홀짝_냥_비교($배팅, $최대배팅) > 0) {
            game_json(['ok' => false, 'data' => '❌ 최대 ' . 홀짝_금액표시($최대배팅, '냥')]);
        }

        if (!game_web_is_multi_room()) {
            $sync_bet = game_web_sync_global_room($두자리닉넴, $GAME_타임아웃초);
            $타인잠금 = $sync_bet['room_lock'];
            if ($타인잠금 !== null) {
                $남은분 = max(1, (int)ceil(((int)($타인잠금['left_sec'] ?? 0)) / 60));
                if (($타인잠금['type'] ?? '') === 'pending') {
                    game_json(['ok' => false, 'data' => '⏳ ' . $타인잠금['nick'] . '님 진행 중 (타임아웃까지 약 ' . $남은분 . '분)']);
                }
                game_json(['ok' => false, 'data' => '⏳ ' . $타인잠금['nick'] . '님 연승 중 (타임아웃까지 약 ' . $남은분 . '분)']);
            }
        }

        $행 = game_state_row($닉_esc);
        game_web_apply_own_streak_timeout_inline($닉_esc, $두자리닉넴, $행, $GAME_타임아웃초);

        $내pending = 홀짝_냥($행['pending_bet'] ?? 0);
        if ($내pending !== '0') {
            $만료 = strtotime($행['pending_at'] ?? '') + $GAME_타임아웃초;
            if ($만료 >= time()) {
                game_json(['ok' => false, 'data' => '⏳ 진행 중인 판이 있어요. `홀`/`짝` 또는 취소를 먼저 진행해주세요.']);
            }
            홀짝_타임아웃_환급처리($닉_esc, $두자리닉넴, $내pending);
            if (function_exists('홀짝_배팅취소_봉인복구')) {
                홀짝_배팅취소_봉인복구($닉_esc, $행['pending_answer'] ?? 0);
            }
            db_query("UPDATE tb_odd_even_state SET pending_bet = 0, pending_answer = NULL, pending_win_mult = 0, pending_lose_mult = 0, pending_at = NULL WHERE nick = '{$닉_esc}' LIMIT 1");
            $행 = game_state_row($닉_esc);
        }

        $행['streak_max_bet'] = 홀짝_연승최고배팅_정리($닉_esc, $가진냥, $행['streak_max_bet'] ?? 0);
        $기본배팅 = 홀짝_기본배팅_문자열($가진냥);
        $바닥배팅 = game_min_bet($행, $가진냥);
        $고정퀵 = function_exists('홀짝_고정퀵배팅인가') && 홀짝_고정퀵배팅인가($배팅);
        if (!$고정퀵 && 홀짝_냥_비교($배팅, $바닥배팅) < 0) {
            if (홀짝_냥_비교($바닥배팅, $기본배팅) > 0) {
                game_json(['ok' => false, 'data' => '❌ 이번 연승 최소 ' . 홀짝_금액표시($바닥배팅, '냥') . ' (최고 배팅 이상만 가능 · 더 높은 금액은 OK)']);
            } else {
                game_json(['ok' => false, 'data' => '❌ 최소 ' . 홀짝_금액표시($기본배팅, '냥')]);
            }
        }

        $streak전 = 홀짝_연승_읽기_및_복구($닉_esc, (int)($행['streak'] ?? 0));
        $odds_mode = 홀짝_모드_웹읽기($닉_esc, $가진냥, $행);
        $배 = game_배수($streak전, $odds_mode);
        $유저픽 = ($pick_raw === '홀') ? 1 : 2;
        // 사전 봉인 소진 → 정산은 pending_answer 사용
        $정답 = function_exists('홀짝_봉인_소진') ? 홀짝_봉인_소진($닉_esc) : 홀짝_정답_선정($odds_mode, null, 0);

        $자숙위반 = 자숙_홀짝위반_적용($두자리닉넴, '냥');
        $자숙위반안내 = (string)($자숙위반['notice'] ?? '');

        $판 = [
            'streak' => $streak전,
            'streak_max_bet' => 홀짝_냥($행['streak_max_bet'] ?? 0),
            'pending_bet' => $배팅,
            'pending_answer' => $정답,
            'pending_win_mult' => (int)$배['win'],
            'pending_lose_mult' => (int)$배['lose'],
        ];
        $정산 = 홀짝_도전_픽정산($판, $유저픽, [
            '닉_esc' => $닉_esc,
            '두자리닉넴' => $두자리닉넴,
            '단위' => '냥',
            '연승최대' => $GAME_연승최대,
            'single_room_reset_others' => !game_web_is_multi_room(),
            'odds_mode' => $odds_mode,
            'state_row' => $행,
            'streak_before' => $streak전,
            'merge_bet_deduct' => true,
            'web_payback' => true,
        ]);
        if (empty($정산['ok'])) {
            game_json($정산);
        }
        game_web_hard_donate_on_complete($정산, $닉_esc);
        if ($자숙위반안내 !== '') {
            $정산['data'] = $자숙위반안내 . (string)($정산['data'] ?? '');
        }
        $streak후 = (int)($정산['streak_after'] ?? 0);
        $streak_max_after = array_key_exists('streak_max_bet', $정산)
            ? 홀짝_냥($정산['streak_max_bet'])
            : null;
        $streak_won = array_key_exists('streak_won', $정산) ? (int)$정산['streak_won'] : null;
        $pt_row = @db_select("SELECT CAST(point AS CHAR) AS point FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
        $point_now = is_array($pt_row) ? game_point_str($pt_row['point'] ?? 0) : game_point_str($회원['point'] ?? 0);
        game_json(array_merge([
            'ok' => true, 'type' => 'pick',
        ], $정산, game_web_outcome_state_fields($닉_esc, $req_code, $streak후, $streak_max_after, $streak_won, [
            'skip_streak_picks' => true,
            'point' => $point_now,
        ])));
    }

    // ===== 홀/짝 (.pick) =====
    if ($req_action === 'pick') {
        $pick_raw = isset($_REQUEST['pick']) ? trim($_REQUEST['pick']) : '';
        if ($pick_raw !== '홀' && $pick_raw !== '짝') {
            game_json(['ok' => false, 'data' => '`홀` 또는 `짝`을 선택해주세요.']);
        }

        $판 = game_state_row($닉_esc);
        if (empty($판) || 홀짝_냥($판['pending_bet'] ?? 0) === '0') {
            game_json(['ok' => false, 'data' => '❌ 진행 중인 배팅이 없어요. 먼저 배팅을 걸어주세요.']);
        }

        require_once __DIR__ . '/odd_even_guards.php';
        $게임금지 = 게임제한_홀짝_금지문구($두자리닉넴);
        if ($게임금지 !== null) {
            game_json(['ok' => false, 'data' => $게임금지]);
        }
        $신용취소 = 홀짝_신용회복_pending이면_취소환급($두자리닉넴, $판, '냥');
        if ($신용취소 !== null) {
            $pt_row = @db_select("SELECT CAST(point AS CHAR) AS point FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
            game_json([
                'ok'     => true,
                'type'   => 'cancel',
                'result' => 'refund',
                'data'   => $신용취소['msg'],
                'refund' => 홀짝_냥($신용취소['refund']),
                'gumgo'  => 홀짝_냥($신용취소['gumgo']),
                'lotto'  => 홀짝_냥($신용취소['lotto'] ?? 0),
                'tax'    => 홀짝_냥($신용취소['tax'] ?? 0),
                'point'  => game_point_str($pt_row['point'] ?? 0),
            ]);
        }

        $만료 = strtotime($판['pending_at'] ?? '') + $GAME_타임아웃초;
        if ($만료 < time()) {
            $타임원 = 홀짝_냥($판['pending_bet'] ?? 0);
            $타임모드 = 홀짝_모드_읽기($닉_esc, $판);
            $타임환급 = '0';
            $타임분배 = ['금고' => '0', '로또' => '0'];
            if ($타임원 !== '0') {
                $타임 = 홀짝_타임아웃_환급처리($닉_esc, $두자리닉넴, $타임원, $타임모드);
                $타임환급 = $타임['환급'];
                $타임분배 = ['금고' => $타임['금고'], '로또' => $타임['로또']];
            }
            if (function_exists('홀짝_배팅취소_봉인복구')) {
              홀짝_배팅취소_봉인복구($닉_esc, $판['pending_answer'] ?? 0);
            }
            db_query("UPDATE tb_odd_even_state SET pending_bet = 0, pending_answer = NULL, pending_win_mult = 0, pending_lose_mult = 0, pending_at = NULL WHERE nick = '{$닉_esc}' LIMIT 1");
            game_json([
                'ok'     => true,
                'type'   => 'timeout',
                'result' => 'timeout',
                'data'   => "⏰ 타임아웃 · " . 홀짝_타임아웃_환급문구(['환급' => $타임환급, '금고' => $타임분배['금고'], '로또' => $타임분배['로또']], $타임모드, '냥'),
            ]);
        }

        $유저픽 = ($pick_raw === '홀') ? 1 : 2;
        $정산 = 홀짝_도전_픽정산($판, $유저픽, [
            '닉_esc' => $닉_esc,
            '두자리닉넴' => $두자리닉넴,
            '단위' => '냥',
            '연승최대' => $GAME_연승최대,
            'single_room_reset_others' => !game_web_is_multi_room(),
            'odds_mode' => 홀짝_모드_읽기($닉_esc),
            'web_payback' => true,
        ]);
        if (empty($정산['ok'])) {
            game_json($정산);
        }
        game_web_hard_donate_on_complete($정산, $닉_esc);
        $streak후 = (int)($정산['streak_after'] ?? 0);
        $streak_max_after = array_key_exists('streak_max_bet', $정산)
            ? 홀짝_냥($정산['streak_max_bet'])
            : null;
        $streak_won = array_key_exists('streak_won', $정산) ? (int)$정산['streak_won'] : null;
        game_json(array_merge([
            'ok' => true, 'type' => 'pick',
        ], $정산, game_web_outcome_state_fields($닉_esc, $req_code, $streak후, $streak_max_after, $streak_won)));
    }

    // ===== 취소 (.도전 취소) =====
    if ($req_action === 'cancel') {
        require_once __DIR__ . '/odd_even_guards.php';
        $기존 = game_state_row($닉_esc);
        $환불 = 홀짝_냥($기존['pending_bet'] ?? 0);
        $연승 = (int)($기존['streak'] ?? 0);

        if ($기존 && $환불 !== '0') {
            $모드 = 홀짝_모드_읽기($닉_esc, $기존);
            $환급결과 = 홀짝_취소무승부_환급처리($닉_esc, $두자리닉넴, $환불, $모드);
            if (function_exists('홀짝_배팅취소_봉인복구')) {
              홀짝_배팅취소_봉인복구($닉_esc, $기존['pending_answer'] ?? 0);
            }
            db_query("UPDATE tb_odd_even_state SET pending_bet = 0, pending_answer = NULL, pending_win_mult = 0, pending_lose_mult = 0, pending_at = NULL WHERE nick = '{$닉_esc}' LIMIT 1");
            지급로그('홀짝도전-취소환급', $두자리닉넴, '', $환급결과['수수료'], $환급결과['환급']);
            game_json([
                'ok' => true, 'type' => 'cancel', 'result' => 'refund',
                'data' => "✅ 취소 · " . 홀짝_취소무승부_환급문구($환급결과, $모드, '냥'),
                'refund' => $환급결과['환급'], 'gumgo' => $환급결과['수수료'], 'lotto' => $환급결과['로또'], 'tax' => $환급결과['금고'],
            ]);
        } elseif ($기존 && 홀짝_연승포기_가능($연승, 홀짝_연승포기_오퍼_조회($닉_esc))) {
            $맥스배 = 홀짝_냥($기존['streak_max_bet'] ?? 0);
            db_query("UPDATE tb_odd_even_state SET streak = 0, streak_max_bet = 0, giveup_offer = 0 WHERE nick = '{$닉_esc}' LIMIT 1");
            홀짝_하드후원_오퍼_클리어($닉_esc);
            $포기 = 홀짝_연승포기_수수료처리($닉_esc, $두자리닉넴, $맥스배, 홀짝_모드_읽기($닉_esc, $기존));
            if ($맥스배 !== '0') {
                $포기문구 = 홀짝_연승포기_수수료문구($포기, '냥');
                game_json([
                    'ok' => true, 'type' => 'cancel', 'result' => 'give_up',
                    'data' => "✅ 연승 포기 ({$연승}→0) · " . ($포기문구 !== '' ? $포기문구 : '수수료 없음'),
                    'gumgo' => $포기['금고'], 'lotto' => $포기['로또'], 'tax' => $포기['금고'],
                    'hard_donate_offer' => '0',
                    'giveup_offer' => 0,
                ]);
            }
            game_json([
                'ok' => true, 'type' => 'cancel', 'result' => 'give_up',
                'data' => "✅ 연승 포기 ({$연승}→0)", 'gumgo' => 0,
                'hard_donate_offer' => '0',
                'giveup_offer' => 0,
            ]);
        } elseif ($기존 && $연승 >= 2) {
            game_json(['ok' => false, 'data' => '❌ 연승 포기 처리에 실패했어요. 잠시 후 다시 시도해 주세요.']);
        } elseif ($기존 && $연승 >= 1) {
            game_json(['ok' => false, 'data' => '❌ 연승 포기는 2연승부터 가능해요.']);
        } else {
            game_json(['ok' => false, 'data' => '❌ 취소할 판·연승이 없어요.']);
        }
    }
}

// ===================== 페이지 출력 =====================
$game_code = isset($_GET['code']) ? trim($_GET['code']) : '';
$game_name = '';
$game_point = 0;
$game_newpoint = 0;
$game_streak = 0;
$game_streak_max_bet = '0';
$game_pending_bet = '0';
$game_pending_left = 0;
$game_win_mult = 2;
$game_lose_mult = 1;
$game_min_bet = '1000000';
$game_max_bet = (string)$GAME_최대배팅;
$game_room_lock = null;
$game_need_code = false;
$game_streak_picks = [];
$game_odds_mode = 'hard';
$game_payback_pool = 0;
$game_hard_donate_offer = '0';
$game_donate_pool = '0';
$game_can_claim_donate_pool = false;
$game_giveup_offer = 0;
$game_hard_mode_forced = false;
$game_profile_num = 1;
$game_profile_hex = '#FAE100';
$game_weapon_item = '';
$game_weapon_enhance = 0;
$game_quick_pcts = [];
$game_can_allin = false;
$game_fixed_bet = '';
$두자리닉넴_page = '';

if ($game_code === '') {
    $game_need_code = true;
} else {
    include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
    include_once $_SERVER['DOCUMENT_ROOT'] . '/api/function.php';
    $m = game_auth($game_code);
    if (!$m) {
        $game_need_code = true;
    } else {
        $game_name = trim($m['name']);
        $game_point = game_point_str($m['point'] ?? 0);
        $game_profile_num = max(1, (int)($m['num'] ?? 1));
        $game_profile_hex = game_profile_hex($game_profile_num);
        $np_row = @db_select("SELECT newpoint FROM tb_member WHERE code = '" . addslashes($game_code) . "' LIMIT 1");
        $game_newpoint = is_array($np_row) ? round((float)($np_row['newpoint'] ?? 0), 1) : 0;
        $두자리닉넴_page = getTwoCharNick($game_name);
        $퀵정보 = function_exists('홀짝_회원_무기퀵정보')
          ? 홀짝_회원_무기퀵정보($game_code, true)
          : ['item' => '', 'enhance' => 0, 'quick_pcts' => [1.0], 'can_allin' => false, 'fixed_bet' => ''];
        $game_weapon_item = (string)($퀵정보['item'] ?? '');
        $game_weapon_enhance = (int)($퀵정보['enhance'] ?? 0);
        $game_quick_pcts = is_array($퀵정보['quick_pcts'] ?? null) ? $퀵정보['quick_pcts'] : [];
        if ($game_quick_pcts === []) {
            $game_quick_pcts = [1.0];
        }
        $game_can_allin = !empty($퀵정보['can_allin']);
        $game_fixed_bet = '';
        if ($두자리닉넴_page !== '') {
            $닉_esc_page = addslashes($두자리닉넴_page);
            $sync_page = game_web_sync_global_room($두자리닉넴_page, $GAME_타임아웃초);
            $game_room_lock = $sync_page['room_lock'];
            $행 = game_state_row($닉_esc_page);
            $game_streak = 홀짝_연승_읽기_및_복구($닉_esc_page, (int)($행['streak'] ?? 0));
            $game_pending_bet = 홀짝_냥($행['pending_bet'] ?? 0);
            if ($game_pending_bet !== '0' && !empty($행['pending_at'])) {
                $game_pending_left = max(0, (strtotime($행['pending_at']) + $GAME_타임아웃초) - time());
            }
            $game_odds_mode = 홀짝_모드_웹읽기($닉_esc_page, $game_point);
            $배_page = game_배수($game_streak, $game_odds_mode);
            $game_win_mult = $배_page['win'];
            $game_lose_mult = $배_page['lose'];
            $game_streak_picks = game_streak_pick_labels($닉_esc_page, $game_streak);
            $pt_page = @db_select("SELECT CAST(point AS CHAR) AS point FROM tb_member WHERE code = '" . addslashes($game_code) . "' LIMIT 1");
            if (is_array($pt_page) && array_key_exists('point', $pt_page)) {
                $game_point = game_point_str($pt_page['point']);
            }
            $game_streak_max_bet = 홀짝_연승최고배팅_정리($닉_esc_page, $game_point, $행['streak_max_bet'] ?? 0);
            $행['streak_max_bet'] = $game_streak_max_bet;
            $game_min_bet = game_min_bet($행, $game_point);
            $game_max_bet = game_max_bet($game_point);
            $game_hard_mode_forced = 홀짝_웹_하드모드_강제($game_point);
            $game_payback_pool = 홀짝_웹_페이백_조회($닉_esc_page);
            $game_hard_donate_offer = function_exists('홀짝_하드후원_오퍼_표시용')
                ? 홀짝_하드후원_오퍼_표시용($닉_esc_page, $game_streak)
                : 홀짝_하드후원_오퍼_조회($닉_esc_page);
            $game_donate_pool = 홀짝_하드후원_모금함_조회();
            $game_can_claim_donate_pool = 홀짝_하드후원_수령가능($두자리닉넴_page);
            $game_giveup_offer = 홀짝_연승포기_오퍼_연승동기화($닉_esc_page, $game_streak);
        }
    }
}

/* 배팅 입력 기본값: 자산 구간별 기본·연승 시 streak_max 적용 — 배팅 카드 노출 구간만 */
$game_default_bet_display = (!$game_need_code && !$game_room_lock && 홀짝_냥($game_pending_bet) === '0')
    ? game_금액_축약_짧게($game_min_bet)
    : '';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#120808">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>홀짝 도전 · 웹</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Black+Han+Sans&family=Noto+Sans+KR:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0f172a;
            --card: #1e293b;
            --accent: #f472b6;
            --gold: #fbbf24;
            --blue: #38bdf8;
            --green: #4ade80;
            --red: #f87171;
            --purple: #a78bfa;
            --text: #e2e8f0;
            --muted: #94a3b8;
            --title-a: #fbbf24;
            --title-b: #f472b6;
            --wrap-glow: rgba(0,0,0,0.45);
            --wrap-ring: rgba(255,255,255,0.06);
            --card-bg: rgba(0,0,0,0.25);
            --card-border: rgba(255,255,255,0.08);
        }
        body.odd-even-play {
            transition: background-color 0.45s ease, background-image 0.45s ease, color 0.35s ease;
        }
        /* 스킨 테마: black / white / red / pink(공주) / gold(왕자) / blue / me(내 프로필색) */
        body.theme-black.odd-even-play {
            --bg: #050505;
            --card: #121212;
            --accent: #d4d4d8;
            --gold: #e4e4e7;
            --blue: #a1a1aa;
            --green: #d4d4d8;
            --red: #a1a1aa;
            --purple: #a1a1aa;
            --text: #f4f4f5;
            --muted: #a1a1aa;
            --title-a: #fafafa;
            --title-b: #a1a1aa;
            --wrap-glow: rgba(0,0,0,0.7);
            --wrap-ring: rgba(255,255,255,0.1);
            --card-bg: rgba(18,18,18,0.72);
            --card-border: rgba(255,255,255,0.1);
            background-image:
                radial-gradient(ellipse at 50% 0%, rgba(255,255,255,0.06) 0%, transparent 52%),
                radial-gradient(ellipse at 85% 85%, rgba(255,255,255,0.04) 0%, transparent 46%),
                radial-gradient(ellipse at 8% 72%, rgba(255,255,255,0.03) 0%, transparent 42%);
        }
        body.theme-red.odd-even-play {
            --bg: #120808;
            --card: #1f0f0f;
            --accent: #fb923c;
            --gold: #fdba74;
            --blue: #f87171;
            --green: #fca5a5;
            --red: #ef4444;
            --text: #e2e8f0;
            --muted: #94a3b8;
            --title-a: #fdba74;
            --title-b: #ef4444;
            --wrap-glow: rgba(239,68,68,0.35);
            --wrap-ring: rgba(251,146,60,0.28);
            --card-bg: rgba(40,8,8,0.48);
            --card-border: rgba(248,113,113,0.18);
            background-image:
                radial-gradient(ellipse at 50% -10%, rgba(239,68,68,0.28) 0%, transparent 55%),
                radial-gradient(ellipse at 92% 88%, rgba(251,146,60,0.18) 0%, transparent 48%),
                radial-gradient(ellipse at 6% 62%, rgba(127,29,29,0.35) 0%, transparent 44%);
        }
        body.theme-white.odd-even-play {
            --bg: #f4f6fb;
            --card: #ffffff;
            --accent: #be123c;
            --gold: #92400e;
            --blue: #0369a1;
            --green: #047857;
            --red: #b91c1c;
            --purple: #6d28d9;
            --text: #0f172a;
            --muted: #475569;
            --title-a: #0f172a;
            --title-b: #be123c;
            --wrap-glow: rgba(15,23,42,0.12);
            --wrap-ring: rgba(15,23,42,0.08);
            --card-bg: rgba(248,250,252,0.92);
            --card-border: rgba(15,23,42,0.12);
            background-image:
                radial-gradient(ellipse at 50% 0%, rgba(225,29,72,0.08) 0%, transparent 52%),
                radial-gradient(ellipse at 90% 80%, rgba(2,132,199,0.08) 0%, transparent 46%),
                radial-gradient(ellipse at 8% 70%, rgba(180,83,9,0.06) 0%, transparent 42%);
        }
        body.theme-pink.odd-even-play {
            --bg: #2a1020;
            --card: #3b1528;
            --accent: #f9a8d4;
            --gold: #fbcfe8;
            --blue: #fda4af;
            --green: #f9a8d4;
            --red: #fb7185;
            --purple: #f0abfc;
            --text: #fff1f5;
            --muted: #f9a8d4;
            --title-a: #fce7f3;
            --title-b: #f472b6;
            --wrap-glow: rgba(244,114,182,0.35);
            --wrap-ring: rgba(249,168,212,0.28);
            --card-bg: rgba(60,20,40,0.55);
            --card-border: rgba(249,168,212,0.22);
            background-image:
                radial-gradient(ellipse at 50% -10%, rgba(244,114,182,0.35) 0%, transparent 55%),
                radial-gradient(ellipse at 90% 85%, rgba(232,121,249,0.2) 0%, transparent 48%),
                radial-gradient(ellipse at 8% 70%, rgba(251,113,133,0.18) 0%, transparent 42%);
        }
        body.theme-gold.odd-even-play {
            --bg: #1a1408;
            --card: #2a2110;
            --accent: #fbbf24;
            --gold: #fde68a;
            --blue: #fcd34d;
            --green: #fef08a;
            --red: #f59e0b;
            --purple: #fcd34d;
            --text: #fffbeb;
            --muted: #fde68a;
            --title-a: #fef3c7;
            --title-b: #f59e0b;
            --wrap-glow: rgba(245,158,11,0.35);
            --wrap-ring: rgba(251,191,36,0.3);
            --card-bg: rgba(50,35,10,0.55);
            --card-border: rgba(251,191,36,0.24);
            background-image:
                radial-gradient(ellipse at 50% -10%, rgba(251,191,36,0.32) 0%, transparent 55%),
                radial-gradient(ellipse at 90% 85%, rgba(245,158,11,0.2) 0%, transparent 48%),
                radial-gradient(ellipse at 8% 70%, rgba(180,83,9,0.25) 0%, transparent 42%);
        }
        body.theme-blue.odd-even-play {
            --bg: #071525;
            --card: #0e2438;
            --accent: #38bdf8;
            --gold: #7dd3fc;
            --blue: #0ea5e9;
            --green: #67e8f9;
            --red: #60a5fa;
            --purple: #818cf8;
            --text: #e0f2fe;
            --muted: #7dd3fc;
            --title-a: #bae6fd;
            --title-b: #38bdf8;
            --wrap-glow: rgba(14,165,233,0.35);
            --wrap-ring: rgba(56,189,248,0.28);
            --card-bg: rgba(10,35,55,0.55);
            --card-border: rgba(56,189,248,0.22);
            background-image:
                radial-gradient(ellipse at 50% -10%, rgba(56,189,248,0.28) 0%, transparent 55%),
                radial-gradient(ellipse at 90% 85%, rgba(99,102,241,0.18) 0%, transparent 48%),
                radial-gradient(ellipse at 8% 70%, rgba(14,165,233,0.16) 0%, transparent 42%);
        }
        /* 내 프로필색 — JS가 --me-* 변수를 채움 */
        body.theme-me.odd-even-play {
            --bg: var(--me-bg, #1a1010);
            --card: var(--me-card, #2a1818);
            --accent: var(--me-accent, #fde68a);
            --gold: var(--me-gold, #fde68a);
            --blue: var(--me-accent, #fde68a);
            --green: var(--me-accent, #fde68a);
            --red: var(--me-accent, #fca5a5);
            --purple: var(--me-accent, #e9d5ff);
            --text: var(--me-text, #fff7ed);
            --muted: var(--me-muted, #e7e5e4);
            --title-a: var(--me-text, #fff7ed);
            --title-b: var(--me-accent, #fde68a);
            --wrap-glow: var(--me-glow, rgba(0,0,0,0.45));
            --wrap-ring: var(--me-ring, rgba(255,255,255,0.18));
            --card-bg: var(--me-card-bg, rgba(0,0,0,0.35));
            --card-border: var(--me-ring, rgba(255,255,255,0.18));
            background-image:
                radial-gradient(ellipse at 50% -10%, var(--me-spot1, rgba(255,255,255,0.12)) 0%, transparent 55%),
                radial-gradient(ellipse at 90% 85%, var(--me-spot2, rgba(255,255,255,0.08)) 0%, transparent 48%),
                radial-gradient(ellipse at 8% 70%, var(--me-spot1, rgba(255,255,255,0.06)) 0%, transparent 42%);
        }
        body.theme-me.odd-even-play .wrap {
            box-shadow: 0 20px 60px var(--wrap-glow), 0 0 0 1px var(--wrap-ring), 0 0 36px var(--me-glow, rgba(0,0,0,0.25));
        }
        body.theme-me.odd-even-play .card {
            background: var(--card-bg);
            border-color: var(--card-border);
        }
        body.theme-me.odd-even-play .btn-pick.hol {
            background: linear-gradient(145deg, var(--me-btn1, #b91c1c), var(--me-btn1-d, #7f1d1d));
            box-shadow: 0 8px 0 var(--me-btn1-d, #7f1d1d), 0 10px 24px var(--me-glow, rgba(0,0,0,0.4));
        }
        body.theme-me.odd-even-play .btn-pick.jjak {
            background: linear-gradient(145deg, var(--me-btn2, #c2410c), var(--me-btn2-d, #9a3412));
            box-shadow: 0 8px 0 var(--me-btn2-d, #9a3412), 0 10px 24px var(--me-glow, rgba(0,0,0,0.35));
        }
        body.theme-me.odd-even-play .pending-box {
            background: linear-gradient(135deg, var(--me-card-bg, rgba(0,0,0,0.35)), rgba(0,0,0,0.2));
            border-color: var(--me-ring, rgba(255,255,255,0.2));
        }
        body.theme-me.odd-even-play .pending-box .timer,
        body.theme-me.odd-even-play .pending-box .bet-info .mult { color: var(--me-accent); }
        body.theme-me.odd-even-play .badge.pink {
            background: var(--me-card-bg, rgba(255,255,255,0.12));
            color: var(--me-accent);
        }
        body.theme-me.odd-even-play .bet-input-row input:focus { border-color: var(--me-accent); }
        body.theme-me.odd-even-play .streak-trail .st-pick.hol-t,
        body.theme-me.odd-even-play .streak-trail .st-pick.jjak-t {
            color: var(--me-accent);
            text-shadow: 0 0 14px var(--me-glow, rgba(255,255,255,0.25));
        }
        body.theme-red.odd-even-play .wrap {
            box-shadow: 0 20px 60px var(--wrap-glow), 0 0 0 1px var(--wrap-ring), 0 0 40px rgba(239,68,68,0.12);
        }
        body.theme-pink.odd-even-play .wrap {
            box-shadow: 0 20px 60px var(--wrap-glow), 0 0 0 1px var(--wrap-ring), 0 0 40px rgba(244,114,182,0.14);
        }
        body.theme-gold.odd-even-play .wrap {
            box-shadow: 0 20px 60px var(--wrap-glow), 0 0 0 1px var(--wrap-ring), 0 0 40px rgba(251,191,36,0.14);
        }
        body.theme-blue.odd-even-play .wrap {
            box-shadow: 0 20px 60px var(--wrap-glow), 0 0 0 1px var(--wrap-ring), 0 0 40px rgba(56,189,248,0.14);
        }
        body.theme-black.odd-even-play .wrap,
        body.theme-white.odd-even-play .wrap {
            box-shadow: 0 20px 60px var(--wrap-glow), 0 0 0 1px var(--wrap-ring);
        }
        body.theme-red.odd-even-play .card,
        body.theme-black.odd-even-play .card,
        body.theme-white.odd-even-play .card,
        body.theme-pink.odd-even-play .card,
        body.theme-gold.odd-even-play .card,
        body.theme-blue.odd-even-play .card {
            background: var(--card-bg);
            border-color: var(--card-border);
        }
        body.theme-red.odd-even-play .pending-box {
            background: linear-gradient(135deg, rgba(239,68,68,0.16), rgba(251,146,60,0.1));
            border-color: rgba(248,113,113,0.42);
            box-shadow: inset 0 0 24px rgba(239,68,68,0.08);
        }
        body.theme-black.odd-even-play .pending-box {
            background: linear-gradient(135deg, rgba(255,255,255,0.08), rgba(255,255,255,0.03));
            border-color: rgba(255,255,255,0.18);
        }
        body.theme-white.odd-even-play .pending-box {
            background: linear-gradient(135deg, rgba(225,29,72,0.08), rgba(2,132,199,0.06));
            border-color: rgba(225,29,72,0.22);
        }
        body.theme-pink.odd-even-play .pending-box {
            background: linear-gradient(135deg, rgba(244,114,182,0.2), rgba(232,121,249,0.12));
            border-color: rgba(249,168,212,0.4);
        }
        body.theme-gold.odd-even-play .pending-box {
            background: linear-gradient(135deg, rgba(251,191,36,0.2), rgba(245,158,11,0.12));
            border-color: rgba(251,191,36,0.42);
        }
        body.theme-blue.odd-even-play .pending-box {
            background: linear-gradient(135deg, rgba(56,189,248,0.18), rgba(99,102,241,0.12));
            border-color: rgba(56,189,248,0.4);
        }
        body.theme-red.odd-even-play .pending-box .timer { color: #fdba74; }
        body.theme-red.odd-even-play .pending-box .bet-info .mult { color: #fb923c; }
        body.theme-black.odd-even-play .pending-box .timer { color: #e4e4e7; }
        body.theme-black.odd-even-play .pending-box .bet-info .mult { color: #d4d4d8; }
        body.theme-white.odd-even-play .pending-box .timer { color: #b45309; }
        body.theme-pink.odd-even-play .pending-box .timer { color: #fbcfe8; }
        body.theme-pink.odd-even-play .pending-box .bet-info .mult { color: #f9a8d4; }
        body.theme-gold.odd-even-play .pending-box .timer { color: #fde68a; }
        body.theme-gold.odd-even-play .pending-box .bet-info .mult { color: #fbbf24; }
        body.theme-blue.odd-even-play .pending-box .timer { color: #7dd3fc; }
        body.theme-blue.odd-even-play .pending-box .bet-info .mult { color: #38bdf8; }
        body.theme-red.odd-even-play .btn-pick.hol {
            background: linear-gradient(145deg, #ef4444, #991b1b);
            box-shadow: 0 8px 0 #7f1d1d, 0 10px 24px rgba(239,68,68,0.5);
        }
        body.theme-red.odd-even-play .btn-pick.jjak {
            background: linear-gradient(145deg, #ea580c, #9a3412);
            box-shadow: 0 8px 0 #7c2d12, 0 10px 24px rgba(251,146,60,0.45);
        }
        body.theme-black.odd-even-play .btn-pick.hol {
            background: linear-gradient(145deg, #3f3f46, #18181b);
            box-shadow: 0 8px 0 #09090b, 0 10px 24px rgba(0,0,0,0.55);
        }
        body.theme-black.odd-even-play .btn-pick.jjak {
            background: linear-gradient(145deg, #52525b, #27272a);
            box-shadow: 0 8px 0 #09090b, 0 10px 24px rgba(0,0,0,0.5);
        }
        body.theme-white.odd-even-play .btn-pick.hol {
            background: linear-gradient(145deg, #e11d48, #9f1239);
            box-shadow: 0 8px 0 #881337, 0 10px 24px rgba(225,29,72,0.28);
            color: #fff;
        }
        body.theme-white.odd-even-play .btn-pick.jjak {
            background: linear-gradient(145deg, #0284c7, #075985);
            box-shadow: 0 8px 0 #0c4a6e, 0 10px 24px rgba(2,132,199,0.28);
            color: #fff;
        }
        body.theme-pink.odd-even-play .btn-pick.hol {
            background: linear-gradient(145deg, #f472b6, #be185d);
            box-shadow: 0 8px 0 #9d174d, 0 10px 24px rgba(244,114,182,0.5);
        }
        body.theme-pink.odd-even-play .btn-pick.jjak {
            background: linear-gradient(145deg, #e879f9, #a21caf);
            box-shadow: 0 8px 0 #86198f, 0 10px 24px rgba(232,121,249,0.45);
        }
        body.theme-gold.odd-even-play .btn-pick.hol {
            background: linear-gradient(145deg, #f59e0b, #b45309);
            box-shadow: 0 8px 0 #92400e, 0 10px 24px rgba(245,158,11,0.5);
        }
        body.theme-gold.odd-even-play .btn-pick.jjak {
            background: linear-gradient(145deg, #eab308, #a16207);
            box-shadow: 0 8px 0 #854d0e, 0 10px 24px rgba(234,179,8,0.45);
        }
        body.theme-blue.odd-even-play .btn-pick.hol {
            background: linear-gradient(145deg, #0ea5e9, #0369a1);
            box-shadow: 0 8px 0 #075985, 0 10px 24px rgba(14,165,233,0.5);
        }
        body.theme-blue.odd-even-play .btn-pick.jjak {
            background: linear-gradient(145deg, #6366f1, #3730a3);
            box-shadow: 0 8px 0 #312e81, 0 10px 24px rgba(99,102,241,0.45);
        }
        body.theme-red.odd-even-play .btn-pick.hol:active:not(:disabled),
        body.theme-red.odd-even-play .btn-pick.hol.punch {
            box-shadow: 0 2px 0 #7f1d1d, 0 4px 16px rgba(239,68,68,0.6), 0 0 32px rgba(239,68,68,0.35);
        }
        body.theme-red.odd-even-play .btn-pick.jjak:active:not(:disabled),
        body.theme-red.odd-even-play .btn-pick.jjak.punch {
            box-shadow: 0 2px 0 #7c2d12, 0 4px 16px rgba(251,146,60,0.55), 0 0 28px rgba(251,146,60,0.32);
        }
        body.theme-red.odd-even-play .payback-card {
            border-color: rgba(251,146,60,0.22);
            background: linear-gradient(135deg, rgba(69,10,10,0.55), rgba(40,8,8,0.35));
        }
        body.theme-black.odd-even-play .payback-card {
            border-color: rgba(255,255,255,0.12);
            background: linear-gradient(135deg, rgba(24,24,27,0.85), rgba(9,9,11,0.55));
        }
        body.theme-white.odd-even-play .payback-card {
            border-color: rgba(225,29,72,0.16);
            background: linear-gradient(135deg, rgba(255,241,242,0.95), rgba(248,250,252,0.9));
        }
        body.theme-pink.odd-even-play .payback-card {
            border-color: rgba(249,168,212,0.28);
            background: linear-gradient(135deg, rgba(80,20,50,0.6), rgba(50,15,35,0.4));
        }
        body.theme-gold.odd-even-play .payback-card {
            border-color: rgba(251,191,36,0.28);
            background: linear-gradient(135deg, rgba(70,45,10,0.6), rgba(40,30,8,0.4));
        }
        body.theme-blue.odd-even-play .payback-card {
            border-color: rgba(56,189,248,0.28);
            background: linear-gradient(135deg, rgba(10,40,65,0.65), rgba(8,25,45,0.4));
        }
        body.theme-red.odd-even-play .btn-payback {
            background: linear-gradient(135deg, rgba(239,68,68,0.28), rgba(251,146,60,0.2));
            border-color: rgba(251,146,60,0.5);
            color: #fed7aa;
        }
        body.theme-black.odd-even-play .btn-payback {
            background: linear-gradient(135deg, rgba(255,255,255,0.1), rgba(255,255,255,0.04));
            border-color: rgba(255,255,255,0.28);
            color: #f4f4f5;
        }
        body.theme-white.odd-even-play .btn-payback {
            background: linear-gradient(135deg, rgba(225,29,72,0.12), rgba(2,132,199,0.1));
            border-color: rgba(225,29,72,0.35);
            color: #9f1239;
        }
        body.theme-pink.odd-even-play .btn-payback {
            background: linear-gradient(135deg, rgba(244,114,182,0.3), rgba(232,121,249,0.2));
            border-color: rgba(249,168,212,0.5);
            color: #fce7f3;
        }
        body.theme-gold.odd-even-play .btn-payback {
            background: linear-gradient(135deg, rgba(251,191,36,0.3), rgba(245,158,11,0.2));
            border-color: rgba(251,191,36,0.5);
            color: #fef3c7;
        }
        body.theme-blue.odd-even-play .btn-payback {
            background: linear-gradient(135deg, rgba(14,165,233,0.3), rgba(99,102,241,0.2));
            border-color: rgba(56,189,248,0.5);
            color: #e0f2fe;
        }
        body.theme-red.odd-even-play .lock-banner {
            background: rgba(69,10,10,0.85);
            border-color: rgba(248,113,113,0.35);
            color: #fecaca;
        }
        body.theme-black.odd-even-play .lock-banner {
            background: rgba(9,9,11,0.92);
            border-color: rgba(255,255,255,0.18);
            color: #e4e4e7;
        }
        body.theme-white.odd-even-play .lock-banner {
            background: rgba(255,241,242,0.95);
            border-color: rgba(225,29,72,0.25);
            color: #9f1239;
        }
        body.theme-pink.odd-even-play .lock-banner {
            background: rgba(80,20,50,0.9);
            border-color: rgba(249,168,212,0.4);
            color: #fce7f3;
        }
        body.theme-gold.odd-even-play .lock-banner {
            background: rgba(70,45,10,0.9);
            border-color: rgba(251,191,36,0.4);
            color: #fef3c7;
        }
        body.theme-blue.odd-even-play .lock-banner {
            background: rgba(10,40,65,0.9);
            border-color: rgba(56,189,248,0.4);
            color: #e0f2fe;
        }
        body.theme-red.odd-even-play .badge.pink {
            background: rgba(239,68,68,0.22);
            color: #fdba74;
        }
        body.theme-black.odd-even-play .badge.pink {
            background: rgba(255,255,255,0.1);
            color: #e4e4e7;
        }
        body.theme-white.odd-even-play .badge.pink {
            background: rgba(225,29,72,0.12);
            color: #be123c;
        }
        body.theme-pink.odd-even-play .badge.pink {
            background: rgba(244,114,182,0.25);
            color: #fce7f3;
        }
        body.theme-gold.odd-even-play .badge.pink {
            background: rgba(251,191,36,0.22);
            color: #fef3c7;
        }
        body.theme-blue.odd-even-play .badge.pink {
            background: rgba(56,189,248,0.2);
            color: #e0f2fe;
        }
        body.theme-red.odd-even-play .bet-input-row input:focus { border-color: #fb923c; }
        body.theme-black.odd-even-play .bet-input-row input:focus { border-color: #a1a1aa; }
        body.theme-white.odd-even-play .bet-input-row input:focus { border-color: #e11d48; }
        body.theme-pink.odd-even-play .bet-input-row input:focus { border-color: #f9a8d4; }
        body.theme-gold.odd-even-play .bet-input-row input:focus { border-color: #fbbf24; }
        body.theme-blue.odd-even-play .bet-input-row input:focus { border-color: #38bdf8; }
        body.theme-white.odd-even-play .bet-input-row input {
            background: #fff;
            color: #0f172a;
            border-color: rgba(15,23,42,0.2);
        }
        body.theme-white.odd-even-play .btn,
        body.theme-white.odd-even-play .btn-quick {
            color: #0f172a;
            border-color: rgba(15,23,42,0.16);
            background: #ffffff;
        }
        body.theme-white.odd-even-play .btn-quick.is-selected {
            color: #92400e;
            border-color: #d97706;
            background: #fef3c7;
            box-shadow: 0 0 0 1px rgba(217, 119, 6, 0.25);
        }
        /* 화이트 테마: 밝은 배경에 고정된 밝은 글자색 보정 */
        body.theme-white.odd-even-play .info-card__title-label,
        body.theme-white.odd-even-play .card h3,
        body.theme-white.odd-even-play .theme-sheet__head h2 {
            color: #92400e;
        }
        body.theme-white.odd-even-play .info-card__title-meta {
            color: #0f172a;
        }
        body.theme-white.odd-even-play .info-card__sep {
            color: rgba(15,23,42,0.35);
        }
        body.theme-white.odd-even-play .payback-label {
            color: #0f172a;
        }
        body.theme-white.odd-even-play .payback-amount {
            color: #92400e;
        }
        body.theme-white.odd-even-play .payback-hint,
        body.theme-white.odd-even-play .hint,
        body.theme-white.odd-even-play .cost-line,
        body.theme-white.odd-even-play .theme-opt__meta span {
            color: #475569;
        }
        body.theme-white.odd-even-play .pending-box .bet-info {
            color: #92400e;
        }
        body.theme-white.odd-even-play .pending-box .bet-info .mult {
            color: #be123c;
        }
        body.theme-white.odd-even-play .pending-box .timer {
            color: #0369a1;
        }
        body.theme-white.odd-even-play .btn-mode {
            color: #334155;
            background: #f1f5f9;
            border-color: rgba(15,23,42,0.14);
        }
        body.theme-white.odd-even-play .btn-mode.active-easy,
        body.theme-white.odd-even-play .btn-mode.active-hard {
            color: #ffffff;
        }
        body.theme-white.odd-even-play .btn-cancel {
            color: #ffffff;
            background: linear-gradient(145deg, #475569, #334155);
        }
        body.theme-white.odd-even-play .btn-giveup {
            color: #ffffff;
            background: linear-gradient(145deg, #9a3412, #7c2d12);
        }
        body.theme-white.odd-even-play .btn-payback {
            color: #9f1239;
            background: linear-gradient(135deg, rgba(225,29,72,0.12), rgba(2,132,199,0.1));
            border-color: rgba(190,18,60,0.4);
        }
        body.theme-white.odd-even-play .btn-refresh-wrap .btn {
            color: #334155;
            border-color: rgba(15,23,42,0.18);
            background: #ffffff;
        }
        body.theme-white.odd-even-play .btn-theme-settings,
        body.theme-white.odd-even-play .theme-sheet__close {
            color: #0f172a;
            background: rgba(15,23,42,0.06);
        }
        body.theme-white.odd-even-play .theme-opt {
            color: #0f172a;
        }
        body.theme-white.odd-even-play .theme-opt__meta strong {
            color: #0f172a;
        }
        body.theme-white.odd-even-play .result.result-slot.is-idle {
            border-color: rgba(15,23,42,0.1);
            background: rgba(15,23,42,0.04);
        }
        body.theme-white.odd-even-play .result.result-slot.is-busy,
        body.theme-white.odd-even-play .result.result-slot.is-info {
            border-color: rgba(3,105,161,0.35);
            background: rgba(224,242,254,0.85);
            color: #0369a1;
        }
        body.theme-white.odd-even-play .btn-quick-swap {
            color: #047857;
            border-color: rgba(4,120,87,0.35);
            background: rgba(236,253,245,0.95);
        }
        body.theme-white.odd-even-play .cost-line b {
            color: #0f172a;
        }
        body.theme-white.odd-even-play .cost-line b.gold { color: #92400e; }
        body.theme-white.odd-even-play .cost-line b.accent { color: #be123c; }
        body.theme-white.odd-even-play .cost-line b.blue { color: #0369a1; }
        body.theme-white.odd-even-play .cost-line b.green { color: #047857; }
        body.theme-white.odd-even-play .lock-banner {
            color: #9f1239;
        }
        body.theme-white.odd-even-play .badge.pink {
            color: #9f1239;
            background: rgba(190,18,60,0.12);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { -webkit-text-size-adjust: 100%; }
        body {
            min-height: 100vh;
            min-height: 100dvh;
            background: var(--bg);
            background-image:
                radial-gradient(ellipse at 50% 0%, rgba(244,114,182,0.18) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 80%, rgba(56,189,248,0.12) 0%, transparent 45%),
                radial-gradient(ellipse at 10% 70%, rgba(251,191,36,0.08) 0%, transparent 40%);
            font-family: 'Noto Sans KR', sans-serif;
            color: var(--text);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            padding: 12px;
            padding-top: max(10px, env(safe-area-inset-top));
            padding-left: max(12px, env(safe-area-inset-left));
            padding-right: max(12px, env(safe-area-inset-right));
            padding-bottom: max(10px, env(safe-area-inset-bottom));
        }
        .wrap {
            width: 100%;
            max-width: 460px;
            min-width: 0;
            box-sizing: border-box;
            background: var(--card);
            border-radius: 16px;
            padding: 14px 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.45), 0 0 0 1px rgba(255,255,255,0.06);
        }
        h1 {
            font-family: 'Black Han Sans', sans-serif;
            font-size: 1.75rem;
            line-height: 1.05;
            text-align: center;
            margin: 0;
            background: linear-gradient(135deg, var(--title-a), var(--title-b));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: 1px;
        }
        .card {
            background: var(--card-bg, rgba(0,0,0,0.25));
            border: 1px solid var(--card-border, rgba(255,255,255,0.08));
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 8px;
        }
        .card h3 {
            font-size: 0.9rem;
            margin-bottom: 6px;
            color: var(--gold);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        /* 배팅 카드: 제목 + 연승 픽 궤적 한 줄 */
        #betCard h3.bet-card__title {
            flex-wrap: nowrap;
            gap: 6px;
            min-width: 0;
            margin-bottom: 6px;
        }
        #betCard .bet-card__title-label {
            flex-shrink: 0;
        }
        #betCard h3.bet-card__title .badge.pink {
            flex-shrink: 0;
            visibility: visible;
        }
        #betCard h3.bet-card__title .badge.pink.is-hidden {
            visibility: hidden;
        }
        #betCard .streak-trail--in-h3 {
            margin: 0;
            flex: 1 1 auto;
            min-width: 0;
            line-height: 1.35;
            overflow-x: auto;
            overflow-y: hidden;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
            display: none;
            white-space: nowrap;
            text-align: left;
        }
        #betCard .streak-trail--in-h3.show {
            display: block;
        }
        #betCard .streak-trail--in-h3 .st-pick {
            font-size: 0.86rem;
            margin-right: 6px;
        }
        #betCard .streak-trail--in-h3 .st-pick:last-child { margin-right: 0; }
        .card h3.info-card__title {
            flex-wrap: wrap;
            align-items: baseline;
            gap: 6px 10px;
        }
        .info-card__title-label {
            color: var(--gold);
            font-weight: 800;
            flex-shrink: 0;
        }
        .info-card__title-meta {
            flex: 1 1 auto;
            min-width: 0;
            font-size: 0.82rem;
            font-weight: 700;
            color: #ffffff;
            text-align: right;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-variant-numeric: tabular-nums;
        }
        .info-card__sep {
            color: rgba(255,255,255,0.45);
            font-weight: 400;
            padding: 0 5px;
        }
        .info-card__title-meta .unit-nyang {
            margin-left: 2px;
            font-weight: 600;
            opacity: 0.9;
        }
        .payback-card {
            margin-top: 10px;
            margin-bottom: 8px;
            padding: 10px 12px;
        }
        .payback-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 4px;
        }
        .payback-label {
            font-size: 0.88rem;
            font-weight: 700;
            color: #e2e8f0;
        }
        .payback-amount {
            font-size: 0.95rem;
            font-weight: 800;
            color: var(--gold);
            font-variant-numeric: tabular-nums;
        }
        .payback-amount .unit-nyang {
            margin-left: 2px;
            font-size: 0.82rem;
            font-weight: 600;
            opacity: 0.9;
        }
        .payback-hint {
            margin: 0 0 8px;
            font-size: 0.72rem;
            color: rgba(226,232,240,0.65);
            line-height: 1.35;
        }
        .btn-payback {
            width: 100%;
            background: linear-gradient(135deg, rgba(251,191,36,0.25), rgba(245,158,11,0.18));
            border: 1px solid rgba(251,191,36,0.45);
            color: #fde68a;
            font-weight: 800;
        }
        .btn-payback:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }
        /* 진행 중 배팅 카드 + 배팅 카드: 같은 그리드 칸에 겹침 → 전환 시에도 행 높이는 더 큰 쪽으로 고정 */
        .play-stack {
            display: grid;
            grid-template-columns: 1fr;
            margin-bottom: 8px;
            min-width: 0;
            width: 100%;
        }
        .play-stack > .pending-box,
        .play-stack > #betCard.card {
            grid-row: 1;
            grid-column: 1;
            align-self: start;
            width: 100%;
            max-width: 100%;
            min-width: 0;
            margin-bottom: 0;
            box-sizing: border-box;
            overflow: hidden;
        }
        .play-stack > :not(.panel-hidden) {
            z-index: 2;
        }
        .play-stack > .panel-hidden {
            z-index: 1;
            visibility: hidden;
            pointer-events: none;
            user-select: none;
        }

        /* 진행 중 배팅 카드 */
        .pending-box {
            background: linear-gradient(135deg, rgba(56,189,248,0.1), rgba(167,139,250,0.1));
            border: 1px solid rgba(56,189,248,0.3);
            border-radius: 12px;
            padding: 12px;
        }
        .pending-box .bet-info {
            text-align: center;
            font-size: 1rem;
            font-weight: 800;
            color: var(--gold);
            margin-bottom: 4px;
            font-family: 'Black Han Sans', sans-serif;
        }
        .pending-box .bet-info .mult { color: var(--accent); }
        .pending-box .timer {
            text-align: center;
            color: var(--blue);
            font-size: 0.78rem;
            margin-bottom: 6px;
        }
        .pick-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        .pick-row.pick-row--start {
            margin-top: 8px;
        }
        .btn-pick {
            position: relative;
            overflow: hidden;
            padding: 16px 8px;
            min-height: 100px;
            font-family: 'Black Han Sans', sans-serif;
            font-size: 1.4rem;
            color: #fff;
            border: none;
            border-radius: 14px;
            cursor: pointer;
            transition: transform 0.1s cubic-bezier(0.34, 1.2, 0.64, 1), box-shadow 0.12s ease, filter 0.1s ease;
            -webkit-tap-highlight-color: transparent;
            touch-action: manipulation;
            user-select: none;
            letter-spacing: 3px;
        }
        .btn-pick::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: inherit;
            opacity: 0;
            pointer-events: none;
            background: radial-gradient(circle at 50% 45%, rgba(255,255,255,0.55) 0%, transparent 68%);
        }
        .btn-pick:active:not(:disabled) {
            transform: scale(0.9) translateY(5px);
            filter: brightness(1.08);
        }
        .btn-pick.punch {
            animation: pick-punch 0.34s cubic-bezier(0.34, 1.45, 0.64, 1);
        }
        .btn-pick.punch::after {
            animation: pick-flash 0.38s ease-out;
        }
        @keyframes pick-punch {
            0% { transform: scale(1) translateY(0); }
            30% { transform: scale(0.88) translateY(6px); }
            65% { transform: scale(1.05) translateY(-2px); }
            100% { transform: scale(1) translateY(0); }
        }
        @keyframes pick-flash {
            0% { opacity: 0.75; transform: scale(0.35); }
            100% { opacity: 0; transform: scale(1.5); }
        }
        .btn-pick:disabled { opacity: 0.45; cursor: not-allowed; transform: none; filter: none; animation: none; }
        .btn-pick.hol {
            background: linear-gradient(145deg, #f472b6, #db2777);
            box-shadow: 0 8px 0 #9d174d, 0 10px 22px rgba(244,114,182,0.45);
        }
        .btn-pick.jjak {
            background: linear-gradient(145deg, #38bdf8, #0284c7);
            box-shadow: 0 8px 0 #075985, 0 10px 22px rgba(56,189,248,0.45);
        }
        .btn-pick.hol:active:not(:disabled),
        .btn-pick.hol.punch {
            box-shadow: 0 2px 0 #9d174d, 0 4px 14px rgba(244,114,182,0.55), 0 0 28px rgba(244,114,182,0.35);
        }
        .btn-pick.jjak:active:not(:disabled),
        .btn-pick.jjak.punch {
            box-shadow: 0 2px 0 #075985, 0 4px 14px rgba(56,189,248,0.55), 0 0 28px rgba(56,189,248,0.35);
        }

        /* 배팅 입력 */
        .bet-input-row { display: flex; gap: 8px; margin-top: 6px; min-width: 0; }
        .bet-input-row input {
            flex: 1 1 auto;
            min-width: 0;
            width: 100%;
            box-sizing: border-box;
            padding: 10px 12px;
            border: 2px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            background: rgba(0,0,0,0.3);
            color: var(--text);
            font-size: 16px;
            text-align: right;
            font-weight: 700;
            -webkit-appearance: none;
            appearance: none;
        }
        .bet-input-row input:focus { outline: none; border-color: var(--gold); }
        .bet-input-row input[readonly] {
            cursor: default;
            caret-color: transparent;
            user-select: none;
            -webkit-user-select: none;
        }
        .bet-input-row input[readonly]:focus { border-color: rgba(255,255,255,0.1); }
        .quick-bets {
            display: flex;
            flex-wrap: wrap;
            align-items: stretch;
            gap: 6px;
            margin-top: 8px;
            width: 100%;
            max-width: 100%;
            min-width: 0;
            box-sizing: border-box;
        }
        .btn-quick {
            flex: 1 1 calc(16.666% - 6px);
            min-width: 48px;
            max-width: 100%;
            padding: 8px 4px;
            min-height: 36px;
            font-family: 'Noto Sans KR', sans-serif;
            font-size: 0.82rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: var(--text);
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            cursor: pointer;
            -webkit-tap-highlight-color: transparent;
            touch-action: manipulation;
            box-sizing: border-box;
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .btn-quick:active { background: rgba(255,255,255,0.14); }
        .btn-quick.is-selected {
            border-color: rgba(251, 191, 36, 0.9);
            background: rgba(245, 158, 11, 0.32);
            box-shadow: 0 0 0 1px rgba(251, 191, 36, 0.35);
            color: #fef3c7;
        }
        .btn-quick:disabled,
        .btn-quick.btn-quick--below-min {
            opacity: 0.45;
            cursor: not-allowed;
        }
        .btn-quick:disabled:active { background: rgba(255,255,255,0.06); }
        .btn-quick.btn-quick--below-min { opacity: 0.35; }
        .btn-quick.btn-quick--below-min.is-selected { opacity: 0.55; }
        .btn-quick-swap {
            flex: 1 1 100%;
            color: #9ef5d4;
            border-color: rgba(90, 220, 180, 0.4);
            background: rgba(30, 110, 85, 0.28);
        }
        .btn-quick-swap:active { background: rgba(50, 150, 115, 0.4); }
        .btn-quick-swap:disabled { opacity: 0.4; }

        /* 후원: 결과(상태)창 안쪽 우측 */
        .btn-donate-float {
            display: none;
            position: absolute;
            z-index: 5;
            top: 50%;
            right: 8px;
            transform: translateY(-50%);
            flex-direction: column;
            gap: 1px;
            padding: 10px 10px;
            min-width: 52px;
            border-radius: 14px;
            font-family: 'Noto Sans KR', sans-serif;
            font-size: 0.92rem;
            font-weight: 900;
            letter-spacing: -0.02em;
            color: #fff7ed;
            border: 2px solid rgba(253, 224, 71, 0.95);
            background: linear-gradient(160deg, #f43f5e 0%, #be123c 45%, #9f1239 100%);
            box-shadow:
                0 0 0 2px rgba(251, 191, 36, 0.28),
                0 6px 16px rgba(0, 0, 0, 0.35),
                0 0 18px rgba(244, 63, 94, 0.45);
            cursor: pointer;
            -webkit-tap-highlight-color: transparent;
            touch-action: manipulation;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
            text-align: center;
            line-height: 1.15;
            animation: donatePulse 1.4s ease-in-out infinite;
        }
        .btn-donate-float.is-visible { display: flex; }
        .btn-donate-float:active { filter: brightness(1.1); }
        .btn-donate-float:disabled { opacity: 0.45; cursor: not-allowed; animation: none; }
        @keyframes donatePulse {
            0%, 100% {
                transform: translateY(-50%) scale(1);
                box-shadow:
                    0 0 0 2px rgba(251, 191, 36, 0.28),
                    0 6px 16px rgba(0, 0, 0, 0.35),
                    0 0 18px rgba(244, 63, 94, 0.45);
            }
            50% {
                transform: translateY(-50%) scale(1.06);
                box-shadow:
                    0 0 0 4px rgba(253, 224, 71, 0.42),
                    0 8px 18px rgba(0, 0, 0, 0.4),
                    0 0 26px rgba(251, 191, 36, 0.55);
            }
        }

        @media (max-width: 768px) {
            .quick-bets .btn-quick-swap { display: none !important; }
        }

        .mode-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
            margin: 6px 0 2px;
        }
        .btn-mode {
            padding: 7px 6px;
            min-height: 36px;
            font-family: 'Noto Sans KR', sans-serif;
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--muted);
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 10px;
            cursor: pointer;
            -webkit-tap-highlight-color: transparent;
            touch-action: manipulation;
            line-height: 1.25;
        }
        .btn-mode small {
            display: block;
            font-size: 0.64rem;
            font-weight: 400;
            margin-top: 1px;
            opacity: 0.85;
        }
        .btn-mode.active-easy {
            color: #fff;
            background: linear-gradient(145deg, #15803d, #166534);
            border-color: rgba(74,222,128,0.45);
        }
        .btn-mode.active-hard {
            color: #fff;
            background: linear-gradient(145deg, #b91c1c, #7f1d1d);
            border-color: rgba(248,113,113,0.45);
        }
        .btn-mode:disabled { opacity: 0.45; cursor: not-allowed; }

        .cost-grid {
            display: block;
        }
        .cost-line { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 2px; font-size: 0.8rem; color: var(--muted); }
        .cost-line b { color: var(--text); font-weight: 700; }
        .cost-line b.gold { color: var(--gold); }
        .cost-line b.accent { color: var(--accent); }
        .cost-line b.blue { color: var(--blue); }
        .cost-line b.green { color: var(--green); }

        .btn {
            padding: 10px 8px;
            min-height: 40px;
            font-family: 'Noto Sans KR', sans-serif;
            font-size: 0.85rem;
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
        .btn-cancel {
            width: 100%;
            margin-top: 6px;
            background: linear-gradient(145deg, #475569, #334155);
            border: 1px solid rgba(255,255,255,0.1);
        }
        .btn-giveup {
            width: 100%;
            margin-top: 14px;
            background: linear-gradient(145deg, #7c2d12, #431407);
            border: 1px solid rgba(248,113,113,0.3);
        }

        .result {
            margin: 0;
            box-sizing: border-box;
            width: 100%;
        }
        /* 결과 슬롯: 항상 고정 높이 유지 (배수·보너스 등장 시 레이아웃 흔들림 방지) */
        .game-feedback {
            margin: 0 0 8px;
            display: block;
        }
        .result-wrap {
            position: relative;
            width: 100%;
            height: 9.2rem;
            min-height: 9.2rem;
            max-height: 9.2rem;
            display: flex;
            align-items: stretch;
        }
        .result.result-slot {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            height: 100%;
            min-height: 0;
            width: 100%;
            padding: 10px 10px;
            border-radius: 10px;
            border: 1.5px solid rgba(255,255,255,0.08);
            background: rgba(0,0,0,0.2);
            font-size: 0.82rem;
            line-height: 1.35;
            white-space: normal;
            word-break: break-word;
            overflow: hidden;
            transition: border-color 0.2s ease, background 0.2s ease;
        }
        .result.result-slot.is-idle {
            border-color: rgba(255,255,255,0.06);
            background: rgba(0,0,0,0.16);
        }
        .result.result-slot.is-busy,
        .result.result-slot.is-info {
            border-color: rgba(56,189,248,0.35);
            background: rgba(0,0,0,0.28);
            color: var(--blue);
            font-weight: 700;
        }
        .result-busy-dice {
            display: inline-block;
            animation: spin 0.32s linear infinite;
            margin-right: 4px;
        }
        /* 승·패·무: 결과 배너 — 슬롯 높이 고정(부모 .result-wrap) */
        .result.result-slot.outcome {
            padding: 10px 12px 12px;
            text-align: center;
            font-size: 0.8rem;
            gap: 2px;
            height: 100%;
            min-height: 0;
            overflow: hidden;
            justify-content: center;
        }
        .result.result-slot .outcome-head {
            font-size: 0.95rem;
            font-weight: 900;
            letter-spacing: -0.02em;
            margin: 0;
            line-height: 1.15;
            min-height: 1.15em;
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            text-shadow: 0 1px 8px rgba(0,0,0,0.4);
        }
        .result.result-slot .outcome-picks {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            align-items: center;
            gap: 3px 6px;
            font-size: 0.72rem;
            font-weight: 800;
            margin: 0;
            min-height: 1.3em;
            line-height: 1.2;
            flex: 0 0 auto;
        }
        .result.result-slot .outcome-streak {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 1px 6px;
            border-radius: 999px;
            background: rgba(251,191,36,0.22);
            border: 1px solid rgba(251,191,36,0.35);
            font-size: 0.68rem;
            font-weight: 800;
            line-height: 1.2;
            color: #fde68a;
            vertical-align: middle;
        }
        .result.result-slot .outcome-amount {
            font-size: 1.2rem;
            font-weight: 900;
            margin: 2px 0;
            min-height: 1.3em;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            gap: 6px 8px;
            letter-spacing: -0.02em;
            line-height: 1.15;
            flex: 0 0 auto;
        }
        .result.result-slot .outcome-amount-num {
            font-weight: 900;
        }
        /* 배수 보너스 줄: 없어도 자리 예약 → 등장 시 화면 안 흔들림 */
        .result.result-slot .outcome-hard-bonus {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            margin: 1px auto;
            padding: 5px 14px;
            min-height: 2.35rem;
            box-sizing: border-box;
            border-radius: 14px;
            background: linear-gradient(135deg, rgba(250,204,21,0.38) 0%, rgba(245,158,11,0.28) 100%);
            border: 2px solid #facc15;
            color: #fef08a;
            font-size: 1.35rem;
            font-weight: 900;
            line-height: 1.1;
            letter-spacing: 0.02em;
            white-space: nowrap;
            box-shadow: 0 0 0 1px rgba(250,204,21,0.35), 0 0 18px rgba(250,204,21,0.45), inset 0 1px 0 rgba(255,255,255,0.2);
            text-shadow: 0 0 12px rgba(250,204,21,0.65);
            animation: hardBonusPop 0.4s ease-out;
            flex: 0 0 auto;
            transform-origin: center center;
        }
        .result.result-slot .outcome-hard-bonus.is-empty {
            visibility: hidden;
            animation: none;
            box-shadow: none;
            border-color: transparent;
            background: transparent;
            text-shadow: none;
        }
        .result.result-slot .outcome-hard-bonus .outcome-hard-mult {
            font-size: 1.55rem;
            font-weight: 900;
            color: #fffbeb;
            letter-spacing: -0.02em;
        }
        .result.result-slot .outcome-hard-bonus.is-lose {
            background: linear-gradient(135deg, rgba(239,68,68,0.35) 0%, rgba(127,29,29,0.4) 100%);
            border-color: #f87171;
            color: #fecaca;
            box-shadow: 0 0 0 1px rgba(248,113,113,0.35), 0 0 16px rgba(239,68,68,0.4), inset 0 1px 0 rgba(255,255,255,0.12);
            text-shadow: 0 0 10px rgba(248,113,113,0.55);
        }
        .result.result-slot .outcome-hard-bonus.is-lose .outcome-hard-mult {
            color: #fff1f2;
        }
        @keyframes hardBonusPop {
            0% { opacity: 0; }
            100% { opacity: 1; }
        }
        .result.result-slot .outcome-amount.win { color: #fde68a; }
        .result.result-slot .outcome-amount.lose { color: #fca5a5; }
        .result.result-slot .outcome-amount.push { color: #ddd6fe; font-size: 0.85rem; font-weight: 700; }
        .result.result-slot .outcome-sub {
            font-size: 0.68rem;
            color: rgba(226,232,240,0.72);
            margin: 0;
            min-height: 1.35em;
            line-height: 1.3;
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
        }
        .result.result-slot .outcome-sub.is-empty {
            visibility: hidden;
        }
        .result.result-slot.outcome.success {
            background: linear-gradient(155deg, #166534 0%, #14532d 38%, #052e16 100%);
            border: 2.5px solid #facc15;
            color: #ecfccb;
            box-shadow: 0 0 0 1px rgba(250,204,21,0.35), inset 0 1px 0 rgba(255,255,255,0.14), 0 6px 22px rgba(34,197,94,0.28);
        }
        .result.result-slot.outcome.success .outcome-head {
            color: #fef08a;
            font-size: 1.05rem;
            letter-spacing: 0.04em;
            text-shadow: 0 0 14px rgba(250,204,21,0.55);
        }
        .result.result-slot.outcome.success .outcome-picks {
            color: #d9f99d;
        }
        .result.result-slot.outcome.success .outcome-streak {
            background: rgba(250,204,21,0.28);
            border-color: #fde047;
            color: #fef9c3;
        }
        .result.result-slot.outcome.success .outcome-amount.win {
            color: #fef08a;
            font-size: 1.28rem;
            text-shadow: 0 0 10px rgba(250,204,21,0.45);
        }
        .result.result-slot.outcome.success .outcome-sub {
            color: rgba(217,249,158,0.85);
        }
        .result.result-slot.outcome.fail {
            background: linear-gradient(155deg, #991b1b 0%, #7f1d1d 38%, #290505 100%);
            border: 2.5px solid #f87171;
            color: #fecaca;
            box-shadow: 0 0 0 1px rgba(248,113,113,0.3), inset 0 1px 0 rgba(255,255,255,0.06), 0 6px 22px rgba(220,38,38,0.32);
        }
        .result.result-slot.outcome.fail .outcome-head {
            color: #fff;
            font-size: 1.05rem;
            letter-spacing: 0.04em;
            text-shadow: 0 0 12px rgba(248,113,113,0.65);
        }
        .result.result-slot.outcome.fail .outcome-picks {
            color: #fca5a5;
        }
        .result.result-slot.outcome.fail .outcome-amount.lose {
            color: #ff8a8a;
            font-size: 1.28rem;
            text-shadow: 0 0 10px rgba(239,68,68,0.5);
        }
        .result.result-slot.outcome.fail .outcome-sub {
            color: rgba(254,202,202,0.9);
        }
        .result.result-slot.outcome.push {
            background: linear-gradient(155deg, #5b21b6 0%, #4c1d95 40%, #1e1b4b 100%);
            border: 2.5px solid #c4b5fd;
            color: #ede9fe;
            box-shadow: 0 0 0 1px rgba(167,139,250,0.3), inset 0 1px 0 rgba(255,255,255,0.08), 0 6px 20px rgba(139,92,246,0.22);
        }
        .result.result-slot.outcome.push .outcome-head {
            color: #e9d5ff;
            font-size: 1rem;
        }
        .result.result-slot.outcome.push .outcome-picks {
            color: #ddd6fe;
        }
        .result.result-slot.outcome.push .outcome-amount.push {
            color: #f5f3ff;
        }
        @keyframes spin {
            0% { transform: rotate(0deg) scale(1); }
            50% { transform: rotate(180deg) scale(1.1); }
            100% { transform: rotate(360deg) scale(1); }
        }

        .hint { font-size: 0.7rem; color: var(--muted); margin-top: 4px; line-height: 1.35; }
        .bigmsg { text-align: center; padding: 24px 12px; color: var(--muted); font-size: 0.95rem; line-height: 1.6; }
        .bigmsg strong { color: var(--accent); }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 0.72rem;
            background: rgba(251,191,36,0.15);
            color: var(--gold);
            margin-left: 6px;
        }
        .badge.warn { background: rgba(248,113,113,0.15); color: var(--red); }
        .badge.info { background: rgba(56,189,248,0.15); color: var(--blue); }
        .badge.pink { background: rgba(244,114,182,0.18); color: var(--accent); }

        /* 이번 연승 픽 순서 (로그 기반 홀→짝→…) */
        .streak-trail {
            margin: 0 0 6px;
            line-height: 1.4;
            display: none;
        }
        .streak-trail.show { display: block; }
        .streak-trail--pending {
            text-align: center;
            margin: 0 0 6px;
        }
        .streak-trail .st-pick {
            display: inline-block;
            font-weight: 900;
            font-family: 'Black Han Sans', sans-serif;
            font-size: 0.9rem;
            letter-spacing: 0.04em;
            margin-right: 8px;
        }
        .streak-trail .st-pick:last-child { margin-right: 0; }
        .streak-trail .st-pick.hol-t { color: #f472b6; text-shadow: 0 0 14px rgba(244,114,182,0.35); }
        .streak-trail .st-pick.jjak-t { color: #38bdf8; text-shadow: 0 0 14px rgba(56,189,248,0.35); }

        body.theme-red.odd-even-play .streak-trail .st-pick.hol-t { color: #f87171; text-shadow: 0 0 14px rgba(239,68,68,0.45); }
        body.theme-red.odd-even-play .streak-trail .st-pick.jjak-t { color: #fb923c; text-shadow: 0 0 14px rgba(251,146,60,0.4); }
        body.theme-black.odd-even-play .streak-trail .st-pick.hol-t { color: #e4e4e7; text-shadow: 0 0 14px rgba(255,255,255,0.25); }
        body.theme-black.odd-even-play .streak-trail .st-pick.jjak-t { color: #a1a1aa; text-shadow: 0 0 14px rgba(255,255,255,0.18); }
        body.theme-white.odd-even-play .streak-trail .st-pick.hol-t { color: #e11d48; text-shadow: none; }
        body.theme-white.odd-even-play .streak-trail .st-pick.jjak-t { color: #0284c7; text-shadow: none; }
        body.theme-pink.odd-even-play .streak-trail .st-pick.hol-t { color: #f9a8d4; text-shadow: 0 0 14px rgba(244,114,182,0.45); }
        body.theme-pink.odd-even-play .streak-trail .st-pick.jjak-t { color: #e879f9; text-shadow: 0 0 14px rgba(232,121,249,0.4); }
        body.theme-gold.odd-even-play .streak-trail .st-pick.hol-t { color: #fbbf24; text-shadow: 0 0 14px rgba(251,191,36,0.45); }
        body.theme-gold.odd-even-play .streak-trail .st-pick.jjak-t { color: #fde68a; text-shadow: 0 0 14px rgba(253,230,138,0.4); }
        body.theme-blue.odd-even-play .streak-trail .st-pick.hol-t { color: #38bdf8; text-shadow: 0 0 14px rgba(56,189,248,0.45); }
        body.theme-blue.odd-even-play .streak-trail .st-pick.jjak-t { color: #818cf8; text-shadow: 0 0 14px rgba(129,140,248,0.4); }

        .title-bar {
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            margin: 0 0 8px;
            min-height: 2rem;
        }
        .title-bar h1 {
            margin: 0;
            flex: 1 1 auto;
            text-align: center;
        }
        .btn-theme-settings {
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 36px;
            height: 36px;
            border: none;
            border-radius: 10px;
            background: rgba(255,255,255,0.08);
            color: var(--text);
            font-size: 1.15rem;
            line-height: 1;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            -webkit-tap-highlight-color: transparent;
            touch-action: manipulation;
        }
        body.theme-white.odd-even-play .btn-theme-settings {
            background: rgba(15,23,42,0.06);
        }
        .btn-theme-settings:active { transform: translateY(-50%) scale(0.94); }
        .theme-layer {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 200;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(0,0,0,0.55);
            backdrop-filter: blur(3px);
        }
        .theme-layer.is-open { display: flex; }
        .theme-sheet {
            width: 100%;
            max-width: 340px;
            max-height: min(80vh, 560px);
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            background: var(--card);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 16px 14px 14px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.4);
        }
        .theme-sheet__head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }
        .theme-sheet__head h2 {
            font-size: 1rem;
            font-weight: 900;
            color: var(--gold);
            margin: 0;
        }
        .theme-sheet__close {
            width: 32px;
            height: 32px;
            border: none;
            border-radius: 8px;
            background: rgba(255,255,255,0.08);
            color: var(--text);
            font-size: 1.1rem;
            cursor: pointer;
        }
        body.theme-white.odd-even-play .theme-sheet__close {
            background: rgba(15,23,42,0.06);
        }
        .theme-options {
            display: grid;
            gap: 8px;
        }
        .theme-opt {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            padding: 10px 12px;
            border-radius: 12px;
            border: 1px solid var(--card-border);
            background: var(--card-bg);
            color: var(--text);
            cursor: pointer;
            text-align: left;
            font-family: inherit;
            -webkit-tap-highlight-color: transparent;
        }
        .theme-opt.is-active {
            border-color: var(--accent);
            box-shadow: 0 0 0 1px var(--accent);
        }
        .theme-opt__swatch {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            flex-shrink: 0;
            border: 1px solid rgba(255,255,255,0.2);
        }
        .theme-opt__swatch.sw-red {
            background: linear-gradient(135deg, #7f1d1d, #ef4444 55%, #fdba74);
        }
        .theme-opt__swatch.sw-black {
            background: linear-gradient(135deg, #000000, #27272a 55%, #d4d4d8);
        }
        .theme-opt__swatch.sw-white {
            background: linear-gradient(135deg, #f8fafc, #e2e8f0 55%, #e11d48);
            border-color: rgba(15,23,42,0.15);
        }
        .theme-opt__swatch.sw-pink {
            background: linear-gradient(135deg, #9d174d, #f472b6 55%, #fce7f3);
        }
        .theme-opt__swatch.sw-gold {
            background: linear-gradient(135deg, #92400e, #fbbf24 55%, #fef3c7);
        }
        .theme-opt__swatch.sw-blue {
            background: linear-gradient(135deg, #075985, #38bdf8 55%, #bae6fd);
        }
        .theme-opt__swatch.sw-me {
            background: linear-gradient(135deg, #111 0%, var(--me-hex, #FAE100) 55%, #fff);
            border-color: rgba(255,255,255,0.25);
        }
        .theme-opt__meta {
            display: flex;
            flex-direction: column;
            gap: 2px;
            min-width: 0;
        }
        .theme-opt__meta strong {
            font-size: 0.9rem;
            font-weight: 800;
        }
        .theme-opt__meta span {
            font-size: 0.72rem;
            color: var(--muted);
        }

        .lock-banner {
            background: rgba(248,113,113,0.1);
            border: 1px solid rgba(248,113,113,0.35);
            border-radius: 10px;
            padding: 8px 10px;
            color: var(--red);
            font-size: 0.8rem;
            text-align: center;
            margin-bottom: 8px;
        }

        .btn-refresh-wrap {
            text-align: center;
            margin-top: 6px;
        }
        .btn-refresh-wrap .btn {
            background: transparent;
            border: 1px solid rgba(255,255,255,0.15);
            color: var(--muted);
            font-weight: 400;
            min-height: 30px;
            padding: 4px 12px;
            font-size: 0.78rem;
        }
        .footer-nav {
            margin-top: 12px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        a.btn-nav-wallet,
        button.btn-nav-swap,
        button.btn-nav-claim-donate {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            text-decoration: none;
            padding: 12px 10px;
            min-height: 46px;
            font-family: 'Noto Sans KR', sans-serif;
            font-size: 0.9rem;
            font-weight: 700;
            color: #fff;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.12);
            background: linear-gradient(145deg, #2d3561, #20264a);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            -webkit-tap-highlight-color: transparent;
            touch-action: manipulation;
            cursor: pointer;
            box-sizing: border-box;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        button.btn-nav-swap {
            color: #d1fae5;
            border-color: rgba(52, 211, 153, 0.35);
            background: linear-gradient(145deg, #1f6b55, #164f40);
        }
        a.btn-nav-wallet:active,
        button.btn-nav-swap:active,
        button.btn-nav-claim-donate:active { transform: translateY(1px); opacity: 0.92; }
        button.btn-nav-swap:disabled,
        button.btn-nav-claim-donate:disabled {
            opacity: 0.45;
            cursor: not-allowed;
            transform: none;
        }
        body.theme-white.odd-even-play a.btn-nav-wallet,
        body.theme-white.odd-even-play button.btn-nav-claim-donate {
            color: #1e293b;
            background: linear-gradient(145deg, #e2e8f0, #cbd5e1);
            border-color: rgba(15,23,42,0.12);
            box-shadow: 0 2px 8px rgba(15,23,42,0.08);
        }
        body.theme-white.odd-even-play button.btn-nav-swap {
            color: #065f46;
            background: linear-gradient(145deg, #d1fae5, #a7f3d0);
            border-color: rgba(5, 150, 105, 0.35);
            box-shadow: 0 2px 8px rgba(15,23,42,0.08);
        }

        /* 우하단 플로팅: 본방 · 홍보 · 가방 */
        .oe-room-fab {
            position: fixed;
            right: max(14px, env(safe-area-inset-right));
            bottom: max(18px, env(safe-area-inset-bottom));
            z-index: 90;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 10px;
            pointer-events: none;
        }
        .oe-room-fab-panel {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 8px;
            pointer-events: auto;
        }
        .oe-room-fab-panel[hidden] {
            display: none !important;
        }
        .oe-room-fab-item,
        .oe-room-fab-toggle {
            pointer-events: auto;
            appearance: none;
            border: 1px solid rgba(255,255,255,0.16);
            background: linear-gradient(160deg, rgba(45, 53, 97, 0.96), rgba(24, 28, 52, 0.98));
            color: #e8e4dc;
            box-shadow: 0 10px 28px rgba(0,0,0,0.35);
            cursor: pointer;
            text-decoration: none;
            font-family: 'Noto Sans KR', sans-serif;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
            -webkit-tap-highlight-color: transparent;
        }
        .oe-room-fab-item:hover,
        .oe-room-fab-toggle:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.42);
        }
        .oe-room-fab-item {
            min-height: 42px;
            padding: 0 14px 0 10px;
            border-radius: 999px;
            font-size: 0.82rem;
            font-weight: 700;
        }
        .oe-room-fab-ico { font-size: 1.05rem; line-height: 1; }
        .oe-room-fab-toggle {
            width: 54px;
            height: 54px;
            border-radius: 50%;
            background: linear-gradient(160deg, rgba(129, 140, 248, 0.4), rgba(45, 53, 97, 0.96));
            border-color: rgba(165, 180, 252, 0.5);
        }
        .oe-room-fab-toggle[aria-expanded="true"] {
            background: linear-gradient(160deg, rgba(129, 140, 248, 0.55), rgba(45, 53, 97, 0.98));
        }
        .oe-room-fab-toggle-ico { font-size: 1.35rem; line-height: 1; }
        body.theme-white.odd-even-play .oe-room-fab-item,
        body.theme-white.odd-even-play .oe-room-fab-toggle {
            color: #1e293b;
            background: linear-gradient(160deg, #f8fafc, #e2e8f0);
            border-color: rgba(15,23,42,0.12);
            box-shadow: 0 8px 20px rgba(15,23,42,0.12);
        }
        body.theme-white.odd-even-play .oe-room-fab-toggle {
            background: linear-gradient(160deg, #c7d2fe, #e2e8f0);
            border-color: rgba(99, 102, 241, 0.35);
        }
        .oe-promo-modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 110;
            background: rgba(0,0,0,0.65);
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .oe-promo-modal.open { display: flex; }
        .oe-promo-box {
            width: 100%;
            max-width: 340px;
            background: #1a1f35;
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 14px;
            padding: 18px 16px 14px;
            color: #e8e4dc;
            box-shadow: 0 16px 40px rgba(0,0,0,0.45);
        }
        .oe-promo-box h3 { margin: 0 0 10px; font-size: 1.05rem; }
        .oe-promo-box p { margin: 0 0 8px; font-size: 0.86rem; line-height: 1.5; color: #cbd5e1; }
        .oe-promo-box ul { margin: 0 0 14px; padding-left: 1.1em; font-size: 0.84rem; line-height: 1.55; color: #cbd5e1; }
        .oe-promo-box .emph { color: #a5b4fc; font-weight: 700; }
        .oe-promo-actions { display: flex; gap: 8px; }
        .oe-promo-actions button {
            flex: 1;
            min-height: 42px;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.12);
            font-family: inherit;
            font-weight: 700;
            cursor: pointer;
        }
        .oe-promo-actions .btn-cancel { background: rgba(255,255,255,0.06); color: #e2e8f0; }
        .oe-promo-actions .btn-go { background: linear-gradient(145deg, #6366f1, #4f46e5); color: #fff; border-color: transparent; }
        body.theme-white.odd-even-play .oe-promo-box {
            background: #fff;
            color: #0f172a;
            border-color: rgba(15,23,42,0.1);
        }
        body.theme-white.odd-even-play .oe-promo-box p,
        body.theme-white.odd-even-play .oe-promo-box ul { color: #475569; }
        body.theme-white.odd-even-play .oe-promo-box .emph { color: #4f46e5; }

        /* perf=1 — 모바일에서도 응답 시간 확인용 */
        #perfPanel {
            position: fixed;
            left: max(6px, env(safe-area-inset-left));
            right: max(6px, env(safe-area-inset-right));
            bottom: max(6px, env(safe-area-inset-bottom));
            z-index: 9999;
            max-width: 460px;
            margin: 0 auto;
            font-family: ui-monospace, 'SF Mono', Menlo, monospace;
            font-size: 0.68rem;
            line-height: 1.35;
            color: #e2e8f0;
            background: rgba(15, 23, 42, 0.92);
            border: 1px solid rgba(56, 189, 248, 0.35);
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.45);
            -webkit-tap-highlight-color: transparent;
        }
        #perfPanel.perf-panel--collapsed #perfBody { display: none; }
        #perfHead {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 10px;
            cursor: pointer;
            user-select: none;
            color: var(--blue);
            font-weight: 700;
            font-size: 0.7rem;
        }
        #perfHead span:last-child { color: var(--muted); font-weight: 400; }
        #perfBody { padding: 0 10px 8px; max-height: 28vh; overflow-y: auto; }
        .perf-line { display: flex; justify-content: space-between; gap: 8px; padding: 2px 0; border-top: 1px solid rgba(255,255,255,0.06); }
        .perf-line:first-child { border-top: none; }
        .perf-line .perf-ms { flex-shrink: 0; font-weight: 700; }
        .perf-line .perf-ms.slow { color: #fbbf24; }
        .perf-line .perf-ms.very-slow { color: #f87171; }
        .perf-line .perf-err { color: #f87171; }

        @media (max-width: 480px) {
            body { padding: 6px; padding-top: max(6px, env(safe-area-inset-top)); }
            .wrap { padding: 8px 10px; border-radius: 12px; }
            h1 { font-size: 1.4rem; margin-bottom: 4px; letter-spacing: 0.5px; }
            .card { padding: 8px 10px; margin-bottom: 6px; }
            .card h3 { font-size: 0.82rem; margin-bottom: 4px; }
            .info-card__title-meta { font-size: 0.72rem; }
            .game-feedback { margin-bottom: 6px; }
            .result-wrap { height: 8.6rem; min-height: 8.6rem; max-height: 8.6rem; }
            .result.result-slot { height: 100%; min-height: 0; padding: 8px 8px; border-radius: 8px; }
            .result.result-slot.outcome { padding: 8px 10px 10px; gap: 2px; height: 100%; min-height: 0; }
            .btn-donate-float { min-width: 48px; padding: 8px 8px; font-size: 0.86rem; right: 6px; }

            .result.result-slot .outcome-head { font-size: 0.88rem; }
            .result.result-slot .outcome-picks { font-size: 0.68rem; gap: 2px 5px; }
            .result.result-slot .outcome-streak { font-size: 0.64rem; padding: 1px 5px; }
            .result.result-slot .outcome-amount { font-size: 1.1rem; }
            .result.result-slot .outcome-amount.win,
            .result.result-slot .outcome-amount.lose { font-size: 1.18rem; }
            .result.result-slot .outcome-hard-bonus { font-size: 1.2rem; padding: 4px 12px; margin: 1px auto; min-height: 2.1rem; }
            .result.result-slot .outcome-hard-bonus .outcome-hard-mult { font-size: 1.4rem; }
            .result.result-slot .outcome-amount.push { font-size: 0.8rem; }
            .result.result-slot .outcome-sub { font-size: 0.64rem; }
            .cost-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 1px 8px;
                margin-bottom: 2px;
            }
            .cost-grid .cost-line:last-child { grid-column: 1 / -1; }
            .cost-line { font-size: 0.72rem; margin-bottom: 1px; }
            .bet-input-row { margin-top: 4px !important; }
            .btn-pick { min-height: 60px; font-size: 1.25rem; padding: 12px 6px; letter-spacing: 2px; border-radius: 10px; }
            .btn-cancel, .btn-giveup { font-size: 0.72rem; min-height: 34px; padding: 6px 8px; }
            .pending-box .hint { display: none; }
            .btn-refresh-wrap { margin-top: 4px; }
        }

        @media (max-width: 480px) and (max-height: 740px) {
            h1 { font-size: 1.25rem; margin-bottom: 2px; }
            .btn-pick { min-height: 74px; font-size: 1.15rem; }
            .btn-mode { min-height: 32px; padding: 5px 4px; }
            .btn-quick { min-height: 32px; font-size: 0.7rem; flex: 1 1 calc(33.333% - 6px); min-width: 56px; }
            .quick-bets { margin-top: 6px; gap: 5px; }
            .pick-row.pick-row--start { margin-top: 5px; }
        }
    </style>
</head>
<body<?php
if ($game_need_code) {
    echo '';
} else {
    echo ' class="odd-even-play theme-red" style="--me-hex:' . htmlspecialchars($game_profile_hex, ENT_QUOTES, 'UTF-8') . ';"';
}
?>>
    <div class="wrap">
        <div class="title-bar">
            <h1>홀 · 짝</h1>
            <?php if (!$game_need_code) { ?>
            <button type="button" class="btn-theme-settings" id="btnThemeSettings" title="테마 설정" aria-label="테마 설정">⚙️</button>
            <?php } ?>
        </div>
        <?php if (!$game_need_code) { ?>
        <div class="theme-layer" id="themeLayer" aria-hidden="true">
            <div class="theme-sheet" role="dialog" aria-modal="true" aria-labelledby="themeSheetTitle">
                <div class="theme-sheet__head">
                    <h2 id="themeSheetTitle">테마 설정</h2>
                    <button type="button" class="theme-sheet__close" id="btnThemeClose" aria-label="닫기">✕</button>
                </div>
                <div class="theme-options">
                    <button type="button" class="theme-opt" data-theme="red" id="themeOptRed">
                        <span class="theme-opt__swatch sw-red" aria-hidden="true"></span>
                        <span class="theme-opt__meta">
                            <strong>레드 버전</strong>
                            <span>지금 기본 테마</span>
                        </span>
                    </button>
                    <button type="button" class="theme-opt" data-theme="black" id="themeOptBlack">
                        <span class="theme-opt__swatch sw-black" aria-hidden="true"></span>
                        <span class="theme-opt__meta">
                            <strong>블랙 버전</strong>
                            <span>차분한 블랙 톤</span>
                        </span>
                    </button>
                    <button type="button" class="theme-opt" data-theme="white" id="themeOptWhite">
                        <span class="theme-opt__swatch sw-white" aria-hidden="true"></span>
                        <span class="theme-opt__meta">
                            <strong>화이트 버전</strong>
                            <span>밝은 라이트 톤</span>
                        </span>
                    </button>
                    <button type="button" class="theme-opt" data-theme="pink" id="themeOptPink">
                        <span class="theme-opt__swatch sw-pink" aria-hidden="true"></span>
                        <span class="theme-opt__meta">
                            <strong>공주 버전</strong>
                            <span>핑크 로맨틱 톤</span>
                        </span>
                    </button>
                    <button type="button" class="theme-opt" data-theme="gold" id="themeOptGold">
                        <span class="theme-opt__swatch sw-gold" aria-hidden="true"></span>
                        <span class="theme-opt__meta">
                            <strong>왕자 버전</strong>
                            <span>골드 럭셔리 톤</span>
                        </span>
                    </button>
                    <button type="button" class="theme-opt" data-theme="blue" id="themeOptBlue">
                        <span class="theme-opt__swatch sw-blue" aria-hidden="true"></span>
                        <span class="theme-opt__meta">
                            <strong>블루 버전</strong>
                            <span>시원한 파랑 톤</span>
                        </span>
                    </button>
                    <button type="button" class="theme-opt" data-theme="me" id="themeOptMe">
                        <span class="theme-opt__swatch sw-me" aria-hidden="true" style="--me-hex:<?php echo htmlspecialchars($game_profile_hex, ENT_QUOTES, 'UTF-8'); ?>"></span>
                        <span class="theme-opt__meta">
                            <strong>내 프로필색</strong>
                            <span>내 색번호 <?php echo (int)$game_profile_num; ?> 톤</span>
                        </span>
                    </button>
                </div>
            </div>
        </div>
        <?php } ?>
        <?php if ($game_need_code) { ?>
            <div class="bigmsg">
                <p style="font-size:2rem;margin-bottom:10px;">🔒</p>
                <strong>코드를 부여받으세요.</strong><br>
                올바른 초대 코드로 접속해야 이용할 수 있어요.<br>
                <span style="opacity:0.7;">예) /api/game/odd_even_web.php?code=XXXX</span>
            </div>
        <?php } else { ?>

        <!-- 회원 정보 카드 -->
        <div class="card">
            <h3 class="info-card__title">
                <span class="info-card__title-label">🎒 내 정보</span>
                <span class="info-card__title-meta" title="<?php echo htmlspecialchars($game_name . ' | ' . (function_exists('냥_숫자콤마') ? 냥_숫자콤마($game_point) : game_point_str($game_point)) . '냥 | ' . (int)$game_streak . '연승', ENT_QUOTES, 'UTF-8'); ?>">
                    <span id="stHeadNick"><?php echo htmlspecialchars($game_name, ENT_QUOTES, 'UTF-8'); ?></span><span class="info-card__sep">|</span><span id="stHeadPoint"><?php echo htmlspecialchars(game_보유냥_표시($game_point), ENT_QUOTES, 'UTF-8'); ?></span><span class="unit-nyang">냥</span><span class="info-card__sep">|</span><span id="stHeadStreak"><?php echo (int)$game_streak; ?>연승</span>
                </span>
            </h3>
        </div>

        <div class="game-feedback">
            <div class="result-wrap" id="resultWrap">
                <div class="result result-slot is-idle" id="result" role="status" aria-live="polite"></div>
                <button type="button" class="btn-donate-float<?php echo ((int)$game_streak >= 3 && 홀짝_하드후원_금액문자열($game_hard_donate_offer) !== '0') ? ' is-visible' : ''; ?>" id="btnDonateFloat" title="3연승 이상 당첨금 10% 후원모금함 적립">후원<br>💝</button>
            </div>
        </div>

        <!-- 방 락 배너 -->
        <?php if ($game_room_lock) { ?>
            <div class="lock-banner" id="lockBanner">
                <?php if ($game_room_lock['type'] === 'pending') { ?>
                    ⏳ <?php echo htmlspecialchars($game_room_lock['nick'], ENT_QUOTES, 'UTF-8'); ?>님 진행 중 · 대기
                <?php } else { ?>
                    ⏳ <?php echo htmlspecialchars($game_room_lock['nick'], ENT_QUOTES, 'UTF-8'); ?>님 <?php echo (int)($game_room_lock['streak'] ?? 0); ?>연승 중 · 대기
                <?php } ?>
            </div>
        <?php } ?>

        <div class="play-stack">
        <!-- 진행 중 배팅 (pending_bet > 0 인 경우) -->
        <div class="pending-box<?php echo (홀짝_냥($game_pending_bet) === '0') ? ' panel-hidden' : ''; ?>" id="pendingBox">
            <div class="bet-info">
                배팅 <span id="pbAmount"><?php echo htmlspecialchars(game_금액_축약_짧게($game_pending_bet), ENT_QUOTES, 'UTF-8'); ?></span>냥
                · 승 <span class="mult">×<span id="pbWin"><?php echo (int)$game_win_mult; ?></span></span>
                · 패 <span class="mult">×<span id="pbLose"><?php echo (int)$game_lose_mult; ?></span></span>
            </div>
            <div class="streak-trail streak-trail--pending" id="pbStreakTrail" role="text" aria-label="이번 연승 픽 순서"></div>
            <div class="timer" id="pbTimer" data-left="<?php echo (int)$game_pending_left; ?>">
                남은 시간 <span id="pbTimeText">—</span>
            </div>
            <div class="pick-row">
                <button type="button" class="btn-pick hol" id="btnHol">홀</button>
                <button type="button" class="btn-pick jjak" id="btnJjak">짝</button>
            </div>
            <button type="button" class="btn btn-cancel" id="btnCancel">❎ 취소</button>
            <div class="hint" id="pendingHint">
                약 30분 무응답 시 타임아웃<br>
                서버는 도전 순간 이미 1(홀)·2(짝)·3(무) 중 하나로 결정되어 있습니다.
            </div>
        </div>

        <!-- 배팅 카드 (진행 중 아니고 방도 안 잠겼을 때) -->
        <div class="card<?php echo (홀짝_냥($game_pending_bet) !== '0' || $game_room_lock) ? ' panel-hidden' : ''; ?>" id="betCard">
            <h3 class="bet-card__title">
                <span class="bet-card__title-label">💰 금액 선택</span>
                <span class="badge pink<?= ((int)$game_streak > 0) ? '' : ' is-hidden' ?>" id="streakBadge">연승 ×<span id="streakBadgeMult"><?php echo (int)$game_win_mult; ?></span></span>
                <span class="streak-trail streak-trail--in-h3" id="streakTrail" role="text" aria-label="이번 연승 픽 순서"></span>
            </h3>
            <div class="mode-row panel-hidden" id="modeRow" aria-hidden="true" style="display:none">
                <!-- 이지/하드 선택 폐지 — 하드모드 고정 -->
            </div>
            <div class="cost-grid">
            <div class="cost-line">
                <span>최소 배팅</span>
                <b class="blue" id="bcMin" title="<?php echo htmlspecialchars(game_금액_축약($game_min_bet) . '냥', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(game_금액_축약_짧게($game_min_bet), ENT_QUOTES, 'UTF-8'); ?>냥</b>
            </div>
            <div class="cost-line">
                <span>최대 배팅</span>
                <b class="gold" id="bcMax" title="<?php echo htmlspecialchars(game_금액_축약($game_max_bet) . '냥', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(game_금액_축약_짧게($game_max_bet), ENT_QUOTES, 'UTF-8'); ?>냥</b>
            </div>
            <div class="cost-line">
                <span>승 배수 / 패 배수</span>
                <b><span class="gold">×<span id="bcWin"><?php echo (int)$game_win_mult; ?></span></span> / <span class="accent">×<span id="bcLose"><?php echo (int)$game_lose_mult; ?></span></span></b>
            </div>
            </div>
            <div class="bet-input-row">
                <input type="text" id="betAmount" readonly tabindex="-1" aria-readonly="true" placeholder="배팅 금액 (냥)" value="<?php echo htmlspecialchars($game_default_bet_display, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="quick-bets" id="quickBets"></div>
            <div class="pick-row pick-row--start">
                <button type="button" class="btn-pick hol" id="btnHolBet">홀</button>
                <button type="button" class="btn-pick jjak" id="btnJjakBet">짝</button>
            </div>
            <?php if ((int)$game_streak >= 2) { ?>
            <button type="button" class="btn btn-giveup" id="btnGiveup">🏳️ 연승 포기</button>
            <?php } ?>
        </div>
        </div><!-- /.play-stack -->

        <div class="card payback-card">
            <div class="payback-row">
                <span class="payback-label">💎 페이백 누적</span>
                <span class="payback-amount"><b id="stPayback"><?php
                  echo htmlspecialchars(game_금액_축약($game_payback_pool), ENT_QUOTES, 'UTF-8');
                ?></b><span class="unit-nyang">냥</span></span>
            </div>
            <p class="payback-hint">패배 시에만 적립 · 손실액 10%</p>
            <button type="button" class="btn btn-payback" id="btnPayback"<?php
              $pbZero = (홀짝_웹_페이백_금액문자열($game_payback_pool) === '0');
              echo $pbZero ? ' disabled' : '';
            ?>>페이백 받기</button>
        </div>

        <?php if (!empty($game_can_claim_donate_pool)) {
            $claim_pool_amt = 홀짝_하드후원_금액문자열($game_donate_pool);
            $claim_disabled = ($claim_pool_amt === '0');
            $claim_title = $claim_disabled
                ? '후원모금함 수령'
                : ('후원모금함 수령 · ' . game_금액_축약($claim_pool_amt));
        ?>
        <div class="footer-nav">
            <button type="button" class="btn-nav-claim-donate" id="btnClaimDonateFloat" title="<?php echo htmlspecialchars($claim_title, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $claim_disabled ? ' disabled' : ''; ?>">💝 후원 수령</button>
        </div>
        <?php } ?>
        <div class="btn-refresh-wrap">
            <button type="button" class="btn" id="btnRefresh">새로고침</button>
        </div>

        <?php } // end has code ?>
    </div>

    <?php if (!$game_need_code) { ?>
    <script>
    (function() {
        var BET_TIERS = <?php
            $tiers_js = array();
            foreach (홀짝_배팅_티어표() as $t) {
                $tiers_js[] = array(
                    'cap' => (string)(function_exists('냥_정수문자열') ? 냥_정수문자열($t['cap']) : $t['cap']),
                    'min' => (int)$t['min'],
                );
            }
            echo json_encode($tiers_js, JSON_UNESCAPED_UNICODE);
        ?>;
        var QUICK_PCTS = <?php echo json_encode(array_map('floatval', $game_quick_pcts ?: [1.0]), JSON_UNESCAPED_UNICODE); ?>;
        if (!QUICK_PCTS || !QUICK_PCTS.length) QUICK_PCTS = [1];
        var FIXED_BET = '';
        var CAN_ALLIN = <?php echo !empty($game_can_allin) ? 'true' : 'false'; ?>;
        var WEAPON_ENHANCE = <?php echo (int)$game_weapon_enhance; ?>;
        var BET_1GYEONG = '10000000000000000'; // 1경
        var BET_10JO = '10000000000000'; // 10조 — 키보드 4
        var BET_500GYEONG = '5000000000000000000'; // 500경 — 하드모드 강제 기준
        function nyangCmp(a, b) {
            a = String(a == null ? '0' : a).replace(/[^\d-]/g, '');
            b = String(b == null ? '0' : b).replace(/[^\d-]/g, '');
            var na = a.charAt(0) === '-';
            var nb = b.charAt(0) === '-';
            if (na !== nb) return na ? -1 : 1;
            a = a.replace(/^-/, '').replace(/^0+/, '') || '0';
            b = b.replace(/^-/, '').replace(/^0+/, '') || '0';
            if (a.length !== b.length) {
                var lenCmp = a.length < b.length ? -1 : 1;
                return na ? -lenCmp : lenCmp;
            }
            if (a === b) return 0;
            var digCmp = a < b ? -1 : 1;
            return na ? -digCmp : digCmp;
        }
        function nyangAbsStr(n) {
            return String(n == null ? '0' : n).replace(/[^\d]/g, '').replace(/^0+/, '') || '0';
        }
        /** 보유 × pct% 내림 — 문자열(BigInt). 소수 1자리(0.1%) 지원. Number 클램프 금지 */
        function nyangPctFloor(point, pct) {
            var pctNum = Number(pct);
            if (!(pctNum > 0)) return '0';
            var tenths = Math.round(pctNum * 10);
            if (tenths <= 0) return '0';
            var s = nyangAbsStr(point);
            if (s === '0') return '0';
            if (typeof BigInt !== 'undefined') {
                try {
                    return ((BigInt(s) * BigInt(tenths)) / 1000n).toString();
                } catch (e) {}
            }
            if (s.length <= 15) {
                return String(Math.floor((parseFloat(s) * tenths) / 1000) || 0);
            }
            return '0';
        }
        /** 보유 전액(올인) 문자열 */
        function nyangAllInStr(point) {
            return nyangAbsStr(point);
        }
        function nyangMaxStr(a, b) {
            return nyangCmp(a, b) >= 0 ? nyangAbsStr(a) : nyangAbsStr(b);
        }
        function nyangMinStr(a, b) {
            return nyangCmp(a, b) <= 0 ? nyangAbsStr(a) : nyangAbsStr(b);
        }
        function nyangClampStr(v, minV, maxV) {
            var s = nyangAbsStr(v);
            s = nyangMaxStr(s, minV);
            s = nyangMinStr(s, maxV);
            return s;
        }
        function nyangIsZero(v) {
            return nyangAbsStr(v) === '0';
        }
        var CODE = <?php echo json_encode($game_code, JSON_UNESCAPED_UNICODE); ?>;
        var BASE = location.pathname;
        var $ = function(id) { return document.getElementById(id); };
        /** URL에 ?perf=1 붙이면 화면 하단·콘솔에 action별 응답 소요(ms) — 모바일 확인용 */
        var PERF_LOG = /(?:^|[?&])perf=1(?:&|$)/.test(location.search);
        var PERF_LINES = [];
        var PERF_MAX = 8;

        function perfInitPanel() {
            if (!PERF_LOG || $('perfPanel')) return;
            var panel = document.createElement('div');
            panel.id = 'perfPanel';
            panel.innerHTML = '<div id="perfHead"><span>⏱ 응답시간</span><span id="perfHint">탭=접기</span></div><div id="perfBody"></div>';
            document.body.appendChild(panel);
            $('perfHead').addEventListener('click', function() {
                panel.classList.toggle('perf-panel--collapsed');
            });
            perfRender();
        }

        function perfRender() {
            var body = $('perfBody');
            if (!body) return;
            if (PERF_LINES.length === 0) {
                body.innerHTML = '<div class="perf-line"><span>요청 대기 중…</span></div>';
                return;
            }
            body.innerHTML = PERF_LINES.map(function(row) {
                if (row.err) {
                    return '<div class="perf-line"><span>' + row.action + '</span><span class="perf-ms perf-err">ERR</span></div>';
                }
                var cls = 'perf-ms';
                if (row.ms >= 1000) cls += ' very-slow';
                else if (row.ms >= 400) cls += ' slow';
                return '<div class="perf-line"><span>' + row.action + (row.tag ? ' ' + row.tag : '') + '</span><span class="' + cls + '">' + row.ms + 'ms</span></div>';
            }).join('');
        }

        function perfRecord(action, ms, ok, tag) {
            var row = { action: action || '?', ms: Math.round(ms), err: !ok, tag: tag || '' };
            PERF_LINES.unshift(row);
            if (PERF_LINES.length > PERF_MAX) PERF_LINES.length = PERF_MAX;
            console.log('[홀짝웹]', row.action, row.ms + 'ms' + (row.err ? ' ERR' : ''));
            perfRender();
        }

        perfInitPanel();
        /** status 폴링·정산 직후 refresh 겹칠 때 늦게 도착한 구 응답이 연승을 되돌리지 않도록 */
        var statusReqId = 0;
        /** applyActionState 직후 일정 시간 status 폴링이 연승·최고배팅 하한을 내리지 않도록 */
        var lastActionStateAt = 0;
        var ACTION_STATE_GUARD_MS = 10000;
        var setBetFromProgram = false;
        var betDigits = '0'; // 축약 표시와 별도로 실제 배팅 정수 문자열 유지

        var state = {
            point: <?php echo json_encode(game_point_str($game_point), JSON_UNESCAPED_UNICODE); ?>,
            newpoint: <?php echo json_encode($game_newpoint); ?>,
            streak: <?php echo (int)$game_streak; ?>,
            streak_max: <?php echo (int)$GAME_연승최대; ?>,
            streak_max_bet: <?php echo json_encode(홀짝_냥($game_streak_max_bet), JSON_UNESCAPED_UNICODE); ?>,
            pending_bet: <?php echo json_encode(홀짝_냥($game_pending_bet), JSON_UNESCAPED_UNICODE); ?>,
            pending_left: <?php echo (int)$game_pending_left; ?>,
            min_bet: <?php
                echo json_encode(
                    ((int)$game_streak > 0) ? 홀짝_냥($game_min_bet) : 홀짝_기본배팅_문자열($game_point),
                    JSON_UNESCAPED_UNICODE
                );
            ?>,
            max_bet: <?php echo json_encode(홀짝_최대배팅_문자열($game_point), JSON_UNESCAPED_UNICODE); ?>,
            win_mult: <?php echo (int)$game_win_mult; ?>,
            lose_mult: <?php echo (int)$game_lose_mult; ?>,
            room_lock: <?php echo $game_room_lock ? json_encode($game_room_lock, JSON_UNESCAPED_UNICODE) : 'null'; ?>,
            web_room_mode: <?php echo json_encode(game_web_is_multi_room() ? 'multi' : 'single', JSON_UNESCAPED_UNICODE); ?>,
            timeout_sec: <?php echo (int)$GAME_타임아웃초; ?>,
            streak_picks: <?php echo json_encode($game_streak_picks, JSON_UNESCAPED_UNICODE); ?>,
            odds_mode: <?php echo json_encode($game_odds_mode, JSON_UNESCAPED_UNICODE); ?>,
            hard_mode_forced: <?php echo $game_hard_mode_forced ? 'true' : 'false'; ?>,
            payback_pool: <?php echo json_encode((string)홀짝_웹_페이백_금액문자열($game_payback_pool), JSON_UNESCAPED_UNICODE); ?>,
            hard_donate_offer: <?php echo json_encode((string)홀짝_하드후원_금액문자열($game_hard_donate_offer), JSON_UNESCAPED_UNICODE); ?>,
            donate_pool: <?php echo json_encode((string)홀짝_하드후원_금액문자열($game_donate_pool), JSON_UNESCAPED_UNICODE); ?>,
            can_claim_donate_pool: <?php echo $game_can_claim_donate_pool ? 'true' : 'false'; ?>,
            giveup_offer: <?php echo ((int)$game_giveup_offer === 1) ? 1 : 0; ?>,
            hard_donate_force_show: false,
            profile_num: <?php echo (int)$game_profile_num; ?>,
            profile_hex: <?php echo json_encode($game_profile_hex, JSON_UNESCAPED_UNICODE); ?>,
            skin_theme: 'red',
            locked: false
        };
        try {
            state.skin_theme = (function() {
                var t = String(localStorage.getItem('odd_even_skin_theme') || 'red').toLowerCase();
                if (t === 'dark') t = 'black';
                var ok = ['red', 'black', 'white', 'pink', 'gold', 'blue', 'me'];
                return ok.indexOf(t) >= 0 ? t : 'red';
            })();
        } catch (e) {
            state.skin_theme = 'red';
        }

        function fmt(n) {
            var s = String(n == null ? '0' : n).replace(/[^\d-]/g, '');
            if (!s || s === '-') return '0';
            var neg = s.charAt(0) === '-';
            var d = s.replace(/^-/, '').replace(/^0+/, '') || '0';
            d = d.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            return neg ? '-' + d : d;
        }
        function fmtNewpoint(n) {
            n = parseFloat(n) || 0;
            if (Math.abs(n - Math.round(n)) < 0.05) return fmt(Math.round(n));
            return n.toLocaleString('ko-KR', { minimumFractionDigits: 0, maximumFractionDigits: 1 });
        }
        function effectiveQuickAmounts(point) {
            var min = resolveMinBet(point);
            var max = resolveMaxBet(point);
            var list = [];
            var seen = {};
            function push(v) {
                var s = nyangClampStr(v, min, max);
                if (nyangIsZero(s) || seen[s]) return;
                seen[s] = true;
                list.push(s);
            }
            (QUICK_PCTS || []).forEach(function(pct) {
                push(nyangPctFloor(point, pct));
            });
            return list;
        }
        var BET_PREF_KEY = 'odd_even_pref_bet';
        var BET_PREF_MODE_KEY = 'odd_even_pref_bet_mode'; // '1' | '3' | '5' | '100'
        var DEFAULT_QUICK_PCT = 1; // 기본 = 보유 1%
        function loadPrefBet() {
            try {
                var s = String(localStorage.getItem(BET_PREF_KEY) || '').replace(/[^\d]/g, '');
                return nyangAbsStr(s);
            } catch (e) {
                return '0';
            }
        }
        function savePrefBet(v) {
            var s = nyangAbsStr(v);
            if (nyangIsZero(s)) return;
            try {
                localStorage.setItem(BET_PREF_KEY, s);
            } catch (e) {}
        }
        function loadPrefQuickMode() {
            try {
                var m = String(localStorage.getItem(BET_PREF_MODE_KEY) || '').trim();
                if (m === 'fixed' || m === '0') return String(DEFAULT_QUICK_PCT);
                var pct = parseFloat(m);
                if (!isNaN(pct) && pct > 0) return String(pct);
            } catch (e) {}
            return String(DEFAULT_QUICK_PCT);
        }
        function savePrefQuickMode(mode) {
            try {
                var m = String(mode == null ? DEFAULT_QUICK_PCT : mode).trim();
                if (m === '' || m === '0' || m === 'fixed') m = String(DEFAULT_QUICK_PCT);
                localStorage.setItem(BET_PREF_MODE_KEY, m);
            } catch (e) {}
        }
        function savePrefQuick(mode, amount) {
            savePrefQuickMode(mode);
            if (amount != null) savePrefBet(amount);
        }
        /** 저장된 버튼 모드 → 현재 보유 기준 배팅액 */
        function resolvePrefBet() {
            normalizeBetLimits();
            var mode = loadPrefQuickMode();
            var amt = '0';
            var pct = parseFloat(mode) || 0;
            if (pct >= 100) {
                if (!CAN_ALLIN) {
                    amt = defaultPctBetAmount(DEFAULT_QUICK_PCT);
                } else {
                    amt = nyangAllInStr(state.point);
                }
            } else if (pct > 0) {
                var allowed = QUICK_PCTS || [];
                var usePct = pct;
                var hasPct = false;
                for (var i = 0; i < allowed.length; i++) {
                    if (Math.abs((Number(allowed[i]) || 0) - pct) < 0.0001) {
                        hasPct = true;
                        break;
                    }
                }
                if (!hasPct) {
                    for (var j = 0; j < allowed.length; j++) {
                        var a = Number(allowed[j]) || 0;
                        if (a > 0 && a < 100) {
                            usePct = a;
                            hasPct = true;
                            break;
                        }
                    }
                }
                if (!hasPct) {
                    usePct = DEFAULT_QUICK_PCT;
                }
                amt = defaultPctBetAmount(usePct);
            } else {
                amt = defaultPctBetAmount(DEFAULT_QUICK_PCT);
            }
            if (nyangIsZero(amt)) amt = defaultPctBetAmount(DEFAULT_QUICK_PCT);
            // 연승 하한: %·올인은 하한까지 올림(버튼 모드는 유지)
            if (state.streak > 0 && nyangCmp(amt, state.min_bet) < 0) {
                amt = state.min_bet;
            }
            if (nyangCmp(amt, state.max_bet) > 0) amt = state.max_bet;
            return amt;
        }
        function applyPrefBet() {
            var amt = resolvePrefBet();
            setBet(amt);
            savePrefBet(amt);
            return amt;
        }
        function preferredBetClamped() {
            return resolvePrefBet();
        }
        function isFixedQuickBet(amt) {
            return false;
        }
        function defaultPctBetAmount(pct) {
            normalizeBetLimits();
            var p = Number(pct) || DEFAULT_QUICK_PCT;
            if (p >= 100) {
                return nyangAllInStr(state.point);
            }
            var amt = nyangPctFloor(state.point, p);
            if (nyangIsZero(amt) && !nyangIsZero(state.point)) amt = '1';
            if (nyangCmp(amt, state.min_bet) < 0) amt = state.min_bet;
            if (nyangCmp(amt, state.max_bet) > 0) amt = state.max_bet;
            return amt;
        }
        function defaultFixedBetClamped() {
            return defaultPctBetAmount(DEFAULT_QUICK_PCT);
        }
        function defaultPctBetClamped() {
            return resolvePrefBet();
        }
        function resolveMinBet(point) {
            var one = nyangPctFloor(point, 1);
            if (nyangIsZero(one) && !nyangIsZero(point)) return '1';
            return one;
        }
        function resolveMaxBet(point) {
            return nyangMaxStr(resolveMinBet(point), nyangAllInStr(point));
        }
        /** 연승 최고가 현재 최대보다 크면 하한 무시 (보유 하락·숫자 깨짐) */
        function normalizeBetLimits() {
            var max = nyangAbsStr(state.max_bet);
            var min = nyangAbsStr(state.min_bet);
            var streakMax = nyangAbsStr(state.streak_max_bet);
            if (nyangCmp(streakMax, max) > 0) {
                state.streak_max_bet = '0';
                streakMax = '0';
            }
            var baseMin = resolveMinBet(state.point);
            min = nyangMaxStr(baseMin, streakMax);
            if (nyangCmp(min, max) > 0) min = max;
            state.min_bet = min;
            state.max_bet = max;
        }
        function getBetTier(point) {
            var p = nyangAbsStr(point);
            for (var i = 0; i < BET_TIERS.length; i++) {
                if (nyangCmp(p, BET_TIERS[i].cap) < 0) return BET_TIERS[i];
            }
            return BET_TIERS[BET_TIERS.length - 1];
        }
        /** 해·경·조·억·만·원 축약 (상세) */
        function fmtAmtShort(n) {
            var s = nyangAbsStr(n);
            if (s === '0') return '0';
            var parts = [];
            if (typeof BigInt !== 'undefined') {
                var v = BigInt(s);
                var G = 10000000000000000n, J = 1000000000000n, E = 100000000n, M = 10000n;
                var gyeong = v / G;
                v %= G;
                var jo = v / J;
                v %= J;
                var eok = v / E;
                v %= E;
                var man = v / M;
                v %= M;
                if (gyeong > 0n) {
                    var hae = gyeong / 10000n;
                    var gyeongRem = gyeong % 10000n;
                    if (hae > 0n) parts.push(hae.toString() + '해');
                    if (gyeongRem > 0n) parts.push(gyeongRem.toString() + '경');
                    if (jo > 0n) parts.push(jo.toString() + '조');
                    if (eok > 0n) parts.push(eok.toString() + '억');
                    if (man > 0n) parts.push(man.toString() + '만');
                    if (v > 0n) parts.push(v.toString());
                } else {
                    if (jo > 0n) parts.push(jo.toString() + '조');
                    if (eok > 0n) parts.push(eok.toString() + '억');
                    if (man > 0n) parts.push(man.toString() + '만');
                    if (v > 0n) parts.push(v.toString());
                }
                return parts.join('') || '0';
            }
            // BigInt 없는 구형: 문자열 길이로 근사 (+해)
            if (s.length > 20 || (s.length === 20 && s >= '10000000000000000000')) {
                parts.push(s.slice(0, s.length - 20).replace(/\B(?=(\d{3})+(?!\d))/g, ',') + '해');
                s = s.slice(-20).replace(/^0+/, '') || '0';
            }
            if (s.length > 16) {
                parts.push(s.slice(0, s.length - 16).replace(/\B(?=(\d{3})+(?!\d))/g, ',') + '경');
                s = s.slice(-16).replace(/^0+/, '') || '0';
            } else if (s.length === 16 && s >= '10000000000000000') {
                parts.push('1경');
                s = '0';
            }
            if (s.length > 12 || (s.length === 12 && s >= '1000000000000')) {
                parts.push((s.slice(0, s.length - 12) || '0') + '조');
                s = s.slice(-12).replace(/^0+/, '') || '0';
            }
            if (s.length >= 8) {
                var eok = (s.slice(0, s.length - 8) || '0').replace(/^0+/, '') || '0';
                if (eok !== '0') parts.push(eok + '억');
                s = s.slice(-8).replace(/^0+/, '') || '0';
            }
            if (s.length > 4) {
                var man = (s.slice(0, s.length - 4) || '0').replace(/^0+/, '') || '0';
                var won = s.slice(-4).replace(/^0+/, '') || '0';
                if (man !== '0') parts.push(man + '만');
                if (won !== '0') parts.push(won);
            } else if (s !== '0') {
                parts.push(s);
            }
            return parts.join('') || '0';
        }
        /** 화면용 짧은 축약 — 조·억·만·원까지 (1조에서 끊지 않음) */
        function fmtAmtCompact(n) {
            var s = nyangAbsStr(n);
            if (s === '0') return '0';
            if (typeof BigInt !== 'undefined') {
                try {
                    var v = BigInt(s);
                    var G = 10000000000000000n, J = 1000000000000n, E = 100000000n, M = 10000n;
                    if (v >= G) {
                        var g = v / G;
                        var hae = g / 10000n;
                        var gRem = g % 10000n;
                        var out = '';
                        if (hae > 0n) out += hae.toString() + '해';
                        if (gRem > 0n) out += gRem.toString() + '경';
                        var restG = v % G;
                        var joG = restG / J;
                        var eokG = (restG % J) / E;
                        if (joG > 0n) out += joG.toString() + '조';
                        if (eokG > 0n) out += eokG.toString() + '억';
                        return out || (g.toString() + '경');
                    }
                    if (v >= J) {
                        var jo = v / J;
                        var eok = (v % J) / E;
                        var man = ((v % J) % E) / M;
                        var outJ = jo.toString() + '조';
                        if (eok > 0n) outJ += eok.toString() + '억';
                        if (man > 0n) outJ += man.toString() + '만';
                        return outJ;
                    }
                    if (v >= E) {
                        var eok2 = v / E;
                        var man2 = (v % E) / M;
                        var won2 = (v % E) % M;
                        var outE = eok2.toString() + '억';
                        if (man2 > 0n) outE += man2.toString() + '만';
                        if (won2 > 0n) outE += won2.toString();
                        return outE;
                    }
                    if (v >= M) {
                        var man3 = v / M;
                        var won3 = v % M;
                        return won3 > 0n ? (man3.toString() + '만' + won3.toString()) : (man3.toString() + '만');
                    }
                    return v.toString();
                } catch (e) {}
            }
            return fmtAmtShort(n);
        }
        /** 내 정보 보유 게임냥 — 억 미만도 0으로 버리지 않음 */
        function fmtPointShort(n) {
            var raw = String(n == null ? '0' : n);
            var neg = raw.charAt(0) === '-';
            var abs = nyangAbsStr(raw);
            if (abs === '0') return neg ? '-0' : '0';
            var txt = fmtAmtShort(abs);
            return neg ? '-' + txt : txt;
        }
        function clampStreak(s) {
            var cap = Math.max(0, (state.streak_max || 5) - 1);
            s = Math.max(0, parseInt(s, 10) || 0);
            return s > cap ? cap : s;
        }
        function parseStreakField(v) {
            if (v === null || v === undefined || v === '') return null;
            var n = parseInt(v, 10);
            if (isNaN(n)) return null;
            return clampStreak(n);
        }
        /** 승·패·무 정산 JSON에서 DB에 저장된 다음 연승 수 추출 (streak_after 우선) */
        function pickStreakFromResponse(j) {
            if (!j) return null;
            if (j.result === 'win' && j.streak_completed) return 0;
            var stored = parseStreakField(j.streak_after);
            if (stored !== null) return stored;
            var s = parseStreakField(j.streak);
            if (s !== null) return s;
            if (j.result === 'win') {
                var won = parseStreakField(j.streak_won);
                if (won !== null) return won;
            }
            return null;
        }
        function multForStreak(streak, mode) {
            var arr = [3, 5, 7, 9, 11];
            var s = Math.max(0, parseInt(streak, 10) || 0);
            if (s > 4) s = 4;
            return { win: arr[s], lose: arr[s] };
        }
        function applyStreakMultFromState() {
            var m = multForStreak(state.streak, state.odds_mode);
            state.win_mult = m.win;
            state.lose_mult = m.lose;
        }
        function fmtSec(s) {
            s = Math.max(0, parseInt(s || 0, 10));
            var m = Math.floor(s / 60);
            var r = s % 60;
            return m + '분 ' + (r < 10 ? '0' : '') + r + '초';
        }

        function escHtml(s) {
            var d = document.createElement('div');
            d.textContent = s == null ? '' : String(s);
            return d.innerHTML;
        }

        function renderOutcomeHtml(j) {
            if (!j) return null;
            var r = j.result;
            if (r === 'win') {
                var picks = escHtml(j.user_pick || '') + ' · ' + escHtml(j.system_pick || '');
                var streak = (j.streak_won != null && j.streak_won !== '') ? (parseInt(j.streak_won, 10) + '연승') : '';
                var amtFull = (j.win_amount != null) ? ('+' + fmtAmtShort(j.win_amount)) : '';
                var amt = (j.win_amount != null) ? ('+' + fmtAmtCompact(j.win_amount)) : '';
                var hardBonusHtml;
                if (parseInt(j.hard_bonus_mult, 10) > 1 || j.hard_x2) {
                    var hm = parseInt(j.hard_bonus_mult, 10) || 2;
                    hardBonusHtml = '<div class="outcome-hard-bonus" aria-label="보너스 ' + hm + '배">'
                        + '🎊 <span class="outcome-hard-mult">×' + hm + '</span> 보너스!'
                        + '</div>';
                } else {
                    hardBonusHtml = '<div class="outcome-hard-bonus is-empty" aria-hidden="true">'
                        + '🎊 <span class="outcome-hard-mult">×2</span> 보너스!'
                        + '</div>';
                }
                var subParts = [];
                if (j.streak_completed) {
                    subParts.push('🏆 ' + (state.streak_max || 5) + '연승 완주! 다음 판부터 첫 도전');
                }
                if (nyangCmp(j.win_fee || 0, 0) > 0) {
                    var feePct = parseInt(j.win_fee_pct, 10) || 10;
                    subParts.push('연승수수료 ' + feePct + '% -' + fmtAmtCompact(j.win_fee));
                }
                var subHtml = subParts.length
                    ? '<div class="outcome-sub">' + subParts.map(escHtml).join('<br>') + '</div>'
                    : '<div class="outcome-sub is-empty" aria-hidden="true">&nbsp;</div>';
                return '<div class="outcome-head">✅ 승리!</div>'
                    + hardBonusHtml
                    + '<div class="outcome-amount win"><span class="outcome-amount-num" title="' + escHtml(amtFull) + '">' + escHtml(amt || '—') + '</span></div>'
                    + '<div class="outcome-picks"><span>' + picks + '</span>'
                    + (streak ? '<span class="outcome-streak">🔥 ' + escHtml(streak) + '</span>' : '') + '</div>'
                    + subHtml;
            }
            if (r === 'lose') {
                var vs = escHtml(j.user_pick || '') + ' ≠ ' + escHtml(j.system_pick || '');
                var lossFull = (j.loss != null) ? ('-' + fmtAmtShort(j.loss)) : '';
                var loss = (j.loss != null) ? ('-' + fmtAmtCompact(j.loss)) : '';
                var mult = j.played_lose_mult ? ' (×' + parseInt(j.played_lose_mult, 10) + ')' : '';
                var hardLoseHtml = j.hard_lose_x2
                    ? '<div class="outcome-hard-bonus is-lose" aria-label="패배 2배 차감">💀 <span class="outcome-hard-mult">×2</span> 차감</div>'
                    : '<div class="outcome-hard-bonus is-empty" aria-hidden="true">💀 <span class="outcome-hard-mult">×2</span> 차감</div>';
                return '<div class="outcome-head">❌ 패배</div>'
                    + hardLoseHtml
                    + '<div class="outcome-amount lose"><span class="outcome-amount-num" title="' + escHtml(lossFull + mult) + '">' + escHtml(loss + mult || '—') + '</span></div>'
                    + '<div class="outcome-picks"><span>' + vs + '</span></div>'
                    + '<div class="outcome-sub">연승 리셋</div>';
            }
            if (r === 'push') {
                var detail = String(j.data || '').replace(/^🤝\s*무승부!\s*/u, '').replace(/\s*·\s*연승 리셋\s*$/u, '');
                var pushPicks = escHtml(j.user_pick || '') + ' · ' + escHtml(j.system_pick || '무(3)');
                return '<div class="outcome-head">🤝 무승부</div>'
                    + '<div class="outcome-hard-bonus is-empty" aria-hidden="true">🎊 <span class="outcome-hard-mult">×2</span> 보너스!</div>'
                    + '<div class="outcome-amount push">' + escHtml(detail || '환급') + '</div>'
                    + '<div class="outcome-picks"><span>' + pushPicks + '</span></div>'
                    + '<div class="outcome-sub">연승 리셋</div>';
            }
            return null;
        }

        function setResultIdle() {
            var r = $('result');
            if (!r) return;
            r.className = 'result result-slot is-idle';
            r.textContent = '';
            r.innerHTML = '';
        }

        function showResult(text, kind) {
            var r = $('result');
            if (!r) return;
            var cls = (kind === 'info') ? 'is-info' : 'is-busy';
            r.className = 'result result-slot ' + cls;
            r.textContent = text;
        }

        function hideResult() {
            setResultIdle();
        }

        /** pick 응답 중 승·패·무(연승 리셋)일 때만 결과창 표시 (타임아웃·기타는 대기 상태 유지) */
        function showPickOutcomeBanner(j) {
            if (!j || !j.ok) {
                setResultIdle();
                return;
            }
            var r = j.result;
            if (r === 'win' || r === 'lose' || r === 'push') {
                if (r === 'win') vibrateWin();
                var kind = 'info';
                if (r === 'win') kind = 'success';
                else if (r === 'lose') kind = 'fail';
                else if (r === 'push') kind = 'push';
                var html = renderOutcomeHtml(j);
                var el = $('result');
                if (html && el) {
                    el.className = 'result result-slot outcome ' + kind;
                    el.innerHTML = html;
                } else {
                    showResult(j.data, kind);
                }
            } else {
                setResultIdle();
            }
        }

        function hasHardDonateOffer() {
            var streak = parseInt(state.streak, 10) || 0;
            var force = !!state.hard_donate_force_show;
            if (!force && streak < 3) return false;
            return nyangCmp(state.hard_donate_offer || 0, 0) > 0;
        }

        function hasDonatePool() {
            return nyangCmp(state.donate_pool || 0, 0) > 0;
        }

        function renderDonateFloat() {
            var show = hasHardDonateOffer();
            var btn = $('btnDonateFloat');
            if (btn) {
                btn.classList.toggle('is-visible', show);
                btn.disabled = !show || state.pending_bet > 0 || state.locked;
            }
            var claimBtn = $('btnClaimDonateFloat');
            if (claimBtn) {
                var claimReady = !!state.can_claim_donate_pool && hasDonatePool();
                claimBtn.disabled = !claimReady || state.pending_bet > 0 || state.locked;
                if (claimReady) {
                    claimBtn.textContent = '💝 후원 수령 · ' + fmtPointShort(state.donate_pool);
                    claimBtn.title = '후원모금함 수령 · ' + fmtAmtShort(state.donate_pool);
                } else {
                    claimBtn.textContent = '💝 후원 수령';
                    claimBtn.title = '후원모금함 수령';
                }
            }
        }

        function showDonateOutcomeBanner(j) {
            var el = $('result');
            if (!el) return;
            var msg = (j && j.data) ? String(j.data) : '후원되었습니다👍🏻';
            el.className = 'result result-slot outcome success';
            el.innerHTML = '<div class="outcome-head">💝 후원 완료</div>'
                + '<div class="outcome-amount win" style="font-size:0.72rem;line-height:1.35;padding:2px 4px;">'
                + escHtml(msg) + '</div>';
        }

        function doDonateHard5() {
            if (!hasHardDonateOffer()) {
                window.alert('후원할 수 있는 3연승 기록이 없어요.');
                return;
            }
            if (state.pending_bet > 0) {
                window.alert('⏳ 진행 중인 판이 있을 때는 후원할 수 없어요.');
                return;
            }
            var btn = $('btnDonateFloat');
            if (btn) btn.disabled = true;
            showBusy('후원 중…');
            ajax({ action: 'donate_hard5' }, function(j) {
                if (j && j.hard_donate_offer != null) {
                    state.hard_donate_offer = String(j.hard_donate_offer);
                } else if (j && j.ok) {
                    state.hard_donate_offer = '0';
                }
                state.hard_donate_force_show = false;
                if (j && j.point != null && j.point !== '') {
                    state.point = String(j.point);
                    state.max_bet = resolveMaxBet(j.point);
                }
                renderHeadPoint();
                renderDonateFloat();
                updateQuickButtons();
                if (!j || !j.ok) {
                    showResult((j && j.data) || '후원 실패', 'fail');
                    if (btn) btn.disabled = !hasHardDonateOffer();
                    return;
                }
                showDonateOutcomeBanner(j);
            });
        }

        function doClaimDonatePool() {
            if (!state.can_claim_donate_pool) {
                window.alert('후원모금함 수령은 민호만 가능해요.');
                return;
            }
            if (!hasDonatePool()) {
                window.alert('수령할 후원모금함이 없어요.');
                return;
            }
            if (state.pending_bet > 0) {
                window.alert('⏳ 진행 중인 판이 있을 때는 수령할 수 없어요.');
                return;
            }
            if (!window.confirm('후원모금함 ' + fmtAmtShort(state.donate_pool) + '을 게임냥으로 수령할까요?')) {
                return;
            }
            var btn = $('btnClaimDonateFloat');
            if (btn) btn.disabled = true;
            showBusy('모금함 수령 중…');
            ajax({ action: 'claim_donate_pool' }, function(j) {
                if (j && j.donate_pool != null) state.donate_pool = String(j.donate_pool);
                if (typeof j.can_claim_donate_pool === 'boolean') state.can_claim_donate_pool = j.can_claim_donate_pool;
                if (j && j.point != null && j.point !== '') {
                    state.point = String(j.point);
                    state.max_bet = resolveMaxBet(j.point);
                }
                renderHeadPoint();
                renderDonateFloat();
                updateQuickButtons();
                if (!j || !j.ok) {
                    showResult((j && j.data) || '수령 실패', 'fail');
                    if (btn) btn.disabled = !(state.can_claim_donate_pool && hasDonatePool());
                    return;
                }
                showDonateOutcomeBanner(j);
            });
        }

        function showBusy(msg) {
            var r = $('result');
            if (!r) return;
            r.className = 'result result-slot is-busy';
            r.innerHTML = '<span class="result-busy-dice" aria-hidden="true">🎲</span>' + escHtml(msg || '처리 중…');
        }

        function ajax(params, onDone) {
            params.code = CODE;
            var body = Object.keys(params).map(function(k) {
                return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
            }).join('&');
            var t0 = PERF_LOG ? performance.now() : 0;
            var actionLabel = params.action || '';
            if (params.full_sync) actionLabel += '+sync';
            fetch(BASE, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body,
                credentials: 'same-origin'
            }).then(function(r) { return r.json(); })
              .then(function(j) {
                  if (PERF_LOG) {
                      perfRecord(actionLabel, performance.now() - t0, true);
                  }
                  onDone(j);
              })
              .catch(function() {
                  if (PERF_LOG) {
                      perfRecord(actionLabel, performance.now() - t0, false);
                  }
                  onDone({ ok: false, data: '통신 오류' });
              });
        }

        /** bet_pick·pick 정산 응답으로 연승·배수·픽 궤적 즉시 반영 (status 대기 전) */
        function applyActionState(j) {
            if (!j || !j.ok) return;
            statusReqId++;
            lastActionStateAt = Date.now();
            var prevStreak = state.streak;
            var playedBet = parseBet();
            if (state.pending_bet > 0) {
                playedBet = state.pending_bet;
            }
            if (j.point != null && j.point !== '') state.point = String(j.point);
            if (j.max_bet != null && j.max_bet !== '') {
                state.max_bet = nyangAbsStr(j.max_bet);
            } else if (j.point != null && j.point !== '') {
                state.max_bet = resolveMaxBet(j.point);
            }
            var nextStreak = pickStreakFromResponse(j);
            if (nextStreak !== null) {
                state.streak = nextStreak;
            }
            if (j.streak_max_bet != null && j.streak_max_bet !== '') state.streak_max_bet = nyangAbsStr(j.streak_max_bet);
            if (Array.isArray(j.streak_picks)) {
                state.streak_picks = j.streak_picks.slice(0, clampStreak(state.streak));
            } else if (j.result === 'win' && j.user_pick) {
                if (state.streak > 0) {
                    var trail = (state.streak_picks || []).slice();
                    trail.push(j.user_pick);
                    state.streak_picks = trail.slice(-clampStreak(state.streak));
                } else {
                    state.streak_picks = [];
                }
            } else if (j.result === 'lose' || j.result === 'push') {
                state.streak_picks = [];
                state.hard_donate_offer = '0';
                state.hard_donate_force_show = false;
                state.giveup_offer = 0;
            } else if (j.result === 'give_up') {
                state.hard_donate_offer = '0';
                state.hard_donate_force_show = false;
                state.giveup_offer = 0;
            }
            if (j.min_bet != null && j.min_bet !== '') state.min_bet = nyangAbsStr(j.min_bet);
            if (typeof j.win_mult === 'number') state.win_mult = j.win_mult;
            if (typeof j.lose_mult === 'number') state.lose_mult = j.lose_mult;
            if (typeof j.payback_pool === 'number' || (typeof j.payback_pool === 'string' && j.payback_pool !== '')) {
                state.payback_pool = String(j.payback_pool);
            }
            if (j.hard_donate_offer != null && j.hard_donate_offer !== '') {
                state.hard_donate_offer = String(j.hard_donate_offer);
            }
            if (j.hard_donate_force_show) {
                state.hard_donate_force_show = true;
            } else if (j.result === 'win' && !j.streak_completed) {
                state.hard_donate_force_show = false;
            }
            if (j.giveup_offer != null && j.giveup_offer !== '') {
                state.giveup_offer = (parseInt(j.giveup_offer, 10) === 1) ? 1 : 0;
            } else if (j.result === 'win' && j.streak_completed) {
                state.giveup_offer = 0;
            }
            if (j.donate_pool != null && j.donate_pool !== '') {
                state.donate_pool = String(j.donate_pool);
            }
            if (typeof j.can_claim_donate_pool === 'boolean') {
                state.can_claim_donate_pool = j.can_claim_donate_pool;
            }
            if (j.odds_mode === 'easy' || j.odds_mode === 'hard') state.odds_mode = 'hard';
            if (typeof j.hard_mode_forced === 'boolean') state.hard_mode_forced = j.hard_mode_forced;
            applyStreakMultFromState();
            normalizeBetLimits();
            state.pending_bet = '0';
            state.pending_left = 0;
            // 승·패·완주·연승끊김 모두 저장한 퀵 버튼 모드로 복원
            applyPrefBet();
            enforceHardModeIfNeeded();
            renderAll();
        }

        function mergeStatusStreakFields(j) {
            var incomingStreak = clampStreak(j.streak);
            var incomingMaxBet = nyangAbsStr(j.streak_max_bet);
            var incomingMinBet = nyangAbsStr(j.min_bet);
            var guard = shouldGuardActionState();
            var allowDecrease = !guard || incomingStreak === 0;
            var blockedDowngrade = guard && !allowDecrease && incomingStreak < state.streak;
            if (allowDecrease || incomingStreak >= state.streak) {
                state.streak = incomingStreak;
            }
            if (allowDecrease || nyangCmp(incomingMaxBet, state.streak_max_bet) >= 0) {
                state.streak_max_bet = incomingMaxBet;
            }
            if (allowDecrease || nyangCmp(incomingMinBet, state.min_bet) >= 0) {
                state.min_bet = incomingMinBet;
            }
            normalizeBetLimits();
            return !blockedDowngrade;
        }

        function refreshStatus(onDone, fullSync) {
            var params = { action: 'status' };
            if (fullSync) params.full_sync = 1;
            var reqId = ++statusReqId;
            ajax(params, function(j) {
                if (reqId !== statusReqId) return;
                if (!j.ok) { hideResult(); return; }
                var prevStreak = state.streak;
                state.point = (j.point != null && j.point !== '') ? String(j.point) : state.point;
                if (typeof j.newpoint === 'number') state.newpoint = j.newpoint;
                var streakAccepted = mergeStatusStreakFields(j);
                state.streak_picks = Array.isArray(j.streak_picks) ? j.streak_picks.slice(0, clampStreak(state.streak) || 0) : [];
                state.pending_bet = nyangAbsStr(j.pending_bet);
                state.pending_left = j.pending_left;
                state.max_bet = (j.max_bet != null && j.max_bet !== '')
                    ? nyangAbsStr(j.max_bet)
                    : resolveMaxBet(j.point);
                if (streakAccepted || !shouldGuardActionState()) {
                    state.win_mult = j.win_mult;
                    state.lose_mult = j.lose_mult;
                }
                applyStreakMultFromState();
                state.room_lock = j.room_lock;
                if (j.web_room_mode) state.web_room_mode = j.web_room_mode;
                if (j.odds_mode === 'easy' || j.odds_mode === 'hard') state.odds_mode = 'hard';
                if (typeof j.hard_mode_forced === 'boolean') state.hard_mode_forced = j.hard_mode_forced;
                if (typeof j.payback_pool === 'number' || (typeof j.payback_pool === 'string' && j.payback_pool !== '')) {
                    state.payback_pool = String(j.payback_pool);
                }
                if (j.hard_donate_offer != null && j.hard_donate_offer !== '') {
                    state.hard_donate_offer = String(j.hard_donate_offer);
                }
                // status는 현재 연승 기준: 3연승 미만이면 강제 표시 해제
                if ((parseInt(state.streak, 10) || 0) < 3) {
                    state.hard_donate_force_show = false;
                }
                if (j.donate_pool != null && j.donate_pool !== '') {
                    state.donate_pool = String(j.donate_pool);
                }
                if (typeof j.can_claim_donate_pool === 'boolean') {
                    state.can_claim_donate_pool = j.can_claim_donate_pool;
                }
                if (j.giveup_offer != null && j.giveup_offer !== '') {
                    state.giveup_offer = (parseInt(j.giveup_offer, 10) === 1) ? 1 : 0;
                } else if ((parseInt(state.streak, 10) || 0) < 1) {
                    state.giveup_offer = 0;
                }
                var quickChanged = false;
                if (Array.isArray(j.quick_pcts)) {
                    var nextPcts = j.quick_pcts.map(function(x) { return Number(x) || 0; }).filter(function(x) { return x > 0; });
                    if (JSON.stringify(nextPcts) !== JSON.stringify(QUICK_PCTS || [])) {
                        QUICK_PCTS = nextPcts;
                        quickChanged = true;
                    }
                }
                if (typeof j.can_allin === 'boolean' && j.can_allin !== CAN_ALLIN) {
                    CAN_ALLIN = j.can_allin;
                    quickChanged = true;
                }
                FIXED_BET = '';
                if (typeof j.weapon_enhance === 'number') {
                    WEAPON_ENHANCE = j.weapon_enhance;
                }
                if (prevStreak > 0 && state.streak === 0 && state.pending_bet <= 0) {
                    applyPrefBet();
                } else {
                    syncBetInputToMin();
                }
                enforceHardModeIfNeeded();
                if (quickChanged) {
                    renderQuickBets(true);
                }
                renderAll();
                if (typeof onDone === 'function') onDone();
            });
        }

        function renderStreak() {
            var el = $('stHeadStreak');
            if (el) el.textContent = state.streak + '연승';
        }

        function renderStreakPicks() {
            var picks = state.streak_picks || [];
            var html = '';
            for (var i = 0; i < picks.length; i++) {
                var p = picks[i];
                var c = (p === '홀') ? 'hol-t' : 'jjak-t';
                html += '<span class="st-pick ' + c + '">' + p + '</span>';
            }
            var tr = $('streakTrail');
            var pbtr = $('pbStreakTrail');
            if (tr) {
                tr.className = 'streak-trail streak-trail--in-h3' + (picks.length ? ' show' : '');
                tr.innerHTML = html;
            }
            if (pbtr) {
                pbtr.className = 'streak-trail streak-trail--pending' + (picks.length ? ' show' : '');
                pbtr.innerHTML = html;
            }
        }

        function renderLock() {
            var banner = $('lockBanner');
            if (state.web_room_mode === 'multi') {
                if (banner) banner.style.display = 'none';
                return;
            }
            if (state.room_lock && state.room_lock.nick) {
                if (!banner) {
                    banner = document.createElement('div');
                    banner.id = 'lockBanner';
                    banner.className = 'lock-banner';
                    var pb = $('pendingBox');
                    if (pb && pb.parentNode) pb.parentNode.insertBefore(banner, pb);
                    else $('betCard').parentNode.appendChild(banner);
                }
                if (state.room_lock.type === 'pending') {
                    banner.textContent = '⏳ ' + state.room_lock.nick + '님 진행 중 · 대기';
                } else {
                    banner.textContent = '⏳ ' + state.room_lock.nick + '님 ' + (state.room_lock.streak || 0) + '연승 중 · 대기';
                }
                banner.style.display = '';
            } else if (banner) {
                banner.style.display = 'none';
            }
        }

        function renderPending() {
            var pb = $('pendingBox');
            var bc = $('betCard');
            if (state.pending_bet > 0) {
                pb.classList.remove('panel-hidden');
                bc.classList.add('panel-hidden');
                $('pbAmount').textContent = fmt(state.pending_bet);
                $('pbWin').textContent = state.win_mult;
                $('pbLose').textContent = state.lose_mult;
                var t = $('pbTimer');
                t.setAttribute('data-left', state.pending_left);
                $('pbTimeText').textContent = fmtSec(state.pending_left);
                var disable = state.locked || state.pending_left <= 0;
                $('btnHol').disabled = disable;
                $('btnJjak').disabled = disable;
                $('btnCancel').disabled = state.locked;
            } else {
                pb.classList.add('panel-hidden');
                if (state.room_lock && state.room_lock.nick) {
                    bc.classList.add('panel-hidden');
                } else {
                    bc.classList.remove('panel-hidden');
                }
            }
        }

        function cancelHintText(mode) {
            return '❎ 취소 (전액 환급 · 수수료 없음)';
        }

        function timeoutHintText(mode) {
            return '약 30분 무응답 시 타임아웃 (전액 환급 · 수수료 없음)';
        }

        function hardModeForced() {
            return true;
        }

        function syncHardModeForcedFlag() {
            state.hard_mode_forced = true;
        }

        function enforceHardModeIfNeeded() {
            state.odds_mode = 'hard';
        }

        function giveupHintText(mode) {
            return '🏳️ 연승 포기 (최고배팅 10% · 금고50%·로또50%)';
        }

        var THEME_KEY = 'odd_even_skin_theme';
        var THEME_COLORS = {
            red: '#120808',
            black: '#050505',
            white: '#f4f6fb',
            pink: '#2a1020',
            gold: '#1a1408',
            blue: '#071525',
            me: <?php echo json_encode($game_profile_hex, JSON_UNESCAPED_UNICODE); ?>
        };
        var THEME_LIST = ['red', 'black', 'white', 'pink', 'gold', 'blue', 'me'];

        function normalizeSkinTheme(t) {
            t = String(t || '').toLowerCase();
            if (t === 'dark') t = 'black';
            if (THEME_LIST.indexOf(t) >= 0) return t;
            return 'red';
        }

        function loadSkinTheme() {
            try {
                return normalizeSkinTheme(localStorage.getItem(THEME_KEY));
            } catch (e) {
                return 'red';
            }
        }

        function saveSkinTheme(t) {
            try {
                localStorage.setItem(THEME_KEY, normalizeSkinTheme(t));
            } catch (e) {}
        }

        function hexToRgb(hex) {
            hex = String(hex || '').replace('#', '').trim();
            if (hex.length === 3) {
                hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
            }
            if (!/^[0-9a-fA-F]{6}$/.test(hex)) return { r: 250, g: 225, b: 0 };
            return {
                r: parseInt(hex.slice(0, 2), 16),
                g: parseInt(hex.slice(2, 4), 16),
                b: parseInt(hex.slice(4, 6), 16)
            };
        }

        function rgbToHex(r, g, b) {
            function c(n) {
                n = Math.max(0, Math.min(255, Math.round(n)));
                var s = n.toString(16);
                return s.length === 1 ? '0' + s : s;
            }
            return '#' + c(r) + c(g) + c(b);
        }

        function mixRgb(a, b, t) {
            return {
                r: a.r + (b.r - a.r) * t,
                g: a.g + (b.g - a.g) * t,
                b: a.b + (b.b - a.b) * t
            };
        }

        function relativeLuma(rgb) {
            return (0.2126 * rgb.r + 0.7152 * rgb.g + 0.0722 * rgb.b) / 255;
        }

        function applyProfileThemeVars(hex) {
            var base = hexToRgb(hex || state.profile_hex || '#FAE100');
            var black = { r: 0, g: 0, b: 0 };
            var white = { r: 255, g: 255, b: 255 };
            var luma = relativeLuma(base);
            var darkBase = mixRgb(base, black, luma > 0.72 ? 0.78 : (luma > 0.45 ? 0.62 : 0.45));
            var card = mixRgb(base, black, luma > 0.72 ? 0.68 : 0.52);
            var accent = luma > 0.78 ? mixRgb(base, black, 0.28) : mixRgb(base, white, 0.18);
            var text = luma > 0.55 ? mixRgb(white, base, 0.08) : mixRgb(white, base, 0.12);
            var muted = mixRgb(text, base, 0.35);
            var btn1 = mixRgb(base, black, 0.25);
            var btn1d = mixRgb(base, black, 0.45);
            var btn2 = mixRgb(base, black, 0.12);
            var btn2d = mixRgb(base, black, 0.38);
            var root = document.body.style;
            root.setProperty('--me-hex', hex || state.profile_hex || '#FAE100');
            root.setProperty('--me-bg', rgbToHex(darkBase.r, darkBase.g, darkBase.b));
            root.setProperty('--me-card', rgbToHex(card.r, card.g, card.b));
            root.setProperty('--me-accent', rgbToHex(accent.r, accent.g, accent.b));
            root.setProperty('--me-gold', rgbToHex(mixRgb(accent, white, 0.25).r, mixRgb(accent, white, 0.25).g, mixRgb(accent, white, 0.25).b));
            root.setProperty('--me-text', rgbToHex(text.r, text.g, text.b));
            root.setProperty('--me-muted', rgbToHex(muted.r, muted.g, muted.b));
            root.setProperty('--me-glow', 'rgba(' + Math.round(base.r) + ',' + Math.round(base.g) + ',' + Math.round(base.b) + ',0.4)');
            root.setProperty('--me-ring', 'rgba(' + Math.round(accent.r) + ',' + Math.round(accent.g) + ',' + Math.round(accent.b) + ',0.35)');
            root.setProperty('--me-card-bg', 'rgba(' + Math.round(card.r) + ',' + Math.round(card.g) + ',' + Math.round(card.b) + ',0.55)');
            root.setProperty('--me-spot1', 'rgba(' + Math.round(base.r) + ',' + Math.round(base.g) + ',' + Math.round(base.b) + ',0.28)');
            root.setProperty('--me-spot2', 'rgba(' + Math.round(accent.r) + ',' + Math.round(accent.g) + ',' + Math.round(accent.b) + ',0.18)');
            root.setProperty('--me-btn1', rgbToHex(btn1.r, btn1.g, btn1.b));
            root.setProperty('--me-btn1-d', rgbToHex(btn1d.r, btn1d.g, btn1d.b));
            root.setProperty('--me-btn2', rgbToHex(btn2.r, btn2.g, btn2.b));
            root.setProperty('--me-btn2-d', rgbToHex(btn2d.r, btn2d.g, btn2d.b));
            THEME_COLORS.me = rgbToHex(darkBase.r, darkBase.g, darkBase.b);
            var sw = document.querySelector('.theme-opt__swatch.sw-me');
            if (sw) sw.style.setProperty('--me-hex', hex || state.profile_hex || '#FAE100');
        }

        function applySkinTheme(theme) {
            theme = normalizeSkinTheme(theme || state.skin_theme || 'red');
            state.skin_theme = theme;
            if (theme === 'me') {
                applyProfileThemeVars(state.profile_hex);
            }
            document.body.classList.remove('theme-red', 'theme-black', 'theme-white', 'theme-pink', 'theme-gold', 'theme-blue', 'theme-me', 'mode-easy', 'mode-hard');
            document.body.classList.add('theme-' + theme);
            var meta = document.querySelector('meta[name="theme-color"]');
            if (meta) meta.content = THEME_COLORS[theme] || THEME_COLORS.red;
            document.querySelectorAll('.theme-opt').forEach(function(btn) {
                btn.classList.toggle('is-active', btn.getAttribute('data-theme') === theme);
            });
        }

        function openThemeLayer() {
            var layer = $('themeLayer');
            if (!layer) return;
            layer.classList.add('is-open');
            layer.setAttribute('aria-hidden', 'false');
        }

        function closeThemeLayer() {
            var layer = $('themeLayer');
            if (!layer) return;
            layer.classList.remove('is-open');
            layer.setAttribute('aria-hidden', 'true');
        }

        function updateModeUI() {
            syncHardModeForcedFlag();
            applySkinTheme(state.skin_theme);
            var mode = 'hard';
            state.odds_mode = 'hard';
            applyStreakMultFromState();
            var bcWin = $('bcWin');
            if (bcWin) bcWin.textContent = state.win_mult;
            var bcLose = $('bcLose');
            if (bcLose) bcLose.textContent = state.lose_mult;
            var btnCancel = $('btnCancel');
            if (btnCancel) btnCancel.textContent = cancelHintText(mode);
            var pendingHint = $('pendingHint');
            if (pendingHint) {
                pendingHint.innerHTML = timeoutHintText(mode) + '<br>서버는 도전 순간 이미 1(홀)·2(짝)·3(무) 중 하나로 결정되어 있습니다.';
            }
            renderQuickBets();
        }

        function renderPayback() {
            var el = $('stPayback');
            if (el) el.textContent = fmtAmtShort(state.payback_pool || 0);
            var btn = $('btnPayback');
            if (btn) {
                var canClaim = nyangCmp(state.payback_pool || 0, 0) > 0 && nyangCmp(state.pending_bet || 0, 0) <= 0 && !state.locked;
                btn.disabled = !canClaim;
            }
        }

        function renderBetCard() {
            normalizeBetLimits();
            var stHp = $('stHeadPoint');
            if (stHp) stHp.textContent = fmtPointShort(state.point);
            var stMeta = document.querySelector('.info-card__title-meta');
            var stNn = $('stHeadNick');
            if (stMeta && stNn) {
                stMeta.title = stNn.textContent + ' | ' + fmt(state.point) + '냥 | ' + state.streak + '연승';
            }
            var bcMin = $('bcMin');
            if (bcMin) {
                bcMin.textContent = fmtAmtCompact(state.min_bet) + '냥';
                bcMin.title = fmtAmtShort(state.min_bet) + '냥';
            }
            var bcMax = $('bcMax');
            if (bcMax) {
                bcMax.textContent = fmtAmtCompact(state.max_bet) + '냥';
                bcMax.title = fmtAmtShort(state.max_bet) + '냥';
            }
            var bcWin = $('bcWin');
            if (bcWin) bcWin.textContent = state.win_mult;
            var bcLose = $('bcLose');
            if (bcLose) bcLose.textContent = state.lose_mult;
            var streakBadge = $('streakBadge');
            var streakBadgeMult = $('streakBadgeMult');
            if (streakBadge) {
                if (state.streak > 0) {
                    streakBadge.classList.remove('is-hidden');
                    if (streakBadgeMult) streakBadgeMult.textContent = state.win_mult;
                } else {
                    streakBadge.classList.add('is-hidden');
                }
            }
            var holBet = $('btnHolBet'), jjakBet = $('btnJjakBet');
            var disStartPick = state.locked || state.pending_bet > 0 || !!(state.room_lock && state.room_lock.nick);
            if (holBet) holBet.disabled = disStartPick;
            if (jjakBet) jjakBet.disabled = disStartPick;
            // 진행 예정 또는 대기 상태가 아닐 때, 입력 칸 비어 있으면 선호/최소 배팅 채우기
            var bc = $('betCard');
            if (bc && state.pending_bet <= 0 && (!state.room_lock || !state.room_lock.nick) && !bc.classList.contains('panel-hidden')) {
                var elAmt = $('betAmount');
                if (elAmt) {
                    var n = parseBet();
                    if (nyangIsZero(n)) {
                        applyPrefBet();
                    } else if (nyangCmp(n, state.min_bet) < 0) {
                        applyPrefBet();
                    }
                }
            }
            // 연승 포기 버튼: 2연승부터 항상 표시 (0→1·1→2 이후)
            var giveup = $('btnGiveup');
            var showGiveup = !!(state.streak >= 2 && nyangCmp(state.pending_bet || 0, 0) <= 0 && !state.locked);
            if (showGiveup && !giveup) {
                giveup = document.createElement('button');
                giveup.type = 'button';
                giveup.className = 'btn btn-giveup';
                giveup.id = 'btnGiveup';
                giveup.textContent = giveupHintText('hard');
                giveup.addEventListener('click', doGiveUp);
                var bc = $('betCard');
                if (bc) bc.appendChild(giveup);
            } else if (!showGiveup && giveup) {
                giveup.parentNode.removeChild(giveup);
            } else if (giveup) {
                giveup.textContent = giveupHintText('hard');
            }
            updateModeUI();
            updateQuickButtons();
        }

        function renderAll() {
            renderStreak();
            renderStreakPicks();
            renderLock();
            renderPending();
            renderPayback();
            renderBetCard();
            renderDonateFloat();
        }

        function parseBet() {
            var el = $('betAmount');
            var raw = String((el && el.value) || '').trim();
            // 축약 표시 중(비포커스)이면 보관 중인 정확 금액 사용
            if (el && document.activeElement !== el && !nyangIsZero(betDigits)) {
                if (!raw) return '0';
                var compactNow = fmtAmtCompact(betDigits);
                if (raw === compactNow || raw === (compactNow + '냥') || raw === fmtAmtShort(betDigits)) {
                    return betDigits;
                }
            }
            if (!raw) return '0';
            if (/[해경조억만]/.test(raw)) {
                return parseNyangShort(raw);
            }
            return nyangAbsStr(raw.replace(/[^0-9]/g, ''));
        }
        function parseNyangShort(text) {
            var s = String(text || '').replace(/,/g, '').replace(/\s/g, '');
            if (!s) return '0';
            if (typeof BigInt !== 'undefined') {
                try {
                    var total = 0n;
                    var re = /(\d+)(해|경|조|억|만)/g;
                    var m;
                    var matched = false;
                    while ((m = re.exec(s))) {
                        matched = true;
                        var n = BigInt(m[1]);
                        if (m[2] === '해') total += n * 100000000000000000000n; // 1해 = 10000경
                        else if (m[2] === '경') total += n * 10000000000000000n;
                        else if (m[2] === '조') total += n * 1000000000000n;
                        else if (m[2] === '억') total += n * 100000000n;
                        else if (m[2] === '만') total += n * 10000n;
                    }
                    if (!matched) {
                        return nyangAbsStr(s.replace(/[^0-9]/g, ''));
                    }
                    var rest = s.replace(/(\d+)(해|경|조|억|만)/g, '').replace(/[^0-9]/g, '');
                    if (rest) total += BigInt(rest);
                    return total > 0n ? total.toString() : '0';
                } catch (e) {}
            }
            var totalN = 0;
            var re2 = /(\d+)(해|경|조|억|만)/g;
            var m2;
            var matched2 = false;
            while ((m2 = re2.exec(s))) {
                matched2 = true;
                var n2 = parseInt(m2[1], 10) || 0;
                if (m2[2] === '해') totalN += n2 * 1e20;
                else if (m2[2] === '경') totalN += n2 * 10000000000000000;
                else if (m2[2] === '조') totalN += n2 * 1000000000000;
                else if (m2[2] === '억') totalN += n2 * 100000000;
                else if (m2[2] === '만') totalN += n2 * 10000;
            }
            if (!matched2) {
                return nyangAbsStr(s.replace(/[^0-9]/g, ''));
            }
            var rest2 = s.replace(/(\d+)(해|경|조|억|만)/g, '').replace(/[^0-9]/g, '');
            if (rest2) totalN += parseInt(rest2, 10) || 0;
            return totalN > 0 ? String(totalN) : '0';
        }
        function updateQuickButtons() {
            var cur = parseBet();
            var point = nyangAbsStr(state.point);
            var matchedPct = -1;
            var matchedAny = false;
            document.querySelectorAll('#quickBets .btn-quick[data-fixed]').forEach(function(b) {
                var fixed = nyangAbsStr(b.getAttribute('data-fixed'));
                var pct = parseFloat(b.getAttribute('data-pct'));
                if (isNaN(pct)) pct = -1;
                var base = b.getAttribute('data-label') || b.textContent.trim();
                var belowStreakMin = state.streak > 0 && !nyangIsZero(fixed) && nyangCmp(fixed, state.min_bet) < 0;
                var cantAfford = !nyangIsZero(fixed) && nyangCmp(fixed, point) > 0;
                b.textContent = base;
                b.disabled = belowStreakMin || cantAfford;
                b.classList.toggle('btn-quick--below-min', belowStreakMin || cantAfford);
                var sel = !nyangIsZero(fixed) && nyangCmp(fixed, cur) === 0 && !belowStreakMin && !cantAfford;
                b.classList.toggle('is-selected', sel);
                if (sel) {
                    matchedAny = true;
                    matchedPct = pct;
                }
            });
            // 금액이 퀵버튼과 안 맞으면 저장된 버튼 모드를 선택 표시
            if (!matchedAny && state.pending_bet <= 0) {
                var prefMode = loadPrefQuickMode();
                document.querySelectorAll('#quickBets .btn-quick[data-pct]').forEach(function(b) {
                    var pct = parseFloat(b.getAttribute('data-pct'));
                    if (isNaN(pct)) pct = -1;
                    var belowStreakMin = b.classList.contains('btn-quick--below-min') || b.disabled;
                    var sel = false;
                    var sel = Math.abs(pct - (parseFloat(prefMode) || DEFAULT_QUICK_PCT)) < 0.0001;
                    b.classList.toggle('is-selected', sel && !belowStreakMin);
                });
            }
        }

        function quickPctLabel(pct) {
            pct = Number(pct) || 0;
            if (pct >= 100) return '올인';
            if (Math.abs(pct - Math.round(pct)) < 0.0001) return String(Math.round(pct)) + '%';
            return String(Math.round(pct * 10) / 10) + '%';
        }

        function getQuickBetList() {
            var point = state.point || 0;
            var min = resolveMinBet(point);
            var max = resolveMaxBet(point);
            var list = [];
            var seen = {};
            function push(fixed, label, pct, keepRaw) {
                var s = nyangAbsStr(fixed);
                if (nyangIsZero(s)) return;
                if (nyangCmp(s, max) > 0) s = max;
                if (!keepRaw && nyangCmp(s, min) < 0) s = min;
                if (nyangIsZero(s) || seen[s]) return;
                seen[s] = true;
                list.push({ fixed: s, label: label || fmtAmtShort(s), pct: pct == null ? 0 : pct });
            }
            // 무기 강화 구간 % (+ 종류 최강이면 올인) — 기본 1%
            (QUICK_PCTS || []).forEach(function(pct) {
                pct = Number(pct) || 0;
                if (pct <= 0) return;
                if (pct >= 100 && !CAN_ALLIN) return;
                var amt = pct >= 100 ? nyangAllInStr(point) : nyangPctFloor(point, pct);
                push(amt, quickPctLabel(pct), pct, false);
            });
            if (list.length === 0) {
                push(nyangPctFloor(point, 1), '1%', 1, false);
            }
            return list;
        }

        function renderQuickBets(force) {
            var wrap = $('quickBets');
            if (!wrap) return;
            var point = state.point || 0;
            var pointKey = nyangAbsStr(point) + '|' + (QUICK_PCTS || []).join(',') + '|' + (CAN_ALLIN ? '1' : '0') + '|' + (FIXED_BET || '');
            if (!force && wrap.getAttribute('data-point') === pointKey && wrap.children.length > 0) {
                updateQuickButtons();
                return;
            }
            wrap.setAttribute('data-point', pointKey);
            var list = getQuickBetList();
            wrap.innerHTML = list.map(function(item) {
                var tip = fmtAmtCompact(item.fixed) + '냥';
                return '<button type="button" class="btn-quick" data-fixed="' + item.fixed
                    + '" data-pct="' + (item.pct == null ? 0 : item.pct)
                    + '" data-label="' + item.label
                    + '" title="' + tip + '">' + item.label + '</button>';
            }).join('');
            updateQuickButtons();
        }
        function setBet(v) {
            setBetFromProgram = true;
            normalizeBetLimits();
            var s;
            if (nyangIsZero(v)) {
                s = '0';
            } else {
                s = nyangClampStr(v, state.min_bet, state.max_bet);
            }
            betDigits = s;
            var el = $('betAmount');
            if (el) {
                el.value = nyangIsZero(s) ? '' : fmtAmtCompact(s);
            }
            setBetFromProgram = false;
            updateQuickButtons();
        }
        /** 연승 중 최고 배팅 하한(min_bet)보다 입력이 작으면 맞춤 */
        function syncBetInputToMin() {
            var el = $('betAmount');
            if (!el) return;
            normalizeBetLimits();
            var cur = parseBet();
            if (nyangCmp(cur, state.min_bet) < 0 || nyangCmp(cur, state.max_bet) > 0) {
                setBet(preferredBetClamped());
            } else {
                updateQuickButtons();
            }
        }
        function shouldGuardActionState() {
            return lastActionStateAt > 0 && (Date.now() - lastActionStateAt) < ACTION_STATE_GUARD_MS;
        }

        function renderHeadPoint() {
            var el = $('stHeadPoint');
            if (el) el.textContent = fmtPointShort(state.point);
        }

        function doSwapNp() {
            if (state.pending_bet > 0) {
                window.alert('⏳ 진행 중인 판이 있을 때는 스왑할 수 없어요.');
                return;
            }
            if (state.locked) return;
            var np = parseFloat(state.newpoint) || 0;
            if (np < 0.1) {
                window.alert('❌ 스왑할 본방냥이 없어요.');
                return;
            }
            var ok = window.confirm(
                '본방냥 전액을 게임냥으로 스왑할까요?\n\n'
                + '· 본방냥 ' + fmtNewpoint(np) + '냥 전액\n'
                + '· 5% 삭제 · 95% 환율 교환\n'
                + '· 받는 게임냥은 전체 총량 비율로 계산돼요'
            );
            if (!ok) return;
            state.locked = true;
            showBusy('스왑 처리 중…');
            ajax({ action: 'swap_np' }, function(j) {
                state.locked = false;
                if (!j.ok) {
                    showResult(j.data || '스왑 실패', 'info');
                    return;
                }
                if (j.point != null && j.point !== '') state.point = String(j.point);
                if (typeof j.newpoint === 'number') state.newpoint = j.newpoint;
                if (j.min_bet != null && j.min_bet !== '') state.min_bet = nyangAbsStr(j.min_bet);
                if (j.max_bet != null && j.max_bet !== '') {
                    state.max_bet = nyangAbsStr(j.max_bet);
                } else {
                    state.max_bet = resolveMaxBet(state.point);
                }
                if (j.odds_mode === 'easy' || j.odds_mode === 'hard') state.odds_mode = 'hard';
                if (typeof j.hard_mode_forced === 'boolean') state.hard_mode_forced = j.hard_mode_forced;
                if (typeof j.win_mult === 'number') state.win_mult = j.win_mult;
                if (typeof j.lose_mult === 'number') state.lose_mult = j.lose_mult;
                syncBetInputToMin();
                renderHeadPoint();
                enforceHardModeIfNeeded();
                renderAll();
                showResult(j.data || '스왑 완료', 'info');
            });
        }

        function doClaimPayback() {
            if (state.locked) return;
            if (nyangCmp(state.payback_pool || 0, 0) <= 0) return;
            if (nyangCmp(state.pending_bet || 0, 0) > 0) {
                window.alert('⏳ 진행 중인 판이 있을 때는 페이백을 받을 수 없어요.');
                return;
            }
            if (!confirm('페이백 ' + fmtAmtShort(state.payback_pool) + '냥을 게임냥으로 받을까요?')) return;
            state.locked = true;
            renderPayback();
            ajax({ action: 'claim_payback' }, function(j) {
                state.locked = false;
                if (!j.ok) {
                    window.alert(j.data || '페이백 수령 실패');
                    refreshStatus();
                    return;
                }
                if (j.point != null && j.point !== '') state.point = String(j.point);
                if (j.payback_pool != null && j.payback_pool !== '') state.payback_pool = String(j.payback_pool);
                else state.payback_pool = '0';
                if (j.min_bet != null && j.min_bet !== '') state.min_bet = nyangAbsStr(j.min_bet);
                if (j.max_bet != null && j.max_bet !== '') state.max_bet = nyangAbsStr(j.max_bet);
                if (j.odds_mode === 'easy' || j.odds_mode === 'hard') state.odds_mode = 'hard';
                if (typeof j.hard_mode_forced === 'boolean') state.hard_mode_forced = j.hard_mode_forced;
                if (typeof j.win_mult === 'number') state.win_mult = j.win_mult;
                if (typeof j.lose_mult === 'number') state.lose_mult = j.lose_mult;
                enforceHardModeIfNeeded();
                renderAll();
                showResult(j.data || '페이백 지급 완료', 'info');
            });
        }

        function doBetThenPick(pick) {
            if (state.locked || state.pending_bet > 0) return;
            if (state.room_lock && state.room_lock.nick) return;
            normalizeBetLimits();
            var amt = parseBet();
            if (nyangCmp(amt, state.min_bet) < 0) {
                window.alert('❌ 연승 중 최소 ' + fmt(state.min_bet) + '냥 이상만 가능합니다.\n(이번 연승 최고 배팅보다 낮은 금액은 걸 수 없어요. 더 높은 금액은 가능)');
                return;
            }
            if (nyangCmp(amt, state.max_bet) > 0) {
                window.alert('❌ 최대 ' + fmt(state.max_bet) + '냥 까지 가능합니다.');
                return;
            }
            savePrefBet(amt);
            state.locked = true;
            renderAll();
            showBusy('배팅·정산 처리 중…');
            ajax({ action: 'bet_pick', amount: String(amt), pick: pick }, function(j) {
                state.locked = false;
                if (!j.ok) {
                    hideResult();
                    window.alert(j.data || '배팅/정산 실패');
                    lastActionStateAt = 0;
                    refreshStatus();
                    return;
                }
                applyActionState(j);
                showPickOutcomeBanner(j);
            });
        }

        function doPick(pick) {
            if (state.locked) return;
            state.locked = true;
            $('btnHol').disabled = true;
            $('btnJjak').disabled = true;
            showBusy('정산 처리 중…');
            ajax({ action: 'pick', pick: pick }, function(j) {
                state.locked = false;
                if (!j.ok) {
                    hideResult();
                    window.alert(j.data || '정산 실패');
                    lastActionStateAt = 0;
                    refreshStatus();
                    return;
                }
                applyActionState(j);
                showPickOutcomeBanner(j);
            });
        }

        function doCancel() {
            if (state.locked) return;
            var msg = '배팅을 취소할까요?\n(전액 환급 · 수수료 없음)';
            if (!confirm(msg)) return;
            state.locked = true;
            ajax({ action: 'cancel' }, function(j) {
                state.locked = false;
                if (!j.ok) {
                    hideResult();
                    window.alert(j.data || '취소 실패');
                    lastActionStateAt = 0;
                    refreshStatus();
                    return;
                }
                hideResult();
                lastActionStateAt = 0;
                refreshStatus();
            });
        }

        function doGiveUp() {
            if (state.locked) return;
            if (state.streak < 2) return;
            var msg = '연승을 포기할까요?\n(최고 배팅의 10% 수수료 · 금고50%·로또50% · 연승 리셋)';
            if (!confirm(msg)) return;
            state.locked = true;
            ajax({ action: 'cancel' }, function(j) {
                state.locked = false;
                if (!j.ok) {
                    hideResult();
                    window.alert(j.data || '포기 실패');
                    lastActionStateAt = 0;
                    refreshStatus();
                    return;
                }
                if (j.result === 'give_up') {
                    showResult(j.data, 'info');
                } else {
                    hideResult();
                }
                lastActionStateAt = 0;
                refreshStatus();
            });
        }

        function doSetMode(mode) {
            // 이지/하드 선택 폐지 — 하드 고정
            state.odds_mode = 'hard';
            updateModeUI();
        }

        /** 진동 — API는 세기 미지원, 길이·패턴으로 약/강 구분 (모바일·Android 위주) */
        function vibrateWin() {
            if (!navigator.vibrate) return;
            try { navigator.vibrate(38); } catch (e) {}
        }

        function playPickHit(btn) {
            if (!btn || btn.disabled) return;
            btn.classList.remove('punch');
            void btn.offsetWidth;
            btn.classList.add('punch');
            clearTimeout(btn._punchT);
            btn._punchT = setTimeout(function() { btn.classList.remove('punch'); }, 360);
        }

        function bindPickButton(el, handler) {
            if (!el) return;
            el.addEventListener('pointerdown', function(e) {
                if (el.disabled || state.locked) return;
                if (e.pointerType === 'mouse' && e.button !== 0) return;
                playPickHit(el);
            });
            el.addEventListener('click', function() {
                handler();
            });
        }

        // 이벤트 바인딩
        bindPickButton($('btnHolBet'), function() { doBetThenPick('홀'); });
        bindPickButton($('btnJjakBet'), function() { doBetThenPick('짝'); });
        bindPickButton($('btnHol'), function() { doPick('홀'); });
        bindPickButton($('btnJjak'), function() { doPick('짝'); });
        $('btnCancel').addEventListener('click', doCancel);
        var initialGiveup = $('btnGiveup');
        if (initialGiveup) initialGiveup.addEventListener('click', doGiveUp);
        $('btnRefresh').addEventListener('click', function() {
            hideResult();
            refreshStatus();
        });
        var btnPayback = $('btnPayback');
        if (btnPayback) btnPayback.addEventListener('click', doClaimPayback);
        var btnDonateFloat = $('btnDonateFloat');
        if (btnDonateFloat) {
            btnDonateFloat.addEventListener('click', function() {
                if (!btnDonateFloat.disabled) doDonateHard5();
            });
        }
        var btnClaimDonateFloat = $('btnClaimDonateFloat');
        if (btnClaimDonateFloat) {
            btnClaimDonateFloat.addEventListener('click', function() {
                if (!btnClaimDonateFloat.disabled) doClaimDonatePool();
            });
        }
        var btnThemeSettings = $('btnThemeSettings');
        if (btnThemeSettings) {
            btnThemeSettings.addEventListener('click', openThemeLayer);
        }
        var btnThemeClose = $('btnThemeClose');
        if (btnThemeClose) {
            btnThemeClose.addEventListener('click', closeThemeLayer);
        }
        var themeLayer = $('themeLayer');
        if (themeLayer) {
            themeLayer.addEventListener('click', function(e) {
                if (e.target === themeLayer) closeThemeLayer();
            });
            themeLayer.querySelectorAll('.theme-opt').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var t = btn.getAttribute('data-theme');
                    saveSkinTheme(t);
                    applySkinTheme(t);
                    closeThemeLayer();
                });
            });
        }
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeThemeLayer();
        });
        // 빠른 배팅 버튼 — 클릭 시 해당 금액으로 설정 (연승 중에는 최고 배팅 이상만)
        var quickBetsWrap = $('quickBets');
        if (quickBetsWrap) {
            quickBetsWrap.addEventListener('click', function(e) {
                var b = e.target.closest('.btn-quick');
                if (!b || !quickBetsWrap.contains(b)) return;
                if (b.getAttribute('data-swap') === '1') {
                    if (!b.disabled) doSwapNp();
                    return;
                }
                var fixed = b.getAttribute('data-fixed');
                var amt = b.getAttribute('data-amt');
                if (fixed) {
                    var unit = nyangAbsStr(fixed);
                    if (nyangIsZero(unit) || b.disabled) return;
                    var next = unit;
                    if (state.streak > 0) {
                        next = nyangMaxStr(unit, state.min_bet);
                    }
                    if (nyangCmp(next, state.min_bet) < 0) {
                        window.alert('❌ 연승 중 최소 ' + fmt(state.min_bet) + '냥 이상만 가능합니다.');
                        return;
                    }
                    if (nyangCmp(next, state.max_bet) > 0) {
                        window.alert('❌ 최대 ' + fmt(state.max_bet) + '냥까지 가능합니다.');
                        return;
                    }
                    setBet(next);
                    var pctAttr = parseFloat(b.getAttribute('data-pct'));
                    if (isNaN(pctAttr) || pctAttr <= 0) {
                        savePrefQuick(String(DEFAULT_QUICK_PCT), next);
                    } else {
                        savePrefQuick(String(pctAttr), next);
                    }
                } else if (amt) {
                    var cur2 = parseBet();
                    var next2;
                    if (typeof BigInt !== 'undefined') {
                        try {
                            next2 = (BigInt(nyangAbsStr(cur2)) + BigInt(nyangAbsStr(amt))).toString();
                        } catch (eAdd) {
                            next2 = nyangAbsStr(amt);
                        }
                    } else {
                        next2 = nyangAbsStr(amt);
                    }
                    if (nyangCmp(next2, state.min_bet) < 0) {
                        window.alert('❌ 연승 중 최소 ' + fmt(state.min_bet) + '냥 이상만 가능합니다.');
                        return;
                    }
                    if (nyangCmp(next2, state.max_bet) > 0) {
                        window.alert('❌ 최대 ' + fmt(state.max_bet) + '냥까지 가능합니다.');
                        return;
                    }
                    setBet(next2);
                    savePrefBet(next2);
                }
            });
        }
        // 키보드 단축키: 1=홀, 2=짝, 4=10조
        document.addEventListener('keydown', function(e) {
            if (!e) return;
            if (e.repeat) return;
            var tag = ((document.activeElement && document.activeElement.tagName) || '').toUpperCase();
            if (tag === 'INPUT' || tag === 'TEXTAREA' || (document.activeElement && document.activeElement.isContentEditable)) {
                return;
            }
            var k = e.key;
            if (k === '1' || k === 'Numpad1') {
                var holPending = $('btnHol');
                var holBet = $('btnHolBet');
                if (holPending && !holPending.disabled && $('pendingBox') && !$('pendingBox').classList.contains('panel-hidden')) {
                    holPending.click();
                    e.preventDefault();
                    return;
                }
                if (holBet && !holBet.disabled && $('betCard') && !$('betCard').classList.contains('panel-hidden')) {
                    holBet.click();
                    e.preventDefault();
                }
                return;
            }
            if (k === '2' || k === 'Numpad2') {
                var jjakPending = $('btnJjak');
                var jjakBet = $('btnJjakBet');
                if (jjakPending && !jjakPending.disabled && $('pendingBox') && !$('pendingBox').classList.contains('panel-hidden')) {
                    jjakPending.click();
                    e.preventDefault();
                    return;
                }
                if (jjakBet && !jjakBet.disabled && $('betCard') && !$('betCard').classList.contains('panel-hidden')) {
                    jjakBet.click();
                    e.preventDefault();
                }
                return;
            }
            if (k === '4' || k === 'Numpad4') {
                normalizeBetLimits();
                var unit10 = parseNyangShort('10조') || BET_10JO;
                var next10 = state.streak > 0 ? nyangMaxStr(unit10, state.min_bet) : nyangAbsStr(unit10);
                if (nyangCmp(next10, state.min_bet) < 0) {
                    window.alert('❌ 연승 중 최소 ' + fmt(state.min_bet) + '냥 이상만 가능합니다.');
                    e.preventDefault();
                    return;
                }
                if (nyangCmp(next10, state.max_bet) > 0) {
                    window.alert('❌ 최대 ' + fmt(state.max_bet) + '냥까지 가능합니다.');
                    e.preventDefault();
                    return;
                }
                setBet(next10);
                savePrefBet(next10);
                e.preventDefault();
            }
        });
        // 배팅 금액은 1%/3% 퀵 버튼으로만 변경 (직접 입력 불가)
        (function lockBetAmountInput() {
            var el = $('betAmount');
            if (!el) return;
            el.readOnly = true;
            el.setAttribute('tabindex', '-1');
            el.addEventListener('focus', function() { this.blur(); });
            ['keydown', 'keypress', 'keyup', 'paste', 'cut', 'drop', 'beforeinput'].forEach(function(ev) {
                el.addEventListener(ev, function(e) { e.preventDefault(); });
            });
        })();

        // 타이머 (1초)
        setInterval(function() {
            if (state.pending_bet > 0 && state.pending_left > 0) {
                state.pending_left -= 1;
                if (state.pending_left <= 0) {
                    state.pending_left = 0;
                    refreshStatus();
                } else {
                    var el = $('pbTimeText');
                    if (el) el.textContent = fmtSec(state.pending_left);
                }
            }
        }, 1000);

        // 방 락/연승자 상태 주기적 폴링 (5초, 30초마다 만료 정리 포함 full_sync)
        var statusPollCount = 0;
        setInterval(function() {
            if (state.locked) return;
            statusPollCount++;
            refreshStatus(null, statusPollCount % 6 === 0);
        }, 5000);

        // 초기 렌더 — 저장된 퀵 버튼(없으면 1%)
        renderAll();
        if (state.pending_bet <= 0) {
            applyPrefBet();
        } else {
            updateQuickButtons();
        }
        var pbTimeEl = $('pbTimeText');
        if (pbTimeEl) pbTimeEl.textContent = fmtSec(state.pending_left);

    })();
    </script>
    <?php
      require_once __DIR__ . '/wallet_nav_fab.inc.php';
      wallet_nav_fab_render([
        'code' => $game_code,
        'variant' => 'indigo',
      ]);
    } ?>
</body>
</html>
