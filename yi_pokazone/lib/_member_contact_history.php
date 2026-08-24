<?php

const MEMBER_CONTACT_HISTORY_TYPES = ['email', 'phone', 'address'];
const MEMBER_CONTACT_HISTORY_ACTIONS = ['create', 'update', 'delete'];

function member_contact_history_table_ready(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $ready = db_table_exists('tb_member_contact_history');

    return $ready;
}

function member_contact_history_request_meta(): array
{
    $ua = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if (mb_strlen($ua) > 500) {
        $ua = mb_substr($ua, 0, 500);
    }

    return [
        'ip'         => get_client_ip(),
        'user_agent' => $ua,
    ];
}

/**
 * @return array{actor_type:string, actor_mb_idx:?int, actor_ad_idx:?int}
 */
function member_contact_history_actor_member(int $mb_idx): array
{
    return [
        'actor_type'   => 'member',
        'actor_mb_idx' => $mb_idx > 0 ? $mb_idx : null,
        'actor_ad_idx' => null,
    ];
}

/**
 * @return array{actor_type:string, actor_mb_idx:?int, actor_ad_idx:?int}
 */
function member_contact_history_actor_admin(int $ad_idx): array
{
    return [
        'actor_type'   => 'admin',
        'actor_mb_idx' => null,
        'actor_ad_idx' => $ad_idx > 0 ? $ad_idx : null,
    ];
}

function member_contact_history_phone_key(?string $phone): string
{
    if ($phone === null || trim($phone) === '') {
        return '';
    }
    $norm = mb_phone_normalize_kr($phone);

    return $norm !== null ? mb_phone_digits($norm) : mb_phone_digits($phone);
}

function member_contact_history_email_changed(?string $old, ?string $new): bool
{
    return strtolower(trim((string) $old)) !== strtolower(trim((string) $new));
}

function member_contact_history_phone_changed(?string $old, ?string $new): bool
{
    return member_contact_history_phone_key($old) !== member_contact_history_phone_key($new);
}

/**
 * @param array<string, mixed> $row
 */
function member_contact_history_address_snapshot(array $row): string
{
    $snapshot = [
        'addr_label'      => trim((string) ($row['addr_label'] ?? '')),
        'addr_name'       => trim((string) ($row['addr_name'] ?? '')),
        'addr_phone'      => trim((string) ($row['addr_phone'] ?? '')),
        'addr_zip'        => trim((string) ($row['addr_zip'] ?? '')),
        'addr_road'       => trim((string) ($row['addr_road'] ?? '')),
        'addr_jibun'      => trim((string) ($row['addr_jibun'] ?? '')),
        'addr_extra'      => trim((string) ($row['addr_extra'] ?? '')),
        'addr_detail'     => trim((string) ($row['addr_detail'] ?? '')),
        'addr_message'    => trim((string) ($row['addr_message'] ?? '')),
        'addr_is_default' => (int) ($row['addr_is_default'] ?? 0) === 1,
    ];
    $json = json_encode($snapshot, JSON_UNESCAPED_UNICODE);

    return $json !== false ? $json : '{}';
}

function member_contact_history_address_changed(?string $old_json, ?string $new_json): bool
{
    return trim((string) $old_json) !== trim((string) $new_json);
}

