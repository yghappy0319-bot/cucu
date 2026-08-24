<?php
/**
 * 채굴 광물 발견 — tb_mining_ore_* (tb_item 별도)
 *
 * 발견 조건: 무기 +10~ 장착 · 30분 창마다 (무기 강화별 N개 + 채굴장비 Lv 보너스) · 1분~창끝 랜덤
 * 생성: api/_auto_mining_ore.php 크론 (mining_ore_next_spawn_at) · 오프라인 포함
 * 만료: expire_at 경과 pending → expired (크론 일괄 + 페이지 조회 시 lazy)
 *
 * 터치: 판매가(본방냥 총합 × 광물별 min~max 비율 랜덤)를 mining_pending 에 가산 · 은총조각은 shard 카운트
 * 30분 미터치 시 expired · tb_mining_ore_log 기록
 * 종류 확률: mining_ore_weight_table_grantable — 표시 표와 동일 소스
 */

require_once __DIR__ . '/mining_storage.inc.php';
require_once __DIR__ . '/mining_weapon.inc.php';
require_once __DIR__ . '/mining_tool.inc.php';

if (!defined('MINING_ORE_WINDOW_SEC')) {
    /** 광물 출현 스케줄 창 (초) — 1800=30분 */
    define('MINING_ORE_WINDOW_SEC', 1800);
}

if (!function_exists('mining_ore_window_seconds')) {
    function mining_ore_window_seconds(): int {
        return max(1, (int)MINING_ORE_WINDOW_SEC);
    }
}

if (!function_exists('mining_ore_window_hours')) {
    /** 창 길이(시간, float) — 보너스 스케일 · 30분=0.5 */
    function mining_ore_window_hours(): float {
        return max(1.0 / 60.0, mining_ore_window_seconds() / 3600.0);
    }
}

if (!function_exists('mining_ore_windows_per_day')) {
    /** 하루 창 수 (예: 30분 창 → 48) */
    function mining_ore_windows_per_day(): float {
        $sec = mining_ore_window_seconds();
        return $sec > 0 ? (86400.0 / $sec) : 24.0;
    }
}

if (!function_exists('mining_ore_window_label')) {
    function mining_ore_window_label(): string {
        $sec = mining_ore_window_seconds();
        if ($sec < 3600) {
            $m = max(1, (int)round($sec / 60));
            return $m . '분';
        }
        $h = mining_ore_window_hours();
        if (abs($h - round($h)) < 1e-9) {
            return ((int)round($h)) . '시간';
        }
        return rtrim(rtrim(number_format($h, 2, '.', ''), '0'), '.') . '시간';
    }
}

if (!function_exists('mining_ore_ttl_label')) {
    /** 미터치 만료 표시 (예: 30분) */
    function mining_ore_ttl_label(): string {
        $sec = max(60, (int)MINING_ORE_FIND_TTL_SEC);
        if ($sec < 3600) {
            return max(1, (int)round($sec / 60)) . '분';
        }
        $h = $sec / 3600.0;
        if (abs($h - round($h)) < 1e-9) {
            return ((int)round($h)) . '시간';
        }
        return rtrim(rtrim(number_format($h, 2, '.', ''), '0'), '.') . '시간';
    }
}

if (!function_exists('mining_ore_enhance_decade')) {
    /** +1~10=1 … +91~100=10 */
    function mining_ore_enhance_decade(int $enhance): int {
        if ($enhance < 1) {
            return 0;
        }
        $max = function_exists('강화_최대') ? (int)강화_최대() : 100;
        $e = min(max(1, $enhance), max(1, $max));
        return (int)ceil($e / 10);
    }
}

if (!function_exists('mining_ore_daily_find_count')) {
    /**
     * 24h 광물 기대 개수(무기만) — 구간마다 다르게, +91~100=60
     * (기존 대비 약 1/2 · 본방냥 비율 지급과 맞춤)
     * +1~10:12 … +81~90:57 · +91~100:60
     */
    function mining_ore_daily_find_count(int $enhance): int {
        static $by_decade = [
            1 => 12,
            2 => 18,
            3 => 24,
            4 => 30,
            5 => 36,
            6 => 42,
            7 => 48,
            8 => 54,
            9 => 57,
            10 => 60,
        ];
        $decade = mining_ore_enhance_decade($enhance);
        return (int)($by_decade[$decade] ?? 0);
    }
}

if (!function_exists('mining_ore_hourly_find_expected')) {
    /**
     * 창당 광물 기대 개수(무기만) = 24h ÷ (하루 창 수)
     * 30분 창이면 ÷48 · 함수명 hourly 는 하위호환
     */
    function mining_ore_hourly_find_expected(int $enhance): float {
        $daily = mining_ore_daily_find_count($enhance);
        $wpd = mining_ore_windows_per_day();
        return ($daily > 0 && $wpd > 0) ? ($daily / $wpd) : 0.0;
    }
}

if (!function_exists('mining_ore_hourly_find_count')) {
    /**
     * 정수 호환(반올림). 스케줄은 mining_ore_roll_window_find_count 사용
     */
    function mining_ore_hourly_find_count(int $enhance): int {
        $e = mining_ore_hourly_find_expected($enhance);
        if ($e <= 0) {
            return 0;
        }
        return (int)max(0, (int)round($e));
    }
}

if (!function_exists('mining_ore_tool_bonus_count')) {
    /**
     * 채굴 장비 Lv 보너스 (창당)
     * 기준(1시간당): Lv2+1 · Lv5+2 · Lv9+3 · Lv12+4 · Lv13+5
     * 창이 N시간이면 ×N (하루 총량 유지)
     */
    function mining_ore_tool_bonus_count(int $tool_level): int {
        $tool_level = max(0, (int)$tool_level);
        if (function_exists('mining_tool_max_level')) {
            $tool_level = min($tool_level, max(0, (int)mining_tool_max_level()));
        }
        $perHour = 0;
        if ($tool_level >= 13) {
            $perHour = 5;
        } elseif ($tool_level >= 12) {
            $perHour = 4;
        } elseif ($tool_level >= 9) {
            $perHour = 3;
        } elseif ($tool_level >= 5) {
            $perHour = 2;
        } elseif ($tool_level >= 2) {
            $perHour = 1;
        }
        if ($perHour < 1) {
            return 0;
        }
        // 창 길이에 비례 (30분=0.5h · 소수 기대는 상위 roll에서 처리)
        $scaled = $perHour * mining_ore_window_hours();
        if ($scaled <= 0) {
            return 0;
        }
        // 창이 짧아 1 미만이어도 기대값 유지를 위해 0 허용 (effective_expected 가 float로 합산)
        return (int)max(0, (int)round($scaled));
    }
}

if (!function_exists('mining_ore_tool_bonus_expected')) {
    /** 창당 장비 보너스 기대(소수 포함) — 30분 창에서 Lv2=0.5 등 */
    function mining_ore_tool_bonus_expected(int $tool_level): float {
        $tool_level = max(0, (int)$tool_level);
        if (function_exists('mining_tool_max_level')) {
            $tool_level = min($tool_level, max(0, (int)mining_tool_max_level()));
        }
        $perHour = 0;
        if ($tool_level >= 13) {
            $perHour = 5;
        } elseif ($tool_level >= 12) {
            $perHour = 4;
        } elseif ($tool_level >= 9) {
            $perHour = 3;
        } elseif ($tool_level >= 5) {
            $perHour = 2;
        } elseif ($tool_level >= 2) {
            $perHour = 1;
        }
        if ($perHour < 1) {
            return 0.0;
        }
        return (float)$perHour * mining_ore_window_hours();
    }
}

if (!function_exists('mining_ore_effective_hourly_find_expected')) {
    /** 무기 기대 + 채굴 장비 Lv 보너스 */
    function mining_ore_effective_hourly_find_expected(int $enhance, int $tool_level = 0): float {
        $base = mining_ore_hourly_find_expected($enhance);
        if ($base <= 0) {
            return 0.0;
        }
        return $base + mining_ore_tool_bonus_expected($tool_level);
    }
}

if (!function_exists('mining_ore_roll_window_find_count')) {
    /** 이번 출현 창 실제 spawn 개수(기대값 floor + 소수 확률) */
    function mining_ore_roll_window_find_count(int $enhance, int $tool_level = 0): int {
        $e = mining_ore_effective_hourly_find_expected($enhance, $tool_level);
        if ($e <= 0) {
            return 0;
        }
        $n = (int)floor($e + 1e-9);
        $frac = $e - $n;
        if ($frac > 1e-9) {
            $thresh = (int)max(1, min(10000, (int)round($frac * 10000)));
            if (random_int(1, 10000) <= $thresh) {
                $n++;
            }
        }
        $n = max(0, $n);
        $cap = mining_ore_effective_hourly_find_count($enhance, $tool_level);
        if ($cap > 0) {
            $n = min($n, $cap);
        }
        return $n;
    }
}

