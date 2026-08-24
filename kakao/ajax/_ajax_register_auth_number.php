<?php
include_once '../common.php';

header('Content-Type: application/json; charset=UTF-8');

global $conn;

$phone_raw = isset($phone) ? trim((string) $phone) : '';
$phone = preg_replace('/[^0-9]/', '', $phone_raw);

if ($phone === '' || strlen($phone) < 10 || strlen($phone) > 11) {
    die(결과(0, '올바른 연락처를 입력해주세요.'));
}

$phone_esc = mysqli_real_escape_string($conn, $phone);
$phone_hyphen_esc = mysqli_real_escape_string($conn, $phone_raw);
$년월일오늘 = date('Y-m-d');

$hpchk = db_select("select count(*) as cnt from member where REPLACE(mb_hp,'-','') = '{$phone_esc}' OR mb_hp = '{$phone_hyphen_esc}' ");
if ($hpchk && (int) $hpchk['cnt'] > 0) {
    die(결과(0, '이미 가입된 번호 입니다.'));
}

$send_count = db_select("select count(*) as cnt from tb_sms_send where status = '회원가입' and code1 = '{$phone_esc}' and senddate = '{$년월일오늘}' ");
if ($send_count && (int) $send_count['cnt'] > 2) {
    die(결과(0, '금일은 문자 발송한도가 초과되었습니다. 내일 다시 시도해주세요.'));
}

$code = 랜덤문자열(6);
$code_esc = mysqli_real_escape_string($conn, $code);

db_query("insert into tb_sms_send set mb_no = 0, status = '회원가입', code1 = '{$phone_esc}', code2 = '{$code_esc}', chk = 0, senddate = '{$년월일오늘}', regdate = now() ");

$내용 = "럭키뱅크 인증번호\n[" . $code . "]";
$result = 다이랙트샌드($phone, '럭키뱅크 인증번호', $내용);

// DirectSend status 0 = 성공. 기존 호환: falsy/"0" 도 성공으로 간주
if ($result === false) {
    die(결과(0, '인증번호 발송에 실패했습니다. 잠시 후 다시 시도해주세요.'));
}
if ($result === '0' || $result === 0 || $result === null || $result === '') {
    if (!isset($_SESSION)) {
        session_start();
    }
    unset($_SESSION['register_phone_verified']);
    unset($_SESSION['register_phone_verified_at']);
    $_SESSION['register_phone_pending'] = $phone;
    die(결과(1, '인증번호가 발송되었습니다.'));
}

die(결과(0, '인증번호 발송에 실패했습니다. (코드: ' . $result . ')'));
