<?php
require_once __DIR__ . '/../../lib/_function.php';
require_once __DIR__ . '/../../lib/_upload.php';
require_once __DIR__ . '/../../lib/_community_html.php';
require_once __DIR__ . '/../lib/_member_trade_sell_suspend.php';
require_once __DIR__ . '/../lib/_trade_write_restore.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/trade/trade.php');
}

if (trade_write_post_overflowed()) {
    trade_write_fail_back(trade_write_post_overflow_message(), false);
}

$me = login_member();
$ad = login_admin();
$from_admin = isset($_POST['from']) && (string) $_POST['from'] === 'admin';
$mode = $_POST['mode'] ?? 'insert';
$idx  = (int) ($_POST['idx'] ?? 0);

if (!$me && !($ad && $mode === 'edit' && $idx > 0)) {
    alert_goto('로그인이 필요합니다.', $ad ? '/admin/login.php' : '/login.php');
}
if ($from_admin && !$ad) {
    $from_admin = false;
}

$allowed_item   = ['card', 'box'];
$allowed_type   = ['sell', 'buy', 'exchange'];
$allowed_cond   = ['S', 'A', 'B', 'C'];
$allowed_method = ['direct', 'delivery', 'both'];
$allowed_grade  = ['', 'SAR', 'SR', 'RR', 'R', 'UR', '프로모', '일반'];
$allowed_card_kind = ['single', 'graded'];
$allowed_language = ['', 'ko', 'ja', 'en', 'other'];
$allowed_grading_company = ['', 'PSA', 'BGS', 'BRG', 'CGC', 'SGC', 'ACE', '기타'];
$allowed_grading_score = ['', '10', '9.5', '9', '8.5', '8', '7.5', '7', '6', '5', '4', '3', '2', '1'];

$tr_item_type = trim($_POST['tr_item_type'] ?? 'card');
$tr_type      = trim($_POST['tr_type']      ?? '');
$tr_title     = trim($_POST['tr_title']     ?? '');
$mb_idx       = $me ? (int) $me['mb_idx'] : 0;

$tr_set_name    = '';
$tr_card_number = '';
$tr_language    = '';
$tr_grading_company = '';
$tr_grading_score   = '';

if ($tr_item_type === 'box') {
    $tr_card_name = trim($_POST['tr_card_name_box'] ?? '');
    $tr_grade     = '';
    $tr_condition = 'S';
    $tr_box_qty   = max(1, (int)($_POST['tr_box_qty'] ?? 1));
    $tr_card_kind = '';
} else {
    $tr_item_type = 'card';
    $tr_card_name = trim($_POST['tr_card_name_card'] ?? '');
    $tr_grade     = trim($_POST['tr_grade']     ?? '');
    $tr_condition = trim($_POST['tr_condition'] ?? 'A');
    $tr_box_qty   = 1;
    $tr_card_kind = trim((string) ($_POST['tr_card_kind'] ?? ''));
    $tr_set_name    = trim($_POST['tr_set_name'] ?? '');
    $tr_card_number = trim($_POST['tr_card_number'] ?? '');
    $tr_language    = trim($_POST['tr_language'] ?? '');
    if (!in_array($tr_card_kind, $allowed_card_kind, true)) {
        $tr_card_kind = 'single';
    }
    if ($tr_card_kind === 'graded') {
        $tr_condition = 'S';
        $tr_grading_company = trim($_POST['tr_grading_company'] ?? '');
        $tr_grading_score   = trim($_POST['tr_grading_score'] ?? '');
        if ($tr_grading_company === '' || $tr_grading_score === '') {
            trade_write_fail_back('등급(슬랩) 카드는 감정 회사와 점수를 선택해 주세요.');
        }
    }
}

$tr_price   = (int)($_POST['tr_price']    ?? 0);
$tr_method  = trim($_POST['tr_method']    ?? 'both');
$tr_shipping_fee = (int)($_POST['tr_shipping_fee'] ?? 0);
$tr_region  = trim($_POST['tr_region']    ?? '');
$tr_content = (string)($_POST['tr_content'] ?? '');
$tr_content = community_sanitize_html($tr_content);

