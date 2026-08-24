<?php
if (!defined('PZ_API_JSON')) {
    header('Content-Type: text/html; charset=UTF-8');
}
$dir = __DIR__;

include_once dirname(__DIR__) . '/config/site_info.php';
include_once __DIR__ . '/_site_legal.php';
include_once __DIR__."/_seo.php";
include_once __DIR__ . '/_session.php';

pz_sessions_boot();
extract($_GET);
extract($_POST);
date_default_timezone_set('Asia/Seoul');

/* ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
    ┃ 데이터베이스 관련 함수                                                                                                                           ┃
    ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛*/

$conn = mysqli_connect($db_admin_host, $db_admin_user, $db_admin_pass, $db_admin_database);
if (!$conn) {
    $err = mysqli_connect_error();
    $eno = mysqli_connect_errno();
    error_log("mysqli connect failed [{$eno}]: {$err}");
    if (!empty($db_connection_debug)) {
        header('Content-Type: text/plain; charset=UTF-8');
        http_response_code(503);
        exit(
            "DB 연결 실패\n"
            . "- 호스트: {$db_admin_host}\n"
            . "- 사용자: {$db_admin_user}\n"
            . "- DB명: {$db_admin_database}\n"
            . "- mysqli errno: {$eno}\n"
            . "- mysqli error: {$err}\n"
            . "\n※ 해결 후 site_info.php 의 \$db_connection_debug 를 false 로 바꾸세요.\n"
        );
    }
    http_response_code(503);
    exit('데이터베이스에 연결할 수 없습니다. 잠시 후 다시 시도해 주세요.');
}
mysqli_query($conn, "set names utf8mb4");
//DB 접속
// function db_connect($db_host, $db_user, $db_pass, $db_name){
//     $result = mysqli_connect($db_host, $db_user, $db_pass) or die(mysql_error());
//     mysqli_select_db($db_name) or die(mysql_error());
//     mysqli_query($conn, "set names utf8");
//     return $result;
// }

//SQL 쿼리 실행 함수
function db_query($sql){
    global $conn;
    $rs = mysqli_query($conn, $sql);
    return $rs;
}

//개별데이터
function db_select($sql, $params = []){
    $rs = db_query($sql, $params);
    return db_fetch($rs);
}

//데이터를 배열로 가져오기
function db_fetch($rs){
    return @mysqli_fetch_array($rs);
}
function db_assoc($rs){
    return @mysqli_fetch_assoc($rs);
}

function db_result($sql){
    $rs = db_query($sql);
    if ($rs === false) {
        return null;
    }
    $row = mysqli_fetch_array($rs);
    if($row ) return @$row[0];
}

//문자열 이스케이프 (SQL Injection 방지)
function db_escape($str){
    global $conn;
    return mysqli_real_escape_string($conn, (string)$str);
}

function db_last_error(): string
{
    global $conn;
    if (!$conn) {
        return '';
    }
    return (string) mysqli_error($conn);
}

/** 테이블 존재 여부 (이름은 영문·숫자·밑줄만 허용) */
function db_table_exists(string $table): bool
{
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
        return false;
    }
    $rs = db_query("SHOW TABLES LIKE '{$table}'");
    return (bool) ($rs && mysqli_num_rows($rs) > 0);
}

