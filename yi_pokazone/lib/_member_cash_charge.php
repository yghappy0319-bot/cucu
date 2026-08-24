<?php

require_once __DIR__ . '/_member_cash.php';

if (!defined('MEMBER_CASH_CHARGE_STATUS_PENDING')) {
    define('MEMBER_CASH_CHARGE_STATUS_PENDING', 0);
}
if (!defined('MEMBER_CASH_CHARGE_STATUS_DONE')) {
    define('MEMBER_CASH_CHARGE_STATUS_DONE', 1);
}
if (!defined('MEMBER_CASH_CHARGE_STATUS_CANCELLED')) {
    define('MEMBER_CASH_CHARGE_STATUS_CANCELLED', 2);
}
if (!defined('MEMBER_CASH_CHARGE_DEPOSITOR_MAX')) {
    define('MEMBER_CASH_CHARGE_DEPOSITOR_MAX', 50);
}
if (!defined('MEMBER_CASH_CHARGE_PENDING_MAX')) {
    define('MEMBER_CASH_CHARGE_PENDING_MAX', 5);
}

/** @return int[] */
function member_cash_charge_amounts(): array
{
    return [10000, 30000, 50000, 100000, 300000, 500000, 1000000];
}

function member_cash_charge_table_ready(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $ready = db_table_exists('tb_cash_charge');

    return $ready;
}

/** @return array<int, string> */
function member_cash_charge_status_labels(): array
{
    return [
        MEMBER_CASH_CHARGE_STATUS_PENDING   => '입금대기',
        MEMBER_CASH_CHARGE_STATUS_DONE      => '충전완료',
        MEMBER_CASH_CHARGE_STATUS_CANCELLED => '취소',
    ];
}

function member_cash_charge_status_label(int $status): string
{
    $labels = member_cash_charge_status_labels();

    return $labels[$status] ?? '알 수 없음';
}

function member_cash_charge_normalize_name(string $name): string
{
    $name = trim($name);
    $name = preg_replace('/\s+/u', '', $name) ?? $name;

    return mb_strtolower($name);
}

function member_cash_charge_names_match(string $stored, string $incoming): bool
{
    $a = member_cash_charge_normalize_name($stored);
    $b = member_cash_charge_normalize_name($incoming);
    if ($a === '' || $b === '') {
        return false;
    }
    if ($a === $b) {
        return true;
    }

    return mb_strpos($a, $b) !== false || mb_strpos($b, $a) !== false;
}

/**
 * @return array{ok: bool, error?: string, name?: string}
 */
function member_cash_charge_parse_depositor(string $raw): array
{
    $name = trim($raw);
    if ($name === '') {
        return ['ok' => false, 'error' => '입금자명을 입력해 주세요.'];
    }
    if (mb_strlen($name) > MEMBER_CASH_CHARGE_DEPOSITOR_MAX) {
        return ['ok' => false, 'error' => '입금자명은 ' . MEMBER_CASH_CHARGE_DEPOSITOR_MAX . '자 이내로 입력해 주세요.'];
    }

    return ['ok' => true, 'name' => $name];
}

/**
 * 무통장 캐시 충전 신청
 *
 * @return array{ok: bool, error?: string, cc_idx?: int}
 */
