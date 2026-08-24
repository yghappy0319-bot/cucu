<?php
/**
 * 채굴 광물 — 크론 스케줄 (무기 장착 · 오프라인 포함)
 *
 * tb_member_mining.mining_ore_next_spawn_at 기준으로 spawn
 * 미터치 pending 은 expire_at 경과 시 expired (크론 일괄)
 */

require_once __DIR__ . '/mining_ore.inc.php';

if (!defined('MINING_ORE_CRON_BATCH')) {
    define('MINING_ORE_CRON_BATCH', 200);
}

if (!defined('MINING_ORE_CRON_MAX_SPAWN_PER_NICK')) {
    define('MINING_ORE_CRON_MAX_SPAWN_PER_NICK', 12);
}

if (!function_exists('mining_ore_schedule_ensure_columns')) {
    function mining_ore_schedule_ensure_columns(): void {
        mining_ore_ensure_member_columns();
        $tbl = MINING_TABLE;
        foreach ([
            'mining_ore_next_spawn_at' => "DATETIME DEFAULT NULL COMMENT '다음 광물 spawn 예정 시각'",
            'mining_ore_window_start' => "DATETIME DEFAULT NULL COMMENT '현재 60분 창 시작'",
        ] as $col => $def) {
            $exists = @db_select("SHOW COLUMNS FROM `{$tbl}` LIKE '{$col}'");
            if (empty($exists)) {
                @db_query("ALTER TABLE `{$tbl}` ADD COLUMN `{$col}` {$def}");
            }
        }
        $idx = @db_select("SHOW INDEX FROM `{$tbl}` WHERE Key_name = 'idx_ore_next_spawn'");
        if (empty($idx)) {
            @db_query("
                ALTER TABLE `{$tbl}`
                  ADD KEY `idx_ore_next_spawn` (`mining_weapon_equipped`, `mining_ore_next_spawn_at`)
            ");
        }
    }
}

if (!function_exists('mining_ore_schedule_row')) {
    function mining_ore_schedule_row($nick): ?array {
        mining_ore_schedule_ensure_columns();
        $nick_esc = mining_data_nick_esc($nick);
        if ($nick_esc === '') {
            return null;
        }
        if (!mining_data_ensure_row($nick)) {
            return null;
        }
        return db_select("
            SELECT mining_weapon_equipped,
                   mining_ore_next_spawn_at,
                   mining_ore_window_start,
                   mining_ore_roll_targets AS targets
            FROM `" . MINING_TABLE . "`
            WHERE nick = '{$nick_esc}'
            LIMIT 1
        ") ?: null;
    }
}

if (!function_exists('mining_ore_schedule_clear')) {
    /** 무기 해제 등 — 스케줄 초기화 */
    function mining_ore_schedule_clear($nick): void {
        mining_ore_schedule_ensure_columns();
        $nick_esc = mining_data_nick_esc($nick);
        if ($nick_esc === '') {
            return;
        }
        db_query("
            UPDATE `" . MINING_TABLE . "`
            SET mining_ore_next_spawn_at = NULL,
                mining_ore_window_start = NULL,
                mining_ore_roll_acc = 0,
                mining_ore_roll_targets = ''
            WHERE nick = '{$nick_esc}'
            LIMIT 1
        ");
    }
}

if (!function_exists('mining_ore_window_spawn_count')) {
    /** rolling 60분 창 내 생성( pending·claimed·expired ) 건수 */
    function mining_ore_window_spawn_count($nick, int $window_start_ts): int {
        $nick_esc = mining_data_nick_esc($nick);
        if ($nick_esc === '' || $window_start_ts <= 0) {
            return 0;
        }
        $from = date('Y-m-d H:i:s', $window_start_ts);
        $to = date('Y-m-d H:i:s', $window_start_ts + mining_ore_window_seconds());
        $row = db_select("
            SELECT COUNT(*) AS cnt
            FROM tb_mining_ore_find
            WHERE nick = '{$nick_esc}'
              AND found_at >= '{$from}'
              AND found_at < '{$to}'
        ");
        return (int)($row['cnt'] ?? 0);
    }
}

if (!function_exists('mining_ore_schedule_save')) {
    function mining_ore_schedule_save(
        $nick,
        ?string $next_spawn_at,
        ?string $window_start,
        array $targets
    ): void {
        $nick_esc = mining_data_nick_esc($nick);
        if ($nick_esc === '') {
            return;
        }
        $next_sql = ($next_spawn_at !== null && $next_spawn_at !== '')
            ? "'" . addslashes($next_spawn_at) . "'"
            : 'NULL';
        $win_sql = ($window_start !== null && $window_start !== '')
            ? "'" . addslashes($window_start) . "'"
            : 'NULL';
        $targets_esc = addslashes(mining_ore_encode_roll_targets($targets));
        db_query("
            UPDATE `" . MINING_TABLE . "`
            SET mining_ore_next_spawn_at = {$next_sql},
                mining_ore_window_start = {$win_sql},
                mining_ore_roll_targets = '{$targets_esc}',
                mining_ore_roll_acc = 0
            WHERE nick = '{$nick_esc}'
            LIMIT 1
        ");
    }
}

if (!function_exists('mining_ore_schedule_compute_next')) {
    /**
     * @return array{next_at:?string,window_start:string,targets:list<int>}
     */
    function mining_ore_schedule_compute_next($nick, array $ctx, ?string $window_start = null, array $targets = []): array {
        $window_sec = mining_ore_window_seconds();
        $now = time();
        $tool_lv = (int)($ctx['tool_level'] ?? 0);
        $find_count = mining_ore_effective_hourly_find_count((int)$ctx['enhance'], $tool_lv);

        if ($find_count <= 0) {
            return ['next_at' => null, 'window_start' => '', 'targets' => []];
        }

        if ($window_start === null || $window_start === '') {
            $window_start = date('Y-m-d H:i:s', $now);
        }
        $win_ts = strtotime($window_start);
        if ($win_ts <= 0) {
            $win_ts = $now;
            $window_start = date('Y-m-d H:i:s', $win_ts);
        }

        while ($now >= $win_ts + $window_sec) {
            $win_ts += $window_sec;
            $window_start = date('Y-m-d H:i:s', $win_ts);
            $targets = [];
        }

        if (empty($targets)) {
            $spawned = mining_ore_window_spawn_count($nick, $win_ts);
            if ($spawned >= $find_count) {
                $win_ts += $window_sec;
                $window_start = date('Y-m-d H:i:s', $win_ts);
                $targets = mining_ore_generate_roll_targets($find_count, $window_sec);
            } else {
                $remaining = max(0, $find_count - $spawned);
                $targets = mining_ore_generate_roll_targets($remaining, $window_sec);
            }
        }

        if (empty($targets)) {
            $next_ts = $win_ts + $window_sec + mining_ore_roll_min_seconds();
            return [
                'next_at' => date('Y-m-d H:i:s', $next_ts),
                'window_start' => $window_start,
                'targets' => [],
            ];
        }

        $offset = max(mining_ore_roll_min_seconds(), (int)$targets[0]);
        $next_ts = $win_ts + $offset;
        if ($next_ts <= $now) {
            $next_ts = $now + 1;
        }

        return [
            'next_at' => date('Y-m-d H:i:s', $next_ts),
            'window_start' => $window_start,
            'targets' => $targets,
        ];
    }
}

if (!function_exists('mining_ore_schedule_init')) {
    /** 무기 장착(+10~) 시 스케줄 시작 */
    function mining_ore_schedule_init($nick): bool {
        mining_ore_schedule_ensure_columns();
        $ctx = mining_ore_roll_context($nick);
        if (!$ctx['ok']) {
            mining_ore_schedule_clear($nick);
            return false;
        }
        if (!mining_data_ensure_row($nick)) {
            return false;
        }

        $row = mining_ore_schedule_row($nick);
        if (!empty($row['mining_ore_next_spawn_at']) && !empty($row['mining_ore_window_start'])) {
            return true;
        }

        $plan = mining_ore_schedule_compute_next($nick, $ctx);
        if ($plan['next_at'] === null) {
            mining_ore_schedule_clear($nick);
            return false;
        }
        mining_ore_schedule_save($nick, $plan['next_at'], $plan['window_start'], $plan['targets']);
        return true;
    }
}

if (!function_exists('mining_ore_schedule_after_spawn')) {
    function mining_ore_schedule_after_spawn($nick, array $ctx, string $spawned_at, string $window_start, array $targets): void {
        if (!empty($targets)) {
            array_shift($targets);
        }
        $win_ts = strtotime($window_start);
        $spawn_ts = strtotime($spawned_at);
        $window_sec = mining_ore_window_seconds();

        if ($win_ts > 0 && $spawn_ts >= $win_ts + $window_sec) {
            $plan = mining_ore_schedule_compute_next($nick, $ctx);
            mining_ore_schedule_save($nick, $plan['next_at'], $plan['window_start'], $plan['targets']);
            return;
        }

        if (!empty($targets)) {
            $next_ts = $win_ts + max(mining_ore_roll_min_seconds(), (int)$targets[0]);
            if ($next_ts <= time()) {
                $next_ts = time() + mining_ore_roll_min_seconds();
            }
            mining_ore_schedule_save($nick, date('Y-m-d H:i:s', $next_ts), $window_start, $targets);
            return;
        }

        $find_count = mining_ore_effective_hourly_find_count(
            (int)$ctx['enhance'],
            (int)($ctx['tool_level'] ?? 0)
        );
        $next_win_ts = ($win_ts > 0 ? $win_ts : time()) + $window_sec;
        $next_targets = $find_count > 0
            ? mining_ore_generate_roll_targets($find_count, $window_sec)
            : [];
        $next_ts = $next_win_ts + ($next_targets ? max(mining_ore_roll_min_seconds(), (int)$next_targets[0]) : mining_ore_roll_min_seconds());
        mining_ore_schedule_save(
            $nick,
            date('Y-m-d H:i:s', $next_ts),
            date('Y-m-d H:i:s', $next_win_ts),
            $next_targets
        );
    }
}

if (!function_exists('mining_ore_cron_spawn_one')) {
    /**
     * next_spawn_at 도래 1회 처리
     *
     * @return array{spawned:bool,reason:string}
     */
    function mining_ore_cron_spawn_one($nick): array {
        mining_ore_schedule_ensure_columns();
        $ctx = mining_ore_roll_context($nick);
        if (!$ctx['ok']) {
            mining_ore_schedule_clear($nick);
            return ['spawned' => false, 'reason' => 'no_weapon'];
        }

        $row = mining_ore_schedule_row($nick);
        if (empty($row['mining_ore_next_spawn_at'])) {
            mining_ore_schedule_init($nick);
            $row = mining_ore_schedule_row($nick);
        }
        if (empty($row['mining_ore_next_spawn_at'])) {
            return ['spawned' => false, 'reason' => 'no_schedule'];
        }

        $spawn_at = (string)$row['mining_ore_next_spawn_at'];
        if (strtotime($spawn_at) > time()) {
            return ['spawned' => false, 'reason' => 'not_due'];
        }

        $window_start = (string)($row['mining_ore_window_start'] ?? '');
        if ($window_start === '') {
            $window_start = date('Y-m-d H:i:s', time());
        }
        $targets = mining_ore_parse_roll_targets($row['targets'] ?? '');

        $win_ts = strtotime($window_start);
        $find_count = mining_ore_effective_hourly_find_count((int)$ctx['enhance'], (int)$ctx['tool_level']);
        if ($win_ts > 0 && mining_ore_window_spawn_count($nick, $win_ts) >= $find_count) {
            $plan = mining_ore_schedule_compute_next($nick, $ctx);
            mining_ore_schedule_save($nick, $plan['next_at'], $plan['window_start'], $plan['targets']);
            return ['spawned' => false, 'reason' => 'window_full'];
        }

        $payload = mining_ore_try_create_find_at(
            $nick,
            $ctx['item'],
            (int)$ctx['enhance'],
            $spawn_at
        );
        if ($payload === null) {
            mining_ore_schedule_after_spawn($nick, $ctx, $spawn_at, $window_start, $targets);
            return ['spawned' => false, 'reason' => 'create_failed'];
        }

        mining_ore_schedule_after_spawn($nick, $ctx, $spawn_at, $window_start, $targets);
        return ['spawned' => true, 'reason' => 'ok'];
    }
}

if (!function_exists('mining_ore_cron_process_due')) {
    /**
     * mining_ore_next_spawn_at <= NOW() 인 row만 처리
     *
     * @return array{processed:int,spawned:int,skipped:int}
     */
    function mining_ore_cron_process_due(int $batch = 0): array {
        mining_ore_schedule_ensure_columns();
        mining_ore_ensure_schema();
        $batch = $batch > 0 ? $batch : (int)MINING_ORE_CRON_BATCH;

        $rs = @db_query("
            SELECT nick
            FROM `" . MINING_TABLE . "`
            WHERE mining_weapon_equipped = 1
              AND mining_ore_next_spawn_at IS NOT NULL
              AND mining_ore_next_spawn_at <= NOW()
            ORDER BY mining_ore_next_spawn_at ASC
            LIMIT {$batch}
        ");

        $processed = 0;
        $spawned = 0;
        $skipped = 0;

        if (!$rs) {
            return compact('processed', 'spawned', 'skipped');
        }

        while ($row = db_fetch($rs)) {
            $nick = trim((string)($row['nick'] ?? ''));
            if ($nick === '') {
                continue;
            }
            $loops = 0;
            while ($loops < (int)MINING_ORE_CRON_MAX_SPAWN_PER_NICK) {
                $sched = mining_ore_schedule_row($nick);
                if (empty($sched['mining_ore_next_spawn_at'])
                    || strtotime((string)$sched['mining_ore_next_spawn_at']) > time()) {
                    break;
                }
                $result = mining_ore_cron_spawn_one($nick);
                $loops++;
                $processed++;
                if (!empty($result['spawned'])) {
                    $spawned++;
                } else {
                    $skipped++;
                    if (in_array($result['reason'], ['no_weapon', 'not_due', 'window_full'], true)) {
                        break;
                    }
                }
            }
        }

        return compact('processed', 'spawned', 'skipped');
    }
}

if (!function_exists('mining_ore_cron_seed_equipped')) {
    /** 장착 중인데 스케줄 없는 row — next_spawn_at 채우기 */
    function mining_ore_cron_seed_equipped(int $batch = 100): int {
        mining_ore_schedule_ensure_columns();
        $batch = max(1, min(500, $batch));
        $rs = @db_query("
            SELECT nick
            FROM `" . MINING_TABLE . "`
            WHERE mining_weapon_equipped = 1
              AND (mining_ore_next_spawn_at IS NULL OR mining_ore_window_start IS NULL)
            LIMIT {$batch}
        ");
        $n = 0;
        if ($rs) {
            while ($row = db_fetch($rs)) {
                $nick = trim((string)($row['nick'] ?? ''));
                if ($nick !== '' && mining_ore_schedule_init($nick)) {
                    $n++;
                }
            }
        }
        return $n;
    }
}

if (!function_exists('mining_ore_cron_expire_all')) {
    /** pending + expire_at 경과 → expired (전체) */
    function mining_ore_cron_expire_all(): int {
        mining_ore_ensure_schema();
        $rs = @db_query("
            SELECT idx, nick, ore_key, qty, weapon_item, weapon_enhance
            FROM tb_mining_ore_find
            WHERE status = 'pending'
              AND expire_at <= NOW()
            ORDER BY expire_at ASC
            LIMIT 500
        ");
        if (!$rs) {
            return 0;
        }
        $count = 0;
        while ($row = db_fetch($rs)) {
            $idx = (int)($row['idx'] ?? 0);
            $nick = (string)($row['nick'] ?? '');
            if ($idx <= 0 || $nick === '') {
                continue;
            }
            db_query("
                UPDATE tb_mining_ore_find
                SET status = 'expired'
                WHERE idx = {$idx} AND status = 'pending'
                LIMIT 1
            ");
            global $conn;
            $ok = ($conn instanceof mysqli) ? ((int)mysqli_affected_rows($conn) > 0) : true;
            if (!$ok) {
                continue;
            }
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
                '1시간 미터치 소멸(크론)'
            );
            $count++;
        }
        return $count;
    }
}

if (!function_exists('mining_ore_cron_run')) {
    function mining_ore_cron_run(): array {
        $expired = mining_ore_cron_expire_all();
        $seeded = mining_ore_cron_seed_equipped();
        $due = mining_ore_cron_process_due();
        return [
            'expired' => $expired,
            'seeded' => $seeded,
            'due' => $due,
        ];
    }
}
