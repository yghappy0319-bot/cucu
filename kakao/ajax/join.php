<?php
include_once '../common.php';
include_once '../lib/pass.lib.php';

header('Content-Type: application/json; charset=UTF-8');

global $conn;

$mb_id = isset($mb_id) ? trim((string) $mb_id) : '';
$mb_password = isset($mb_password) ? (string) $mb_password : '';
$mb_name = isset($mb_name) ? trim((string) $mb_name) : '';
$mb_homepage = isset($mb_homepage) ? trim((string) $mb_homepage) : '';
$mb_email = isset($mb_email) ? trim((string) $mb_email) : '';
$mb_hp = isset($mb_hp) ? trim((string) $mb_hp) : '';

if ($mb_id === '' || $mb_password === '' || $mb_name === '' || $mb_hp === '') {
    die(json_encode(array('status' => '0', 'msg' => '필수 정보를 입력해주세요.'), JSON_UNESCAPED_UNICODE));
}

if (!preg_match('/^[A-Za-z0-9_\-]{2,50}$/', $mb_id)) {
    die(json_encode(array('status' => '0', 'msg' => '아이디 형식이 올바르지 않습니다.'), JSON_UNESCAPED_UNICODE));
}

// 관리자 예약 아이디 가입 차단
$reserved_ids = array('admin', 'administrator', 'root', 'system');
if (in_array(strtolower($mb_id), $reserved_ids, true)) {
    die(json_encode(array('status' => '0', 'msg' => '사용할 수 없는 아이디입니다.'), JSON_UNESCAPED_UNICODE));
}

if (strlen($mb_password) < 6) {
    die(json_encode(array('status' => '0', 'msg' => '비밀번호는 6자리 이상 입력해주세요.'), JSON_UNESCAPED_UNICODE));
}

$mb_id_esc = mysqli_real_escape_string($conn, $mb_id);
$mb_hp_esc = mysqli_real_escape_string($conn, $mb_hp);

$idchk = db_select("select count(*) as cnt from member where mb_id = '{$mb_id_esc}' ");
if ($idchk && (int) $idchk['cnt'] > 0) {
    die(json_encode(array('status' => '0', 'msg' => '다른 아이디를 입력해주세요.'), JSON_UNESCAPED_UNICODE));
}

$hpchk = db_select("select count(*) as cnt from member where mb_hp = '{$mb_hp_esc}' ");
if ($hpchk && (int) $hpchk['cnt'] > 0) {
    die(json_encode(array('status' => '0', 'msg' => '이미 가입된 번호 입니다.'), JSON_UNESCAPED_UNICODE));
}

$phone_digits = preg_replace('/[^0-9]/', '', $mb_hp);
$verified_phone = isset($_SESSION['register_phone_verified']) ? (string) $_SESSION['register_phone_verified'] : '';
$verified_at = isset($_SESSION['register_phone_verified_at']) ? (int) $_SESSION['register_phone_verified_at'] : 0;
if ($verified_phone === '' || $verified_phone !== $phone_digits || $verified_at < (time() - 1800)) {
    die(json_encode(array('status' => '0', 'msg' => '휴대폰 인증을 완료해주세요.'), JSON_UNESCAPED_UNICODE));
}

$mb_name_esc = mysqli_real_escape_string($conn, $mb_name);
$mb_homepage_esc = mysqli_real_escape_string($conn, $mb_homepage);
$mb_email_esc = mysqli_real_escape_string($conn, $mb_email);
$pass_esc = mysqli_real_escape_string($conn, get_encrypt_string($mb_password));
$ip_esc = mysqli_real_escape_string($conn, isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '');

$sql = 'insert into member set ';
$sql .= "mb_id = '{$mb_id_esc}' ";
$sql .= ",mb_name = '{$mb_name_esc}' ";
$sql .= ",mb_nick = '{$mb_id_esc}' ";
$sql .= ",mb_password = '{$pass_esc}' ";
$sql .= ",mb_homepage = '{$mb_homepage_esc}' ";
$sql .= ",mb_email = '{$mb_email_esc}' ";
$sql .= ",mb_start_page = '/adm/index.php' ";
$sql .= ",mb_hp = '{$mb_hp_esc}' ";
$sql .= ',mb_hp_cert = 1 ';
$sql .= ',mb_datetime = now() ';
$sql .= ",mb_ip = '{$ip_esc}' ";

$result = db_query($sql);
if ($result) {
    unset($_SESSION['register_phone_verified'], $_SESSION['register_phone_verified_at'], $_SESSION['register_phone_pending']);
    die(json_encode(array('status' => '1', 'msg' => "회원으로 가입해주셔서 대단히 감사드립니다.\n카카오톡 메시지 꼭 남겨주세요!"), JSON_UNESCAPED_UNICODE));
}

die(json_encode(array('status' => '0', 'msg' => '가입에 실패했습니다. 잠시 후 다시 시도해주세요.'), JSON_UNESCAPED_UNICODE));
