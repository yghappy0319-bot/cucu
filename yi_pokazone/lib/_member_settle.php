<?php

const MEMBER_SETTLE_BANK_MAX    = 30;
const MEMBER_SETTLE_HOLDER_MAX  = 50;
const MEMBER_SETTLE_ACCOUNT_MAX = 20;

/** @return string[] */
function member_settle_bank_presets(): array
{
    return [
        'KB국민은행',
        '신한은행',
        '우리은행',
        '하나은행',
        'NH농협은행',
        'IBK기업은행',
        '카카오뱅크',
        '토스뱅크',
        '케이뱅크',
        'SC제일은행',
        '씨티은행',
        '새마을금고',
        '신협',
        '우체국',
        '수협은행',
        '부산은행',
        '경남은행',
        '대구은행',
        '광주은행',
        '전북은행',
        '제주은행',
    ];
}

function member_settle_account_column_ready(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    if (!db_table_exists('tb_member')) {
        $ready = false;

        return false;
    }
    $ready = (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_member LIKE 'mb_settle_bank'"));

    return $ready;
}

function member_settle_account_digits(string $raw): string
{
    return preg_replace('/\D/', '', $raw) ?? '';
}

/**
 * @return array{ok: bool, error?: string, bank?: string, holder?: string, account?: string}
 */
function member_settle_account_parse_input(array $input): array
{
    $bank = trim((string) ($input['mb_settle_bank'] ?? ''));
    $holder = trim((string) ($input['mb_settle_holder'] ?? ''));
    $account = member_settle_account_digits((string) ($input['mb_settle_account'] ?? ''));

    if ($bank === '' || $holder === '' || $account === '') {
        return ['ok' => false, 'error' => '은행명, 예금주, 계좌번호를 모두 입력해 주세요.'];
    }
    if (mb_strlen($bank) > MEMBER_SETTLE_BANK_MAX) {
        return ['ok' => false, 'error' => '은행명이 너무 깁니다.'];
    }
    if (mb_strlen($holder) > MEMBER_SETTLE_HOLDER_MAX) {
        return ['ok' => false, 'error' => '예금주명이 너무 깁니다.'];
    }
    if (strlen($account) < 10 || strlen($account) > MEMBER_SETTLE_ACCOUNT_MAX) {
        return ['ok' => false, 'error' => '계좌번호는 10~' . MEMBER_SETTLE_ACCOUNT_MAX . '자리 숫자로 입력해 주세요.'];
    }

    return [
        'ok'      => true,
        'bank'    => $bank,
        'holder'  => $holder,
        'account' => $account,
    ];
}

function member_settle_account_get(int $mb_idx): ?array
{
    if (!member_settle_account_column_ready() || $mb_idx < 1) {
        return null;
    }

    $row = db_assoc(db_query("
        SELECT mb_settle_bank, mb_settle_holder, mb_settle_account, mb_settle_updated_at
        FROM tb_member
        WHERE mb_idx = {$mb_idx}
        LIMIT 1
    "));
    if (!$row) {
        return null;
    }

    return $row;
}

function member_settle_account_is_registered(?array $row): bool
{
    if (!$row) {
        return false;
    }

    return trim((string) ($row['mb_settle_bank'] ?? '')) !== ''
        && trim((string) ($row['mb_settle_holder'] ?? '')) !== ''
        && trim((string) ($row['mb_settle_account'] ?? '')) !== '';
}

function member_settle_account_mask(string $account): string
{
    $digits = member_settle_account_digits($account);
    $len    = strlen($digits);
    if ($len < 5) {
        return $digits !== '' ? '****' : '—';
    }

    return str_repeat('*', max(4, $len - 4)) . substr($digits, -4);
}

/**
 * @return array{ok: bool, error?: string}
 */
function member_settle_account_save(int $mb_idx, array $input): array
{
    if (!member_settle_account_column_ready()) {
        return ['ok' => false, 'error' => '정산계좌 기능 DB가 없습니다. sql/migrate_tb_member_settle_account.sql 을 적용해 주세요.'];
    }
    if ($mb_idx < 1) {
        return ['ok' => false, 'error' => '잘못된 요청입니다.'];
    }

    $parsed = member_settle_account_parse_input($input);
    if (!$parsed['ok']) {
        return $parsed;
    }

    $esc_bank    = db_escape($parsed['bank']);
    $esc_holder  = db_escape($parsed['holder']);
    $esc_account = db_escape($parsed['account']);

    $ok = db_query("
        UPDATE tb_member SET
            mb_settle_bank = '{$esc_bank}',
            mb_settle_holder = '{$esc_holder}',
            mb_settle_account = '{$esc_account}',
            mb_settle_updated_at = NOW(),
            mb_updated_at = NOW()
        WHERE mb_idx = {$mb_idx} AND mb_status = 1
        LIMIT 1
    ");
    if (!$ok) {
        return ['ok' => false, 'error' => '정산계좌 저장에 실패했습니다.'];
    }

    return ['ok' => true];
}
