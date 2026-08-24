<?php
include_once __DIR__ . '/../../../common.php';

header('Content-Type: text/plain; charset=UTF-8');

if (!isset($_SESSION['midx']) || (int) $_SESSION['midx'] <= 0) {
    die('로그인이 필요합니다.');
}

$member = 로그인정보($_SESSION['midx']);
if (!isset($member['mb_no'])) {
    die('회원 정보를 확인할 수 없습니다.');
}

$panel_name_en = isset($_POST['panel_name_en']) ? trim((string) $_POST['panel_name_en']) : '';

if (!preg_match('/^[A-Za-z]{6,}$/', $panel_name_en)) {
    die('패널명(영문)은 영문 대·소문자만 6자 이상 입력한 뒤 조회해 주세요.');
}

global $conn;

$en = mysqli_real_escape_string($conn, $panel_name_en);
$mno = (int) $member['mb_no'];

$dup = db_select("select idx from tb_panel where member_no = {$mno} and panel_name_en = '{$en}' limit 1 ");
if ($dup && isset($dup['idx'])) {
    die('내 계정에 이미 같은 영문 패널명이 등록되어 있습니다.');
}

echo '1';
