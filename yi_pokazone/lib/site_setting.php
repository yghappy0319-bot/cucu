<?php

function site_setting_table_ready(): bool
{
    return db_table_exists('tb_site_setting');
}

function site_setting_get(string $key, string $default = ''): string
{
    if (!site_setting_table_ready() || $key === '') {
        return $default;
    }
    $k   = db_escape($key);
    $row = db_assoc(db_query("SELECT sk_value FROM tb_site_setting WHERE sk_key = '{$k}' LIMIT 1"));
    if (!$row || $row['sk_value'] === null) {
        return $default;
    }
    return (string) $row['sk_value'];
}

function site_setting_set(string $key, string $value): bool
{
    if (!site_setting_table_ready() || $key === '') {
        return false;
    }
    $k = db_escape($key);
    $v = db_escape($value);
    $q = "
        INSERT INTO tb_site_setting (sk_key, sk_value)
        VALUES ('{$k}', '{$v}')
        ON DUPLICATE KEY UPDATE sk_value = '{$v}', sk_updated_at = CURRENT_TIMESTAMP
    ";
    return (bool) db_query($q);
}

/**
 * 거래게시판 플랫폼 수수료 (%)
 */
function platform_fee_trade_percent(): int
{
    $raw = site_setting_get('trade_platform_fee_percent', '5');
    if ($raw === '' || !is_numeric($raw)) {
        return 5;
    }
    return max(0, min(100, (int) $raw));
}

/**
 * 경매 플랫폼 수수료 (%)
 */
function platform_fee_auction_percent(): int
{
    $raw = site_setting_get('auction_platform_fee_percent', '5');
    if ($raw === '' || !is_numeric($raw)) {
        return 5;
    }
    return max(0, min(100, (int) $raw));
}
