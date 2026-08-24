<?php
require_once __DIR__ . '/../lib/_function.php';

/* =============================================================================
 * DEBUG MODE
 *   - URL 끝에 ?debug=1 을 붙이거나, 아래 $DEBUG = true 로 바꾸면
 *     alert 대신 상세 디버그 화면을 출력합니다.
 *   - 운영 배포 시에는 반드시 false 로 되돌려주세요.
 * =============================================================================
 */
$DEBUG = isset($_GET['debug']) || isset($_POST['debug']);
// $DEBUG = true; // 강제 활성화하고 싶으면 주석 해제

if ($DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    @mysqli_report(MYSQLI_REPORT_OFF); // 예외로 튀지 않도록 (아래에서 직접 체크)
}

// 에러 로그 파일 경로
$LOG_DIR  = __DIR__ . '/../logs';
$LOG_FILE = $LOG_DIR . '/register_error.log';
if (!is_dir($LOG_DIR)) { @mkdir($LOG_DIR, 0775, true); }

/**
 * 디버그/로그 종료 처리
 *  - DEBUG: HTML 디버그 페이지 출력
 *  - 평상시: 로그 파일에 기록 후 alert_goto
 */
function reg_fail($step, $msg, $extra = []) {
    global $DEBUG, $LOG_FILE, $conn;

    $mysqli_error = $conn ? mysqli_error($conn) : '(no connection)';
    $mysqli_errno = $conn ? mysqli_errno($conn) : 0;

    $log_line = sprintf(
        "[%s] STEP=%s | MSG=%s | MYSQLI(%d)=%s | EXTRA=%s | POST=%s\n",
        date('Y-m-d H:i:s'),
        $step,
        $msg,
        $mysqli_errno,
        $mysqli_error,
        json_encode($extra, JSON_UNESCAPED_UNICODE),
        json_encode(array_map(function($v){
            return is_string($v) && strlen($v) > 200 ? substr($v,0,200).'...' : $v;
        }, $_POST), JSON_UNESCAPED_UNICODE)
    );
    @file_put_contents($LOG_FILE, $log_line, FILE_APPEND | LOCK_EX);

    if ($DEBUG) {
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!doctype html><meta charset="utf-8"><title>Register Debug</title>';
        echo '<style>body{font:13px/1.6 ui-monospace,Menlo,monospace;padding:24px;background:#0f172a;color:#e2e8f0;}';
        echo 'h1{color:#f87171;font-size:20px;margin:0 0 16px;} h2{color:#fbbf24;font-size:14px;margin:20px 0 6px;border-bottom:1px solid #334155;padding-bottom:4px;}';
        echo 'pre{background:#1e293b;padding:14px;border-radius:6px;overflow:auto;white-space:pre-wrap;word-break:break-all;color:#e2e8f0;}';
        echo '.k{color:#94a3b8}.v{color:#a5f3fc}.err{color:#fca5a5;font-weight:700}</style>';
        echo '<h1>× 회원가입 디버그 [' . htmlspecialchars($step) . ']</h1>';
        echo '<div class="err">' . htmlspecialchars($msg) . '</div>';

        echo '<h2>MySQL Error</h2><pre>('.$mysqli_errno.') '.htmlspecialchars($mysqli_error ?: '(none)').'</pre>';
        echo '<h2>POST</h2><pre>'.htmlspecialchars(print_r($_POST, true)).'</pre>';
        if (!empty($extra)) {
            echo '<h2>Extra</h2><pre>'.htmlspecialchars(print_r($extra, true)).'</pre>';
        }
        echo '<h2>Server</h2><pre>';
        echo 'PHP       : '.PHP_VERSION."\n";
        echo 'mysqli    : '.(function_exists('mysqli_connect') ? 'ok' : 'MISSING')."\n";
        echo 'session   : '.session_id()."\n";
        echo 'Log file  : '.$GLOBALS['LOG_FILE']."\n";
        echo '</pre>';
        echo '<p style="margin-top:20px"><a style="color:#fbbf24" href="javascript:history.back()">← 뒤로</a></p>';
        exit;
    }

    alert_goto($msg);
}

