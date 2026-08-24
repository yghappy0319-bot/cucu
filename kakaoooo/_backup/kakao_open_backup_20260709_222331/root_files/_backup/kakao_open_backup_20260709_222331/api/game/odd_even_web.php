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
 *   action=cancel   — 대기 중(배팅 90% 환급·10% 수수료 금고50%·로또50%) 또는 연승 포기(2연승~)
 *   action=set_mode — 이지/하드 모드 선택 (easy|hard)
 *   action=swap_np  — 본방냥 전액 → 게임냥 (5% 삭제 · 95% 환율 교환)
 *   action=claim_payback — 웹 페이백 누적분 게임냥 수령
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
function game_auth($code) {
    $code = is_string($code) ? trim($code) : '';
    if ($code === '') return null;
    $esc = addslashes($code);
    $row = db_select("SELECT idx, name, point FROM tb_member WHERE code = '{$esc}' LIMIT 1");
    if (empty($row['name'])) return null;
    return $row;
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
function game_min_bet($행, $가진냥) {
    $기본 = function_exists('홀짝_기본배팅') ? 홀짝_기본배팅($가진냥) : 1000000;
    return max($기본, (int)($행['streak_max_bet'] ?? 0));
}
function game_max_bet($가진냥) {
    return function_exists('홀짝_최대배팅') ? 홀짝_최대배팅($가진냥) : 100000000000;
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
    $이전맥스배 = (int)($행['streak_max_bet'] ?? 0);
    if ($이전맥스배 > 0) {
        홀짝_연승포기_수수료처리($닉_esc, $두자리닉넴, $이전맥스배);
    }
    db_query("UPDATE tb_odd_even_state SET streak = 0, streak_max_bet = 0 WHERE nick = '{$닉_esc}' LIMIT 1");
    $행['streak'] = 0;
    $행['streak_max_bet'] = 0;
}

/** 냥 금액 → 만·억·조·경 축약 (예: 1000000→100만, 100000000000→1000억) */
function game_금액_축약($n) {
    $n = max(0, (int)$n);
    if ($n <= 0) {
        return '0';
    }
    if (function_exists('냥_경조억_파트') && $n >= 100000000) {
        [$경수, $조수, $억수] = 냥_경조억_파트($n);
        if ($경수 > 0 || $조수 > 0 || $억수 > 0) {
            $parts = [];
            if ($경수 > 0) {
                $parts[] = $경수 . '경';
            }
            if ($조수 > 0) {
                $parts[] = $조수 . '조';
            }
            if ($억수 > 0) {
                $parts[] = $억수 . '억';
            }
            return implode('', $parts);
        }
    }
    $parts = [];
    $jo = intdiv($n, 1000000000000);
    $n %= 1000000000000;
    $eok = intdiv($n, 100000000);
    $n %= 100000000;
    $man = intdiv($n, 10000);
    $n %= 10000;
    if ($jo > 0) {
        $parts[] = $jo . '조';
    }
    if ($eok > 0) {
        $parts[] = $eok . '억';
    }
    if ($man > 0) {
        $parts[] = $man . '만';
    }
    if ($n > 0) {
        $parts[] = (string)$n;
    }
    return implode('', $parts);
}

/** 내 정보 보유냥 표시 — 경·조·억만 (억 미만 절사, 마이너스 허용) */
function game_보유냥_표시($n) {
    $n = (int)$n;
    $neg = $n < 0;
    $abs = abs($n);
    if (function_exists('냥_경조억_축약문구')) {
        $txt = 냥_경조억_축약문구($abs, '', '');
    } else {
        $txt = game_금액_축약($abs);
        if ($txt !== '0') {
            $txt = preg_replace('/\d+만/u', '', $txt);
            $txt = preg_replace('/\d+$/u', '', $txt);
            if ($txt === '') {
                $txt = '0';
            }
        }
    }
    return ($neg ? '-' : '') . $txt;
}

/**
 * multi 모드: 본인 pending·연승 타임아웃만 정리 (타인 상태는 건드리지 않음).
 */
function game_web_expire_own_timed_out_states($두자리닉넴, $GAME_타임아웃초) {
    $만료초 = (int)$GAME_타임아웃초;
    $닉_esc = addslashes($두자리닉넴);

    $진행행 = @db_select("
      SELECT pending_at, pending_bet,
             TIMESTAMPDIFF(SECOND, pending_at, NOW()) AS elapsed_sec
      FROM tb_odd_even_state
      WHERE nick = '{$닉_esc}' AND pending_bet > 0
      LIMIT 1
    ");
    if ($진행행 && (int)($진행행['elapsed_sec'] ?? 0) >= $만료초) {
        $만료환 = (int)($진행행['pending_bet'] ?? 0);
        if ($만료환 > 0) {
            홀짝_타임아웃_환급처리($닉_esc, $두자리닉넴, $만료환);
        }
        db_query("UPDATE tb_odd_even_state SET pending_bet = 0, pending_answer = NULL, pending_win_mult = 0, pending_lose_mult = 0, pending_at = NULL WHERE nick = '{$닉_esc}' LIMIT 1");
    }

    $연승행 = @db_select("
      SELECT streak_max_bet,
             TIMESTAMPDIFF(SECOND, updated_at, NOW()) AS elapsed_sec
      FROM tb_odd_even_state
      WHERE nick = '{$닉_esc}' AND streak > 0
      LIMIT 1
    ");
    if ($연승행 && (int)($연승행['elapsed_sec'] ?? 0) >= $만료초) {
        $이전맥스배 = (int)($연승행['streak_max_bet'] ?? 0);
        if ($이전맥스배 > 0) {
            홀짝_연승포기_수수료처리($닉_esc, $두자리닉넴, $이전맥스배);
        }
        db_query("UPDATE tb_odd_even_state SET streak = 0, streak_max_bet = 0 WHERE nick = '{$닉_esc}' LIMIT 1");
    }
}

/**
 * single 모드: 만료된 pending·연승 상태 정리(UPDATE·지급로그). 무거워서 status 폴링마다 돌리지 않음.
 */
function game_web_expire_timed_out_states($두자리닉넴, $GAME_타임아웃초) {
    $만료초 = (int)$GAME_타임아웃초;

    $진행행 = @db_select("
      SELECT nick, pending_at, pending_bet,
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
                $만료환 = (int)($진행행['pending_bet'] ?? 0);
                $만료금고 = 0;
                $만료환급 = 0;
                if ($만료환 > 0) {
                    홀짝_타임아웃_환급처리($진행중닉_esc, $진행중닉, $만료환);
                }
                db_query("UPDATE tb_odd_even_state SET pending_bet = 0, pending_answer = NULL, pending_win_mult = 0, pending_lose_mult = 0, pending_at = NULL WHERE nick = '{$진행중닉_esc}' LIMIT 1");
            }
        } elseif ($경과초 >= $만료초) {
            $닉_esc = addslashes($두자리닉넴);
            $만료환 = (int)($진행행['pending_bet'] ?? 0);
            if ($만료환 > 0) {
                홀짝_타임아웃_환급처리($닉_esc, $두자리닉넴, $만료환);
            }
            db_query("UPDATE tb_odd_even_state SET pending_bet = 0, pending_answer = NULL, pending_win_mult = 0, pending_lose_mult = 0, pending_at = NULL WHERE nick = '{$닉_esc}' LIMIT 1");
        }
    }

    $연승행 = @db_select("
      SELECT nick, streak, streak_max_bet, updated_at,
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
                $이전맥스배 = (int)($연승행['streak_max_bet'] ?? 0);
                $연승금고 = 0;
                $연승유지분 = 0;
                if ($이전맥스배 > 0) {
                    홀짝_연승포기_수수료처리($연승닉_esc, $연승닉, $이전맥스배);
                }
                db_query("UPDATE tb_odd_even_state SET streak = 0, streak_max_bet = 0 WHERE nick = '{$연승닉_esc}' LIMIT 1");
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
        $streak_max_bet = (int)($행['streak_max_bet'] ?? 0);
    } else {
        $streak_max_bet = max(0, (int)$streak_max_bet);
    }
    if (isset($opts['point']) && is_numeric($opts['point'])) {
        $표시포인트 = (int)$opts['point'];
    } else {
        $pt = @db_select("SELECT point FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
        $표시포인트 = is_array($pt) ? (int)($pt['point'] ?? 0) : 0;
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
    return $out;
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

$ACTIONS_NEED_AUTH = ['status', 'bet', 'bet_pick', 'pick', 'cancel', 'set_mode', 'swap_np', 'claim_payback'];
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
        $pt_now = @db_select("SELECT point, newpoint FROM tb_member WHERE code = '" . addslashes($req_code) . "' LIMIT 1");
        $표시포인트 = is_array($pt_now) ? (int)($pt_now['point'] ?? 0) : (int)($회원['point'] ?? 0);
        $표시본방냥 = is_array($pt_now) ? round((float)($pt_now['newpoint'] ?? 0), 1) : 0;

        $pending_bet = (int)($행['pending_bet'] ?? 0);
        $pending_at_ts = !empty($행['pending_at']) ? strtotime($행['pending_at']) : 0;
        $남은초 = 0;
        $pending_expired = 0;
        if ($pending_bet > 0 && $pending_at_ts > 0) {
            $남은초 = max(0, ($pending_at_ts + $GAME_타임아웃초) - time());
            if ($남은초 <= 0) $pending_expired = 1;
        }

        $streak = 홀짝_연승_읽기_및_복구($닉_esc, (int)($행['streak'] ?? 0));
        $odds_mode = 홀짝_모드_웹읽기($닉_esc, $표시포인트);
        $바닥 = game_min_bet($행, $표시포인트);
        $배 = game_배수($streak, $odds_mode);
        game_json([
            'ok'              => true,
            'name'            => $닉,
            'point'           => $표시포인트,
            'newpoint'        => $표시본방냥,
            'streak'          => $streak,
            'streak_max'      => $GAME_연승최대,
            'streak_max_bet'  => (int)($행['streak_max_bet'] ?? 0),
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
        ]);
    }

    // ===== 페이백 수령 (웹 전용) =====
    if ($req_action === 'claim_payback') {
        $행 = game_state_row($닉_esc);
        if ((int)($행['pending_bet'] ?? 0) > 0) {
            game_json(['ok' => false, 'data' => '⏳ 진행 중인 판이 있을 때는 페이백을 받을 수 없어요.']);
        }
        $결과 = 홀짝_웹_페이백_수령($닉_esc, $두자리닉넴);
        if (empty($결과['ok'])) {
            game_json(['ok' => false, 'data' => $결과['data'] ?? '❌ 페이백 수령에 실패했어요.']);
        }
        $streak = 홀짝_연승_읽기_및_복구($닉_esc, (int)($행['streak'] ?? 0));
        $페이백후냥 = (int)$결과['point'];
        $odds_mode = 홀짝_모드_웹읽기($닉_esc, $페이백후냥);
        $바닥 = game_min_bet($행, $페이백후냥);
        $배 = game_배수($streak, $odds_mode);
        game_json([
            'ok'           => true,
            'type'         => 'claim_payback',
            'data'         => $결과['data'],
            'amount'       => (int)$결과['amount'],
            'point'        => $페이백후냥,
            'payback_pool' => 0,
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
        if ((int)($행['pending_bet'] ?? 0) > 0) {
            game_json(['ok' => false, 'data' => '⏳ 진행 중인 판이 있을 때는 스왑할 수 없어요.']);
        }
        $결과 = 스왑_홀짝_본방_실행_데이터($두자리닉넴);
        if (empty($결과['ok'])) {
            game_json(['ok' => false, 'data' => $결과['data'] ?? '❌ 스왑에 실패했어요.']);
        }
        $게임냥 = (int)($결과['point'] ?? 0);
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

    // ===== 이지/하드 모드 =====
    if ($req_action === 'set_mode') {
        $mode_raw = isset($_REQUEST['mode']) ? trim($_REQUEST['mode']) : '';
        if ($mode_raw === '') {
            game_json(['ok' => false, 'data' => '모드를 선택해주세요. (easy 또는 hard)']);
        }
        $행 = game_state_row($닉_esc);
        if ((int)($행['pending_bet'] ?? 0) > 0) {
            game_json(['ok' => false, 'data' => '⏳ 진행 중인 판이 있을 때는 모드를 바꿀 수 없어요.']);
        }
        $가진냥 = (int)($회원['point'] ?? 0);
        $mode = 홀짝_모드_정규화($mode_raw);
        if (홀짝_웹_하드모드_강제($가진냥) && $mode !== 'hard') {
            game_json(['ok' => false, 'data' => '❌ 보유냥 100경 이상은 하드모드만 선택할 수 있어요.']);
        }
        if (!홀짝_모드_저장($닉_esc, $mode)) {
            game_json(['ok' => false, 'data' => '❌ 모드 저장에 실패했어요.']);
        }
        $라벨 = ($mode === 'hard') ? '하드모드' : '이지모드';
        $설명 = ($mode === 'hard')
            ? '불리한 확률 · 패배 +1 · 연승 3~15% x2~x4 · 연승 포기만 최고배팅 1% 수수료(로또)'
            : '유리한 확률 · 연승 승리 시 배팅 10% 수수료(금고 5%·로또 5%)';
        game_json([
            'ok' => true,
            'type' => 'set_mode',
            'odds_mode' => $mode,
            'data' => "✅ {$라벨} 선택\n{$설명}",
        ]);
    }

    // ===== 배팅 (.도전 금액) =====
    if ($req_action === 'bet') {
        require_once __DIR__ . '/odd_even_guards.php';
        $가진냥 = (int)($회원['point'] ?? 0);
        $배팅 = isset($_REQUEST['amount']) ? (int)$_REQUEST['amount'] : 0;
        if ($배팅 <= 0) {
            $배팅 = 홀짝_기본배팅($가진냥);
        }
        if ($가진냥 < 0) game_json(['ok' => false, 'data' => '❌ 가진 냥이 마이너스면 도전 불가']);

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
        $기본배팅 = 홀짝_기본배팅($가진냥);
        $바닥배팅 = game_min_bet($행_pre, $가진냥);
        if ($배팅 < $바닥배팅) {
            if ($바닥배팅 > $기본배팅) {
                game_json(['ok' => false, 'data' => '❌ 이번 연승 최소 ' . number_format($바닥배팅) . '냥 (최고 배팅 이상만 가능 · 더 높은 금액은 OK)']);
            } else {
                game_json(['ok' => false, 'data' => '❌ 최소 ' . number_format($기본배팅) . "냥"]);
            }
        }
        $최대배팅 = game_max_bet($가진냥);
        if ($배팅 > $최대배팅) {
            game_json(['ok' => false, 'data' => '❌ 최대 ' . number_format($최대배팅) . "냥"]);
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
        $내pending = (int)($행['pending_bet'] ?? 0);
        if ($내pending > 0) {
            $만료 = strtotime($행['pending_at'] ?? '') + $GAME_타임아웃초;
            if ($만료 >= time()) {
                game_json(['ok' => false, 'data' => '⏳ 진행 중인 판이 있어요. `홀`/`짝` 또는 취소를 먼저 진행해주세요.']);
            }
            $만료환 = $내pending;
            if ($만료환 > 0) {
                홀짝_타임아웃_환급처리($닉_esc, $두자리닉넴, $만료환);
            }
            db_query("UPDATE tb_odd_even_state SET pending_bet = 0, pending_answer = NULL, pending_win_mult = 0, pending_lose_mult = 0, pending_at = NULL WHERE nick = '{$닉_esc}' LIMIT 1");
        }

        $streak = 홀짝_연승_읽기_및_복구($닉_esc, (int)($행['streak'] ?? 0));
        $bet_odds_mode = 홀짝_모드_웹읽기($닉_esc, $가진냥, $행);
        $배 = game_배수($streak, $bet_odds_mode);

        $정답 = 홀짝_닉_정답_선정($닉_esc);

        $누적맥스 = (int)($행['streak_max_bet'] ?? 0);
        if (!홀짝_배팅_저장($닉_esc, $streak, $누적맥스, $배팅, $정답, (int)$배['win'], (int)$배['lose'])) {
            game_json(['ok' => false, 'data' => '❌ 배팅 저장에 실패했어요. 잠시 후 다시 시도해 주세요.']);
        }

        $자숙위반 = 자숙_홀짝위반_적용($두자리닉넴, '냥');
        $자숙위반안내 = (string)($자숙위반['notice'] ?? '');

        db_query("UPDATE tb_member SET point = point - {$배팅} WHERE name = '{$닉_esc}' LIMIT 1");
        지급로그('홀짝도전-배팅', $두자리닉넴, '', 0, $배팅);
        홀짝_웹_페이백_배팅적립($닉_esc, $배팅, $bet_odds_mode);

        $msg  = $자숙위반안내;
        $msg .= "🎲 " . ($streak <= 0 ? '첫 도전' : "{$streak}연승 도전") . " · " . number_format($배팅) . "냥\n";
        $msg .= "승 ×{$배['win']} → +" . number_format($배팅 * $배['win']) . "\n";
        $msg .= "패 ×{$배['lose']} → -" . number_format($배팅 * $배['lose']) . "\n";
        $msg .= "👉 `홀` / `짝` 선택!";

        game_json([
            'ok'             => true,
            'type'           => 'bet',
            'data'           => $msg,
            'bet'            => $배팅,
            'streak'         => $streak,
            'win_mult'       => $배['win'],
            'lose_mult'      => $배['lose'],
            'point'          => max(0, (int)($회원['point'] ?? 0)) - $배팅,
            'pending_bet'    => $배팅,
            'pending_left'   => $GAME_타임아웃초,
            'payback_pool'   => 홀짝_웹_페이백_조회($닉_esc),
        ]);
    }

    // ===== 배팅+선택 단건 처리 (웹 최적화) =====
    if ($req_action === 'bet_pick') {
        $배팅 = isset($_REQUEST['amount']) ? (int)$_REQUEST['amount'] : 0;
        $pick_raw = isset($_REQUEST['pick']) ? trim($_REQUEST['pick']) : '';
        if ($pick_raw !== '홀' && $pick_raw !== '짝') {
            game_json(['ok' => false, 'data' => '`홀` 또는 `짝`을 선택해주세요.']);
        }
        $가진냥 = (int)($회원['point'] ?? 0);
        if ($배팅 <= 0) {
            $배팅 = 홀짝_기본배팅($가진냥);
        }
        if ($가진냥 < 0) game_json(['ok' => false, 'data' => '❌ 가진 냥이 마이너스면 도전 불가']);
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
        if ($배팅 > $최대배팅) {
            game_json(['ok' => false, 'data' => '❌ 최대 ' . number_format($최대배팅) . "냥"]);
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

        $내pending = (int)($행['pending_bet'] ?? 0);
        if ($내pending > 0) {
            $만료 = strtotime($행['pending_at'] ?? '') + $GAME_타임아웃초;
            if ($만료 >= time()) {
                game_json(['ok' => false, 'data' => '⏳ 진행 중인 판이 있어요. `홀`/`짝` 또는 취소를 먼저 진행해주세요.']);
            }
            $만료환 = $내pending;
            if ($만료환 > 0) {
                홀짝_타임아웃_환급처리($닉_esc, $두자리닉넴, $만료환);
            }
            db_query("UPDATE tb_odd_even_state SET pending_bet = 0, pending_answer = NULL, pending_win_mult = 0, pending_lose_mult = 0, pending_at = NULL WHERE nick = '{$닉_esc}' LIMIT 1");
            $행 = game_state_row($닉_esc);
        }

        $기본배팅 = 홀짝_기본배팅($가진냥);
        $바닥배팅 = game_min_bet($행, $가진냥);
        if ($배팅 < $바닥배팅) {
            if ($바닥배팅 > $기본배팅) {
                game_json(['ok' => false, 'data' => '❌ 이번 연승 최소 ' . number_format($바닥배팅) . '냥 (최고 배팅 이상만 가능 · 더 높은 금액은 OK)']);
            } else {
                game_json(['ok' => false, 'data' => '❌ 최소 ' . number_format($기본배팅) . "냥"]);
            }
        }

        $streak전 = 홀짝_연승_읽기_및_복구($닉_esc, (int)($행['streak'] ?? 0));
        $odds_mode = 홀짝_모드_웹읽기($닉_esc, $가진냥, $행);
        $배 = game_배수($streak전, $odds_mode);
        $정답 = 홀짝_닉_정답_선정($닉_esc);
        $유저픽 = ($pick_raw === '홀') ? 1 : 2;

        $자숙위반 = 자숙_홀짝위반_적용($두자리닉넴, '냥');
        $자숙위반안내 = (string)($자숙위반['notice'] ?? '');

        홀짝_웹_페이백_배팅적립($닉_esc, $배팅, $odds_mode);

        $판 = [
            'streak' => $streak전,
            'streak_max_bet' => (int)($행['streak_max_bet'] ?? 0),
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
        if ($자숙위반안내 !== '') {
            $정산['data'] = $자숙위반안내 . (string)($정산['data'] ?? '');
        }
        $streak후 = (int)($정산['streak_after'] ?? 0);
        $streak_max_after = array_key_exists('streak_max_bet', $정산)
            ? (int)$정산['streak_max_bet']
            : null;
        $streak_won = array_key_exists('streak_won', $정산) ? (int)$정산['streak_won'] : null;
        $pt_row = @db_select("SELECT point FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
        $point_now = is_array($pt_row) ? (int)($pt_row['point'] ?? 0) : max(0, (int)($회원['point'] ?? 0));
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
        if (empty($판) || (int)($판['pending_bet'] ?? 0) <= 0) {
            game_json(['ok' => false, 'data' => '❌ 진행 중인 배팅이 없어요. 먼저 배팅을 걸어주세요.']);
        }

        require_once __DIR__ . '/odd_even_guards.php';
        $게임금지 = 게임제한_홀짝_금지문구($두자리닉넴);
        if ($게임금지 !== null) {
            game_json(['ok' => false, 'data' => $게임금지]);
        }
        $신용취소 = 홀짝_신용회복_pending이면_취소환급($두자리닉넴, $판, '냥');
        if ($신용취소 !== null) {
            $pt_row = @db_select("SELECT point FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
            game_json([
                'ok'     => true,
                'type'   => 'cancel',
                'result' => 'refund',
                'data'   => $신용취소['msg'],
                'refund' => (int)$신용취소['refund'],
                'gumgo'  => (int)$신용취소['gumgo'],
                'lotto'  => (int)($신용취소['lotto'] ?? 0),
                'tax'    => (int)($신용취소['tax'] ?? 0),
                'point'  => (int)($pt_row['point'] ?? 0),
            ]);
        }

        $만료 = strtotime($판['pending_at'] ?? '') + $GAME_타임아웃초;
        if ($만료 < time()) {
            $타임원 = (int)($판['pending_bet'] ?? 0);
            $타임모드 = 홀짝_모드_읽기($닉_esc, $판);
            $타임환급 = 0;
            $타임분배 = ['금고' => 0, '로또' => 0];
            if ($타임원 > 0) {
                $타임 = 홀짝_타임아웃_환급처리($닉_esc, $두자리닉넴, $타임원, $타임모드);
                $타임환급 = (int)$타임['환급'];
                $타임분배 = ['금고' => (int)$타임['금고'], '로또' => (int)$타임['로또']];
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
        $streak후 = (int)($정산['streak_after'] ?? 0);
        $streak_max_after = array_key_exists('streak_max_bet', $정산)
            ? (int)$정산['streak_max_bet']
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
        $환불 = (int)($기존['pending_bet'] ?? 0);
        $연승 = (int)($기존['streak'] ?? 0);

        if ($기존 && $환불 > 0) {
            $모드 = 홀짝_모드_읽기($닉_esc, $기존);
            $환급결과 = 홀짝_취소무승부_환급처리($닉_esc, $두자리닉넴, $환불, $모드);
            db_query("UPDATE tb_odd_even_state SET pending_bet = 0, pending_answer = NULL, pending_win_mult = 0, pending_lose_mult = 0, pending_at = NULL WHERE nick = '{$닉_esc}' LIMIT 1");
            지급로그('홀짝도전-취소환급', $두자리닉넴, '', (int)$환급결과['수수료'], (int)$환급결과['환급']);
            game_json([
                'ok' => true, 'type' => 'cancel', 'result' => 'refund',
                'data' => "✅ 취소 · " . 홀짝_취소무승부_환급문구($환급결과, $모드, '냥'),
                'refund' => (int)$환급결과['환급'], 'gumgo' => (int)$환급결과['수수료'], 'lotto' => (int)$환급결과['로또'], 'tax' => (int)$환급결과['금고'],
            ]);
        } elseif ($기존 && $연승 >= 2) {
            $맥스배 = (int)($기존['streak_max_bet'] ?? 0);
            db_query("UPDATE tb_odd_even_state SET streak = 0, streak_max_bet = 0 WHERE nick = '{$닉_esc}' LIMIT 1");
            $포기 = 홀짝_연승포기_수수료처리($닉_esc, $두자리닉넴, $맥스배, 홀짝_모드_읽기($닉_esc, $기존));
            if ($맥스배 > 0) {
                $포기문구 = 홀짝_연승포기_수수료문구($포기, '냥');
                game_json([
                    'ok' => true, 'type' => 'cancel', 'result' => 'give_up',
                    'data' => "✅ 연승 포기 ({$연승}→0) · " . ($포기문구 !== '' ? $포기문구 : '수수료 없음'),
                    'gumgo' => $포기['금고'], 'lotto' => $포기['로또'], 'tax' => $포기['금고'],
                ]);
            }
            game_json([
                'ok' => true, 'type' => 'cancel', 'result' => 'give_up',
                'data' => "✅ 연승 포기 ({$연승}→0)", 'gumgo' => 0,
            ]);
        } elseif ($기존 && $연승 === 1) {
            game_json(['ok' => false, 'data' => '❌ 연승 포기는 2연승부터 취소 가능합니다.']);
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
$game_streak_max_bet = 0;
$game_pending_bet = 0;
$game_pending_left = 0;
$game_win_mult = 2;
$game_lose_mult = 1;
$game_min_bet = 1000000;
$game_max_bet = $GAME_최대배팅;
$game_room_lock = null;
$game_need_code = false;
$game_streak_picks = [];
$game_odds_mode = 'easy';
$game_payback_pool = 0;
$game_hard_mode_forced = false;

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
        $game_point = (int)($m['point'] ?? 0);
        $np_row = @db_select("SELECT newpoint FROM tb_member WHERE code = '" . addslashes($game_code) . "' LIMIT 1");
        $game_newpoint = is_array($np_row) ? round((float)($np_row['newpoint'] ?? 0), 1) : 0;
        $두자리닉넴_page = getTwoCharNick($game_name);
        if ($두자리닉넴_page !== '') {
            $닉_esc_page = addslashes($두자리닉넴_page);
            $sync_page = game_web_sync_global_room($두자리닉넴_page, $GAME_타임아웃초);
            $game_room_lock = $sync_page['room_lock'];
            $행 = game_state_row($닉_esc_page);
            $game_streak = 홀짝_연승_읽기_및_복구($닉_esc_page, (int)($행['streak'] ?? 0));
            $game_streak_max_bet = (int)($행['streak_max_bet'] ?? 0);
            $game_pending_bet = (int)($행['pending_bet'] ?? 0);
            if ($game_pending_bet > 0 && !empty($행['pending_at'])) {
                $game_pending_left = max(0, (strtotime($행['pending_at']) + $GAME_타임아웃초) - time());
            }
            $game_odds_mode = 홀짝_모드_웹읽기($닉_esc_page, $game_point);
            $배_page = game_배수($game_streak, $game_odds_mode);
            $game_win_mult = $배_page['win'];
            $game_lose_mult = $배_page['lose'];
            $game_min_bet = game_min_bet($행, $game_point);
            $game_streak_picks = game_streak_pick_labels($닉_esc_page, $game_streak);
            $pt_page = @db_select("SELECT point FROM tb_member WHERE code = '" . addslashes($game_code) . "' LIMIT 1");
            if (is_array($pt_page) && array_key_exists('point', $pt_page)) {
                $game_point = (int)$pt_page['point'];
            }
            $game_max_bet = game_max_bet($game_point);
            $game_hard_mode_forced = 홀짝_웹_하드모드_강제($game_point);
            $game_payback_pool = 홀짝_웹_페이백_조회($닉_esc_page);
        }
    }
}

/* 배팅 입력 기본값: 자산 구간별 기본·연승 시 streak_max 적용 — 배팅 카드 노출 구간만 */
$game_default_bet_display = (!$game_need_code && !$game_room_lock && $game_pending_bet <= 0)
    ? number_format($game_min_bet)
    : '';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="<?php echo ($game_need_code || $game_odds_mode !== 'hard') ? '#0b1628' : '#120808'; ?>">
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
        body.mode-easy.odd-even-play {
            --bg: #0b1628;
            --card: #152238;
            --accent: #5eead4;
            --gold: #fde68a;
            --blue: #67e8f9;
            --green: #6ee7b7;
            --title-a: #6ee7b7;
            --title-b: #67e8f9;
            --wrap-glow: rgba(56,189,248,0.22);
            --wrap-ring: rgba(110,231,183,0.18);
            --card-bg: rgba(8,30,45,0.42);
            --card-border: rgba(103,232,249,0.14);
            background-image:
                radial-gradient(ellipse at 50% 0%, rgba(110,231,183,0.16) 0%, transparent 52%),
                radial-gradient(ellipse at 85% 85%, rgba(56,189,248,0.14) 0%, transparent 46%),
                radial-gradient(ellipse at 8% 72%, rgba(253,230,138,0.07) 0%, transparent 42%);
        }
        body.mode-hard.odd-even-play {
            --bg: #120808;
            --card: #1f0f0f;
            --accent: #fb923c;
            --gold: #fdba74;
            --blue: #f87171;
            --green: #fca5a5;
            --red: #ef4444;
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
        body.mode-hard.odd-even-play .wrap {
            box-shadow: 0 20px 60px var(--wrap-glow), 0 0 0 1px var(--wrap-ring), 0 0 40px rgba(239,68,68,0.12);
        }
        body.mode-easy.odd-even-play .wrap {
            box-shadow: 0 20px 60px var(--wrap-glow), 0 0 0 1px var(--wrap-ring);
        }
        body.mode-hard.odd-even-play .card {
            background: var(--card-bg);
            border-color: var(--card-border);
        }
        body.mode-easy.odd-even-play .card {
            background: var(--card-bg);
            border-color: var(--card-border);
        }
        body.mode-hard.odd-even-play .pending-box {
            background: linear-gradient(135deg, rgba(239,68,68,0.16), rgba(251,146,60,0.1));
            border-color: rgba(248,113,113,0.42);
            box-shadow: inset 0 0 24px rgba(239,68,68,0.08);
        }
        body.mode-easy.odd-even-play .pending-box {
            background: linear-gradient(135deg, rgba(56,189,248,0.12), rgba(110,231,183,0.1));
            border-color: rgba(103,232,249,0.32);
        }
        body.mode-hard.odd-even-play .pending-box .timer { color: #fdba74; }
        body.mode-hard.odd-even-play .pending-box .bet-info .mult { color: #fb923c; }
        body.mode-hard.odd-even-play .btn-pick.hol {
            background: linear-gradient(145deg, #ef4444, #991b1b);
            box-shadow: 0 8px 0 #7f1d1d, 0 10px 24px rgba(239,68,68,0.5);
        }
        body.mode-hard.odd-even-play .btn-pick.jjak {
            background: linear-gradient(145deg, #ea580c, #9a3412);
            box-shadow: 0 8px 0 #7c2d12, 0 10px 24px rgba(251,146,60,0.45);
        }
        body.mode-hard.odd-even-play .btn-pick.hol:active:not(:disabled),
        body.mode-hard.odd-even-play .btn-pick.hol.punch {
            box-shadow: 0 2px 0 #7f1d1d, 0 4px 16px rgba(239,68,68,0.6), 0 0 32px rgba(239,68,68,0.35);
        }
        body.mode-hard.odd-even-play .btn-pick.jjak:active:not(:disabled),
        body.mode-hard.odd-even-play .btn-pick.jjak.punch {
            box-shadow: 0 2px 0 #7c2d12, 0 4px 16px rgba(251,146,60,0.55), 0 0 28px rgba(251,146,60,0.32);
        }
        body.mode-hard.odd-even-play .payback-card {
            border-color: rgba(251,146,60,0.22);
            background: linear-gradient(135deg, rgba(69,10,10,0.55), rgba(40,8,8,0.35));
        }
        body.mode-hard.odd-even-play .btn-payback {
            background: linear-gradient(135deg, rgba(239,68,68,0.28), rgba(251,146,60,0.2));
            border-color: rgba(251,146,60,0.5);
            color: #fed7aa;
        }
        body.mode-hard.odd-even-play .lock-banner {
            background: rgba(69,10,10,0.85);
            border-color: rgba(248,113,113,0.35);
            color: #fecaca;
        }
        body.mode-hard.odd-even-play .badge.pink {
            background: rgba(239,68,68,0.22);
            color: #fdba74;
        }
        body.mode-hard.odd-even-play .bet-input-row input:focus {
            border-color: #fb923c;
        }
        body.mode-easy.odd-even-play .bet-input-row input:focus {
            border-color: #6ee7b7;
        }
        body.mode-easy.odd-even-play .badge.pink {
            background: rgba(110,231,183,0.16);
            color: #99f6e4;
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
            margin: 0 0 8px;
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
        }
        .play-stack > .pending-box,
        .play-stack > #betCard.card {
            grid-row: 1;
            grid-column: 1;
            align-self: start;
            width: 100%;
            margin-bottom: 0;
            box-sizing: border-box;
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
        .bet-input-row { display: flex; gap: 8px; margin-top: 6px; }
        .bet-input-row input {
            flex: 1;
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
        .quick-bets {
            display: flex;
            flex-wrap: nowrap;
            gap: 5px;
            margin-top: 6px;
            overflow-x: auto;
            overflow-y: hidden;
            -webkit-overflow-scrolling: touch;
            overscroll-behavior-x: contain;
            scrollbar-width: thin;
            scrollbar-color: rgba(255,255,255,0.22) transparent;
            padding-bottom: 2px;
        }
        .quick-bets::-webkit-scrollbar { height: 4px; }
        .quick-bets::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.22);
            border-radius: 4px;
        }
        .btn-quick {
            flex: 0 0 auto;
            min-width: 52px;
            padding: 6px 8px;
            min-height: 32px;
            font-family: 'Noto Sans KR', sans-serif;
            font-size: 0.74rem;
            font-weight: 700;
            color: var(--text);
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            cursor: pointer;
            -webkit-tap-highlight-color: transparent;
            touch-action: manipulation;
        }
        .btn-quick:active { background: rgba(255,255,255,0.14); }
        .btn-quick:disabled,
        .btn-quick.btn-quick--below-min {
            opacity: 0.45;
            cursor: not-allowed;
        }
        .btn-quick:disabled:active { background: rgba(255,255,255,0.06); }
        .btn-quick.btn-quick--below-min { opacity: 0.35; }
        .btn-quick-swap {
            color: #9ef5d4;
            border-color: rgba(90, 220, 180, 0.4);
            background: rgba(30, 110, 85, 0.28);
        }
        .btn-quick-swap:active { background: rgba(50, 150, 115, 0.4); }
        .btn-quick-swap:disabled { opacity: 0.4; }

        .btn-swap-float {
            display: none;
            position: fixed;
            z-index: 90;
            bottom: max(14px, env(safe-area-inset-bottom));
            left: max(14px, env(safe-area-inset-left));
            padding: 10px 16px;
            min-height: 44px;
            min-width: 44px;
            border-radius: 999px;
            font-family: 'Noto Sans KR', sans-serif;
            font-size: 0.82rem;
            font-weight: 700;
            color: #9ef5d4;
            border: 1px solid rgba(90, 220, 180, 0.45);
            background: rgba(22, 95, 72, 0.92);
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.45);
            cursor: pointer;
            -webkit-tap-highlight-color: transparent;
            touch-action: manipulation;
            align-items: center;
            justify-content: center;
        }
        .btn-swap-float:active { background: rgba(40, 130, 100, 0.95); }
        .btn-swap-float:disabled { opacity: 0.4; cursor: not-allowed; }

        @media (max-width: 768px) {
            .btn-swap-float { display: flex; }
            .quick-bets .btn-quick-swap { display: none !important; }
            body.odd-even-play { padding-bottom: calc(58px + env(safe-area-inset-bottom)); }
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
            margin-top: 6px;
            background: linear-gradient(145deg, #7c2d12, #431407);
            border: 1px solid rgba(248,113,113,0.3);
        }

        .result {
            margin: 0;
            box-sizing: border-box;
            width: 100%;
        }
        /* 결과 슬롯: 항상 고정 높이 유지 (레이아웃 흔들림·깜빡임 방지) */
        .game-feedback {
            margin: 0 0 8px;
            display: block;
        }
        .result-wrap {
            width: 100%;
            height: 5rem;
            min-height: 5rem;
            display: flex;
            align-items: stretch;
        }
        .result.result-slot {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            height: 100%;
            padding: 6px 8px;
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
        /* 승·패·무: 결과 배너 */
        .result.result-slot.outcome {
            padding: 6px 8px;
            text-align: center;
            font-size: 0.8rem;
            gap: 3px;
        }
        .result.result-slot .outcome-head {
            font-size: 0.95rem;
            font-weight: 900;
            letter-spacing: -0.02em;
            margin: 0;
            line-height: 1.15;
            min-height: 0;
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
            min-height: 0;
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
            font-size: 0.82rem;
            font-weight: 900;
            margin: 0;
            min-height: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            letter-spacing: -0.01em;
            line-height: 1.2;
            flex: 0 0 auto;
        }
        .result.result-slot .outcome-amount.win { color: #fde68a; }
        .result.result-slot .outcome-amount.lose { color: #fca5a5; }
        .result.result-slot .outcome-amount.push { color: #ddd6fe; font-size: 0.72rem; font-weight: 700; }
        .result.result-slot .outcome-sub {
            font-size: 0.68rem;
            color: rgba(226,232,240,0.72);
            margin: 0;
            min-height: 0;
            line-height: 1.3;
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
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
            font-size: 0.9rem;
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
            font-size: 0.9rem;
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

        body.mode-hard.odd-even-play .streak-trail .st-pick.hol-t { color: #f87171; text-shadow: 0 0 14px rgba(239,68,68,0.45); }
        body.mode-hard.odd-even-play .streak-trail .st-pick.jjak-t { color: #fb923c; text-shadow: 0 0 14px rgba(251,146,60,0.4); }
        body.mode-easy.odd-even-play .streak-trail .st-pick.hol-t { color: #5eead4; text-shadow: 0 0 14px rgba(110,231,183,0.35); }
        body.mode-easy.odd-even-play .streak-trail .st-pick.jjak-t { color: #67e8f9; text-shadow: 0 0 14px rgba(103,232,249,0.35); }

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
            .result-wrap { height: 4.5rem; min-height: 4.5rem; }
            .result.result-slot { padding: 5px 6px; border-radius: 8px; }
            .result.result-slot.outcome { gap: 2px; }
            .result.result-slot .outcome-head { font-size: 0.88rem; }
            .result.result-slot .outcome-picks { font-size: 0.68rem; gap: 2px 5px; }
            .result.result-slot .outcome-streak { font-size: 0.64rem; padding: 1px 5px; }
            .result.result-slot .outcome-amount { font-size: 0.78rem; }
            .result.result-slot .outcome-amount.push { font-size: 0.68rem; }
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
            .btn-quick { min-height: 28px; font-size: 0.68rem; }
            .quick-bets { margin-top: 4px; }
            .pick-row.pick-row--start { margin-top: 5px; }
        }
    </style>
</head>
<body<?php
if ($game_need_code) {
    echo '';
} else {
    $body_mode = ($game_odds_mode === 'hard') ? 'hard' : 'easy';
    echo ' class="odd-even-play mode-' . htmlspecialchars($body_mode, ENT_QUOTES, 'UTF-8') . '"';
}
?>>
    <div class="wrap">
        <h1>홀 · 짝</h1>
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
                <span class="info-card__title-meta" title="<?php echo htmlspecialchars($game_name . ' | ' . number_format($game_point) . '냥 | ' . (int)$game_streak . '연승', ENT_QUOTES, 'UTF-8'); ?>">
                    <span id="stHeadNick"><?php echo htmlspecialchars($game_name, ENT_QUOTES, 'UTF-8'); ?></span><span class="info-card__sep">|</span><span id="stHeadPoint"><?php echo htmlspecialchars(game_보유냥_표시($game_point), ENT_QUOTES, 'UTF-8'); ?></span><span class="unit-nyang">냥</span><span class="info-card__sep">|</span><span id="stHeadStreak"><?php echo (int)$game_streak; ?>연승</span>
                </span>
            </h3>
        </div>

        <div class="card payback-card">
            <div class="payback-row">
                <span class="payback-label">💎 페이백 누적</span>
                <span class="payback-amount"><b id="stPayback"><?php echo number_format($game_payback_pool); ?></b><span class="unit-nyang">냥</span></span>
            </div>
            <p class="payback-hint">이지 배팅 1% · 하드 배팅 3% · 이지 승리 수수료 1% 누적</p>
            <button type="button" class="btn btn-payback" id="btnPayback"<?php echo ($game_payback_pool > 0) ? '' : ' disabled'; ?>>페이백 받기</button>
        </div>

        <div class="game-feedback">
            <div class="result-wrap">
                <div class="result result-slot is-idle" id="result" role="status" aria-live="polite"></div>
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
        <div class="pending-box<?php echo ($game_pending_bet <= 0) ? ' panel-hidden' : ''; ?>" id="pendingBox">
            <div class="bet-info">
                배팅 <span id="pbAmount"><?php echo number_format($game_pending_bet); ?></span>냥
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
        <div class="card<?php echo ($game_pending_bet > 0 || $game_room_lock) ? ' panel-hidden' : ''; ?>" id="betCard">
            <h3 class="bet-card__title">
                <span class="bet-card__title-label">💰 금액 선택</span>
                <span class="badge pink" id="streakBadge"<?php echo ($game_streak > 0) ? '' : ' style="display:none"'; ?>>연승 ×<span id="streakBadgeMult"><?php echo (int)$game_win_mult; ?></span></span>
                <span class="streak-trail streak-trail--in-h3" id="streakTrail" role="text" aria-label="이번 연승 픽 순서"></span>
            </h3>
            <div class="mode-row" id="modeRow">
                <button type="button" class="btn-mode<?php echo ($game_odds_mode === 'easy') ? ' active-easy' : ''; ?>" id="btnModeEasy" data-mode="easy">
                    이지모드
                    <small>유리한 확률 · 승리 10% 수수료</small>
                </button>
                <button type="button" class="btn-mode<?php echo ($game_odds_mode === 'hard') ? ' active-hard' : ''; ?>" id="btnModeHard" data-mode="hard">
                    하드모드
                    <small>무 8% · 패 +1 · 3~15% x2~x4</small>
                </button>
            </div>
            <div class="cost-grid">
            <div class="cost-line">
                <span>최소 배팅</span>
                <b class="blue" id="bcMin"><?php echo game_금액_축약($game_min_bet); ?>냥</b>
            </div>
            <div class="cost-line">
                <span>최대 배팅</span>
                <b class="gold" id="bcMax"><?php echo game_금액_축약($game_max_bet); ?>냥</b>
            </div>
            <div class="cost-line">
                <span>승 배수 / 패 배수</span>
                <b><span class="gold">×<span id="bcWin"><?php echo (int)$game_win_mult; ?></span></span> / <span class="accent">×<span id="bcLose"><?php echo (int)$game_lose_mult; ?></span></span></b>
            </div>
            </div>
            <div class="bet-input-row">
                <input type="text" id="betAmount" inputmode="numeric" placeholder="배팅 금액 (냥)" value="<?php echo htmlspecialchars($game_default_bet_display, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="quick-bets" id="quickBets"></div>
            <div class="pick-row pick-row--start">
                <button type="button" class="btn-pick hol" id="btnHolBet">홀</button>
                <button type="button" class="btn-pick jjak" id="btnJjakBet">짝</button>
            </div>
            <?php if ($game_streak >= 2) { ?>
            <button type="button" class="btn btn-giveup" id="btnGiveup">🏳️ 연승 포기</button>
            <?php } ?>
        </div>
        </div><!-- /.play-stack -->

        <div class="btn-refresh-wrap">
            <button type="button" class="btn" id="btnRefresh">새로고침</button>
        </div>

        <?php } // end has code ?>
    </div>

    <?php if (!$game_need_code) { ?>
    <button type="button" class="btn-swap-float btn-quick-swap" id="btnSwapFloat" title="본방냥 전액→게임냥 (5% 삭제)">💱 스왑</button>
    <?php } ?>

    <?php if (!$game_need_code) { ?>
    <script>
    (function() {
        var BET_TIERS = <?php
            $tiers_js = array();
            foreach (홀짝_배팅_티어표() as $t) {
                $tiers_js[] = array(
                    'cap' => (int)$t['cap'],
                    'min' => (int)$t['min'],
                    'quick' => array_map('intval', (array)$t['quick']),
                );
            }
            echo json_encode($tiers_js, JSON_UNESCAPED_UNICODE);
        ?>;
        var BET_1GYEONG = 10000000000000000; // 1경
        var BET_100GYEONG = 100 * BET_1GYEONG; // 100경 — 하드모드 강제 기준
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

        var state = {
            point: <?php echo (int)$game_point; ?>,
            newpoint: <?php echo json_encode($game_newpoint); ?>,
            streak: <?php echo (int)$game_streak; ?>,
            streak_max: <?php echo (int)$GAME_연승최대; ?>,
            streak_max_bet: <?php echo (int)$game_streak_max_bet; ?>,
            pending_bet: <?php echo (int)$game_pending_bet; ?>,
            pending_left: <?php echo (int)$game_pending_left; ?>,
            min_bet: <?php echo (int)$game_min_bet; ?>,
            max_bet: <?php echo (int)$game_max_bet; ?>,
            win_mult: <?php echo (int)$game_win_mult; ?>,
            lose_mult: <?php echo (int)$game_lose_mult; ?>,
            room_lock: <?php echo $game_room_lock ? json_encode($game_room_lock, JSON_UNESCAPED_UNICODE) : 'null'; ?>,
            web_room_mode: <?php echo json_encode(game_web_is_multi_room() ? 'multi' : 'single', JSON_UNESCAPED_UNICODE); ?>,
            timeout_sec: <?php echo (int)$GAME_타임아웃초; ?>,
            streak_picks: <?php echo json_encode($game_streak_picks, JSON_UNESCAPED_UNICODE); ?>,
            odds_mode: <?php echo json_encode($game_odds_mode, JSON_UNESCAPED_UNICODE); ?>,
            hard_mode_forced: <?php echo $game_hard_mode_forced ? 'true' : 'false'; ?>,
            payback_pool: <?php echo (int)$game_payback_pool; ?>,
            locked: false
        };

        function fmt(n) { return (n || 0).toLocaleString('ko-KR'); }
        function fmtNewpoint(n) {
            n = parseFloat(n) || 0;
            if (Math.abs(n - Math.round(n)) < 0.05) return fmt(Math.round(n));
            return n.toLocaleString('ko-KR', { minimumFractionDigits: 0, maximumFractionDigits: 1 });
        }
        function effectiveQuickAmounts(tier) {
            return (tier.quick || []).slice();
        }
        function resolveMaxBet(point) {
            var tier = getBetTier(point);
            var max = tier.min || 0;
            effectiveQuickAmounts(tier).forEach(function(v) {
                v = parseInt(v, 10) || 0;
                if (v > max) max = v;
            });
            return max;
        }
        function getBetTier(point) {
            point = Math.max(0, parseInt(point, 10) || 0);
            for (var i = 0; i < BET_TIERS.length; i++) {
                if (point < BET_TIERS[i].cap) return BET_TIERS[i];
            }
            return BET_TIERS[BET_TIERS.length - 1];
        }
        /** 만·억·조·경 축약 (화면 최소/최대 배팅 표시용) */
        function fmtAmtShort(n) {
            n = Math.max(0, Math.floor(parseInt(n, 10) || 0));
            if (n <= 0) return '0';
            var parts = [];
            if (typeof BigInt !== 'undefined' && (n >= 9007199254740992 || String(n).length > 15)) {
                var v = BigInt(String(n));
                var G = 10000000000000000n, J = 1000000000000n, E = 100000000n, M = 10000n;
                if (v >= G) { parts.push((v / G).toString() + '경'); v %= G; }
                if (v >= J) { parts.push((v / J).toString() + '조'); v %= J; }
                if (v >= E) { parts.push((v / E).toString() + '억'); v %= E; }
                if (v >= M) { parts.push((v / M).toString() + '만'); v %= M; }
                if (v > 0n) parts.push(v.toString());
                return parts.join('');
            }
            var gyeong = Math.floor(n / 10000000000000000);
            n %= 10000000000000000;
            var jo = Math.floor(n / 1000000000000);
            n %= 1000000000000;
            var eok = Math.floor(n / 100000000);
            n %= 100000000;
            var man = Math.floor(n / 10000);
            n %= 10000;
            if (gyeong > 0) parts.push(gyeong + '경');
            if (jo > 0) parts.push(jo + '조');
            if (eok > 0) parts.push(eok + '억');
            if (man > 0) parts.push(man + '만');
            if (n > 0) parts.push(String(n));
            return parts.join('');
        }
        function fmtPointShort(n) {
            n = Math.floor(parseInt(n, 10) || 0);
            var neg = n < 0;
            var abs = Math.abs(n);
            if (abs < 100000000) return neg ? '-0' : '0';
            var parts = [];
            if (typeof BigInt !== 'undefined' && (abs >= 9007199254740992 || String(abs).length > 15)) {
                var v = BigInt(String(abs));
                var G = 10000000000000000n, J = 1000000000000n, E = 100000000n;
                var gyeong = v / G;
                v %= G;
                var jo = v / J;
                v %= J;
                var eok = v / E;
                if (gyeong > 0n) parts.push(gyeong.toLocaleString('ko-KR') + '경');
                if (jo > 0n) parts.push(jo.toLocaleString('ko-KR') + '조');
                if (eok > 0n) parts.push(eok.toLocaleString('ko-KR') + '억');
            } else {
                var gyeong = Math.floor(abs / 10000000000000000);
                abs %= 10000000000000000;
                var jo = Math.floor(abs / 1000000000000);
                abs %= 1000000000000;
                var eok = Math.floor(abs / 100000000);
                if (gyeong > 0) parts.push(gyeong.toLocaleString('ko-KR') + '경');
                if (jo > 0) parts.push(jo.toLocaleString('ko-KR') + '조');
                if (eok > 0) parts.push(eok.toLocaleString('ko-KR') + '억');
            }
            var txt = parts.length ? parts.join('') : '0';
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
            var winArr = [3, 5, 7, 9, 11];
            var loseEasy = [2, 4, 6, 8, 10];
            var loseHard = [3, 5, 7, 9, 11];
            var s = Math.max(0, parseInt(streak, 10) || 0);
            if (s > 4) s = 4;
            var loseArr = (mode === 'hard') ? loseHard : loseEasy;
            return { win: winArr[s], lose: loseArr[s] };
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
                var amt = (j.win_amount != null) ? '+' + fmtAmtShort(j.win_amount) + '냥' : '';
                var subParts = [];
                if (j.streak_completed) {
                    subParts.push('🏆 ' + (state.streak_max || 5) + '연승 완주! 다음 판부터 첫 도전');
                }
                if (parseInt(j.win_fee, 10) > 0) {
                    subParts.push('이지모드 수수료 10% -' + fmtAmtShort(j.win_fee) + '냥');
                }
                if (parseInt(j.hard_bonus_mult, 10) > 1 || j.hard_x2) {
                    var hm = parseInt(j.hard_bonus_mult, 10) || 2;
                    subParts.push('🎊 하드모드 x' + hm + ' 보너스!');
                }
                var subHtml = subParts.length
                    ? '<div class="outcome-sub">' + subParts.map(escHtml).join('<br>') + '</div>'
                    : '';
                return '<div class="outcome-head">✅ 승리!</div>'
                    + '<div class="outcome-picks"><span>' + picks + '</span>'
                    + (streak ? '<span class="outcome-streak">🔥 ' + escHtml(streak) + '</span>' : '') + '</div>'
                    + '<div class="outcome-amount win">' + escHtml(amt || '—') + '</div>'
                    + subHtml;
            }
            if (r === 'lose') {
                var vs = escHtml(j.user_pick || '') + ' ≠ ' + escHtml(j.system_pick || '');
                var loss = (j.loss != null) ? '-' + fmtAmtShort(j.loss) + '냥' : '';
                var mult = j.played_lose_mult ? ' (×' + parseInt(j.played_lose_mult, 10) + ')' : '';
                return '<div class="outcome-head">❌ 패배</div>'
                    + '<div class="outcome-picks"><span>' + vs + '</span></div>'
                    + '<div class="outcome-amount lose">' + escHtml(loss + mult || '—') + '</div>'
                    + '<div class="outcome-sub">연승 리셋</div>';
            }
            if (r === 'push') {
                var detail = String(j.data || '').replace(/^🤝\s*무승부!\s*/u, '').replace(/\s*·\s*연승 리셋\s*$/u, '');
                var pushPicks = escHtml(j.user_pick || '') + ' · ' + escHtml(j.system_pick || '무(3)');
                return '<div class="outcome-head">🤝 무승부</div>'
                    + '<div class="outcome-picks"><span>' + pushPicks + '</span></div>'
                    + '<div class="outcome-amount push">' + escHtml(detail || '환급') + '</div>'
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
            if (typeof j.point === 'number') state.point = j.point;
            if (typeof j.max_bet === 'number') {
                state.max_bet = j.max_bet;
            } else if (typeof j.point === 'number') {
                state.max_bet = resolveMaxBet(j.point);
            }
            var nextStreak = pickStreakFromResponse(j);
            if (nextStreak !== null) {
                state.streak = nextStreak;
            }
            if (typeof j.streak_max_bet === 'number') state.streak_max_bet = j.streak_max_bet;
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
            }
            if (typeof j.min_bet === 'number') state.min_bet = j.min_bet;
            if (typeof j.win_mult === 'number') state.win_mult = j.win_mult;
            if (typeof j.lose_mult === 'number') state.lose_mult = j.lose_mult;
            if (typeof j.payback_pool === 'number') state.payback_pool = j.payback_pool;
            if (j.odds_mode === 'easy' || j.odds_mode === 'hard') state.odds_mode = j.odds_mode;
            if (typeof j.hard_mode_forced === 'boolean') state.hard_mode_forced = j.hard_mode_forced;
            applyStreakMultFromState();
            state.pending_bet = 0;
            state.pending_left = 0;
            if (j.result === 'win') {
                if (state.streak > 0) {
                    // 연승 승리 후: 이번에 건 금액·연승 하한 중 큰 값 유지 (100억 승리 → 100억·천억 가능, 10억 불가)
                    var keepBet = Math.max(playedBet, state.min_bet);
                    if (keepBet > state.max_bet) keepBet = state.max_bet;
                    setBet(keepBet);
                } else {
                    setBet(state.min_bet);
                }
            } else if (prevStreak > 0 && state.streak === 0) {
                setBet(state.min_bet);
            } else {
                syncBetInputToMin();
            }
            enforceHardModeIfNeeded();
            renderAll();
        }

        function mergeStatusStreakFields(j) {
            var incomingStreak = clampStreak(j.streak);
            var incomingMaxBet = Math.max(0, parseInt(j.streak_max_bet, 10) || 0);
            var incomingMinBet = Math.max(0, parseInt(j.min_bet, 10) || 0);
            var guard = shouldGuardActionState();
            var allowDecrease = !guard || incomingStreak === 0;
            var blockedDowngrade = guard && !allowDecrease && incomingStreak < state.streak;
            if (allowDecrease || incomingStreak >= state.streak) {
                state.streak = incomingStreak;
            }
            if (allowDecrease || incomingMaxBet >= state.streak_max_bet) {
                state.streak_max_bet = incomingMaxBet;
            }
            if (allowDecrease || incomingMinBet >= state.min_bet) {
                state.min_bet = incomingMinBet;
            }
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
                state.point = j.point;
                if (typeof j.newpoint === 'number') state.newpoint = j.newpoint;
                var streakAccepted = mergeStatusStreakFields(j);
                state.streak_picks = Array.isArray(j.streak_picks) ? j.streak_picks.slice(0, clampStreak(state.streak) || 0) : [];
                state.pending_bet = j.pending_bet;
                state.pending_left = j.pending_left;
                state.max_bet = typeof j.max_bet === 'number' ? j.max_bet : resolveMaxBet(j.point);
                if (streakAccepted || !shouldGuardActionState()) {
                    state.win_mult = j.win_mult;
                    state.lose_mult = j.lose_mult;
                }
                applyStreakMultFromState();
                state.room_lock = j.room_lock;
                if (j.web_room_mode) state.web_room_mode = j.web_room_mode;
                if (j.odds_mode === 'easy' || j.odds_mode === 'hard') state.odds_mode = j.odds_mode;
                if (typeof j.hard_mode_forced === 'boolean') state.hard_mode_forced = j.hard_mode_forced;
                if (typeof j.payback_pool === 'number') state.payback_pool = j.payback_pool;
                if (prevStreak > 0 && state.streak === 0 && state.pending_bet <= 0) {
                    setBet(state.min_bet);
                } else {
                    syncBetInputToMin();
                }
                enforceHardModeIfNeeded();
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
            if (mode === 'hard') {
                return '❎ 취소 (전액 환급 · 수수료 없음)';
            }
            return '❎ 취소 (90% 환급 · 10% 수수료 금고50%·로또50%)';
        }

        function timeoutHintText(mode) {
            if (mode === 'hard') {
                return '약 30분 무응답 시 타임아웃 (전액 환급 · 수수료 없음)';
            }
            return '약 30분 무응답 시 타임아웃 (50% 환급 · 50% 수수료 금고·로또 각 반)';
        }

        function hardModeForced() {
            return (parseInt(state.point, 10) || 0) >= BET_100GYEONG;
        }

        function syncHardModeForcedFlag() {
            state.hard_mode_forced = hardModeForced();
        }

        function enforceHardModeIfNeeded() {
            syncHardModeForcedFlag();
            if (!hardModeForced()) return;
            if (state.odds_mode === 'hard') return;
            if (state.locked || state.pending_bet > 0) return;
            doSetMode('hard');
        }

        function giveupHintText(mode) {
            if (mode === 'hard') {
                return '🏳️ 연승 포기 (최고배팅 1% · 로또)';
            }
            return '🏳️ 연승 포기 (최고배팅 20% · 금고·로또 50%)';
        }

        function applyModeTheme() {
            var mode = (state.odds_mode === 'hard') ? 'hard' : 'easy';
            document.body.classList.remove('mode-easy', 'mode-hard');
            document.body.classList.add('mode-' + mode);
            var meta = document.querySelector('meta[name="theme-color"]');
            if (meta) meta.content = (mode === 'hard') ? '#120808' : '#0b1628';
        }

        function updateModeUI() {
            syncHardModeForcedFlag();
            applyModeTheme();
            var easy = $('btnModeEasy');
            var hard = $('btnModeHard');
            var mode = (state.odds_mode === 'hard') ? 'hard' : 'easy';
            var forced = hardModeForced();
            var dis = state.locked || state.pending_bet > 0;
            if (easy) {
                easy.className = 'btn-mode' + (mode === 'easy' ? ' active-easy' : '');
                easy.disabled = dis || forced;
                easy.title = forced ? '보유냥 100경 이상은 하드모드만 선택할 수 있어요.' : '';
            }
            if (hard) {
                hard.className = 'btn-mode' + (mode === 'hard' ? ' active-hard' : '');
                hard.disabled = dis;
                hard.title = '';
            }
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
            if (el) el.textContent = fmt(state.payback_pool || 0);
            var btn = $('btnPayback');
            if (btn) {
                var canClaim = (state.payback_pool || 0) > 0 && state.pending_bet <= 0 && !state.locked;
                btn.disabled = !canClaim;
            }
        }

        function renderBetCard() {
            var stHp = $('stHeadPoint');
            if (stHp) stHp.textContent = fmtPointShort(state.point);
            var stMeta = document.querySelector('.info-card__title-meta');
            var stNn = $('stHeadNick');
            if (stMeta && stNn) {
                stMeta.title = stNn.textContent + ' | ' + fmt(state.point) + '냥 | ' + state.streak + '연승';
            }
            var bcMin = $('bcMin');
            if (bcMin) bcMin.textContent = fmtAmtShort(state.min_bet) + '냥';
            var bcMax = $('bcMax');
            if (bcMax) bcMax.textContent = fmtAmtShort(state.max_bet) + '냥';
            var bcWin = $('bcWin');
            if (bcWin) bcWin.textContent = state.win_mult;
            var bcLose = $('bcLose');
            if (bcLose) bcLose.textContent = state.lose_mult;
            var streakBadge = $('streakBadge');
            var streakBadgeMult = $('streakBadgeMult');
            if (streakBadge) {
                if (state.streak > 0) {
                    streakBadge.style.display = '';
                    if (streakBadgeMult) streakBadgeMult.textContent = state.win_mult;
                } else {
                    streakBadge.style.display = 'none';
                }
            }
            var holBet = $('btnHolBet'), jjakBet = $('btnJjakBet');
            var disStartPick = state.locked || state.pending_bet > 0 || !!(state.room_lock && state.room_lock.nick);
            if (holBet) holBet.disabled = disStartPick;
            if (jjakBet) jjakBet.disabled = disStartPick;
            // 진행 예정 또는 대기 상태가 아닐 때, 입력 칸 비어 있으면 최소(기본 100만냥 등) 채우기
            var bc = $('betCard');
            if (bc && state.pending_bet <= 0 && (!state.room_lock || !state.room_lock.nick) && !bc.classList.contains('panel-hidden')) {
                var elAmt = $('betAmount');
                if (elAmt) {
                    var raw = (elAmt.value || '').replace(/[^0-9]/g, '');
                    var n = parseInt(raw || '0', 10) || 0;
                    if (n <= 0 || n < state.min_bet) {
                        setBet(state.min_bet);
                    }
                }
            }
            // 연승 포기 버튼 동적 표시
            var giveup = $('btnGiveup');
            if (state.streak >= 2 && !giveup) {
                giveup = document.createElement('button');
                giveup.type = 'button';
                giveup.className = 'btn btn-giveup';
                giveup.id = 'btnGiveup';
                giveup.textContent = giveupHintText(state.odds_mode === 'hard' ? 'hard' : 'easy');
                giveup.addEventListener('click', doGiveUp);
                var bc = $('betCard');
                if (bc) bc.appendChild(giveup);
            } else if (state.streak < 2 && giveup) {
                giveup.parentNode.removeChild(giveup);
            } else if (giveup) {
                giveup.textContent = giveupHintText(state.odds_mode === 'hard' ? 'hard' : 'easy');
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
        }

        function parseBet() {
            var raw = ($('betAmount').value || '').replace(/[^0-9]/g, '');
            return parseInt(raw || '0', 10) || 0;
        }
        function updateQuickButtons() {
            document.querySelectorAll('#quickBets .btn-quick[data-fixed]').forEach(function(b) {
                var fixed = b.getAttribute('data-fixed');
                var base = b.getAttribute('data-label') || b.textContent.trim();
                var unit = parseInt(fixed, 10) || 0;
                var belowStreakMin = state.streak > 0 && unit > 0 && unit < state.min_bet;
                b.textContent = base;
                b.disabled = belowStreakMin;
                b.classList.toggle('btn-quick--below-min', belowStreakMin);
            });
            var swapBtn = document.querySelector('#quickBets .btn-quick-swap');
            var floatSwapBtn = $('btnSwapFloat');
            var noNp = (parseFloat(state.newpoint) || 0) < 0.1;
            var busy = state.pending_bet > 0 || state.locked;
            [swapBtn, floatSwapBtn].forEach(function(btn) {
                if (btn) btn.disabled = noNp || busy;
            });
        }

        function getQuickBetList() {
            var point = state.point || 0;
            var tier = getBetTier(point);
            return effectiveQuickAmounts(tier).map(function(fixed) {
                return { fixed: fixed, label: fmtAmtShort(fixed) };
            });
        }

        function renderQuickBets() {
            var wrap = $('quickBets');
            if (!wrap) return;
            var point = state.point || 0;
            var tier = getBetTier(point);
            var tierKey = String(tier.cap);
            var hasSwap = wrap.querySelector('.btn-quick-swap');
            if (wrap.getAttribute('data-tier-cap') === tierKey && wrap.children.length > 0 && hasSwap) {
                updateQuickButtons();
                return;
            }
            wrap.setAttribute('data-tier-cap', tierKey);
            var list = getQuickBetList();
            wrap.innerHTML = list.map(function(item) {
                return '<button type="button" class="btn-quick" data-fixed="' + item.fixed + '" data-label="' + item.label + '">' + item.label + '</button>';
            }).join('')
                + '<button type="button" class="btn-quick btn-quick-swap" data-swap="1" title="본방냥 전액→게임냥 (5% 삭제)">💱 스왑</button>';
            updateQuickButtons();
        }
        function setBet(v) {
            setBetFromProgram = true;
            v = Math.max(0, Math.floor(v));
            if (v > state.max_bet) v = state.max_bet;
            $('betAmount').value = v ? fmt(v) : '';
            setBetFromProgram = false;
        }
        /** 연승 중 최고 배팅 하한(min_bet)보다 입력이 작으면 맞춤 (큰 배팅 후 100만 등으로 꼬이는 것 방지) */
        function syncBetInputToMin() {
            var el = $('betAmount');
            if (!el) return;
            var cur = parseBet();
            if (cur < state.min_bet) {
                setBet(state.min_bet);
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
                if (typeof j.point === 'number') state.point = j.point;
                if (typeof j.newpoint === 'number') state.newpoint = j.newpoint;
                if (typeof j.min_bet === 'number') state.min_bet = j.min_bet;
                if (typeof j.max_bet === 'number') {
                    state.max_bet = j.max_bet;
                } else {
                    state.max_bet = resolveMaxBet(state.point);
                }
                if (j.odds_mode === 'easy' || j.odds_mode === 'hard') state.odds_mode = j.odds_mode;
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
            if ((state.payback_pool || 0) <= 0) return;
            if (state.pending_bet > 0) {
                window.alert('⏳ 진행 중인 판이 있을 때는 페이백을 받을 수 없어요.');
                return;
            }
            if (!confirm('페이백 ' + fmt(state.payback_pool) + '냥을 게임냥으로 받을까요?')) return;
            state.locked = true;
            renderPayback();
            ajax({ action: 'claim_payback' }, function(j) {
                state.locked = false;
                if (!j.ok) {
                    window.alert(j.data || '페이백 수령 실패');
                    refreshStatus();
                    return;
                }
                if (typeof j.point === 'number') state.point = j.point;
                if (typeof j.payback_pool === 'number') state.payback_pool = j.payback_pool;
                if (typeof j.min_bet === 'number') state.min_bet = j.min_bet;
                if (typeof j.max_bet === 'number') state.max_bet = j.max_bet;
                if (j.odds_mode === 'easy' || j.odds_mode === 'hard') state.odds_mode = j.odds_mode;
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
            var amt = parseBet();
            if (amt < state.min_bet) {
                window.alert('❌ 연승 중 최소 ' + fmt(state.min_bet) + '냥 이상만 가능합니다.\n(이번 연승 최고 배팅보다 낮은 금액은 걸 수 없어요. 더 높은 금액은 가능)');
                return;
            }
            if (amt > state.max_bet) {
                window.alert('❌ 최대 ' + fmt(state.max_bet) + '냥 까지 가능합니다.');
                return;
            }
            state.locked = true;
            renderAll();
            showBusy('배팅·정산 처리 중…');
            ajax({ action: 'bet_pick', amount: amt, pick: pick }, function(j) {
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
            var mode = (state.odds_mode === 'hard') ? 'hard' : 'easy';
            var msg = (mode === 'hard')
                ? '배팅을 취소할까요?\n(전액 환급 · 수수료 없음)'
                : '배팅을 취소할까요?\n(90% 환급 · 10% 수수료는 금고·로또 각 50%로 적립)';
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
            var mode = (state.odds_mode === 'hard') ? 'hard' : 'easy';
            var msg = (mode === 'hard')
                ? '연승을 포기할까요?\n(최고 배팅의 1% 수수료 → 로또 · 연승 리셋)'
                : '연승을 포기할까요?\n(최고 배팅의 20% 수수료 · 금고·로또 각 50% · 연승 리셋)';
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
            if (state.locked) return;
            if (state.pending_bet > 0) return;
            if (mode !== 'easy' && mode !== 'hard') return;
            if (mode === 'easy' && hardModeForced()) {
                window.alert('보유냥 100경 이상은 하드모드만 선택할 수 있어요.');
                return;
            }
            if (state.odds_mode === mode) return;
            state.locked = true;
            ajax({ action: 'set_mode', mode: mode }, function(j) {
                state.locked = false;
                if (!j.ok) {
                    window.alert(j.data || '모드 변경 실패');
                    return;
                }
                if (j.odds_mode === 'easy' || j.odds_mode === 'hard') {
                    state.odds_mode = j.odds_mode;
                }
                if (typeof j.hard_mode_forced === 'boolean') state.hard_mode_forced = j.hard_mode_forced;
                if (typeof j.win_mult === 'number') state.win_mult = j.win_mult;
                if (typeof j.lose_mult === 'number') state.lose_mult = j.lose_mult;
                updateModeUI();
                if (j.data) showResult(j.data, 'info');
            });
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
        var btnModeEasy = $('btnModeEasy');
        var btnModeHard = $('btnModeHard');
        if (btnModeEasy) btnModeEasy.addEventListener('click', function() { doSetMode('easy'); });
        if (btnModeHard) btnModeHard.addEventListener('click', function() { doSetMode('hard'); });
        var initialGiveup = $('btnGiveup');
        if (initialGiveup) initialGiveup.addEventListener('click', doGiveUp);
        $('btnRefresh').addEventListener('click', function() {
            hideResult();
            refreshStatus();
        });
        var btnPayback = $('btnPayback');
        if (btnPayback) btnPayback.addEventListener('click', doClaimPayback);
        var btnSwapFloat = $('btnSwapFloat');
        if (btnSwapFloat) {
            btnSwapFloat.addEventListener('click', function() {
                if (!btnSwapFloat.disabled) doSwapNp();
            });
        }
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
                    var unit = parseInt(fixed, 10) || 0;
                    if (unit <= 0 || b.disabled) return;
                    var next = state.streak > 0 ? Math.max(unit, state.min_bet) : unit;
                    if (next < state.min_bet) {
                        window.alert('❌ 연승 중 최소 ' + fmt(state.min_bet) + '냥 이상만 가능합니다.');
                        return;
                    }
                    if (next > state.max_bet) {
                        window.alert('❌ 최대 ' + fmt(state.max_bet) + '냥까지 가능합니다.');
                        return;
                    }
                    setBet(next);
                } else if (amt) {
                    var cur2 = parseBet();
                    var next2 = cur2 + parseInt(amt, 10);
                    if (next2 < state.min_bet) {
                        window.alert('❌ 연승 중 최소 ' + fmt(state.min_bet) + '냥 이상만 가능합니다.');
                        return;
                    }
                    if (next2 > state.max_bet) {
                        window.alert('❌ 최대 ' + fmt(state.max_bet) + '냥까지 가능합니다.');
                        return;
                    }
                    setBet(next2);
                }
            });
        }
        // 키보드 단축키: 1=홀, 2=짝, 3=첫 빠른배팅
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
            if (k === '3' || k === 'Numpad3') {
                var quickFirst = document.querySelector('#quickBets .btn-quick');
                if (quickFirst) {
                    quickFirst.click();
                    e.preventDefault();
                }
                return;
            }
            if (k === '4' || k === 'Numpad4') {
                var giveupBtn = $('btnGiveup');
                if (giveupBtn && !giveupBtn.disabled) {
                    giveupBtn.click();
                    e.preventDefault();
                }
            }
        });
        // 입력 시 천단위 포맷
        $('betAmount').addEventListener('input', function() {
            var v = parseBet();
            $('betAmount').value = v ? fmt(v) : '';
        });

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

        // 초기 렌더
        renderAll();
        var pbTimeEl = $('pbTimeText');
        if (pbTimeEl) pbTimeEl.textContent = fmtSec(state.pending_left);
    })();
    </script>
    <?php } ?>
</body>
</html>
