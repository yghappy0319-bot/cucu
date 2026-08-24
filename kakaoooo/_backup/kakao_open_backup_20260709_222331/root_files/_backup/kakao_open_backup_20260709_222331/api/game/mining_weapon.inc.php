<?php
/**
 * 채굴 무기 장착 — tb_member_mining.mining_weapon_equipped
 */

require_once __DIR__ . '/mining_storage.inc.php';

if (!function_exists('mining_weapon_ensure_columns')) {
    function mining_weapon_ensure_columns(): void {
        static $done = false;
        if ($done || !function_exists('db_query') || !function_exists('db_select')) {
            return;
        }
        $done = true;
        mining_data_ensure_table();

        $tbl = MINING_TABLE;
        $col = @db_select("SHOW COLUMNS FROM `{$tbl}` LIKE 'mining_weapon_equipped'");
        if (empty($col)) {
            @db_query("
                ALTER TABLE `{$tbl}`
                  ADD COLUMN `mining_weapon_equipped` TINYINT(1) NOT NULL DEFAULT 0
                    COMMENT '채굴 무기 장착 여부'
                  AFTER `mining_lease_until`
            ");
        }
    }
}

if (!function_exists('mining_weapon_member_row')) {
    function mining_weapon_member_row($nick) {
        $nick_esc = mining_data_nick_esc($nick);
        if ($nick_esc === '') {
            return null;
        }
        return db_select("
            SELECT item, enhance
            FROM tb_member
            WHERE name = '{$nick_esc}'
            LIMIT 1
        ");
    }
}

if (!function_exists('mining_weapon_equipped_flag')) {
    function mining_weapon_equipped_flag($nick): bool {
        mining_weapon_ensure_columns();
        $nick_esc = mining_data_nick_esc($nick);
        if ($nick_esc === '') {
            return false;
        }
        if (!mining_data_ensure_row($nick)) {
            return false;
        }
        $row = db_select("
            SELECT mining_weapon_equipped
            FROM `" . MINING_TABLE . "`
            WHERE nick = '{$nick_esc}'
            LIMIT 1
        ");
        return !empty($row) && (int)($row['mining_weapon_equipped'] ?? 0) === 1;
    }
}

if (!function_exists('mining_weapon_clear_if_no_item')) {
    function mining_weapon_clear_if_no_item($nick, $item = null): void {
        if ($item === null) {
            $member = mining_weapon_member_row($nick);
            $item = trim((string)($member['item'] ?? ''));
        } else {
            $item = trim((string)$item);
        }
        if ($item !== '') {
            return;
        }
        $nick_esc = mining_data_nick_esc($nick);
        if ($nick_esc === '') {
            return;
        }
        @db_query("
            UPDATE `" . MINING_TABLE . "`
            SET mining_weapon_equipped = 0
            WHERE nick = '{$nick_esc}'
            LIMIT 1
        ");
    }
}

if (!function_exists('mining_weapon_equipped')) {
    /** 채팅 .시전 · .보호 차단용 */
    function mining_weapon_equipped($nick): bool {
        if (!mining_weapon_equipped_flag($nick)) {
            return false;
        }
        $member = mining_weapon_member_row($nick);
        $item = trim((string)($member['item'] ?? ''));
        if ($item === '') {
            mining_weapon_clear_if_no_item($nick, '');
            return false;
        }
        return true;
    }
}

if (!function_exists('mining_weapon_label')) {
    function mining_weapon_label($item, $enhance): string {
        $item = trim((string)$item);
        if ($item === '') {
            return '없음';
        }
        $enhance = (int)$enhance;
        return $item . ($enhance > 0 ? " +{$enhance}" : '');
    }
}

if (!function_exists('mining_weapon_payload')) {
    function mining_weapon_payload($nick, $member_row = null) {
        mining_weapon_ensure_columns();
        if ($member_row === null) {
            $member_row = mining_weapon_member_row($nick);
        }
        $item = trim((string)($member_row['item'] ?? ''));
        $enhance = (int)($member_row['enhance'] ?? 0);
        $flag = mining_weapon_equipped_flag($nick);
        if ($flag && $item === '') {
            mining_weapon_clear_if_no_item($nick, '');
            $flag = false;
        }
        return [
            'has_weapon' => $item !== '',
            'item' => $item,
            'enhance' => $enhance,
            'label' => mining_weapon_label($item, $enhance),
            'equipped' => $flag && $item !== '',
        ];
    }
}

if (!function_exists('mining_weapon_sync_from_mount')) {
    /**
     * 채팅 .내무기(mount) → 채굴 무기 결합 동기화
     * mount=0(해제·채굴) + +10 무기 → mining_weapon_equipped=1
     * mount=1(장착·시전) → mining_weapon_equipped=0
     *
     * @return bool 채굴 결합 ON 여부
     */
    function mining_weapon_sync_from_mount($nick, int $mount): bool {
        mining_weapon_ensure_columns();
        if (!mining_data_ensure_row($nick)) {
            return false;
        }
        $member = mining_weapon_member_row($nick);
        $item = trim((string)($member['item'] ?? ''));
        $enhance = (int)($member['enhance'] ?? 0);
        $mining_equip = ($mount === 0 && $item !== '' && $enhance >= 10) ? 1 : 0;
        $nick_esc = mining_data_nick_esc($nick);
        if ($nick_esc === '') {
            return false;
        }
        db_query("
            UPDATE `" . MINING_TABLE . "`
            SET mining_weapon_equipped = {$mining_equip}
            WHERE nick = '{$nick_esc}'
            LIMIT 1
        ");
        if ($mining_equip === 1) {
            if (!function_exists('mining_ore_schedule_init')) {
                require_once __DIR__ . '/mining_ore_cron.inc.php';
            }
            mining_ore_schedule_init($nick);
        } else {
            if (!function_exists('mining_ore_schedule_clear')) {
                require_once __DIR__ . '/mining_ore_cron.inc.php';
            }
            mining_ore_schedule_clear($nick);
        }
        return $mining_equip === 1;
    }
}

if (!function_exists('mining_mount_sync_from_weapon')) {
    /**
     * 채굴 화면 장착/해제 → 채팅 mount 동기화
     * mining ON → mount=0 · mining OFF → mount=1
     */
    function mining_mount_sync_from_weapon($nick, bool $mining_equipped): void {
        $nick_esc = mining_data_nick_esc($nick);
        if ($nick_esc === '') {
            return;
        }
        $mount = $mining_equipped ? 0 : 1;
        @db_query("
            UPDATE tb_member
            SET mount = {$mount}
            WHERE name = '{$nick_esc}'
            LIMIT 1
        ");
    }
}

if (!function_exists('mining_weapon_equip_execute')) {
    function mining_weapon_equip_execute($nick) {
        mining_weapon_ensure_columns();
        $member = mining_weapon_member_row($nick);
        if (empty($member)) {
            return ['ok' => false, 'data' => '회원 정보를 찾을 수 없어요.'];
        }
        $item = trim((string)($member['item'] ?? ''));
        if ($item === '') {
            return ['ok' => false, 'data' => '보유 무기가 없어요.'];
        }
        if (!mining_data_ensure_row($nick)) {
            return ['ok' => false, 'data' => '채굴 정보를 준비할 수 없어요.'];
        }
        $enhance = (int)($member['enhance'] ?? 0);
        if ($enhance < 10) {
            return ['ok' => false, 'data' => '+10 이상 무기부터 채굴 결합이 가능해요.'];
        }
        $nick_esc = mining_data_nick_esc($nick);
        db_query("
            UPDATE `" . MINING_TABLE . "`
            SET mining_weapon_equipped = 1
            WHERE nick = '{$nick_esc}'
            LIMIT 1
        ");
        mining_mount_sync_from_weapon($nick, true);
        if (!function_exists('mining_ore_schedule_init')) {
            require_once __DIR__ . '/mining_ore_cron.inc.php';
        }
        mining_ore_schedule_init($nick);
        $weapon = mining_weapon_payload($nick, $member);
        return [
            'ok' => true,
            'data' => $weapon['label'] . ' 장착! 채팅 .시전 · .보호는 사용할 수 없어요. (`.내무기` 해제 상태와 연동)',
            'weapon' => $weapon,
        ];
    }
}

if (!function_exists('mining_weapon_unequip_execute')) {
    function mining_weapon_unequip_execute($nick) {
        mining_weapon_ensure_columns();
        if (!mining_data_ensure_row($nick)) {
            return ['ok' => false, 'data' => '채굴 정보를 찾을 수 없어요.'];
        }
        $nick_esc = mining_data_nick_esc($nick);
        db_query("
            UPDATE `" . MINING_TABLE . "`
            SET mining_weapon_equipped = 0
            WHERE nick = '{$nick_esc}'
            LIMIT 1
        ");
        mining_mount_sync_from_weapon($nick, false);
        if (!function_exists('mining_ore_schedule_clear')) {
            require_once __DIR__ . '/mining_ore_cron.inc.php';
        }
        mining_ore_schedule_clear($nick);
        $member = mining_weapon_member_row($nick);
        $weapon = mining_weapon_payload($nick, $member ?: []);
        return [
            'ok' => true,
            'data' => '무기 장착을 해제했어요. (`.내무기` 장착 상태와 연동)',
            'weapon' => $weapon,
        ];
    }
}