/* ─────────────────────────────────────────────
 * 0. 접근 방법 체크
 * ───────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    reg_fail('METHOD', '잘못된 접근입니다.');
}

/* ─────────────────────────────────────────────
 * 1. 입력 수집
 * ───────────────────────────────────────────── */
$mb_id       = trim($_POST['mb_id']        ?? '');
$mb_pw       = (string)($_POST['mb_pw']    ?? '');
$mb_pw_conf  = (string)($_POST['mb_pw_confirm'] ?? '');
$mb_name     = trim($_POST['mb_name']      ?? '');
$mb_nick     = trim($_POST['mb_nick']      ?? '');
$mb_email    = trim($_POST['mb_email']     ?? '');
$mb_phone_raw = trim($_POST['mb_phone']   ?? '');

$agree_terms     = !empty($_POST['agree_terms'])     ? 1 : 0;
$agree_privacy   = !empty($_POST['agree_privacy'])   ? 1 : 0;
$agree_marketing = !empty($_POST['agree_marketing']) ? 1 : 0;

/* ─────────────────────────────────────────────
 * 2. 유효성 검사
 * ───────────────────────────────────────────── */
if (!preg_match('/^[A-Za-z0-9_]{4,20}$/', $mb_id)) {
    reg_fail('VALIDATE_ID', '아이디는 영문/숫자/밑줄 4~20자여야 합니다.', ['mb_id' => $mb_id]);
}
if (strlen($mb_pw) < 8 || strlen($mb_pw) > 50) {
    reg_fail('VALIDATE_PW', '비밀번호는 8자 이상이어야 합니다.', ['len' => strlen($mb_pw)]);
}
if ($mb_pw !== $mb_pw_conf) {
    reg_fail('VALIDATE_PW_MATCH', '비밀번호가 일치하지 않습니다.');
}
if ($mb_name === '' || mb_strlen($mb_name) > 20) {
    reg_fail('VALIDATE_NAME', '이름을 올바르게 입력해주세요.', ['mb_name' => $mb_name]);
}
if ($mb_nick === '' || mb_strlen($mb_nick) > 20) {
    reg_fail('VALIDATE_NICK', '닉네임을 올바르게 입력해주세요.', ['mb_nick' => $mb_nick]);
}
if (!filter_var($mb_email, FILTER_VALIDATE_EMAIL)) {
    reg_fail('VALIDATE_EMAIL', '이메일 형식이 올바르지 않습니다.', ['mb_email' => $mb_email]);
}
$mb_phone = mb_phone_normalize_kr($mb_phone_raw);
if ($mb_phone === null) {
    reg_fail('VALIDATE_PHONE', '휴대폰 번호를 올바르게 입력해 주세요. (예: 010-1234-5678)', ['mb_phone' => $mb_phone_raw]);
}
$mb_phone_digits = mb_phone_digits($mb_phone);
if (!$agree_terms || !$agree_privacy) {
    reg_fail('VALIDATE_AGREE', '필수 약관에 동의해 주세요.',
        ['terms' => $agree_terms, 'privacy' => $agree_privacy]);
}

/* ─────────────────────────────────────────────
 * 3. DB 연결 체크
 * ───────────────────────────────────────────── */
if (!$conn || mysqli_connect_errno()) {
    reg_fail('DB_CONNECT', 'DB 연결에 실패했습니다.',
        ['errno' => mysqli_connect_errno(), 'error' => mysqli_connect_error()]);
}

/* ─────────────────────────────────────────────
 * 4. 중복 체크
 * ───────────────────────────────────────────── */
$esc_id    = db_escape($mb_id);
$esc_nick  = db_escape($mb_nick);
$esc_email = db_escape($mb_email);

