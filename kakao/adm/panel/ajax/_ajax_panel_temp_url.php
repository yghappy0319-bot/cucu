<?php
include_once __DIR__ . '/../../../common.php';

header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['midx']) || (int)$_SESSION['midx'] <= 0) {
    echo json_encode(array('url' => '', 'msg' => '로그인이 필요합니다.'));
    exit;
}

$member = 로그인정보($_SESSION['midx']);
if (!isset($member['mb_no'])) {
    echo json_encode(array('url' => '', 'msg' => '회원 정보를 확인할 수 없습니다.'));
    exit;
}

$idx = isset($_REQUEST['idx']) ? (int)$_REQUEST['idx'] : 0;
if ($idx <= 0) {
    echo json_encode(array('url' => ''));
    exit;
}

$row = db_select("select idx, member_no, COALESCE(test_panel_url, '') as test_panel_url from tb_panel where idx = {$idx} limit 1 ");
if (!$row || !isset($row['idx'])) {
    echo json_encode(array('url' => '', 'msg' => '데이터를 찾을 수 없습니다.'));
    exit;
}

$is_admin = ($member['mb_id'] === 'admin');
if (!$is_admin && (int)$row['member_no'] !== (int)$member['mb_no']) {
    echo json_encode(array('url' => '', 'msg' => '권한이 없습니다.'));
    exit;
}

$url = isset($row['test_panel_url']) ? trim($row['test_panel_url']) : '';
echo json_encode(array('url' => $url));