$image_order = isset($_POST['image_order']) && is_array($_POST['image_order']) ? $_POST['image_order'] : [];
$remove_ids  = isset($_POST['remove_img']) && is_array($_POST['remove_img'])
    ? array_filter(array_map('intval', $_POST['remove_img']))
    : [];

if (!in_array($tr_item_type, $allowed_item, true)) trade_write_fail_back('상품 종류가 올바르지 않습니다.');
if (!in_array($tr_type, $allowed_type, true))      trade_write_fail_back('거래 유형이 올바르지 않습니다.');
if ($mode !== 'edit' && $me && $tr_type === 'sell'
    && member_trade_sale_cancel_penalty_column_ready()
    && member_trade_sell_is_suspended($mb_idx)) {
    $status = member_trade_sale_cancel_penalty_status($mb_idx);
    alert_goto(
        member_trade_sell_suspend_user_message($status['suspended_until'] ?? null),
        '/trade/trade.php'
    );
}
if (!in_array($tr_condition, $allowed_cond, true)) trade_write_fail_back('상태가 올바르지 않습니다.');
if (!in_array($tr_method, $allowed_method, true))  trade_write_fail_back('거래 방식이 올바르지 않습니다.');
if ($tr_method === 'direct') {
    $tr_shipping_fee = 0;
}
if ($tr_shipping_fee < 0 || $tr_shipping_fee > 999999999) {
    trade_write_fail_back('택배비는 0원 이상 9억 이하로 입력해 주세요.');
}
if (!in_array($tr_grade, $allowed_grade, true))    trade_write_fail_back('레어도가 올바르지 않습니다.');
if (!in_array($tr_language, $allowed_language, true)) trade_write_fail_back('언어가 올바르지 않습니다.');
if (!in_array($tr_grading_company, $allowed_grading_company, true)) {
    trade_write_fail_back('감정 회사가 올바르지 않습니다.');
}
if (!in_array($tr_grading_score, $allowed_grading_score, true)) {
    trade_write_fail_back('슬랩 점수가 올바르지 않습니다.');
}
if ($tr_title === '' || mb_strlen($tr_title) > 150) trade_write_fail_back('제목을 1~150자 이내로 입력해 주세요.');
if ($tr_card_name === '' || mb_strlen($tr_card_name) > 100) {
    $label = $tr_item_type === 'box' ? '상자명/상품명' : '카드명';
    trade_write_fail_back($label . '을(를) 1~100자 이내로 입력해 주세요.');
}
if (mb_strlen($tr_set_name) > 80) trade_write_fail_back('세트명은 80자 이내로 입력해 주세요.');
if (mb_strlen($tr_card_number) > 20) trade_write_fail_back('카드번호는 20자 이내로 입력해 주세요.');
if ($tr_box_qty < 1 || $tr_box_qty > 9999)   trade_write_fail_back('수량은 1~9999 범위로 입력해 주세요.');
if ($tr_price < 0 || $tr_price > 999999999)  trade_write_fail_back('희망가는 0원 이상 9억 이하로 입력해 주세요.');
if (mb_strlen($tr_region) > 30)              trade_write_fail_back('거래 지역은 30자 이내로 입력해 주세요.');
if (community_editor_is_effectively_empty($tr_content)) {
    trade_write_fail_back('상세 설명을 입력해 주세요.');
}
if (mb_strlen($tr_content) > 200000) {
    trade_write_fail_back('상세 설명이 너무 깁니다. 이미지·서식을 줄여 주세요.');
}

