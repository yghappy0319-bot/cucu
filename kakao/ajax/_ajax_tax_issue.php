<?php
include_once('../common.php');
include_once('../lib/popbill_pay_log_receipt.php');

header('Content-Type: text/plain; charset=UTF-8');

if (!isset($_SESSION['midx']) || (int) $_SESSION['midx'] <= 0) {
    die('로그인이 필요합니다.');
}

$login_member = 로그인정보($_SESSION['midx']);
if (!isset($login_member['mb_id']) || $login_member['mb_id'] !== 'admin') {
    die('발행 권한이 없습니다.');
}

$id = isset($id) ? (int) $id : 0;
if ($id <= 0) {
    die('id null');
}

$row = db_select("select * from tb_pay_log where idx = {$id} limit 1 ");
if (!$row || !isset($row['idx'])) {
    die('발행 대상이 없습니다.');
}

if ((int) $row['receipt_type'] <= 0) {
    die('영수증 신청 건이 아닙니다.');
}

$receipt_status_val = (isset($row['receipt_status']) && $row['receipt_status'] !== '' && $row['receipt_status'] !== null)
    ? (int) $row['receipt_status'] : null;

if ($receipt_status_val === 1) {
    die('이미 발급 완료된 건 입니다.');
}
if ($receipt_status_val !== 0) {
    die('신청 완료된 건만 발행할 수 있습니다.');
}

try {
    $issue_result = pay_log_issue_receipt($row);
} catch (Exception $e) {
    die($e->getMessage());
}

global $conn;

$confirm_num = isset($issue_result['confirm_num']) ? trim((string) $issue_result['confirm_num']) : '';
$mgt_key = isset($issue_result['mgt_key']) ? trim((string) $issue_result['mgt_key']) : '';
$approval_number = $confirm_num !== '' ? $confirm_num : $mgt_key;
if ($approval_number === '') {
    die('발행은 완료되었으나 발행번호를 확인할 수 없습니다.');
}

$approval_esc = mysqli_real_escape_string($conn, $approval_number);
$sql = "update tb_pay_log set receipt_status = 1, approval_number = '{$approval_esc}' where idx = {$id} and receipt_status = 0 limit 1";

$result = db_query($sql);
if (!$result || mysqli_affected_rows($conn) !== 1) {
    die('발행은 완료되었으나 DB 상태 갱신에 실패했습니다.');
}

echo '1';
