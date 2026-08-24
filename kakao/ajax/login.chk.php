<?php
include_once "../common.php";
include_once "../lib/pass.lib.php";

header('Content-Type: application/json; charset=UTF-8');

global $conn;

$mb_id = isset($mb_id) ? trim((string) $mb_id) : '';
$mb_password = isset($mb_password) ? (string) $mb_password : '';

if ($mb_id === '' || $mb_password === '') {
    echo json_encode(array('status' => '', 'msg' => '로그인 정보를 확인해주세요.'), JSON_UNESCAPED_UNICODE);
    exit;
}

if (!preg_match('/^[A-Za-z0-9_\-]{2,50}$/', $mb_id)) {
    echo json_encode(array('status' => '', 'msg' => '로그인 정보를 확인해주세요.'), JSON_UNESCAPED_UNICODE);
    exit;
}

$mb_id_esc = mysqli_real_escape_string($conn, $mb_id);
$data = db_select("select * from member where mb_id = '{$mb_id_esc}' limit 1 ");
if (!$data || empty($data['mb_id'])) {
    die('null_id');
}

if (login_password_check($data, $mb_password, $data['mb_password'])) {
    $mb_no = (int) $data['mb_no'];
    $ip_esc = mysqli_real_escape_string($conn, isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '');
    db_query("update member set mb_today_login = now(), mb_login_ip = '{$ip_esc}' where mb_no = {$mb_no} limit 1 ");
    $_SESSION['midx'] = $mb_no;
    $out = array(
        'status' => isset($status) ? $status : '',
        'msg' => 'login_ok',
        'url' => isset($data['mb_start_page']) ? $data['mb_start_page'] : '/adm/index.php',
    );
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(array('status' => '', 'msg' => '로그인 정보를 확인해주세요.'), JSON_UNESCAPED_UNICODE);
}
