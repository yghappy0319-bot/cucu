<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../auction/lib/_member_auction_ban.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/admin/members.php');
}

$ad = login_admin();
if (!$ad) {
    alert_goto('로그인이 필요합니다.', '/admin/login.php');
}

$mb_idx = (int) ($_POST['mb_idx'] ?? 0);
$action = trim((string) ($_POST['action'] ?? ''));
$return = '/admin/member_edit.php?idx=' . $mb_idx;

if ($mb_idx < 1) {
    alert_goto('잘못된 요청입니다.', '/admin/members.php');
}

if (!db_table_exists('tb_member') || !db_assoc(db_query("SELECT mb_idx FROM tb_member WHERE mb_idx = {$mb_idx} LIMIT 1"))) {
    alert_goto('존재하지 않는 회원입니다.', '/admin/members.php');
}

if (!member_auction_ban_column_ready()) {
    alert_goto('경매 이용 제한 DB가 없습니다. sql/migrate_tb_member_auction_ban.sql 을 적용해 주세요.', $return);
}

if ($action === 'lift') {
    $result = member_auction_ban_lift($mb_idx);
    if (empty($result['ok'])) {
        alert_goto((string) ($result['error'] ?? '경매 이용 제한 해제에 실패했습니다.'), $return);
    }
    alert_goto('경매 이용 제한을 해제했습니다.', $return);
}

if ($action === 'apply') {
    $memo = trim((string) ($_POST['memo'] ?? ''));
    if ($memo === '') {
        $memo = '관리자 경매 이용 제한';
    }
    $result = member_auction_ban_apply($mb_idx, $memo);
    if (empty($result['ok'])) {
        alert_goto((string) ($result['error'] ?? '경매 이용 제한 처리에 실패했습니다.'), $return);
    }
    alert_goto('경매 이용을 제한했습니다.', $return);
}

alert_goto('잘못된 요청입니다.', $return);
