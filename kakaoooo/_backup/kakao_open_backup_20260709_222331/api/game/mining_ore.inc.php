<?php
/**
 * 채굴 광물 발견 — tb_mining_ore_* (tb_item 별도)
 *
 * 발견 조건: 무기 +10~ 장착 · 60분 창마다 (무기 강화별 N개 + 채굴장비 Lv 보너스) · 1~60분 랜덤
 * 생성: api/_auto_mining_ore.php 크론 (mining_ore_next_spawn_at) · 오프라인 포함
 * 만료: expire_at 경과 pending → expired (크론 일괄 + 페이지 조회 시 lazy)
 *
 * 터치: 판매가를 mining_pending 에 가산 · 은총조각은 shard 카운트
 * 1시간 미터치 시 expired · tb_mining_ore_log 기록
 */

require_once __DIR__ . '/mining_storage.inc.php';
require_once __DIR__ . '/mining_weapon.inc.php';
require_once __DIR__ . '/mining_tool.inc.php';

if (!defined('MINING_ORE_WINDOW_SEC')) {
    define('MINING_ORE_WINDOW_SEC', 3600);
}

if (!function_exists('mining_ore_window_seconds')) {
    function mining_ore_window_seconds(): int {
        return max(1, (int)MINING_ORE_WINDOW_SEC);
    }
}

if (!function_exists('mining_ore_hourly_find_count')) {
    /** +10~+13: 1 · +14~+16: 2 · +17~+19: 3 · +20: 5 (60분당, 무기만) */
    function mining_ore_hourly_find_count(int $enhance): int {
        if ($enhance < 10) {
            return 0;
        }
        if ($enhance >= 20) {
            return 5;
        }
        if ($enhance >= 17) {
            return 3;
        }
        if ($enhance >= 14) {
            return 2;
        }
        return 1;
    }
}

if (!function_exists('mining_ore_tool_bonus_count')) {
    /** 채굴 장비 Lv 보너스 — Lv2+1 · Lv5+2 · Lv9+3 · Lv12+4 · Lv13+5 (60분당) */
    function mining_ore_tool_bonus_count(int $tool_level): int {
        $tool_level = max(0, (int)$tool_level);
        if (function_exists('mining_tool_max_level')) {
            $tool_level = min($tool_level, max(0, (int)mining_tool_max_level()));
        }
        if ($tool_level >= 13) {
            return 5;
        }
        if ($tool_level >= 12) {
            return 4;
        }
        if ($tool_level >= 9) {
            return 3;
        }
        if ($tool_level >= 5) {
            return 2;
        }
        if ($tool_level >= 2) {
            return 1;
        }
        return 0;
    }
}

if (!function_exists('mining_ore_effective_hourly_find_count')) {
    /** 무기 기본 + 채굴 장비 Lv 보너스 */
    function mining_ore_effective_hourly_find_count(int $enhance, int $tool_level = 0): int {
        $base = mining_ore_hourly_find_count($enhance);
        if ($base <= 0) {
            return 0;
        }
        return $base + mining_ore_tool_bonus_count($tool_level);
    }
}

if (!function_exists('mining_ore_discover_hint')) {
    function mining_ore_discover_hint(int $enhance, int $tool_level = 0): string {
        $cnt = mining_ore_effective_hourly_find_count($enhance, $tool_level);
        if ($cnt <= 0) {
            return '';
        }
        $bonus = mining_ore_tool_bonus_count($tool_level);
        if ($bonus > 0) {
            return '시간당 광물 ' . $cnt . '개 (장비 +' . $bonus . ') · 1~60분 랜덤';
        }
        return '시간당 광물 ' . $cnt . '개 (1~60분 랜덤)';
    }
}

if (!function_exists('mining_ore_roll_min_seconds')) {
    function mining_ore_roll_min_seconds(): int {
        return 60;
    }
}

if (!function_exists('mining_ore_roll_targets_uninitialized')) {
    /** DB 기본값 '' — 아직 이번 60분 창 스케줄 없음. '[]' 는 이번 창 소진 완료 */
    function mining_ore_roll_targets_uninitialized($raw): bool {
        return $raw === null || $raw === '';
    }
}

if (!function_exists('mining_ore_parse_roll_targets')) {
    /** @return list<int> */
    function mining_ore_parse_roll_targets($raw): array {
        if (mining_ore_roll_targets_uninitialized($raw)) {
            return [];
        }
        if (is_array($raw)) {
            $list = $raw;
        } else {
            $decoded = json_decode((string)$raw, true);
            if (!is_array($decoded)) {
                return [];
            }
            $list = $decoded;
        }
        $out = [];
        foreach ($list as $v) {
            $t = (int)$v;
            if ($t > 0) {
                $out[] = $t;
            }
        }
        sort($out, SORT_NUMERIC);
        return $out;
    }
}

if (!function_exists('mining_ore_encode_roll_targets')) {
    function mining_ore_encode_roll_targets(array $targets): string {
        $clean = [];
        foreach ($targets as $v) {
            $t = (int)$v;
            if ($t > 0) {
                $clean[] = $t;
            }
        }
        sort($clean, SORT_NUMERIC);
        return json_encode(array_values($clean), JSON_UNESCAPED_UNICODE);
    }
}

if (!function_exists('mining_ore_generate_roll_targets')) {
    /**
     * 현재 60분 창 안에서 광물이 뜰 누적 초(1~60분 랜덤) N개
     *
     * @return list<int>
     */
    function mining_ore_generate_roll_targets(int $count, ?int $window_sec = null): array {
        $count = max(0, $count);
        if ($count <= 0) {
            return [];
        }
        $window_sec = $window_sec ?? mining_ore_window_seconds();
        $min = mining_ore_roll_min_seconds();
        $max = max($min, (int)$window_sec);
        if ($count === 1) {
            return [random_int($min, $max)];
        }
        $set = [];
        $guard = 0;
        while (count($set) < $count && $guard < $count * 50) {
            $set[random_int($min, $max)] = true;
            $guard++;
        }
        $targets = array_keys($set);
        sort($targets, SORT_NUMERIC);
        while (count($targets) < $count) {
            $targets[] = random_int($min, $max);
            sort($targets, SORT_NUMERIC);
        }
        return array_slice($targets, 0, $count);
    }
}

