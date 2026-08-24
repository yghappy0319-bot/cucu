<?php
include_once __DIR__ . '/../../../common.php';

header('Content-Type: text/plain; charset=UTF-8');

if (!isset($_SESSION['midx']) || (int)$_SESSION['midx'] <= 0) {
    die('로그인이 필요합니다.');
}

$member = 로그인정보($_SESSION['midx']);
if (!isset($member['mb_no'])) {
    die('회원 정보를 확인할 수 없습니다.');
}

if ($member['mb_id'] !== 'admin') {
    die('계정 정보는 관리자만 저장할 수 있습니다.');
}

function tb_panel_admin_pw_is_hash($stored)
{
    if ($stored === '' || $stored === null) {
        return false;
    }
    $info = password_get_info($stored);

    return !empty($info['algo']);
}

function tb_panel_admin_pw_plain_value($row)
{
    if (!empty($row['admin_pw_plain'])) {
        return $row['admin_pw_plain'];
    }
    if (!empty($row['admin_pw']) && !tb_panel_admin_pw_is_hash($row['admin_pw'])) {
        return $row['admin_pw'];
    }

    return '';
}

global $conn;

$idx = isset($_POST['idx']) ? (int)$_POST['idx'] : 0;
if ($idx <= 0) {
    die('패널을 찾을 수 없습니다.');
}

$admin_id = isset($_POST['admin_id']) ? trim($_POST['admin_id']) : '';
$admin_pw = isset($_POST['admin_pw']) ? trim($_POST['admin_pw']) : '';
$ftp_id = isset($_POST['ftp_id']) ? trim($_POST['ftp_id']) : '';
$ftp_pw = isset($_POST['ftp_pw']) ? trim($_POST['ftp_pw']) : '';
$db_name = isset($_POST['db_name']) ? trim($_POST['db_name']) : '';
$db_id = isset($_POST['db_id']) ? trim($_POST['db_id']) : '';
$db_pw = isset($_POST['db_pw']) ? trim($_POST['db_pw']) : '';

$row = db_select("select idx, status, admin_pw, admin_pw_plain from tb_panel where idx = {$idx} limit 1 ");
if (!$row || !isset($row['idx'])) {
    die('데이터를 찾을 수 없습니다.');
}
if ((int)$row['status'] === 2) {
    die('삭제 완료된 패널은 수정할 수 없습니다.');
}

$esc_admin_id = mysqli_real_escape_string($conn, $admin_id);
if ($admin_id !== '') {
    $dup = db_select("select idx from tb_panel where admin_id = '{$esc_admin_id}' and idx != {$idx} limit 1 ");
    if ($dup && isset($dup['idx'])) {
        die('이미 사용 중인 관리자 아이디입니다.');
    }
}
$esc_ftp_id = mysqli_real_escape_string($conn, $ftp_id);
$esc_ftp_pw = mysqli_real_escape_string($conn, $ftp_pw);
$esc_db_name = mysqli_real_escape_string($conn, $db_name);
$esc_db_id = mysqli_real_escape_string($conn, $db_id);
$esc_db_pw = mysqli_real_escape_string($conn, $db_pw);

$sql = "update tb_panel set ";
$sql .= "admin_id = '{$esc_admin_id}', ";

$current_plain = tb_panel_admin_pw_plain_value($row);
if ($admin_pw !== '' && $admin_pw !== $current_plain) {
    $hashed = password_hash($admin_pw, PASSWORD_DEFAULT);
    $esc_admin_pw_hash = mysqli_real_escape_string($conn, $hashed);
    $esc_admin_pw_plain = mysqli_real_escape_string($conn, $admin_pw);
    $sql .= "admin_pw = '{$esc_admin_pw_hash}', ";
    $sql .= "admin_pw_plain = '{$esc_admin_pw_plain}', ";
}

$sql .= "ftp_id = '{$esc_ftp_id}', ";
$sql .= "ftp_pw = '{$esc_ftp_pw}', ";
$sql .= "db_name = '{$esc_db_name}', ";
$sql .= "db_id = '{$esc_db_id}', ";
$sql .= "db_pw = '{$esc_db_pw}', ";
$sql .= "moddate = now() ";
$sql .= "where idx = {$idx} limit 1";

$result = db_query($sql);
if ($result) {
    echo '1';
} else {
    $err = mysqli_error($conn);
    echo '저장에 실패했습니다. ' . ($err !== '' ? $err : '테이블(tb_panel) 및 계정 정보 컬럼을 확인해 주세요.');
}
