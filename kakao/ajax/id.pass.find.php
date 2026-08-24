<?php
include_once '../common.php';
include_once '../lib/pass.lib.php';

header('Content-Type: application/json; charset=UTF-8');

global $conn;

$mb_hp = isset($mb_hp) ? trim((string) $mb_hp) : '';
if ($mb_hp === '') {
    die(json_encode(array('status' => '0', 'msg' => '휴대폰 번호를 입력해주세요.'), JSON_UNESCAPED_UNICODE));
}

$mb_hp_esc = mysqli_real_escape_string($conn, $mb_hp);
$년월일오늘 = date('Y-m-d');
$hpchk = db_select("select mb_no, mb_id from member where mb_hp = '{$mb_hp_esc}' limit 1 ");

if (!$hpchk || !isset($hpchk['mb_no']) || (int) $hpchk['mb_no'] <= 0) {
    die(json_encode(array('status' => '0', 'msg' => '가입이력이 없는번호 입니다.'), JSON_UNESCAPED_UNICODE));
}

// 관리자 계정은 비밀번호 찾기로 변경 불가
if (isset($hpchk['mb_id']) && $hpchk['mb_id'] === 'admin') {
    die(json_encode(array('status' => '0', 'msg' => '관리자 계정은 비밀번호 찾기를 사용할 수 없습니다. 관리자에게 문의해주세요.'), JSON_UNESCAPED_UNICODE));
}

$send_count = db_select("select count(*) as cnt from tb_sms_send where status = '비번찾기' and code1 = '{$mb_hp_esc}' and senddate = '{$년월일오늘}' ");
if ($send_count && (int) $send_count['cnt'] > 2) {
    die(json_encode(array('status' => '0', 'msg' => '금일은 문자 발송한도가 초과되었습니다. 내일 다시 시도해주세요.'), JSON_UNESCAPED_UNICODE));
}

// 숫자만이 아닌 영문+숫자 10자리 임시 비밀번호
$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
$임시비밀번호 = '';
$chars_len = strlen($chars);
for ($i = 0; $i < 10; $i++) {
    $임시비밀번호 .= $chars[mt_rand(0, $chars_len - 1)];
}

$mb_no = (int) $hpchk['mb_no'];
$new_pass = get_encrypt_string($임시비밀번호);
$new_pass_esc = mysqli_real_escape_string($conn, $new_pass);
db_query("update member set mb_password = '{$new_pass_esc}' where mb_no = {$mb_no} limit 1 ");

$내용 = "럭키뱅크 아이디/비번찾기 결과\n";
$내용 .= '아이디 : ' . $hpchk['mb_id'] . "\n";
$내용 .= '임시비밀번호 : ' . $임시비밀번호;
다이랙트샌드($mb_hp, '럭키뱅크 아이디 비번찾기 결과', $내용);

// 평문 임시비번은 DB에 저장하지 않음 (발송 이력만 남김)
db_query("insert into tb_sms_send set status = '비번찾기', code1 = '{$mb_hp_esc}', code2 = '', senddate = '{$년월일오늘}', regdate = now() ");

die(json_encode(array('status' => '1', 'msg' => '입력된 번호로 아이디/비밀번호가 발송되었습니다.'), JSON_UNESCAPED_UNICODE));