function member_contact_history_insert(
    int $mb_idx,
    string $type,
    string $action,
    ?string $old_value,
    ?string $new_value,
    array $actor,
    ?int $addr_idx = null
): bool {
    if (!member_contact_history_table_ready() || $mb_idx < 1) {
        return false;
    }
    if (!in_array($type, MEMBER_CONTACT_HISTORY_TYPES, true)) {
        return false;
    }
    if (!in_array($action, MEMBER_CONTACT_HISTORY_ACTIONS, true)) {
        return false;
    }

    $actor_type = (string) ($actor['actor_type'] ?? 'member');
    if (!in_array($actor_type, ['member', 'admin'], true)) {
        $actor_type = 'member';
    }

    $actor_mb = isset($actor['actor_mb_idx']) && (int) $actor['actor_mb_idx'] > 0
        ? (int) $actor['actor_mb_idx']
        : null;
    $actor_ad = isset($actor['actor_ad_idx']) && (int) $actor['actor_ad_idx'] > 0
        ? (int) $actor['actor_ad_idx']
        : null;

    $meta      = member_contact_history_request_meta();
    $esc_type  = db_escape($type);
    $esc_act   = db_escape($action);
    $esc_actor = db_escape($actor_type);
    $esc_ip    = db_escape($meta['ip']);
    $esc_ua    = db_escape($meta['user_agent']);
    $old_sql   = $old_value !== null ? "'" . db_escape($old_value) . "'" : 'NULL';
    $new_sql   = $new_value !== null ? "'" . db_escape($new_value) . "'" : 'NULL';
    $addr_sql  = $addr_idx !== null && $addr_idx > 0 ? (string) (int) $addr_idx : 'NULL';
    $amb_sql   = $actor_mb !== null ? (string) $actor_mb : 'NULL';
    $aad_sql   = $actor_ad !== null ? (string) $actor_ad : 'NULL';

    return (bool) db_query("
        INSERT INTO tb_member_contact_history
            (mb_idx, mch_type, mch_action, mch_old_value, mch_new_value, addr_idx,
             mch_actor_type, actor_mb_idx, actor_ad_idx, mch_ip, mch_user_agent)
        VALUES
            ({$mb_idx}, '{$esc_type}', '{$esc_act}', {$old_sql}, {$new_sql}, {$addr_sql},
             '{$esc_actor}', {$amb_sql}, {$aad_sql}, '{$esc_ip}', '{$esc_ua}')
    ");
}

function member_contact_history_log_email(
    int $mb_idx,
    ?string $old_email,
    ?string $new_email,
    array $actor
): bool {
    if (!member_contact_history_email_changed($old_email, $new_email)) {
        return true;
    }

    return member_contact_history_insert(
        $mb_idx,
        'email',
        'update',
        $old_email !== null && trim($old_email) !== '' ? trim($old_email) : null,
        $new_email !== null && trim($new_email) !== '' ? trim($new_email) : null,
        $actor
    );
}

function member_contact_history_log_phone(
    int $mb_idx,
    ?string $old_phone,
    ?string $new_phone,
    array $actor
): bool {
    if (!member_contact_history_phone_changed($old_phone, $new_phone)) {
        return true;
    }

    $old_out = trim((string) $old_phone);
    $new_out = trim((string) $new_phone);

    return member_contact_history_insert(
        $mb_idx,
        'phone',
        'update',
        $old_out !== '' ? $old_out : null,
        $new_out !== '' ? $new_out : null,
        $actor
    );
}

/**
 * @param array<string, mixed>|null $old_row
 * @param array<string, mixed>|null $new_row
 */
function member_contact_history_log_address(
    int $mb_idx,
    string $action,
    ?array $old_row,
    ?array $new_row,
    array $actor,
    int $addr_idx = 0
): bool {
    if (!in_array($action, MEMBER_CONTACT_HISTORY_ACTIONS, true)) {
        return false;
    }

    $old_json = $old_row !== null ? member_contact_history_address_snapshot($old_row) : null;
    $new_json = $new_row !== null ? member_contact_history_address_snapshot($new_row) : null;

    if ($action === 'update' && !member_contact_history_address_changed($old_json, $new_json)) {
        return true;
    }

    if ($addr_idx < 1) {
        if ($new_row !== null && (int) ($new_row['addr_idx'] ?? 0) > 0) {
            $addr_idx = (int) $new_row['addr_idx'];
        } elseif ($old_row !== null && (int) ($old_row['addr_idx'] ?? 0) > 0) {
            $addr_idx = (int) $old_row['addr_idx'];
        }
    }

    return member_contact_history_insert(
        $mb_idx,
        'address',
        $action,
        $old_json,
        $new_json,
        $actor,
        $addr_idx > 0 ? $addr_idx : null
    );
}
