<?php
/**
 * 거래글 게시중지 (관리자 제재)
 * tr_status: 1 정상 / 2 게시중지 / 9 삭제(숨김)
 */

if (!defined('TRADE_STATUS_OK')) {
    define('TRADE_STATUS_OK', 1);
}
if (!defined('TRADE_STATUS_HOLD')) {
    define('TRADE_STATUS_HOLD', 2);
}
if (!defined('TRADE_STATUS_DELETED')) {
    define('TRADE_STATUS_DELETED', 9);
}

function trade_hold_columns_ready(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    if (!db_table_exists('tb_trade')) {
        $ready = false;

        return false;
    }

    $has_code   = (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_trade LIKE 'tr_hold_code'"));
    $has_reason = (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_trade LIKE 'tr_hold_reason'"));
    $has_at     = (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_trade LIKE 'tr_hold_at'"));
    $has_ad     = (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_trade LIKE 'tr_hold_ad_idx'"));

    if (!$has_code) {
        db_query("
            ALTER TABLE `tb_trade`
            ADD COLUMN `tr_hold_code` VARCHAR(40) NULL DEFAULT NULL
            COMMENT '게시중지 사유 코드' AFTER `tr_status`
        ");
        $has_code = (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_trade LIKE 'tr_hold_code'"));
    }
    if (!$has_reason) {
        $after = $has_code ? 'tr_hold_code' : 'tr_status';
        db_query("
            ALTER TABLE `tb_trade`
            ADD COLUMN `tr_hold_reason` VARCHAR(500) NULL DEFAULT NULL
            COMMENT '게시중지 사유(표시용)' AFTER `{$after}`
        ");
        $has_reason = (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_trade LIKE 'tr_hold_reason'"));
    }
    if (!$has_at) {
        $after = $has_reason ? 'tr_hold_reason' : ($has_code ? 'tr_hold_code' : 'tr_status');
        db_query("
            ALTER TABLE `tb_trade`
            ADD COLUMN `tr_hold_at` DATETIME NULL DEFAULT NULL
            COMMENT '게시중지 시각' AFTER `{$after}`
        ");
        $has_at = (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_trade LIKE 'tr_hold_at'"));
    }
    if (!$has_ad) {
        $after = $has_at ? 'tr_hold_at' : ($has_reason ? 'tr_hold_reason' : ($has_code ? 'tr_hold_code' : 'tr_status'));
        db_query("
            ALTER TABLE `tb_trade`
            ADD COLUMN `tr_hold_ad_idx` INT UNSIGNED NULL DEFAULT NULL
            COMMENT '게시중지 처리 관리자' AFTER `{$after}`
        ");
        $has_ad = (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_trade LIKE 'tr_hold_ad_idx'"));
    }

    $ready = $has_code && $has_reason && $has_at && $has_ad;

    return $ready;
}

/**
 * @return array<string, string>
 */
function trade_hold_preset_reasons(): array
{
    return [
        'policy'     => '거래 규정 위반',
        'false_info' => '허위·과장 정보',
        'prohibited' => '금지 품목',
        'fraud'      => '사기·분쟁 의심',
        'duplicate'  => '중복 게시',
        'contact'    => '외부 거래·연락처 유도',
        'fake'       => '가품·진위 의심',
        'custom'     => '직접 입력',
    ];
}

function trade_is_held(array $row): bool
{
    return (int) ($row['tr_status'] ?? 0) === TRADE_STATUS_HOLD;
}

function trade_is_deleted_status(array $row): bool
{
    $st = (int) ($row['tr_status'] ?? 0);

    return $st !== TRADE_STATUS_OK && $st !== TRADE_STATUS_HOLD;
}

function trade_hold_reason_text(array $row): string
{
    $reason = trim((string) ($row['tr_hold_reason'] ?? ''));
    if ($reason !== '') {
        return $reason;
    }
    $code = trim((string) ($row['tr_hold_code'] ?? ''));
    $presets = trade_hold_preset_reasons();
    if ($code !== '' && $code !== 'custom' && isset($presets[$code])) {
        return $presets[$code];
    }

    return '운영정책에 따른 게시중지';
}

function trade_status_public_sql(string $alias = 't'): string
{
    $a = preg_replace('/[^a-z_]/', '', $alias);
    $col = ($a !== '' ? "{$a}." : '') . 'tr_status';

    return "{$col} IN (" . TRADE_STATUS_OK . ', ' . TRADE_STATUS_HOLD . ')';
}

function trade_list_item_class(array $row, string $extra = ''): string
{
    $parts = [];
    if ((int) ($row['tr_deal_status'] ?? 0) === 3) {
        $parts[] = 'is-done';
    }
    if (trade_is_held($row)) {
        $parts[] = 'is-held';
    }
    $extra = trim($extra);
    if ($extra !== '') {
        $parts[] = $extra;
    }

    return implode(' ', $parts);
}

function trade_hold_build_reason(string $code, string $custom): string
{
    $presets = trade_hold_preset_reasons();
    $code = array_key_exists($code, $presets) ? $code : 'custom';
    $custom = trim($custom);
    if (mb_strlen($custom, 'UTF-8') > 400) {
        $custom = mb_substr($custom, 0, 400, 'UTF-8');
    }

    if ($code === 'custom') {
        return $custom;
    }
    $label = $presets[$code];
    if ($custom !== '') {
        return $label . ' — ' . $custom;
    }

    return $label;
}
