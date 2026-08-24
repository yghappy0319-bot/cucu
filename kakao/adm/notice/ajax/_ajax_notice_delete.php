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
    die('삭제 권한이 없습니다.');
}

$idx = isset($_POST['idx']) ? (int)$_POST['idx'] : 0;
if ($idx <= 0) {
    die('잘못된 요청입니다.');
}

$row = db_select("select idx from tb_notice where idx = {$idx} limit 1 ");
if (!$row || !isset($row['idx'])) {
    die('데이터를 찾을 수 없습니다.');
}

$result = db_query("delete from tb_notice where idx = {$idx} limit 1 ");
if ($result) {
    echo '1';
} else {
    global $conn;
    $err = mysqli_error($conn);
    echo '삭제에 실패했습니다.' . ($err !== '' ? ' ' . $err : '');
}
