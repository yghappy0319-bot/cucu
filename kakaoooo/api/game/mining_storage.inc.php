<?php
/**
 * 채굴 회원 상태 — tb_member_mining (tb_member 와 분리)
 */

require_once __DIR__ . '/mining_config.inc.php';

if (!defined('MINING_TABLE')) {
    define('MINING_TABLE', 'tb_member_mining');
}

if (!function_exists('mining_data_ensure_table')) {
    function mining_data_ensure_table(): void {
        static $done = false;
        if ($done || !function_exists('db_query')) {
            return;
        }
        $done = true;

        @db_query("
            CREATE TABLE IF NOT EXISTS `" . MINING_TABLE . "` (
              `nick` VARCHAR(32) NOT NULL COMMENT 'tb_member.name',
              `mining_tool` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=숟가락 1=포크',
              `mining_pending` DECIMAL(24,10) NOT NULL DEFAULT 0 COMMENT '미저장 냥(소수 10자리)',
              `mining_sync_at` DATETIME DEFAULT NULL COMMENT 'pending 마지막 확정',
              `mining_lease_token` VARCHAR(32) DEFAULT NULL COMMENT '활성 세션 토큰',
              `mining_lease_until` DATETIME DEFAULT NULL COMMENT 'lease 만료',
              `mining_weapon_equipped` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '채굴 무기 장착',
              `mining_upgrade_attempts` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '채굴강화 시도 누적',
              `mining_durability` DECIMAL(12,6) NOT NULL DEFAULT 120 COMMENT '채굴 장비 내구도 (장비별 최대)',
              `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`nick`),
              KEY `idx_lease_until` (`mining_lease_until`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='채굴 장비·누적·동기화 상태'
        ");

        mining_data_migrate_from_member();
        mining_data_ensure_pending_scale();
        if (function_exists('mining_eunchong_tier_ensure_column')) {
            mining_eunchong_tier_ensure_column();
        }
        if (function_exists('mining_durability_ensure_column')) {
            mining_durability_ensure_column();
        } elseif (is_file(__DIR__ . '/mining_durability.inc.php')) {
            require_once __DIR__ . '/mining_durability.inc.php';
            if (function_exists('mining_durability_ensure_column')) {
                mining_durability_ensure_column();
            }
        }
    }
}

if (!function_exists('mining_data_ensure_pending_scale')) {
    function mining_data_ensure_pending_scale(): void {
        if (!function_exists('db_select') || !function_exists('db_query')) {
            return;
        }
        $tbl = MINING_TABLE;
        $col = @db_select("SHOW COLUMNS FROM `{$tbl}` LIKE 'mining_pending'");
        if (empty($col['Type'])) {
            return;
        }
        if (!preg_match('/decimal\s*\(\s*\d+\s*,\s*(\d+)\s*\)/i', (string)$col['Type'], $m)) {
            return;
        }
        if ((int)$m[1] === (int)MINING_PENDING_SCALE) {
            return;
        }
        $scale = max(0, (int)MINING_PENDING_SCALE);
        @db_query("
            ALTER TABLE `{$tbl}`
              MODIFY mining_pending DECIMAL(24,{$scale}) NOT NULL DEFAULT 0
                COMMENT '미저장 냥(소수 {$scale}자리)'
        ");
    }
}

if (!function_exists('mining_data_migrate_from_member')) {
    /** tb_member 에 있던 채굴 컬럼 1회 이전 (이미 배포된 환경용) */
    function mining_data_migrate_from_member(): void {
        static $done = false;
        if ($done || !function_exists('db_select') || !function_exists('db_query')) {
            return;
        }
        $col = @db_select("SHOW COLUMNS FROM tb_member LIKE 'mining_tool'");
        if (empty($col)) {
            $done = true;
            return;
        }
        $done = true;

        $pending_col = @db_select("SHOW COLUMNS FROM tb_member LIKE 'mining_pending'");
        $has_pending = !empty($pending_col);

        if ($has_pending) {
            @db_query("
                INSERT INTO `" . MINING_TABLE . "` (
                    nick, mining_tool, mining_pending, mining_sync_at,
                    mining_lease_token, mining_lease_until
                )
                SELECT
                    m.name,
                    IFNULL(m.mining_tool, 0),
                    IFNULL(m.mining_pending, 0),
                    m.mining_sync_at,
                    m.mining_lease_token,
                    m.mining_lease_until
                FROM tb_member m
                LEFT JOIN `" . MINING_TABLE . "` mm ON mm.nick = m.name
                WHERE mm.nick IS NULL
                  AND (
                    IFNULL(m.mining_tool, 0) > 0
                    OR IFNULL(m.mining_pending, 0) > 0
                    OR m.mining_sync_at IS NOT NULL
                    OR m.mining_lease_token IS NOT NULL
                  )
            ");
        } else {
            @db_query("
                INSERT INTO `" . MINING_TABLE . "` (nick, mining_tool)
                SELECT m.name, IFNULL(m.mining_tool, 0)
                FROM tb_member m
                LEFT JOIN `" . MINING_TABLE . "` mm ON mm.nick = m.name
                WHERE mm.nick IS NULL
                  AND IFNULL(m.mining_tool, 0) > 0
            ");
        }
    }
}

if (!function_exists('mining_data_nick_esc')) {
    function mining_data_nick_esc($nick): string {
        return addslashes(trim((string)$nick));
    }
}

if (!function_exists('mining_data_ensure_row')) {
    function mining_data_ensure_row($nick): bool {
        mining_data_ensure_table();
        $nick_esc = mining_data_nick_esc($nick);
        if ($nick_esc === '') {
            return false;
        }

        $exists = db_select("
            SELECT nick FROM `" . MINING_TABLE . "`
            WHERE nick = '{$nick_esc}'
            LIMIT 1
        ");
        if (!empty($exists)) {
            return true;
        }

        $member = db_select("SELECT name FROM tb_member WHERE name = '{$nick_esc}' LIMIT 1");
        if (empty($member)) {
            return false;
        }

        db_query("
            INSERT IGNORE INTO `" . MINING_TABLE . "` (nick)
            VALUES ('{$nick_esc}')
        ");
        return true;
    }
}

if (!function_exists('mining_data_select_raw')) {
    function mining_data_select_raw($nick) {
        mining_data_ensure_table();
        $nick_esc = mining_data_nick_esc($nick);
        if ($nick_esc === '') {
            return null;
        }
        if (!mining_data_ensure_row($nick)) {
            return null;
        }
        mining_upgrade_attempts_ensure_column();
        if (function_exists('mining_durability_ensure_column')) {
            mining_durability_ensure_column();
        }
        $dur_default = function_exists('mining_durability_sql')
            ? mining_durability_sql(function_exists('mining_durability_max') ? mining_durability_max(0) : 120, 0)
            : '120';
        return db_select("
            SELECT mining_tool, mining_pending, mining_sync_at,
                   mining_lease_token, mining_lease_until,
                   IFNULL(mining_upgrade_attempts, 0) AS mining_upgrade_attempts,
                   IFNULL(mining_durability, {$dur_default}) AS mining_durability
            FROM `" . MINING_TABLE . "`
            WHERE nick = '{$nick_esc}'
            LIMIT 1
        ");
    }
}

if (!function_exists('mining_upgrade_attempts_ensure_column')) {
    function mining_upgrade_attempts_ensure_column(): void {
        static $done = false;
        if ($done || !function_exists('db_query')) {
            return;
        }
        $done = true;
        mining_data_ensure_table();
        $tbl = MINING_TABLE;
        $exists = @db_select("SHOW COLUMNS FROM `{$tbl}` LIKE 'mining_upgrade_attempts'");
        if (empty($exists)) {
            @db_query("
                ALTER TABLE `{$tbl}`
                ADD COLUMN `mining_upgrade_attempts` INT UNSIGNED NOT NULL DEFAULT 0
                COMMENT '채굴강화 시도 누적(성공·실패)' AFTER `mining_weapon_equipped`
            ");
        }
    }
}

if (!function_exists('mining_upgrade_attempts_required')) {
    function mining_upgrade_attempts_required(): int {
        return max(1, (int)MINING_UNLOCK_UPGRADE_ATTEMPTS);
    }
}

if (!function_exists('mining_upgrade_attempts_for_nick')) {
    function mining_upgrade_attempts_for_nick($nick): int {
        mining_upgrade_attempts_ensure_column();
        $nick_esc = mining_data_nick_esc($nick);
        if ($nick_esc === '') {
            return 0;
        }
        if (!mining_data_ensure_row($nick)) {
            return 0;
        }
        $row = db_select("
            SELECT IFNULL(mining_upgrade_attempts, 0) AS cnt
            FROM `" . MINING_TABLE . "`
            WHERE nick = '{$nick_esc}'
            LIMIT 1
        ");
        return (int)($row['cnt'] ?? 0);
    }
}

if (!function_exists('mining_yield_unlocked_for_attempts')) {
    function mining_yield_unlocked_for_attempts(int $attempts): bool {
        return $attempts >= mining_upgrade_attempts_required();
    }
}

if (!function_exists('mining_yield_unlocked_for_nick')) {
    function mining_yield_unlocked_for_nick($nick): bool {
        return mining_yield_unlocked_for_attempts(mining_upgrade_attempts_for_nick($nick));
    }
}

if (!function_exists('mining_upgrade_attempts_add')) {
    function mining_upgrade_attempts_add($nick, int $add): int {
        $add = max(0, $add);
        if ($add < 1) {
            return mining_upgrade_attempts_for_nick($nick);
        }
        mining_upgrade_attempts_ensure_column();
        $nick_esc = mining_data_nick_esc($nick);
        if ($nick_esc === '' || !mining_data_ensure_row($nick)) {
            return 0;
        }
        db_query("
            UPDATE `" . MINING_TABLE . "`
            SET mining_upgrade_attempts = IFNULL(mining_upgrade_attempts, 0) + {$add}
            WHERE nick = '{$nick_esc}'
            LIMIT 1
        ");
        return mining_upgrade_attempts_for_nick($nick);
    }
}

if (!function_exists('mining_yield_unlock_payload')) {
    /** @return array{upgrade_attempts:int,upgrade_attempts_required:int,yield_unlocked:bool,unlock_hint:string} */
    function mining_yield_unlock_payload($nick): array {
        $attempts = mining_upgrade_attempts_for_nick($nick);
        return mining_yield_unlock_payload_from_attempts($attempts);
    }
}

if (!function_exists('mining_yield_unlock_payload_from_attempts')) {
    /** @return array{upgrade_attempts:int,upgrade_attempts_required:int,yield_unlocked:bool,unlock_hint:string} */
    function mining_yield_unlock_payload_from_attempts(int $attempts): array {
        $required = mining_upgrade_attempts_required();
        $unlocked = mining_yield_unlocked_for_attempts($attempts);
        $hint = $unlocked
            ? ''
            : "강화 {$required}회 후 채굴냥 적립 (현재 {$attempts}/{$required})";
        return [
            'upgrade_attempts' => $attempts,
            'upgrade_attempts_required' => $required,
            'yield_unlocked' => $unlocked,
            'unlock_hint' => $hint,
        ];
    }
}

if (!function_exists('mining_yield_just_unlocked')) {
    /** 이번 시도로 처음 해제됐을 때만 true */
    function mining_yield_just_unlocked(int $attempts_before, int $attempts_after): bool {
        return !mining_yield_unlocked_for_attempts($attempts_before)
            && mining_yield_unlocked_for_attempts($attempts_after);
    }
}

if (!function_exists('mining_tool_ensure_column')) {
    /** @deprecated tb_member 컬럼 대신 tb_member_mining 테이블 사용 */
    function mining_tool_ensure_column(): void {
        mining_data_ensure_table();
    }
}

if (!function_exists('mining_sync_ensure_columns')) {
    /** @deprecated tb_member 컬럼 대신 tb_member_mining 테이블 사용 */
    function mining_sync_ensure_columns(): void {
        mining_data_ensure_table();
    }
}

if (!function_exists('mining_data_rename_nick')) {
    /**
     * 닉변 시 채굴 장비·광물 발견/로그 nick 동기화
     *
     * @return array{ok:bool,mining:bool,ore_find:int,ore_log:int}
     */
    function mining_data_rename_nick($old_nick, $new_nick): array {
        $old = trim((string)$old_nick);
        $new = trim((string)$new_nick);
        $empty = ['ok' => false, 'mining' => false, 'ore_find' => 0, 'ore_log' => 0];
        if ($old === '' || $new === '' || $old === $new || !function_exists('db_query')) {
            return $empty;
        }

        mining_data_ensure_table();
        if (is_file(__DIR__ . '/mining_ore.inc.php')) {
            require_once __DIR__ . '/mining_ore.inc.php';
            if (function_exists('mining_ore_ensure_schema')) {
                mining_ore_ensure_schema();
            }
        }

        $old_esc = mining_data_nick_esc($old);
        $new_esc = mining_data_nick_esc($new);
        if ($old_esc === '' || $new_esc === '') {
            return $empty;
        }

        $mining_ok = false;
        $old_row = @db_select("SELECT nick FROM `" . MINING_TABLE . "` WHERE nick = '{$old_esc}' LIMIT 1");
        if (!empty($old_row['nick'])) {
            $new_row = @db_select("SELECT nick FROM `" . MINING_TABLE . "` WHERE nick = '{$new_esc}' LIMIT 1");
            if (!empty($new_row['nick'])) {
                // PK 충돌: 새 닉에 남은 고아 행 제거 후 본인 장비 이전
                @db_query("DELETE FROM `" . MINING_TABLE . "` WHERE nick = '{$new_esc}' LIMIT 1");
            }
            $mining_ok = (bool)@db_query("
                UPDATE `" . MINING_TABLE . "`
                SET nick = '{$new_esc}'
                WHERE nick = '{$old_esc}'
                LIMIT 1
            ");
        }

        $ore_find = 0;
        $ore_log = 0;
        if (@db_query("UPDATE tb_mining_ore_find SET nick = '{$new_esc}' WHERE nick = '{$old_esc}'")) {
            global $conn;
            if ($conn instanceof mysqli) {
                $ore_find = (int)mysqli_affected_rows($conn);
            }
        }
        if (@db_query("UPDATE tb_mining_ore_log SET nick = '{$new_esc}' WHERE nick = '{$old_esc}'")) {
            global $conn;
            if ($conn instanceof mysqli) {
                $ore_log = (int)mysqli_affected_rows($conn);
            }
        }

        return [
            'ok' => true,
            'mining' => $mining_ok,
            'ore_find' => $ore_find,
            'ore_log' => $ore_log,
        ];
    }
}

if (!function_exists('mining_member_purge')) {
    /**
     * 회원 퇴사·삭제 시 채굴·광물 관련 데이터 전부 제거
     *
     * @return array{ok:bool,mining_deleted:int,ore_find_deleted:int,ore_log_deleted:int}
     */
    function mining_member_purge($nick): array {
        mining_data_ensure_table();

        require_once __DIR__ . '/mining_ore.inc.php';
        if (function_exists('mining_ore_ensure_schema')) {
            mining_ore_ensure_schema();
        }

        $nick_esc = mining_data_nick_esc($nick);
        if ($nick_esc === '') {
            return [
                'ok' => false,
                'mining_deleted' => 0,
                'ore_find_deleted' => 0,
                'ore_log_deleted' => 0,
            ];
        }

        $ore_find_deleted = 0;
        $rs_find = @db_select("
            SELECT COUNT(*) AS cnt
            FROM tb_mining_ore_find
            WHERE nick = '{$nick_esc}'
        ");
        if (!empty($rs_find['cnt'])) {
            $ore_find_deleted = (int)$rs_find['cnt'];
        }
        if ($ore_find_deleted > 0) {
            db_query("DELETE FROM tb_mining_ore_find WHERE nick = '{$nick_esc}'");
        }

        $ore_log_deleted = 0;
        $rs_log = @db_select("
            SELECT COUNT(*) AS cnt
            FROM tb_mining_ore_log
            WHERE nick = '{$nick_esc}'
        ");
        if (!empty($rs_log['cnt'])) {
            $ore_log_deleted = (int)$rs_log['cnt'];
        }
        if ($ore_log_deleted > 0) {
            db_query("DELETE FROM tb_mining_ore_log WHERE nick = '{$nick_esc}'");
        }

        db_query("DELETE FROM `" . MINING_TABLE . "` WHERE nick = '{$nick_esc}'");

        global $conn;
        $mining_deleted = ($conn instanceof mysqli) ? (int)mysqli_affected_rows($conn) : 1;

        return [
            'ok' => true,
            'mining_deleted' => $mining_deleted,
            'ore_find_deleted' => $ore_find_deleted,
            'ore_log_deleted' => $ore_log_deleted,
        ];
    }
}
