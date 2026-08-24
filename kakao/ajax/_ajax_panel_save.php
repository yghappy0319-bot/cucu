<?php
include_once __DIR__ . '/../common.php';

header('Content-Type: text/plain; charset=UTF-8');

if (!isset($_SESSION['midx']) || (int)$_SESSION['midx'] <= 0) {
    die('로그인이 필요합니다.');
}

$member = 로그인정보($_SESSION['midx']);
if (!isset($member['mb_no'])) {
    die('회원 정보를 확인할 수 없습니다.');
}

global $conn;

$save_action = isset($_POST['save_action']) ? $_POST['save_action'] : 'insert';
$idx = isset($_POST['idx']) ? (int)$_POST['idx'] : 0;
$panel_status = isset($_POST['panel_status']) ? (int)$_POST['panel_status'] : 1;
if ($panel_status !== 0) {
    $panel_status = 1;
}
$panel_name_ko = isset($_POST['panel_name_ko']) ? trim($_POST['panel_name_ko']) : '';
$panel_name_en = isset($_POST['panel_name_en']) ? trim($_POST['panel_name_en']) : '';
$contact_phone = isset($_POST['contact_phone']) ? trim($_POST['contact_phone']) : '';
$panel_url = isset($_POST['panel_url']) ? trim($_POST['panel_url']) : '';

function tb_panel_hangul_count($s)
{
    if (!preg_match_all('/[가-힣]/u', $s, $m)) {
        return 0;
    }
    return count($m[0]);
}

if (tb_panel_hangul_count($panel_name_ko) < 2) {
    die('패널명(한글)은 한글 2글자 이상 입력해 주세요.');
}

if (!preg_match('/^[A-Za-z]{6,}$/', $panel_name_en)) {
    die('패널명(영문)은 영문 대·소문자만 6자 이상 입력해 주세요.');
}

$digits = preg_replace('/\D+/', '', $contact_phone);
if ($digits === '' || strlen($digits) < 9 || strlen($digits) > 15) {
    die('담당자 연락처를 올바르게 입력해 주세요.');
}

if ($panel_url === '' || stripos($panel_url, 'https://') !== 0) {
    die('패널 접속 URL은 https:// 로 시작하는 전체 주소를 입력해 주세요.');
}

$ko = mysqli_real_escape_string($conn, $panel_name_ko);
$en = mysqli_real_escape_string($conn, $panel_name_en);
$phone = mysqli_real_escape_string($conn, $contact_phone);
$url = mysqli_real_escape_string($conn, $panel_url);
$mno = (int)$member['mb_no'];

if ($save_action === 'update' && $idx > 0) {
    $row = db_select("select idx, member_no from tb_panel where idx = {$idx} limit 1 ");
    if (!$row || !isset($row['idx'])) {
        die('데이터를 찾을 수 없습니다.');
    }
    if ($member['mb_id'] !== 'admin' && (int)$row['member_no'] !== $mno) {
        die('수정 권한이 없습니다.');
    }

    $dup = db_select("select idx from tb_panel where member_no = {$row['member_no']} and panel_name_en = '{$en}' and idx != {$idx} limit 1 ");
    if ($dup && isset($dup['idx'])) {
        die('같은 영문 패널명이 이미 등록되어 있습니다.');
    }

    $sql = "update tb_panel set ";
    $sql .= "panel_name_ko = '{$ko}', ";
    $sql .= "panel_name_en = '{$en}', ";
    $sql .= "contact_phone = '{$phone}', ";
    $sql .= "panel_url = '{$url}', ";
    $sql .= "status = {$panel_status}, ";
    $sql .= "moddate = now() ";
    $sql .= "where idx = {$idx} limit 1";
} else {
    $dup = db_select("select idx from tb_panel where member_no = {$mno} and panel_name_en = '{$en}' limit 1 ");
    if ($dup && isset($dup['idx'])) {
        die('같은 영문 패널명이 이미 등록되어 있습니다.');
    }

    $sql = "insert into tb_panel set ";
    $sql .= "member_no = {$mno}, ";
    $sql .= "panel_name_ko = '{$ko}', ";
    $sql .= "panel_name_en = '{$en}', ";
    $sql .= "contact_phone = '{$phone}', ";
    $sql .= "panel_url = '{$url}', ";
    $sql .= "status = {$panel_status}, ";
    $sql .= "regdate = now(), ";
    $sql .= "enddate = DATE_ADD(now(), INTERVAL 31 DAY) ";
}

$result = db_query($sql);
if ($result) {
    echo '1';
} else {
    echo '저장에 실패했습니다. 테이블(tb_panel) 생성 여부를 확인해 주세요.';
}
