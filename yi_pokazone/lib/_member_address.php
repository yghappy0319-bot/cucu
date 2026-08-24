<?php

require_once __DIR__ . '/_member_contact_history.php';

const MEMBER_ADDRESS_MAX = 10;
const MEMBER_ADDRESS_MESSAGE_MAX = 200;

function member_address_table_ready(): bool
{
    return db_table_exists('tb_member_address');
}

function member_address_message_column_ready(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    if (!member_address_table_ready()) {
        $ready = false;

        return false;
    }
    $ready = (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_member_address LIKE 'addr_message'"));

    return $ready;
}

function member_address_select_fields(bool $with_timestamps = false): string
{
    $fields = 'addr_idx, addr_label, addr_name, addr_phone, addr_zip,
               addr_road, addr_jibun, addr_extra, addr_detail';
    if (member_address_message_column_ready()) {
        $fields .= ', addr_message';
    }
    $fields .= ', addr_is_default';
    if ($with_timestamps) {
        $fields .= ', addr_created_at, addr_updated_at';
    }

    return $fields;
}

function member_address_count(int $mb_idx): int
{
    if (!member_address_table_ready()) {
        return 0;
    }

    return (int) db_result(
        "SELECT COUNT(*) FROM tb_member_address
         WHERE mb_idx = {$mb_idx} AND addr_status = 1"
    );
}

function member_address_get_default(int $mb_idx): ?array
{
    $list = member_address_list($mb_idx);

    return $list[0] ?? null;
}

/**
 * @param array<string, mixed> $addr
 */
function member_address_format_lines(array $addr): array
{
    $road = trim((string) ($addr['addr_road'] ?? ''));
    if ($road === '') {
        $road = trim((string) ($addr['addr_jibun'] ?? ''));
    }
    $extra = trim((string) ($addr['addr_extra'] ?? ''));
    $detail = trim((string) ($addr['addr_detail'] ?? ''));
    $zip = trim((string) ($addr['addr_zip'] ?? ''));
    $full = trim($road . ($extra !== '' ? ' ' . $extra : '') . ($detail !== '' ? ' ' . $detail : ''));

    $lines = [
        'label'   => trim((string) ($addr['addr_label'] ?? '')),
        'name'    => trim((string) ($addr['addr_name'] ?? '')),
        'phone'   => member_address_format_phone((string) ($addr['addr_phone'] ?? '')),
        'zip'     => $zip,
        'address' => $full,
        'message' => trim((string) ($addr['addr_message'] ?? '')),
    ];

    return $lines;
}

function member_address_list(int $mb_idx): array
{
    if (!member_address_table_ready()) {
        return [];
    }

    $rows = [];
    $fields = member_address_select_fields(true);
    $rs     = db_query("
        SELECT {$fields}
        FROM tb_member_address
        WHERE mb_idx = {$mb_idx} AND addr_status = 1
        ORDER BY addr_is_default DESC, addr_idx DESC
    ");
    while ($r = db_assoc($rs)) {
        $rows[] = $r;
    }

    return $rows;
}

function member_address_get(int $mb_idx, int $addr_idx): ?array
{
    if (!member_address_table_ready() || $addr_idx < 1) {
        return null;
    }

    $fields = member_address_select_fields(false);
    $row    = db_assoc(db_query("
        SELECT {$fields}
        FROM tb_member_address
        WHERE mb_idx = {$mb_idx} AND addr_idx = {$addr_idx} AND addr_status = 1
        LIMIT 1
    "));

    return $row ?: null;
}

function member_address_clear_default(int $mb_idx): void
{
    db_query("
        UPDATE tb_member_address
        SET addr_is_default = 0, addr_updated_at = NOW()
        WHERE mb_idx = {$mb_idx} AND addr_status = 1
    ");
}

function member_address_format_phone(string $phone): string
{
    $norm = mb_phone_normalize_kr($phone);
    if ($norm !== null) {
        return $norm;
    }

    return trim($phone);
}

function member_address_validate_input(array $in, bool $is_update = false): array
{
    $label  = trim((string) ($in['addr_label'] ?? ''));
    $name   = trim((string) ($in['addr_name'] ?? ''));
    $phone  = trim((string) ($in['addr_phone'] ?? ''));
    $zip    = trim((string) ($in['addr_zip'] ?? ''));
    $road   = trim((string) ($in['addr_road'] ?? ''));
    $jibun  = trim((string) ($in['addr_jibun'] ?? ''));
    $extra  = trim((string) ($in['addr_extra'] ?? ''));
    $detail  = trim((string) ($in['addr_detail'] ?? ''));
    $message = trim((string) ($in['addr_message'] ?? ''));
    $is_def  = !empty($in['addr_is_default']);

    if ($label === '') {
        $label = '배송지';
    }
    if (mb_strlen($label) > 30) {
        return ['ok' => false, 'error' => '배송지명은 30자 이내로 입력해 주세요.'];
    }
    if ($name === '' || mb_strlen($name) > 50) {
        return ['ok' => false, 'error' => '받는 분 이름을 50자 이내로 입력해 주세요.'];
    }
    if ($phone === '') {
        return ['ok' => false, 'error' => '연락처를 입력해 주세요.'];
    }
    $phone_fmt = mb_phone_normalize_kr($phone);
    if ($phone_fmt === null) {
        return ['ok' => false, 'error' => '연락처를 올바르게 입력해 주세요. (예: 010-1234-5678)'];
    }
    if ($zip === '' || !preg_match('/^\d{5}$/', $zip)) {
        return ['ok' => false, 'error' => '주소 검색으로 우편번호를 입력해 주세요.'];
    }
    if ($road === '' || mb_strlen($road) > 255) {
        return ['ok' => false, 'error' => '주소 검색으로 기본 주소를 입력해 주세요.'];
    }
    if (mb_strlen($jibun) > 255) {
        return ['ok' => false, 'error' => '지번 주소가 너무 깁니다.'];
    }
    if (mb_strlen($extra) > 100) {
        return ['ok' => false, 'error' => '참고항목은 100자 이내로 입력해 주세요.'];
    }
    if (mb_strlen($detail) > 100) {
        return ['ok' => false, 'error' => '상세주소는 100자 이내로 입력해 주세요.'];
    }
    if (mb_strlen($message) > MEMBER_ADDRESS_MESSAGE_MAX) {
        return [
            'ok'    => false,
            'error' => '배송 메시지는 ' . MEMBER_ADDRESS_MESSAGE_MAX . '자 이내로 입력해 주세요.',
        ];
    }

    $data = [
        'addr_label'      => $label,
        'addr_name'       => $name,
        'addr_phone'      => $phone_fmt,
        'addr_zip'        => $zip,
        'addr_road'       => $road,
        'addr_jibun'      => $jibun !== '' ? $jibun : null,
        'addr_extra'      => $extra,
        'addr_detail'     => $detail,
        'addr_is_default' => $is_def ? 1 : 0,
    ];
    if (member_address_message_column_ready()) {
        $data['addr_message'] = $message;
    }

    return ['ok' => true, 'data' => $data];
}

function member_address_save(int $mb_idx, array $data, int $addr_idx = 0): array
{
    if (!member_address_table_ready()) {
        return ['ok' => false, 'error' => '배송지 기능이 아직 준비되지 않았습니다. 관리자에게 문의해 주세요.'];
    }

    $validated = member_address_validate_input($data, $addr_idx > 0);
    if (!$validated['ok']) {
        return $validated;
    }
    $d = $validated['data'];

    if ($addr_idx > 0) {
        $existing = member_address_get($mb_idx, $addr_idx);
        if (!$existing) {
            return ['ok' => false, 'error' => '수정할 배송지를 찾을 수 없습니다.'];
        }
    } elseif (member_address_count($mb_idx) >= MEMBER_ADDRESS_MAX) {
        return ['ok' => false, 'error' => '배송지는 최대 ' . MEMBER_ADDRESS_MAX . '개까지 등록할 수 있습니다.'];
    }

    if ((int) $d['addr_is_default'] === 1) {
        member_address_clear_default($mb_idx);
    } elseif ($addr_idx < 1 && member_address_count($mb_idx) === 0) {
        $d['addr_is_default'] = 1;
    } elseif ($addr_idx > 0 && (int) ($existing['addr_is_default'] ?? 0) === 1) {
        $d['addr_is_default'] = 1;
    }

    $esc_label  = db_escape($d['addr_label']);
    $esc_name   = db_escape($d['addr_name']);
    $esc_phone  = db_escape($d['addr_phone']);
    $esc_zip    = db_escape($d['addr_zip']);
    $esc_road   = db_escape($d['addr_road']);
    $esc_jibun  = $d['addr_jibun'] !== null ? "'" . db_escape((string) $d['addr_jibun']) . "'" : 'NULL';
    $esc_extra  = db_escape($d['addr_extra']);
    $esc_detail = db_escape($d['addr_detail']);
    $is_def     = (int) $d['addr_is_default'];
    $msg_sql    = '';
    if (member_address_message_column_ready()) {
        $esc_msg = db_escape((string) ($d['addr_message'] ?? ''));
        $msg_sql = ", addr_message = '{$esc_msg}'";
    }

    if ($addr_idx > 0) {
        $ok = db_query("
            UPDATE tb_member_address SET
                addr_label = '{$esc_label}',
                addr_name = '{$esc_name}',
                addr_phone = '{$esc_phone}',
                addr_zip = '{$esc_zip}',
                addr_road = '{$esc_road}',
                addr_jibun = {$esc_jibun},
                addr_extra = '{$esc_extra}',
                addr_detail = '{$esc_detail}'{$msg_sql},
                addr_is_default = {$is_def},
                addr_updated_at = NOW()
            WHERE mb_idx = {$mb_idx} AND addr_idx = {$addr_idx} AND addr_status = 1
            LIMIT 1
        ");
        if (!$ok) {
            return ['ok' => false, 'error' => '저장 중 오류가 발생했습니다.'];
        }

        $new_row = array_merge($existing, $d, ['addr_idx' => $addr_idx]);
        if (
            member_contact_history_table_ready()
            && !member_contact_history_log_address(
                $mb_idx,
                'update',
                $existing,
                $new_row,
                member_contact_history_actor_member($mb_idx),
                $addr_idx
            )
        ) {
            error_log("member_contact_history: address update log failed mb_idx={$mb_idx} addr_idx={$addr_idx}");
        }

        return ['ok' => true, 'addr_idx' => $addr_idx];
    }

    $msg_cols = '';
    $msg_vals = '';
    if (member_address_message_column_ready()) {
        $esc_msg  = db_escape((string) ($d['addr_message'] ?? ''));
        $msg_cols = ', addr_message';
        $msg_vals = ", '{$esc_msg}'";
    }

    $ok = db_query("
        INSERT INTO tb_member_address
            (mb_idx, addr_label, addr_name, addr_phone, addr_zip, addr_road, addr_jibun,
             addr_extra, addr_detail{$msg_cols}, addr_is_default, addr_status, addr_created_at, addr_updated_at)
        VALUES
            ({$mb_idx}, '{$esc_label}', '{$esc_name}', '{$esc_phone}', '{$esc_zip}', '{$esc_road}', {$esc_jibun},
             '{$esc_extra}', '{$esc_detail}'{$msg_vals}, {$is_def}, 1, NOW(), NOW())
    ");
    if (!$ok) {
        return ['ok' => false, 'error' => '저장 중 오류가 발생했습니다.'];
    }

    $new_addr_idx = (int) db_result('SELECT LAST_INSERT_ID()');
    $new_row      = array_merge($d, ['addr_idx' => $new_addr_idx]);
    if (
        member_contact_history_table_ready()
        && !member_contact_history_log_address(
            $mb_idx,
            'create',
            null,
            $new_row,
            member_contact_history_actor_member($mb_idx),
            $new_addr_idx
        )
    ) {
        error_log("member_contact_history: address create log failed mb_idx={$mb_idx} addr_idx={$new_addr_idx}");
    }

    return ['ok' => true, 'addr_idx' => $new_addr_idx];
}

function member_address_delete(int $mb_idx, int $addr_idx): array
{
    if (!member_address_table_ready() || $addr_idx < 1) {
        return ['ok' => false, 'error' => '삭제할 배송지를 찾을 수 없습니다.'];
    }

    $row = member_address_get($mb_idx, $addr_idx);
    if (!$row) {
        return ['ok' => false, 'error' => '삭제할 배송지를 찾을 수 없습니다.'];
    }

    $was_default = (int) ($row['addr_is_default'] ?? 0) === 1;

    $ok = db_query("
        UPDATE tb_member_address
        SET addr_status = 9, addr_is_default = 0, addr_updated_at = NOW()
        WHERE mb_idx = {$mb_idx} AND addr_idx = {$addr_idx} AND addr_status = 1
        LIMIT 1
    ");
    if (!$ok) {
        return ['ok' => false, 'error' => '삭제 중 오류가 발생했습니다.'];
    }

    if (
        member_contact_history_table_ready()
        && !member_contact_history_log_address(
            $mb_idx,
            'delete',
            $row,
            null,
            member_contact_history_actor_member($mb_idx),
            $addr_idx
        )
    ) {
        error_log("member_contact_history: address delete log failed mb_idx={$mb_idx} addr_idx={$addr_idx}");
    }

    if ($was_default) {
        $next = db_assoc(db_query("
            SELECT addr_idx FROM tb_member_address
            WHERE mb_idx = {$mb_idx} AND addr_status = 1
            ORDER BY addr_idx DESC
            LIMIT 1
        "));
        if ($next) {
            db_query("
                UPDATE tb_member_address
                SET addr_is_default = 1, addr_updated_at = NOW()
                WHERE mb_idx = {$mb_idx} AND addr_idx = " . (int) $next['addr_idx'] . "
                LIMIT 1
            ");
        }
    }

    return ['ok' => true];
}

function member_address_set_default(int $mb_idx, int $addr_idx): array
{
    if (!member_address_get($mb_idx, $addr_idx)) {
        return ['ok' => false, 'error' => '배송지를 찾을 수 없습니다.'];
    }

    member_address_clear_default($mb_idx);
    $ok = db_query("
        UPDATE tb_member_address
        SET addr_is_default = 1, addr_updated_at = NOW()
        WHERE mb_idx = {$mb_idx} AND addr_idx = {$addr_idx} AND addr_status = 1
        LIMIT 1
    ");
    if (!$ok) {
        return ['ok' => false, 'error' => '기본 배송지 설정에 실패했습니다.'];
    }

    return ['ok' => true];
}

function member_address_row_to_json(array $row): array
{
    return [
        'addr_idx'        => (int) ($row['addr_idx'] ?? 0),
        'addr_label'      => (string) ($row['addr_label'] ?? ''),
        'addr_name'       => (string) ($row['addr_name'] ?? ''),
        'addr_phone'      => (string) ($row['addr_phone'] ?? ''),
        'addr_zip'        => (string) ($row['addr_zip'] ?? ''),
        'addr_road'       => (string) ($row['addr_road'] ?? ''),
        'addr_jibun'      => (string) ($row['addr_jibun'] ?? ''),
        'addr_extra'      => (string) ($row['addr_extra'] ?? ''),
        'addr_detail'     => (string) ($row['addr_detail'] ?? ''),
        'addr_message'    => (string) ($row['addr_message'] ?? ''),
        'addr_is_default' => (int) ($row['addr_is_default'] ?? 0) === 1,
        'addr_created_at' => (string) ($row['addr_created_at'] ?? ''),
        'addr_updated_at' => (string) ($row['addr_updated_at'] ?? ''),
    ];
}
