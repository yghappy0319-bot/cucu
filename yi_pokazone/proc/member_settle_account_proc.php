<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/_member_settle.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/page/member_info.php');
}

$me = login_member();
if (!$me) {
    alert_goto('로그인이 필요합니다.', '/login.php?return=' . urlencode('/page/member_info.php'));
}

$mb_idx = (int) $me['mb_idx'];

$rs = db_query("SELECT mb_idx, mb_status FROM tb_member WHERE mb_idx = {$mb_idx} LIMIT 1");
$row = db_assoc($rs);
if (!$row) {
    alert_goto('회원 정보를 찾을 수 없습니다.', '/logout.php');
}
if ((int) $row['mb_status'] !== 1) {
    alert_goto('로그인할 수 없는 계정입니다. 고객센터로 문의해 주세요.', '/logout.php');
}

$result = member_settle_account_save($mb_idx, $_POST);
if (!$result['ok']) {
    alert_goto($result['error'] ?? '정산계좌 저장에 실패했습니다.', '/page/member_info.php');
}

alert_goto('정산계좌가 저장되었습니다.', '/page/member_info.php');