/** tb_trade_room_msg.msg_image 컬럼 적용 여부(첨부 이미지) */
function trade_chat_msg_has_image_column(): bool
{
    static $c = null;
    if ($c !== null) {
        return $c;
    }
    if (!db_table_exists('tb_trade_room_msg')) {
        $c = false;

        return false;
    }
    $c = (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_trade_room_msg LIKE 'msg_image'"));

    return $c;
}

/** tb_trade_room 읽음 컬럼(room_buyer_read_msg_idx 등) 적용 여부 */
function trade_chat_room_has_read_columns(): bool
{
    static $c = null;
    if ($c !== null) {
        return $c;
    }
    if (!db_table_exists('tb_trade_room')) {
        $c = false;

        return false;
    }
    $c = (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_trade_room LIKE 'room_buyer_read_msg_idx'"));

    return $c;
}

/** 대화방 열람 시 읽음 위치를 최신 msg_idx 로 (헤더 안읽음 개수용) */
function trade_chat_mark_room_read(int $room_idx, int $mb_idx, bool $is_seller): void
{
    if ($room_idx < 1 || $mb_idx < 1 || !trade_chat_room_has_read_columns()) {
        return;
    }
    $room_idx = (int) $room_idx;
    $mb_idx   = (int) $mb_idx;
    $max_id   = (int) db_result("SELECT COALESCE(MAX(msg_idx), 0) FROM tb_trade_room_msg WHERE room_idx = {$room_idx}");
    if ($is_seller) {
        db_query("UPDATE tb_trade_room SET room_seller_read_msg_idx = {$max_id} WHERE room_idx = {$room_idx}");
    } else {
        db_query("UPDATE tb_trade_room SET room_buyer_read_msg_idx = {$max_id} WHERE room_idx = {$room_idx} AND buyer_mb_idx = {$mb_idx}");
    }
}

/** 채팅 첨부 이미지 웹경로 삭제 */
function trade_chat_delete_msg_image_file(string $web_path): void
{
    $web_path = trim($web_path);
    if ($web_path === '' || strpos($web_path, '/uploads/') !== 0) {
        return;
    }
    $doc_root = isset($_SERVER['DOCUMENT_ROOT']) ? (string) $_SERVER['DOCUMENT_ROOT'] : '';
    if ($doc_root === '') {
        return;
    }
    $full = rtrim($doc_root, '/') . $web_path;
    if (is_file($full)) {
        @unlink($full);
    }
}

/**
 * 채팅 종료(보관) 컬럼 적용 여부
 */
function trade_chat_room_archive_columns_ready(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    if (!db_table_exists('tb_trade_room')) {
        $ready = false;

        return false;
    }
    $ready = (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_trade_room LIKE 'room_buyer_archived_at'"))
        && (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_trade_room LIKE 'room_seller_archived_at'"));

    return $ready;
}

/**
 * 대화종료 목록에서 숨김(소프트 삭제) 컬럼
 */
function trade_chat_room_hidden_columns_ready(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    if (!db_table_exists('tb_trade_room')) {
        $ready = false;

        return false;
    }

    $has_buyer  = (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_trade_room LIKE 'room_buyer_hidden_at'"));
    $has_seller = (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_trade_room LIKE 'room_seller_hidden_at'"));
    if (!$has_buyer) {
        db_query("
            ALTER TABLE `tb_trade_room`
            ADD COLUMN `room_buyer_hidden_at` DATETIME NULL DEFAULT NULL
            COMMENT '구매자 목록 숨김 시각' AFTER `room_seller_archived_at`
        ");
        $has_buyer = (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_trade_room LIKE 'room_buyer_hidden_at'"));
    }
    if (!$has_seller) {
        $after = $has_buyer ? 'room_buyer_hidden_at' : 'room_seller_archived_at';
        db_query("
            ALTER TABLE `tb_trade_room`
            ADD COLUMN `room_seller_hidden_at` DATETIME NULL DEFAULT NULL
            COMMENT '판매자 목록 숨김 시각' AFTER `{$after}`
        ");
        $has_seller = (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_trade_room LIKE 'room_seller_hidden_at'"));
    }

    $ready = $has_buyer && $has_seller;

    return $ready;
}

/**
 * 거래글+구매자당 여러 대화방 허용 (대화종료 후 새 문의)
 */
function trade_chat_allow_multiple_buyer_rooms(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    if (!db_table_exists('tb_trade_room')) {
        $ready = false;

        return false;
    }

    $uniq = db_assoc(db_query("SHOW INDEX FROM tb_trade_room WHERE Key_name = 'uq_trade_buyer'"));
    if ($uniq) {
        if (!db_query('ALTER TABLE tb_trade_room DROP INDEX uq_trade_buyer')) {
            $ready = false;

            return false;
        }
    }

    $idx = db_assoc(db_query("SHOW INDEX FROM tb_trade_room WHERE Key_name = 'idx_trade_buyer'"));
    if (!$idx) {
        db_query('ALTER TABLE tb_trade_room ADD INDEX idx_trade_buyer (tr_idx, buyer_mb_idx)');
    }

    $ready = true;

    return true;
}

/**
 * 구매자가 종료하지 않은 최신 대화방
 */
function trade_chat_find_open_buyer_room(int $tr_idx, int $buyer_mb_idx): int
{
    $tr_idx       = (int) $tr_idx;
    $buyer_mb_idx = (int) $buyer_mb_idx;
    if ($tr_idx < 1 || $buyer_mb_idx < 1 || !db_table_exists('tb_trade_room')) {
        return 0;
    }

    $arch = '';
    if (trade_chat_room_archive_columns_ready()) {
        $arch = ' AND room_buyer_archived_at IS NULL';
    }
    if (trade_chat_room_hidden_columns_ready()) {
        $arch .= ' AND room_buyer_hidden_at IS NULL';
    }

    $rw = db_assoc(db_query("
        SELECT room_idx
        FROM tb_trade_room
        WHERE tr_idx = {$tr_idx} AND buyer_mb_idx = {$buyer_mb_idx}
        {$arch}
        ORDER BY room_idx DESC
        LIMIT 1
    "));

    return $rw ? (int) $rw['room_idx'] : 0;
}

/**
 * 판매자가 종료하지 않은 최신 대화방 (해당 거래글)
 */
function trade_chat_find_open_seller_room(int $tr_idx): int
{
    $tr_idx = (int) $tr_idx;
    if ($tr_idx < 1 || !db_table_exists('tb_trade_room')) {
        return 0;
    }

    $arch = '';
    if (trade_chat_room_archive_columns_ready()) {
        $arch = ' AND r.room_seller_archived_at IS NULL';
    }
    if (trade_chat_room_hidden_columns_ready()) {
        $arch .= ' AND r.room_seller_hidden_at IS NULL';
    }

    $rw = db_assoc(db_query("
        SELECT r.room_idx
        FROM tb_trade_room r
        WHERE r.tr_idx = {$tr_idx}
        {$arch}
        ORDER BY r.room_updated_at DESC, r.room_idx DESC
        LIMIT 1
    "));

    return $rw ? (int) $rw['room_idx'] : 0;
}

function trade_chat_buyer_owns_room(int $room_idx, int $tr_idx, int $buyer_mb_idx): bool
{
    $room_idx     = (int) $room_idx;
    $tr_idx       = (int) $tr_idx;
    $buyer_mb_idx = (int) $buyer_mb_idx;
    if ($room_idx < 1 || $tr_idx < 1 || $buyer_mb_idx < 1) {
        return false;
    }

    $rw = db_assoc(db_query("
        SELECT room_idx
        FROM tb_trade_room
        WHERE room_idx = {$room_idx} AND tr_idx = {$tr_idx} AND buyer_mb_idx = {$buyer_mb_idx}
        LIMIT 1
    "));

    return (bool) $rw;
}

/**
 * @param 'active'|'closed' $tab
 */
function trade_chat_inbox_archive_sql(int $my_mb, string $inbox_role, string $tab): string
{
    if (!trade_chat_room_archive_columns_ready()) {
        return $tab === 'closed' ? ' AND 1=0' : '';
    }

    $col = $inbox_role === 'seller' ? 'room_seller_archived_at' : 'room_buyer_archived_at';
    $sql = $tab === 'closed'
        ? " AND r.{$col} IS NOT NULL"
        : " AND r.{$col} IS NULL";

    if (trade_chat_room_hidden_columns_ready()) {
        $hide_col = $inbox_role === 'seller' ? 'room_seller_hidden_at' : 'room_buyer_hidden_at';
        $sql .= " AND r.{$hide_col} IS NULL";
    }

    return $sql;
}

function trade_chat_is_room_archived_for_member(array $room, int $my_mb): bool
{
    if (!trade_chat_room_archive_columns_ready() || $my_mb < 1) {
        return false;
    }

    $buyer_mb  = (int) ($room['buyer_mb_idx'] ?? 0);
    $seller_mb = (int) ($room['seller_mb_idx'] ?? ($room['mb_idx'] ?? 0));

    if ($my_mb === $buyer_mb) {
        return trim((string) ($room['room_buyer_archived_at'] ?? '')) !== '';
    }
    if ($my_mb === $seller_mb) {
        return trim((string) ($room['room_seller_archived_at'] ?? '')) !== '';
    }

    return false;
}

function trade_chat_is_room_hidden_for_member(array $room, int $my_mb): bool
{
    if (!trade_chat_room_hidden_columns_ready() || $my_mb < 1) {
        return false;
    }

    $buyer_mb  = (int) ($room['buyer_mb_idx'] ?? 0);
    $seller_mb = (int) ($room['seller_mb_idx'] ?? ($room['mb_idx'] ?? 0));

    if ($my_mb === $buyer_mb) {
        return trim((string) ($room['room_buyer_hidden_at'] ?? '')) !== '';
    }
    if ($my_mb === $seller_mb) {
        return trim((string) ($room['room_seller_hidden_at'] ?? '')) !== '';
    }

    return false;
}

function trade_chat_room_is_hidden_for(int $room_idx, int $my_mb): bool
{
    $room_idx = (int) $room_idx;
    $my_mb    = (int) $my_mb;
    if ($room_idx < 1 || $my_mb < 1 || !trade_chat_room_hidden_columns_ready()) {
        return false;
    }

    $room = db_assoc(db_query("
        SELECT r.buyer_mb_idx, r.room_buyer_hidden_at, r.room_seller_hidden_at, t.mb_idx AS seller_mb_idx
        FROM tb_trade_room r
        INNER JOIN tb_trade t ON t.tr_idx = r.tr_idx
        WHERE r.room_idx = {$room_idx}
        LIMIT 1
    "));

    return $room ? trade_chat_is_room_hidden_for_member($room, $my_mb) : false;
}

function trade_chat_unarchive_recipient_on_message(int $room_idx, int $sender_mb_idx): void
{
    if (!trade_chat_room_archive_columns_ready() || $room_idx < 1 || $sender_mb_idx < 1) {
        return;
    }

    $room = db_assoc(db_query("
        SELECT r.buyer_mb_idx, t.mb_idx AS seller_mb_idx
        FROM tb_trade_room r
        INNER JOIN tb_trade t ON t.tr_idx = r.tr_idx
        WHERE r.room_idx = {$room_idx}
        LIMIT 1
    "));
    if (!$room) {
        return;
    }

    $buyer_mb  = (int) ($room['buyer_mb_idx'] ?? 0);
    $seller_mb = (int) ($room['seller_mb_idx'] ?? 0);

    if ($sender_mb_idx === $seller_mb && $buyer_mb > 0) {
        db_query("UPDATE tb_trade_room SET room_buyer_archived_at = NULL WHERE room_idx = {$room_idx}");
    } elseif ($sender_mb_idx === $buyer_mb && $seller_mb > 0) {
        db_query("UPDATE tb_trade_room SET room_seller_archived_at = NULL WHERE room_idx = {$room_idx}");
    }
}

function trade_chat_bump_room(int $room_idx, int $sender_mb_idx = 0): void
{
    if ($room_idx < 1) {
        return;
    }
    db_query('UPDATE tb_trade_room SET room_updated_at = NOW() WHERE room_idx = ' . (int) $room_idx);
    if ($sender_mb_idx > 0) {
        trade_chat_unarchive_recipient_on_message($room_idx, $sender_mb_idx);
    }
}

/**
 * @return array<int, array<string, mixed>>
 */
function trade_messages_inbox_list(int $my_mb, string $thumb_sql_fragment, string $tab = 'active'): array
{
    $tab = $tab === 'closed' ? 'closed' : 'active';
    $out = [];

    $buyer_archive = trade_chat_inbox_archive_sql($my_mb, 'buyer', $tab);
    $q1 = db_query("
        SELECT r.room_idx, r.tr_idx, r.room_updated_at, t.tr_title,
               t.mb_idx AS seller_mb_idx, s.mb_nick AS peer_nick, 'buyer' AS inbox_role
               {$thumb_sql_fragment}
        FROM tb_trade_room r
        INNER JOIN tb_trade t ON t.tr_idx = r.tr_idx AND t.tr_status = 1
        LEFT JOIN tb_member s ON s.mb_idx = t.mb_idx
        WHERE r.buyer_mb_idx = {$my_mb}{$buyer_archive}
    ");
    while ($rw = db_assoc($q1)) {
        $out[] = [
            'room_idx'   => (int) $rw['room_idx'],
            'tr_idx'     => (int) $rw['tr_idx'],
            'updated_at' => (string) $rw['room_updated_at'],
            'tr_title'   => (string) $rw['tr_title'],
            'peer_nick'  => (string) ($rw['peer_nick'] ?? ''),
            'inbox_role' => 'buyer',
            'thumb_path' => (string) ($rw['thumb_path'] ?? ''),
        ];
    }

    $seller_archive = trade_chat_inbox_archive_sql($my_mb, 'seller', $tab);
    $q2 = db_query("
        SELECT r.room_idx, r.tr_idx, r.room_updated_at, t.tr_title,
               r.buyer_mb_idx, b.mb_nick AS peer_nick, 'seller' AS inbox_role
               {$thumb_sql_fragment}
        FROM tb_trade_room r
        INNER JOIN tb_trade t ON t.tr_idx = r.tr_idx AND t.tr_status = 1
        LEFT JOIN tb_member b ON b.mb_idx = r.buyer_mb_idx
        WHERE t.mb_idx = {$my_mb}{$seller_archive}
    ");
    while ($rw = db_assoc($q2)) {
        $out[] = [
            'room_idx'   => (int) $rw['room_idx'],
            'tr_idx'     => (int) $rw['tr_idx'],
            'updated_at' => (string) $rw['room_updated_at'],
            'tr_title'   => (string) $rw['tr_title'],
            'peer_nick'  => (string) ($rw['peer_nick'] ?? ''),
            'inbox_role' => 'seller',
            'thumb_path' => (string) ($rw['thumb_path'] ?? ''),
        ];
    }

    usort($out, static function (array $a, array $b): int {
        return strcmp($b['updated_at'], $a['updated_at']);
    });

    return $out;
}

/**
 * 채팅 종료 — 내 목록에서 보관 (대화 내용 유지)
 *
 * @return array{ok: bool, error?: string, redirect?: string}
 */
function trade_chat_close_room(int $room_idx, int $tr_idx, int $my_mb): array
{
    $fail = static function (string $error): array {
        return ['ok' => false, 'error' => $error];
    };

    if ($room_idx < 1 || $tr_idx < 1 || $my_mb < 1) {
        return $fail('잘못된 요청입니다.');
    }
    if (!db_table_exists('tb_trade_room') || !db_table_exists('tb_trade_room_msg')) {
        return $fail('채팅 DB가 설치되지 않았습니다.');
    }
    if (!trade_chat_room_archive_columns_ready()) {
        return $fail('대화 종료 기능 DB가 없습니다. sql/migrate_tb_trade_room_archive.sql 을 적용해 주세요.');
    }

    $room = db_assoc(db_query("
        SELECT r.room_idx, r.tr_idx, r.buyer_mb_idx, r.room_buyer_archived_at, r.room_seller_archived_at,
               t.mb_idx AS seller_mb_idx, t.tr_status
        FROM tb_trade_room r
        INNER JOIN tb_trade t ON t.tr_idx = r.tr_idx
        WHERE r.room_idx = {$room_idx} AND r.tr_idx = {$tr_idx}
        LIMIT 1
    "));
    if (!$room) {
        return $fail('대화방을 찾을 수 없습니다.');
    }
    if ((int) ($room['tr_status'] ?? 0) !== 1) {
        return $fail('종료된 거래글의 채팅은 정리할 수 없습니다.');
    }

    $seller_mb = (int) ($room['seller_mb_idx'] ?? 0);
    $buyer_mb  = (int) ($room['buyer_mb_idx'] ?? 0);
    if ($my_mb !== $seller_mb && $my_mb !== $buyer_mb) {
        return $fail('이 대화방에 접근할 수 없습니다.');
    }

    if (trade_chat_is_room_archived_for_member($room, $my_mb)) {
        return $fail('이미 종료된 대화입니다.');
    }

    $set = $my_mb === $seller_mb
        ? 'room_seller_archived_at = NOW()'
        : 'room_buyer_archived_at = NOW()';

    if (!db_query("UPDATE tb_trade_room SET {$set}, room_updated_at = NOW() WHERE room_idx = {$room_idx} LIMIT 1")) {
        return $fail('채팅을 종료하지 못했습니다. 잠시 후 다시 시도해 주세요.');
    }

    return [
        'ok'       => true,
        'redirect' => '/trade/trade_messages.php?tab=closed',
    ];
}

/**
 * 대화종료 채팅 — 내 목록에서만 숨김 (DB 방·메시지는 유지)
 *
 * @return array{ok: bool, error?: string, redirect?: string, message?: string}
 */
function trade_chat_hide_closed_room(int $room_idx, int $tr_idx, int $my_mb): array
{
    $fail = static function (string $error): array {
        return ['ok' => false, 'error' => $error];
    };

    if ($room_idx < 1 || $tr_idx < 1 || $my_mb < 1) {
        return $fail('잘못된 요청입니다.');
    }
    if (!db_table_exists('tb_trade_room')) {
        return $fail('채팅 DB가 설치되지 않았습니다.');
    }
    if (!trade_chat_room_hidden_columns_ready()) {
        return $fail('삭제 기능을 준비하지 못했습니다. 잠시 후 다시 시도해 주세요.');
    }

    $hide_sel = ', r.room_buyer_hidden_at, r.room_seller_hidden_at';
    $room = db_assoc(db_query("
        SELECT r.room_idx, r.tr_idx, r.buyer_mb_idx, r.room_buyer_archived_at, r.room_seller_archived_at
               {$hide_sel},
               t.mb_idx AS seller_mb_idx, t.tr_status
        FROM tb_trade_room r
        INNER JOIN tb_trade t ON t.tr_idx = r.tr_idx
        WHERE r.room_idx = {$room_idx} AND r.tr_idx = {$tr_idx}
        LIMIT 1
    "));
    if (!$room) {
        return $fail('대화방을 찾을 수 없습니다.');
    }

    $seller_mb = (int) ($room['seller_mb_idx'] ?? 0);
    $buyer_mb  = (int) ($room['buyer_mb_idx'] ?? 0);
    if ($my_mb !== $seller_mb && $my_mb !== $buyer_mb) {
        return $fail('이 대화방에 접근할 수 없습니다.');
    }

    if (trade_chat_is_room_hidden_for_member($room, $my_mb)) {
        return [
            'ok'       => true,
            'message'  => '이미 목록에서 삭제된 대화입니다.',
            'redirect' => '/trade/trade_messages.php?tab=closed',
        ];
    }
    if (!trade_chat_is_room_archived_for_member($room, $my_mb)) {
        return $fail('대화종료한 채팅만 삭제할 수 있습니다.');
    }

    $set = $my_mb === $seller_mb
        ? 'room_seller_hidden_at = NOW()'
        : 'room_buyer_hidden_at = NOW()';

    if (!db_query("UPDATE tb_trade_room SET {$set} WHERE room_idx = {$room_idx} LIMIT 1")) {
        return $fail('대화를 삭제하지 못했습니다. 잠시 후 다시 시도해 주세요.');
    }

    return [
        'ok'       => true,
        'message'  => '목록에서 삭제했습니다.',
        'redirect' => '/trade/trade_messages.php?tab=closed',
    ];
}

/** 상대방이 보낸·아직 읽지 않은 거래 메시지 개수 (거래 진행 중 글만) */
function trade_chat_unread_count_for_member(int $mb_idx): int
{
    if ($mb_idx < 1 || !trade_chat_room_has_read_columns() || !db_table_exists('tb_trade_room_msg')) {
        return 0;
    }
    $mb_idx = (int) $mb_idx;
    $buyer_archive = trade_chat_room_archive_columns_ready()
        ? ' AND r.room_buyer_archived_at IS NULL'
        : '';
    $seller_archive = trade_chat_room_archive_columns_ready()
        ? ' AND r.room_seller_archived_at IS NULL'
        : '';
    if (trade_chat_room_hidden_columns_ready()) {
        $buyer_archive .= ' AND r.room_buyer_hidden_at IS NULL';
        $seller_archive .= ' AND r.room_seller_hidden_at IS NULL';
    }
    $as_buyer = (int) db_result("
        SELECT COUNT(*) FROM tb_trade_room_msg m
        INNER JOIN tb_trade_room r ON r.room_idx = m.room_idx
        INNER JOIN tb_trade t ON t.tr_idx = r.tr_idx AND t.tr_status = 1
        WHERE r.buyer_mb_idx = {$mb_idx}
          {$buyer_archive}
          AND m.mb_idx <> {$mb_idx}
          AND m.msg_idx > r.room_buyer_read_msg_idx
    ");
    $as_seller = (int) db_result("
        SELECT COUNT(*) FROM tb_trade_room_msg m
        INNER JOIN tb_trade_room r ON r.room_idx = m.room_idx
        INNER JOIN tb_trade t ON t.tr_idx = r.tr_idx AND t.tr_status = 1 AND t.mb_idx = {$mb_idx}
        WHERE 1=1{$seller_archive}
          AND m.mb_idx <> {$mb_idx}
          AND m.msg_idx > r.room_seller_read_msg_idx
    ");

    return $as_buyer + $as_seller;
}

/** tb_trade_room_msg 결제 연동 컬럼(msg_type, msg_pay_idx) 적용 여부 */
function trade_chat_msg_has_payment_columns(): bool
{
    static $c = null;
    if ($c !== null) {
        return $c;
    }
    if (!db_table_exists('tb_trade_room_msg')) {
        $c = false;

        return false;
    }
    $has_type = (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_trade_room_msg LIKE 'msg_type'"));
    $has_pay  = (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_trade_room_msg LIKE 'msg_pay_idx'"));
    if ($has_type && $has_pay) {
        $c = true;

        return true;
    }

    global $conn, $db_admin_database;
    $schema = isset($db_admin_database) ? trim((string) $db_admin_database) : '';
    if ($schema === '') {
        $row = db_assoc(db_query('SELECT DATABASE() AS db'));
        $schema = trim((string) ($row['db'] ?? ''));
    }
    if ($schema !== '') {
        $esc_schema = db_escape($schema);
        $cnt = (int) db_result("
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = '{$esc_schema}'
              AND TABLE_NAME = 'tb_trade_room_msg'
              AND COLUMN_NAME IN ('msg_type', 'msg_pay_idx')
        ");
        $c = ($cnt >= 2);

        return $c;
    }

    $c = false;

    return false;
}

/** 결제 모듈(_trade_payment.php) 로드 — 채팅 API 등에서 필요 시에만 호출 */
function trade_chat_payment_lib_fail_reason(): string
{
    return (string) ($GLOBALS['_trade_payment_lib_fail'] ?? '');
}

/**
 * 서버에 올라간 _trade_payment.php 내용 점검 (require 전)
 *
 * @return array{build: string, md5: string, webhook_sig: string, webhook_sig_line: int, issue: string}
 */
function trade_chat_payment_lib_file_info(string $path): array
{
    $info = [
        'build'            => '',
        'md5'              => '',
        'webhook_sig'      => '',
        'webhook_sig_line' => 0,
        'issue'            => '',
    ];

    if (!is_readable($path)) {
        $info['issue'] = 'trade/lib/_trade_payment.php 파일을 읽을 수 없습니다.';

        return $info;
    }

    $content = (string) @file_get_contents($path);
    if ($content === '') {
        $info['issue'] = 'trade/lib/_trade_payment.php 파일이 비어 있습니다. 전체 파일을 다시 업로드해 주세요.';

        return $info;
    }

    $info['md5'] = md5($content);
    if (preg_match('/BUILD:\s*(\S+)/', $content, $m)) {
        $info['build'] = (string) $m[1];
    }

    if (preg_match('/function\s+trade_payment_confirm_from_bank_webhook\s*\(([^)]*)\)/', $content, $m, PREG_OFFSET_CAPTURE)) {
        $info['webhook_sig']      = 'function trade_payment_confirm_from_bank_webhook(' . trim((string) $m[1][0]) . ')';
        $info['webhook_sig_line'] = substr_count(substr($content, 0, (int) $m[0][1]), "\n") + 1;
    } else {
        $info['issue'] = 'trade/lib/_trade_payment.php 에 trade_payment_confirm_from_bank_webhook 함수가 없습니다. 최신 파일 전체를 업로드해 주세요.';

        return $info;
    }

    if (preg_match('/function\s+trade_payment_confirm_from_bank_webhook\s*\([^)]*string\s+\$bank_msg/', $content)) {
        $info['issue'] = '서버 trade/lib/_trade_payment.php 가 구버전입니다 (PHP 7.4: string $bank_msg). '
            . '로컬 최신 trade/lib/_trade_payment.php (BUILD: 20260624l-php74) 를 FTP로 덮어쓰세요. '
            . '현재: ' . $info['webhook_sig'];

        return $info;
    }

    if (preg_match('/function\s+trade_payment_confirm_from_bank_webhook\s*\(\s*string\s+depositor_name\b/', $content)) {
        $info['issue'] = 'trade/lib/_trade_payment.php 시그니처 오류 ($depositor_name 앞 $ 누락). 최신 파일을 다시 업로드해 주세요.';

        return $info;
    }

    if (preg_match('/function\s+trade_payment_confirm_from_bank_webhook\s*\([^)]*,\s*string\s+\$depositor_name/', $content)) {
        $info['issue'] = '서버 trade/lib/_trade_payment.php 가 구버전입니다 (타입 있는 매개변수가 타입 없는 매개변수 뒤에 옴). '
            . '최신 trade/lib/_trade_payment.php (BUILD: 20260624l-php74) 를 업로드하세요. '
            . '현재: ' . $info['webhook_sig'];

        return $info;
    }

    if ($info['build'] !== '' && preg_match('/^20260624[a-z]?-php74$/', $info['build']) !== 1) {
        error_log('trade_chat_payment_lib_file_info: unexpected BUILD ' . $info['build'] . ' — ' . $path);
    }

    return $info;
}

function trade_chat_payment_lib_load(): bool
{
    static $loaded = false;
    static $ok     = false;
    if ($loaded) {
        return $ok;
    }
    $loaded = true;
    $GLOBALS['_trade_payment_lib_fail'] = '';

    if (function_exists('trade_payment_bank_info')) {
        $ok = true;
        return true;
    }

    $path = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'trade' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . '_trade_payment.php';
    if (!is_file($path)) {
        $GLOBALS['_trade_payment_lib_fail'] = 'trade/lib/_trade_payment.php 파일이 서버에 없습니다. FTP로 업로드해 주세요.';
        error_log('trade_chat_payment_lib_load: file missing — ' . $path);

        return false;
    }

    $size = @filesize($path);
    if ($size !== false && (int) $size < 500) {
        $GLOBALS['_trade_payment_lib_fail'] = 'trade/lib/_trade_payment.php 파일이 비어 있거나 손상되었습니다. 전체 파일을 다시 업로드해 주세요.';
        error_log('trade_chat_payment_lib_load: file too small — ' . $path . ' (' . (int) $size . ' bytes)');

        return false;
    }

    $inspect = trade_chat_payment_lib_file_info($path);
    if (($inspect['issue'] ?? '') !== '') {
        $GLOBALS['_trade_payment_lib_fail'] = (string) $inspect['issue'];
        error_log('trade_chat_payment_lib_load: inspect — ' . $GLOBALS['_trade_payment_lib_fail'] . ' — ' . $path);
        $ok = false;

        return false;
    }

    $load_err = '';
    set_error_handler(static function ($errno, $errstr, $errfile, $errline) use (&$load_err): bool {
        $load_err = $errstr;
        if ($errline > 0) {
            $load_err .= ' (line ' . $errline . ')';
        }
        return true;
    });
    try {
        require_once $path;
    } catch (ParseError $e) {
        $load_err = $e->getMessage() . ' (line ' . $e->getLine() . ')';
    } catch (Throwable $e) {
        $load_err = $e->getMessage();
        if ($e->getLine() > 0) {
            $load_err .= ' (line ' . $e->getLine() . ')';
        }
    }
    restore_error_handler();

    if (!function_exists('trade_payment_bank_info')) {
        if ($load_err !== '') {
            $hint = ' 서버 시그니처: ' . ($inspect['webhook_sig'] ?? '');
            $GLOBALS['_trade_payment_lib_fail'] = '결제 모듈 로드 오류: ' . $load_err . $hint
                . ' — trade/lib/_trade_payment.php (BUILD: 20260624l-php74) 업로드 후 PHP opcache/opcache_reset 또는 php-fpm 재시작.';
        } else {
            $GLOBALS['_trade_payment_lib_fail'] = 'trade/lib/_trade_payment.php 가 구버전이거나 손상되었습니다. 최신 파일 전체를 다시 업로드해 주세요.';
        }
        error_log('trade_chat_payment_lib_load: ' . $GLOBALS['_trade_payment_lib_fail'] . ' — ' . $path);
        $ok = false;

        return false;
    }

    $ok = true;

    return true;
}

/** 결제 DB 테이블·컬럼만 적용됐는지 (마이그레이션 SQL) */
function trade_chat_payment_db_ready(): bool
{
    return db_table_exists('tb_trade_payment')
        && trade_chat_msg_has_payment_columns()
        && trade_chat_payment_table_columns_ok();
}

/**
 * tb_trade_payment 필수 컬럼 존재 여부
 *
 * @return array{ok: bool, missing: string[]}
 */
function trade_chat_payment_table_columns_status(): array
{
    $required = [
        'pay_idx',
        'room_idx',
        'tr_idx',
        'request_msg_idx',
        'seller_mb_idx',
        'buyer_mb_idx',
        'pay_amount',
        'product_label',
        'pay_status',
        'pay_created_at',
    ];
    $missing = [];
    if (!db_table_exists('tb_trade_payment')) {
        return ['ok' => false, 'missing' => $required];
    }
    foreach ($required as $column) {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            continue;
        }
        $hit = db_assoc(db_query("SHOW COLUMNS FROM tb_trade_payment LIKE '{$column}'"));
        if (!$hit) {
            $missing[] = $column;
        }
    }

    return ['ok' => $missing === [], 'missing' => $missing];
}

function trade_chat_payment_table_columns_ok(): bool
{
    return trade_chat_payment_table_columns_status()['ok'];
}

/**
 * 결제 기능 상태 (DB · PHP 모듈)
 *
 * @return array{ready: bool, db_ready: bool, lib_ok: bool, error: string}
 */
function trade_chat_payment_status(): array
{
    $has_table = db_table_exists('tb_trade_payment');
    $has_cols  = trade_chat_msg_has_payment_columns();
    $pay_cols  = trade_chat_payment_table_columns_status();
    $db_ready  = $has_table && $has_cols && !empty($pay_cols['ok']);
    $lib_ok    = trade_chat_payment_lib_load();

    $sync_to_db = function_exists('trade_payment_sync_to_db') && trade_payment_sync_to_db();
    $chat_only  = function_exists('trade_payment_sync_to_db') && !trade_payment_sync_to_db();

    $error = '';
    if (!$has_table && $sync_to_db) {
        $error = '결제 테이블(tb_trade_payment)이 없습니다. sql/migrate_tb_trade_payment.sql 을 적용해 주세요.';
    } elseif (!$has_cols && $sync_to_db) {
        $error = '채팅 메시지 결제 컬럼(msg_type, msg_pay_idx)이 없습니다. sql/migrate_tb_trade_payment.sql 또는 sql/migrate_tb_trade_payment_columns_only.sql 을 적용해 주세요.';
    } elseif (empty($pay_cols['ok']) && $sync_to_db) {
        $missing = implode(', ', (array) ($pay_cols['missing'] ?? []));
        $error = 'tb_trade_payment 컬럼이 부족합니다 (' . $missing . '). sql/migrate_tb_trade_payment.sql 을 적용해 주세요.';
    } elseif (!$lib_ok) {
        $lib_fail = trade_chat_payment_lib_fail_reason();
        $error = $lib_fail !== ''
            ? $lib_fail
            : '결제 모듈을 불러오지 못했습니다. trade/lib/_trade_payment.php 를 서버에 업로드해 주세요.';
    }

    return [
        'ready'      => $chat_only ? $lib_ok : ($db_ready && $lib_ok),
        'db_ready'   => $chat_only ? true : $db_ready,
        'lib_ok'     => $lib_ok,
        'chat_only'  => $chat_only,
        'sync_to_db' => $sync_to_db,
        'error'      => $error,
    ];
}

/** 결제 DB·모듈 사용 가능 여부 (채팅 API용) */
function trade_chat_payment_ready(): bool
{
    return trade_chat_payment_status()['ready'];
}

/** 무통장 입금 안내 (결제 모듈 없이도 채팅 패널에서 사용) */
function trade_chat_bank_label(): string
{
    if (function_exists('trade_payment_bank_info')) {
        $bank = trade_payment_bank_info();

        return (string) ($bank['label'] ?? '');
    }

    return '케이뱅크 100-124-200276 장영관(럭키루트)';
}

/** 거래 문의 첫 메시지용 상품명 (카드명·상자명 우선, 없으면 글 제목) */
function trade_chat_inquiry_product_label(array $trade): string
{
    $label = trim((string) ($trade['tr_card_name'] ?? ''));
    if ($label === '') {
        $label = trim((string) ($trade['tr_title'] ?? ''));
    }

    return $label !== '' ? $label : '상품';
}

/**
 * 구매자 거래 문의 — 방이 없으면 생성 후 "{상품명} 문의드립니다." 첫 메시지 전송
 *
 * @return array{ok: bool, room_idx: int, created: bool, error?: string}
 */
function trade_chat_ensure_buyer_inquiry(int $tr_idx, int $buyer_mb_idx, array $trade, string $buyer_nick = ''): array
{
    $fail = static function (string $error): array {
        return ['ok' => false, 'room_idx' => 0, 'created' => false, 'error' => $error];
    };

    if ($tr_idx < 1 || $buyer_mb_idx < 1) {
        return $fail('잘못된 요청입니다.');
    }
    if (!db_table_exists('tb_trade_room') || !db_table_exists('tb_trade_room_msg')) {
        return $fail('채팅 DB가 설치되지 않았습니다.');
    }

    $seller_mb = (int) ($trade['mb_idx'] ?? 0);
    if ($seller_mb < 1) {
        return $fail('판매자 정보를 찾을 수 없습니다.');
    }
    if ($buyer_mb_idx === $seller_mb) {
        return $fail('본인이 올린 거래글에는 문의할 수 없습니다.');
    }
    if ((int) ($trade['tr_status'] ?? 0) !== 1) {
        return $fail('거래글을 찾을 수 없거나 종료된 글입니다.');
    }

    $exist_open = trade_chat_find_open_buyer_room($tr_idx, $buyer_mb_idx);
    if ($exist_open > 0) {
        return ['ok' => true, 'room_idx' => $exist_open, 'created' => false];
    }

    trade_chat_allow_multiple_buyer_rooms();

    $body = trade_chat_inquiry_product_label($trade) . ' 문의드립니다.';
    if (mb_strlen($body) > 2000) {
        $body = mb_substr($body, 0, 2000);
    }
    $esc_body = db_escape($body);

    global $conn;
    mysqli_begin_transaction($conn);

    try {
        if (!db_query("
            INSERT INTO tb_trade_room (tr_idx, buyer_mb_idx, room_updated_at)
            VALUES ({$tr_idx}, {$buyer_mb_idx}, NOW())
        ")) {
            throw new RuntimeException('문의방을 만들 수 없습니다.');
        }
        $room_idx = (int) db_insert_id();
        if ($room_idx < 1) {
            throw new RuntimeException('문의방을 만들 수 없습니다.');
        }

        if (!db_query("
            INSERT INTO tb_trade_room_msg (room_idx, mb_idx, msg_body, msg_created_at)
            VALUES ({$room_idx}, {$buyer_mb_idx}, '{$esc_body}', NOW())
        ")) {
            throw new RuntimeException('첫 메시지를 보낼 수 없습니다.');
        }

        db_query("UPDATE tb_trade_room SET room_updated_at = NOW() WHERE room_idx = {$room_idx}");

        mysqli_commit($conn);

        if (trade_chat_room_has_read_columns()) {
            trade_chat_mark_room_read($room_idx, $buyer_mb_idx, false);
        }

        $from_nick = trim($buyer_nick);
        if ($from_nick === '') {
            $nick_row = db_assoc(db_query("SELECT mb_nick FROM tb_member WHERE mb_idx = {$buyer_mb_idx} LIMIT 1"));
            $from_nick = trim((string) ($nick_row['mb_nick'] ?? ''));
        }
        if ($from_nick === '') {
            $from_nick = '회원';
        }

        try {
            require_once __DIR__ . '/webpush.php';
            webpush_notify_trade_message($seller_mb, $from_nick, $body, $tr_idx, $room_idx);
        } catch (Throwable $e) {
            error_log('trade_chat_ensure_buyer_inquiry webpush: ' . $e->getMessage());
        }

        return ['ok' => true, 'room_idx' => $room_idx, 'created' => true];
    } catch (Throwable $e) {
        mysqli_rollback($conn);

        return $fail($e->getMessage());
    }
}

//INSERT 후 마지막 ID
function db_insert_id(){
    global $conn;
    return mysqli_insert_id($conn);
}

/* ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
    ┃ 회원 / 세션 관련 함수                                                                                                                                ┃
    ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛*/

//로그인 여부
function is_login(){
    return !empty($_SESSION['mb_idx']);
}

//현재 로그인 회원 정보 (세션 기반)
function login_member(){
    if (!is_login()) return null;
    return [
        'mb_idx'   => $_SESSION['mb_idx']   ?? 0,
        'mb_id'    => $_SESSION['mb_id']    ?? '',
        'mb_nick'  => $_SESSION['mb_nick']  ?? '',
        'mb_level' => $_SESSION['mb_level'] ?? 1,
    ];
}

/** 백오피스(관리자 테이블) 로그인 여부 */
function is_admin_login(): bool
{
    $ad = $GLOBALS['_PZ_ADMIN'] ?? null;

    return is_array($ad) && (int) ($ad['ad_idx'] ?? 0) > 0;
}

/** 관리자 세션 (tb_admin 기준 · 회원 세션과 분리) */
function login_admin(): ?array
{
    if (!is_admin_login()) {
        return null;
    }
    $ad = pz_admin_session_snapshot();

    return [
        'ad_idx'   => (int) $ad['ad_idx'],
        'ad_id'    => (string) $ad['ad_id'],
        'ad_name'  => (string) $ad['ad_name'],
        'ad_level' => (int) $ad['ad_level'],
    ];
}

/** tb_notice에 no_ad_idx 컬럼(마이그레이션) 적용 여부 */
function notice_table_has_no_ad_idx(): bool
{
    static $c = null;
    if ($c !== null) {
        return $c;
    }
    if (!db_table_exists('tb_notice')) {
        $c = false;

        return false;
    }
    $c = (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_notice LIKE 'no_ad_idx'"));

    return $c;
}

/**
 * 공지 목록/상세에 표시할 작성자명 (회원 닉 · 백오피스 이름 · 기본)
 *
 * @param array $r tb_notice + (선택) mb_nick, mb_id, ad_name, ad_id
 */
function notice_row_author(array $r): string
{
    $n = trim((string) ($r['mb_nick'] ?? ''));
    if ($n !== '') {
        return $n;
    }
    $a = trim((string) ($r['ad_name'] ?? ''));
    if ($a !== '') {
        return $a;
    }
    $ad_id = trim((string) ($r['ad_id'] ?? ''));
    if ($ad_id !== '') {
        return $ad_id;
    }
    if (!empty($r['mb_idx'])) {
        return '#' . (int) $r['mb_idx'];
    }
    if (!empty($r['no_ad_idx'])) {
        return '운영자';
    }

    return '관리자';
}

/**
 * (레거시 DB) no_ad_idx 없을 때만 — 공지 insert용 mb_idx. no_ad_idx 마이그레이션 후에는 사용 안 함.
 * @return int 0이면 없음
 */
function notice_author_mb_idx_for_admin(): int
{
    $ad = login_admin();
    if (!$ad) {
        return 0;
    }
    $ad_id = db_escape($ad['ad_id']);
    $r     = db_assoc(db_query("SELECT mb_idx FROM tb_member WHERE mb_id = '{$ad_id}' AND mb_status = 1 LIMIT 1"));
    if ($r) {
        return (int) $r['mb_idx'];
    }
    $r2 = db_assoc(db_query("SELECT mb_idx FROM tb_member WHERE mb_level >= 9 AND mb_status = 1 ORDER BY mb_idx ASC LIMIT 1"));

    return $r2 ? (int) $r2['mb_idx'] : 0;
}

/** 휴대폰 번호에서 숫자만 추출 (중복 비교·저장용) */
function mb_phone_digits(string $s): string
{
    return preg_replace('/\D/u', '', $s);
}

/**
 * 한국 휴대폰(010 11자리 / 011·016·017·018·019 10자리 등)을 하이픈 표기로 정규화. 유효하지 않으면 null
 */
function mb_phone_normalize_kr(string $raw): ?string
{
    $d = mb_phone_digits($raw);
    if (preg_match('/^01[016789]\d{8}$/', $d)) {
        return substr($d, 0, 3) . '-' . substr($d, 3, 4) . '-' . substr($d, 7, 4);
    }
    if (preg_match('/^01[016789]\d{7}$/', $d)) {
        return substr($d, 0, 3) . '-' . substr($d, 3, 3) . '-' . substr($d, 6, 4);
    }
    return null;
}

//접속 IP
function get_client_ip(){
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    }
    return trim($ip);
}

//간단한 alert + 이동 (폼 결과 응답용)
function alert_goto($msg, $url = ''){
    header('Content-Type: text/html; charset=UTF-8');
    $msg = str_replace(["\r\n","\n","\r"], ' ', $msg);
    $msg = addslashes($msg);
    echo '<script>';
    if ($msg !== '') echo 'alert("'.$msg.'");';
    if ($url !== '') {
        echo 'location.href="'.$url.'";';
    } else {
        echo 'history.back();';
    }
    echo '</script>';
    exit;
}

/** 일일 출석 체크 시 지급 포인트 */
function attendance_reward_point(): int
{
    return 20;
}

/** 커뮤니티 신규 글 등록 시 지급 포인트 */
function point_reward_community_new_post(): int
{
    return 10;
}

/** 커뮤니티 글 등록 포인트 — 하루 최대 지급 횟수 */
function point_reward_community_new_post_daily_limit(): int
{
    return 5;
}

/** 오늘(KST) 커뮤니티 글 등록으로 지급된 포인트 횟수 */
function point_reward_community_new_post_today_count(int $mb_idx): int
{
    $mb_idx = max(0, $mb_idx);
    if ($mb_idx < 1 || !db_table_exists('tb_point_log')) {
        return 0;
    }
    $today = db_escape(date('Y-m-d'));

    return (int) db_result("
        SELECT COUNT(*) FROM tb_point_log
        WHERE mb_idx = {$mb_idx}
          AND pl_type = 'community_post'
          AND pl_change > 0
          AND pl_created_at >= '{$today} 00:00:00'
          AND pl_created_at < DATE_ADD('{$today}', INTERVAL 1 DAY)
    ");
}

/**
 * 커뮤니티 글 등록 보상 시도 (하루 한도 초과 시 미지급)
 * @return bool 포인트 지급 성공 여부
 */
function point_reward_try_community_new_post(int $mb_idx): bool
{
    $amount = point_reward_community_new_post();
    $limit  = point_reward_community_new_post_daily_limit();
    if ($mb_idx < 1 || $amount <= 0 || $limit < 1) {
        return false;
    }
    if (point_reward_community_new_post_today_count($mb_idx) >= $limit) {
        return false;
    }

    return point_reward_add($mb_idx, $amount, 'community_post', '커뮤니티 글 등록 보상');
}

/** 거래 게시판 신규 글 등록 시 지급 포인트 */
function point_reward_trade_new_post(): int
{
    return 15;
}

/** 회원가입 완료 시 지급 포인트 */
function point_reward_signup(): int
{
    return 500;
}

/**
 * 포인트 적립 + 내역 기록 (tb_point_log 필요)
 * @return bool 성공 여부 (실패해도 글 등록 등 본 작업은 유지하는 용도)
 */
function point_reward_add(int $mb_idx, int $amount, string $type, string $memo): bool
{
    if ($mb_idx <= 0 || $amount <= 0) {
        return false;
    }
    if (strlen($type) > 20) {
        $type = substr($type, 0, 20);
    }

    global $conn;

    mysqli_begin_transaction($conn);

    if (!db_query("UPDATE tb_member SET mb_point = mb_point + {$amount} WHERE mb_idx = {$mb_idx} LIMIT 1")
        || mysqli_affected_rows($conn) !== 1) {
        mysqli_rollback($conn);
        return false;
    }

    $bal      = (int) db_result("SELECT mb_point FROM tb_member WHERE mb_idx = {$mb_idx} LIMIT 1");
    $esc_type = db_escape($type);
    $esc_memo = db_escape($memo);
    $sql      = "
        INSERT INTO tb_point_log (mb_idx, pl_change, pl_balance, pl_type, pl_memo)
        VALUES ({$mb_idx}, {$amount}, {$bal}, '{$esc_type}', '{$esc_memo}')
    ";
    if (!db_query($sql)) {
        mysqli_rollback($conn);
        return false;
    }

    mysqli_commit($conn);
    return true;
}

/**
 * 포인트 증감 + 내역 기록 (차감 허용, 잔액 0 미만 불가)
 */
function point_change_with_log(int $mb_idx, int $delta, string $type, string $memo): bool
{
    if ($mb_idx <= 0 || $delta === 0) {
        return false;
    }
    if (!db_table_exists('tb_point_log')) {
        return false;
    }
    if (strlen($type) > 20) {
        $type = substr($type, 0, 20);
    }

    global $conn;

    mysqli_begin_transaction($conn);

    $sql = "
        UPDATE tb_member
        SET mb_point = mb_point + ({$delta})
        WHERE mb_idx = {$mb_idx}
          AND mb_point + ({$delta}) >= 0
        LIMIT 1
    ";
    if (!db_query($sql) || mysqli_affected_rows($conn) !== 1) {
        mysqli_rollback($conn);
        return false;
    }

    $bal      = (int) db_result("SELECT mb_point FROM tb_member WHERE mb_idx = {$mb_idx} LIMIT 1");
    $esc_type = db_escape($type);
    $esc_memo = db_escape($memo);
    $ins      = "
        INSERT INTO tb_point_log (mb_idx, pl_change, pl_balance, pl_type, pl_memo)
        VALUES ({$mb_idx}, {$delta}, {$bal}, '{$esc_type}', '{$esc_memo}')
    ";
    if (!db_query($ins)) {
        mysqli_rollback($conn);
        return false;
    }

    mysqli_commit($conn);
    return true;
}

/** @return int|false */
function community_co_likes_count(int $co_idx)
{
    if (!db_table_exists('tb_community_like')) {
        return false;
    }
    return (int) db_result("SELECT COUNT(*) FROM tb_community_like WHERE co_idx = {$co_idx}");
}

/** @return int|false */
function community_co_comments_count(int $co_idx)
{
    if (!db_table_exists('tb_community_comment')) {
        return false;
    }
    return (int) db_result("SELECT COUNT(*) FROM tb_community_comment WHERE co_idx = {$co_idx} AND cc_status = 1");
}

function community_sync_counts(int $co_idx): void
{
    $likes = community_co_likes_count($co_idx);
    if ($likes !== false) {
        db_query("UPDATE tb_community SET co_likes = {$likes} WHERE co_idx = {$co_idx} LIMIT 1");
    }
    $comments = community_co_comments_count($co_idx);
    if ($comments !== false) {
        db_query("UPDATE tb_community SET co_comments = {$comments} WHERE co_idx = {$co_idx} LIMIT 1");
    }
}

/** @return int|false */
function trade_tr_likes_count(int $tr_idx)
{
    if (!db_table_exists('tb_trade_like')) {
        return false;
    }
    return (int) db_result("SELECT COUNT(*) FROM tb_trade_like WHERE tr_idx = {$tr_idx}");
}

function trade_sync_likes_count(int $tr_idx): void
{
    $likes = trade_tr_likes_count($tr_idx);
    if ($likes !== false) {
        db_query("UPDATE tb_trade SET tr_likes = {$likes} WHERE tr_idx = {$tr_idx} LIMIT 1");
    }
}

/**
 * @param int[] $tr_idxs
 * @return array<int, true>
 */
function trade_user_wished_map(array $tr_idxs, int $mb_idx): array
{
    if (!db_table_exists('tb_trade_like') || $mb_idx < 1 || empty($tr_idxs)) {
        return [];
    }
    $ids = array_values(array_unique(array_filter(array_map('intval', $tr_idxs), static function ($id) {
        return $id > 0;
    })));
    if (empty($ids)) {
        return [];
    }
    $in = implode(',', $ids);
    $map = [];
    $rs = db_query("SELECT tr_idx FROM tb_trade_like WHERE mb_idx = {$mb_idx} AND tr_idx IN ({$in})");
    while ($row = db_assoc($rs)) {
        $map[(int) $row['tr_idx']] = true;
    }
    return $map;
}

/**
 * 비슷한 거래글 검색용 키워드 (공백·구분자 분리, 긴 토큰 우선)
 *
 * @return string[]
 */
function trade_similar_match_terms(string $card_name, string $title = ''): array
{
    $terms = [];
    foreach ([trim($card_name), trim($title)] as $src) {
        if ($src === '') {
            continue;
        }
        if (mb_strlen($src, 'UTF-8') >= 2) {
            $terms[] = $src;
        }
        $parts = preg_split('/[\s\/\-·・,，.+#]+/u', $src, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($parts)) {
            continue;
        }
        foreach ($parts as $part) {
            $part = trim($part);
            if (mb_strlen($part, 'UTF-8') >= 2) {
                $terms[] = $part;
            }
        }
    }

    $terms = array_values(array_unique($terms));
    usort($terms, static function ($a, $b) {
        return mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8');
    });

    return $terms;
}

/**
 * 비슷한 거래글 WHERE 절 (카드명·제목 토큰 + 역방향 포함 매칭)
 */
function trade_similar_match_where_sql(string $card_name, string $title, string $item_type, int $exclude_idx): string
{
    $card_esc = db_escape($card_name);
    $item_type_esc = db_escape($item_type);
    $conds = ["t.tr_card_name = '{$card_esc}'"];

    $conds[] = "(CHAR_LENGTH(t.tr_card_name) >= 2 AND '{$card_esc}' LIKE CONCAT('%', t.tr_card_name, '%'))";
    $conds[] = "(CHAR_LENGTH(t.tr_title) >= 2 AND '{$card_esc}' LIKE CONCAT('%', t.tr_title, '%'))";

    $like_parts = [];
    foreach (trade_similar_match_terms($card_name, $title) as $term) {
        $term_esc = db_escape($term);
        $like_parts[] = "(t.tr_card_name LIKE '%{$term_esc}%' OR t.tr_title LIKE '%{$term_esc}%')";
    }
    if (!empty($like_parts)) {
        $conds[] = '(t.tr_item_type = \'' . $item_type_esc . '\' AND (' . implode(' OR ', $like_parts) . '))';
    }

    return 't.tr_status = 1 AND t.tr_idx != ' . (int) $exclude_idx . ' AND (' . implode(' OR ', $conds) . ')';
}

/** @see site_info.php — $pz_nav_show_card_price */
function nav_show_card_price(): bool
{
    global $pz_nav_show_card_price;

    return isset($pz_nav_show_card_price) && $pz_nav_show_card_price === true;
}

/** @see site_info.php — $pz_nav_show_market_price */
function nav_show_market_price(): bool
{
    global $pz_nav_show_market_price;

    return isset($pz_nav_show_market_price) && $pz_nav_show_market_price === true;
}

/**
 * 거래글 진행 상태 뱃지 텍스트.
 * 상태 1은 아직 입금 전 모집(판매중/구매중/교환중),
 * 상태 2는 입금완료 후 진행 중(거래중), 상태 3은 거래완료.
 */
function trade_deal_status_label(int $deal_status, string $tr_type = 'sell'): string
{
    if ($deal_status === 2) {
        return '거래중';
    }
    if ($deal_status === 3) {
        return '거래완료';
    }

    switch ($tr_type) {
        case 'buy':      return '구매중';
        case 'exchange': return '교환중';
        default:         return '판매중';
    }
}

require_once dirname(__DIR__) . '/trade/lib/_trade_hold.php';
require_once __DIR__ . '/_member_login_block.php';

require_once __DIR__ . '/site_setting.php';
require_once __DIR__ . '/_site_presence.php';
site_presence_maybe_ping();
member_login_block_enforce_session();