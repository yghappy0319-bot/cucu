<?php
/**
 * 회원 휴대폰 변경 SMS 인증
 */
declare(strict_types=1);

require_once __DIR__ . '/_directsend.php';
require_once __DIR__ . '/_member_contact_history.php';

const MEMBER_PHONE_VERIFY_DAILY_LIMIT = 3;
const MEMBER_PHONE_VERIFY_CODE_TTL    = 180;
const MEMBER_PHONE_VERIFY_SESSION_KEY = 'pz_member_phone_verify';

function member_phone_verify_log_table_ready(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $ready = db_table_exists('tb_member_phone_verify_log');

    return $ready;
}

function member_phone_verify_send_count_today(int $mb_idx): int
{
    if ($mb_idx < 1 || !member_phone_verify_log_table_ready()) {
        return 0;
    }
    $cnt = db_result(
        "SELECT COUNT(*) FROM tb_member_phone_verify_log
         WHERE mb_idx = {$mb_idx}
           AND mpv_created_at >= CURDATE()
           AND mpv_created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)"
    );

    return (int) $cnt;
}

function member_phone_verify_remaining_today(int $mb_idx): int
{
    return max(0, MEMBER_PHONE_VERIFY_DAILY_LIMIT - member_phone_verify_send_count_today($mb_idx));
}

function member_phone_verify_log_send(int $mb_idx, string $phone_digits, bool $success): void
{
    if ($mb_idx < 1 || !member_phone_verify_log_table_ready()) {
        return;
    }
    $esc_phone = db_escape($phone_digits);
    $ok        = $success ? 1 : 0;
    $ip        = db_escape(get_client_ip());
    db_query(
        "INSERT INTO tb_member_phone_verify_log (mb_idx, mpv_phone, mpv_success, mpv_ip, mpv_created_at)
         VALUES ({$mb_idx}, '{$esc_phone}', {$ok}, '{$ip}', NOW())"
    );
}

