<?php
/**
 * 거래글 1:1 채팅 API (JSON)
 */
define('PZ_API_JSON', true);

register_shutdown_function(static function (): void {
    $err = error_get_last();
    if (!$err || !in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }
    if (headers_sent()) {
        return;
    }
    header('Content-Type: application/json; charset=UTF-8');
    http_response_code(200);
    $msg = '채팅 서버 오류가 발생했습니다. 잠시 후 다시 시도해 주세요.';
    if (!empty($GLOBALS['db_connection_debug'])) {
        $msg .= ' [' . $err['message'] . ' @ ' . basename((string) ($err['file'] ?? '')) . ':' . (int) ($err['line'] ?? 0) . ']';
    }
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
});

require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/_upload.php';

header('Content-Type: application/json; charset=UTF-8');

const TRADE_CHAT_BODY_MAX = 2000;

function trade_chat_json_exit(array $payload): void
{
    $flags = JSON_UNESCAPED_UNICODE;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    echo json_encode($payload, $flags);
    exit;
}

/** 결제 모듈 inspect 없이 취소 API용 로드 */
function trade_chat_payment_cancel_load(): bool
{
    if (function_exists('trade_payment_cancel_request')) {
        return true;
    }
    $path = __DIR__ . '/lib/_trade_payment.php';
    if (!is_readable($path)) {
        return false;
    }
    try {
        require_once $path;
    } catch (Throwable $e) {
        error_log('trade_chat_payment_cancel_load: ' . $e->getMessage());

        return false;
    }

    return function_exists('trade_payment_cancel_request');
}

/**
 * @return array{ok: bool, error?: string, request_msg_idx?: int}
 */