if (!function_exists('mining_ore_daily_discover_pct')) {
    /** @deprecated mining_ore_hourly_find_count 사용 */
    function mining_ore_daily_discover_pct(int $enhance): int {
        return mining_ore_hourly_find_count($enhance);
    }
}
if (!defined('MINING_ORE_FIND_TTL_SEC')) {
    define('MINING_ORE_FIND_TTL_SEC', 3600);
}
if (!defined('MINING_ORE_EUNCHONG_SHARDS')) {
    define('MINING_ORE_EUNCHONG_SHARDS', 10);
}

if (!function_exists('mining_ore_ensure_schema')) {
    function mining_ore_ensure_schema(): void {
        static $done = false;
        if ($done || !function_exists('db_query') || !function_exists('db_select')) {
            return;
        }
        $done = true;
        mining_data_ensure_table();

        @db_query("
            CREATE TABLE IF NOT EXISTS `tb_mining_ore_def` (
              `ore_key` VARCHAR(32) NOT NULL,
              `icon` VARCHAR(16) NOT NULL DEFAULT '',
              `label` VARCHAR(32) NOT NULL DEFAULT '',
              `sell_newpoint` DECIMAL(24,10) NOT NULL DEFAULT 0,
              `sort_order` TINYINT UNSIGNED NOT NULL DEFAULT 0,
              `enabled` TINYINT(1) NOT NULL DEFAULT 1,
              PRIMARY KEY (`ore_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='채굴 광물 정의'
        ");

        @db_query("
            CREATE TABLE IF NOT EXISTS `tb_mining_ore_find` (
              `idx` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `nick` VARCHAR(32) NOT NULL,
              `ore_key` VARCHAR(32) NOT NULL,
              `qty` TINYINT UNSIGNED NOT NULL DEFAULT 1,
              `pending_value` DECIMAL(24,10) NOT NULL DEFAULT 0,
              `weapon_item` VARCHAR(50) DEFAULT NULL,
              `weapon_enhance` TINYINT UNSIGNED NOT NULL DEFAULT 0,
              `status` ENUM('pending','claimed','expired') NOT NULL DEFAULT 'pending',
              `found_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `expire_at` DATETIME NOT NULL,
              `claimed_at` DATETIME DEFAULT NULL,
              PRIMARY KEY (`idx`),
              KEY `idx_nick_status_expire` (`nick`, `status`, `expire_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='채굴 광물 발견 (터치 대기)'
        ");

        @db_query("
            CREATE TABLE IF NOT EXISTS `tb_mining_ore_log` (
              `idx` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `nick` VARCHAR(32) NOT NULL,
              `find_idx` BIGINT UNSIGNED DEFAULT NULL,
              `ore_key` VARCHAR(32) NOT NULL,
              `event` ENUM('found','claimed','expired') NOT NULL,
              `qty` TINYINT UNSIGNED NOT NULL DEFAULT 1,
              `pending_added` DECIMAL(24,10) NOT NULL DEFAULT 0,
              `weapon_item` VARCHAR(50) DEFAULT NULL,
              `weapon_enhance` TINYINT UNSIGNED NOT NULL DEFAULT 0,
              `shard_after` TINYINT UNSIGNED DEFAULT NULL,
              `memo` VARCHAR(255) DEFAULT NULL,
              `regdate` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`idx`),
              KEY `idx_nick_regdate` (`nick`, `regdate`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='채굴 광물 이벤트 로그'
        ");

        mining_ore_seed_defs();
        mining_ore_ensure_member_columns();
        mining_ore_ensure_find_columns();
    }
}

if (!function_exists('mining_ore_ensure_find_columns')) {
    function mining_ore_ensure_find_columns(): void {
        foreach ([
            'pos_x_pct' => "DECIMAL(5,2) DEFAULT NULL COMMENT '채굴장면 X 위치 %'",
            'pos_y_pct' => "DECIMAL(5,2) DEFAULT NULL COMMENT '채굴장면 Y 위치 %'",
        ] as $col => $def) {
            $exists = @db_select("SHOW COLUMNS FROM `tb_mining_ore_find` LIKE '{$col}'");
            if (empty($exists)) {
                @db_query("ALTER TABLE `tb_mining_ore_find` ADD COLUMN `{$col}` {$def}");
            }
        }
    }
}

if (!function_exists('mining_ore_pending_spawn_positions')) {
    /** @return list<array{x:float,y:float}> */
    function mining_ore_pending_spawn_positions($nick, int $exclude_idx = 0): array {
        mining_ore_ensure_find_columns();
        $nick_esc = mining_data_nick_esc($nick);
        if ($nick_esc === '') {
            return [];
        }
        $exclude_sql = $exclude_idx > 0 ? " AND f.idx <> {$exclude_idx}" : '';
        $rs = @db_query("
            SELECT f.pos_x_pct, f.pos_y_pct
            FROM tb_mining_ore_find f
            WHERE f.nick = '{$nick_esc}'
              AND f.status = 'pending'
              AND f.expire_at > NOW()
              AND f.pos_x_pct IS NOT NULL
              AND f.pos_y_pct IS NOT NULL
              {$exclude_sql}
        ");
        $out = [];
        if ($rs) {
            while ($row = db_fetch($rs)) {
                $x = (float)($row['pos_x_pct'] ?? 0);
                $y = (float)($row['pos_y_pct'] ?? 0);
                if ($x > 0 && $y > 0) {
                    $out[] = ['x' => $x, 'y' => $y];
                }
            }
        }
        return $out;
    }
}

if (!function_exists('mining_ore_pick_spawn_position')) {
    /** @param list<array{x:float,y:float}> $existing */
    function mining_ore_pick_spawn_position(array $existing = []): array {
        $pad_x = 8.0;
        $pad_y = 10.0;
        $max_x = 92.0;
        $max_y = 88.0;
        $avoid = ['x_min' => 38.0, 'x_max' => 62.0, 'y_min' => 28.0, 'y_max' => 72.0];
        $min_dist = 14.0;

        for ($attempt = 0; $attempt < 30; $attempt++) {
            $x = $pad_x + (mt_rand(0, (int)(($max_x - $pad_x) * 100)) / 100.0);
            $y = $pad_y + (mt_rand(0, (int)(($max_y - $pad_y) * 100)) / 100.0);
            if ($x >= $avoid['x_min'] && $x <= $avoid['x_max'] && $y >= $avoid['y_min'] && $y <= $avoid['y_max']) {
                continue;
            }
            $ok = true;
            foreach ($existing as $pos) {
                $dx = $x - (float)($pos['x'] ?? 0);
                $dy = $y - (float)($pos['y'] ?? 0);
                if (sqrt($dx * $dx + $dy * $dy) < $min_dist) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                return ['x' => round($x, 2), 'y' => round($y, 2)];
            }
        }

        return [
            'x' => round($pad_x + (mt_rand(0, (int)(($max_x - $pad_x) * 100)) / 100.0), 2),
            'y' => round($pad_y + (mt_rand(0, (int)(($max_y - $pad_y) * 100)) / 100.0), 2),
        ];
    }
}

if (!function_exists('mining_ore_ensure_find_spawn_pos')) {
    function mining_ore_ensure_find_spawn_pos(array $row): array {
        $x = $row['pos_x_pct'] ?? null;
        $y = $row['pos_y_pct'] ?? null;
        if ($x !== null && $y !== null && (float)$x > 0 && (float)$y > 0) {
            return $row;
        }

        $idx = (int)($row['idx'] ?? 0);
        $nick = (string)($row['nick'] ?? '');
        if ($idx <= 0 || $nick === '') {
            return $row;
        }

        $pos = mining_ore_pick_spawn_position(mining_ore_pending_spawn_positions($nick, $idx));
        $x_sql = number_format($pos['x'], 2, '.', '');
        $y_sql = number_format($pos['y'], 2, '.', '');
        @db_query("
            UPDATE tb_mining_ore_find
            SET pos_x_pct = {$x_sql}, pos_y_pct = {$y_sql}
            WHERE idx = {$idx}
            LIMIT 1
        ");
        $row['pos_x_pct'] = $pos['x'];
        $row['pos_y_pct'] = $pos['y'];
        return $row;
    }
}

if (!function_exists('mining_ore_seed_defs')) {
    function mining_ore_seed_defs(): void {
        $rows = [
            ['copper',         '🟤', '구리',     '0.1', 1],
            ['iron',           '⚙️', '철',       '1',   2],
            ['silver',         '🥈', '은',       '3',   3],
            ['gold',           '🥇', '금',       '5',   4],
            ['diamond',        '💎', '다이아',   '10',  5],
            ['eunchong_shard', '✨', '은총조각', '0',   6],
        ];
        foreach ($rows as $r) {
            $key = addslashes($r[0]);
            $icon = addslashes($r[1]);
            $label = addslashes($r[2]);
            $sell = addslashes($r[3]);
            $ord = (int)$r[4];
            @db_query("
                INSERT INTO tb_mining_ore_def (ore_key, icon, label, sell_newpoint, sort_order, enabled)
                VALUES ('{$key}', '{$icon}', '{$label}', {$sell}, {$ord}, 1)
                ON DUPLICATE KEY UPDATE
                  icon = VALUES(icon),
                  label = VALUES(label),
                  sell_newpoint = VALUES(sell_newpoint),
                  sort_order = VALUES(sort_order),
                  enabled = 1
            ");
        }
    }
}

if (!function_exists('mining_ore_ensure_member_columns')) {
    function mining_ore_ensure_member_columns(): void {
        $tbl = MINING_TABLE;
        foreach ([
            'mining_ore_roll_acc' => "DECIMAL(24,10) NOT NULL DEFAULT 0 COMMENT '광물 판정 누적(legacy)'",
            'mining_ore_roll_targets' => "VARCHAR(128) NOT NULL DEFAULT '' COMMENT '60분 창 spawn offset JSON(초)'",
            'mining_eunchong_shard' => "TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '은총조각'",
            'mining_ore_next_spawn_at' => "DATETIME DEFAULT NULL COMMENT '다음 광물 spawn 예정'",
            'mining_ore_window_start' => "DATETIME DEFAULT NULL COMMENT '현재 60분 창 시작'",
        ] as $col => $def) {
            $exists = @db_select("SHOW COLUMNS FROM `{$tbl}` LIKE '{$col}'");
            if (empty($exists)) {
                @db_query("ALTER TABLE `{$tbl}` ADD COLUMN `{$col}` {$def}");
            }
        }
    }
}

if (!function_exists('mining_ore_def')) {
    function mining_ore_def($ore_key) {
        mining_ore_ensure_schema();
        $key_esc = addslashes(trim((string)$ore_key));
        if ($key_esc === '') {
            return null;
        }
        return db_select("
            SELECT ore_key, icon, label, sell_newpoint, sort_order
            FROM tb_mining_ore_def
            WHERE ore_key = '{$key_esc}' AND enabled = 1
            LIMIT 1
        ");
    }
}

if (!function_exists('mining_ore_discover_rate_pct')) {
    /** @deprecated mining_ore_hourly_find_count 사용 */
    function mining_ore_discover_rate_pct(int $enhance): int {
        return mining_ore_hourly_find_count($enhance);
    }
}

if (!function_exists('mining_ore_weight_table')) {
    /**
     * @return array<string,int> ore_key => weight (0 = 미해금)
     */
    function mining_ore_weight_table(int $enhance): array {
        if ($enhance < 10) {
            return [];
        }
        if ($enhance > 20) {
            $enhance = 20;
        }

        $shard_w = 5;
        if ($enhance >= 13) {
            $shard_w = 8;
        }
        if ($enhance >= 16) {
            $shard_w = 12;
        }
        if ($enhance >= 18) {
            $shard_w = 15;
        }
        if ($enhance >= 20) {
            $shard_w = 20;
        }

        $weights = [
            'copper' => 0,
            'iron' => 0,
            'silver' => 0,
            'gold' => 0,
            'diamond' => 0,
            'eunchong_shard' => $shard_w,
        ];

        if ($enhance <= 11) {
            $weights['copper'] = 1000 - $shard_w;
            return $weights;
        }
        if ($enhance <= 13) {
            $weights['copper'] = 700;
            $weights['iron'] = 300 - $shard_w;
            return $weights;
        }
        if ($enhance <= 15) {
            $weights['copper'] = 680;
            $weights['iron'] = 220;
            $weights['silver'] = 100 - $shard_w;
            return $weights;
        }
        if ($enhance <= 17) {
            $weights['copper'] = 450;
            $weights['iron'] = 280;
            $weights['silver'] = 180;
            $weights['gold'] = 90 - $shard_w;
            return $weights;
        }
        if ($enhance <= 19) {
            $weights['copper'] = 300;
            $weights['iron'] = 250;
            $weights['silver'] = 220;
            $weights['gold'] = 150;
            $weights['diamond'] = 60 - $shard_w;
            return $weights;
        }

        $weights['copper'] = 300;
        $weights['iron'] = 250;
        $weights['silver'] = 220;
        $weights['gold'] = 150;
        $weights['diamond'] = 55;
        return $weights;
    }
}

if (!function_exists('mining_ore_pick_key')) {
    function mining_ore_pick_key(int $enhance): ?string {
        $weights = mining_ore_weight_table($enhance);
        $total = 0;
        foreach ($weights as $w) {
            if ($w > 0) {
                $total += $w;
            }
        }
        if ($total <= 0) {
            return null;
        }
        $roll = random_int(1, $total);
        $acc = 0;
        foreach ($weights as $key => $w) {
            if ($w <= 0) {
                continue;
            }
            $acc += $w;
            if ($roll <= $acc) {
                return $key;
            }
        }
        return 'copper';
    }
}

if (!function_exists('mining_ore_find_message')) {
    function mining_ore_find_message(array $def, int $qty = 1): string {
        $icon = trim((string)($def['icon'] ?? ''));
        $label = trim((string)($def['label'] ?? ''));
        return '⛏️ ' . $icon . $label . ' ' . max(1, $qty) . '개 발견!';
    }
}

if (!function_exists('mining_ore_log_insert')) {
    function mining_ore_log_insert(
        $nick,
        $ore_key,
        $event,
        $find_idx = null,
        $qty = 1,
        $pending_added = 0,
        $weapon_item = '',
        $weapon_enhance = 0,
        $shard_after = null,
        $memo = ''
    ): void {
        mining_ore_ensure_schema();
        $nick_esc = mining_data_nick_esc($nick);
        $ore_esc = addslashes(trim((string)$ore_key));
        $event_esc = addslashes(trim((string)$event));
        $weapon_esc = addslashes(trim((string)$weapon_item));
        $memo_esc = addslashes(trim((string)$memo));
        $find_sql = ($find_idx !== null && (int)$find_idx > 0) ? (int)$find_idx : 'NULL';
        $shard_sql = ($shard_after !== null) ? (int)$shard_after : 'NULL';
        $pending_sql = mining_pending_sql((float)$pending_added);
        $qty = max(1, (int)$qty);
        $enh = max(0, (int)$weapon_enhance);

        @db_query("
            INSERT INTO tb_mining_ore_log (
                nick, find_idx, ore_key, event, qty, pending_added,
                weapon_item, weapon_enhance, shard_after, memo, regdate
            ) VALUES (
                '{$nick_esc}', {$find_sql}, '{$ore_esc}', '{$event_esc}', {$qty}, {$pending_sql},
                '{$weapon_esc}', {$enh}, {$shard_sql}, '{$memo_esc}', NOW()
            )
        ");
    }
}

if (!function_exists('mining_ore_expire_pending')) {
    /** @return int 만료 처리 건수 */
    function mining_ore_expire_pending($nick): int {
        mining_ore_ensure_schema();
        $nick_esc = mining_data_nick_esc($nick);
        if ($nick_esc === '') {
            return 0;
        }

        $rs = @db_query("
            SELECT idx, ore_key, qty, weapon_item, weapon_enhance
            FROM tb_mining_ore_find
            WHERE nick = '{$nick_esc}'
              AND status = 'pending'
              AND expire_at <= NOW()
        ");
        if (!$rs) {
            return 0;
        }

        $count = 0;
        while ($row = db_fetch($rs)) {
            $idx = (int)($row['idx'] ?? 0);
            if ($idx <= 0) {
                continue;
            }
            db_query("
                UPDATE tb_mining_ore_find
                SET status = 'expired'
                WHERE idx = {$idx} AND status = 'pending'
                LIMIT 1
            ");
            mining_ore_log_insert(
                $nick,
                $row['ore_key'] ?? '',
                'expired',
                $idx,
                (int)($row['qty'] ?? 1),
                0,
                $row['weapon_item'] ?? '',
                (int)($row['weapon_enhance'] ?? 0),
                null,
                '1시간 미터치 소멸'
            );
            $count++;
        }
        return $count;
    }
}

if (!function_exists('mining_ore_create_find')) {
    function mining_ore_create_find($nick, $ore_key, $weapon_item, int $weapon_enhance, int $qty = 1, ?string $found_at = null): ?array {
        mining_ore_ensure_schema();
        $def = mining_ore_def($ore_key);
        if (empty($def)) {
            return null;
        }

        $nick_esc = mining_data_nick_esc($nick);
        $ore_esc = addslashes(trim((string)$ore_key));
        $weapon_esc = addslashes(trim((string)$weapon_item));
        $qty = max(1, $qty);
        $sell = (float)($def['sell_newpoint'] ?? 0);
        $pending_value = mining_pending_round($sell * $qty);
        $pending_sql = mining_pending_sql($pending_value);
        $ttl = max(60, (int)MINING_ORE_FIND_TTL_SEC);
        $enh = max(0, $weapon_enhance);

        if ($found_at !== null && trim($found_at) !== '') {
            $found_ts = strtotime($found_at);
            if ($found_ts === false || $found_ts <= 0) {
                $found_ts = time();
            }
            $found_sql = "'" . addslashes(date('Y-m-d H:i:s', $found_ts)) . "'";
            $expire_sql = "DATE_ADD({$found_sql}, INTERVAL {$ttl} SECOND)";
        } else {
            $found_sql = 'NOW()';
            $expire_sql = "DATE_ADD(NOW(), INTERVAL {$ttl} SECOND)";
        }

        $spawn_pos = mining_ore_pick_spawn_position(mining_ore_pending_spawn_positions($nick));
        $pos_x_sql = number_format($spawn_pos['x'], 2, '.', '');
        $pos_y_sql = number_format($spawn_pos['y'], 2, '.', '');

        db_query("
            INSERT INTO tb_mining_ore_find (
                nick, ore_key, qty, pending_value, weapon_item, weapon_enhance,
                status, found_at, expire_at, pos_x_pct, pos_y_pct
            ) VALUES (
                '{$nick_esc}', '{$ore_esc}', {$qty}, {$pending_sql}, '{$weapon_esc}', {$enh},
                'pending', {$found_sql}, {$expire_sql}, {$pos_x_sql}, {$pos_y_sql}
            )
        ");

        global $conn;
        $find_idx = ($conn instanceof mysqli) ? (int)mysqli_insert_id($conn) : 0;
        if ($find_idx <= 0) {
            $last = db_select("SELECT idx FROM tb_mining_ore_find WHERE nick = '{$nick_esc}' ORDER BY idx DESC LIMIT 1");
            $find_idx = (int)($last['idx'] ?? 0);
        }

        mining_ore_log_insert(
            $nick,
            $ore_key,
            'found',
            $find_idx,
            $qty,
            0,
            $weapon_item,
            $weapon_enhance,
            null,
            mining_ore_find_message($def, $qty)
        );

        return mining_ore_find_row_by_idx($find_idx, $nick);
    }
}

if (!function_exists('mining_ore_find_row_by_idx')) {
    function mining_ore_find_row_by_idx($idx, $nick = null) {
        mining_ore_ensure_schema();
        $idx = (int)$idx;
        if ($idx <= 0) {
            return null;
        }
        $nick_sql = '';
        if ($nick !== null) {
            $nick_esc = mining_data_nick_esc($nick);
            if ($nick_esc !== '') {
                $nick_sql = " AND f.nick = '{$nick_esc}'";
            }
        }
        return db_select("
            SELECT f.*, d.icon, d.label, d.sell_newpoint
            FROM tb_mining_ore_find f
            LEFT JOIN tb_mining_ore_def d ON d.ore_key = f.ore_key
            WHERE f.idx = {$idx}{$nick_sql}
            LIMIT 1
        ");
    }
}

if (!function_exists('mining_ore_find_payload')) {
    function mining_ore_find_payload(array $row): array {
        $def = [
            'icon' => $row['icon'] ?? '',
            'label' => $row['label'] ?? '',
        ];
        $qty = max(1, (int)($row['qty'] ?? 1));
        $expire_ts = strtotime((string)($row['expire_at'] ?? ''));
        $left = $expire_ts > 0 ? max(0, $expire_ts - time()) : 0;
        return [
            'idx' => (int)($row['idx'] ?? 0),
            'ore_key' => (string)($row['ore_key'] ?? ''),
            'icon' => (string)($def['icon'] ?? ''),
            'label' => (string)($def['label'] ?? ''),
            'qty' => $qty,
            'pending_value' => (float)($row['pending_value'] ?? 0),
            'pending_value_fmt' => mining_fmt_ore_value($row['pending_value'] ?? 0),
            'message' => mining_ore_find_message([
                'icon' => $def['icon'] ?? '',
                'label' => $def['label'] ?? '',
            ], $qty),
            'expire_at' => (string)($row['expire_at'] ?? ''),
            'left_sec' => $left,
            'is_shard' => (($row['ore_key'] ?? '') === 'eunchong_shard'),
            'pos_x_pct' => (float)($row['pos_x_pct'] ?? 0),
            'pos_y_pct' => (float)($row['pos_y_pct'] ?? 0),
        ];
    }
}

if (!function_exists('mining_fmt_ore_value')) {
    function mining_fmt_ore_value($n): string {
        if (function_exists('mining_fmt_pending')) {
            return mining_fmt_pending($n);
        }
        $s = sprintf('%.10f', (float)$n);
        return rtrim(rtrim($s, '0'), '.');
    }
}

if (!function_exists('mining_ore_pending_finds')) {
    function mining_ore_pending_finds($nick): array {
        mining_ore_expire_pending($nick);
        $nick_esc = mining_data_nick_esc($nick);
        if ($nick_esc === '') {
            return [];
        }
        $rs = @db_query("
            SELECT f.*, d.icon, d.label, d.sell_newpoint
            FROM tb_mining_ore_find f
            LEFT JOIN tb_mining_ore_def d ON d.ore_key = f.ore_key
            WHERE f.nick = '{$nick_esc}'
              AND f.status = 'pending'
              AND f.expire_at > NOW()
            ORDER BY f.idx ASC
        ");
        $out = [];
        if ($rs) {
            while ($row = db_fetch($rs)) {
                $row = mining_ore_ensure_find_spawn_pos($row);
                $out[] = mining_ore_find_payload($row);
            }
        }
        return $out;
    }
}

if (!function_exists('mining_ore_next_spawn_preview')) {
    /**
     * 다음 광물 등장까지 남은 초 (스케줄 확정 시에만)
     *
     * @return array{ore_next_in_sec:?int,ore_has_schedule:bool}
     */
    function mining_ore_next_spawn_preview($nick): array {
        $empty = ['ore_next_in_sec' => null, 'ore_has_schedule' => false];
        $ctx = mining_ore_roll_context($nick);
        if (!$ctx['ok']) {
            return $empty;
        }
        if (!mining_data_ensure_row($nick)) {
            return $empty;
        }
        mining_ore_ensure_member_columns();
        $nick_esc = mining_data_nick_esc($nick);
        if ($nick_esc === '') {
            return $empty;
        }
        $row = db_select("
            SELECT mining_ore_next_spawn_at
            FROM `" . MINING_TABLE . "`
            WHERE nick = '{$nick_esc}'
            LIMIT 1
        ");
        $next_at = trim((string)($row['mining_ore_next_spawn_at'] ?? ''));
        if ($next_at === '') {
            return $empty;
        }
        $left = strtotime($next_at) - time();
        return [
            'ore_next_in_sec' => $left > 0 ? (int)$left : 0,
            'ore_has_schedule' => true,
        ];
    }
}

if (!function_exists('mining_ore_member_state')) {
    function mining_ore_member_state($nick): array {
        mining_ore_ensure_schema();
        mining_ore_expire_pending($nick);
        $nick_esc = mining_data_nick_esc($nick);
        $shard = 0;
        if ($nick_esc !== '') {
            $row = db_select("
                SELECT IFNULL(mining_eunchong_shard, 0) AS shard
                FROM `" . MINING_TABLE . "`
                WHERE nick = '{$nick_esc}'
                LIMIT 1
            ");
            $shard = (int)($row['shard'] ?? 0);
        }
        $ctx = mining_ore_roll_context($nick);
        $tool_lv = (int)($ctx['tool_level'] ?? 0);
        if ($ctx['ok']) {
            if (!function_exists('mining_ore_schedule_init')) {
                require_once __DIR__ . '/mining_ore_cron.inc.php';
            }
            $sched = mining_ore_schedule_row($nick);
            if (empty($sched['mining_ore_next_spawn_at'])) {
                mining_ore_schedule_init($nick);
            }
        }
        $hourly_cnt = $ctx['ok'] ? mining_ore_effective_hourly_find_count((int)$ctx['enhance'], $tool_lv) : 0;
        $discover_hint = $hourly_cnt > 0 ? mining_ore_discover_hint((int)$ctx['enhance'], $tool_lv) : '';
        $spawn_preview = mining_ore_next_spawn_preview($nick);
        return [
            'eunchong_shard' => $shard,
            'eunchong_shard_need' => (int)MINING_ORE_EUNCHONG_SHARDS,
            'ore_finds' => mining_ore_pending_finds($nick),
            'ore_hourly_find_count' => $hourly_cnt,
            'ore_discover_hint' => $discover_hint,
            'ore_find_ttl_sec' => (int)MINING_ORE_FIND_TTL_SEC,
            'ore_next_in_sec' => $spawn_preview['ore_next_in_sec'],
            'ore_has_schedule' => !empty($spawn_preview['ore_has_schedule']),
        ];
    }
}

if (!function_exists('mining_ore_roll_context')) {
    /** @return array{ok:bool,item:string,enhance:int,equipped:bool,tool_level:int} */
    function mining_ore_roll_context($nick): array {
        $tool_level = mining_tool_level_for_nick($nick);
        if (!mining_weapon_equipped($nick)) {
            return ['ok' => false, 'item' => '', 'enhance' => 0, 'equipped' => false, 'tool_level' => $tool_level];
        }
        $member = mining_weapon_member_row($nick);
        $item = trim((string)($member['item'] ?? ''));
        $enhance = (int)($member['enhance'] ?? 0);
        if ($item === '' || $enhance < 10) {
            return ['ok' => false, 'item' => $item, 'enhance' => $enhance, 'equipped' => true, 'tool_level' => $tool_level];
        }
        return ['ok' => true, 'item' => $item, 'enhance' => $enhance, 'equipped' => true, 'tool_level' => $tool_level];
    }
}

if (!function_exists('mining_ore_try_create_find')) {
    function mining_ore_try_create_find($nick, $weapon_item, int $weapon_enhance, ?string $found_at = null): ?array {
        return mining_ore_try_create_find_at($nick, $weapon_item, $weapon_enhance, $found_at);
    }
}

if (!function_exists('mining_ore_try_create_find_at')) {
    function mining_ore_try_create_find_at($nick, $weapon_item, int $weapon_enhance, ?string $found_at = null): ?array {
        $ore_key = mining_ore_pick_key($weapon_enhance);
        if ($ore_key === null) {
            return null;
        }
        $find = mining_ore_create_find($nick, $ore_key, $weapon_item, $weapon_enhance, 1, $found_at);
        return $find ? mining_ore_find_payload($find) : null;
    }
}

if (!function_exists('mining_ore_try_roll_once')) {
    /** @deprecated 24h 확률 판정은 mining_ore_on_elapsed 에서 처리 */
    function mining_ore_try_roll_once($nick, $weapon_item, int $weapon_enhance): ?array {
        $pct = mining_ore_daily_discover_pct($weapon_enhance);
        if ($pct <= 0 || random_int(1, 100) > $pct) {
            return null;
        }
        return mining_ore_try_create_find($nick, $weapon_item, $weapon_enhance);
    }
}

if (!function_exists('mining_ore_on_elapsed')) {
    /**
     * @deprecated 광물 생성은 mining_ore_cron_run() — sync 경과와 분리
     * @return list<array>
     */
    function mining_ore_on_elapsed($nick, int $elapsed_sec): array {
        if ($elapsed_sec > 0 && function_exists('mining_ore_schedule_init')) {
            require_once __DIR__ . '/mining_ore_cron.inc.php';
            if (mining_weapon_equipped($nick)) {
                mining_ore_schedule_init($nick);
            }
        }
        return [];
    }
}

if (!function_exists('mining_ore_on_mined')) {
    /** @deprecated mining_ore_on_elapsed 사용 */
    function mining_ore_on_mined($nick, $delta_amount): array {
        return [];
    }
}

if (!function_exists('mining_ore_add_shards')) {
    /** @return array{shard:int, eunchong_granted:int} */
    function mining_ore_add_shards($nick, int $add): array {
        mining_ore_ensure_schema();
        $add = max(0, $add);
        $need = max(1, (int)MINING_ORE_EUNCHONG_SHARDS);
        $nick_esc = mining_data_nick_esc($nick);
        if ($nick_esc === '' || $add <= 0) {
            return ['shard' => 0, 'eunchong_granted' => 0];
        }

        mining_data_ensure_row($nick);
        $row = db_select("
            SELECT IFNULL(mining_eunchong_shard, 0) AS shard
            FROM `" . MINING_TABLE . "`
            WHERE nick = '{$nick_esc}'
            LIMIT 1
        ");
        $shard = (int)($row['shard'] ?? 0) + $add;
        $granted = 0;

        while ($shard >= $need) {
            $shard -= $need;
            $granted++;
        }

        db_query("
            UPDATE `" . MINING_TABLE . "`
            SET mining_eunchong_shard = {$shard}
            WHERE nick = '{$nick_esc}'
            LIMIT 1
        ");

        if ($granted > 0) {
            db_query("
                UPDATE tb_member
                SET 은총개수 = IFNULL(은총개수, 0) + {$granted}
                WHERE name = '{$nick_esc}'
                LIMIT 1
            ");
        }

        return ['shard' => $shard, 'eunchong_granted' => $granted];
    }
}

if (!function_exists('mining_ore_touch_execute')) {
    function mining_ore_touch_execute($nick, $find_idx, $lease_token = '') {
        mining_ore_ensure_schema();
        if (!function_exists('mining_sync_commit_elapsed')) {
            require_once __DIR__ . '/mining_sync.inc.php';
        }
        mining_sync_commit_elapsed($nick);
        mining_ore_expire_pending($nick);

        $find_idx = (int)$find_idx;
        if ($find_idx <= 0) {
            return ['ok' => false, 'data' => '잘못된 발견입니다.'];
        }

        $row = mining_ore_find_row_by_idx($find_idx, $nick);
        if (empty($row) || ($row['status'] ?? '') !== 'pending') {
            return ['ok' => false, 'data' => '이미 수령했거나 소멸한 광물이에요.'];
        }
        if (strtotime((string)($row['expire_at'] ?? '')) <= time()) {
            mining_ore_expire_pending($nick);
            return ['ok' => false, 'data' => '1시간이 지나 소멸했어요.'];
        }

        $nick_esc = mining_data_nick_esc($nick);
        $ore_key = (string)($row['ore_key'] ?? '');
        $qty = max(1, (int)($row['qty'] ?? 1));
        $pending_add = (float)($row['pending_value'] ?? 0);
        $weapon_item = (string)($row['weapon_item'] ?? '');
        $weapon_enhance = (int)($row['weapon_enhance'] ?? 0);

        global $conn;
        db_query("
            UPDATE tb_mining_ore_find
            SET status = 'claimed', claimed_at = NOW()
            WHERE idx = {$find_idx}
              AND nick = '{$nick_esc}'
              AND status = 'pending'
            LIMIT 1
        ");
        $claimed = ($conn instanceof mysqli) ? ((int)mysqli_affected_rows($conn) > 0) : true;
        if (!$claimed) {
            return ['ok' => false, 'data' => '수령에 실패했어요.'];
        }

        $shard_after = null;
        $memo = '';
        $msg = '';

        if ($ore_key === 'eunchong_shard') {
            $shard_result = mining_ore_add_shards($nick, $qty);
            $shard_after = (int)$shard_result['shard'];
            $granted = (int)$shard_result['eunchong_granted'];
            $need = (int)MINING_ORE_EUNCHONG_SHARDS;
            $msg = '✨ 은총조각 ' . $qty . '개 수령! (' . $shard_after . '/' . $need . ')';
            if ($granted > 0) {
                $msg .= "\n🎁 은총조각 " . ($granted * $need) . "개 → 은총 {$granted}개!";
            }
            $memo = '은총조각 터치';
        } else {
            if ($pending_add > 0) {
                $tbl = MINING_TABLE;
                $add_sql = mining_pending_sql($pending_add);
                db_query("
                    UPDATE `{$tbl}`
                    SET mining_pending = IFNULL(mining_pending, 0) + {$add_sql},
                        mining_sync_at = NOW()
                    WHERE nick = '{$nick_esc}'
                    LIMIT 1
                ");
            }
            $def = mining_ore_def($ore_key);
            $icon = $def['icon'] ?? '';
            $label = $def['label'] ?? $ore_key;
            $msg = $icon . $label . ' +' . mining_fmt_ore_value($pending_add) . '냥이 채굴량에 더해졌어요!';
            $memo = '채굴량 가산';
        }

        mining_ore_log_insert(
            $nick,
            $ore_key,
            'claimed',
            $find_idx,
            $qty,
            $pending_add,
            $weapon_item,
            $weapon_enhance,
            $shard_after,
            $memo
        );

        $sync_read = function_exists('mining_sync_read')
            ? mining_sync_read($nick, trim((string)$lease_token))
            : ['sync' => null];

        return array_merge([
            'ok' => true,
            'data' => $msg,
        ], mining_ore_member_state($nick), [
            'sync' => $sync_read['sync'] ?? null,
        ]);
    }
}

if (!function_exists('mining_ore_def_sell_map')) {
    /** @return array<string,float> */
    function mining_ore_def_sell_map(): array {
        return [
            'copper' => 0.1,
            'iron' => 1.0,
            'silver' => 3.0,
            'gold' => 5.0,
            'diamond' => 10.0,
            'eunchong_shard' => 0.0,
        ];
    }
}

if (!function_exists('mining_ore_catalog')) {
    /**
     * @return list<array{key:string,icon:string,label:string,sell_value:float,sell_value_fmt:string}>
     */
    function mining_ore_catalog(): array {
        $sells = mining_ore_def_sell_map();
        $defs = [
            ['copper', '🟤', '구리'],
            ['iron', '⚙️', '철'],
            ['silver', '🥈', '은'],
            ['gold', '🥇', '금'],
            ['diamond', '💎', '다이아'],
            ['eunchong_shard', '✨', '은총조각'],
        ];
        $out = [];
        foreach ($defs as $d) {
            $val = (float)($sells[$d[0]] ?? 0);
            $out[] = [
                'key' => $d[0],
                'icon' => $d[1],
                'label' => $d[2],
                'sell_value' => $val,
                'sell_value_fmt' => $val > 0
                    ? '+' . mining_ore_fmt_amount($val) . '냥'
                    : '—',
            ];
        }
        return $out;
    }
}

if (!function_exists('mining_ore_pct_fmt')) {
    function mining_ore_pct_fmt(float $pct): string {
        if ($pct < 0.05) {
            return '—';
        }
        $s = rtrim(rtrim(number_format($pct, 1, '.', ''), '0'), '.');
        return $s . '%';
    }
}

if (!function_exists('mining_ore_weapon_drop_matrix')) {
    /**
     * 강화별 광물 종류 확률 표
     *
     * @return array{catalog:list<array>,rows:list<array>}
     */
    function mining_ore_weapon_drop_matrix(): array {
        $catalog = mining_ore_catalog();
        $rows = [];
        for ($enh = 10; $enh <= 20; $enh++) {
            $stats = mining_ore_expected_find_stats($enh);
            $cells = [];
            foreach ($catalog as $ore) {
                $key = $ore['key'];
                $drop = $stats['drops'][$key] ?? null;
                $pct = $drop ? (float)$drop['pct'] : 0.0;
                $cells[$key] = [
                    'pct' => $pct,
                    'pct_fmt' => mining_ore_pct_fmt($pct),
                    'unlocked' => $pct >= 0.05,
                ];
            }
            $rows[] = [
                'enhance' => $enh,
                'hourly_count' => mining_ore_hourly_find_count($enh),
                'cells' => $cells,
            ];
        }
        return ['catalog' => $catalog, 'rows' => $rows];
    }
}

if (!function_exists('mining_ore_expected_find_stats')) {
    /**
     * @return array{avg_value:float,total_weight:int,drops:array<string,array{weight:int,pct:float,value:float}>}
     */
    function mining_ore_expected_find_stats(int $enhance): array {
        $weights = mining_ore_weight_table($enhance);
        $sells = mining_ore_def_sell_map();
        $total = 0;
        foreach ($weights as $w) {
            if ($w > 0) {
                $total += $w;
            }
        }
        $drops = [];
        $avg = 0.0;
        if ($total > 0) {
            foreach ($weights as $key => $w) {
                if ($w <= 0) {
                    continue;
                }
                $val = (float)($sells[$key] ?? 0);
                $drops[$key] = [
                    'weight' => $w,
                    'pct' => 100.0 * $w / $total,
                    'value' => $val,
                ];
                $avg += $w * $val;
            }
            $avg /= $total;
        }
        return ['avg_value' => $avg, 'total_weight' => $total, 'drops' => $drops];
    }
}

if (!function_exists('mining_ore_fmt_amount')) {
    function mining_ore_fmt_amount(float $v): string {
        if ($v >= 100) {
            return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
        }
        if ($v >= 0.01) {
            return rtrim(rtrim(number_format($v, 4, '.', ''), '0'), '.');
        }
        return rtrim(rtrim(number_format($v, 6, '.', ''), '0'), '.');
    }
}

if (!function_exists('mining_ore_weapon_bonus_drop_summary')) {
    function mining_ore_weapon_bonus_drop_summary(array $drops): string {
        static $icons = [
            'copper' => '🟤',
            'iron' => '⚙️',
            'silver' => '🥈',
            'gold' => '🥇',
            'diamond' => '💎',
            'eunchong_shard' => '✨',
        ];
        $parts = [];
        foreach ($drops as $key => $d) {
            if (($d['pct'] ?? 0) < 0.5) {
                continue;
            }
            $parts[] = ($icons[$key] ?? '') . (int)round($d['pct']) . '%';
        }
        return implode(' · ', $parts);
    }
}

if (!function_exists('mining_ore_weapon_bonus_table')) {
    /**
     * 무기 +10~+20 · 60분·24h 광물 기대 표
     *
     * @return array{rows:list<array>}
     */
    function mining_ore_weapon_bonus_table(): array {
        $hours_per_day = 24;
        $rows = [];
        for ($enh = 10; $enh <= 20; $enh++) {
            $hourly = mining_ore_hourly_find_count($enh);
            $stats = mining_ore_expected_find_stats($enh);
            $shard_pct = (float)($stats['drops']['eunchong_shard']['pct'] ?? 0);
            $hourly_bonus = $hourly * $stats['avg_value'];
            $daily_bonus = $hourly_bonus * $hours_per_day;

            $rows[] = [
                'enhance' => $enh,
                'hourly_count' => $hourly,
                'avg_find_value_fmt' => mining_ore_fmt_amount($stats['avg_value']),
                'hourly_bonus_fmt' => mining_ore_fmt_amount($hourly_bonus),
                'daily_bonus_fmt' => mining_ore_fmt_amount($daily_bonus),
                'daily_shards_fmt' => mining_ore_fmt_amount($hourly * $hours_per_day * ($shard_pct / 100.0)),
                'drop_summary' => mining_ore_weapon_bonus_drop_summary($stats['drops']),
            ];
        }

        return ['rows' => $rows];
    }
}

if (!function_exists('mining_ore_weapon_bonus_page_data')) {
    /** @return array{summary:array,drop_matrix:array} */
    function mining_ore_weapon_bonus_page_data(): array {
        return [
            'summary' => mining_ore_weapon_bonus_table(),
            'drop_matrix' => mining_ore_weapon_drop_matrix(),
        ];
    }
}
