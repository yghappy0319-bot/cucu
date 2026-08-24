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
    die('무통장 연결 해지는 관리자만 할 수 있습니다.');
}

global $conn;

$idx = isset($_POST['idx']) ? (int)$_POST['idx'] : 0;
if ($idx <= 0) {
    die('패널을 찾을 수 없습니다.');
}

$panel = db_select("select idx, status from tb_panel where idx = {$idx} limit 1 ");
if (!$panel || !isset($panel['idx'])) {
    die('데이터를 찾을 수 없습니다.');
}
if ((int)$panel['status'] === 2) {
    die('삭제 완료된 패널은 수정할 수 없습니다.');
}

$linked_site = db_select("select idx from site where luckypanel = 1 and luckypanel_idx = {$idx} limit 1 ");
if (!$linked_site || !isset($linked_site['idx'])) {
    die('연결된 무통장 사이트가 없습니다.');
}

$site_idx = (int)$linked_site['idx'];
$result = db_query("update site set luckypanel_idx = 0 where idx = {$site_idx} limit 1 ");
if ($result) {
    echo '1';
} else {
    $err = mysqli_error($conn);
    echo '연결 해지에 실패했습니다. ' . ($err !== '' ? $err : 'site 테이블 luckypanel_idx 컬럼을 확인해 주세요.');
}