// 시세 식별 컬럼 마이그레이션 여부
$market_identity_ready = (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_trade LIKE 'tr_set_name'"));
if (!$market_identity_ready) {
    trade_write_fail_back('시세용 상품 정보 컬럼이 아직 준비되지 않았습니다. 관리자에게 migrate_tb_trade_market_identity.sql 적용을 요청해 주세요.');
}
$shipping_fee_ready = (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_trade LIKE 'tr_shipping_fee'"));
if (!$shipping_fee_ready) {
    trade_write_fail_back('택배비 컬럼이 아직 준비되지 않았습니다. 관리자에게 migrate_tb_trade_shipping_fee.sql 적용을 요청해 주세요.');
}

// 새 이미지 업로드
$upload_result = trade_upload_images($_FILES['tr_images'] ?? null);
if (!$upload_result['ok']) {
    trade_write_fail_back($upload_result['error']);
}
$new_images = $upload_result['items']; // index 0..n
$rollback_new = function() use (&$new_images) {
    foreach ($new_images as $img) trade_remove_files([$img['path']]);
};

// 사진 최소 1장 필수 (등록·수정 공통)
if ($mode === 'edit' && $idx > 0) {
    $existing_count = (int) db_result("SELECT COUNT(*) FROM tb_trade_image WHERE tr_idx = {$idx}");
    $removing_count = 0;
    if (!empty($remove_ids)) {
        $ids_sql = implode(',', array_map('intval', $remove_ids));
        $removing_count = (int) db_result(
            "SELECT COUNT(*) FROM tb_trade_image WHERE tr_idx = {$idx} AND ti_idx IN ({$ids_sql})"
        );
    }
    $final_image_count = ($existing_count - $removing_count) + count($new_images);
} else {
    $final_image_count = count($new_images);
}
if ($final_image_count < 1) {
    $rollback_new();
    trade_write_fail_back('사진을 1장 이상 첨부해 주세요.');
}

$esc_item    = db_escape($tr_item_type);
$esc_card_kind = db_escape($tr_card_kind);
$esc_type    = db_escape($tr_type);
$esc_title   = db_escape($tr_title);
$esc_card    = db_escape($tr_card_name);
$esc_set     = db_escape($tr_set_name);
$esc_number  = db_escape($tr_card_number);
$esc_lang    = db_escape($tr_language);
$esc_grade   = db_escape($tr_grade);
$esc_cond    = db_escape($tr_condition);
$esc_gco     = db_escape($tr_grading_company);
$esc_gsc     = db_escape($tr_grading_score);
$esc_method  = db_escape($tr_method);
$esc_region  = db_escape($tr_region);
$esc_content = db_escape($tr_content);
$ip          = db_escape(get_client_ip());

$sql_set_name = ($tr_set_name === '' ? 'NULL' : "'{$esc_set}'");
$sql_card_number = ($tr_card_number === '' ? 'NULL' : "'{$esc_number}'");
$sql_language = ($tr_language === '' ? 'NULL' : "'{$esc_lang}'");
$sql_grading_company = ($tr_grading_company === '' ? 'NULL' : "'{$esc_gco}'");
$sql_grading_score = ($tr_grading_score === '' ? 'NULL' : "'{$esc_gsc}'");
$sql_card_kind = ($tr_card_kind === '' ? 'NULL' : "'{$esc_card_kind}'");
$sql_grade = ($tr_grade === '' ? 'NULL' : "'{$esc_grade}'");
$sql_region = ($tr_region === '' ? 'NULL' : "'{$esc_region}'");

/**
 * image_order = ['existing:123', 'new:0', 'existing:122', ...]
 * 최종 순서대로 ti_order 를 0..N 으로 재할당
 */
function apply_image_order($tr_idx, array $order, array $new_images, array $remove_ids) {
    // 삭제 대상 미리 빼두기
    $order = array_values(array_filter($order, function($ref) use ($remove_ids) {
        if (!is_string($ref)) return false;
        if (strpos($ref, 'existing:') === 0) {
            $id = (int)substr($ref, 9);
            return !in_array($id, $remove_ids, true);
        }
        if (strpos($ref, 'new:') === 0) return true;
        return false;
    }));

    $used_new = [];
    $ord = 0;
    foreach ($order as $ref) {
        if (strpos($ref, 'existing:') === 0) {
            $id = (int)substr($ref, 9);
            if ($id > 0) {
                db_query("UPDATE tb_trade_image SET ti_order = {$ord} WHERE tr_idx = {$tr_idx} AND ti_idx = {$id}");
                $ord++;
            }
        } elseif (strpos($ref, 'new:') === 0) {
            $ni = (int)substr($ref, 4);
            if (isset($new_images[$ni]) && !isset($used_new[$ni])) {
                $img = $new_images[$ni];
                $used_new[$ni] = true;
                $sql = "
                    INSERT INTO tb_trade_image
                        (tr_idx, ti_path, ti_orig_name, ti_size, ti_mime, ti_order, ti_created_at)
                    VALUES
                        ({$tr_idx},
                         '" . db_escape($img['path']) . "',
                         '" . db_escape($img['orig_name']) . "',
                         " . (int)$img['size'] . ",
                         '" . db_escape($img['mime']) . "',
                         {$ord},
                         NOW())
                ";
                if (db_query($sql)) {
                    $ord++;
                } else {
                    trade_remove_files([$img['path']]);
                }
            }
        }
    }

    // image_order 에 포함되지 않은 새 이미지는 뒤에 붙이기
    for ($i = 0; $i < count($new_images); $i++) {
        if (isset($used_new[$i])) continue;
        $img = $new_images[$i];
        $sql = "
            INSERT INTO tb_trade_image
                (tr_idx, ti_path, ti_orig_name, ti_size, ti_mime, ti_order, ti_created_at)
            VALUES
                ({$tr_idx},
                 '" . db_escape($img['path']) . "',
                 '" . db_escape($img['orig_name']) . "',
                 " . (int)$img['size'] . ",
                 '" . db_escape($img['mime']) . "',
                 {$ord},
                 NOW())
        ";
        if (db_query($sql)) {
            $ord++;
        } else {
            trade_remove_files([$img['path']]);
        }
    }
}

if ($mode === 'edit' && $idx > 0) {
    $status_sql = $ad ? '' : ' AND tr_status = 1';
    $rs  = db_query("SELECT mb_idx, tr_status FROM tb_trade WHERE tr_idx = {$idx}{$status_sql} LIMIT 1");
    $row = db_assoc($rs);
    if (!$row) {
        $rollback_new();
        alert_goto(
            '존재하지 않거나 삭제된 거래글입니다.',
            $from_admin ? '/trade/admin/trades.php' : '/trade/trade.php'
        );
    }
    $is_owner = $me && (int) $row['mb_idx'] === $mb_idx;
    $is_staff_member = $me && (int) $me['mb_level'] >= 9;
    if (!$is_owner && !$is_staff_member && !$ad) {
        $rollback_new();
        alert_goto('수정 권한이 없습니다.', '/trade/trade_view.php?idx=' . $idx);
    }

    $sql = "
        UPDATE tb_trade SET
            tr_item_type  = '{$esc_item}',
            tr_card_kind  = {$sql_card_kind},
            tr_type       = '{$esc_type}',
            tr_title      = '{$esc_title}',
            tr_card_name  = '{$esc_card}',
            tr_set_name   = {$sql_set_name},
            tr_card_number = {$sql_card_number},
            tr_language   = {$sql_language},
            tr_grade      = {$sql_grade},
            tr_condition  = '{$esc_cond}',
            tr_grading_company = {$sql_grading_company},
            tr_grading_score   = {$sql_grading_score},
            tr_box_qty    = {$tr_box_qty},
            tr_price      = {$tr_price},
            tr_method     = '{$esc_method}',
            tr_shipping_fee = {$tr_shipping_fee},
            tr_region     = {$sql_region},
            tr_content    = '{$esc_content}',
            tr_updated_at = NOW()
        WHERE tr_idx = {$idx}
    ";
    if (!$ad) {
        $sql .= ' AND tr_status = 1';
    }
    if (!db_query($sql)) {
        $rollback_new();
        trade_write_fail_back('거래글 수정 중 오류가 발생했습니다.');
    }

    // 기존 이미지 삭제 (요청된 것만)
    if (!empty($remove_ids)) {
        $ids_sql = implode(',', $remove_ids);
        $rs_del = db_query("SELECT ti_idx, ti_path FROM tb_trade_image WHERE tr_idx = {$idx} AND ti_idx IN ({$ids_sql})");
        $paths  = [];
        while ($dr = db_assoc($rs_del)) $paths[] = $dr['ti_path'];
        if (!empty($paths)) {
            db_query("DELETE FROM tb_trade_image WHERE tr_idx = {$idx} AND ti_idx IN ({$ids_sql})");
            trade_remove_files($paths);
        }
    }

    // 순서 반영 (신규 이미지 INSERT 포함)
    if (!empty($image_order)) {
        apply_image_order($idx, $image_order, $new_images, $remove_ids);
    } else {
        // 순서정보가 없으면 그냥 뒤에 append
        $next_order = (int) db_result("SELECT COALESCE(MAX(ti_order), -1) + 1 FROM tb_trade_image WHERE tr_idx = {$idx}");
        foreach ($new_images as $img) {
            $sqlIns = "
                INSERT INTO tb_trade_image
                    (tr_idx, ti_path, ti_orig_name, ti_size, ti_mime, ti_order, ti_created_at)
                VALUES
                    ({$idx},
                     '" . db_escape($img['path']) . "',
                     '" . db_escape($img['orig_name']) . "',
                     " . (int)$img['size'] . ",
                     '" . db_escape($img['mime']) . "',
                     " . (int)$next_order++ . ",
                     NOW())
            ";
            if (!db_query($sqlIns)) trade_remove_files([$img['path']]);
        }
    }

    $cnt = (int) db_result("SELECT COUNT(*) FROM tb_trade_image WHERE tr_idx = {$idx}");
    db_query("UPDATE tb_trade SET tr_image_count = {$cnt} WHERE tr_idx = {$idx}");

    unset($_SESSION['pz_trade_write_old'], $_SESSION['pz_trade_write_err']);
    if ($from_admin || ($ad && !$me)) {
        alert_goto('거래글이 수정되었습니다.', '/trade/admin/trades.php?wrote=1');
    }
    alert_goto('거래글이 수정되었습니다.', '/trade/trade_view.php?idx=' . $idx . '&wrote=1');
} else {
    if (!$me || $mb_idx < 1) {
        $rollback_new();
        alert_goto('로그인이 필요합니다.', '/login.php');
    }
    $sql = "
        INSERT INTO tb_trade
            (mb_idx, tr_item_type, tr_card_kind, tr_type, tr_title, tr_card_name,
             tr_set_name, tr_card_number, tr_language,
             tr_grade, tr_condition, tr_grading_company, tr_grading_score, tr_box_qty,
             tr_price, tr_method, tr_shipping_fee, tr_region, tr_content, tr_image_count, tr_ip, tr_created_at, tr_updated_at)
        VALUES
            ({$mb_idx}, '{$esc_item}', {$sql_card_kind}, '{$esc_type}', '{$esc_title}', '{$esc_card}',
             {$sql_set_name}, {$sql_card_number}, {$sql_language},
             {$sql_grade},
             '{$esc_cond}', {$sql_grading_company}, {$sql_grading_score}, {$tr_box_qty}, {$tr_price}, '{$esc_method}',
             {$tr_shipping_fee}, {$sql_region},
             '{$esc_content}', 0, '{$ip}', NOW(), NOW())
    ";
    if (!db_query($sql)) {
        $rollback_new();
        trade_write_fail_back('거래글 등록 중 오류가 발생했습니다.');
    }
    $new_idx = (int)db_insert_id();

    if (!empty($image_order)) {
        apply_image_order($new_idx, $image_order, $new_images, []);
    } else {
        $order = 0;
        foreach ($new_images as $img) {
            $sqlIns = "
                INSERT INTO tb_trade_image
                    (tr_idx, ti_path, ti_orig_name, ti_size, ti_mime, ti_order, ti_created_at)
                VALUES
                    ({$new_idx},
                     '" . db_escape($img['path']) . "',
                     '" . db_escape($img['orig_name']) . "',
                     " . (int)$img['size'] . ",
                     '" . db_escape($img['mime']) . "',
                     " . $order++ . ",
                     NOW())
            ";
            if (!db_query($sqlIns)) trade_remove_files([$img['path']]);
        }
    }

    $cnt = (int) db_result("SELECT COUNT(*) FROM tb_trade_image WHERE tr_idx = {$new_idx}");
    db_query("UPDATE tb_trade SET tr_image_count = {$cnt} WHERE tr_idx = {$new_idx}");

    unset($_SESSION['pz_trade_write_old'], $_SESSION['pz_trade_write_err']);
    point_reward_add($mb_idx, point_reward_trade_new_post(), 'trade_post', '거래글 등록 보상');
    alert_goto('거래글이 등록되었습니다.', '/trade/trade_view.php?idx=' . $new_idx . '&wrote=1');
}