function trade_chat_cancel_payment_fallback(int $seller_mb_idx, int $room_idx, int $msg_idx, int $pay_idx = 0): array
{
    if ($seller_mb_idx < 1 || $room_idx < 1 || $msg_idx < 1) {
        return ['ok' => false, 'error' => '취소할 결제 요청을 찾을 수 없습니다.'];
    }

    $msg_rw = db_assoc(db_query("
        SELECT msg_idx, mb_idx, msg_body
        FROM tb_trade_room_msg
        WHERE msg_idx = {$msg_idx} AND room_idx = {$room_idx}
        LIMIT 1
    "));
    if (!$msg_rw) {
        return ['ok' => false, 'error' => '결제 요청 메시지를 찾을 수 없습니다.'];
    }
    if ((int) ($msg_rw['mb_idx'] ?? 0) !== $seller_mb_idx) {
        return ['ok' => false, 'error' => '판매자만 결제 요청을 취소할 수 있습니다.'];
    }

    $body = (string) ($msg_rw['msg_body'] ?? '');
    if (preg_match('/^\[PZ_PAY_CANCEL:(\d+)\]/', $body)) {
        return ['ok' => true, 'request_msg_idx' => $msg_idx];
    }

    $amount = 0;
    if (preg_match('/^\[PZ_PAY:(\d+)/', $body, $m)) {
        $amount = (int) $m[1];
    }

    $cancel_body = '[PZ_PAY_CANCEL:' . max(0, $amount) . "]\n* 결제요청 취소 *\n결제요청을 취소하였습니다.";
    $esc = db_escape($cancel_body);
    if (!db_query("UPDATE tb_trade_room_msg SET msg_body = '{$esc}' WHERE msg_idx = {$msg_idx} AND room_idx = {$room_idx}")) {
        return ['ok' => false, 'error' => '결제 요청을 취소할 수 없습니다.'];
    }

    $like = db_escape('%:' . $msg_idx . ']%');
    db_query("
        DELETE FROM tb_trade_room_msg
        WHERE room_idx = {$room_idx}
          AND msg_body LIKE '[PZ_PAY_ACK:%'
          AND msg_body LIKE '{$like}'
    ");

    if ($pay_idx < 1 && db_table_exists('tb_trade_payment')) {
        $pay_cols = trade_chat_payment_table_columns_status();
        if (!empty($pay_cols['ok'])) {
            $pr = db_assoc(db_query("SELECT pay_idx FROM tb_trade_payment WHERE request_msg_idx = {$msg_idx} LIMIT 1"));
            if ($pr) {
                $pay_idx = (int) ($pr['pay_idx'] ?? 0);
            }
        }
    }
    if ($pay_idx > 0 && db_table_exists('tb_trade_payment')) {
        db_query('UPDATE tb_trade_payment SET pay_status = 3 WHERE pay_idx = ' . $pay_idx . ' AND pay_status < 2');
    }

    db_query('UPDATE tb_trade_room SET room_updated_at = NOW() WHERE room_idx = ' . $room_idx);

    return ['ok' => true, 'request_msg_idx' => $msg_idx];
}

/**
 * 진행 중인 결제 요청(0·1)이 있는지 — 결제 모듈 없을 때 채팅 POST 차단용
 *
 * @return array{ok: bool, error?: string}
 */
function trade_chat_assert_can_create_payment_request(int $room_idx): array
{
    if ($room_idx < 1) {
        return ['ok' => true];
    }

    if (function_exists('trade_payment_assert_can_create_request')) {
        return trade_payment_assert_can_create_request($room_idx);
    }

    $q = db_query("
        SELECT msg_idx, msg_body
        FROM tb_trade_room_msg
        WHERE room_idx = {$room_idx}
          AND msg_body LIKE '[PZ_PAY:%'
          AND msg_body NOT LIKE '[PZ_PAY_CANCEL:%'
          AND msg_body NOT LIKE '[PZ_PAY_ACK:%'
          AND msg_body NOT LIKE '[PZ_PAY_DONE:%'
        ORDER BY msg_idx ASC
    ");
    while ($rw = db_assoc($q)) {
        if (preg_match('/^\[PZ_PAY:(\d+)/', (string) ($rw['msg_body'] ?? ''))) {
            return [
                'ok'    => false,
                'error' => '진행 중인 결제 요청이 있습니다. 취소한 뒤 다시 보내 주세요.',
            ];
        }
    }

    return ['ok' => true];
}

function trade_chat_tables_ok(): bool
{
    return db_table_exists('tb_trade_room') && db_table_exists('tb_trade_room_msg');
}

function trade_chat_load_trade(int $tr_idx): ?array
{
    if ($tr_idx < 1) {
        return null;
    }
    $rs = db_query("
        SELECT tr_idx, mb_idx, tr_status
        FROM tb_trade
        WHERE tr_idx = {$tr_idx}
        LIMIT 1
    ");

    return db_assoc($rs) ?: null;
}

function trade_chat_room_rows(int $tr_idx): array
{
    $out = [];
    $hide_sql = '';
    if (function_exists('trade_chat_room_hidden_columns_ready') && trade_chat_room_hidden_columns_ready()) {
        $hide_sql = ' AND r.room_seller_hidden_at IS NULL';
    }
    $q   = db_query("
        SELECT r.room_idx, r.buyer_mb_idx, r.room_updated_at, m.mb_nick AS buyer_nick
        FROM tb_trade_room r
        LEFT JOIN tb_member m ON m.mb_idx = r.buyer_mb_idx
        WHERE r.tr_idx = {$tr_idx}{$hide_sql}
        ORDER BY r.room_updated_at DESC, r.room_idx DESC
    ");
    while ($rw = db_assoc($q)) {
        $out[] = [
            'room_idx'  => (int) $rw['room_idx'],
            'buyer_mb'  => (int) $rw['buyer_mb_idx'],
            'buyer_nick'=> (string) ($rw['buyer_nick'] ?? ''),
            'updated'   => (string) $rw['room_updated_at'],
        ];
    }

    return $out;
}

function trade_chat_fetch_messages(int $room_idx, int $after_id): array
{
    $after = max(0, $after_id);
    $list  = [];
    $img_sel = trade_chat_msg_has_image_column() ? ', m.msg_image' : '';
    $type_sel = trade_chat_msg_has_payment_columns() ? ', m.msg_type, m.msg_pay_idx' : '';
    if (function_exists('trade_payment_msg_pay_status_ready') && trade_payment_msg_pay_status_ready()) {
        $type_sel .= ', m.msg_pay_status';
    }
    $sql = "
        SELECT m.msg_idx, m.mb_idx, m.msg_body{$type_sel}{$img_sel}, m.msg_created_at, mb.mb_nick
        FROM tb_trade_room_msg m
        LEFT JOIN tb_member mb ON mb.mb_idx = m.mb_idx
        WHERE m.room_idx = {$room_idx}
          AND m.msg_idx > {$after}
        ORDER BY m.msg_idx ASC
        LIMIT 200
    ";
    $q = db_query($sql);
    if ($q === false && $type_sel !== '') {
        error_log('trade_chat_fetch_messages: payment columns query failed — ' . db_last_error());
        $sql = "
            SELECT m.msg_idx, m.mb_idx, m.msg_body{$img_sel}, m.msg_created_at, mb.mb_nick
            FROM tb_trade_room_msg m
            LEFT JOIN tb_member mb ON mb.mb_idx = m.mb_idx
            WHERE m.room_idx = {$room_idx}
              AND m.msg_idx > {$after}
            ORDER BY m.msg_idx ASC
            LIMIT 200
        ";
        $q = db_query($sql);
    }
    while ($rw = db_assoc($q)) {
        $list[] = $rw;
    }

    return $list;
}

function trade_chat_touch_room(int $room_idx): void
{
    db_query("UPDATE tb_trade_room SET room_updated_at = NOW() WHERE room_idx = " . (int) $room_idx);
}

$me = login_member();
if (!$me) {
    trade_chat_json_exit(['ok' => false, 'error' => '로그인이 필요합니다.']);
}

if (!trade_chat_tables_ok()) {
    trade_chat_json_exit(['ok' => false, 'error' => '채팅 DB가 설치되지 않았습니다. sql/tb_trade_chat.sql 을 적용해 주세요.']);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    try {
        $tr_idx    = (int) ($_GET['tr_idx']    ?? 0);
        $room_get  = (int) ($_GET['room_idx']  ?? 0);
        $after_id  = (int) ($_GET['after_id']   ?? 0);

        $trade = trade_chat_load_trade($tr_idx);
        if (!$trade || (int) $trade['tr_status'] !== 1) {
            trade_chat_json_exit(['ok' => false, 'error' => '거래글을 찾을 수 없습니다.']);
        }

        $seller_mb = (int) $trade['mb_idx'];
        $my_mb     = (int) $me['mb_idx'];
        $is_seller = $my_mb === $seller_mb;
        $payment_status = trade_chat_payment_status();
        $payment_ready = !empty($payment_status['ready']);

        $rooms_out = [];
        foreach (trade_chat_room_rows($tr_idx) as $r) {
            $rooms_out[] = [
                'room_idx'   => $r['room_idx'],
                'buyer_nick' => $r['buyer_nick'] !== '' ? $r['buyer_nick'] : ('회원#' . $r['buyer_mb']),
                'updated'    => $r['updated'],
            ];
        }

        $room_idx = 0;
        $rows_raw = [];

        if ($is_seller) {
            if ($room_get > 0) {
                $chk = db_assoc(db_query("
                    SELECT room_idx FROM tb_trade_room
                    WHERE room_idx = {$room_get} AND tr_idx = {$tr_idx}
                    LIMIT 1
                "));
                $room_idx = $chk ? $room_get : 0;
            }
            if ($room_idx < 1) {
                $room_idx = function_exists('trade_chat_find_open_seller_room')
                    ? trade_chat_find_open_seller_room($tr_idx)
                    : ((int) ($rooms_out[0]['room_idx'] ?? 0));
            }
        } else {
            if ($room_get > 0 && function_exists('trade_chat_buyer_owns_room')
                && trade_chat_buyer_owns_room($room_get, $tr_idx, $my_mb)) {
                $room_idx = $room_get;
            } else {
                $room_idx = function_exists('trade_chat_find_open_buyer_room')
                    ? trade_chat_find_open_buyer_room($tr_idx, $my_mb)
                    : 0;
            }
        }

        if ($room_idx > 0 && function_exists('trade_chat_room_is_hidden_for')
            && trade_chat_room_is_hidden_for($room_idx, $my_mb)) {
            $room_idx = 0;
        }

        if ($room_idx > 0) {
            if ($payment_ready) {
                try {
                    trade_payment_sync_room_payments($room_idx);
                    trade_payment_sync_room_delivery_status($room_idx);
                } catch (Throwable $e) {
                    error_log('trade_chat_api payment sync: ' . $e->getMessage());
                }
            }
            $rows_raw = trade_chat_fetch_messages($room_idx, $after_id);
        }

        $messages = [];
        foreach ($rows_raw as $rw) {
            $mid = (int) $rw['msg_idx'];
            $img_path = trade_chat_msg_has_image_column()
                ? trim((string) ($rw['msg_image'] ?? ''))
                : '';
            if ($img_path !== '' && strpos($img_path, '/' . TR_UP_BASE_DIR . '/') !== 0) {
                $img_path = '';
            }
            $msg = [
                'id'    => (string) $mid,
                'mine'  => (int) $rw['mb_idx'] === $my_mb,
                'nick'  => (string) ($rw['mb_nick'] ?? ''),
                'body'  => (string) $rw['msg_body'],
                'at'    => (string) $rw['msg_created_at'],
                'type'  => trade_chat_msg_has_payment_columns() ? (string) ($rw['msg_type'] ?? 'text') : 'text',
            ];
            if ($img_path !== '') {
                $msg['image_src'] = public_url($img_path);
            }
            if ($payment_ready) {
                try {
                    $pay_payload = trade_payment_message_api_payload(
                        $rw,
                        $my_mb,
                        $room_idx,
                        $seller_mb,
                        $product_label
                    );
                    if ($pay_payload) {
                        $msg['payment'] = $pay_payload;
                        $msg['type']    = 'payment_request';
                    }
                } catch (Throwable $e) {
                    error_log('trade_chat_api payment payload: ' . $e->getMessage());
                }
            }
            $messages[] = $msg;
        }

        if ($room_idx > 0 && trade_chat_room_has_read_columns()) {
            trade_chat_mark_room_read($room_idx, $my_mb, $is_seller);
        }

        $active_payment = null;
        if ($payment_ready && $is_seller && $room_idx > 0) {
            try {
                $active_payment = trade_payment_active_request_api_payload(
                    trade_payment_room_open_request($room_idx)
                );
            } catch (Throwable $e) {
                error_log('trade_chat_api active_payment: ' . $e->getMessage());
            }
        }

        $room_archived = false;
        if ($room_idx > 0 && trade_chat_room_archive_columns_ready()) {
            $room_rw = db_assoc(db_query("
                SELECT r.buyer_mb_idx, r.room_buyer_archived_at, r.room_seller_archived_at, t.mb_idx AS seller_mb_idx
                FROM tb_trade_room r
                INNER JOIN tb_trade t ON t.tr_idx = r.tr_idx
                WHERE r.room_idx = {$room_idx}
                LIMIT 1
            "));
            if ($room_rw) {
                $room_archived = trade_chat_is_room_archived_for_member($room_rw, $my_mb);
            }
        }

        trade_chat_json_exit([
            'ok'       => true,
            'is_seller'=> $is_seller,
            'room_idx' => $room_idx,
            'room_archived' => $room_archived,
            'rooms'    => $rooms_out,
            'messages' => $messages,
            'payment_db_ready' => !empty($payment_status['db_ready']),
            'payment_ready' => $payment_ready,
            'payment_chat_only' => !empty($payment_status['chat_only']),
            'payment_sync_to_db' => !empty($payment_status['sync_to_db']),
            'payment_error' => (string) ($payment_status['error'] ?? ''),
            'active_payment' => $active_payment,
        ]);
    } catch (Throwable $e) {
        error_log('trade_chat_api GET: ' . $e->getMessage());
        trade_chat_json_exit(['ok' => false, 'error' => '채팅을 불러오지 못했습니다. 잠시 후 다시 시도해 주세요.']);
    }
}

if ($method !== 'POST') {
    trade_chat_json_exit(['ok' => false, 'error' => '지원하지 않는 요청입니다.']);
}

$post_action = trim((string) ($_POST['action'] ?? ''));
if ($post_action === 'cancel_payment') {
    $tr_idx   = (int) ($_POST['tr_idx'] ?? 0);
    $room_idx = (int) ($_POST['room_idx'] ?? 0);
    $pay_idx  = (int) ($_POST['pay_idx'] ?? 0);
    $msg_idx  = (int) ($_POST['msg_idx'] ?? 0);

    if ($tr_idx < 1 || $room_idx < 1) {
        trade_chat_json_exit(['ok' => false, 'error' => '잘못된 요청입니다.']);
    }

    $trade = trade_chat_load_trade($tr_idx);
    if (!$trade || (int) $trade['tr_status'] !== 1) {
        trade_chat_json_exit(['ok' => false, 'error' => '거래글을 찾을 수 없습니다.']);
    }

    $my_mb     = (int) $me['mb_idx'];
    $seller_mb = (int) $trade['mb_idx'];
    if ($my_mb !== $seller_mb) {
        trade_chat_json_exit(['ok' => false, 'error' => '판매자만 결제 요청을 취소할 수 있습니다.']);
    }

    if ($msg_idx < 1 && $pay_idx > 0 && db_table_exists('tb_trade_payment')) {
        $pay_cols = trade_chat_payment_table_columns_status();
        if (!empty($pay_cols['ok'])) {
            $pr = db_assoc(db_query("SELECT request_msg_idx FROM tb_trade_payment WHERE pay_idx = {$pay_idx} LIMIT 1"));
            if ($pr) {
                $msg_idx = (int) ($pr['request_msg_idx'] ?? 0);
            }
        }
    }

    if (trade_chat_payment_cancel_load()) {
        trade_chat_json_exit(trade_payment_cancel_request($pay_idx, $my_mb, $room_idx, $msg_idx));
    }

    trade_chat_json_exit(trade_chat_cancel_payment_fallback($my_mb, $room_idx, $msg_idx, $pay_idx));
}

$post_action = trim((string) ($_POST['action'] ?? ''));
if ($post_action === 'confirm_shipping' || $post_action === 'confirm_purchase') {
    $tr_idx  = (int) ($_POST['tr_idx'] ?? 0);
    $pay_idx = (int) ($_POST['pay_idx'] ?? 0);

    if ($tr_idx < 1 || $pay_idx < 1) {
        trade_chat_json_exit(['ok' => false, 'error' => '잘못된 요청입니다.']);
    }

    $trade = trade_chat_load_trade($tr_idx);
    if (!$trade || (int) $trade['tr_status'] !== 1) {
        trade_chat_json_exit(['ok' => false, 'error' => '거래글을 찾을 수 없습니다.']);
    }

    $my_mb     = (int) $me['mb_idx'];
    $seller_mb = (int) $trade['mb_idx'];
    if ($my_mb === $seller_mb) {
        trade_chat_json_exit(['ok' => false, 'error' => '구매자만 이용할 수 있습니다.']);
    }

    if (!trade_chat_payment_cancel_load()) {
        trade_chat_json_exit(['ok' => false, 'error' => '결제 모듈을 불러오지 못했습니다.']);
    }

    if ($post_action === 'confirm_shipping') {
        trade_chat_json_exit(trade_payment_confirm_shipping($pay_idx, $my_mb));
    }
    trade_chat_json_exit(trade_payment_confirm_purchase($pay_idx, $my_mb));
}

if ($post_action === 'mark_trade_sold') {
    $tr_idx  = (int) ($_POST['tr_idx'] ?? 0);
    $pay_idx = (int) ($_POST['pay_idx'] ?? 0);

    if ($tr_idx < 1 || $pay_idx < 1) {
        trade_chat_json_exit(['ok' => false, 'error' => '잘못된 요청입니다.']);
    }

    $trade = trade_chat_load_trade($tr_idx);
    if (!$trade || (int) $trade['tr_status'] !== 1) {
        trade_chat_json_exit(['ok' => false, 'error' => '거래글을 찾을 수 없습니다.']);
    }

    $my_mb     = (int) $me['mb_idx'];
    $seller_mb = (int) $trade['mb_idx'];
    if ($my_mb !== $seller_mb) {
        trade_chat_json_exit(['ok' => false, 'error' => '판매자만 이용할 수 있습니다.']);
    }

    if (!trade_chat_payment_cancel_load()) {
        trade_chat_json_exit(['ok' => false, 'error' => '결제 모듈을 불러오지 못했습니다.']);
    }

    trade_chat_json_exit(trade_payment_mark_trade_sold($pay_idx, $my_mb));
}

$post_action = trim((string) ($_POST['action'] ?? ''));
if ($post_action === 'pay_cash') {
    $tr_idx          = (int) ($_POST['tr_idx'] ?? 0);
    $pay_idx         = (int) ($_POST['pay_idx'] ?? 0);
    $request_msg_idx = (int) ($_POST['request_msg_idx'] ?? 0);

    if ($tr_idx < 1 || ($pay_idx < 1 && $request_msg_idx < 1)) {
        trade_chat_json_exit(['ok' => false, 'error' => '잘못된 요청입니다.']);
    }

    $trade = trade_chat_load_trade($tr_idx);
    if (!$trade || (int) $trade['tr_status'] !== 1) {
        trade_chat_json_exit(['ok' => false, 'error' => '거래글을 찾을 수 없습니다.']);
    }

    $my_mb     = (int) $me['mb_idx'];
    $seller_mb = (int) $trade['mb_idx'];
    if ($my_mb === $seller_mb) {
        trade_chat_json_exit(['ok' => false, 'error' => '구매자만 캐시 결제를 할 수 있습니다.']);
    }

    if (!trade_chat_payment_cancel_load()) {
        trade_chat_json_exit(['ok' => false, 'error' => '결제 모듈을 불러오지 못했습니다.']);
    }

    trade_chat_json_exit(trade_payment_pay_with_cash($my_mb, $pay_idx, $request_msg_idx));
}

$post_action = trim((string) ($_POST['action'] ?? ''));
if ($post_action === 'close_room') {
    $tr_idx   = (int) ($_POST['tr_idx'] ?? 0);
    $room_idx = (int) ($_POST['room_idx'] ?? 0);

    if ($tr_idx < 1 || $room_idx < 1) {
        trade_chat_json_exit(['ok' => false, 'error' => '잘못된 요청입니다.']);
    }

    trade_chat_json_exit(trade_chat_close_room($room_idx, $tr_idx, (int) $me['mb_idx']));
}

$post_action = trim((string) ($_POST['action'] ?? ''));
if ($post_action === 'hide_room') {
    $tr_idx   = (int) ($_POST['tr_idx'] ?? 0);
    $room_idx = (int) ($_POST['room_idx'] ?? 0);

    if ($tr_idx < 1 || $room_idx < 1) {
        trade_chat_json_exit(['ok' => false, 'error' => '잘못된 요청입니다.']);
    }
    if (!function_exists('trade_chat_hide_closed_room')) {
        trade_chat_json_exit(['ok' => false, 'error' => '삭제 기능을 불러오지 못했습니다.']);
    }

    trade_chat_json_exit(trade_chat_hide_closed_room($room_idx, $tr_idx, (int) $me['mb_idx']));
}

$tr_idx   = (int) ($_POST['tr_idx']   ?? 0);
$room_in  = (int) ($_POST['room_idx'] ?? 0);
$body_raw = trim((string) ($_POST['body'] ?? ''));
$body     = mb_substr($body_raw, 0, TRADE_CHAT_BODY_MAX);
$fi       = isset($_FILES['msg_image']) && is_array($_FILES['msg_image']) ? $_FILES['msg_image'] : null;
$file_err = $fi && isset($fi['error']) ? (int) $fi['error'] : UPLOAD_ERR_NO_FILE;

if ($tr_idx < 1) {
    trade_chat_json_exit(['ok' => false, 'error' => '잘못된 요청입니다.']);
}
if ($body === '' && $file_err === UPLOAD_ERR_NO_FILE) {
    trade_chat_json_exit(['ok' => false, 'error' => '메시지 또는 이미지를 입력해 주세요.']);
}

$has_file = $file_err !== UPLOAD_ERR_NO_FILE;

$trade = trade_chat_load_trade($tr_idx);
if (!$trade || (int) $trade['tr_status'] !== 1) {
    trade_chat_json_exit(['ok' => false, 'error' => '거래글을 찾을 수 없습니다.']);
}

$trade_detail = db_assoc(db_query("
    SELECT tr_idx, mb_idx, tr_status, tr_card_name, tr_title
    FROM tb_trade
    WHERE tr_idx = {$tr_idx}
    LIMIT 1
"));
$product_label = $trade_detail ? trade_chat_inquiry_product_label($trade_detail) : '상품';

$seller_mb = (int) $trade['mb_idx'];
$my_mb     = (int) $me['mb_idx'];
$is_seller = $my_mb === $seller_mb;

if (!$is_seller && function_exists('trade_chat_allow_multiple_buyer_rooms')) {
    trade_chat_allow_multiple_buyer_rooms();
}

global $conn;
mysqli_begin_transaction($conn);

try {
    $room_idx = 0;

    if ($is_seller) {
        if ($room_in < 1) {
            throw new RuntimeException('대화방이 없습니다.');
        }
        $chk = db_assoc(db_query("
            SELECT room_idx FROM tb_trade_room
            WHERE room_idx = {$room_in} AND tr_idx = {$tr_idx}
            LIMIT 1
        "));
        if (!$chk) {
            throw new RuntimeException('대화방을 찾을 수 없습니다.');
        }
        $room_idx = $room_in;
    } else {
        if ($room_in > 0 && function_exists('trade_chat_buyer_owns_room')
            && trade_chat_buyer_owns_room($room_in, $tr_idx, $my_mb)) {
            $use_in = true;
            if (trade_chat_room_archive_columns_ready()) {
                $arch_in = db_assoc(db_query("
                    SELECT r.buyer_mb_idx, r.room_buyer_archived_at, r.room_seller_archived_at, t.mb_idx AS seller_mb_idx
                    FROM tb_trade_room r
                    INNER JOIN tb_trade t ON t.tr_idx = r.tr_idx
                    WHERE r.room_idx = {$room_in}
                    LIMIT 1
                "));
                if ($arch_in && trade_chat_is_room_archived_for_member($arch_in, $my_mb)) {
                    $use_in = false;
                }
            }
            if ($use_in) {
                $room_idx = $room_in;
            }
        }
        if ($room_idx < 1) {
            $room_idx = function_exists('trade_chat_find_open_buyer_room')
                ? trade_chat_find_open_buyer_room($tr_idx, $my_mb)
                : 0;
        }
        if ($room_idx < 1) {
            if (!db_query("
                INSERT INTO tb_trade_room (tr_idx, buyer_mb_idx, room_updated_at)
                VALUES ({$tr_idx}, {$my_mb}, NOW())
            ")) {
                throw new RuntimeException('방을 만들 수 없습니다.');
            }
            $room_idx = (int) db_insert_id();
        }
    }

    $uploaded_path = '';
    if ($has_file) {
        if (!trade_chat_msg_has_image_column()) {
            throw new RuntimeException('이미지 첨부를 사용하려면 DB에 sql/tb_trade_chat_msg_image_migration.sql 을 적용해 주세요.');
        }
        $up = trade_upload_chat_image($_FILES['msg_image']);
        if (!$up['ok']) {
            throw new RuntimeException($up['error'] !== '' ? $up['error'] : '이미지 업로드에 실패했습니다.');
        }
        $uploaded_path = $up['path'];
        if ($body === '' && $uploaded_path === '') {
            throw new RuntimeException('메시지 또는 이미지를 입력해 주세요.');
        }
    }

    if ($is_seller && $body !== '' && preg_match('/^\[PZ_PAY:\d+/', $body)) {
        if (trade_chat_payment_cancel_load() && function_exists('trade_payment_assert_can_create_request')) {
            $can_create = trade_payment_assert_can_create_request($room_idx);
        } else {
            $can_create = trade_chat_assert_can_create_payment_request($room_idx);
        }
        if (!$can_create['ok']) {
            if ($uploaded_path !== '') {
                trade_remove_files([$uploaded_path]);
            }
            throw new RuntimeException((string) ($can_create['error'] ?? '결제 요청을 보낼 수 없습니다.'));
        }
    }

    if ($room_idx > 0 && trade_chat_room_archive_columns_ready()) {
        $arch_chk = db_assoc(db_query("
            SELECT r.buyer_mb_idx, r.room_buyer_archived_at, r.room_seller_archived_at, t.mb_idx AS seller_mb_idx
            FROM tb_trade_room r
            INNER JOIN tb_trade t ON t.tr_idx = r.tr_idx
            WHERE r.room_idx = {$room_idx}
            LIMIT 1
        "));
        if ($arch_chk && trade_chat_is_room_archived_for_member($arch_chk, $my_mb)) {
            throw new RuntimeException('종료된 대화에는 메시지를 보낼 수 없습니다.');
        }
    }

    $esc = db_escape($body);
    if (trade_chat_msg_has_image_column()) {
        $img_sql = $uploaded_path !== '' ? ("'" . db_escape($uploaded_path) . "'") : 'NULL';
        $ok_ins = db_query("
            INSERT INTO tb_trade_room_msg (room_idx, mb_idx, msg_body, msg_image, msg_created_at)
            VALUES ({$room_idx}, {$my_mb}, '{$esc}', {$img_sql}, NOW())
        ");
    } else {
        if ($uploaded_path !== '') {
            trade_remove_files([$uploaded_path]);
            throw new RuntimeException('이미지 첨부를 사용하려면 DB에 sql/tb_trade_chat_msg_image_migration.sql 을 적용해 주세요.');
        }
        $ok_ins = db_query("
            INSERT INTO tb_trade_room_msg (room_idx, mb_idx, msg_body, msg_created_at)
            VALUES ({$room_idx}, {$my_mb}, '{$esc}', NOW())
        ");
    }
    if (!$ok_ins) {
        if ($uploaded_path !== '') {
            trade_remove_files([$uploaded_path]);
        }
        throw new RuntimeException('메시지 저장에 실패했습니다.');
    }
    $new_id = (int) db_insert_id();

    if ($is_seller && $body !== '' && preg_match('/^\[PZ_PAY:\d+/', $body)) {
        trade_chat_payment_cancel_load();
        if (function_exists('trade_payment_init_request_msg_status')) {
            trade_payment_init_request_msg_status($new_id);
        }
        if (function_exists('trade_payment_list_active_requests')) {
            $active_after = trade_payment_list_active_requests($room_idx);
            if (count($active_after) > 1) {
                db_query('DELETE FROM tb_trade_room_msg WHERE msg_idx = ' . $new_id);
                if ($uploaded_path !== '') {
                    trade_remove_files([$uploaded_path]);
                }
                throw new RuntimeException('진행 중인 결제 요청이 있습니다. 취소한 뒤 다시 보내 주세요.');
            }
        }
    }

    $pay_hook = [];
    if (trade_chat_payment_lib_load()) {
        $pay_hook = trade_payment_after_chat_message_insert(
            $new_id,
            $room_idx,
            $tr_idx,
            $my_mb,
            $is_seller,
            $body,
            $seller_mb,
            $product_label
        );
        if (!empty($pay_hook['error'])) {
            if ($uploaded_path !== '') {
                trade_remove_files([$uploaded_path]);
            }
            throw new RuntimeException((string) $pay_hook['error']);
        }
    }

    trade_chat_bump_room($room_idx, $my_mb);

    mysqli_commit($conn);
    if (trade_chat_room_has_read_columns()) {
        trade_chat_mark_room_read($room_idx, $my_mb, $is_seller);
    }

    $skip_push = !empty($pay_hook['skip_push']);
    if (!$skip_push) {
        require_once __DIR__ . '/../lib/webpush.php';
        $buyer_row   = db_assoc(db_query("SELECT buyer_mb_idx FROM tb_trade_room WHERE room_idx = " . (int) $room_idx . " LIMIT 1"));
        $buyer_mb    = (int) ($buyer_row['buyer_mb_idx'] ?? 0);
        $recipient_mb = $is_seller ? $buyer_mb : $seller_mb;
        if ($recipient_mb > 0 && $recipient_mb !== $my_mb) {
            $from_nick = trim((string) ($me['mb_nick'] ?? ''));
            if ($from_nick === '') {
                $from_nick = '회원';
            }
            $push_preview = $body;
            if (trade_chat_payment_lib_load() && function_exists('trade_payment_chat_push_preview')) {
                $push_preview = trade_payment_chat_push_preview($body);
            }
            if ($push_preview === '' && $uploaded_path !== '') {
                $push_preview = '📷 사진';
            }
            try {
                webpush_notify_trade_message($recipient_mb, $from_nick, $push_preview, $tr_idx, $room_idx);
            } catch (Throwable $e) {
                error_log('trade_chat_api webpush: ' . $e->getMessage());
            }
        }
    }

    trade_chat_json_exit([
        'ok'      => true,
        'room_idx'=> $room_idx,
        'id'      => (string) $new_id,
        'pay_idx' => (int) ($pay_hook['pay_idx'] ?? 0),
    ]);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    trade_chat_json_exit(['ok' => false, 'error' => $e->getMessage()]);
}
