<?php
include_once '../common.php';

header('Content-Type: application/json; charset=UTF-8');

global $conn;

$phone_raw = isset($phone) ? trim((string) $phone) : '';
$phone = preg_replace('/[^0-9]/', '', $phone_raw);
$code = isset($code) ? trim((string) $code) : '';

if ($phone === '' || strlen($phone) < 10) {
    die(결과(0, '올바른 연락처를 입력해주세요.'));
}
if ($code === '' || !preg_match('/^\d{6}$/', $code)) {
    die(결과(0, '인증번호 6자리를 입력해주세요.'));
}

$phone_esc = mysqli_real_escape_string($conn, $phone);
$code_esc = mysqli_real_escape_string($conn, $code);

// 최근 10분 이내 미확인 인증번호만 유효
$sql = "select * from tb_sms_send where status = '회원가입' and code1 = '{$phone_esc}' and code2 = '{$code_esc}' and chk = 0 and regdate >= DATE_SUB(NOW(), INTERVAL 10 MINUTE) order by idx desc limit 1 ";
$row = db_select($sql);

if (!$row || empty($row['idx'])) {
    die(결과(0, '인증번호를 확인해주세요.'));
}

db_query('update tb_sms_send set chk = 1 where idx = ' . (int) $row['idx'] . ' limit 1 ');

if (!isset($_SESSION)) {
    session_start();
}
$_SESSION['register_phone_verified'] = $phone;
$_SESSION['register_phone_verified_at'] = time();
unset($_SESSION['register_phone_pending']);

die(결과(1, '인증번호가 확인되었습니다.'));