if (!function_exists('mining_ore_effective_hourly_find_count')) {
    /** 무기 기본 + 채굴 장비 Lv 보너스 (창당 상한≈ceil) */
    function mining_ore_effective_hourly_find_count(int $enhance, int $tool_level = 0): int {
        $e = mining_ore_effective_hourly_find_expected($enhance, $tool_level);
        if ($e <= 0) {
            return 0;
        }
        return (int)max(1, (int)ceil($e - 1e-9));
    }
}

if (!function_exists('mining_ore_discover_hint')) {
    function mining_ore_discover_hint(int $enhance, int $tool_level = 0): string {
        $e = mining_ore_effective_hourly_find_expected($enhance, $tool_level);
        if ($e <= 0) {
            return '';
        }
        $cnt_fmt = mining_ore_fmt_count($e);
        $bonus = mining_ore_tool_bonus_count($tool_level);
        $win = mining_ore_window_label();
        $winMin = max(1, (int)round(mining_ore_window_seconds() / 60));
        if ($bonus > 0) {
            return $win . '당 광물 약 ' . $cnt_fmt . '개 (장비 +' . $bonus . ') · 1~' . $winMin . '분 랜덤';
        }
        return $win . '당 광물 약 ' . $cnt_fmt . '개 (1~' . $winMin . '분 랜덤)';
    }
}

if (!function_exists('mining_ore_fmt_count')) {
    /** 광물 개수 표시 (소수면 최대 2자리) */
    function mining_ore_fmt_count(float $v): string {
        if ($v <= 0) {
            return '0';
        }
        if (abs($v - round($v)) < 1e-9) {
            return (string)(int)round($v);
        }
        return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    }
}

if (!function_exists('mining_ore_roll_min_seconds')) {
    function mining_ore_roll_min_seconds(): int {
        return 60;
    }
}

