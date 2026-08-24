<?php
/**
 * 회원 로그인(접속) 제한 — 1/3/7/14/30일
 */

/**
 * @return int[]
 */
function member_login_block_day_options(): array
{
    return [1, 3, 7, 14, 30];
}

function member_login_block_columns_ready(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    if (!db_table_exists('tb_member')) {
        $ready = false;

        return false;
    }

    $has_until  = (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_member LIKE 'mb_login_block_until'"));
    $has_days   = (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_member LIKE 'mb_login_block_days'"));
    $has_reason = (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_member LIKE 'mb_login_block_reason'"));
    $has_at     = (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_member LIKE 'mb_login_block_at'"));
    $has_ad     = (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_member LIKE 'mb_login_block_ad_idx'"));

    if (!$has_until) {
        db_query("
            ALTER TABLE `tb_member`
            ADD COLUMN `mb_login_block_until` DATETIME NULL DEFAULT NULL
            COMMENT '로그인 제한 해제 시각' AFTER `mb_status`
        ");
        $has_until = (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_member LIKE 'mb_login_block_until'"));
    }
    if (!$has_days) {
        $after = $has_until ? 'mb_login_block_until' : 'mb_status';
        db_query("
            ALTER TABLE `tb_member`
            ADD COLUMN `mb_login_block_days` TINYINT UNSIGNED NULL DEFAULT NULL
            COMMENT '로그인 제한 일수' AFTER `{$after}`
        ");
        $has_days = (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_member LIKE 'mb_login_block_days'"));
    }
    if (!$has_reason) {
        $after = $has_days ? 'mb_login_block_days' : ($has_until ? 'mb_login_block_until' : 'mb_status');
        db_query("
            ALTER TABLE `tb_member`
            ADD COLUMN `mb_login_block_reason` VARCHAR(500) NULL DEFAULT NULL
            COMMENT '로그인 제한 사유' AFTER `{$after}`
        ");
        $has_reason = (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_member LIKE 'mb_login_block_reason'"));
    }
    if (!$has_at) {
        $after = $has_reason ? 'mb_login_block_reason' : ($has_days ? 'mb_login_block_days' : ($has_until ? 'mb_login_block_until' : 'mb_status'));
        db_query("
            ALTER TABLE `tb_member`
            ADD COLUMN `mb_login_block_at` DATETIME NULL DEFAULT NULL
            COMMENT '로그인 제한 적용 시각' AFTER `{$after}`
        ");
        $has_at = (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_member LIKE 'mb_login_block_at'"));
    }
    if (!$has_ad) {
        $after = $has_at ? 'mb_login_block_at' : ($has_reason ? 'mb_login_block_reason' : ($has_days ? 'mb_login_block_days' : ($has_until ? 'mb_login_block_until' : 'mb_status')));
        db_query("
            ALTER TABLE `tb_member`
            ADD COLUMN `mb_login_block_ad_idx` INT UNSIGNED NULL DEFAULT NULL
            COMMENT '로그인 제한 처리 관리자' AFTER `{$after}`
        ");
        $has_ad = (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_member LIKE 'mb_login_block_ad_idx'"));
    }

    $ready = $has_until && $has_days && $has_reason && $has_at && $has_ad;

    return $ready;
}

/**
 * @return array{blocked: bool, until: ?string, days: int, reason: string, blocked_at: ?string}
 */
function member_login_block_info(int $mb_idx): array
{
    $empty = [
        'blocked'    => false,
        'until'      => null,
        'days'       => 0,
        'reason'     => '',
        'blocked_at' => null,
    ];
    if (!member_login_block_columns_ready() || $mb_idx < 1) {
        return $empty;
    }

    $row = db_assoc(db_query("
        SELECT mb_login_block_until, mb_login_block_days, mb_login_block_reason, mb_login_block_at
        FROM tb_member
        WHERE mb_idx = {$mb_idx}
        LIMIT 1
    "));
    if (!$row) {
        return $empty;
    }

    $until = trim((string) ($row['mb_login_block_until'] ?? ''));
    $ts = $until !== '' ? strtotime($until) : false;
    $blocked = $ts !== false && $ts > time();

    return [
        'blocked'    => $blocked,
        'until'      => $blocked ? $until : null,
        'days'       => max(0, (int) ($row['mb_login_block_days'] ?? 0)),
        'reason'     => trim((string) ($row['mb_login_block_reason'] ?? '')),
        'blocked_at' => !empty($row['mb_login_block_at']) ? (string) $row['mb_login_block_at'] : null,
    ];
}

function member_login_is_blocked(int $mb_idx): bool
{
    return member_login_block_info($mb_idx)['blocked'];
}

/**
 * @param array{blocked?: bool, until?: ?string, days?: int, reason?: string} $info
 */
function member_login_block_user_message(array $info): string
{
    $reason = trim((string) ($info['reason'] ?? ''));
    if ($reason === '') {
        $reason = '운영정책 위반';
    }
    $until = trim((string) ($info['until'] ?? ''));
    $until_lbl = '';
    if ($until !== '') {
        $ts = strtotime($until);
        if ($ts !== false) {
            $until_lbl = date('Y-m-d H:i', $ts);
        }
    }
    $days = (int) ($info['days'] ?? 0);
    $msg = '접속이 제한된 계정입니다. 사유: ' . $reason;
    if ($until_lbl !== '') {
        $msg .= ' (' . $until_lbl . '까지';
        if ($days > 0) {
            $msg .= ', ' . $days . '일';
        }
        $msg .= ')';
    }
    $msg .= ' 기간이 끝난 뒤 다시 로그인해 주세요.';

    return $msg;
}

function member_login_block_clear_session(): void
{
    unset($_SESSION['mb_idx'], $_SESSION['mb_id'], $_SESSION['mb_nick'], $_SESSION['mb_level']);
}

/**
 * 로그인된 회원이 접속 제한이면 세션을 끊고 경고 후 로그인 페이지로 보냄.
 */
function member_login_block_enforce_session(): void
{
    if (defined('PZ_API_JSON') && PZ_API_JSON) {
        if (function_exists('is_login') && is_login()) {
            $mb_idx = (int) ($_SESSION['mb_idx'] ?? 0);
            if ($mb_idx > 0 && member_login_is_blocked($mb_idx)) {
                member_login_block_clear_session();
            }
        }

        return;
    }
    if (PHP_SAPI === 'cli') {
        return;
    }
    if (!function_exists('is_login') || !is_login()) {
        return;
    }
    $mb_idx = (int) ($_SESSION['mb_idx'] ?? 0);
    if ($mb_idx < 1) {
        return;
    }
    $info = member_login_block_info($mb_idx);
    if (empty($info['blocked'])) {
        return;
    }
    $msg = member_login_block_user_message($info);
    $_SESSION['pz_login_block_notice'] = $msg;
    member_login_block_clear_session();
    alert_goto($msg, '/login.php');
}

/**
 * @return array{ok: bool, error?: string, until?: string}
 */
function member_login_block_apply(int $mb_idx, int $days, string $reason, int $ad_idx = 0): array
{
    if (!member_login_block_columns_ready()) {
        return ['ok' => false, 'error' => '로그인 제한 기능 DB가 없습니다. sql/migrate_tb_member_login_block.sql 을 적용해 주세요.'];
    }
    if ($mb_idx < 1) {
        return ['ok' => false, 'error' => '잘못된 요청입니다.'];
    }
    if (!in_array($days, member_login_block_day_options(), true)) {
        return ['ok' => false, 'error' => '제한 기간을 선택해 주세요. (1일, 3일, 7일, 14일, 30일)'];
    }
    $reason = trim($reason);
    if ($reason === '') {
        return ['ok' => false, 'error' => '접속 제한 사유를 입력해 주세요.'];
    }
    if (mb_strlen($reason, 'UTF-8') > 400) {
        $reason = mb_substr($reason, 0, 400, 'UTF-8');
    }

    $row = db_assoc(db_query("
        SELECT mb_idx, mb_level, mb_status
        FROM tb_member
        WHERE mb_idx = {$mb_idx}
        LIMIT 1
    "));
    if (!$row) {
        return ['ok' => false, 'error' => '회원을 찾을 수 없습니다.'];
    }
    if ((int) ($row['mb_level'] ?? 0) >= 9) {
        return ['ok' => false, 'error' => '관리자 계정에는 로그인 제한을 적용할 수 없습니다.'];
    }
    if ((int) ($row['mb_status'] ?? 0) === 3) {
        return ['ok' => false, 'error' => '탈퇴한 계정입니다.'];
    }

    $esc_reason = db_escape($reason);
    $ad_sql = $ad_idx > 0 ? (string) $ad_idx : 'NULL';
    $ok = db_query("
        UPDATE tb_member SET
            mb_login_block_until = DATE_ADD(NOW(), INTERVAL {$days} DAY),
            mb_login_block_days = {$days},
            mb_login_block_reason = '{$esc_reason}',
            mb_login_block_at = NOW(),
            mb_login_block_ad_idx = {$ad_sql},
            mb_updated_at = NOW()
        WHERE mb_idx = {$mb_idx}
        LIMIT 1
    ");
    if (!$ok) {
        return ['ok' => false, 'error' => '로그인 제한 처리에 실패했습니다.'];
    }

    $until = (string) db_result("SELECT mb_login_block_until FROM tb_member WHERE mb_idx = {$mb_idx} LIMIT 1");

    return ['ok' => true, 'until' => $until];
}

/**
 * @return array{ok: bool, error?: string}
 */
function member_login_block_lift(int $mb_idx): array
{
    if (!member_login_block_columns_ready()) {
        return ['ok' => false, 'error' => '로그인 제한 기능 DB가 없습니다. sql/migrate_tb_member_login_block.sql 을 적용해 주세요.'];
    }
    if ($mb_idx < 1) {
        return ['ok' => false, 'error' => '잘못된 요청입니다.'];
    }

    $ok = db_query("
        UPDATE tb_member SET
            mb_login_block_until = NULL,
            mb_login_block_days = NULL,
            mb_login_block_reason = NULL,
            mb_login_block_at = NULL,
            mb_login_block_ad_idx = NULL,
            mb_updated_at = NOW()
        WHERE mb_idx = {$mb_idx}
        LIMIT 1
    ");
    if (!$ok) {
        return ['ok' => false, 'error' => '로그인 제한 해제에 실패했습니다.'];
    }

    return ['ok' => true];
}