$rs_id = @mysqli_query($conn, "SELECT COUNT(*) FROM tb_member WHERE mb_id='{$esc_id}'");
if (!$rs_id) {
    reg_fail('DUP_CHECK_ID', '아이디 중복 검사 중 DB 오류.',
        ['sql' => "SELECT ... mb_id='{$esc_id}'"]);
}
if ((int)mysqli_fetch_row($rs_id)[0] > 0) {
    reg_fail('DUP_ID', '이미 사용 중인 아이디입니다.', ['mb_id' => $mb_id]);
}

$rs_nick = @mysqli_query($conn, "SELECT COUNT(*) FROM tb_member WHERE mb_nick='{$esc_nick}'");
if (!$rs_nick) {
    reg_fail('DUP_CHECK_NICK', '닉네임 중복 검사 중 DB 오류.');
}
if ((int)mysqli_fetch_row($rs_nick)[0] > 0) {
    reg_fail('DUP_NICK', '이미 사용 중인 닉네임입니다.', ['mb_nick' => $mb_nick]);
}

$rs_email = @mysqli_query($conn, "SELECT COUNT(*) FROM tb_member WHERE mb_email='{$esc_email}'");
if (!$rs_email) {
    reg_fail('DUP_CHECK_EMAIL', '이메일 중복 검사 중 DB 오류.');
}
if ((int)mysqli_fetch_row($rs_email)[0] > 0) {
    reg_fail('DUP_EMAIL', '이미 가입된 이메일입니다.', ['mb_email' => $mb_email]);
}

$esc_phone_digits = db_escape($mb_phone_digits);
$rs_phone = @mysqli_query(
    $conn,
    "SELECT COUNT(*) AS c FROM tb_member
     WHERE IFNULL(`mb_phone`, '') <> ''
       AND REPLACE(REPLACE(REPLACE(`mb_phone`, '-', ''), ' ', ''), '.', '') = '{$esc_phone_digits}'"
);
if (!$rs_phone) {
    reg_fail('DUP_CHECK_PHONE', '휴대폰 중복 검사 중 DB 오류.');
}
if ((int) (mysqli_fetch_assoc($rs_phone)['c'] ?? 0) > 0) {
    reg_fail('DUP_PHONE', '이미 가입된 휴대폰 번호입니다.');
}

/* ─────────────────────────────────────────────
 * 5. INSERT
 * ───────────────────────────────────────────── */
$pw_hash = password_hash($mb_pw, PASSWORD_DEFAULT);
if ($pw_hash === false) {
    reg_fail('HASH', '비밀번호 해시 생성에 실패했습니다.');
}

$sql = "
    INSERT INTO tb_member
        (mb_id, mb_pw, mb_name, mb_nick, mb_email, mb_phone,
         mb_agree_terms, mb_agree_privacy, mb_agree_marketing,
         mb_created_at, mb_updated_at)
    VALUES
        ('".db_escape($mb_id)."',
         '".db_escape($pw_hash)."',
         '".db_escape($mb_name)."',
         '".db_escape($mb_nick)."',
         '".db_escape($mb_email)."',
         '".db_escape($mb_phone)."',
         {$agree_terms}, {$agree_privacy}, {$agree_marketing},
         NOW(), NOW())
";

$ok = @mysqli_query($conn, $sql);

if (!$ok) {
    reg_fail('INSERT', '회원가입 INSERT 실패.', ['sql' => $sql]);
}

$new_mb_idx = (int) db_insert_id();
point_reward_add($new_mb_idx, point_reward_signup(), 'signup', '회원가입 축하 포인트');

if ($DEBUG) {
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><meta charset="utf-8"><title>Register OK</title>';
    echo '<pre style="font:13px/1.6 ui-monospace,Menlo,monospace;padding:24px;">';
    echo "✓ 회원가입 성공\n";
    echo "mb_idx = " . db_insert_id() . "\n";
    echo "mb_id  = " . htmlspecialchars($mb_id) . "\n";
    echo '</pre>';
    echo '<a href="/login.php">로그인 페이지로 →</a>';
    exit;
}

alert_goto('회원가입이 완료되었습니다. 가입 축하 ' . number_format(point_reward_signup()) . 'P가 지급되었습니다. 로그인해 주세요.', '/login.php');