function member_cash_charge_apply_bank(int $mb_idx, int $amount, string $depositor): array
{
    $fail = static function (string $error): array {
        return ['ok' => false, 'error' => $error];
    };

    if ($mb_idx < 1) {
        return $fail('로그인이 필요합니다.');
    }
    if (!member_cash_column_ready()) {
        return $fail('캐시 기능이 준비되지 않았습니다.');
    }
    if (!member_cash_charge_table_ready()) {
        return $fail('캐시 충전 기능이 준비되지 않았습니다. sql/migrate_tb_cash_charge.sql 을 적용해 주세요.');
    }
    if (!in_array($amount, member_cash_charge_amounts(), true)) {
        return $fail('충전 금액을 선택해 주세요.');
    }

    $parsed = member_cash_charge_parse_depositor($depositor);
    if (!$parsed['ok']) {
        return $fail((string) ($parsed['error'] ?? '입금자명을 확인해 주세요.'));
    }
    $name = (string) $parsed['name'];

    $st_pending = MEMBER_CASH_CHARGE_STATUS_PENDING;
    $pending_cnt = (int) db_result("
        SELECT COUNT(*) FROM tb_cash_charge
        WHERE mb_idx = {$mb_idx} AND cc_status = {$st_pending}
    ");
    if ($pending_cnt >= MEMBER_CASH_CHARGE_PENDING_MAX) {
        return $fail('입금 대기 중인 충전 신청이 너무 많습니다. 입금 확인 후 다시 신청해 주세요.');
    }

    $dup = (int) db_result("
        SELECT COUNT(*) FROM tb_cash_charge
        WHERE mb_idx = {$mb_idx}
          AND cc_status = {$st_pending}
          AND cc_amount = {$amount}
          AND cc_depositor = '" . db_escape($name) . "'
    ");
    if ($dup > 0) {
        return $fail('동일한 금액·입금자명의 충전 신청이 이미 대기 중입니다.');
    }

    $esc_name = db_escape($name);
    $ok = db_query("
        INSERT INTO tb_cash_charge (mb_idx, cc_amount, cc_method, cc_depositor, cc_status)
        VALUES ({$mb_idx}, {$amount}, 'bank', '{$esc_name}', {$st_pending})
    ");
    if (!$ok) {
        return $fail('충전 신청에 실패했습니다. 잠시 후 다시 시도해 주세요.');
    }

    global $conn;
    $cc_idx = (int) mysqli_insert_id($conn);
    if ($cc_idx < 1) {
        return $fail('충전 신청에 실패했습니다.');
    }

    return ['ok' => true, 'cc_idx' => $cc_idx];
}

/**
 * @return list<array<string, mixed>>
 */
function member_cash_charge_list_for_member(int $mb_idx, int $limit = 20): array
{
    if (!member_cash_charge_table_ready() || $mb_idx < 1) {
        return [];
    }
    $limit = max(1, min(100, $limit));
    $rows  = [];
    $rs    = db_query("
        SELECT *
        FROM tb_cash_charge
        WHERE mb_idx = {$mb_idx}
        ORDER BY cc_idx DESC
        LIMIT {$limit}
    ");
    while ($r = db_assoc($rs)) {
        $rows[] = $r;
    }

    return $rows;
}

/**
 * 본인 충전 신청 상태 조회 (폴링용)
 *
 * @param list<int> $cc_idxs
 * @return array{ok: bool, error?: string, cash_balance?: int, items?: list<array{cc_idx: int, cc_amount: int, cc_depositor: string, cc_status: int, status_label: string}>}
 */
function member_cash_charge_status_for_member(int $mb_idx, array $cc_idxs = []): array
{
    $fail = static function (string $error): array {
        return ['ok' => false, 'error' => $error];
    };

    if ($mb_idx < 1) {
        return $fail('로그인이 필요합니다.');
    }
    if (!member_cash_charge_table_ready()) {
        return $fail('캐시 충전 기능이 준비되지 않았습니다.');
    }

    $ids = [];
    foreach ($cc_idxs as $id) {
        $id = (int) $id;
        if ($id > 0) {
            $ids[$id] = true;
        }
    }
    $id_list = array_keys($ids);

    $st_pending = MEMBER_CASH_CHARGE_STATUS_PENDING;
    $labels     = member_cash_charge_status_labels();
    $items      = [];

    if ($id_list) {
        $in = implode(',', $id_list);
        $rs = db_query("
            SELECT cc_idx, cc_amount, cc_depositor, cc_status
            FROM tb_cash_charge
            WHERE mb_idx = {$mb_idx} AND cc_idx IN ({$in})
            ORDER BY cc_idx DESC
        ");
    } else {
        $rs = db_query("
            SELECT cc_idx, cc_amount, cc_depositor, cc_status
            FROM tb_cash_charge
            WHERE mb_idx = {$mb_idx} AND cc_status = {$st_pending}
            ORDER BY cc_idx DESC
            LIMIT 20
        ");
    }

    while ($r = db_assoc($rs)) {
        $st = (int) ($r['cc_status'] ?? 0);
        $items[] = [
            'cc_idx'       => (int) ($r['cc_idx'] ?? 0),
            'cc_amount'    => (int) ($r['cc_amount'] ?? 0),
            'cc_depositor' => (string) ($r['cc_depositor'] ?? ''),
            'cc_status'    => $st,
            'status_label' => $labels[$st] ?? '알 수 없음',
        ];
    }

    return [
        'ok'           => true,
        'cash_balance' => member_cash_balance($mb_idx),
        'items'        => $items,
    ];
}

/**
 * 입금대기 중인 본인 충전 신청 삭제
 *
 * @return array{ok: bool, error?: string}
 */
function member_cash_charge_delete_pending(int $mb_idx, int $cc_idx): array
{
    $fail = static function (string $error): array {
        return ['ok' => false, 'error' => $error];
    };

    if ($mb_idx < 1) {
        return $fail('로그인이 필요합니다.');
    }
    if ($cc_idx < 1) {
        return $fail('잘못된 요청입니다.');
    }
    if (!member_cash_charge_table_ready()) {
        return $fail('캐시 충전 기능이 준비되지 않았습니다.');
    }

    $st_pending = MEMBER_CASH_CHARGE_STATUS_PENDING;
    $row = db_assoc(db_query("
        SELECT cc_idx, mb_idx, cc_status
        FROM tb_cash_charge
        WHERE cc_idx = {$cc_idx} AND mb_idx = {$mb_idx}
        LIMIT 1
    "));
    if (!$row) {
        return $fail('충전 신청 내역을 찾을 수 없습니다.');
    }
    if ((int) ($row['cc_status'] ?? -1) !== $st_pending) {
        return $fail('입금대기 중인 신청만 삭제할 수 있습니다.');
    }

    $ok = db_query("
        DELETE FROM tb_cash_charge
        WHERE cc_idx = {$cc_idx}
          AND mb_idx = {$mb_idx}
          AND cc_status = {$st_pending}
        LIMIT 1
    ");
    global $conn;
    if (!$ok || mysqli_affected_rows($conn) !== 1) {
        return $fail('신청 삭제에 실패했습니다. 잠시 후 다시 시도해 주세요.');
    }

    return ['ok' => true];
}

/**
 * 은행 입금 알림과 대기 중인 캐시 충전 신청 매칭 후 충전 완료
 *
 * @return array{ok: bool, error?: string, cc_idx?: int, message?: string, already?: bool}
 */
function member_cash_charge_confirm_from_bank(string $depositor_name, $amount_raw, string $bank_msg = ''): array
{
    $fail = static function (string $error): array {
        return ['ok' => false, 'error' => $error];
    };

    if (!member_cash_charge_table_ready() || !member_cash_column_ready()) {
        return $fail('캐시 충전 기능이 준비되지 않았습니다.');
    }

    $name = trim($depositor_name);
    if ($name === '') {
        return $fail('입금자명이 없습니다.');
    }

    $digits = preg_replace('/\D+/', '', (string) $amount_raw);
    if ($digits === '' || $digits === null) {
        return $fail('입금 금액이 올바르지 않습니다.');
    }
    $amount = (int) $digits;
    if ($amount < 1) {
        return $fail('입금 금액이 올바르지 않습니다.');
    }

    $st_pending = MEMBER_CASH_CHARGE_STATUS_PENDING;
    $st_done    = MEMBER_CASH_CHARGE_STATUS_DONE;
    $q = db_query("
        SELECT *
        FROM tb_cash_charge
        WHERE cc_status IN ({$st_pending}, {$st_done})
          AND cc_amount = {$amount}
          AND cc_method = 'bank'
        ORDER BY
            CASE WHEN cc_status = {$st_pending} THEN 0 ELSE 1 END,
            cc_created_at ASC,
            cc_idx ASC
        LIMIT 20
    ");

    $matched = null;
    while ($row = db_assoc($q)) {
        if (member_cash_charge_names_match((string) ($row['cc_depositor'] ?? ''), $name)) {
            $matched = $row;
            break;
        }
    }

    if (!$matched) {
        return $fail('일치하는 캐시 충전 신청을 찾을 수 없습니다.');
    }

    $cc_idx = (int) ($matched['cc_idx'] ?? 0);
    $mb_idx = (int) ($matched['mb_idx'] ?? 0);
    if ($cc_idx < 1 || $mb_idx < 1) {
        return $fail('충전 신청 정보를 찾을 수 없습니다.');
    }

    if ((int) ($matched['cc_status'] ?? 0) === MEMBER_CASH_CHARGE_STATUS_DONE) {
        return [
            'ok'      => true,
            'cc_idx'  => $cc_idx,
            'message' => '캐시 충전이 이미 완료되었습니다.',
            'already' => true,
        ];
    }

    $memo = '무통장 캐시 충전 ₩' . number_format($amount)
        . ' (입금자: ' . $matched['cc_depositor'] . ')';
    if (trim($bank_msg) !== '') {
        $memo .= ' · ' . mb_substr(trim($bank_msg), 0, 80);
    }

    global $conn;
    if (!mysqli_begin_transaction($conn)) {
        return $fail('충전 처리에 실패했습니다.');
    }

    $locked = db_assoc(db_query("
        SELECT * FROM tb_cash_charge
        WHERE cc_idx = {$cc_idx} AND cc_status = {$st_pending}
        LIMIT 1 FOR UPDATE
    "));
    if (!$locked) {
        mysqli_rollback($conn);
        $row = db_assoc(db_query("SELECT * FROM tb_cash_charge WHERE cc_idx = {$cc_idx} LIMIT 1"));
        if ($row && (int) ($row['cc_status'] ?? 0) === MEMBER_CASH_CHARGE_STATUS_DONE) {
            return [
                'ok'      => true,
                'cc_idx'  => $cc_idx,
                'message' => '캐시 충전이 이미 완료되었습니다.',
                'already' => true,
            ];
        }

        return $fail('충전 처리에 실패했습니다.');
    }

    $ok = db_query("
        UPDATE tb_member SET mb_cash = mb_cash + {$amount}
        WHERE mb_idx = {$mb_idx} LIMIT 1
    ");
    if (!$ok || mysqli_affected_rows($conn) !== 1) {
        mysqli_rollback($conn);

        return $fail('캐시 적립에 실패했습니다.');
    }

    $bal = (int) db_result("SELECT mb_cash FROM tb_member WHERE mb_idx = {$mb_idx} LIMIT 1");
    if (member_cash_log_table_ready()) {
        if (!member_cash_log_insert($mb_idx, $amount, $bal, 'cash_charge', $memo, null, null)) {
            mysqli_rollback($conn);

            return $fail('캐시 내역 기록에 실패했습니다.');
        }
    }

    $esc_memo = db_escape(mb_substr(trim($bank_msg), 0, 200));
    $ok = db_query("
        UPDATE tb_cash_charge
        SET cc_status = {$st_done},
            cc_balance_after = {$bal},
            cc_confirmed_at = NOW(),
            cc_memo = IF('{$esc_memo}' = '', cc_memo, '{$esc_memo}')
        WHERE cc_idx = {$cc_idx} AND cc_status = {$st_pending}
        LIMIT 1
    ");
    if (!$ok || mysqli_affected_rows($conn) !== 1) {
        mysqli_rollback($conn);

        return $fail('충전 상태 갱신에 실패했습니다.');
    }

    mysqli_commit($conn);

    return [
        'ok'      => true,
        'cc_idx'  => $cc_idx,
        'message' => '캐시 충전이 완료되었습니다.',
        'already' => false,
    ];
}
