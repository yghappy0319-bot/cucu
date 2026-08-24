<?php
/**
 * 스톱워치 그룹 게임 웹
 * - 시스템이 라운드마다 목표 시간 선정 (전원 동일)
 * - 친구들이 1000억 내고 같은 목표에 도전 · 오차 최소 1명이 팟 획득
 *
 * 접속: ?code=xxx
 * action=status | start | stop
 */

$SW_참가비 = 100000000000; // 1000억
$SW_라운드초 = 600;         // 라운드 10분
$SW_시도초 = 15;            // 1회 시도 최대 15초
$SW_목표밀리초목록 = [3000, 3500, 4000, 4500, 5000, 5500, 6000, 6500, 7000, 7500, 8000];

require_once __DIR__ . '/odd_even_guards.php';

function sw_json($data) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function sw_auth($code) {
    $code = is_string($code) ? trim($code) : '';
    if ($code === '') {
        return null;
    }
    $esc = addslashes($code);
    $row = db_select("SELECT idx, name, point FROM tb_member WHERE code = '{$esc}' LIMIT 1");
    return !empty($row['name']) ? $row : null;
}

function sw_ms_label($ms) {
    return number_format(max(0, (int)$ms) / 1000, 2, '.', '');
}

function sw_금액_축약($n) {
    if (function_exists('게임냥_안전표시')) {
        $txt = 게임냥_안전표시($n, '');
        return $txt === '' ? '0' : $txt;
    }
    if (function_exists('랭킹_게임냥표시')) {
        return 랭킹_게임냥표시($n, '');
    }
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
    if ($jo > 0) {
        $parts[] = $jo . '조';
    }
    if ($eok > 0) {
        $parts[] = $eok . '억';
    }
    if ($man > 0) {
        $parts[] = $man . '만';
    }
    return $parts ? implode('', $parts) : number_format($n);
}

function sw_round_row($round_id) {
    $id = (int)$round_id;
    if ($id <= 0) {
        return null;
    }
    $row = @db_select("SELECT * FROM tb_stopwatch_round WHERE idx = {$id} LIMIT 1");
    return is_array($row) ? $row : null;
}

function sw_open_round_row() {
    $row = @db_select("SELECT * FROM tb_stopwatch_round WHERE status = 'open' ORDER BY idx DESC LIMIT 1");
    return is_array($row) ? $row : null;
}

