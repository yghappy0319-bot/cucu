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
    die('무통장 연결은 관리자만 저장할 수 있습니다.');
}

global $conn;

$idx = isset($_POST['idx']) ? (int)$_POST['idx'] : 0;
$site_idx = isset($_POST['site_idx']) ? (int)$_POST['site_idx'] : 0;

if ($idx <= 0) {
    die('패널을 찾을 수 없습니다.');
}
if ($site_idx <= 0) {
    die('연결할 무통장 사이트를 선택해 주세요.');
}

$panel = db_select("select idx, member_no, status from tb_panel where idx = {$idx} limit 1 ");
if (!$panel || !isset($panel['idx'])) {
    die('데이터를 찾을 수 없습니다.');
}
if ((int)$panel['status'] === 2) {
    die('삭제 완료된 패널은 수정할 수 없습니다.');
}

$panel_mno = (int)$panel['member_no'];
$site = db_select("select idx, member_no, luckypanel, luckypanel_idx from site where idx = {$site_idx} limit 1 ");
if (!$site || !isset($site['idx'])) {
    die('선택한 사이트를 찾을 수 없습니다.');
}
if ((int)$site['luckypanel'] !== 1) {
    die('럭키패널 무통장 사이트만 연결할 수 있습니다.');
}
if ((int)$site['member_no'] !== $panel_mno) {
    die('패널 소유 회원의 사이트만 연결할 수 있습니다.');
}

$site_luckypanel_idx = isset($site['luckypanel_idx']) ? (int)$site['luckypanel_idx'] : 0;
if ($site_luckypanel_idx !== 0 && $site_luckypanel_idx !== $idx) {
    die('이미 다른 패널에 연결된 사이트입니다.');
}

$prev_site = db_select("select idx from site where luckypanel = 1 and luckypanel_idx = {$idx} and idx != {$site_idx} limit 1 ");
if ($prev_site && isset($prev_site['idx'])) {
    $prev_idx = (int)$prev_site['idx'];
    db_query("update site set luckypanel_idx = 0 where idx = {$prev_idx} limit 1 ");
}

$result = db_query("update site set luckypanel_idx = {$idx} where idx = {$site_idx} limit 1 ");
if ($result) {
    echo '1';
} else {
    $err = mysqli_error($conn);
    echo '저장에 실패했습니다. ' . ($err !== '' ? $err : 'site 테이블 luckypanel_idx 컬럼을 확인해 주세요.');
}
