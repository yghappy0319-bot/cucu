<?php
/**
 * 지또 웹 게임 (info2.php 250-263 + lotto.php 로직)
 * action=play 이면 게임 실행 후 JSON 반환, 아니면 게임 페이지 출력
 * 접속 시 ?code=xxx 로 tb_member.code와 매칭해 name 사용
 * 브라우저(탭) 1개만 이용 가능: lock_token으로 동시 접속 차단
 */

// CODE당 브라우저(탭) 1개 락: code별 파일에 활성 토큰·시간 저장
$JITO_LOCK_EXPIRE = 30;   // 초 단위 (이 시간 동안 갱신 없으면 락 해제)
$JITO_LOCK_STALE  = 60;    // 이 시간(초) 이상 갱신 없으면 다른 탭이 락 인수 가능

function jito_lock_file($key) {
    $key = is_string($key) ? trim($key) : '';
    if ($key === '') $key = 'guest';
    return sys_get_temp_dir() . '/jito_web_lock_' . md5($key) . '.txt';
}

function jito_lock_read($key) {
    $JITO_LOCK_EXPIRE = 200;
    $path = jito_lock_file($key);
    if (!is_file($path)) return [null, 0];
    $raw = @file_get_contents($path);
    if ($raw === false) return [null, 0];
    $parts = explode("\t", trim($raw), 2);
    $token = isset($parts[0]) ? trim($parts[0]) : null;
    $ts = isset($parts[1]) ? (int)$parts[1] : 0;
    if (time() - $ts > $JITO_LOCK_EXPIRE) return [null, 0];
    return [$token, $ts];
}

function jito_lock_allow($request_token, $key) {
    global $JITO_LOCK_STALE;
    $request_token = is_string($request_token) ? trim($request_token) : '';
    if ($request_token === '') return false;
    $path = jito_lock_file($key);
    if (!is_file($path)) return true;
    $raw = @file_get_contents($path);
    if ($raw === false) return true;
    $parts = explode("\t", trim($raw), 2);
    $current = isset($parts[0]) ? trim($parts[0]) : null;
    $ts = isset($parts[1]) ? (int)$parts[1] : 0;
    $elapsed = time() - $ts;
    if ($current === null || $current === '') return true;
    if ($current === $request_token) return true;
    // 60초 이상 갱신 없으면 다른 탭/브라우저가 락 인수 가능 (탭 닫고 다시 열었을 때 대비)
    if ($elapsed > (int)$JITO_LOCK_STALE) return true;
    return false;
}

function jito_lock_refresh($request_token, $key) {
    $request_token = is_string($request_token) ? trim($request_token) : '';
    if ($request_token === '') return;
    $path = jito_lock_file($key);
    @file_put_contents($path, $request_token . "\t" . time(), LOCK_EX);
}