function member_phone_verify_generate_code(): string
{
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function member_phone_verify_store(int $mb_idx, string $phone, string $phone_digits, string $code): void
{
    $_SESSION[MEMBER_PHONE_VERIFY_SESSION_KEY] = [
        'mb_idx'       => $mb_idx,
        'phone'        => $phone,
        'phone_digits' => $phone_digits,
        'code_hash'    => password_hash($code, PASSWORD_DEFAULT),
        'expires_at'   => time() + MEMBER_PHONE_VERIFY_CODE_TTL,
        'sent_at'      => time(),
    ];
}

/** @return array{mb_idx:int,phone:string,phone_digits:string,code_hash:string,expires_at:int,sent_at:int}|null */
function member_phone_verify_get(): ?array
{
    $row = $_SESSION[MEMBER_PHONE_VERIFY_SESSION_KEY] ?? null;
    if (!is_array($row)) {
        return null;
    }
    $mb_idx = (int) ($row['mb_idx'] ?? 0);
    if ($mb_idx < 1) {
        return null;
    }

    return [
        'mb_idx'       => $mb_idx,
        'phone'        => (string) ($row['phone'] ?? ''),
        'phone_digits' => (string) ($row['phone_digits'] ?? ''),
        'code_hash'    => (string) ($row['code_hash'] ?? ''),
        'expires_at'   => (int) ($row['expires_at'] ?? 0),
        'sent_at'      => (int) ($row['sent_at'] ?? 0),
    ];
}

function member_phone_verify_clear(): void
{
    unset($_SESSION[MEMBER_PHONE_VERIFY_SESSION_KEY]);
}

/**
 * @return array{ok:bool,error:?string,phone:?string,expires_in:?int,remaining:?int}
 */
function member_phone_verify_prepare_send(int $mb_idx, string $phone_raw): array
{
    if (!directsend_is_ready()) {
        return ['ok' => false, 'error' => 'SMS 인증이 설정되지 않았습니다. 관리자에게 문의해 주세요.', 'phone' => null, 'expires_in' => null, 'remaining' => null];
    }
    if (!member_phone_verify_log_table_ready()) {
        return ['ok' => false, 'error' => '인증 기능 DB가 준비되지 않았습니다. sql/tb_member_phone_verify_log.sql 을 적용해 주세요.', 'phone' => null, 'expires_in' => null, 'remaining' => null];
    }

    $remaining = member_phone_verify_remaining_today($mb_idx);
    if ($remaining < 1) {
        return ['ok' => false, 'error' => '오늘 인증번호 발송은 하루 ' . MEMBER_PHONE_VERIFY_DAILY_LIMIT . '회까지만 가능합니다.', 'phone' => null, 'expires_in' => null, 'remaining' => 0];
    }

    $phone = mb_phone_normalize_kr(trim($phone_raw));
    if ($phone === null) {
        return ['ok' => false, 'error' => '휴대폰 번호를 올바르게 입력해 주세요. (예: 010-1234-5678)', 'phone' => null, 'expires_in' => null, 'remaining' => $remaining];
    }
    $phone_digits = mb_phone_digits($phone);

    $rs = db_query("SELECT mb_phone FROM tb_member WHERE mb_idx = {$mb_idx} LIMIT 1");
    $cur = db_assoc($rs);
    if ($cur && member_contact_history_phone_key((string) ($cur['mb_phone'] ?? '')) === $phone_digits) {
        return ['ok' => false, 'error' => '현재 등록된 번호와 동일합니다.', 'phone' => $phone, 'expires_in' => null, 'remaining' => $remaining];
    }

    $esc_digits = db_escape($phone_digits);
    $dup_phone  = (int) db_result(
        "SELECT COUNT(*) FROM tb_member
         WHERE mb_idx <> {$mb_idx}
           AND IFNULL(`mb_phone`, '') <> ''
           AND REPLACE(REPLACE(REPLACE(`mb_phone`, '-', ''), ' ', ''), '.', '') = '{$esc_digits}'"
    );
    if ($dup_phone > 0) {
        return ['ok' => false, 'error' => '다른 회원이 이미 사용 중인 휴대폰 번호입니다.', 'phone' => $phone, 'expires_in' => null, 'remaining' => $remaining];
    }

    $code = member_phone_verify_generate_code();
    $send = directsend_send_auth_sms($phone_digits, $code);
    member_phone_verify_log_send($mb_idx, $phone_digits, $send['ok']);

    if (!$send['ok']) {
        return ['ok' => false, 'error' => $send['error'] ?? 'SMS 발송에 실패했습니다.', 'phone' => $phone, 'expires_in' => null, 'remaining' => $remaining - 1];
    }

    member_phone_verify_store($mb_idx, $phone, $phone_digits, $code);

    return [
        'ok'         => true,
        'error'      => null,
        'phone'      => $phone,
        'expires_in' => MEMBER_PHONE_VERIFY_CODE_TTL,
        'remaining'  => $remaining - 1,
    ];
}

/**
 * @return array{ok:bool,error:?string}
 */
function member_phone_verify_check_code(int $mb_idx, string $phone_raw, string $code_raw): array
{
    $code = preg_replace('/\D/u', '', trim($code_raw));
    if (!preg_match('/^\d{6}$/', $code)) {
        return ['ok' => false, 'error' => '6자리 인증번호를 입력해 주세요.'];
    }

    $phone = mb_phone_normalize_kr(trim($phone_raw));
    if ($phone === null) {
        return ['ok' => false, 'error' => '휴대폰 번호를 올바르게 입력해 주세요.'];
    }
    $phone_digits = mb_phone_digits($phone);

    $sess = member_phone_verify_get();
    if ($sess === null || $sess['mb_idx'] !== $mb_idx) {
        return ['ok' => false, 'error' => '인증번호를 먼저 요청해 주세요.'];
    }
    if ($sess['phone_digits'] !== $phone_digits) {
        return ['ok' => false, 'error' => '인증 요청한 번호와 일치하지 않습니다.'];
    }
    if (time() > $sess['expires_at']) {
        member_phone_verify_clear();

        return ['ok' => false, 'error' => '인증번호가 만료되었습니다. 다시 요청해 주세요.'];
    }
    if (!password_verify($code, $sess['code_hash'])) {
        return ['ok' => false, 'error' => '인증번호가 일치하지 않습니다.'];
    }

    return ['ok' => true, 'error' => null];
}

/**
 * @return array{ok:bool,error:?string,phone:?string}
 */
function member_phone_verify_apply_phone(int $mb_idx, string $phone): array
{
    $rs = db_query("SELECT mb_idx, mb_status, mb_phone FROM tb_member WHERE mb_idx = {$mb_idx} LIMIT 1");
    $row = db_assoc($rs);
    if (!$row) {
        return ['ok' => false, 'error' => '회원 정보를 찾을 수 없습니다.', 'phone' => null];
    }
    if ((int) $row['mb_status'] !== 1) {
        return ['ok' => false, 'error' => '로그인할 수 없는 계정입니다.', 'phone' => null];
    }

    $phone_norm = mb_phone_normalize_kr($phone);
    if ($phone_norm === null) {
        return ['ok' => false, 'error' => '휴대폰 번호가 올바르지 않습니다.', 'phone' => null];
    }

    $phone_digits = mb_phone_digits($phone_norm);
    $esc_digits   = db_escape($phone_digits);
    $dup_phone    = (int) db_result(
        "SELECT COUNT(*) FROM tb_member
         WHERE mb_idx <> {$mb_idx}
           AND IFNULL(`mb_phone`, '') <> ''
           AND REPLACE(REPLACE(REPLACE(`mb_phone`, '-', ''), ' ', ''), '.', '') = '{$esc_digits}'"
    );
    if ($dup_phone > 0) {
        return ['ok' => false, 'error' => '다른 회원이 이미 사용 중인 휴대폰 번호입니다.', 'phone' => null];
    }

    $esc_phone = db_escape($phone_norm);
    $ok        = db_query("
        UPDATE tb_member SET
            mb_phone = '{$esc_phone}',
            mb_updated_at = NOW()
        WHERE mb_idx = {$mb_idx} AND mb_status = 1
        LIMIT 1
    ");
    if (!$ok) {
        return ['ok' => false, 'error' => '저장 중 오류가 발생했습니다.', 'phone' => null];
    }

    $old_phone = isset($row['mb_phone']) ? (string) $row['mb_phone'] : null;
    if (
        member_contact_history_table_ready()
        && !member_contact_history_log_phone(
            $mb_idx,
            $old_phone,
            $phone_norm,
            member_contact_history_actor_member($mb_idx)
        )
    ) {
        error_log("member_contact_history: phone verify log failed mb_idx={$mb_idx}");
    }

    member_phone_verify_clear();

    return ['ok' => true, 'error' => null, 'phone' => $phone_norm];
}
