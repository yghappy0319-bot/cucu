<?php


require_once __DIR__ . '/../../lib/_function.php';
require_once __DIR__ . '/../../lib/_upload.php';
require_once __DIR__ . '/../../lib/_community_html.php';
require_once __DIR__ . '/../lib/_auction.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/auction/auction.php');
}

if (!auction_table_ok()) {
    alert_goto('경매 DB 테이블이 없습니다. sql/tb_auction.sql 을 실행해 주세요.', '/auction/auction.php');
}

$me = login_member();
if (!$me) {
    alert_goto('로그인이 필요합니다.', '/login.php');
}

require_once __DIR__ . '/../lib/_member_auction_ban.php';
require_once __DIR__ . '/../lib/_member_auction_eligibility.php';
if (member_auction_ban_column_ready() && member_auction_is_banned((int) $me['mb_idx'])) {
    alert_goto(member_auction_ban_user_message(), '/page/mypage.php');
}

$mode = $_POST['mode'] ?? 'insert';
if ($mode !== 'edit') {
    $elig = member_auction_eligibility_create($me);
    if (!$elig['ok']) {
        alert_goto(member_auction_eligibility_user_message($elig), '/auction/auction_write.php');
    }
}

$allowed_item      = ['card', 'box'];
$allowed_cond      = ['S', 'A', 'B', 'C'];
$allowed_grade     = ['', 'SAR', 'SR', 'RR', 'R', 'UR', '프로모', '일반'];
$allowed_card_kind = ['single', 'graded'];
$allowed_duration  = [1, 3, 5, 7, 14];

$idx          = (int) ($_POST['idx'] ?? 0);
$au_item_type = trim((string) ($_POST['au_item_type'] ?? 'card'));
$au_title     = trim((string) ($_POST['au_title'] ?? ''));

if ($au_item_type === 'box') {
    $au_card_name = trim((string) ($_POST['au_card_name_box'] ?? ''));
    $au_grade     = '';
    $au_condition = 'S';
    $au_box_qty   = max(1, (int) ($_POST['au_box_qty'] ?? 1));
    $au_card_kind = '';
} else {
    $au_item_type = 'card';
    $au_card_name = trim((string) ($_POST['au_card_name_card'] ?? ''));
    $au_grade     = trim((string) ($_POST['au_grade'] ?? ''));
    $au_condition = trim((string) ($_POST['au_condition'] ?? 'A'));
    $au_box_qty   = 1;
    $au_card_kind = trim((string) ($_POST['au_card_kind'] ?? ''));
    if (!in_array($au_card_kind, $allowed_card_kind, true)) {
        alert_goto('카드 형태(싱글/등급)를 선택해 주세요.');
    }
}

$au_start_price   = (int) ($_POST['au_start_price'] ?? 0);
$au_buy_now_price = trim((string) ($_POST['au_buy_now_price'] ?? ''));
$au_buy_now_price = $au_buy_now_price === '' ? 0 : (int) $au_buy_now_price;
$au_bid_step      = (int) ($_POST['au_bid_step'] ?? 1000);
$au_method        = 'delivery';
$au_region        = trim((string) ($_POST['au_region'] ?? ''));
$au_content       = community_sanitize_html((string) ($_POST['au_content'] ?? ''));
$au_duration      = (int) ($_POST['au_duration'] ?? 3);

$image_order = isset($_POST['image_order']) && is_array($_POST['image_order']) ? $_POST['image_order'] : [];
$remove_ids  = isset($_POST['remove_img']) && is_array($_POST['remove_img'])
    ? array_filter(array_map('intval', $_POST['remove_img']))
    : [];