// 탭 확보(페이지 로드 시 호출) — CODE당 1탭
if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'acquire_lock') {
    header('Content-Type: application/json; charset=utf-8');
    $lock_token = isset($_REQUEST['lock_token']) ? trim($_REQUEST['lock_token']) : '';
    $code = isset($_REQUEST['code']) ? trim($_REQUEST['code']) : '';
    $lock_key = ($code !== '') ? $code : 'guest';

    if ($lock_token === '') {
        echo json_encode(['data' => 'lock_token이 필요합니다.', 'lock_ok' => false], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!jito_lock_allow($lock_token, $lock_key)) {
        echo json_encode(['data' => '이 코드로 열린 다른 브라우저(또는 탭)에서 이용 중입니다. 기존 창을 닫아주세요.', 'lock_ok' => false], JSON_UNESCAPED_UNICODE);
        exit;
    }
    jito_lock_refresh($lock_token, $lock_key);
    echo json_encode(['lock_ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

// 락 상태 확인 (디버깅/확인용) — GET/POST 모두 가능, code 없으면 guest 기준
if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'lock_status') {
    header('Content-Type: application/json; charset=utf-8');
    $code = isset($_REQUEST['code']) ? trim($_REQUEST['code']) : '';
    $lock_key = ($code !== '') ? $code : 'guest';
    $lock_token = isset($_REQUEST['lock_token']) ? trim($_REQUEST['lock_token']) : '';
    $path = jito_lock_file($lock_key);
    $has_file = is_file($path);
    $current_token = null;
    $last_ts = 0;
    if ($has_file) {
        $raw = @file_get_contents($path);
        if ($raw !== false) {
            $parts = explode("\t", trim($raw), 2);
            $current_token = isset($parts[0]) ? trim($parts[0]) : null;
            $last_ts = isset($parts[1]) ? (int)$parts[1] : 0;
        }
    }
    $last_activity_sec = $last_ts > 0 ? (time() - $last_ts) : null;
    $is_owner = ($lock_token !== '' && $current_token === $lock_token);
    $lock_ok = ($lock_token !== '' && jito_lock_allow($lock_token, $lock_key));
    echo json_encode([
        'lock_ok' => $lock_ok,
        'is_owner' => $is_owner,
        'has_lock_file' => $has_file,
        'last_activity_sec_ago' => $last_activity_sec,
        'hint' => !$has_file ? '락 없음 (바로 사용 가능)' : ($is_owner ? '이 탭이 락 보유 중' : ($last_activity_sec !== null && $last_activity_sec > $JITO_LOCK_STALE ? $last_activity_sec . '초 미갱신 → 곧 인수 가능 또는 새로고침' : '다른 탭이 사용 중'))
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'play') {
    header('Content-Type: application/json; charset=utf-8');

    $num1   = isset($_REQUEST['num1']) ? (int)$_REQUEST['num1'] : null;
    $num2   = isset($_REQUEST['num2']) ? (int)$_REQUEST['num2'] : 1;
    $nick   = '';
    $code   = isset($_REQUEST['code']) ? trim($_REQUEST['code']) : '';
    $lock_key = ($code !== '') ? $code : 'guest';
    $lock_token = isset($_REQUEST['lock_token']) ? trim($_REQUEST['lock_token']) : '';
    if ($lock_token === '' || !jito_lock_allow($lock_token, $lock_key)) {
        echo json_encode(['data' => '이 코드로 열린 다른 브라우저(또는 탭)에서 이용 중입니다. 기존 창을 닫아주세요.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($code !== '') {
        include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
        $code_esc = addslashes($code);
        $row = db_select("SELECT name, enhance, item, style FROM tb_member WHERE code = '{$code_esc}' LIMIT 1");
        if (!empty($row['name'])) {
            $nick = trim($row['name']);
        }
        if ($nick === '') {
            echo json_encode(['data' => '유효하지 않은 초대 코드입니다.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    } else {
        $nick = isset($_REQUEST['nick']) ? trim($_REQUEST['nick']) : '';
    }

    if ($nick === '') {
        echo json_encode(['data' => '닉네임을 입력해주세요.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // lotto.php 가 기대하는 $msg (숫자 또는 "숫자 배수")
    $msg = ($num1 !== null && $num1 > 0) ? ((int)$num2 > 1 ? "{$num1} {$num2}" : "{$num1}") : '';

    include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
    include_once $_SERVER['DOCUMENT_ROOT'] . '/api/function.php';

    $두자리닉넴 = getTwoCharNick($nick);
    if ($두자리닉넴 === '') {
        echo json_encode(['data' => '닉네임을 확인해주세요. (한글 2글자 등)'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    include_once $_SERVER['DOCUMENT_ROOT'] . '/api/config.php';

    // 지또출입은 여러 건 있을 수 있음. 유효한 것(enddate > NOW()) 중 1명만 이용 가능
    $사용중여부 = db_select("SELECT * FROM tb_item_use WHERE item = '지또출입' AND enddate > NOW() ORDER BY enddate asc LIMIT 1");
    if (empty($사용중여부['idx'])) {
        echo json_encode(['data' => '‼️이용불가‼️ .지또 명령어를 입력해주세요.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (trim($사용중여부['nickname'] ?? '') !== $두자리닉넴) {
        echo json_encode(['data' => '‼️이용불가‼️ 현재 ' . trim($사용중여부['nickname']) . ' 이용 중입니다.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    jito_lock_refresh($lock_token, $lock_key);

    // lotto.php 포함 (같은 변수 사용, echo 전송() 후 exit 하므로 여기서 반환 형식 통일만 함)
    include $_SERVER['DOCUMENT_ROOT'] . '/api/lotto.php';
    exit;
}

// 지또 배수 설정 (info2 .지또배수 로직)
if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'set_getto') {
    header('Content-Type: application/json; charset=utf-8');

    $code = isset($_REQUEST['code']) ? trim($_REQUEST['code']) : '';
    $lock_token = isset($_REQUEST['lock_token']) ? trim($_REQUEST['lock_token']) : '';
    if ($code === '') {
        echo json_encode(['data' => '초대 코드가 필요합니다.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($lock_token === '' || !jito_lock_allow($lock_token, $code)) {
        echo json_encode(['data' => '이 코드로 열린 다른 브라우저(또는 탭)에서 이용 중입니다. 기존 창을 닫아주세요.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $배수 = isset($_REQUEST['getto']) ? (int)$_REQUEST['getto'] : 0;

    include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
    include_once $_SERVER['DOCUMENT_ROOT'] . '/api/function.php';
    $code_esc = addslashes($code);
    $row = db_select("SELECT name FROM tb_member WHERE code = '{$code_esc}' LIMIT 1");
    if (empty($row['name'])) {
        echo json_encode(['data' => '유효하지 않은 초대 코드입니다.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $nick = trim($row['name']);
    $두자리닉넴 = getTwoCharNick($nick);
    if ($두자리닉넴 === '') {
        echo json_encode(['data' => '닉네임을 확인할 수 없습니다.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    include_once $_SERVER['DOCUMENT_ROOT'] . '/api/config.php';

    if ($배수 > $레버리지) {
        echo json_encode(['data' => $레버리지 . '배 이상 불가!'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($배수 % 100 !== 0 || $배수 < 100 || $배수 > 2000) {
        echo json_encode(['data' => '잘못된 배율입니다. (100~2000, 100단위)'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $정보 = db_select("SELECT idx, point, max_getto FROM tb_member WHERE name = '{$두자리닉넴}' LIMIT 1");
    $정보['max_getto'] = (int)($정보['max_getto'] ?? 0);

    include_once $_SERVER['DOCUMENT_ROOT'] . '/api/jito_tax_rate.php';

    jito_lock_refresh($lock_token, $code);

    if ($배수 > $정보['max_getto']) {
        $현재최대 = $정보['max_getto'];
        $다음단계 = ($현재최대 < 2000) ? $현재최대 + 100 : null;
        if ($배수 !== $정보['max_getto'] + 100) {
            $msg = $다음단계
                ? "배율은 단계별로만 상향할 수 있습니다. (다음 가능 단계: {$다음단계}배)"
                : "이미 최대 배율입니다.";
            echo json_encode(['data' => $msg], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $차감포인트 = $배수 * 10000;
        if ($정보['point'] < $차감포인트) {
            echo json_encode(['data' => "😭냥 부족😭 {$배수}배 상향은 " . number_format($차감포인트) . "냥 필요"], JSON_UNESCAPED_UNICODE);
            exit;
        }
        db_query("
            UPDATE tb_member
            SET point = point - {$차감포인트}, getto = {$배수}, max_getto = {$배수}
            WHERE name = '{$두자리닉넴}' AND max_getto = {$정보['max_getto']}
        ");
        $new_point = (int)$정보['point'] - $차감포인트;
        echo json_encode([
            'data' => "기본 {$배수}배 설정완료 (" . number_format($차감포인트) . " 냥 차감)",
            'getto' => $배수,
            'max_getto' => $배수,
            'point' => $new_point,
            'jito_tax_rate' => 지또_보유기준_세율표시문자열($new_point)
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    db_query("UPDATE tb_member SET getto = {$배수} WHERE name = '{$두자리닉넴}'");
    echo json_encode([
        'data' => "기본 {$배수}배 설정완료",
        'getto' => $배수,
        'max_getto' => $정보['max_getto'],
        'point' => (int)$정보['point'],
        'jito_tax_rate' => 지또_보유기준_세율표시문자열((int)$정보['point'])
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 보유 냥/강화 실시간 조회 (도전 후 UI 갱신용)
if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'get_balance') {
    header('Content-Type: application/json; charset=utf-8');

    $code = isset($_REQUEST['code']) ? trim($_REQUEST['code']) : '';
    $lock_key = ($code !== '') ? $code : 'guest';
    $lock_token = isset($_REQUEST['lock_token']) ? trim($_REQUEST['lock_token']) : '';
    if ($lock_token === '' || !jito_lock_allow($lock_token, $lock_key)) {
        echo json_encode(['point' => 0, 'enhance' => 0, 'lock_error' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($code === '') {
        echo json_encode(['point' => 0, 'enhance' => 0], JSON_UNESCAPED_UNICODE);
        exit;
    }
    include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
    include_once $_SERVER['DOCUMENT_ROOT'] . '/api/jito_tax_rate.php';
    $code_esc = addslashes($code);
    $row = db_select("SELECT point, enhance, item, style FROM tb_member WHERE code = '{$code_esc}' LIMIT 1");
    $point = (int)($row['point'] ?? 0);
    $enhance = (int)($row['enhance'] ?? 0);
    $item = trim((string)($row['item'] ?? ''));
    $style = trim((string)($row['style'] ?? ''));
    jito_lock_refresh($lock_token, $lock_key);
    echo json_encode([
        'point' => $point,
        'enhance' => $enhance,
        'item' => $item,
        'style' => $style,
        'jito_tax_rate' => 지또_보유기준_세율표시문자열($point)
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 페이지 로드: code로 tb_member 조회해 name, enhance, point
$jito_code = isset($_GET['code']) ? trim($_GET['code']) : '';
$jito_name = '';
$jito_enhance = 0;
$jito_point = 0;
$jito_item = '';
$jito_style = '';
$jito_code_error = false;
$jito_need_code = false; // 코드 없음 또는 유효하지 않음 → 닉네임 제외, 코드 부여 문구 표시

include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
if ($jito_code !== '') {
    $code_esc = addslashes($jito_code);
    $row = db_select("SELECT name, enhance, point, item, style FROM tb_member WHERE code = '{$code_esc}' LIMIT 1");
    if (!empty($row['name'])) {
        $jito_name = trim($row['name']);
        $jito_enhance = (int)($row['enhance'] ?? 0);
        $jito_point = (int)($row['point'] ?? 0);
        $jito_item = trim((string)($row['item'] ?? ''));
        $jito_style = trim((string)($row['style'] ?? ''));
    } else {
        $jito_code_error = true;
        $jito_need_code = true;
    }
} else {
    $jito_need_code = true;
}

// config.php가 $두자리닉넴을 사용하므로, include 전에 반드시 설정
include_once $_SERVER['DOCUMENT_ROOT'] . '/api/function.php';
$두자리닉넴 = ($jito_name !== '') ? getTwoCharNick($jito_name) : '';
include_once $_SERVER['DOCUMENT_ROOT'] . '/api/config.php';

// code가 있는 경우: 현재 지또 이용 중인 사람 및 남은시간 (tb_item_use, item='지또출입')
$jito_use_nick = '';
$jito_use_enddate_iso = '';
if (!$jito_need_code && $jito_code !== '') {
    $use_row = db_select("SELECT nickname, enddate FROM tb_item_use WHERE item = '지또출입' AND enddate > NOW() ORDER BY enddate asc LIMIT 1");
    if (!empty($use_row['nickname'])) {
        $jito_use_nick = trim($use_row['nickname']);
        $jito_use_enddate_iso = $use_row['enddate']; // datetime for JS countdown
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
    <title>지또 · 웹</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Black+Han+Sans&family=Noto+Sans+KR:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #1a0a2e;
            --card: #16213e;
            --accent: #e94560;
            --gold: #ffd700;
            --text: #eaeaea;
            --muted: #a0a0a0;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html {
            -webkit-text-size-adjust: 100%;
        }
        body {
            min-height: 100vh;
            min-height: -webkit-fill-available;
            background: var(--bg);
            background-image: radial-gradient(ellipse at 50% 0%, rgba(233,69,96,0.15) 0%, transparent 50%),
                              radial-gradient(ellipse at 80% 80%, rgba(255,215,0,0.08) 0%, transparent 40%);
            font-family: 'Noto Sans KR', sans-serif;
            color: var(--text);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px;
            padding-left: max(24px, env(safe-area-inset-left));
            padding-right: max(24px, env(safe-area-inset-right));
            padding-bottom: max(24px, env(safe-area-inset-bottom));
        }
        .game-wrap {
            width: 100%;
            max-width: 420px;
            background: var(--card);
            border-radius: 24px;
            padding: 32px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4), 0 0 0 1px rgba(255,255,255,0.06);
        }
        h1 {
            font-family: 'Black Han Sans', sans-serif;
            font-size: 2.2rem;
            text-align: center;
            margin-bottom: 8px;
            background: linear-gradient(135deg, var(--gold), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .sub {
            text-align: center;
            color: var(--muted);
            font-size: 0.9rem;
            margin-bottom: 28px;
        }
        .jito-use-status {
            text-align: center;
            font-size: 0.9rem;
            color: var(--muted);
            margin-bottom: 16px;
            padding: 10px 14px;
            background: rgba(0,0,0,0.2);
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.08);
        }
        .jito-use-status strong { color: var(--accent); }
        .field {
            
        }
        .field label {
            display: block;
            font-size: 0.85rem;
            color: var(--muted);
            margin-bottom: 6px;
        }
        .field input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            background: rgba(0,0,0,0.25);
            color: var(--text);
            font-size: 16px; /* 16px 이상으로 iOS 입력 시 줌 방지 */
            transition: border-color 0.2s;
            -webkit-appearance: none;
            appearance: none;
        }
        .field input:focus {
            outline: none;
            border-color: var(--accent);
        }
        .field input::placeholder {
            color: var(--muted);
        }
        .row {
            display: flex;
            gap: 12px;
        }
        .row .field { flex: 1; }
        .btn-play {
            width: 100%;
            padding: 10px 16px;
            min-height: 44px;
            font-family: 'Black Han Sans', sans-serif;
            font-size: 0.95rem;
            font-weight: 700;
            color: #fff;
            background: linear-gradient(145deg, var(--accent), #c73e54);
            border: none;
            border-radius: 10px;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(233,69,96,0.35);
            transition: transform 0.1s, box-shadow 0.2s;
            -webkit-tap-highlight-color: transparent;
            touch-action: manipulation;
        }
        .btn-play:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(233,69,96,0.45);
        }
        .btn-play:active {
            transform: translateY(0);
        }
        .btn-play:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        .play-stop-row {
            display: flex;
            flex-direction: row;
            align-items: stretch;
            gap: 10px;
            width: 100%;
        }
        .play-stop-row .btn-play {
            flex: 7 1 0;
            min-width: 0;
            width: auto;
        }
        .play-stop-row .btn-stop {
            flex: 3 1 0;
            min-width: 0;
            width: auto;
            padding-left: 10px;
            padding-right: 10px;
        }
        .result {
            margin-top: 24px;
            padding: 20px;
            border-radius: 14px;
            background: rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.08);
            font-size: 0.95rem;
            line-height: 1.6;
            white-space: pre-wrap;
            word-break: break-word;
            min-height: 60px;
            display: none;
        }
        .result.show { display: block; }
        .result.win { border-color: rgba(255,215,0,0.3); color: #ffd700; }
        .result.lose { border-color: rgba(233,69,96,0.3); color: #f08; }
        .result.info { border-color: rgba(255,255,255,0.15); color: var(--text); }
        @keyframes result-sparkle {
            0%, 100% { box-shadow: 0 0 0 0 rgba(255,215,0,0); }
            50% { box-shadow: 0 0 20px 4px rgba(255,215,0,0.5); }
        }
        .result.sparkle {
            animation: result-sparkle 0.6s ease-out;
        }
        .hint {
            font-size: 0.8rem;
            color: var(--muted);
            margin-top: 6px;
        }
        .getto-section {
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        .getto-section .getto-table {
            margin-bottom: 0;
        }
        .getto-section .getto-table th {
            font-size: 0.85rem;
        }
        .getto-section .getto-table td {
            font-size: 0.9rem;
        }
        .getto-section .getto-oneline {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px 18px;
        }
        .getto-section .getto-item {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            background: rgba(255,255,255,0.06);
            border-radius: 10px;
            font-size: 0.9rem;
        }
        .getto-section .getto-item .getto-label {
            color: var(--muted, rgba(255,255,255,0.6));
            font-weight: 600;
        }
        .getto-section .getto-oneline .getto-row { flex: 1; min-width: 0; }
        .getto-section .getto-row {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: nowrap;
            margin-bottom: 10px;
        }
        .getto-section .getto-row select {
            flex: 1;
            min-width: 0;
            padding: 10px 12px;
            border-radius: 10px;
            border: 2px solid rgba(255,255,255,0.1);
            background: rgba(0,0,0,0.25);
            color: var(--text);
            font-size: 0.9rem;
        }
        .btn-upgrade {
            padding: 10px 16px;
            min-height: 42px;
            white-space: nowrap;
            font-size: 0.9rem;
            font-weight: 700;
            border-radius: 10px;
            border: none;
            background: rgba(255,215,0,0.2);
            color: var(--gold);
            cursor: pointer;
            -webkit-tap-highlight-color: transparent;
            touch-action: manipulation;
        }
        .getto-section .getto-oneline .getto-row { flex: 1; min-width: 0; }
        .getto-section .getto-row {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: nowrap;
            margin-bottom: 10px;
        }
        .getto-section .getto-row select {
            flex: 1;
            min-width: 0;
            padding: 10px 12px;
            border-radius: 10px;
            border: 2px solid rgba(255,255,255,0.1);
            background: rgba(0,0,0,0.25);
            color: var(--text);
            font-size: 0.9rem;
        }
        .btn-upgrade {
            padding: 10px 16px;
            min-height: 42px;
            white-space: nowrap;
            font-size: 0.9rem;
            font-weight: 700;
            border-radius: 10px;
            border: none;
            background: rgba(255,215,0,0.2);
            color: var(--gold);
            cursor: pointer;
            -webkit-tap-highlight-color: transparent;
            touch-action: manipulation;
        }
        .jito-dual-select-row {
            display: flex;
            flex-direction: row;
            gap: 10px;
            margin-top: 10px;
            align-items: stretch;
            width: 100%;
        }
        .jito-dual-select-row select {
            flex: 1 1 0;
            min-width: 0;
            padding: 10px 12px;
            border-radius: 10px;
            border: 2px solid rgba(255,255,255,0.1);
            background: rgba(0,0,0,0.25);
            color: var(--text);
            font-size: 0.9rem;
        }
        .btn-stop {
            padding: 10px 16px;
            min-height: 44px;
            font-size: 0.9rem;
            font-weight: 700;
            color: #fff;
            background: rgba(233,69,96,0.5);
            border: 2px solid var(--accent);
            border-radius: 10px;
            cursor: pointer;
            -webkit-tap-highlight-color: transparent;
            touch-action: manipulation;
        }
        .btn-stop:hover:not(:disabled) {
            background: rgba(233,69,96,0.7);
        }
        .btn-stop:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
        .nick-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
            background: rgba(0,0,0,0.2);
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.08);
        }
        .nick-table th,
        .nick-table td {
            padding: 14px 16px;
            text-align: left;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        .nick-table tr:last-child th,
        .nick-table tr:last-child td {
            border-bottom: none;
        }
        .nick-table th {
            width: 100px;
            color: var(--muted);
            font-size: 0.9rem;
            font-weight: 700;
        }
        .nick-table td {
            color: var(--text);
            font-size: 1rem;
        }
        .nick-table td input {
            width: 100%;
            padding: 8px 0;
            border: none;
            background: transparent;
            color: var(--text);
            font-size: 1rem;
        }
        .nick-table td input:focus {
            outline: none;
        }
        .nick-table td input::placeholder {
            color: var(--muted);
        }
        .btn-lock-status {
            font-size: 0.85rem;
            color: var(--muted);
            background: none;
            border: none;
            cursor: pointer;
            text-decoration: underline;
            padding: 4px 8px;
            -webkit-tap-highlight-color: transparent;
        }
        .btn-lock-status:hover { color: var(--text); }
        /* 모바일 최적화 */
        @media (max-width: 480px) {
            body { padding: 16px; padding-left: max(16px, env(safe-area-inset-left)); padding-right: max(16px, env(safe-area-inset-right)); padding-bottom: max(16px, env(safe-area-inset-bottom)); }
            .game-wrap {
                padding: 24px 20px;
                border-radius: 20px;
            }
            h1 { font-size: 1.85rem; }
            .sub { font-size: 0.85rem; margin-bottom: 22px; }
            .field { margin-bottom: 14px; }
            .field label { font-size: 0.8rem; }
            .row { gap: 10px; flex-wrap: wrap; }
            .play-stop-row { gap: 8px; }
            .btn-play { padding: 10px 16px; min-height: 44px; font-size: 0.95rem; border-radius: 10px; }
            .play-stop-row .btn-stop { padding-left: 8px; padding-right: 8px; font-size: 0.85rem; }
            .result { margin-top: 20px; padding: 16px; font-size: 0.9rem; min-height: 52px; }
            .getto-section { margin-top: 18px; padding-top: 14px; }
            .jito-dual-select-row { gap: 8px; }
            .jito-dual-select-row select { font-size: 0.85rem; padding: 10px 8px; }
            .btn-upgrade { min-height: 44px; padding: 12px 14px; }
        }
        @media (max-width: 360px) {
            body { padding: 12px; padding-left: max(12px, env(safe-area-inset-left)); padding-right: max(12px, env(safe-area-inset-right)); }
            .game-wrap { padding: 20px 16px; }
            h1 { font-size: 1.65rem; }
            .play-stop-row { gap: 6px; }
            .btn-play { padding: 10px 12px; min-height: 44px; font-size: 0.9rem; border-radius: 10px; }
            .play-stop-row .btn-stop { padding-left: 6px; padding-right: 6px; font-size: 0.8rem; }
            .jito-dual-select-row { gap: 6px; }
            .jito-dual-select-row select { font-size: 0.78rem; padding: 8px 4px; }
        }
    </style>
</head>
<body>
    <div class="game-wrap">
        <h1>지또</h1>
        <?php if ($jito_need_code) { ?>
        <div class="result show info" style="margin-top: 0;">
            <strong>코드를 부여받으세요.</strong>
        </div>
        <?php } else { ?>
        <?php if ($jito_code !== '') { ?>
        <p class="jito-use-status" id="jitoUseStatus"<?php if ($jito_use_enddate_iso !== '') { ?> data-end="<?php echo htmlspecialchars($jito_use_enddate_iso, ENT_QUOTES, 'UTF-8'); ?>"<?php } ?>>
            <?php if ($jito_use_nick !== '') { ?>
            현재 <strong id="jitoUseNick"><?php echo htmlspecialchars($jito_use_nick, ENT_QUOTES, 'UTF-8'); ?></strong> 이용중 · 남은시간 <strong id="jitoRemain">—</strong>
            <?php } else { ?>
            현재 이용 중인 사람 없음
            <?php } ?>
        </p>
        <?php } ?>
        <form id="f" class="game-form">
            <?php if ($jito_code !== '') { ?>
            <input type="hidden" name="code" id="code" value="<?php echo htmlspecialchars($jito_code, ENT_QUOTES, 'UTF-8'); ?>">
            <?php } ?>
            <input type="hidden" id="num1" name="num1" min="1" max="50" placeholder="비우면 랜덤">
            <input type="hidden" id="num2" name="num2" min="1" max="2000" value="<?php echo max(1, (int)$jito_enhance); ?>" placeholder="1">
            <input type="hidden" id="nick" name="nick" value="<?php echo htmlspecialchars($jito_name, ENT_QUOTES, 'UTF-8'); ?>">
    

            <div class="play-stop-row">
                <button type="submit" class="btn-play" id="btnPlay">도전!</button>
                <button type="button" class="btn-stop" id="btnStop" disabled>중단</button>
            </div>
            <div class="jito-dual-select-row">
                <?php
                $오늘총타수 = (int)($오늘타수['cnt'] ?? 0);
                $자동돌리기횟수 = max(1, $오늘총타수);
                ?>
                <input type="hidden" id="autoCountValue" value="<?php echo (int)$자동돌리기횟수; ?>">
                <div class="getto-item" id="autoCountText" aria-label="오늘의 타수">
                    <span class="getto-label">오늘의 타수</span>
                    <strong><?php echo number_format($오늘총타수); ?>타</strong>
                </div>
                <?php if ($jito_name !== '' && $jito_code !== '') { ?>
                <div class="getto-item" id="weaponMultiplierBox">
                    <strong id="curMultiplier">x<?php echo max(1, (int)$jito_enhance); ?>배 적용중(<?=$jito_item?>)</strong>
                </div>
                <?php } ?>
            </div>
        </form>
        <?php } ?>

        <?php if ($jito_name !== '' && $jito_code !== '') {
            include_once $_SERVER['DOCUMENT_ROOT'] . '/api/jito_tax_rate.php';
            $jito_tax_rate_display = 지또_보유기준_세율표시문자열((int)$jito_point);
        ?>
        <section class="getto-section" data-initial-point="<?php echo (int)$jito_point; ?>">
            <div class="getto-table getto-oneline">
                <div class="getto-item">
                    <span class="getto-label">상태</span>
                    <strong id="curPoint"><?php echo number_format($jito_point); ?></strong>냥
                </div>
                <div class="getto-item">
                    <span class="getto-label">획득</span>
                    <strong id="sessionEarned">0</strong> 냥
                </div>
            
                <div class="getto-item">
                    <span class="getto-label">지또세율</span>
                    <strong id="jitoTaxRate"><?php echo htmlspecialchars($jito_tax_rate_display, ENT_QUOTES, 'UTF-8'); ?></strong>
                </div>
       
                <div class="getto-item">
                    <span class="getto-label">누적세금</span>
                    <strong id="sessionJitoTax">0</strong> 냥
                </div>
               
            </div>
        </section>
        <?php } ?>

        <div class="result" id="result" role="status"></div>
        <?php if (!$jito_need_code && $jito_code !== '') { ?>
        <p style="margin-top:10px;text-align:center;"><button type="button" id="btnLockStatus" class="btn-lock-status" aria-label="락 상태 확인">락 상태 확인</button></p>
        <?php } ?>
    </div>

    <script>
        (function() {
            var statusEl = document.getElementById('jitoUseStatus');
            var remainEl = document.getElementById('jitoRemain');
            var endIso = statusEl && statusEl.getAttribute('data-end');
            if (endIso && remainEl) {
                function updateRemain() {
                    var end = new Date(endIso.replace(/\s/, 'T'));
                    var now = new Date();
                    var sec = Math.max(0, Math.floor((end - now) / 1000));
                    if (sec <= 0) {
                        remainEl.textContent = '종료';
                        location.reload();
                        return;
                    }
                    var m = Math.floor(sec / 60);
                    var s = sec % 60;
                    remainEl.textContent = m + '분 ' + s + '초';
                }
                updateRemain();
                setInterval(updateRemain, 1000);
            }
        })();
        (function() {
            var f = document.getElementById('f');
            if (!f) return;
            var result = document.getElementById('result');
            var btn = document.getElementById('btnPlay');

            // 브라우저(탭) 1개만 사용: 탭별 토큰 생성
            var LOCK_KEY = 'jito_lock_token';
            var lockToken = sessionStorage.getItem(LOCK_KEY);
            if (!lockToken) {
                lockToken = Math.random().toString(36).slice(2) + Date.now().toString(36) + Math.random().toString(36).slice(2);
                sessionStorage.setItem(LOCK_KEY, lockToken);
            }

            var jitoLocked = true; // 로드 후 락 확보 전까지 true, 다른 탭 사용 중이면 true
            setLocked(true); // 락 응답 올 때까지 버튼 비활성

            var gettoSection = document.querySelector('.getto-section');
            var initialPoint = gettoSection ? (parseInt(gettoSection.getAttribute('data-initial-point'), 10) || 0) : 0;
            function updateSessionEarned(currentPoint) {
                var el = document.getElementById('sessionEarned');
                if (!el) return;
                var diff = (typeof currentPoint === 'number' ? currentPoint : parseInt(currentPoint, 10) || 0) - initialPoint;
                el.textContent = (diff >= 0 ? '+' : '') + Number(diff).toLocaleString();
            }

            var sessionJitoTaxTotal = 0;
            function updateSessionJitoTaxDisplay() {
                var el = document.getElementById('sessionJitoTax');
                if (el) el.textContent = Number(sessionJitoTaxTotal).toLocaleString();
            }
            function addTaxFromPlayMessage(msg) {
                if (!msg) return;
                var m = msg.match(/세금\s+([0-9,]+)냥/);
                if (m) {
                    var n = parseInt(m[1].replace(/,/g, ''), 10) || 0;
                    if (n > 0) {
                        sessionJitoTaxTotal += n;
                        updateSessionJitoTaxDisplay();
                    }
                }
            }
            function applyJitoTaxRateFromServer(label) {
                var el = document.getElementById('jitoTaxRate');
                if (el && label != null && label !== '') el.textContent = label;
            }
            function applyBalancePayload(b) {
                if (!b) return;
                var curPoint = document.getElementById('curPoint');
                var num2El = document.getElementById('num2');
                if (curPoint && b.point != null) curPoint.textContent = Number(b.point).toLocaleString();
                var curWeapon = document.getElementById('curWeapon');
                if (curWeapon && (b.item != null || b.style != null)) {
                    var item = (b.item || '').trim();
                    var style = (b.style || '').trim();
                    var enhance = (b.enhance != null) ? parseInt(b.enhance, 10) : 0;
                    var weaponText = item ? ('+' + (isNaN(enhance) ? 0 : enhance) + ' ' + (style + ' ' + item).trim()) : '-';
                    curWeapon.textContent = weaponText !== '' ? weaponText : '-';
                }
                if (num2El && b.enhance != null) num2El.value = Math.max(1, parseInt(b.enhance, 10) || 1);
                var curMultiplier = document.getElementById('curMultiplier');
                if (curMultiplier && b.enhance != null) {
                    curMultiplier.textContent = '내무기 x' + Math.max(1, parseInt(b.enhance, 10) || 1) + '배 적용중';
                }
                if (b.point != null) updateSessionEarned(b.point);
                if (b.jito_tax_rate != null) applyJitoTaxRateFromServer(b.jito_tax_rate);
            }

            function setLocked(locked) {
                jitoLocked = !!locked;
                var btnStop = document.getElementById('btnStop');
                if (btnStop) btnStop.disabled = true;
                if (btn) btn.disabled = locked;
            }

            // 항상 현재 페이지로 요청 (서브폴더/리라이트 환경에서도 동작)
            var jitoActionUrl = (function() {
                var path = window.location.pathname || '';
                var q = path.indexOf('?');
                return (q >= 0 ? path.slice(0, q) : path) || 'jito.php';
            })();

            // 페이지 로드 시 락 확보 (CODE당 1탭)
            (function acquireLock() {
                var codeInput = document.getElementById('code');
                var code = codeInput ? (codeInput.value || '').trim() : '';
                var form = new FormData();
                form.append('action', 'acquire_lock');
                form.append('lock_token', lockToken);
                if (code) form.append('code', code);
                fetch(jitoActionUrl, { method: 'POST', body: form })
                    .then(function(r) {
                        if (!r.ok) throw new Error('서버 오류');
                        return r.json();
                    })
                    .then(function(data) {
                        if (data && data.lock_ok) {
                            setLocked(false);
                            return;
                        }
                        setLocked(true);
                        showResult((data && data.data) ? data.data : '다른 브라우저(또는 탭)에서 이용 중입니다.', 'lose');
                        result.style.display = 'block';
                        result.classList.add('show');
                    })
                    .catch(function() {
                        setLocked(true);
                        showResult('서버에 연결할 수 없습니다. 주소와 네트워크를 확인해주세요.', 'lose');
                        result.style.display = 'block';
                        result.classList.add('show');
                    });
            })();

            // 락 실패 시 상태 확인 버튼 (디버깅/확인용)
            var lockStatusBtn = document.getElementById('btnLockStatus');
            if (lockStatusBtn) {
                lockStatusBtn.addEventListener('click', function() {
                    var codeInput = document.getElementById('code');
                    var code = codeInput ? (codeInput.value || '').trim() : '';
                    var form = new FormData();
                    form.append('action', 'lock_status');
                    form.append('lock_token', lockToken);
                    if (code) form.append('code', code);
                    fetch(jitoActionUrl, { method: 'POST', body: form })
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            var msg = '락 상태: ' + (data.hint || '-') + (data.last_activity_sec_ago != null ? '\n마지막 활동 ' + data.last_activity_sec_ago + '초 전' : '');
                            showResult(msg, 'info');
                            result.style.display = 'block';
                            result.classList.add('show');
                        })
                        .catch(function() { showResult('상태 확인 실패', 'lose'); });
                });
            }

            function showResult(text, type) {
                result.textContent = text || '';
                result.className = 'result show ' + (type || 'info');
                result.style.display = text ? 'block' : 'none';
            }

            var autoStopRequested = false;
            var btnStop = document.getElementById('btnStop');

            if (btnStop) {
                btnStop.addEventListener('click', function() {
                    autoStopRequested = true;
                });
            }

            function startJitoBatch() {
                if (jitoLocked) return;
                var codeInput = document.getElementById('code');
                var code = codeInput ? (codeInput.value || '').trim() : '';
                var nick = (document.getElementById('nick').value || '').trim();
                var num2 = parseInt(document.getElementById('num2').value, 10) || 1;
                if (!code && !nick) {
                    showResult('닉네임을 입력해주세요.', 'info');
                    return;
                }
                var autoCountInput = document.getElementById('autoCountValue');
                var total = autoCountInput ? (parseInt(autoCountInput.value, 10) || 100) : 100;
                if (total < 1) total = 1;
                autoStopRequested = false;
                btn.disabled = true;
                if (btnStop) btnStop.disabled = false;
                showResult('0/' + total + ' 진행 중...', 'info');

                function runOne(n) {
                        var form = new FormData();
                        form.append('action', 'play');
                        form.append('lock_token', lockToken);
                        if (code) form.append('code', code);
                        else form.append('nick', nick);
                        form.append('num2', num2);
                        return fetch(jitoActionUrl, { method: 'POST', body: form })
                            .then(function(r) { return r.json(); })
                            .then(function(data) {
                                var msg = (data && data.data) ? data.data : '';
                                if (msg && /다른 브라우저|다른 탭/.test(msg)) {
                                    setLocked(true);
                                    showResult(msg + '\n\n(' + n + '회에서 중단)', 'lose');
                                    return { stop: true };
                                }
                                if (msg && /이용불가|유효하지|닉네임을|타수|불가/.test(msg)) {
                                    showResult(msg + '\n\n(' + n + '회에서 중단)', 'lose');
                                    return { stop: true };
                                }
                                return { stop: false, msg: msg };
                            })
                            .catch(function() {
                                showResult(n + '회에서 요청 실패. 다시 시도해주세요.', 'lose');
                                return { stop: true };
                            });
                    }

                function done() {
                    btn.disabled = false;
                    if (btnStop) btnStop.disabled = true;
                }

                function loop(count) {
                        if (autoStopRequested) {
                            showResult((count - 1) + '/' + total + ' 중단됨', 'info');
                            done();
                            if (code) {
                                var fd = new FormData();
                                fd.append('action', 'get_balance');
                                fd.append('lock_token', lockToken);
                                fd.append('code', code);
                                fetch(jitoActionUrl, { method: 'POST', body: fd })
                                    .then(function(r) { return r.json(); })
                                    .then(function(b) {
                                        if (b.lock_error) return;
                                        applyBalancePayload(b);
                                    })
                                    .catch(function() {});
                            }
                            return;
                        }
                        if (count > total) {
                            showResult(total + '회 완료!', 'info');
                            done();
                            if (code) {
                                var fd = new FormData();
                                fd.append('action', 'get_balance');
                                fd.append('lock_token', lockToken);
                                fd.append('code', code);
                                fetch(jitoActionUrl, { method: 'POST', body: fd })
                                    .then(function(r) { return r.json(); })
                                    .then(function(b) {
                                        if (b.lock_error) return;
                                        applyBalancePayload(b);
                                    })
                                    .catch(function() {});
                            }
                            return;
                        }
                        runOne(count).then(function(res) {
                            if (res.stop) {
                                done();
                                return;
                            }
                            if (autoStopRequested) {
                                showResult(count + '/' + total + ' 중단됨', 'info');
                                done();
                                return;
                            }
                            var msg = res.msg || '-';
                            addTaxFromPlayMessage(res.msg || '');
                            // "다른 번호 입력해줘" 류 문구는 따로 노출하지 않고 실패로만 처리
                            var isDup = /다른 번호|입력해줘/.test(msg);
                            if (isDup) {
                                msg = '';
                            }
                            var kind, type;
                            if (/꽝|차감|탕진/.test(msg) || isDup) {
                                kind = '실패';
                                type = 'lose';
                            } else if (/획득|당첨|냥 획득/.test(msg)) {
                                kind = '당첨';
                                type = 'win';
                            } else {
                                kind = '결과';
                                type = 'info';
                            }
                            var oneLineMsg = msg ? String(msg).replace(/\s*\n+\s*/g, ' ').trim() : '';
                            showResult("● "+count + '/' + total + (oneLineMsg ? ' ' + oneLineMsg : ''), type);
                            if (type === 'win') {
                                result.classList.add('sparkle');
                                setTimeout(function() { result.classList.remove('sparkle'); }, 600);
                            }
                            if (code) {
                                var fd = new FormData();
                                fd.append('action', 'get_balance');
                                fd.append('lock_token', lockToken);
                                fd.append('code', code);
                                fetch(jitoActionUrl, { method: 'POST', body: fd })
                                    .then(function(r) { return r.json(); })
                                    .then(function(b) {
                                        if (b.lock_error) { setLocked(true); return; }
                                        applyBalancePayload(b);
                                    })
                                    .catch(function() {});
                            }
                            var nextDelayMs = /2배\s*확률\s*발동/.test(res.msg || '') ? 2000 : 200;
                            setTimeout(function() { loop(count + 1); }, nextDelayMs);
                        });
                }
                loop(1);
            }

            f.addEventListener('submit', function(e) {
                e.preventDefault();
                startJitoBatch();
            });

            // 배수는 무기 강화수치로 자동 적용됨 (수동 선택 제거)
        })();
    </script>
</body>
</html>