if (!function_exists('mining_ore_roll_targets_uninitialized')) {
    /** DB 기본값 '' — 아직 이번 출현 창 스케줄 없음. '[]' 는 이번 창 소진 완료 */
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
     * 현재 출현 창 안에서 광물이 뜰 누적 초(1분~창 끝 랜덤) N개
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
    /** 출현 후 미터치 만료 (초) — 1800=30분 */
    define('MINING_ORE_FIND_TTL_SEC', 1800);
}
if (!defined('MINING_ORE_EUNCHONG_SHARDS')) {
    /** 은총조각 보유 상한 */
    define('MINING_ORE_EUNCHONG_SHARDS', 500);
}
/** 홍보방 `.은총교환` — 은총조각 N개 → 은총 1개 */
if (!defined('MINING_ORE_EUNCHONG_EXCHANGE')) {
    define('MINING_ORE_EUNCHONG_EXCHANGE', 10);
}
/** 은총 → 은총조각 역교환 성공 확률(%) */
if (!defined('MINING_ORE_EUNCHONG_TO_SHARD_SUCCESS_PCT')) {
    define('MINING_ORE_EUNCHONG_TO_SHARD_SUCCESS_PCT', 60);
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
              `schedule_window_start` DATETIME DEFAULT NULL COMMENT '스폰 당시 스케줄 창 시작(캡·비정상 판정 키)',
              PRIMARY KEY (`idx`),
              KEY `idx_nick_status_expire` (`nick`, `status`, `expire_at`),
              KEY `idx_nick_sched_window` (`nick`, `schedule_window_start`)
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
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        foreach ([
            'pos_x_pct' => "DECIMAL(5,2) DEFAULT NULL COMMENT '채굴장면 X 위치 %'",
            'pos_y_pct' => "DECIMAL(5,2) DEFAULT NULL COMMENT '채굴장면 Y 위치 %'",
            'schedule_window_start' => "DATETIME DEFAULT NULL COMMENT '스폰 당시 스케줄 창 시작(캡·비정상 판정 키)'",
        ] as $col => $def) {
            $exists = @db_select("SHOW COLUMNS FROM `tb_mining_ore_find` LIKE '{$col}'");
            if (empty($exists)) {
                @db_query("ALTER TABLE `tb_mining_ore_find` ADD COLUMN `{$col}` {$def}");
            }
        }
        $idx_rs = @db_query("SHOW INDEX FROM `tb_mining_ore_find`");
        $has_idx = false;
        if ($idx_rs) {
            while ($idx_row = db_fetch($idx_rs)) {
                if (($idx_row['Key_name'] ?? '') === 'idx_nick_sched_window') {
                    $has_idx = true;
                    break;
                }
            }
        }
        if (!$has_idx) {
            @db_query("ALTER TABLE `tb_mining_ore_find` ADD KEY `idx_nick_sched_window` (`nick`, `schedule_window_start`)");
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

if (!function_exists('mining_ore_seed_def_rows')) {
    /**
     * 광물 정의 시드 — 아이콘·라벨·정렬
     * 판매가는 mining_ore_sell_np_ratio_range_table() (본방냥 총합 × min~max 비율)
     * @return list<array{0:string,1:string,2:string,3:int}>
     */
    function mining_ore_seed_def_rows(): array {
        return [
            ['copper',         '🟤', '구리',     1],
            ['iron',           '⚙️', '철',       2],
            ['silver',         '🥈', '은',       3],
            ['gold',           '🥇', '금',       4],
            ['diamond',        '💎', '다이아',   5],
            ['eunchong_shard', '✨', '은총조각', 6],
        ];
    }
}

if (!function_exists('mining_ore_sell_unit_np_ratio')) {
    /**
     * 광물 가격 단위 비율 — 본방냥 총합 × 이 값 = "1냥 단위"
     *
     * 공식:
     *   1단위 = 본방냥총합 × UNIT
     *   지급 = 1단위 × 광물배수(min~max 랜덤)
     *   배수: 구리 0.1~0.9 · 철 1~10 · 은 3~30 · 금 5~50 · 다이아 10~100
     *
     * UNIT=0.00001 (0.001%) → 총합 6.4만이면 1단위≈0.64냥
     *   구리 ≈ 0.06~0.58 · 철 ≈ 0.6~6.4 · 다이아 ≈ 6.4~64
     */
    function mining_ore_sell_unit_np_ratio(): string {
        return '0.00001'; // 0.001%
    }
}

if (!function_exists('mining_ore_sell_unit_mult_range_table')) {
    /**
     * 단위배수 min~max (절대 폴백 금액과 동일 스케일)
     * @return array<string,array{0:string,1:string}>
     */
    function mining_ore_sell_unit_mult_range_table(): array {
        return [
            'copper' => ['0.1', '0.9'],
            'iron' => ['1', '10'],
            'silver' => ['3', '30'],
            'gold' => ['5', '50'],
            'diamond' => ['10', '100'],
            'eunchong_shard' => ['0', '0'],
        ];
    }
}

if (!function_exists('mining_ore_sell_np_ratio_range_table')) {
    /**
     * 광물별 본방냥 총합 대비 min~max 비율 (문자열)
     * @return array<string,array{0:string,1:string}>
     */
    function mining_ore_sell_np_ratio_range_table(): array {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $u = mining_ore_sell_unit_np_ratio();
        $cache = [];
        foreach (mining_ore_sell_unit_mult_range_table() as $key => $mult) {
            if (function_exists('bcmul')) {
                $cache[$key] = [
                    bcmul($u, (string)$mult[0], 16),
                    bcmul($u, (string)$mult[1], 16),
                ];
            } else {
                $uf = (float)$u;
                $cache[$key] = [
                    (string)max(0.0, $uf * (float)$mult[0]),
                    (string)max(0.0, $uf * (float)$mult[1]),
                ];
            }
        }
        return $cache;
    }
}

if (!function_exists('mining_ore_sell_np_ratio_range_str')) {
    /** @return array{0:string,1:string} */
    function mining_ore_sell_np_ratio_range_str(string $ore_key): array {
        $table = mining_ore_sell_np_ratio_range_table();
        $key = trim($ore_key);
        if (!isset($table[$key])) {
            return ['0', '0'];
        }
        return [(string)$table[$key][0], (string)$table[$key][1]];
    }
}

if (!function_exists('mining_ore_sell_np_ratio_range')) {
    /** @return array{0:float,1:float} [min_ratio, max_ratio] */
    function mining_ore_sell_np_ratio_range(string $ore_key): array {
        [$min, $max] = mining_ore_sell_np_ratio_range_str($ore_key);
        $min_f = max(0.0, (float)$min);
        $max_f = max($min_f, (float)$max);
        return [$min_f, $max_f];
    }
}

if (!function_exists('mining_ore_sell_np_ratio')) {
    /** 기대값용 중간 비율 (min+max)/2 */
    function mining_ore_sell_np_ratio(string $ore_key): float {
        [$min, $max] = mining_ore_sell_np_ratio_range($ore_key);
        return ($min + $max) / 2.0;
    }
}

if (!function_exists('mining_ore_sell_np_ratio_table')) {
    /** @return array<string,float> ore_key → 중간 비율(표시·기대값) */
    function mining_ore_sell_np_ratio_table(): array {
        $out = [];
        foreach (mining_ore_sell_np_ratio_range_table() as $key => $range) {
            $out[$key] = mining_ore_sell_np_ratio((string)$key);
        }
        return $out;
    }
}

if (!function_exists('mining_ore_ceil_int_str')) {
    /** 광물 냥 — 소수 버림이 아니라 올림 정수 문자열 */
    function mining_ore_ceil_int_str(string $v): string {
        $v = trim($v);
        if ($v === '' || $v === '0' || $v === '0.0') {
            return '0';
        }
        if (function_exists('bccomp') && function_exists('bcadd') && function_exists('bcmul')) {
            if (bccomp($v, '0', 12) <= 0) {
                return '0';
            }
            $trunc = bcmul($v, '1', 0); // 양수  Truncate
            if (bccomp($v, $trunc, 12) > 0) {
                return bcadd($trunc, '1', 0);
            }
            return $trunc === '' ? '0' : $trunc;
        }
        $f = (float)$v;
        if ($f <= 0) {
            return '0';
        }
        return (string)(int)ceil($f);
    }
}

if (!function_exists('mining_ore_np_ratio_amount_str')) {
    /**
     * 본방냥 총합 × 비율 — 올림 정수 냥
     * @param float|string $ratio
     */
    function mining_ore_np_ratio_amount_str($ratio): string {
        $ratio_str = is_string($ratio) ? trim($ratio) : sprintf('%.16F', (float)$ratio);
        if ($ratio_str === '' || (function_exists('bccomp') && bccomp($ratio_str, '0', 16) <= 0)
            || (!function_exists('bccomp') && (float)$ratio_str <= 0)) {
            return '0';
        }
        $total = function_exists('mining_tool_golden_np_total_str')
            ? mining_tool_golden_np_total_str()
            : '0';
        if ($total === '0' || $total === '' || $total === '0.0') {
            return '0';
        }
        $scale = defined('MINING_PENDING_SCALE') ? max(0, (int)MINING_PENDING_SCALE) : 10;
        if (function_exists('bcmul') && function_exists('bccomp')) {
            $raw = bcmul($total, $ratio_str, $scale);
            if ($raw === '' || bccomp($raw, '0', $scale) <= 0) {
                return '0';
            }
            return mining_ore_ceil_int_str($raw);
        }
        $v = (float)$total * (float)$ratio_str;
        if ($v <= 0) {
            return '0';
        }
        return mining_ore_ceil_int_str((string)$v);
    }
}

if (!function_exists('mining_ore_amount_lerp_str')) {
    /** min~max 금액 사이 비율 t(0~1) 보간 → 올림 정수 */
    function mining_ore_amount_lerp_str(string $min, string $max, float $t): string {
        $scale = defined('MINING_PENDING_SCALE') ? max(0, (int)MINING_PENDING_SCALE) : 10;
        $t = max(0.0, min(1.0, $t));
        if (!function_exists('bcadd') || !function_exists('bcmul') || !function_exists('bcsub')) {
            $a = (float)$min;
            $b = (float)$max;
            if ($b < $a) {
                $b = $a;
            }
            return mining_ore_ceil_int_str((string)($a + ($b - $a) * $t));
        }
        if (bccomp($max, $min, $scale) <= 0) {
            return mining_ore_ceil_int_str($min);
        }
        $diff = bcsub($max, $min, $scale);
        $part = bcmul($diff, sprintf('%.12F', $t), $scale);
        return mining_ore_ceil_int_str(bcadd($min, $part, $scale));
    }
}

if (!function_exists('mining_ore_sell_amount_range_str')) {
    /**
     * @return array{0:string,1:string} [min_amount, max_amount]
     * 본방냥 총합 미조회·환산 0이면 절대 구간(구리 0.1~0.9 …) 폴백
     */
    function mining_ore_sell_amount_range_str(string $ore_key): array {
        $key = trim($ore_key);
        [$rmin, $rmax] = mining_ore_sell_np_ratio_range_str($key);
        $min = mining_ore_np_ratio_amount_str($rmin);
        $max = mining_ore_np_ratio_amount_str($rmax);
        $mult = mining_ore_sell_unit_mult_range_table();
        $abs = $mult[$key] ?? ['0', '0'];
        $scale = defined('MINING_PENDING_SCALE') ? max(0, (int)MINING_PENDING_SCALE) : 10;
        $is_zero = static function (string $v) use ($scale): bool {
            if ($v === '' || $v === '0') {
                return true;
            }
            if (function_exists('bccomp')) {
                return bccomp($v, '0', $scale) <= 0;
            }
            return (float)$v <= 0;
        };
        if ($is_zero($min) && $is_zero($max) && !$is_zero((string)$abs[1])) {
            return [
                mining_ore_ceil_int_str((string)$abs[0]),
                mining_ore_ceil_int_str((string)$abs[1]),
            ];
        }
        if ($is_zero($max) && !$is_zero($min)) {
            $max = $min;
        }
        if ($is_zero($min) && !$is_zero($max)) {
            $min = $max;
        }
        return [
            mining_ore_ceil_int_str($min),
            mining_ore_ceil_int_str($max),
        ];
    }
}

if (!function_exists('mining_ore_sell_amount_roll_str')) {
    /** 스폰/터치용 — min~max 균등 랜덤 */
    function mining_ore_sell_amount_roll_str(string $ore_key): string {
        if (trim($ore_key) === 'eunchong_shard') {
            return '0';
        }
        [$min, $max] = mining_ore_sell_amount_range_str($ore_key);
        $scale = defined('MINING_PENDING_SCALE') ? max(0, (int)MINING_PENDING_SCALE) : 10;
        if ((function_exists('bccomp') && bccomp($min, '0', $scale) <= 0 && bccomp($max, '0', $scale) <= 0)
            || (!function_exists('bccomp') && (float)$min <= 0 && (float)$max <= 0)) {
            return '0';
        }
        try {
            $t = random_int(0, 1000000) / 1000000.0;
        } catch (Throwable $e) {
            $t = mt_rand(0, 1000000) / 1000000.0;
        }
        return mining_ore_amount_lerp_str($min, $max, $t);
    }
}

if (!function_exists('mining_ore_sell_amount_str')) {
    /** 광물 1개 기대 판매가(중간값) 문자열 — 표시·시드용 */
    function mining_ore_sell_amount_str(string $ore_key): string {
        [$min, $max] = mining_ore_sell_amount_range_str($ore_key);
        return mining_ore_amount_lerp_str($min, $max, 0.5);
    }
}

if (!function_exists('mining_ore_sell_amount')) {
    /** 광물 1개 기대 판매가 float (표시·기대값용 = 구간 중간) */
    function mining_ore_sell_amount(string $ore_key): float {
        $s = mining_ore_sell_amount_str($ore_key);
        return max(0.0, (float)$s);
    }
}

if (!function_exists('mining_ore_sell_range_fmt')) {
    /** 카탈로그용 "0.1~0.9냥" */
    function mining_ore_sell_range_fmt(string $ore_key): string {
        [$min, $max] = mining_ore_sell_amount_range_str($ore_key);
        $min_f = max(0.0, (float)$min);
        $max_f = max($min_f, (float)$max);
        if ($max_f <= 0) {
            return '—';
        }
        if (abs($max_f - $min_f) < 1e-12) {
            return '+' . mining_ore_fmt_amount($min_f) . '냥';
        }
        return mining_ore_fmt_amount($min_f) . '~' . mining_ore_fmt_amount($max_f) . '냥';
    }
}

if (!function_exists('mining_ore_seed_defs')) {
    function mining_ore_seed_defs(): void {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        // 프로세스당 1회 UPSERT — sell_newpoint 는 구간 중간값(표시/하위호환)
        foreach (mining_ore_seed_def_rows() as $r) {
            $key = addslashes($r[0]);
            $icon = addslashes($r[1]);
            $label = addslashes($r[2]);
            $sell = mining_ore_sell_amount_str((string)$r[0]);
            if ($sell === '' || !is_numeric($sell)) {
                $sell = '0';
            }
            $sell_sql = addslashes($sell);
            $ord = (int)$r[3];
            @db_query("
                INSERT INTO tb_mining_ore_def (ore_key, icon, label, sell_newpoint, sort_order, enabled)
                VALUES ('{$key}', '{$icon}', '{$label}', {$sell_sql}, {$ord}, 1)
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

if (!function_exists('mining_ore_shard_column_def')) {
    /** 보유 상한 500 — TINYINT(255)면 `.생성 … 100` 등에서 저장 실패 */
    function mining_ore_shard_column_def(): string {
        return "SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '은총조각'";
    }
}

if (!function_exists('mining_ore_ensure_shard_column')) {
    function mining_ore_ensure_shard_column(): bool {
        if (!defined('MINING_TABLE') || !function_exists('db_query') || !function_exists('db_select')) {
            return false;
        }
        $tbl = MINING_TABLE;
        $def = mining_ore_shard_column_def();
        $exists = @db_select("SHOW COLUMNS FROM `{$tbl}` LIKE 'mining_eunchong_shard'");
        if (empty($exists)) {
            @db_query("ALTER TABLE `{$tbl}` ADD COLUMN `mining_eunchong_shard` {$def}");
            $exists = @db_select("SHOW COLUMNS FROM `{$tbl}` LIKE 'mining_eunchong_shard'");
            return !empty($exists);
        }
        $type = strtolower((string)($exists['Type'] ?? ''));
        if (strpos($type, 'tinyint') !== false) {
            @db_query("ALTER TABLE `{$tbl}` MODIFY `mining_eunchong_shard` {$def}");
            $exists = @db_select("SHOW COLUMNS FROM `{$tbl}` LIKE 'mining_eunchong_shard'");
            $type = strtolower((string)($exists['Type'] ?? ''));
            return !empty($exists) && strpos($type, 'tinyint') === false;
        }
        return true;
    }
}

if (!function_exists('mining_ore_ensure_member_columns')) {
    function mining_ore_ensure_member_columns(): void {
        static $done = false;
        static $shard_ok = false;
        $tbl = MINING_TABLE;
        // 은총조각 컬럼은 매 요청 확인(없으면 ALTER · TINYINT면 확장) — 지급 누락 방지
        if (!$shard_ok) {
            $shard_ok = mining_ore_ensure_shard_column();
        }
        if ($done) {
            return;
        }
        $done = true;
        foreach ([
            'mining_ore_roll_acc' => "DECIMAL(24,10) NOT NULL DEFAULT 0 COMMENT '광물 판정 누적(legacy)'",
            'mining_ore_roll_targets' => "VARCHAR(128) NOT NULL DEFAULT '' COMMENT '출현 창 spawn offset JSON(초)'",
            'mining_ore_next_spawn_at' => "DATETIME DEFAULT NULL COMMENT '다음 광물 spawn 예정'",
            'mining_ore_window_start' => "DATETIME DEFAULT NULL COMMENT '현재 출현 창 시작'",
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

if (!function_exists('mining_ore_weight_table_base20')) {
    /** +20 기준 광물 비율 — 구리·철 위주 · 은·금·다이아·조각↓ */
    function mining_ore_weight_table_base20(): array {
        return [
            'copper' => 520,
            'iron' => 320,
            'silver' => 12,
            'gold' => 8,
            'diamond' => 3,
            'eunchong_shard' => 12,
        ];
    }
}

if (!function_exists('mining_ore_weight_table_target100')) {
    /** +100 목표 광물 비율 — 구리·철 위주 · 은·금·다이아·조각↓ */
    function mining_ore_weight_table_target100(): array {
        return [
            'copper' => 280,
            'iron' => 220,
            'silver' => 10,
            'gold' => 12,
            'diamond' => 18,
            'eunchong_shard' => 40,
        ];
    }
}

if (!function_exists('mining_ore_weight_table_decade1')) {
    /** +10: 구리 + 은총조각 (결합 가능 최저 구간) · 조각↓ */
    function mining_ore_weight_table_decade1(): array {
        return [
            'copper' => 970,
            'iron' => 0,
            'silver' => 0,
            'gold' => 0,
            'diamond' => 0,
            'eunchong_shard' => 30,
        ];
    }
}

if (!function_exists('mining_ore_weight_table')) {
    /**
     * 광물 종류 확률 — +10 … +91~100 구간별 동일
     * (채굴 결합은 +10↑ · 구간 내에서는 상한 강화 비율 사용)
     *
     * @return array<string,int> ore_key => weight (0 = 미해금)
     */
    function mining_ore_weight_table(int $enhance): array {
        if ($enhance < 10) {
            return [];
        }
        $max = function_exists('강화_최대') ? (int)강화_최대() : 100;
        $enhance = min(max(10, $enhance), max(10, $max));
        $decade = mining_ore_enhance_decade($enhance);
        if ($decade <= 0) {
            return [];
        }

        // +10
        if ($decade <= 1) {
            return mining_ore_weight_table_decade1();
        }
        // +11~20: 전 광물 해금
        if ($decade === 2) {
            return mining_ore_weight_table_base20();
        }

        // +21~30 … +91~100: 구간 상한 기준 +20→+100 보간
        $hi = min($decade * 10, $max);
        $from = mining_ore_weight_table_base20();
        $to = mining_ore_weight_table_target100();
        $t = ($hi - 20) / max(1, ($max - 20));
        $t = max(0.0, min(1.0, $t));
        $weights = [];
        foreach ($from as $key => $w0) {
            $w1 = (int)($to[$key] ?? 0);
            $weights[$key] = max(0, (int)round($w0 + ($w1 - $w0) * $t));
        }
        return $weights;
    }
}

if (!function_exists('mining_ore_weight_table_grantable')) {
    /**
     * 실제 지급 가능 가중치 — enabled 광물만 (표시·pick 공통)
     * @return array<string,int>
     */
    function mining_ore_weight_table_grantable(int $enhance): array {
        $weights = mining_ore_weight_table($enhance);
        $enabled = mining_ore_enabled_keys();
        foreach ($weights as $key => $w) {
            if ($w > 0 && empty($enabled[$key])) {
                $weights[$key] = 0;
            }
        }
        return $weights;
    }
}

if (!function_exists('mining_ore_enabled_keys')) {
    /** @return array<string,true> */
    function mining_ore_enabled_keys(): array {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $cache = [];
        mining_ore_ensure_schema();
        if (function_exists('db_query') && function_exists('db_fetch')) {
            $rs = @db_query("SELECT ore_key FROM tb_mining_ore_def WHERE enabled = 1");
            if ($rs) {
                while ($row = db_fetch($rs)) {
                    $key = (string)($row['ore_key'] ?? '');
                    if ($key !== '') {
                        $cache[$key] = true;
                    }
                }
            }
        }
        if (empty($cache)) {
            foreach (mining_ore_seed_def_rows() as $r) {
                $cache[$r[0]] = true;
            }
        }
        return $cache;
    }
}

if (!function_exists('mining_ore_pick_key')) {
    function mining_ore_pick_key(int $enhance): ?string {
        $weights = mining_ore_weight_table_grantable($enhance);
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
        return null;
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
        // 동일 요청 내 nick당 1회만 (member_state + pending_finds 중복 방지)
        static $doneNicks = [];
        $nickKey = trim((string)$nick);
        if ($nickKey !== '' && isset($doneNicks[$nickKey])) {
            return 0;
        }
        if ($nickKey !== '') {
            $doneNicks[$nickKey] = true;
        }

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

        $rows = [];
        $ids = [];
        while ($row = db_fetch($rs)) {
            $idx = (int)($row['idx'] ?? 0);
            if ($idx <= 0) {
                continue;
            }
            $ids[] = $idx;
            $rows[] = $row;
        }
        if ($ids === []) {
            return 0;
        }

        $idList = implode(',', array_map('intval', $ids));
        db_query("
            UPDATE tb_mining_ore_find
            SET status = 'expired'
            WHERE idx IN ({$idList}) AND status = 'pending'
        ");

        foreach ($rows as $row) {
            mining_ore_log_insert(
                $nick,
                $row['ore_key'] ?? '',
                'expired',
                (int)($row['idx'] ?? 0),
                (int)($row['qty'] ?? 1),
                0,
                $row['weapon_item'] ?? '',
                (int)($row['weapon_enhance'] ?? 0),
                null,
                (function_exists('mining_ore_ttl_label') ? mining_ore_ttl_label() : '30분') . ' 미터치 소멸'
            );
        }
        return count($ids);
    }
}

if (!function_exists('mining_ore_create_find')) {
    function mining_ore_create_find(
        $nick,
        $ore_key,
        $weapon_item,
        int $weapon_enhance,
        int $qty = 1,
        ?string $found_at = null,
        ?string $schedule_window_start = null
    ): ?array {
        mining_ore_ensure_schema();
        $def = mining_ore_def($ore_key);
        if (empty($def)) {
            return null;
        }

        $nick_esc = mining_data_nick_esc($nick);
        $ore_esc = addslashes(trim((string)$ore_key));
        $weapon_esc = addslashes(trim((string)$weapon_item));
        $qty = max(1, $qty);
        // 본방냥 총합 × 광물별 min~max 비율 랜덤 (스폰 시점 스냅샷 → pending_value)
        $sell_str = function_exists('mining_ore_sell_amount_roll_str')
            ? mining_ore_sell_amount_roll_str((string)$ore_key)
            : (function_exists('mining_ore_sell_amount_str')
                ? mining_ore_sell_amount_str((string)$ore_key)
                : (string)($def['sell_newpoint'] ?? 0));
        // 환산 실패 시 def/절대폴백이 roll 안에 있음 — 그래도 0이면 def 재시도
        if ((string)$ore_key !== 'eunchong_shard'
            && (float)$sell_str <= 0
            && function_exists('mining_ore_sell_amount_str')) {
            $sell_str = mining_ore_sell_amount_str((string)$ore_key);
        }
        if ($qty > 1 && function_exists('bcmul')) {
            $pending_raw = bcmul($sell_str, (string)$qty, defined('MINING_PENDING_SCALE') ? (int)MINING_PENDING_SCALE : 10);
        } else {
            $pending_raw = (string)((float)$sell_str * $qty);
        }
        $pending_value = (float)mining_ore_ceil_int_str((string)mining_pending_round($pending_raw));
        if ($pending_value < 0) {
            $pending_value = 0.0;
        }
        // 은총조각이 아니면 최소 1냥 (올림 후 0 방지 — 비율이 극소일 때)
        if ((string)$ore_key !== 'eunchong_shard' && $pending_value < 1 && (float)$sell_str > 0) {
            $pending_value = 1.0;
        }
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

        $win_sql = 'NULL';
        if ($schedule_window_start !== null && trim($schedule_window_start) !== '') {
            $win_ts = strtotime($schedule_window_start);
            if ($win_ts !== false && $win_ts > 0) {
                $win_sql = "'" . addslashes(date('Y-m-d H:i:s', $win_ts)) . "'";
            }
        }

        $spawn_pos = mining_ore_pick_spawn_position(mining_ore_pending_spawn_positions($nick));
        $pos_x_sql = number_format($spawn_pos['x'], 2, '.', '');
        $pos_y_sql = number_format($spawn_pos['y'], 2, '.', '');

        db_query("
            INSERT INTO tb_mining_ore_find (
                nick, ore_key, qty, pending_value, weapon_item, weapon_enhance,
                status, found_at, expire_at, pos_x_pct, pos_y_pct, schedule_window_start
            ) VALUES (
                '{$nick_esc}', '{$ore_esc}', {$qty}, {$pending_sql}, '{$weapon_esc}', {$enh},
                'pending', {$found_sql}, {$expire_sql}, {$pos_x_sql}, {$pos_y_sql}, {$win_sql}
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
        $hourly_cnt = $ctx['ok']
            ? mining_ore_effective_hourly_find_expected((int)$ctx['enhance'], $tool_lv)
            : 0.0;
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
    function mining_ore_try_create_find(
        $nick,
        $weapon_item,
        int $weapon_enhance,
        ?string $found_at = null,
        ?string $schedule_window_start = null
    ): ?array {
        return mining_ore_try_create_find_at($nick, $weapon_item, $weapon_enhance, $found_at, $schedule_window_start);
    }
}

if (!function_exists('mining_ore_try_create_find_at')) {
    function mining_ore_try_create_find_at(
        $nick,
        $weapon_item,
        int $weapon_enhance,
        ?string $found_at = null,
        ?string $schedule_window_start = null
    ): ?array {
        $ore_key = mining_ore_pick_key($weapon_enhance);
        if ($ore_key === null) {
            return null;
        }
        $find = mining_ore_create_find(
            $nick,
            $ore_key,
            $weapon_item,
            $weapon_enhance,
            1,
            $found_at,
            $schedule_window_start
        );
        return $find ? mining_ore_find_payload($find) : null;
    }
}

if (!function_exists('mining_ore_resolve_key_from_label')) {
    /**
     * 한글/영문 광물명 → ore_key
     * 예) 철|iron → iron · 은총조각|은총 → eunchong_shard
     */
    function mining_ore_resolve_key_from_label($input): ?string {
        $raw = trim((string)$input);
        if ($raw === '') {
            return null;
        }
        $aliases = [
            '구리' => 'copper',
            'copper' => 'copper',
            '철' => 'iron',
            'iron' => 'iron',
            '은' => 'silver',
            'silver' => 'silver',
            '금' => 'gold',
            'gold' => 'gold',
            '다이아' => 'diamond',
            '다이아몬드' => 'diamond',
            'diamond' => 'diamond',
            '은총조각' => 'eunchong_shard',
            '은총' => 'eunchong_shard',
            '조각' => 'eunchong_shard',
            'eunchong' => 'eunchong_shard',
            'eunchong_shard' => 'eunchong_shard',
            'shard' => 'eunchong_shard',
        ];
        $lower = function_exists('mb_strtolower') ? mb_strtolower($raw, 'UTF-8') : strtolower($raw);
        if (isset($aliases[$raw])) {
            return $aliases[$raw];
        }
        if (isset($aliases[$lower])) {
            return $aliases[$lower];
        }
        foreach (mining_ore_seed_def_rows() as $r) {
            if ((string)$r[0] === $raw || (string)$r[0] === $lower || (string)$r[2] === $raw) {
                return (string)$r[0];
            }
        }
        return null;
    }
}

if (!function_exists('mining_ore_admin_spawn_for_nick')) {
    /**
     * 관리자 강제 광물 스폰 — 무기 결합 여부와 무관하게 pending 광물 생성
     * @return array{ok:bool,data:string,find?:array}
     */
    function mining_ore_admin_spawn_for_nick($nick, $ore_label_or_key, int $qty = 1): array {
        $nick = trim((string)$nick);
        if ($nick === '') {
            return ['ok' => false, 'data' => '❌ 닉네임을 확인해 주세요.'];
        }
        $ore_key = mining_ore_resolve_key_from_label($ore_label_or_key);
        if ($ore_key === null) {
            return [
                'ok' => false,
                'data' => "❌ 광물 종류를 확인해 주세요.\n예) 구리 · 철 · 은 · 금 · 다이아 · 은총조각",
            ];
        }
        $qty = max(1, min(20, $qty));

        if (!function_exists('mining_data_ensure_row')) {
            require_once __DIR__ . '/mining_storage.inc.php';
        }
        if (!mining_data_ensure_row($nick)) {
            return ['ok' => false, 'data' => '❌ 채굴 정보를 준비할 수 없습니다.'];
        }

        $weapon_item = '강제광물';
        $weapon_enhance = 0;
        if (function_exists('mining_weapon_member_row')) {
            $m = mining_weapon_member_row($nick);
            if (!empty($m)) {
                $weapon_item = trim((string)($m['item'] ?? '')) ?: '강제광물';
                $weapon_enhance = max(0, (int)($m['enhance'] ?? 0));
            }
        } else {
            $nick_esc = addslashes($nick);
            $m = @db_select("SELECT item, enhance FROM tb_member WHERE name = '{$nick_esc}' LIMIT 1");
            if (!empty($m)) {
                $weapon_item = trim((string)($m['item'] ?? '')) ?: '강제광물';
                $weapon_enhance = max(0, (int)($m['enhance'] ?? 0));
            }
        }

        $find = mining_ore_create_find($nick, $ore_key, $weapon_item, $weapon_enhance, $qty);
        if (!$find) {
            return ['ok' => false, 'data' => '❌ 광물 생성에 실패했어요.'];
        }
        $payload = mining_ore_find_payload($find);
        $icon = (string)($payload['icon'] ?? '');
        $label = (string)($payload['label'] ?? $ore_key);
        $val_fmt = (string)($payload['pending_value_fmt'] ?? '0');
        $msg = "✅ 광물 강제 스폰\n{$nick}\n{$icon}{$label}";
        if ($qty > 1) {
            $msg .= " ×{$qty}";
        }
        if ($ore_key === 'eunchong_shard') {
            $msg .= "\n(터치 시 은총조각 적립)";
        } else {
            $msg .= "\n채굴량 +{$val_fmt}냥 (터치 시)";
        }
        $msg .= "\n채굴 페이지를 열면 바로 보여요.";
        return ['ok' => true, 'data' => $msg, 'find' => $payload];
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
    /**
     * 은총조각 적립 (최대 MINING_ORE_EUNCHONG_SHARDS · 자동 변환 없음)
     * @return array{ok:bool,shard:int,before:int,added:int,eunchong_granted:int,capped?:bool,data?:string}
     */
    function mining_ore_add_shards($nick, int $add): array {
        mining_ore_ensure_schema();
        // 컬럼 보장 재시도 (이전 요청에서 ALTER 실패·static $done 으로 스킵된 경우)
        if (function_exists('mining_ore_ensure_member_columns')) {
            mining_ore_ensure_member_columns();
        }
        $add = max(0, $add);
        $max = max(1, (int)MINING_ORE_EUNCHONG_SHARDS);
        $nick_esc = mining_data_nick_esc($nick);
        if ($nick_esc === '' || $add <= 0) {
            return ['ok' => false, 'shard' => 0, 'before' => 0, 'added' => 0, 'eunchong_granted' => 0, 'data' => '지급할 수 없어요.'];
        }

        if (!mining_data_ensure_row($nick)) {
            return ['ok' => false, 'shard' => 0, 'before' => 0, 'added' => 0, 'eunchong_granted' => 0, 'data' => '채굴 정보를 준비할 수 없어요.'];
        }

        if (!mining_ore_ensure_shard_column()) {
            return ['ok' => false, 'shard' => 0, 'before' => 0, 'added' => 0, 'eunchong_granted' => 0, 'data' => '은총조각 컬럼이 없어요.'];
        }

        $row = db_select("
            SELECT IFNULL(mining_eunchong_shard, 0) AS shard
            FROM `" . MINING_TABLE . "`
            WHERE nick = '{$nick_esc}'
            LIMIT 1
        ");
        if (!$row) {
            return ['ok' => false, 'shard' => 0, 'before' => 0, 'added' => 0, 'eunchong_granted' => 0, 'data' => '채굴 행을 읽을 수 없어요.'];
        }
        $before = max(0, (int)($row['shard'] ?? 0));
        $shard = $before + $add;
        $capped = false;
        if ($shard > $max) {
            $shard = $max;
            $capped = true;
        }
        $added = max(0, $shard - $before);

        // 이미 상한이면 성공(추가 0) — 호출측에서 capped 표시
        if ($added <= 0) {
            return [
                'ok' => true,
                'shard' => $before,
                'before' => $before,
                'added' => 0,
                'eunchong_granted' => 0,
                'capped' => true,
            ];
        }

        $rs = db_query("
            UPDATE `" . MINING_TABLE . "`
            SET mining_eunchong_shard = {$shard}
            WHERE nick = '{$nick_esc}'
            LIMIT 1
        ");
        if (!$rs) {
            return ['ok' => false, 'shard' => $before, 'before' => $before, 'added' => 0, 'eunchong_granted' => 0, 'data' => '은총조각 저장 실패'];
        }

        // 실제 반영 재확인 (UPDATE 성공처럼 보여도 컬럼/트리거 이슈 방지)
        $afterRow = db_select("
            SELECT IFNULL(mining_eunchong_shard, 0) AS shard
            FROM `" . MINING_TABLE . "`
            WHERE nick = '{$nick_esc}'
            LIMIT 1
        ");
        $after = max(0, (int)($afterRow['shard'] ?? 0));
        if ($after < $shard) {
            return [
                'ok' => false,
                'shard' => $after,
                'before' => $before,
                'added' => max(0, $after - $before),
                'eunchong_granted' => 0,
                'data' => '은총조각이 저장되지 않았어요.',
            ];
        }

        return [
            'ok' => true,
            'shard' => $after,
            'before' => $before,
            'added' => max(0, $after - $before),
            'eunchong_granted' => 0,
            'capped' => $capped,
        ];
    }
}

if (!function_exists('mining_ore_shard_count')) {
    function mining_ore_shard_count($nick): int {
        mining_ore_ensure_schema();
        $nick_esc = mining_data_nick_esc($nick);
        if ($nick_esc === '') {
            return 0;
        }
        mining_data_ensure_row($nick);
        $row = db_select("
            SELECT IFNULL(mining_eunchong_shard, 0) AS shard
            FROM `" . MINING_TABLE . "`
            WHERE nick = '{$nick_esc}'
            LIMIT 1
        ");
        return max(0, (int)($row['shard'] ?? 0));
    }
}

if (!function_exists('mining_ore_spend_shards')) {
    /**
     * 은총조각 차감
     * @return array{ok:bool,shard:int,spent:int,data?:string}
     */
    function mining_ore_spend_shards($nick, int $qty): array {
        mining_ore_ensure_schema();
        $qty = max(0, $qty);
        $nick_esc = mining_data_nick_esc($nick);
        if ($nick_esc === '' || $qty < 1) {
            return ['ok' => false, 'shard' => 0, 'spent' => 0, 'data' => '차감할 수 없어요.'];
        }

        mining_data_ensure_row($nick);
        $have = mining_ore_shard_count($nick);
        if ($have < $qty) {
            return [
                'ok' => false,
                'shard' => $have,
                'spent' => 0,
                'data' => '은총조각이 부족해요. (보유 ' . $have . '개)',
            ];
        }

        global $conn;
        @db_query("
            UPDATE `" . MINING_TABLE . "`
            SET mining_eunchong_shard = IFNULL(mining_eunchong_shard, 0) - {$qty}
            WHERE nick = '{$nick_esc}'
              AND IFNULL(mining_eunchong_shard, 0) >= {$qty}
            LIMIT 1
        ");
        $ok = ($conn instanceof mysqli) ? ((int)mysqli_affected_rows($conn) > 0) : true;
        $shard = mining_ore_shard_count($nick);
        if (!$ok) {
            return [
                'ok' => false,
                'shard' => $shard,
                'spent' => 0,
                'data' => '은총조각이 부족해요. (보유 ' . $shard . '개)',
            ];
        }
        return ['ok' => true, 'shard' => $shard, 'spent' => $qty];
    }
}

if (!function_exists('mining_ore_exchange_to_eunchong')) {
    /**
     * 은총조각 → 은총 교환 (기본 10개당 1개)
     * @param int|null $want 교환할 은총 개수(null이면 가능한 최대)
     * @return array{ok:bool,msg:string,shard?:int,spent?:int,granted?:int}
     */
    function mining_ore_exchange_to_eunchong($nick, $want = null): array {
        $nick = trim((string)$nick);
        if ($nick === '') {
            return ['ok' => false, 'msg' => '❌ 닉네임을 확인할 수 없어요.'];
        }
        if (!function_exists('mining_data_ensure_row')) {
            if (is_file(__DIR__ . '/mining_storage.inc.php')) {
                require_once __DIR__ . '/mining_storage.inc.php';
            }
        }
        if (!function_exists('bag_은총_가산') && is_file(__DIR__ . '/../item_bag_enhance.inc.php')) {
            require_once __DIR__ . '/../item_bag_enhance.inc.php';
        }

        $rate = max(1, (int)MINING_ORE_EUNCHONG_EXCHANGE);
        $have = mining_ore_shard_count($nick);
        $maxGrant = intdiv($have, $rate);
        if ($maxGrant < 1) {
            $maxHold = max(1, (int)MINING_ORE_EUNCHONG_SHARDS);
            return [
                'ok' => false,
                'msg' => "❌ 은총조각이 부족해요. (보유 {$have}개 · {$rate}개당 은총 1개)\n최대 {$maxHold}개까지 모을 수 있어요.",
                'shard' => $have,
                'spent' => 0,
                'granted' => 0,
            ];
        }

        $grant = $maxGrant;
        if ($want !== null) {
            $want = max(1, (int)$want);
            $grant = min($maxGrant, $want);
        }
        $spend = $grant * $rate;

        $차감 = mining_ore_spend_shards($nick, $spend);
        if (empty($차감['ok'])) {
            return [
                'ok' => false,
                'msg' => '❌ ' . trim((string)($차감['data'] ?? '은총조각 차감에 실패했어요.')),
                'shard' => (int)($차감['shard'] ?? $have),
                'spent' => 0,
                'granted' => 0,
            ];
        }

        if (!function_exists('bag_은총_가산')) {
            // 차감 롤백
            db_query("
                UPDATE `" . MINING_TABLE . "`
                SET mining_eunchong_shard = IFNULL(mining_eunchong_shard, 0) + {$spend}
                WHERE nick = '" . mining_data_nick_esc($nick) . "'
                LIMIT 1
            ");
            return ['ok' => false, 'msg' => '❌ 은총 지급 기능을 불러올 수 없어요.', 'shard' => $have, 'spent' => 0, 'granted' => 0];
        }
        $지급 = bag_은총_가산($nick, $grant);
        if (empty($지급['ok'])) {
            db_query("
                UPDATE `" . MINING_TABLE . "`
                SET mining_eunchong_shard = IFNULL(mining_eunchong_shard, 0) + {$spend}
                WHERE nick = '" . mining_data_nick_esc($nick) . "'
                LIMIT 1
            ");
            return [
                'ok' => false,
                'msg' => '❌ 은총 지급에 실패했어요. ' . trim((string)($지급['msg'] ?? '')),
                'shard' => mining_ore_shard_count($nick),
                'spent' => 0,
                'granted' => 0,
            ];
        }

        $남음 = (int)($차감['shard'] ?? mining_ore_shard_count($nick));
        $은총보유 = function_exists('bag_은총_수량') ? (int)bag_은총_수량($nick) : 0;
        if (function_exists('지급로그')) {
            지급로그('은총교환', $nick, '', 0, $grant);
        }

        return [
            'ok' => true,
            'msg' => "✨ 은총교환 완료!\n은총조각 {$spend}개 → 은총 {$grant}개\n남은 조각 {$남음}개 · 은총 보유 {$은총보유}개",
            'shard' => $남음,
            'spent' => $spend,
            'granted' => $grant,
        ];
    }
}

if (!function_exists('mining_ore_exchange_eunchong_to_shards')) {
    /**
     * 은총 1개 → 은총조각 N개 역교환 (성공 확률 적용 · 실패 시 은총만 소멸)
     * @return array{ok:bool,msg:string,success?:bool,shard?:int,granted?:int}
     */
    function mining_ore_exchange_eunchong_to_shards($nick): array {
        $nick = trim((string)$nick);
        if ($nick === '') {
            return ['ok' => false, 'msg' => '❌ 닉네임을 확인할 수 없어요.'];
        }
        if (!function_exists('mining_data_ensure_row') && is_file(__DIR__ . '/mining_storage.inc.php')) {
            require_once __DIR__ . '/mining_storage.inc.php';
        }
        if (!function_exists('bag_은총_차감') && is_file(__DIR__ . '/../item_bag_enhance.inc.php')) {
            require_once __DIR__ . '/../item_bag_enhance.inc.php';
        }
        if (!function_exists('bag_은총_수량') || !function_exists('bag_은총_차감')) {
            return ['ok' => false, 'msg' => '❌ 은총 기능을 불러올 수 없어요.'];
        }

        $rate = max(1, (int)MINING_ORE_EUNCHONG_EXCHANGE);
        $maxHold = max(1, (int)MINING_ORE_EUNCHONG_SHARDS);
        $successPct = max(1, min(100, (int)MINING_ORE_EUNCHONG_TO_SHARD_SUCCESS_PCT));
        $haveEunchong = (int)bag_은총_수량($nick);
        if ($haveEunchong < 1) {
            return ['ok' => false, 'msg' => '❌ 은총이 없어요. (보유 0개)'];
        }

        $haveShard = mining_ore_shard_count($nick);
        if ($haveShard + $rate > $maxHold) {
            $여유 = max(0, $maxHold - $haveShard);
            return [
                'ok' => false,
                'msg' => "❌ 은총조각 보관 한도가 부족해요.\n필요 여유 {$rate}개 · 현재 {$haveShard}/{$maxHold} (여유 {$여유}개)",
                'shard' => $haveShard,
            ];
        }

        $차감 = bag_은총_차감($nick, 1);
        if (empty($차감['ok'])) {
            return [
                'ok' => false,
                'msg' => '❌ 은총 차감에 실패했어요. ' . trim((string)($차감['msg'] ?? '')),
            ];
        }

        $roll = random_int(1, 100);
        $success = ($roll <= $successPct);
        $은총남음 = (int)bag_은총_수량($nick);

        if (!$success) {
            if (function_exists('지급로그')) {
                지급로그('은총조각역교환실패', $nick, '', 0, 1);
            }
            return [
                'ok' => true,
                'success' => false,
                'msg' => "💥 교환 실패… (확률 {$successPct}%)\n은총 1개가 소멸했어요.\n남은 은총 {$은총남음}개 · 조각 {$haveShard}/{$maxHold}",
                'shard' => $haveShard,
                'granted' => 0,
            ];
        }

        $add = mining_ore_add_shards($nick, $rate);
        $조각남음 = (int)($add['shard'] ?? mining_ore_shard_count($nick));
        if (function_exists('지급로그')) {
            지급로그('은총조각역교환성공', $nick, '', 0, $rate);
        }

        return [
            'ok' => true,
            'success' => true,
            'msg' => "✨ 교환 성공! (확률 {$successPct}%)\n은총 1개 → 은총조각 {$rate}개\n조각 {$조각남음}/{$maxHold} · 은총 {$은총남음}개",
            'shard' => $조각남음,
            'granted' => $rate,
        ];
    }
}

if (!function_exists('mining_ore_touch_execute')) {
    function mining_ore_touch_execute($nick, $find_idx, $lease_token = '') {
        mining_ore_ensure_schema();
        if (!function_exists('mining_sync_select_sql')) {
            require_once __DIR__ . '/mining_sync.inc.php';
        }
        mining_ore_expire_pending($nick);

        $find_idx = (int)$find_idx;
        if ($find_idx <= 0) {
            return ['ok' => false, 'data' => '잘못된 발견입니다.'];
        }

        $nick_esc = mining_data_nick_esc($nick);
        if ($nick_esc === '') {
            return ['ok' => false, 'data' => '회원 정보를 확인할 수 없습니다.'];
        }
        if (!mining_data_ensure_row($nick)) {
            return ['ok' => false, 'data' => '채굴 정보를 준비할 수 없습니다.'];
        }

        global $conn;
        $use_txn = ($conn instanceof mysqli);
        $tbl = function_exists('mining_sync_table') ? mining_sync_table() : MINING_TABLE;

        $ore_key = '';
        $qty = 1;
        $pending_add = 0.0;
        $weapon_item = '';
        $weapon_enhance = 0;
        $shard_after = null;
        $memo = '';
        $msg = '';

        if ($use_txn) {
            mysqli_begin_transaction($conn);
        }
        try {
            // 채굴 행 잠금 → 경과 반영 + 광물 가산을 한 번에 (동시 터치/sync 레이스 방지)
            $mrow = db_select("
                SELECT " . mining_sync_select_sql() . ",
                       IFNULL(mining_eunchong_shard, 0) AS mining_eunchong_shard
                FROM `{$tbl}`
                WHERE nick = '{$nick_esc}'
                LIMIT 1
                FOR UPDATE
            ");
            if (!$mrow) {
                throw new RuntimeException('채굴 정보를 확인할 수 없습니다.');
            }

            $find = db_select("
                SELECT *
                FROM tb_mining_ore_find
                WHERE idx = {$find_idx}
                  AND nick = '{$nick_esc}'
                LIMIT 1
                FOR UPDATE
            ");
            if (empty($find) || ($find['status'] ?? '') !== 'pending') {
                throw new RuntimeException('이미 수령했거나 소멸한 광물이에요.');
            }
            if (strtotime((string)($find['expire_at'] ?? '')) <= time()) {
                throw new RuntimeException('1시간이 지나 소멸했어요.');
            }

            $ore_key = (string)($find['ore_key'] ?? '');
            $qty = max(1, (int)($find['qty'] ?? 1));
            $pending_add = (float)($find['pending_value'] ?? 0);
            // 스폰 당시 0으로 저장된 광물(총합 미조회 등) → 터치 시 재계산
            if ($ore_key !== 'eunchong_shard' && $pending_add <= 0 && function_exists('mining_ore_sell_amount_roll_str')) {
                $pending_add = (float)mining_ore_sell_amount_roll_str($ore_key);
                if ($qty > 1) {
                    $pending_add = $pending_add * $qty;
                }
                if (function_exists('mining_pending_round')) {
                    $pending_add = mining_pending_round($pending_add);
                }
            }
            $weapon_item = (string)($find['weapon_item'] ?? '');
            $weapon_enhance = (int)($find['weapon_enhance'] ?? 0);

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
                throw new RuntimeException('수령에 실패했어요.');
            }

            $sync_at = mining_sync_ts($mrow['mining_sync_at'] ?? '');
            $elapsed_sec = max(0, mining_sync_now() - $sync_at);
            $sim = mining_sync_simulate_elapsed($mrow, $nick, $elapsed_sec);
            $tool_lv = max(0, (int)($mrow['mining_tool'] ?? 0));
            $dur_sql = mining_durability_sql($sim['durability'], $tool_lv);
            $pending_sql = mining_pending_sql($sim['pending']);

            if ($ore_key === 'eunchong_shard') {
                $max = max(1, (int)MINING_ORE_EUNCHONG_SHARDS);
                $before = max(0, (int)($mrow['mining_eunchong_shard'] ?? 0));
                $shard = min($max, $before + $qty);
                $capped = ($before + $qty) > $max;
                db_query("
                    UPDATE `{$tbl}`
                    SET mining_eunchong_shard = {$shard},
                        mining_pending = {$pending_sql},
                        mining_durability = {$dur_sql},
                        mining_sync_at = NOW()
                    WHERE nick = '{$nick_esc}'
                    LIMIT 1
                ");
                $shard_after = $shard;
                $need = $max;
                $msg = '✨ 은총조각 ' . $qty . '개 수령! (' . $shard_after . '/' . $need . ')';
                if ($capped && $shard_after >= $need) {
                    $msg .= "\n최대 {$need}개까지 모을 수 있어요.";
                }
                $memo = '은총조각 터치';
            } else {
                $new_pending = (float)$sim['pending'] + max(0.0, $pending_add);
                if (function_exists('mining_pending_round')) {
                    $new_pending = mining_pending_round($new_pending);
                }
                $new_pending_sql = mining_pending_sql($new_pending);
                db_query("
                    UPDATE `{$tbl}`
                    SET mining_pending = {$new_pending_sql},
                        mining_durability = {$dur_sql},
                        mining_sync_at = NOW()
                    WHERE nick = '{$nick_esc}'
                    LIMIT 1
                ");
                $def = mining_ore_def($ore_key);
                $icon = $def['icon'] ?? '';
                $label = $def['label'] ?? $ore_key;
                $msg = $icon . $label . ' +' . mining_fmt_ore_value($pending_add) . '냥이 채굴량에 더해졌어요!';
                $memo = '채굴량 가산';
            }

            if ($use_txn) {
                mysqli_commit($conn);
            }
        } catch (Throwable $e) {
            if ($use_txn) {
                mysqli_rollback($conn);
            }
            $err = trim((string)$e->getMessage());
            if ($err === '1시간이 지나 소멸했어요.') {
                mining_ore_expire_pending($nick);
            }
            if (in_array($err, [
                '이미 수령했거나 소멸한 광물이에요.',
                '1시간이 지나 소멸했어요.',
                '수령에 실패했어요.',
                '채굴 정보를 확인할 수 없습니다.',
            ], true)) {
                return ['ok' => false, 'data' => $err];
            }
            return ['ok' => false, 'data' => '수령에 실패했어요.'];
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

        $pending_after = null;
        if (isset($sync_read['sync']) && is_array($sync_read['sync']) && array_key_exists('pending', $sync_read['sync'])) {
            $pending_after = (float)$sync_read['sync']['pending'];
        }

        return array_merge([
            'ok' => true,
            'data' => $msg,
            'pending_added' => ($ore_key === 'eunchong_shard') ? 0.0 : max(0.0, (float)$pending_add),
            'pending_added_fmt' => ($ore_key === 'eunchong_shard')
                ? '0'
                : mining_fmt_ore_value($pending_add),
            'pending_after' => $pending_after,
        ], mining_ore_member_state($nick), [
            'sync' => $sync_read['sync'] ?? null,
        ]);
    }
}

if (!function_exists('mining_ore_def_sell_map')) {
    /** @return array<string,float> 구간 중간값(기대 지급가) */
    function mining_ore_def_sell_map(): array {
        $map = [];
        foreach (mining_ore_seed_def_rows() as $r) {
            $key = (string)$r[0];
            $map[$key] = function_exists('mining_ore_sell_amount')
                ? mining_ore_sell_amount($key)
                : 0.0;
        }
        return $map;
    }
}

if (!function_exists('mining_ore_catalog')) {
    /**
     * @return list<array{key:string,icon:string,label:string,sell_value:float,sell_value_fmt:string}>
     */
    function mining_ore_catalog(): array {
        $out = [];
        $rows = mining_ore_seed_def_rows();
        $build = static function (string $key, string $icon, string $label): array {
            $val = function_exists('mining_ore_sell_amount')
                ? mining_ore_sell_amount($key)
                : 0.0;
            $fmt = function_exists('mining_ore_sell_range_fmt')
                ? mining_ore_sell_range_fmt($key)
                : ($val > 0 ? '+' . mining_ore_fmt_amount($val) . '냥' : '—');
            return [
                'key' => $key,
                'icon' => $icon,
                'label' => $label,
                'sell_value' => $val,
                'sell_value_fmt' => $fmt,
            ];
        };
        if (function_exists('db_query') && function_exists('db_fetch')) {
            mining_ore_ensure_schema();
            $rs = @db_query("
                SELECT ore_key, icon, label
                FROM tb_mining_ore_def
                WHERE enabled = 1
                ORDER BY sort_order ASC, ore_key ASC
            ");
            if ($rs) {
                while ($row = db_fetch($rs)) {
                    $key = (string)($row['ore_key'] ?? '');
                    if ($key === '') {
                        continue;
                    }
                    $out[] = $build(
                        $key,
                        (string)($row['icon'] ?? ''),
                        (string)($row['label'] ?? $key)
                    );
                }
            }
        }
        if (!empty($out)) {
            return $out;
        }
        foreach ($rows as $d) {
            $out[] = $build((string)$d[0], (string)$d[1], (string)$d[2]);
        }
        return $out;
    }
}

if (!function_exists('mining_ore_pct_fmt')) {
    function mining_ore_pct_fmt(float $pct): string {
        if ($pct <= 0) {
            return '—';
        }
        $s = rtrim(rtrim(number_format($pct, 1, '.', ''), '0'), '.');
        return $s . '%';
    }
}

if (!function_exists('mining_ore_weapon_drop_matrix')) {
    /**
     * 강화별 광물 종류 확률 표 (지급 가중치와 동일)
     *
     * @return array{catalog:list<array>,rows:list<array>}
     */
    function mining_ore_weapon_drop_matrix(): array {
        $catalog = mining_ore_catalog();
        $rows = [];
        foreach (mining_ore_weapon_bonus_brackets() as $br) {
            $enh = (int)$br['enhance'];
            $stats = mining_ore_expected_find_stats($enh);
            $cells = [];
            foreach ($catalog as $ore) {
                $key = $ore['key'];
                $drop = $stats['drops'][$key] ?? null;
                $pct = $drop ? (float)$drop['pct'] : 0.0;
                $cells[$key] = [
                    'pct' => $pct,
                    'pct_fmt' => mining_ore_pct_fmt($pct),
                    'unlocked' => $pct > 0,
                ];
            }
            $hourly = mining_ore_hourly_find_expected($enh);
            $rows[] = [
                'enhance' => $enh,
                'label' => $br['label'],
                'hourly_count' => $hourly,
                'hourly_count_fmt' => mining_ore_fmt_count($hourly),
                'daily_count' => mining_ore_daily_find_count($enh),
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
        $weights = mining_ore_weight_table_grantable($enhance);
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
    /** 광물 냥 표시 — 올림 정수(소수점 없음) */
    function mining_ore_fmt_amount(float $v): string {
        $n = (int)ceil(max(0.0, $v));
        if (function_exists('냥_숫자콤마')) {
            return 냥_숫자콤마((string)$n);
        }
        return number_format($n);
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
            if (($d['pct'] ?? 0) <= 0) {
                continue;
            }
            $parts[] = ($icons[$key] ?? '') . (int)round($d['pct']) . '%';
        }
        return implode(' · ', $parts);
    }
}

if (!function_exists('mining_ore_weapon_bonus_brackets')) {
    /**
     * +10 … +91~100 구간 대표 강화(각 구간 상한 · 지급 비율과 동일)
     *
     * @return list<array{label:string,enhance:int}>
     */
    function mining_ore_weapon_bonus_brackets(): array {
        $max = function_exists('강화_최대') ? (int)강화_최대() : 100;
        $brackets = [];
        for ($decade = 1; $decade <= 10; $decade++) {
            $hi = min($decade * 10, $max);
            $lo = ($decade - 1) * 10 + 1;
            if ($lo > $max) {
                break;
            }
            // 결합은 +10↑ — 첫 구간은 +10만 해당
            $label = ($decade === 1)
                ? '+10'
                : ('+' . $lo . '~' . $hi);
            $brackets[] = [
                'label' => $label,
                'enhance' => $hi,
            ];
        }
        return $brackets;
    }
}

if (!function_exists('mining_ore_weapon_bonus_table')) {
    /**
     * 무기 +10~+100 구간 · 3시간·24h 광물 기대 표
     *
     * @return array{rows:list<array>}
     */
    function mining_ore_weapon_bonus_table(): array {
        $rows = [];
        foreach (mining_ore_weapon_bonus_brackets() as $br) {
            $enh = (int)$br['enhance'];
            $hourly = mining_ore_hourly_find_expected($enh);
            $daily = mining_ore_daily_find_count($enh);
            $stats = mining_ore_expected_find_stats($enh);
            $shard_pct = (float)($stats['drops']['eunchong_shard']['pct'] ?? 0);
            $hourly_bonus = $hourly * $stats['avg_value'];
            $daily_bonus = $daily * $stats['avg_value'];

            $rows[] = [
                'enhance' => $enh,
                'label' => $br['label'],
                'hourly_count' => $hourly,
                'hourly_count_fmt' => mining_ore_fmt_count($hourly),
                'daily_count' => $daily,
                'avg_find_value_fmt' => mining_ore_fmt_amount($stats['avg_value']),
                'hourly_bonus_fmt' => mining_ore_fmt_amount($hourly_bonus),
                'daily_bonus_fmt' => mining_ore_fmt_amount($daily_bonus),
                'daily_shards_fmt' => mining_ore_fmt_amount($daily * ($shard_pct / 100.0)),
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
