<?php
include_once '../common.php';
include_once '../_chk.php';

header('Content-Type: text/plain; charset=UTF-8');

global $conn;

if (!isset($_SESSION['midx']) || (int) $_SESSION['midx'] <= 0) {
    die('권한이 없습니다.');
}

$actor = 로그인정보($_SESSION['midx']);
if (!$actor || !isset($actor['mb_id']) || $actor['mb_id'] !== 'admin') {
    die('권한이 없습니다.');
}

$mb_id = isset($mb_id) ? trim((string) $mb_id) : '';
if ($mb_id === '' || !preg_match('/^[A-Za-z0-9_\-]{2,50}$/', $mb_id)) {
    die('회원 정보를 확인할 수 없습니다.');
}

$mb_id_esc = mysqli_real_escape_string($conn, $mb_id);
$data = db_select("select mb_no from member where mb_id = '{$mb_id_esc}' limit 1 ");
if ($data && isset($data['mb_no']) && (int) $data['mb_no'] > 0) {
    // 관리자 강제 접속: 실제 회원 로그인 IP/최근접속(mb_login_ip, mb_today_login)은 갱신하지 않음
    $_SESSION['midx'] = (int) $data['mb_no'];
    echo '1';
} else {
    die('회원 정보를 확인할 수 없습니다.');
}
