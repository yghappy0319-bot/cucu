<?php
require_once __DIR__ . '/../lib/_function.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/admin/members.php');
}

$ad = login_admin();
if (!$ad) {
    alert_goto('로그인이 필요합니다.', '/admin/login.php');
}

if (!db_table_exists('tb_member')) {
    alert_goto('회원 테이블이 없습니다.', '/admin/members.php');
}

$mb_idx = (int) ($_POST['mb_idx'] ?? 0);
if ($mb_idx < 1) {
    alert_goto('잘못된 요청입니다.', '/admin/members.php');
}

$mb_status = isset($_POST['mb_status']) ? (int) $_POST['mb_status'] : -1;
$mb_level  = isset($_POST['mb_level']) ? (int) $_POST['mb_level'] : -1;

if (!in_array($mb_status, [0, 1, 2, 3], true) || $mb_level < 1 || $mb_level > 9) {
    alert_goto('상태 또는 등급 값이 올바르지 않습니다.', '/admin/members.php');
}

if (!db_query("UPDATE tb_member SET mb_status = {$mb_status}, mb_level = {$mb_level}, mb_updated_at = NOW() WHERE mb_idx = {$mb_idx} LIMIT 1")) {
    alert_goto('저장 중 오류가 발생했습니다.', '/admin/members.php');
}

alert_goto('회원 정보를 저장했습니다.', '/admin/members.php');
