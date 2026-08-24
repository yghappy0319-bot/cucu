<?php
require_once __DIR__ . '/../../lib/_function.php';
require_once __DIR__ . '/../../lib/_upload.php';
require_once __DIR__ . '/../lib/_auction.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/auction/auction.php');
}

if (!auction_table_ok()) {
    alert_goto('경매 DB 테이블이 없습니다.', '/auction/auction.php');
}

$me = login_member();
if (!$me) {
    alert_goto('로그인이 필요합니다.', '/login.php');
}

$idx = (int) ($_POST['idx'] ?? 0);
if ($idx < 1) {
    alert_goto('잘못된 요청입니다.', '/auction/auction.php');
}

$return_edit = '/auction/auction_write.php?idx=' . $idx;
$penalty     = auction_delete_point_penalty();
$mb_idx      = (int) $me['mb_idx'];
$is_admin    = (int) $me['mb_level'] >= 9;

global $conn;

mysqli_begin_transaction($conn);

$rs  = db_query("
    SELECT au_idx, mb_idx, au_title, au_bid_count
    FROM tb_auction
    WHERE au_idx = {$idx} AND au_status = 1
    LIMIT 1
    FOR UPDATE
");
$row = db_assoc($rs);

if (!$row) {
    mysqli_rollback($conn);
    alert_goto('이미 삭제되었거나 존재하지 않는 경매입니다.', '/auction/auction.php');
}

$owner_mb_idx = (int) $row['mb_idx'];
if ($owner_mb_idx !== $mb_idx && !$is_admin) {
    mysqli_rollback($conn);
    alert_goto('삭제 권한이 없습니다.', $return_edit);
}

if ((int) ($row['au_bid_count'] ?? 0) > 0) {
    mysqli_rollback($conn);
    alert_goto('입찰이 시작된 경매는 삭제할 수 없습니다.', '/auction/auction_view.php?idx=' . $idx);
}

$charge_mb_idx = $owner_mb_idx;
if (db_table_exists('tb_point_log')) {
    $bal = (int) db_result("SELECT mb_point FROM tb_member WHERE mb_idx = {$charge_mb_idx} LIMIT 1");
    if ($bal < $penalty) {
        mysqli_rollback($conn);
        alert_goto(
            '포인트가 부족해 경매를 삭제할 수 없습니다. (보유 ' . number_format($bal) . 'P · 삭제 시 ' . number_format($penalty) . 'P 차감)',
            $return_edit
        );
    }

    $title_snip = mb_substr((string) $row['au_title'], 0, 40);
    $memo       = '경매 삭제 패널티 · ' . $title_snip . ' (#' . $idx . ')';
    $delta      = -$penalty;

    if (!db_query("
        UPDATE tb_member
        SET mb_point = mb_point + ({$delta})
        WHERE mb_idx = {$charge_mb_idx}
          AND mb_point + ({$delta}) >= 0
        LIMIT 1
    ") || mysqli_affected_rows($conn) !== 1) {
        mysqli_rollback($conn);
        alert_goto('포인트 차감에 실패했습니다.', $return_edit);
    }

    $new_bal  = (int) db_result("SELECT mb_point FROM tb_member WHERE mb_idx = {$charge_mb_idx} LIMIT 1");
    $esc_memo = db_escape($memo);
    if (!db_query("
        INSERT INTO tb_point_log (mb_idx, pl_change, pl_balance, pl_type, pl_memo)
        VALUES ({$charge_mb_idx}, {$delta}, {$new_bal}, 'auction_del', '{$esc_memo}')
    ")) {
        mysqli_rollback($conn);
        alert_goto('포인트 내역 기록에 실패했습니다.', $return_edit);
    }
}

if (!db_query("
    UPDATE tb_auction
    SET au_status = 9,
        au_auction_status = 9,
        au_image_count = 0,
        au_updated_at = NOW()
    WHERE au_idx = {$idx}
      AND au_status = 1
")) {
    mysqli_rollback($conn);
    alert_goto('경매 삭제 처리 중 오류가 발생했습니다.', $return_edit);
}

mysqli_commit($conn);

$paths = [];
if (auction_image_table_ok()) {
    $rs_img = db_query("SELECT ai_path FROM tb_auction_image WHERE au_idx = {$idx}");
    while ($im = db_assoc($rs_img)) {
        $paths[] = $im['ai_path'];
    }
    db_query("DELETE FROM tb_auction_image WHERE au_idx = {$idx}");
}
if (!empty($paths)) {
    auction_remove_files($paths);
}

$msg = '경매가 삭제되었습니다.';
if (db_table_exists('tb_point_log')) {
    $msg .= ' 등록자 계정에서 ' . number_format($penalty) . 'P가 차감되었습니다.';
}
alert_goto($msg, '/auction/auction.php');
