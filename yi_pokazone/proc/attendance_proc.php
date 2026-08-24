<?php
require_once __DIR__ . '/../lib/_function.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/page/attendance.php');
}

$me = login_member();
if (!$me) {
    alert_goto('로그인이 필요합니다.', '/login.php?return=' . urlencode('/page/attendance.php'));
}

$mb_idx           = (int) $me['mb_idx'];
$attendance_point = attendance_reward_point();

$today = date('Y-m-d');

global $conn;

mysqli_begin_transaction($conn);

$esc_today = db_escape($today);
$sql_ins   = "
    INSERT INTO tb_attendance (mb_idx, att_ymd, att_point)
    VALUES ({$mb_idx}, '{$esc_today}', " . (int) $attendance_point . ")
";
if (!db_query($sql_ins)) {
    mysqli_rollback($conn);
    if (mysqli_errno($conn) === 1062) {
        alert_goto('오늘은 이미 출석했습니다.', '/page/attendance.php');
    }
    alert_goto('출석 처리 중 오류가 발생했습니다. 잠시 후 다시 시도해 주세요.', '/page/attendance.php');
}

$sql_up = "UPDATE tb_member SET mb_point = mb_point + " . (int) $attendance_point . " WHERE mb_idx = {$mb_idx} LIMIT 1";
if (!db_query($sql_up) || mysqli_affected_rows($conn) !== 1) {
    mysqli_rollback($conn);
    alert_goto('포인트 지급 중 오류가 발생했습니다.', '/page/attendance.php');
}

$new_bal  = (int) db_result("SELECT mb_point FROM tb_member WHERE mb_idx = {$mb_idx} LIMIT 1");
$esc_memo = db_escape('일일 출석 보상');
$esc_type = db_escape('attendance');
$sql_log  = "
    INSERT INTO tb_point_log (mb_idx, pl_change, pl_balance, pl_type, pl_memo)
    VALUES ({$mb_idx}, " . (int) $attendance_point . ", {$new_bal}, '{$esc_type}', '{$esc_memo}')
";
if (!db_query($sql_log)) {
    mysqli_rollback($conn);
    alert_goto('포인트 내역 저장 중 오류가 발생했습니다.', '/page/attendance.php');
}

mysqli_commit($conn);

alert_goto('출석 완료! ' . number_format($attendance_point) . 'P가 지급되었습니다.', '/page/attendance.php');