if (!in_array($au_item_type, $allowed_item, true)) {
    alert_goto('상품 종류가 올바르지 않습니다.');
}
if (!in_array($au_condition, $allowed_cond, true)) {
    alert_goto('상태가 올바르지 않습니다.');
}
if (!in_array($au_grade, $allowed_grade, true)) {
    alert_goto('등급이 올바르지 않습니다.');
}
if ($mode !== 'edit' && !in_array($au_duration, $allowed_duration, true)) {
    alert_goto('경매 기간이 올바르지 않습니다.');
}
if ($au_title === '' || mb_strlen($au_title) > 150) {
    alert_goto('제목을 1~150자 이내로 입력해 주세요.');
}
if ($au_card_name === '' || mb_strlen($au_card_name) > 100) {
    $label = $au_item_type === 'box' ? '상자명/상품명' : '카드명';
    alert_goto($label . '을(를) 1~100자 이내로 입력해 주세요.');
}
if ($au_box_qty < 1 || $au_box_qty > 9999) {
    alert_goto('수량은 1~9999 범위로 입력해 주세요.');
}
if ($au_start_price < 1000 || $au_start_price > 999999999) {
    alert_goto('시작가는 1,000원 이상 9억 이하로 입력해 주세요.');
}
if ($au_bid_step < 100 || $au_bid_step > 10000000) {
    alert_goto('입찰 단위는 100원 이상 1천만 원 이하로 입력해 주세요.');
}
if ($au_buy_now_price > 0) {
    if ($au_buy_now_price <= $au_start_price) {
        alert_goto('즉시구매가는 시작가보다 커야 합니다.');
    }
    if ($au_buy_now_price > 999999999) {
        alert_goto('즉시구매가는 9억 이하로 입력해 주세요.');
    }
}
if (mb_strlen($au_region) > 30) {
    alert_goto('거래 지역은 30자 이내로 입력해 주세요.');
}
if (community_editor_is_effectively_empty($au_content)) {
    alert_goto('내용을 입력해주세요.');
}
if (mb_strlen($au_content) > 200000) {
    alert_goto('상세 설명이 너무 깁니다. 이미지·서식을 줄여 주세요.');
}

$upload_result = auction_upload_images($_FILES['au_images'] ?? null);
if (!$upload_result['ok']) {
    alert_goto($upload_result['error']);
}
$new_images = $upload_result['items'];
$rollback_new = static function () use (&$new_images): void {
    foreach ($new_images as $img) {
        auction_remove_files([$img['path']]);
    }
};

$esc_item     = db_escape($au_item_type);
$esc_card_kind = db_escape($au_card_kind);
$esc_title    = db_escape($au_title);
$esc_card     = db_escape($au_card_name);
$esc_grade    = db_escape($au_grade);
$esc_cond     = db_escape($au_condition);
$esc_method   = db_escape($au_method);
$esc_region   = db_escape($au_region);
$esc_content  = db_escape($au_content);
$ip           = db_escape(get_client_ip());
$mb_idx       = (int) $me['mb_idx'];

$starts_at = date('Y-m-d H:i:s');
$ends_at   = date('Y-m-d H:i:s', strtotime('+' . $au_duration . ' days'));
$esc_starts = db_escape($starts_at);
$esc_ends   = db_escape($ends_at);
$is_edit_mode = ($mode === 'edit' && $idx > 0);

$buy_now_sql = $au_buy_now_price > 0 ? (string) (int) $au_buy_now_price : 'NULL';

$auction_sql_fail = static function (string $user_msg) use ($rollback_new): void {
    $rollback_new();
    $db_err = function_exists('db_last_error') ? db_last_error() : '';
    if ($db_err !== '') {
        $user_msg .= "\n\n(DB: " . $db_err . ')';
    }
    alert_goto($user_msg);
};

