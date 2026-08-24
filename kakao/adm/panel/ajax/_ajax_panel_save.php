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

$digits = preg_replace('/\D+/', '', $contact_phone);
if ($digits === '' || strlen($digits) < 9 || strlen($digits) > 15) {
    die('담당자 연락처를 올바르게 입력해 주세요.');
}

$ko = mysqli_real_escape_string($conn, $panel_name_ko);
$phone = mysqli_real_escape_string($conn, $contact_phone);
$mno = (int)$member['mb_no'];
$enddate_sql = '';

if ($save_action === 'update' && $idx > 0) {
    $row = db_select("select idx, member_no, status from tb_panel where idx = {$idx} limit 1 ");
    if (!$row || !isset($row['idx'])) {
        die('데이터를 찾을 수 없습니다.');
    }
    if ((int)$row['status'] === 2) {
        die('삭제 완료된 패널은 수정할 수 없습니다.');
    }
    if ($member['mb_id'] !== 'admin' && (int)$row['member_no'] !== $mno) {
        die('수정 권한이 없습니다.');
    }

    // 패널명(영문)은 최초 등록 후 변경 불가 — POST 값 무시

    if ($member['mb_id'] === 'admin') {
        $enddate_post = isset($_POST['enddate']) ? trim($_POST['enddate']) : '';
        if ($enddate_post === '') {
            die('이용 종료일을 입력해 주세요.');
        }
        $enddate_norm = str_replace('T', ' ', $enddate_post);
        $end_ts = strtotime($enddate_norm);
        if ($end_ts === false) {
            die('이용 종료일 형식이 올바르지 않습니다.');
        }
        $enddate_sql = date('Y-m-d H:i:s', $end_ts);
    }

    $sql = "update tb_panel set ";
    $sql .= "panel_name_ko = '{$ko}', ";
    $sql .= "contact_phone = '{$phone}', ";
    $sql .= "status = {$panel_status}, ";
    if ($enddate_sql !== '') {
        $enddate_esc = mysqli_real_escape_string($conn, $enddate_sql);
        $sql .= "enddate = '{$enddate_esc}', ";
    }
    $sql .= "moddate = now() ";
    $sql .= "where idx = {$idx} limit 1";
} else {
    if (!preg_match('/^[A-Za-z]{6,}$/', $panel_name_en)) {
        die('패널명(영문)은 영문 대·소문자만 6자 이상 입력해 주세요.');
    }
    $en = mysqli_real_escape_string($conn, $panel_name_en);

    $dup = db_select("select idx from tb_panel where member_no = {$mno} and panel_name_en = '{$en}' limit 1 ");
    if ($dup && isset($dup['idx'])) {
        die('같은 영문 패널명이 이미 등록되어 있습니다.');
    }

    $sql = "insert into tb_panel set ";
    $sql .= "member_no = {$mno}, ";
    $sql .= "panel_name_ko = '{$ko}', ";
    $sql .= "panel_name_en = '{$en}', ";
    $sql .= "contact_phone = '{$phone}', ";
    $sql .= "panel_url = '', ";
    $sql .= "status = {$panel_status}, ";
    $sql .= "regdate = now(), ";
    $sql .= "enddate = DATE_ADD(now(), INTERVAL 31 DAY) ";
}

$result = db_query($sql);
if ($result) {
    if ($save_action === 'update' && $idx > 0 && $enddate_sql !== '') {
        $enddate_esc = mysqli_real_escape_string($conn, $enddate_sql);
        db_query("update site set luckypanel_bankdata = '{$enddate_esc}' where luckypanel = 1 and luckypanel_idx = {$idx}");
    }
    echo '1';
} else {
    $err = mysqli_error($conn);
    echo '저장에 실패했습니다. ' . ($err !== '' ? $err : '테이블(tb_panel) 및 컬럼(status, enddate 등)을 확인해 주세요.');
}
