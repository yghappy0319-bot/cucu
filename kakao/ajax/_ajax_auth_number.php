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
$phone_raw = isset($phone) ? trim((string)$phone) : '';
$phone_digits = preg_replace('/[^0-9]/', '', $phone_raw);

if ($phone_digits === '' || strlen($phone_digits) < 10 || strlen($phone_digits) > 11) {
    die(결과(0, '올바른 연락처를 입력해주세요.'));
}

$phone_esc = mysqli_real_escape_string($conn, $phone_raw);
$phone_digits_esc = mysqli_real_escape_string($conn, $phone_digits);

// 본인 번호는 허용, 다른 회원 번호만 차단
$hpchk = db_select("select count(*) as cnt from member where mb_no != {$mb_no} and (REPLACE(mb_hp,'-','') = '{$phone_digits_esc}' OR mb_hp = '{$phone_esc}') ");
if ($hpchk && (int)$hpchk['cnt'] > 0) {
    die(결과(0, '이미 등록된 번호 입니다.'));
}

$년월일오늘 = date('Y-m-d');
$send_count = db_select("select count(*) as cnt from tb_sms_send where status = '휴대번호번호변경' and mb_no = {$mb_no} and senddate = '{$년월일오늘}' ");
if ($send_count && (int)$send_count['cnt'] > 2) {
    die(결과(0, '금일은 문자 발송한도가 초과되었습니다. 내일 다시 시도해주세요.'));
}

$code = 랜덤문자열(6);
$code_esc = mysqli_real_escape_string($conn, $code);

db_query("insert into tb_sms_send set mb_no = {$mb_no}, status = '휴대번호번호변경', code1 = '{$phone_digits_esc}', code2 = '{$code_esc}', chk = 0, senddate = '{$년월일오늘}', regdate = now() ");

$내용 = "럭키뱅크 인증번호\n[" . $code . "]";
$result = 다이랙트샌드($phone_digits, '럭키뱅크 인증번호', $내용);

if ($result === false) {
    die(결과(0, '인증번호 발송에 실패했습니다. 잠시 후 다시 시도해주세요.'));
}
if ($result === '0' || $result === 0 || $result === null || $result === '') {
    die(결과(1, '인증번호가 발송되었습니다.'));
}

die(결과(0, '인증번호 발송에 실패했습니다. (코드: ' . $result . ')'));