if ($is_edit_mode) {
    $rs  = db_query("SELECT mb_idx, au_bid_count FROM tb_auction WHERE au_idx = {$idx} AND au_status = 1 LIMIT 1");
    $row = db_assoc($rs);
    if (!$row) {
        $rollback_new();
        alert_goto('존재하지 않거나 삭제된 경매입니다.', '/auction/auction.php');
    }
    if ((int) $row['mb_idx'] !== $mb_idx && (int) $me['mb_level'] < 9) {
        $rollback_new();
        alert_goto('수정 권한이 없습니다.', '/auction/auction_view.php?idx=' . $idx);
    }
    if ((int) ($row['au_bid_count'] ?? 0) > 0) {
        $rollback_new();
        alert_goto('입찰이 시작된 경매는 수정할 수 없습니다.', '/auction/auction_view.php?idx=' . $idx);
    }

    $sql = "
        UPDATE tb_auction SET
            au_item_type     = '{$esc_item}',
            au_card_kind     = " . ($au_card_kind === '' ? 'NULL' : "'{$esc_card_kind}'") . ",
            au_title         = '{$esc_title}',
            au_card_name     = '{$esc_card}',
            au_grade         = " . ($au_grade === '' ? 'NULL' : "'{$esc_grade}'") . ",
            au_condition     = '{$esc_cond}',
            au_box_qty       = {$au_box_qty},
            au_start_price   = {$au_start_price},
            au_buy_now_price = {$buy_now_sql},
            au_bid_step      = {$au_bid_step},
            au_current_price = {$au_start_price},
            au_method        = '{$esc_method}',
            au_region        = " . ($au_region === '' ? 'NULL' : "'{$esc_region}'") . ",
            au_content       = '{$esc_content}',
            au_updated_at    = NOW()
        WHERE au_idx = {$idx}
          AND au_status = 1
    ";
    if (!db_query($sql)) {
        $auction_sql_fail('경매 수정 중 오류가 발생했습니다.');
    }

    if (auction_image_table_ok() && !empty($remove_ids)) {
        $ids_sql = implode(',', $remove_ids);
        $rs_del  = db_query("SELECT ai_idx, ai_path FROM tb_auction_image WHERE au_idx = {$idx} AND ai_idx IN ({$ids_sql})");
        $paths   = [];
        while ($dr = db_assoc($rs_del)) {
            $paths[] = $dr['ai_path'];
        }
        if (!empty($paths)) {
            db_query("DELETE FROM tb_auction_image WHERE au_idx = {$idx} AND ai_idx IN ({$ids_sql})");
            auction_remove_files($paths);
        }
    }

    if (auction_image_table_ok()) {
        if (!empty($image_order)) {
            auction_apply_image_order($idx, $image_order, $new_images, $remove_ids);
        } else {
            $next_order = (int) db_result("SELECT COALESCE(MAX(ai_order), -1) + 1 FROM tb_auction_image WHERE au_idx = {$idx}");
            foreach ($new_images as $img) {
                $sqlIns = "
                    INSERT INTO tb_auction_image
                        (au_idx, ai_path, ai_orig_name, ai_size, ai_mime, ai_order, ai_created_at)
                    VALUES
                        ({$idx},
                         '" . db_escape($img['path']) . "',
                         '" . db_escape($img['orig_name']) . "',
                         " . (int) $img['size'] . ",
                         '" . db_escape($img['mime']) . "',
                         " . (int) $next_order++ . ",
                         NOW())
                ";
                if (!db_query($sqlIns)) {
                    auction_remove_files([$img['path']]);
                }
            }
        }
        auction_sync_image_count($idx);
    }

    alert_goto('경매가 수정되었습니다.', '/auction/auction_view.php?idx=' . $idx);
}

$sql = "
    INSERT INTO tb_auction
        (mb_idx, au_item_type, au_card_kind, au_title, au_card_name, au_grade, au_condition, au_box_qty,
         au_start_price, au_buy_now_price, au_bid_step, au_current_price, au_method, au_region, au_content,
         au_image_count, au_auction_status, au_starts_at, au_ends_at, au_ip, au_created_at, au_updated_at)
    VALUES
        ({$mb_idx}, '{$esc_item}', " . ($au_card_kind === '' ? 'NULL' : "'{$esc_card_kind}'") . ",
         '{$esc_title}', '{$esc_card}',
         " . ($au_grade === '' ? 'NULL' : "'{$esc_grade}'") . ",
         '{$esc_cond}', {$au_box_qty},
         {$au_start_price}, {$buy_now_sql}, {$au_bid_step}, {$au_start_price},
         '{$esc_method}',
         " . ($au_region === '' ? 'NULL' : "'{$esc_region}'") . ",
         '{$esc_content}', 0, 1, '{$esc_starts}', '{$esc_ends}', '{$ip}', NOW(), NOW())
";
if (!db_query($sql)) {
    $auction_sql_fail('경매 등록 중 오류가 발생했습니다.');
}
$new_idx = (int) db_insert_id();
if ($new_idx < 1) {
    $auction_sql_fail('경매 등록 후 글 번호를 확인하지 못했습니다.');
}

if (auction_image_table_ok()) {
    if (!empty($image_order)) {
        auction_apply_image_order($new_idx, $image_order, $new_images, []);
    } else {
        $order = 0;
        foreach ($new_images as $img) {
            $sqlIns = "
                INSERT INTO tb_auction_image
                    (au_idx, ai_path, ai_orig_name, ai_size, ai_mime, ai_order, ai_created_at)
                VALUES
                    ({$new_idx},
                     '" . db_escape($img['path']) . "',
                     '" . db_escape($img['orig_name']) . "',
                     " . (int) $img['size'] . ",
                     '" . db_escape($img['mime']) . "',
                     " . $order++ . ",
                     NOW())
            ";
            if (!db_query($sqlIns)) {
                auction_remove_files([$img['path']]);
            }
        }
    }
    auction_sync_image_count($new_idx);
}

alert_goto('경매가 등록되었습니다.', '/auction/auction_view.php?idx=' . $new_idx);
