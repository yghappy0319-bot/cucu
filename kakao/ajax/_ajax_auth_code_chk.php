<?php
include_once('../common.php');

header('Content-Type: application/json; charset=UTF-8');

global $conn;

if (!isset($_SESSION['midx']) || (int)$_SESSION['midx'] <= 0) {
    die(결과(0, '로그인이 필요합니다.'));
}

$login_member = 로그인정보($_SESSION['midx']);
if (!$login_member || empty($login_member['mb_no'])) {
    die(결과(0, '로그인이 필요합니다.'));
}

$mb_no = (int)$login_member['mb_no'];
$phone_raw = isset($_POST['phone']) ? trim((string)$_POST['phone']) : (isset($phone) ? trim((string)$phone) : '');
$code = isset($_POST['code']) ? trim((string)$_POST['code']) : (isset($code) ? trim((string)$code) : '');
$phone_digits = preg_replace('/[^0-9]/', '', $phone_raw);

if ($phone_digits === '' || strlen($phone_digits) < 10) {
    die(결과(0, '올바른 연락처를 입력해주세요.'));
}
if ($code === '' || !preg_match('/^\d{6}$/', $code)) {
    die(결과(0, '인증번호 6자리를 입력해주세요.'));
}

$phone_esc = mysqli_real_escape_string($conn, $phone_digits);
$code_esc = mysqli_real_escape_string($conn, $code);

$sql = "select * from tb_sms_send where mb_no = {$mb_no} and code1 = '{$phone_esc}' and code2 = '{$code_esc}' and chk = 0 and status = '휴대번호번호변경' and regdate >= DATE_SUB(NOW(), INTERVAL 10 MINUTE) order by idx desc limit 1 ";
$code_chk = db_select($sql);

if (!$code_chk || empty($code_chk['idx'])) {
    die(결과(0, '인증번호를 확인해주세요.'));
}

db_query('update tb_sms_send set chk = 1 where idx = ' . (int)$code_chk['idx'] . ' limit 1 ');

$phone_save_esc = mysqli_real_escape_string($conn, $phone_raw !== '' ? $phone_raw : $phone_digits);
db_query("update member set mb_hp = '{$phone_save_esc}', mb_hp_cert = 1 where mb_no = {$mb_no} limit 1 ");

die(결과(1, '휴대폰 인증이 완료되었습니다.'));
