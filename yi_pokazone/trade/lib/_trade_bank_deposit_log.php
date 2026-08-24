<?php
/**
 * 무통장 입금 알림(은행·자동화 POST) 수신 로그
 */

function trade_bank_deposit_log_table_ready(): bool
{
    return db_table_exists('tb_trade_bank_deposit_log');
}

/** @return array<string, string> */
function trade_bank_deposit_log_status_labels(): array
{
    return [
        'confirmed' => '입금 확인',
        'already'   => '이미 확인됨',
        'unmatched' => '미매칭',
        'error'     => '오류',
    ];
}

function trade_bank_deposit_log_status_label(string $status): string
{
    $map = trade_bank_deposit_log_status_labels();

    return $map[$status] ?? $status;
}

/**
 * @param array{
 *   depositor?: string,
 *   amount?: int,
 *   bank_msg?: string,
 *   status?: string,
 *   pay_idx?: int,
 *   result_msg?: string,
 *   expected_depositor?: string
 * } $data
 */
function trade_bank_deposit_log_insert(array $data): void
{
    if (!trade_bank_deposit_log_table_ready()) {
        return;
    }

    $depositor = mb_substr(trim((string) ($data['depositor'] ?? '')), 0, 80);
    $amount    = max(0, (int) ($data['amount'] ?? 0));
    $bank_msg  = mb_substr(trim((string) ($data['bank_msg'] ?? '')), 0, 500);
    $status    = trim((string) ($data['status'] ?? 'error'));
    $labels    = trade_bank_deposit_log_status_labels();
    if (!isset($labels[$status])) {
        $status = 'error';
    }
    $pay_idx   = max(0, (int) ($data['pay_idx'] ?? 0));
    $result    = mb_substr(trim((string) ($data['result_msg'] ?? '')), 0, 255);
    $expected  = mb_substr(trim((string) ($data['expected_depositor'] ?? '')), 0, 80);

    $pay_sql = $pay_idx > 0 ? (string) $pay_idx : 'NULL';

    db_query("
        INSERT INTO tb_trade_bank_deposit_log
            (bdl_depositor, bdl_amount, bdl_bank_msg, pay_idx, bdl_status, bdl_result_msg, bdl_expected_depositor)
        VALUES
            ('" . db_escape($depositor) . "', {$amount}, '" . db_escape($bank_msg) . "', {$pay_sql},
             '" . db_escape($status) . "', '" . db_escape($result) . "', '" . db_escape($expected) . "')
    ");
}

/**
 * 웹훅 처리 결과를 로그에 기록
 *
 * @param array<string, mixed> $result trade_payment_confirm_from_bank_webhook 반환값
 */
function trade_bank_deposit_log_from_webhook(
    string $depositor,
    int $amount,
    string $bank_msg,
    array $result,
    string $expected_depositor = ''
): void {
    $ok = !empty($result['ok']);
    if ($ok && !empty($result['already'])) {
        $status = 'already';
    } elseif ($ok) {
        $status = 'confirmed';
    } elseif (($result['log_status'] ?? '') === 'unmatched') {
        $status = 'unmatched';
    } else {
        $status = 'error';
    }

    trade_bank_deposit_log_insert([
        'depositor'          => $depositor,
        'amount'             => $amount,
        'bank_msg'           => $bank_msg,
        'status'             => $status,
        'pay_idx'            => (int) ($result['pay_idx'] ?? 0),
        'result_msg'         => (string) ($result['message'] ?? $result['error'] ?? ''),
        'expected_depositor' => $expected_depositor,
    ]);
}
