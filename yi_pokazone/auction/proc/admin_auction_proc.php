<?php
require_once __DIR__ . '/../../lib/_function.php';
require_once __DIR__ . '/../../lib/_upload.php';
require_once __DIR__ . '/../lib/_auction.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/auction/admin/auction_listings.php');
}

$ad = login_admin();
if (!$ad) {
    alert_goto('로그인이 필요합니다.', '/admin/login.php');
}

if (!auction_table_ok()) {
    alert_goto('경매 테이블이 없습니다.', '/auction/admin/auction_listings.php');
}

$idx    = (int) ($_POST['au_idx'] ?? 0);
$action = trim((string) ($_POST['action'] ?? ''));
if ($idx < 1) {
    alert_goto('잘못된 요청입니다.', '/auction/admin/auction_listings.php');
}

$return = '/auction/admin/auction_listings.php';

/**
 * @return list<string>
 */
$collect_auction_image_paths = static function (int $au_idx): array {
    if (!auction_image_table_ok()) {
        return [];
    }
    $paths = [];
    $rs    = db_query("SELECT ai_path FROM tb_auction_image WHERE au_idx = {$au_idx}");
    while ($r = db_assoc($rs)) {
        $paths[] = $r['ai_path'];
    }

    return $paths;
};

$remove_auction_images = static function (int $au_idx) use ($collect_auction_image_paths): void {
    $paths = $collect_auction_image_paths($au_idx);
    if (auction_image_table_ok()) {
        db_query("DELETE FROM tb_auction_image WHERE au_idx = {$au_idx}");
    }
    if (!empty($paths)) {
        auction_remove_files($paths);
    }
};

if (in_array($action, ['hide', 'show'], true)) {
    $rs  = db_query("
        SELECT au_idx, au_status, au_starts_at, au_ends_at, au_winner_mb_idx, au_auction_status
        FROM tb_auction
        WHERE au_idx = {$idx}
        LIMIT 1
    ");
    $row = db_assoc($rs);
    if (!$row) {
        alert_goto('존재하지 않는 경매입니다.', $return);
    }

    if ($action === 'hide') {
        if ((int) ($row['au_status'] ?? 0) === 9) {
            alert_goto('이미 삭제(숨김) 처리된 경매입니다.', $return);
        }
        $remove_auction_images($idx);
        if (!db_query("
            UPDATE tb_auction
            SET au_status = 9,
                au_auction_status = 9,
                au_image_count = 0,
                au_updated_at = NOW()
            WHERE au_idx = {$idx}
            LIMIT 1
        ")) {
            alert_goto('숨김 처리 중 오류가 발생했습니다.', $return);
        }
        alert_goto('경매를 삭제(숨김) 처리했습니다. 입찰·주문 기록은 유지됩니다.', $return);
    }

    if ((int) ($row['au_status'] ?? 0) !== 9) {
        alert_goto('노출 중인 경매입니다.', $return);
    }

    $ast = (int) ($row['au_auction_status'] ?? 9);
    if ($ast === 9) {
        $now    = time();
        $starts = strtotime((string) ($row['au_starts_at'] ?? ''));
        $ends   = strtotime((string) ($row['au_ends_at'] ?? ''));
        if ($starts !== false && $now < $starts) {
            $ast = 0;
        } elseif ($ends !== false && $now < $ends) {
            $ast = 1;
        } elseif ((int) ($row['au_winner_mb_idx'] ?? 0) > 0) {
            $ast = 3;
        } else {
            $ast = 2;
        }
    }

    if (!db_query("
        UPDATE tb_auction
        SET au_status = 1,
            au_auction_status = {$ast},
            au_updated_at = NOW()
        WHERE au_idx = {$idx}
        LIMIT 1
    ")) {
        alert_goto('복구 처리 중 오류가 발생했습니다.', $return);
    }
    alert_goto('경매를 다시 노출했습니다. (첨부 이미지는 복구되지 않습니다)', $return);
}

if ($action === 'delete') {
    $rs  = db_query("SELECT au_idx FROM tb_auction WHERE au_idx = {$idx} LIMIT 1");
    $row = db_assoc($rs);
    if (!$row) {
        alert_goto('존재하지 않는 경매입니다.', $return);
    }

    if (db_table_exists('tb_auction_order')) {
        $has_order = (int) db_result("SELECT COUNT(*) FROM tb_auction_order WHERE au_idx = {$idx}");
        if ($has_order > 0) {
            alert_goto('낙찰 주문이 있는 경매는 DB에서 완전 삭제할 수 없습니다. 삭제(숨김) 처리를 이용해 주세요.', $return);
        }
    }

    $paths = $collect_auction_image_paths($idx);
    if (!empty($paths)) {
        auction_remove_files($paths);
    }

    if (!db_query("DELETE FROM tb_auction WHERE au_idx = {$idx} LIMIT 1")) {
        alert_goto('삭제 처리 중 오류가 발생했습니다.', $return);
    }
    alert_goto('경매를 DB에서 완전히 삭제했습니다. 입찰 기록도 함께 제거됩니다.', $return);
}

alert_goto('잘못된 요청입니다.', $return);