function sw_new_round() {
    global $SW_참가비, $SW_라운드초, $SW_목표밀리초목록;
    $목표 = (int)$SW_목표밀리초목록[array_rand($SW_목표밀리초목록)];
    $fee = (int)$SW_참가비;
    $sec = max(60, (int)$SW_라운드초);
    @db_query("
      INSERT INTO tb_stopwatch_round (target_ms, entry_fee, pot, player_count, status, ends_at)
      VALUES ({$목표}, {$fee}, 0, 0, 'open', DATE_ADD(NOW(), INTERVAL {$sec} SECOND))
    ");
    return sw_open_round_row();
}

function sw_timeout_playing_entries($round_id, $SW_시도초) {
    $rid = (int)$round_id;
    $max_ms = max(1000, (int)$SW_시도초 * 1000);
    $rs = @db_query("
      SELECT idx, nick, bet, started_at
      FROM tb_stopwatch_entry
      WHERE round_id = {$rid} AND status = 'playing' AND started_at > 0
    ");
    if (!$rs) {
        return;
    }
    $now = microtime(true);
    while ($row = @mysqli_fetch_assoc($rs)) {
        $elapsed_ms = (int)round(($now - (float)$row['started_at']) * 1000);
        if ($elapsed_ms < $max_ms) {
            continue;
        }
        $eid = (int)$row['idx'];
        $닉_esc = addslashes((string)$row['nick']);
        @db_query("
          UPDATE tb_stopwatch_entry
          SET status = 'timeout', elapsed_ms = {$elapsed_ms}, diff_ms = 999999,
              finished_at = NOW()
          WHERE idx = {$eid} LIMIT 1
        ");
        $round = sw_round_row($rid);
        if ($round) {
            @db_query("INSERT INTO tb_stopwatch_log (round_id, nick, bet, target_ms, elapsed_ms, diff_ms, result, delta_point)
              VALUES ({$rid}, '{$닉_esc}', " . (int)$row['bet'] . ", " . (int)$round['target_ms'] . ", {$elapsed_ms}, 999999, 'timeout', 0)");
        }
    }
}

function sw_close_round(array $round) {
    global $SW_시도초;
    $rid = (int)$round['idx'];
    if ($rid <= 0 || ($round['status'] ?? '') !== 'open') {
        return null;
    }

    sw_timeout_playing_entries($rid, $SW_시도초);

    $winner = @db_select("
      SELECT nick, diff_ms, elapsed_ms
      FROM tb_stopwatch_entry
      WHERE round_id = {$rid} AND status = 'done' AND diff_ms IS NOT NULL
      ORDER BY diff_ms ASC, finished_at ASC
      LIMIT 1
    ");

    $pot = (int)($round['pot'] ?? 0);
    $target = (int)($round['target_ms'] ?? 0);
    $winner_nick = '';
    $payout = 0;
    $fee = 0;

    if (is_array($winner) && !empty($winner['nick']) && $pot > 0) {
        $winner_nick = trim((string)$winner['nick']);
        $winner_esc = addslashes($winner_nick);
        $fee = (int)floor($pot * 10 / 100);
        $payout = $pot - $fee;
        if ($payout > 0) {
            db_query("UPDATE tb_member SET point = point + {$payout} WHERE name = '{$winner_esc}' LIMIT 1");
            홀짝_지급로그('스톱워치-우승', $winner_nick, $fee, $payout);
            if ($fee > 0) {
                홀짝_수수료_금고로또배분($fee);
            }
        }
        @db_query("INSERT INTO tb_stopwatch_log (round_id, nick, bet, target_ms, elapsed_ms, diff_ms, result, delta_point)
          VALUES ({$rid}, '{$winner_esc}', 0, {$target}, " . (int)$winner['elapsed_ms'] . ", " . (int)$winner['diff_ms'] . ", 'win', {$payout})");
    } elseif ($pot > 0) {
        홀짝_수수료_금고로또배분($pot);
    }

    $winner_sql = $winner_nick !== '' ? "'" . addslashes($winner_nick) . "'" : 'NULL';
    @db_query("UPDATE tb_stopwatch_round SET status = 'closed', winner_nick = {$winner_sql}, closed_at = NOW() WHERE idx = {$rid} LIMIT 1");

    return [
        'round_id' => $rid,
        'target_ms' => $target,
        'pot' => $pot,
        'winner_nick' => $winner_nick,
        'winner_diff_ms' => is_array($winner) ? (int)($winner['diff_ms'] ?? 0) : null,
        'winner_elapsed_ms' => is_array($winner) ? (int)($winner['elapsed_ms'] ?? 0) : null,
        'payout' => $payout,
    ];
}

/** 만료 라운드 정산 후 새 라운드 확보 */
function sw_sync_rounds() {
    global $SW_라운드초, $SW_시도초;
    $closed = [];
    $rs = @db_query("
      SELECT * FROM tb_stopwatch_round
      WHERE status = 'open' AND ends_at <= NOW()
      ORDER BY idx ASC
    ");
    if ($rs) {
        while ($row = @mysqli_fetch_assoc($rs)) {
            $result = sw_close_round($row);
            if ($result) {
                $closed[] = $result;
            }
        }
    }
    $open = sw_open_round_row();
    if (!$open) {
        $open = sw_new_round();
    } else {
        sw_timeout_playing_entries((int)$open['idx'], $SW_시도초);
    }
    return ['open' => $open, 'closed' => $closed];
}

function sw_entry_row($round_id, $닉_esc) {
    $rid = (int)$round_id;
    $row = @db_select("
      SELECT * FROM tb_stopwatch_entry
      WHERE round_id = {$rid} AND nick = '{$닉_esc}'
      LIMIT 1
    ");
    return is_array($row) ? $row : null;
}

function sw_leaderboard($round_id, $limit = 12) {
    $rid = (int)$round_id;
    $limit = max(1, min(30, (int)$limit));
    $rs = @db_query("
      SELECT nick, elapsed_ms, diff_ms, status
      FROM tb_stopwatch_entry
      WHERE round_id = {$rid} AND status IN ('done','timeout')
      ORDER BY
        CASE WHEN status = 'done' THEN 0 ELSE 1 END,
        diff_ms ASC,
        finished_at ASC
      LIMIT {$limit}
    ");
    $out = [];
    if ($rs) {
        $rank = 1;
        while ($row = @mysqli_fetch_assoc($rs)) {
            $out[] = [
                'rank' => $rank++,
                'nick' => (string)$row['nick'],
                'elapsed_ms' => (int)($row['elapsed_ms'] ?? 0),
                'diff_ms' => (int)($row['diff_ms'] ?? 0),
                'status' => (string)$row['status'],
            ];
        }
    }
    return $out;
}

function sw_round_public(array $round, $닉_esc = '') {
    $left = 0;
    if (!empty($round['ends_at'])) {
        $left = max(0, (int)strtotime($round['ends_at']) - time());
    }
    $my = $닉_esc !== '' ? sw_entry_row((int)$round['idx'], $닉_esc) : null;
    $my_status = 'none';
    $my_playing = false;
    $my_done = false;
    if ($my) {
        $my_status = (string)($my['status'] ?? 'playing');
        $my_playing = ($my_status === 'playing' && (float)($my['started_at'] ?? 0) > 0);
        $my_done = in_array($my_status, ['done', 'timeout'], true);
    }
    return [
        'round_id' => (int)$round['idx'],
        'target_ms' => (int)$round['target_ms'],
        'entry_fee' => (int)$round['entry_fee'],
        'pot' => (int)$round['pot'],
        'player_count' => (int)$round['player_count'],
        'round_left_sec' => $left,
        'my_status' => $my_status,
        'my_playing' => $my_playing ? 1 : 0,
        'my_done' => $my_done ? 1 : 0,
        'my_elapsed_ms' => $my && $my_done ? (int)($my['elapsed_ms'] ?? 0) : 0,
        'my_diff_ms' => $my && $my_done ? (int)($my['diff_ms'] ?? 0) : 0,
        'my_token' => $my ? (string)($my['token'] ?? '') : '',
        'leaderboard' => sw_leaderboard((int)$round['idx']),
    ];
}

function sw_status_payload($닉, $닉_esc, $회원) {
    $sync = sw_sync_rounds();
    $round = $sync['open'];
    if (!$round) {
        return ['ok' => false, 'data' => '❌ 라운드를 열 수 없어요. DB 마이그레이션을 확인해주세요.'];
    }
    $pt = @db_select("SELECT point FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
    $payload = [
        'ok' => true,
        'name' => $닉,
        'point' => (int)($pt['point'] ?? $회원['point'] ?? 0),
        'entry_fee' => (int)$GLOBALS['SW_참가비'],
        'max_play_sec' => (int)$GLOBALS['SW_시도초'],
    ];
    $payload = array_merge($payload, sw_round_public($round, $닉_esc));
    if ($payload['my_playing']) {
        $entry = sw_entry_row((int)$round['idx'], $닉_esc);
        $payload['elapsed_ms'] = max(0, (int)round((microtime(true) - (float)$entry['started_at']) * 1000));
    }
    if (!empty($sync['closed'])) {
        $payload['round_closed'] = $sync['closed'];
    }
    return $payload;
}

function sw_finish_attempt($닉_esc, $두자리닉넴, array $entry, array $round) {
    global $SW_시도초;
    $started = (float)($entry['started_at'] ?? 0);
    if ($started <= 0) {
        return ['ok' => false, 'data' => '❌ 시작 정보가 없어요.'];
    }
    $elapsed_ms = (int)round((microtime(true) - $started) * 1000);
    if ($elapsed_ms > (int)$SW_시도초 * 1000) {
        $elapsed_ms = (int)$SW_시도초 * 1000;
    }
    $target = (int)$round['target_ms'];
    $diff_ms = abs($elapsed_ms - $target);
    $eid = (int)$entry['idx'];
    $rid = (int)$round['idx'];

    @db_query("
      UPDATE tb_stopwatch_entry
      SET status = 'done', elapsed_ms = {$elapsed_ms}, diff_ms = {$diff_ms}, finished_at = NOW()
      WHERE idx = {$eid} AND status = 'playing' LIMIT 1
    ");
    @db_query("INSERT INTO tb_stopwatch_log (round_id, nick, bet, target_ms, elapsed_ms, diff_ms, result, delta_point)
      VALUES ({$rid}, '{$닉_esc}', " . (int)$entry['bet'] . ", {$target}, {$elapsed_ms}, {$diff_ms}, 'done', 0)");

    $board = sw_leaderboard($rid);
    $my_rank = 0;
    foreach ($board as $row) {
        if ($row['nick'] === $두자리닉넴) {
            $my_rank = (int)$row['rank'];
            break;
        }
    }

    $pt = @db_select("SELECT point FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
    $msg = "⏱ 기록 " . sw_ms_label($elapsed_ms) . "초 · 오차 " . sw_ms_label($diff_ms) . "초\n";
    $msg .= "현재 순위 {$my_rank}위 · 라운드 종료 후 1위가 팟을 가져갑니다!";
    return [
        'ok' => true,
        'type' => 'stop',
        'result' => 'done',
        'data' => $msg,
        'target_ms' => $target,
        'elapsed_ms' => $elapsed_ms,
        'diff_ms' => $diff_ms,
        'my_rank' => $my_rank,
        'point' => (int)($pt['point'] ?? 0),
        'leaderboard' => $board,
    ];
}

$req_action = isset($_REQUEST['action']) ? trim($_REQUEST['action']) : '';
$req_code = isset($_REQUEST['code']) ? trim($_REQUEST['code']) : '';

$sw_name = '';
$sw_point = 0;
$sw_need_code = true;
$sw_round = null;
$sw_entry = null;

if (in_array($req_action, ['status', 'start', 'stop'], true)) {
    if ($req_code === '') {
        sw_json(['ok' => false, 'data' => '초대 코드가 필요합니다.']);
    }
    include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
    include_once $_SERVER['DOCUMENT_ROOT'] . '/api/function.php';

    $회원 = sw_auth($req_code);
    if (!$회원) {
        sw_json(['ok' => false, 'data' => '유효하지 않은 초대 코드입니다.']);
    }
    $닉 = trim($회원['name']);
    $두자리닉넴 = getTwoCharNick($닉);
    if ($두자리닉넴 === '') {
        sw_json(['ok' => false, 'data' => '닉네임을 확인할 수 없습니다.']);
    }
    $닉_esc = addslashes($두자리닉넴);

    if ($req_action === 'status') {
        sw_json(sw_status_payload($닉, $닉_esc, $회원));
    }

    $sync = sw_sync_rounds();
    $round = $sync['open'];
    if (!$round) {
        sw_json(['ok' => false, 'data' => '❌ 라운드를 열 수 없어요.']);
    }
    $rid = (int)$round['idx'];

    if ($req_action === 'start') {
        global $SW_참가비;
        $배팅 = (int)$SW_참가비;
        $가진냥 = (int)($회원['point'] ?? 0);
        if ($가진냥 < 0) {
            sw_json(['ok' => false, 'data' => '❌ 가진 냥이 마이너스면 도전 불가']);
        }
        if ($가진냥 < $배팅) {
            sw_json(['ok' => false, 'data' => '❌ 참가비 ' . number_format($배팅) . '냥이 부족해요.']);
        }
        $게임금지 = function_exists('게임제한_차단문구') ? 게임제한_차단문구($두자리닉넴) : null;
        if ($게임금지 !== null) {
            sw_json(['ok' => false, 'data' => $게임금지]);
        }

        $entry = sw_entry_row($rid, $닉_esc);
        if ($entry) {
            if (($entry['status'] ?? '') === 'playing') {
                sw_json(['ok' => false, 'data' => '⏳ 이미 진행 중이에요. STOP을 눌러주세요.']);
            }
            sw_json(['ok' => false, 'data' => '❌ 이번 라운드는 이미 참가했어요. 다음 라운드를 기다려주세요.']);
        }

        $token = bin2hex(random_bytes(16));
        $started = microtime(true);
        $목표 = (int)$round['target_ms'];

        db_query("UPDATE tb_member SET point = point - {$배팅} WHERE name = '{$닉_esc}' LIMIT 1");
        홀짝_지급로그('스톱워치-참가', $두자리닉넴, 0, $배팅);
        @db_query("
          INSERT INTO tb_stopwatch_entry (round_id, nick, bet, started_at, token, status)
          VALUES ({$rid}, '{$닉_esc}', {$배팅}, {$started}, '{$token}', 'playing')
        ");
        @db_query("
          UPDATE tb_stopwatch_round
          SET pot = pot + {$배팅}, player_count = player_count + 1
          WHERE idx = {$rid} LIMIT 1
        ");

        $pt = @db_select("SELECT point FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
        sw_json([
            'ok' => true,
            'type' => 'start',
            'data' => '🎯 시스템 목표 ' . sw_ms_label($목표) . '초 · 정확히 맞춰 STOP!',
            'bet' => $배팅,
            'target_ms' => $목표,
            'token' => $token,
            'round_id' => $rid,
            'point' => (int)($pt['point'] ?? 0),
        ]);
    }

    if ($req_action === 'stop') {
        $entry = sw_entry_row($rid, $닉_esc);
        if (!$entry || ($entry['status'] ?? '') !== 'playing') {
            sw_json(['ok' => false, 'data' => '❌ 진행 중인 시도가 없어요.']);
        }
        $req_token = isset($_REQUEST['token']) ? trim((string)$_REQUEST['token']) : '';
        if ($req_token !== '' && $req_token !== (string)($entry['token'] ?? '')) {
            sw_json(['ok' => false, 'data' => '❌ 판 정보가 일치하지 않아요.']);
        }
        sw_json(sw_finish_attempt($닉_esc, $두자리닉넴, $entry, $round));
    }
}

if ($req_code !== '') {
    include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
    include_once $_SERVER['DOCUMENT_ROOT'] . '/api/function.php';
    $회원_page = sw_auth($req_code);
    if ($회원_page) {
        $sw_need_code = false;
        $sw_name = trim($회원_page['name']);
        $닉_esc_page = addslashes(getTwoCharNick($sw_name));
        $sw_point = (int)($회원_page['point'] ?? 0);
        $pt_page = @db_select("SELECT point FROM tb_member WHERE name = '{$닉_esc_page}' LIMIT 1");
        if (is_array($pt_page)) {
            $sw_point = (int)($pt_page['point'] ?? $sw_point);
        }
        $sync_page = sw_sync_rounds();
        $sw_round = $sync_page['open'];
        if ($sw_round) {
            $sw_entry = sw_entry_row((int)$sw_round['idx'], $닉_esc_page);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#0f172a">
    <title>스톱워치 · 그룹</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Black+Han+Sans&family=JetBrains+Mono:wght@500;700&family=Noto+Sans+KR:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0f172a; --card: #1e293b; --accent: #a78bfa; --gold: #fbbf24;
            --blue: #38bdf8; --green: #4ade80; --red: #f87171; --text: #e2e8f0; --muted: #94a3b8;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100dvh;
            background: var(--bg);
            background-image:
                radial-gradient(ellipse at 50% 0%, rgba(167,139,250,0.2) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 80%, rgba(56,189,248,0.12) 0%, transparent 45%);
            font-family: 'Noto Sans KR', sans-serif;
            color: var(--text);
            display: flex; justify-content: center;
            padding: 12px;
            padding-top: max(10px, env(safe-area-inset-top));
            padding-bottom: max(10px, env(safe-area-inset-bottom));
        }
        .wrap {
            width: 100%; max-width: 460px;
            background: var(--card); border-radius: 16px; padding: 14px 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.45), 0 0 0 1px rgba(255,255,255,0.06);
        }
        h1 {
            font-family: 'Black Han Sans', sans-serif; font-size: 1.65rem; text-align: center; margin-bottom: 6px;
            background: linear-gradient(135deg, var(--gold), var(--accent));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        }
        .sub-title { text-align: center; font-size: 0.75rem; color: var(--muted); margin-bottom: 10px; }
        .top-bar { display: flex; justify-content: space-between; font-size: 0.85rem; color: var(--muted); margin-bottom: 8px; }
        .top-bar b { color: var(--gold); }
        .card {
            background: rgba(0,0,0,0.25); border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px; padding: 12px; margin-bottom: 8px;
        }
        .round-meta {
            display: grid; grid-template-columns: 1fr 1fr; gap: 6px 10px;
            font-size: 0.78rem; color: var(--muted); margin-bottom: 8px;
        }
        .round-meta b { color: var(--text); display: block; font-size: 0.9rem; }
        .target-box { text-align: center; padding: 4px 0 8px; }
        .target-label { font-size: 0.78rem; color: var(--muted); }
        .target-value {
            font-family: 'JetBrains Mono', monospace; font-size: 2.4rem; font-weight: 700;
            color: var(--gold); letter-spacing: 1px;
        }
        .timer-display {
            font-family: 'JetBrains Mono', monospace; font-size: 3rem; font-weight: 700;
            text-align: center; color: var(--blue); margin: 6px 0;
        }
        .timer-display.running { color: var(--green); }
        .timer-sub { text-align: center; font-size: 0.78rem; color: var(--muted); min-height: 1.1rem; margin-bottom: 8px; }
        .btn-main {
            width: 100%; min-height: 72px; border: none; border-radius: 14px;
            font-family: 'Black Han Sans', sans-serif; font-size: 1.45rem; color: #fff;
            cursor: pointer; letter-spacing: 2px; touch-action: manipulation;
        }
        .btn-main:disabled { opacity: 0.45; cursor: not-allowed; }
        .btn-start { background: linear-gradient(145deg, #4ade80, #16a34a); }
        .btn-stop { background: linear-gradient(145deg, #f87171, #dc2626); }
        .btn-wait { background: linear-gradient(145deg, #64748b, #475569); font-size: 1rem; }
        .fee-line { text-align: center; font-size: 0.82rem; color: var(--gold); margin-bottom: 6px; font-weight: 700; }
        .board-title { font-size: 0.85rem; color: var(--gold); margin-bottom: 6px; font-weight: 700; }
        .board-list { list-style: none; font-size: 0.78rem; }
        .board-list li {
            display: flex; justify-content: space-between; gap: 8px;
            padding: 5px 0; border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        .board-list li.me { color: var(--green); font-weight: 700; }
        .board-list .rank { color: var(--muted); width: 1.6rem; }
        .board-empty { font-size: 0.75rem; color: var(--muted); text-align: center; padding: 8px 0; }
        .result {
            display: none; margin-bottom: 8px; padding: 10px 12px; border-radius: 10px;
            background: rgba(0,0,0,0.35); border: 1px solid rgba(255,255,255,0.08);
            font-size: 0.82rem; line-height: 1.45; white-space: pre-line;
        }
        .result.show { display: block; }
        .rules { font-size: 0.72rem; color: var(--muted); line-height: 1.5; }
        .rules li { margin-left: 1rem; margin-bottom: 2px; }
        .need-code { text-align: center; padding: 40px 16px; color: var(--muted); }
        .panel-hidden { display: none !important; }
        @media (max-width: 480px) {
            .target-value { font-size: 2rem; }
            .timer-display { font-size: 2.5rem; }
            .btn-main { min-height: 64px; font-size: 1.25rem; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <h1>⏱ 스톱워치</h1>
    <div class="sub-title">시스템 목표 · 친구들과 승부</div>

    <?php if ($sw_need_code) { ?>
        <div class="need-code">초대 코드가 필요합니다.<br><code>?code=XXXX</code></div>
    <?php } else {
        $round_page = is_array($sw_round) ? $sw_round : [];
        $entry_page = is_array($sw_entry) ? $sw_entry : null;
        $target_page = (int)($round_page['target_ms'] ?? 0);
        $playing_page = $entry_page && ($entry_page['status'] ?? '') === 'playing';
        $done_page = $entry_page && in_array($entry_page['status'] ?? '', ['done', 'timeout'], true);
        $round_left = !empty($round_page['ends_at']) ? max(0, (int)strtotime($round_page['ends_at']) - time()) : 0;
        $boot_elapsed = 0;
        if ($playing_page) {
            $boot_elapsed = max(0, (int)round((microtime(true) - (float)$entry_page['started_at']) * 1000));
        }
    ?>
        <div class="top-bar">
            <span><?php echo htmlspecialchars($sw_name, ENT_QUOTES, 'UTF-8'); ?></span>
            <span>보유 <b id="ptDisplay"><?php echo number_format($sw_point); ?></b>냥</span>
        </div>

        <div class="card">
            <div class="round-meta">
                <div>라운드 남은 시간<b id="roundLeft"><?php echo gmdate('i:s', $round_left); ?></b></div>
                <div>참가 인원<b id="playerCount"><?php echo (int)($round_page['player_count'] ?? 0); ?></b>명</div>
                <div>총 팟<b id="potDisplay"><?php echo sw_금액_축약($round_page['pot'] ?? 0); ?></b>냥</div>
                <div>참가비<b><?php echo sw_금액_축약($SW_참가비); ?></b>냥</div>
            </div>
            <div class="target-box">
                <div class="target-label">🎯 시스템 목표 시간</div>
                <div class="target-value" id="targetDisplay"><?php echo $target_page > 0 ? sw_ms_label($target_page) : '—'; ?></div>
                <div class="timer-display" id="timerDisplay">0.00</div>
                <div class="timer-sub" id="timerSub"><?php
                    if ($done_page) echo '이번 라운드 참가 완료';
                    elseif ($playing_page) echo 'STOP! 목표에 맞춰 멈추세요';
                    else echo '참가비 ' . sw_금액_축약($SW_참가비) . '냥 · START';
                ?></div>
            </div>
            <div class="fee-line">라운드당 참가비 <b><?php echo number_format($SW_참가비); ?></b>냥 (1000억)</div>
            <button type="button" class="btn-main <?php echo $playing_page ? 'btn-stop' : ($done_page ? 'btn-wait' : 'btn-start'); ?>" id="btnMain"<?php echo $done_page ? ' disabled' : ''; ?>>
                <?php echo $playing_page ? 'STOP' : ($done_page ? '참가 완료' : 'START'); ?>
            </button>
        </div>

        <div class="result" id="resultBox"></div>

        <div class="card">
            <div class="board-title">🏆 실시간 순위 (오차 작을수록 상위)</div>
            <ul class="board-list" id="boardList"></ul>
            <div class="board-empty panel-hidden" id="boardEmpty">아직 기록이 없어요. 첫 도전자가 되어보세요!</div>
        </div>

        <div class="card rules">
            <strong>규칙</strong>
            <ul>
                <li>시스템이 정한 <b>목표 시간</b>에 모두 도전 (라운드마다 변경)</li>
                <li>참가비 <b>1000억냥</b> · 라운드당 1회</li>
                <li>라운드 <?php echo (int)($SW_라운드초 / 60); ?>분 · 종료 시 <b>오차 최소 1명</b>이 팟 전액 획득</li>
                <li>우승 지급 시 10% 수수료 (금고70%·로또30%)</li>
                <li>시도 <?php echo (int)$SW_시도초; ?>초 내 STOP · 초과 시 기록 무효</li>
            </ul>
        </div>
    <?php } ?>
</div>

<?php if (!$sw_need_code) { ?>
<script>
(function() {
    var CODE = <?php echo json_encode($req_code, JSON_UNESCAPED_UNICODE); ?>;
    var MY_NICK = <?php echo json_encode(getTwoCharNick($sw_name), JSON_UNESCAPED_UNICODE); ?>;
    var state = {
        point: <?php echo (int)$sw_point; ?>,
        running: <?php echo $playing_page ? 'true' : 'false'; ?>,
        done: <?php echo $done_page ? 'true' : 'false'; ?>,
        targetMs: <?php echo (int)$target_page; ?>,
        token: <?php echo json_encode($entry_page ? (string)$entry_page['token'] : '', JSON_UNESCAPED_UNICODE); ?>,
        roundLeftSec: <?php echo (int)$round_left; ?>,
        maxPlaySec: <?php echo (int)$SW_시도초; ?>,
        bootElapsedMs: <?php echo (int)$boot_elapsed; ?>,
        locked: false,
        localRunning: false,
        startPerf: 0,
        rafId: 0,
        pollId: 0,
        roundLeftTimer: 0
    };

    function $(id) { return document.getElementById(id); }
    function fmt(n) { return String(Math.max(0, parseInt(n, 10) || 0)).replace(/\B(?=(\d{3})+(?!\d))/g, ','); }
    function msLabel(ms) { return (Math.max(0, ms) / 1000).toFixed(2); }
    function fmtShort(n) {
        n = parseInt(n, 10) || 0;
        if (typeof BigInt !== 'undefined' && (n >= 9007199254740992 || String(n).length > 15)) {
            var v = BigInt(String(n));
            var G = 10000000000000000n, J = 1000000000000n, E = 100000000n, M = 10000n;
            if (v >= G) return (v / G).toString() + '경';
            if (v >= J) return (v / J).toString() + '조';
            if (v >= E) return (v / E).toString() + '억';
            if (v >= M) return (v / M).toString() + '만';
            return fmt(n);
        }
        if (n >= 10000000000000000) return Math.floor(n / 10000000000000000) + '경';
        if (n >= 1000000000000) return Math.floor(n / 1000000000000) + '조';
        if (n >= 100000000) return Math.floor(n / 100000000) + '억';
        if (n >= 10000) return Math.floor(n / 10000) + '만';
        return fmt(n);
    }
    function fmtRoundLeft(sec) {
        sec = Math.max(0, parseInt(sec, 10) || 0);
        var m = Math.floor(sec / 60), s = sec % 60;
        return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
    }

    function ajax(params, onDone) {
        var qs = new URLSearchParams(params);
        qs.set('code', CODE);
        fetch(location.pathname + '?' + qs.toString(), { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(onDone)
            .catch(function() { onDone({ ok: false, data: '통신 오류' }); });
    }

    function stopAnim() {
        if (state.rafId) { cancelAnimationFrame(state.rafId); state.rafId = 0; }
    }

    function tick() {
        if (!state.localRunning) return;
        var elapsed = performance.now() - state.startPerf;
        $('timerDisplay').textContent = msLabel(elapsed);
        $('timerDisplay').className = 'timer-display running';
        if (elapsed >= state.maxPlaySec * 1000) {
            $('timerSub').textContent = '시간 초과…';
            state.localRunning = false;
            stopAnim();
            return;
        }
        state.rafId = requestAnimationFrame(tick);
    }

    function beginLocalTimer(fromMs) {
        state.localRunning = true;
        var base = typeof fromMs === 'number' ? fromMs : 0;
        state.startPerf = performance.now() - base;
        $('timerDisplay').textContent = msLabel(base);
        $('timerSub').textContent = 'STOP! 목표 ' + msLabel(state.targetMs) + '초';
        stopAnim();
        state.rafId = requestAnimationFrame(tick);
    }

    function renderBoard(list) {
        var ul = $('boardList');
        var empty = $('boardEmpty');
        ul.innerHTML = '';
        if (!list || !list.length) {
            empty.classList.remove('panel-hidden');
            return;
        }
        empty.classList.add('panel-hidden');
        list.forEach(function(row) {
            var li = document.createElement('li');
            if (row.nick === MY_NICK) li.className = 'me';
            li.innerHTML = '<span class="rank">' + row.rank + '</span>'
                + '<span>' + row.nick + '</span>'
                + '<span>' + msLabel(row.elapsed_ms) + 's · Δ' + msLabel(row.diff_ms) + '</span>';
            ul.appendChild(li);
        });
    }

    function applyStatus(j) {
        if (!j.ok) return;
        state.point = j.point;
        state.targetMs = j.target_ms || state.targetMs;
        state.roundLeftSec = j.round_left_sec || 0;
        state.running = !!j.my_playing;
        state.done = !!j.my_done;
        if (typeof j.my_token === 'string' && j.my_token) state.token = j.my_token;
        $('targetDisplay').textContent = msLabel(state.targetMs);
        $('roundLeft').textContent = fmtRoundLeft(state.roundLeftSec);
        $('playerCount').textContent = j.player_count || 0;
        $('potDisplay').textContent = fmtShort(j.pot || 0);
        renderBoard(j.leaderboard || []);
        if (j.round_closed && j.round_closed.length) {
            var last = j.round_closed[j.round_closed.length - 1];
            if (last.winner_nick) {
                $('resultBox').textContent = '🏆 라운드 종료!\n우승 ' + last.winner_nick
                    + ' · 오차 ' + msLabel(last.winner_diff_ms || 0) + '초\n'
                    + '+' + fmt(last.payout || 0) + '냥 획득';
                $('resultBox').className = 'result show';
            }
        }
        updateUI();
        if (state.running && !state.localRunning) {
            beginLocalTimer(j.elapsed_ms || state.bootElapsedMs || 0);
        }
    }

    function updateUI() {
        var btn = $('btnMain');
        $('ptDisplay').textContent = fmt(state.point);
        if (state.running) {
            btn.textContent = 'STOP';
            btn.className = 'btn-main btn-stop';
            btn.disabled = state.locked;
        } else if (state.done) {
            btn.textContent = '참가 완료';
            btn.className = 'btn-main btn-wait';
            btn.disabled = true;
            stopAnim();
            state.localRunning = false;
        } else {
            btn.textContent = 'START';
            btn.className = 'btn-main btn-start';
            btn.disabled = state.locked;
            $('timerDisplay').textContent = '0.00';
            $('timerDisplay').className = 'timer-display';
            $('timerSub').textContent = '참가비 1000억냥 · START';
            stopAnim();
            state.localRunning = false;
        }
    }

    function doStart() {
        if (state.locked || state.running || state.done) return;
        state.locked = true;
        updateUI();
        ajax({ action: 'start' }, function(j) {
            state.locked = false;
            if (!j.ok) { window.alert(j.data || '시작 실패'); updateUI(); return; }
            state.running = true;
            state.done = false;
            state.targetMs = j.target_ms || state.targetMs;
            state.token = j.token || '';
            if (typeof j.point === 'number') state.point = j.point;
            $('targetDisplay').textContent = msLabel(state.targetMs);
            beginLocalTimer(0);
            updateUI();
            refreshStatus();
        });
    }

    function doStop() {
        if (state.locked || !state.running) return;
        state.locked = true;
        state.localRunning = false;
        stopAnim();
        ajax({ action: 'stop', token: state.token }, function(j) {
            state.locked = false;
            state.running = false;
            state.done = true;
            if (!j.ok) { window.alert(j.data || '정산 실패'); refreshStatus(); return; }
            if (typeof j.point === 'number') state.point = j.point;
            if (typeof j.elapsed_ms === 'number') {
                $('timerDisplay').textContent = msLabel(j.elapsed_ms);
                $('timerDisplay').className = 'timer-display';
            }
            $('timerSub').textContent = '오차 ' + msLabel(j.diff_ms || 0) + '초 · ' + (j.my_rank || '?') + '위';
            $('resultBox').textContent = j.data || '';
            $('resultBox').className = 'result show';
            if (j.leaderboard) renderBoard(j.leaderboard);
            updateUI();
            refreshStatus();
        });
    }

    function refreshStatus() {
        ajax({ action: 'status' }, applyStatus);
    }

    $('btnMain').addEventListener('click', function() {
        if (state.running) doStop();
        else doStart();
    });

    document.addEventListener('keydown', function(e) {
        if (e.code === 'Space' || e.code === 'Enter') {
            e.preventDefault();
            if (state.running) doStop();
            else if (!state.locked && !state.done) doStart();
        }
    });

    state.roundLeftTimer = setInterval(function() {
        if (state.roundLeftSec > 0) {
            state.roundLeftSec--;
            $('roundLeft').textContent = fmtRoundLeft(state.roundLeftSec);
        }
    }, 1000);

    state.pollId = setInterval(refreshStatus, 5000);

    if (state.running) beginLocalTimer(state.bootElapsedMs || 0);
    refreshStatus();
    updateUI();
})();
</script>
<?php } ?>
</body>
</html>
