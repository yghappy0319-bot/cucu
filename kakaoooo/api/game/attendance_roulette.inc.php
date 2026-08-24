<?php
/**
 * 출석 룰렛 — 당일 생타 구간별 티켓 · 자정(날짜) 기준 초기화
 *
 * 100→1 · 300→2 · 500→3 · 1000→5
 * 1500/2000/2500/3000 → 각 +5
 * 4000→+15 · 5000→+15 · 6000→+20 (최대 75장)
 * 지급: ATTENDANCE_ROULETTE_GRANT_ENABLED
 */

if (!defined('ATTENDANCE_ROULETTE_GRANT_ENABLED')) {
    /** true 로 켜면 실제 아이템 지급 */
    define('ATTENDANCE_ROULETTE_GRANT_ENABLED', true);
}

if (!defined('ATTENDANCE_ROULETTE_TABLE')) {
    define('ATTENDANCE_ROULETTE_TABLE', 'tb_attendance_roulette');
}
if (!defined('ATTENDANCE_ROULETTE_GRANT_TABLE')) {
    define('ATTENDANCE_ROULETTE_GRANT_TABLE', 'tb_attendance_roulette_grant');
}

if (!function_exists('attendance_roulette_test_nicks')) {
    /** 가방 바로가기·페이지 테스트 노출 닉 */
    function attendance_roulette_test_nicks(): array {
        return ['준호', '민호'];
    }
}

if (!function_exists('attendance_roulette_test_visible')) {
    function attendance_roulette_test_visible($nick): bool {
        $n = trim((string)$nick);
        if ($n === '') {
            return false;
        }
        if (function_exists('getTwoCharNick')) {
            $t = trim((string)getTwoCharNick($n));
            if ($t !== '') {
                $n = $t;
            }
        }
        return in_array($n, attendance_roulette_test_nicks(), true);
    }
}

if (!function_exists('attendance_roulette_ticket_tiers')) {
    /**
     * mode=max: 해당 구간까지 누적 최대 티켓
     * mode=add: 달성 시 추가 티켓 (1000타 이후 구간)
     * @return list<array{tasu:int,tickets:int,mode:string}>
     */
    function attendance_roulette_ticket_tiers(): array {
        return [
            ['tasu' => 100, 'tickets' => 1, 'mode' => 'max'],
            ['tasu' => 300, 'tickets' => 2, 'mode' => 'max'],
            ['tasu' => 500, 'tickets' => 3, 'mode' => 'max'],
            ['tasu' => 1000, 'tickets' => 5, 'mode' => 'max'],
            ['tasu' => 1500, 'tickets' => 5, 'mode' => 'add'],
            ['tasu' => 2000, 'tickets' => 5, 'mode' => 'add'],
            ['tasu' => 2500, 'tickets' => 5, 'mode' => 'add'],
            ['tasu' => 3000, 'tickets' => 5, 'mode' => 'add'],
            ['tasu' => 4000, 'tickets' => 15, 'mode' => 'add'],
            ['tasu' => 5000, 'tickets' => 15, 'mode' => 'add'],
            ['tasu' => 6000, 'tickets' => 20, 'mode' => 'add'],
        ];
    }
}

if (!function_exists('attendance_roulette_tickets_for_tasu')) {
    /**
     * 100~1000: 구간 최대치(1→2→3→5)
     * 1500~3000: 각 +5 · 4000/5000 +15 · 6000 +20 (최대 75)
     */
    function attendance_roulette_tickets_for_tasu(int $raw): int {
        $maxPart = 0;
        $addPart = 0;
        foreach (attendance_roulette_ticket_tiers() as $tier) {
            if ($raw < (int)$tier['tasu']) {
                continue;
            }
            $n = (int)$tier['tickets'];
            if (($tier['mode'] ?? 'max') === 'add') {
                $addPart += $n;
            } else {
                if ($n > $maxPart) {
                    $maxPart = $n;
                }
            }
        }
        return $maxPart + $addPart;
    }
}

if (!function_exists('attendance_roulette_next_tier')) {
    /** @return array{tasu:int,tickets:int}|null */
    function attendance_roulette_next_tier(int $raw): ?array {
        foreach (attendance_roulette_ticket_tiers() as $tier) {
            if ($raw < (int)$tier['tasu']) {
                return $tier;
            }
        }
        return null;
    }
}

if (!function_exists('attendance_roulette_본방냥총합')) {
    /** 우리방 본방냥(newpoint) 총합 */
    function attendance_roulette_본방냥총합(): float {
        if (function_exists('시세기준_본방냥')) {
            return max(0.0, (float)시세기준_본방냥());
        }
        if (function_exists('db_select')) {
            $row = @db_select("
                SELECT COALESCE(SUM(IFNULL(newpoint, 0)), 0) AS total_np
                FROM tb_member
                WHERE status = 0
            ");
            return max(0.0, (float)($row['total_np'] ?? 0));
        }
        return 0.0;
    }
}

if (!function_exists('attendance_roulette_np_from_pct')) {
    /** 본방냥 총합 × pct% → 지급 냥 (내림, 총합>0이면 최소 1) */
    function attendance_roulette_np_from_pct(float $pct): int {
        $pct = max(0.0, $pct);
        if ($pct <= 0) {
            return 0;
        }
        $total = attendance_roulette_본방냥총합();
        if ($total <= 0) {
            return 0;
        }
        $qty = (int)floor($total * ($pct / 100.0) + 1e-9);
        return max(1, $qty);
    }
}

if (!function_exists('attendance_roulette_resolve_reward')) {
    /**
     * pct 보상이면 실시간 본방냥 총합 기준으로 qty·label 확정
     * @param array{key?:string,label?:string,type?:string,qty?:int|float,pct?:int|float,weight?:int,index?:int} $reward
     * @return array
     */
    function attendance_roulette_resolve_reward(array $reward): array {
        $pct = (float)($reward['pct'] ?? 0);
        if ($pct > 0 && (string)($reward['type'] ?? '') === 'newpoint') {
            $qty = attendance_roulette_np_from_pct($pct);
            $reward['qty'] = $qty;
            // 화면·결과: % 문구 없이 금액만 (계산은 pct 유지)
            $reward['label'] = '본방냥 ' . number_format($qty);
        }
        return $reward;
    }
}

if (!function_exists('attendance_roulette_rewards')) {
    /**
     * weight: 상대 가중치 (합=100 기준 %에 가깝게)
     * @return list<array{key:string,label:string,type:string,qty:int,weight:int,pct?:float}>
     */
    function attendance_roulette_rewards(): array {
        $list = [
            ['key' => 'miss', 'label' => '꽝!', 'type' => 'miss', 'qty' => 0, 'weight' => 26],
            ['key' => 'shard_1', 'label' => '은총조각 1개', 'type' => 'shard', 'qty' => 1, 'weight' => 30],
            ['key' => 'shard_2', 'label' => '은총조각 2개', 'type' => 'shard', 'qty' => 2, 'weight' => 30],
            ['key' => 'shard_3', 'label' => '은총조각 3개', 'type' => 'shard', 'qty' => 3, 'weight' => 3],
            ['key' => 'shard_5', 'label' => '은총조각 5개', 'type' => 'shard', 'qty' => 5, 'weight' => 2],
            ['key' => 'eunchong_1', 'label' => '은총 1개', 'type' => 'eunchong', 'qty' => 1, 'weight' => 1],
            ['key' => 'np_05pct', 'label' => '본방냥 0.5%', 'type' => 'newpoint', 'qty' => 0, 'pct' => 0.5, 'weight' => 30],
        ];
        foreach ($list as $i => $r) {
            $list[$i] = attendance_roulette_resolve_reward($r);
        }
        return $list;
    }
}

if (!function_exists('attendance_roulette_pick')) {
    /** @return array{key:string,label:string,type:string,qty:int,weight?:int,index:int} */
    function attendance_roulette_pick(): array {
        $list = attendance_roulette_rewards();
        $total = 0;
        foreach ($list as $r) {
            $total += max(0, (int)($r['weight'] ?? 1));
        }
        if ($total < 1) {
            $r = $list[0];
            $r['index'] = 0;
            return $r;
        }
        $roll = random_int(1, $total);
        $acc = 0;
        foreach ($list as $i => $r) {
            $acc += max(0, (int)($r['weight'] ?? 1));
            if ($roll <= $acc) {
                $r['index'] = (int)$i;
                return $r;
            }
        }
        $last = count($list) - 1;
        $r = $list[$last];
        $r['index'] = $last;
        return $r;
    }
}

if (!function_exists('attendance_roulette_ensure_table')) {
    function attendance_roulette_ensure_table(): void {
        static $done = false;
        if ($done || !function_exists('db_query')) {
            return;
        }
        $done = true;
        $tbl = ATTENDANCE_ROULETTE_TABLE;
        @db_query("
            CREATE TABLE IF NOT EXISTS `{$tbl}` (
              `idx` INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `nick` VARCHAR(32) NOT NULL,
              `spin_date` DATE NOT NULL COMMENT '스핀 일자(서울 기준·자정 리셋)',
              `reward_key` VARCHAR(32) NOT NULL DEFAULT '',
              `reward_label` VARCHAR(64) NOT NULL DEFAULT '',
              `reward_type` VARCHAR(16) NOT NULL DEFAULT '',
              `reward_qty` INT UNSIGNED NOT NULL DEFAULT 0,
              `granted` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=실제 지급됨',
              `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`idx`),
              KEY `idx_nick_date` (`nick`, `spin_date`),
              KEY `idx_spin_date` (`spin_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='출석룰렛 일일 스핀(티켓 소모)'
        ");
        // 구버전 UNIQUE(nick,spin_date) → 하루 다회 스핀 허용
        $uniq = @db_select("SHOW INDEX FROM `{$tbl}` WHERE Key_name = 'uk_nick_date'");
        if (!empty($uniq)) {
            @db_query("ALTER TABLE `{$tbl}` DROP INDEX `uk_nick_date`");
            $has = @db_select("SHOW INDEX FROM `{$tbl}` WHERE Key_name = 'idx_nick_date'");
            if (empty($has)) {
                @db_query("ALTER TABLE `{$tbl}` ADD KEY `idx_nick_date` (`nick`, `spin_date`)");
            }
        }
        attendance_roulette_grant_ensure_table();
        // 민호 1회 초기화는 배포 시 완료 — ensure 때마다 지우면 티켓 차감이 안 된 것처럼 보임
    }
}

if (!function_exists('attendance_roulette_reset_nick')) {
    /** 닉의 스핀·지급 로그 전체 삭제 (테스트/초기화) */
    function attendance_roulette_reset_nick($nick): array {
        attendance_roulette_ensure_table();
        attendance_roulette_grant_ensure_table();
        $nick = trim((string)$nick);
        if ($nick === '' || !function_exists('db_query')) {
            return ['ok' => false, 'spin' => 0, 'grant' => 0];
        }
        $esc = addslashes($nick);
        $spin_tbl = ATTENDANCE_ROULETTE_TABLE;
        $grant_tbl = ATTENDANCE_ROULETTE_GRANT_TABLE;
        @db_query("DELETE FROM `{$grant_tbl}` WHERE nick = '{$esc}'");
        $grant_n = 0;
        global $conn;
        if ($conn instanceof mysqli) {
            $grant_n = (int)mysqli_affected_rows($conn);
        }
        @db_query("DELETE FROM `{$spin_tbl}` WHERE nick = '{$esc}'");
        $spin_n = 0;
        if ($conn instanceof mysqli) {
            $spin_n = (int)mysqli_affected_rows($conn);
        }
        return ['ok' => true, 'spin' => $spin_n, 'grant' => $grant_n];
    }
}

if (!function_exists('attendance_roulette_oneshot_reset_minho')) {
    /** 민호 룰렛 데이터 1회 초기화 (오픈 배포용) */
    function attendance_roulette_oneshot_reset_minho(): void {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        $flag = __DIR__ . '/.attendance_roulette_reset_minho_20260730';
        if (is_file($flag)) {
            return;
        }
        attendance_roulette_reset_nick('민호');
        @file_put_contents($flag, date('c') . " reset 민호\n");
    }
}

if (!function_exists('attendance_roulette_grant_ensure_table')) {
    /** 지급·회수 감사 로그 (과지급 회수용) */
    function attendance_roulette_grant_ensure_table(): void {
        static $done = false;
        if ($done || !function_exists('db_query')) {
            return;
        }
        $done = true;
        $tbl = ATTENDANCE_ROULETTE_GRANT_TABLE;
        @db_query("
            CREATE TABLE IF NOT EXISTS `{$tbl}` (
              `idx` INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `spin_idx` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'tb_attendance_roulette.idx',
              `nick` VARCHAR(32) NOT NULL,
              `spin_date` DATE NOT NULL,
              `reward_key` VARCHAR(32) NOT NULL DEFAULT '',
              `reward_label` VARCHAR(64) NOT NULL DEFAULT '',
              `reward_type` VARCHAR(16) NOT NULL DEFAULT '' COMMENT 'shard|eunchong|newpoint',
              `reward_qty` INT UNSIGNED NOT NULL DEFAULT 0,
              `status` VARCHAR(16) NOT NULL DEFAULT 'granted' COMMENT 'granted|revoked|failed',
              `balance_before` VARCHAR(32) NOT NULL DEFAULT '' COMMENT '지급 직전 보유(참고)',
              `balance_after` VARCHAR(32) NOT NULL DEFAULT '' COMMENT '지급 직후 보유(참고)',
              `note` VARCHAR(255) NOT NULL DEFAULT '',
              `revoked_at` DATETIME DEFAULT NULL,
              `revoked_by` VARCHAR(32) NOT NULL DEFAULT '',
              `revoke_note` VARCHAR(255) NOT NULL DEFAULT '',
              `revoke_qty` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '실제 회수량',
              `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`idx`),
              KEY `idx_spin` (`spin_idx`),
              KEY `idx_nick_date` (`nick`, `spin_date`),
              KEY `idx_status` (`status`),
              KEY `idx_created` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='출석룰렛 지급·회수 기록'
        ");
    }
}

if (!function_exists('attendance_roulette_today')) {
    function attendance_roulette_today(): string {
        try {
            return (new DateTime('now', new DateTimeZone('Asia/Seoul')))->format('Y-m-d');
        } catch (Throwable $e) {
            return date('Y-m-d');
        }
    }
}

if (!function_exists('attendance_roulette_tasu_today')) {
    /** @return array{raw:int,buff:int} */
    function attendance_roulette_tasu_today($nick): array {
        $raw = 0;
        $buff = 0;
        if (function_exists('wallet_today_tasu')) {
            $t = wallet_today_tasu($nick);
            $raw = (int)($t['raw'] ?? 0);
            $buff = (int)($t['buff'] ?? 0);
        } elseif (function_exists('db_select')) {
            $esc = addslashes(trim((string)$nick));
            $raw_expr = function_exists('생타_SQL_select_expr')
                ? 생타_SQL_select_expr('msg')
                : 'COUNT(*)';
            $row = @db_select("
                SELECT {$raw_expr} AS cnt2, " . (function_exists('버프타_SQL_select_expr') ? 버프타_SQL_select_expr('msg', 'tasu') : 'COALESCE(SUM(tasu), 0)') . " AS cnt
                FROM tb_msg
                WHERE nickname = '{$esc}'
                  AND tasu != 0
                  AND regdate >= CURDATE()
                  AND regdate < CURDATE() + INTERVAL 1 DAY
            ");
            $raw = (int)($row['cnt2'] ?? 0);
            $buff = (int)($row['cnt'] ?? 0);
        }
        return [
            'raw' => $raw,
            'buff' => $buff,
        ];
    }
}

if (!function_exists('attendance_roulette_nick')) {
    /** 저장·조회용 닉 정규화 */
    function attendance_roulette_nick($nick): string {
        $nick = trim((string)$nick);
        if ($nick === '') {
            return '';
        }
        if (function_exists('getTwoCharNick')) {
            $t = trim((string)getTwoCharNick($nick));
            if ($t !== '') {
                return $t;
            }
        }
        return $nick;
    }
}

if (!function_exists('attendance_roulette_spins_today')) {
    /** 오늘 룰렛 스핀으로 쓴 티켓 수 · 날짜 바뀌면 0 (자정 초기화) */
    function attendance_roulette_spins_today($nick): int {
        attendance_roulette_ensure_table();
        $nick = attendance_roulette_nick($nick);
        if ($nick === '' || !function_exists('db_select')) {
            return 0;
        }
        $esc = addslashes($nick);
        $day = attendance_roulette_today();
        $tbl = ATTENDANCE_ROULETTE_TABLE;
        $row = @db_select("
            SELECT COUNT(*) AS c
            FROM `{$tbl}`
            WHERE nick = '{$esc}' AND spin_date = '{$day}'
        ");
        return (int)($row['c'] ?? 0);
    }
}

if (!function_exists('attendance_roulette_dice2_ticket_uses_today')) {
    /**
     * 오늘 빚탕감 `.주사위 2` 추가사용으로 소모한 출석룰렛 티켓 수
     * (무료 시간당 1회와 별개 · status: 주사위2-티켓 / 주사위2-티켓더블)
     */
    function attendance_roulette_dice2_ticket_uses_today($nick): int {
        $nick = attendance_roulette_nick($nick);
        if ($nick === '' || !function_exists('db_select')) {
            return 0;
        }
        $esc = addslashes($nick);
        $day = attendance_roulette_today();
        $row = @db_select("
            SELECT COUNT(*) AS c
            FROM tb_point_log
            WHERE nick = '{$esc}'
              AND status IN ('주사위2-티켓', '주사위2-티켓더블')
              AND DATE(regdate) = '{$day}'
        ");
        return (int)($row['c'] ?? 0);
    }
}

if (!function_exists('attendance_roulette_tickets_left')) {
    /** 오늘 남은 출석룰렛 티켓 (룰렛 스핀 + 빚탕감 주사위 추가사용 반영) */
    function attendance_roulette_tickets_left($nick): int {
        $nick = attendance_roulette_nick($nick);
        if ($nick === '') {
            return 0;
        }
        $tasu = attendance_roulette_tasu_today($nick);
        $earned = attendance_roulette_tickets_for_tasu((int)($tasu['raw'] ?? 0));
        $used = attendance_roulette_spins_today($nick)
            + attendance_roulette_dice2_ticket_uses_today($nick);
        return max(0, $earned - $used);
    }
}

if (!function_exists('attendance_roulette_today_results')) {
    /** @return list<array<string,mixed>> */
    function attendance_roulette_today_results($nick, int $limit = 10): array {
        attendance_roulette_ensure_table();
        $nick = trim((string)$nick);
        if ($nick === '' || !function_exists('db_query')) {
            return [];
        }
        $esc = addslashes($nick);
        $day = attendance_roulette_today();
        $tbl = ATTENDANCE_ROULETTE_TABLE;
        $limit = max(1, min(50, $limit));
        $rs = @db_query("
            SELECT idx, reward_key, reward_label, reward_type, reward_qty, granted, created_at
            FROM `{$tbl}`
            WHERE nick = '{$esc}' AND spin_date = '{$day}'
            ORDER BY idx DESC
            LIMIT {$limit}
        ");
        $out = [];
        if ($rs) {
            while ($row = mysqli_fetch_assoc($rs)) {
                $out[] = $row;
            }
        }
        return $out;
    }
}

if (!function_exists('attendance_roulette_balance_snapshot')) {
    /** 지급 전후 보유량 스냅샷 (회수 참고용) */
    function attendance_roulette_balance_snapshot($nick, string $type): string {
        $nick = trim((string)$nick);
        if ($nick === '') {
            return '';
        }
        if ($type === 'shard') {
            if (!function_exists('mining_ore_shard_count') && is_file(__DIR__ . '/mining_ore.inc.php')) {
                require_once __DIR__ . '/mining_ore.inc.php';
            }
            return function_exists('mining_ore_shard_count')
                ? (string)(int)mining_ore_shard_count($nick)
                : '';
        }
        if ($type === 'eunchong') {
            if (!function_exists('bag_은총_수량') && is_file(__DIR__ . '/../item_bag_enhance.inc.php')) {
                require_once __DIR__ . '/../item_bag_enhance.inc.php';
            }
            return function_exists('bag_은총_수량')
                ? (string)(int)bag_은총_수량($nick)
                : '';
        }
        if ($type === 'newpoint') {
            $esc = addslashes($nick);
            $row = @db_select("SELECT IFNULL(newpoint, 0) AS np FROM tb_member WHERE name = '{$esc}' LIMIT 1");
            return (string)(int)floor((float)($row['np'] ?? 0));
        }
        return '';
    }
}

if (!function_exists('attendance_roulette_grant_log')) {
    /**
     * @return int 로그 idx (0=실패)
     */
    function attendance_roulette_grant_log(
        $nick,
        int $spin_idx,
        array $reward,
        string $status,
        string $before = '',
        string $after = '',
        string $note = ''
    ): int {
        attendance_roulette_grant_ensure_table();
        $nick = attendance_roulette_nick($nick);
        if ($nick === '' || !function_exists('db_query')) {
            return 0;
        }
        $esc = addslashes($nick);
        $day = attendance_roulette_today();
        $key = addslashes((string)($reward['key'] ?? ''));
        $label = addslashes((string)($reward['label'] ?? ''));
        $type = addslashes((string)($reward['type'] ?? ''));
        $qty = max(0, (int)($reward['qty'] ?? 0));
        $status = addslashes($status);
        $before = addslashes($before);
        $after = addslashes($after);
        $note = addslashes(mb_substr($note, 0, 240));
        $tbl = ATTENDANCE_ROULETTE_GRANT_TABLE;
        $ok = @db_query("
            INSERT INTO `{$tbl}`
              (spin_idx, nick, spin_date, reward_key, reward_label, reward_type, reward_qty,
               status, balance_before, balance_after, note)
            VALUES
              ({$spin_idx}, '{$esc}', '{$day}', '{$key}', '{$label}', '{$type}', {$qty},
               '{$status}', '{$before}', '{$after}', '{$note}')
        ");
        if (!$ok) {
            return 0;
        }
        global $conn;
        return ($conn instanceof mysqli) ? (int)mysqli_insert_id($conn) : 0;
    }
}

if (!function_exists('attendance_roulette_grant_list')) {
    /**
     * 지급 내역 (누적 · 최신순)
     * @return list<array<string,mixed>>
     */
    function attendance_roulette_grant_list($nick, int $limit = 50, $today_only = false): array {
        attendance_roulette_grant_ensure_table();
        $nick = attendance_roulette_nick($nick);
        if ($nick === '' || !function_exists('db_query')) {
            return [];
        }
        $esc = addslashes($nick);
        $tbl = ATTENDANCE_ROULETTE_GRANT_TABLE;
        $limit = max(1, min(200, $limit));
        $where = "nick = '{$esc}'";
        if ($today_only) {
            $day = attendance_roulette_today();
            $where .= " AND spin_date = '{$day}'";
        }
        $rs = @db_query("
            SELECT idx, spin_idx, nick, spin_date, reward_key, reward_label, reward_type, reward_qty,
                   status, balance_before, balance_after, note,
                   revoked_at, revoked_by, revoke_note, revoke_qty, created_at
            FROM `{$tbl}`
            WHERE {$where}
            ORDER BY idx DESC
            LIMIT {$limit}
        ");
        $out = [];
        if ($rs) {
            while ($row = mysqli_fetch_assoc($rs)) {
                $out[] = $row;
            }
        }
        return $out;
    }
}

if (!function_exists('attendance_roulette_revoke_grant')) {
    /**
     * 과지급 회수 (관리자용 · 추후 UI 연결)
     * @return array{ok:bool,data:string,revoke_qty?:int}
     */
    function attendance_roulette_revoke_grant(int $grant_idx, $admin_nick = '', string $note = ''): array {
        attendance_roulette_grant_ensure_table();
        $grant_idx = max(0, $grant_idx);
        if ($grant_idx < 1 || !function_exists('db_select')) {
            return ['ok' => false, 'data' => '지급 기록을 찾을 수 없어요.'];
        }
        $tbl = ATTENDANCE_ROULETTE_GRANT_TABLE;
        $row = @db_select("SELECT * FROM `{$tbl}` WHERE idx = {$grant_idx} LIMIT 1");
        if (empty($row['idx'])) {
            return ['ok' => false, 'data' => '지급 기록이 없어요.'];
        }
        if ((string)($row['status'] ?? '') === 'revoked') {
            return ['ok' => false, 'data' => '이미 회수된 지급이에요.'];
        }
        if ((string)($row['status'] ?? '') !== 'granted') {
            return ['ok' => false, 'data' => '회수할 수 있는 지급 상태가 아니에요.'];
        }

        $nick = trim((string)($row['nick'] ?? ''));
        $type = (string)($row['reward_type'] ?? '');
        $qty = max(0, (int)($row['reward_qty'] ?? 0));
        if ($type === 'miss') {
            @db_query("
                UPDATE `{$tbl}`
                SET status = 'revoked',
                    revoked_at = NOW(),
                    revoked_by = '" . addslashes(trim((string)$admin_nick)) . "',
                    revoke_note = '" . addslashes(mb_substr($note !== '' ? $note : '꽝 기록 처리', 0, 240)) . "',
                    revoke_qty = 0
                WHERE idx = {$grant_idx}
                LIMIT 1
            ");
            return ['ok' => true, 'data' => '꽝 기록은 회수할 아이템이 없어요.', 'revoke_qty' => 0];
        }
        if ($nick === '' || $qty < 1) {
            return ['ok' => false, 'data' => '회수 정보가 부족해요.'];
        }

        $revoked_qty = 0;
        if ($type === 'shard') {
            if (!function_exists('mining_ore_spend_shards') && is_file(__DIR__ . '/mining_ore.inc.php')) {
                require_once __DIR__ . '/mining_ore.inc.php';
            }
            if (!function_exists('mining_ore_spend_shards')) {
                return ['ok' => false, 'data' => '은총조각 회수 함수가 없어요.'];
            }
            $have = function_exists('mining_ore_shard_count') ? (int)mining_ore_shard_count($nick) : 0;
            $take = min($qty, max(0, $have));
            if ($take < 1) {
                return ['ok' => false, 'data' => '보유 은총조각이 없어 회수할 수 없어요.'];
            }
            $r = mining_ore_spend_shards($nick, $take);
            if (empty($r['ok'])) {
                return ['ok' => false, 'data' => (string)($r['data'] ?? '은총조각 회수 실패')];
            }
            $revoked_qty = $take;
        } elseif ($type === 'eunchong') {
            if (!function_exists('bag_은총_차감') && is_file(__DIR__ . '/../item_bag_enhance.inc.php')) {
                require_once __DIR__ . '/../item_bag_enhance.inc.php';
            }
            if (!function_exists('bag_은총_차감')) {
                return ['ok' => false, 'data' => '은총 회수 함수가 없어요.'];
            }
            $have = function_exists('bag_은총_수량') ? (int)bag_은총_수량($nick) : 0;
            $take = min($qty, max(0, $have));
            if ($take < 1) {
                return ['ok' => false, 'data' => '보유 은총이 없어 회수할 수 없어요.'];
            }
            $r = bag_은총_차감($nick, $take);
            if (empty($r['ok'])) {
                return ['ok' => false, 'data' => (string)($r['data'] ?? '은총 회수 실패')];
            }
            $revoked_qty = $take;
        } elseif ($type === 'newpoint') {
            $esc = addslashes($nick);
            $mem = @db_select("SELECT IFNULL(newpoint, 0) AS np FROM tb_member WHERE name = '{$esc}' LIMIT 1");
            $have = (int)floor((float)($mem['np'] ?? 0));
            $take = min($qty, max(0, $have));
            if ($take < 1) {
                return ['ok' => false, 'data' => '보유 본방냥이 없어 회수할 수 없어요.'];
            }
            $rs = @db_query("
                UPDATE tb_member
                SET newpoint = GREATEST(IFNULL(newpoint, 0) - {$take}, 0)
                WHERE name = '{$esc}'
                LIMIT 1
            ");
            if (!$rs) {
                return ['ok' => false, 'data' => '본방냥 회수 실패'];
            }
            if (function_exists('지급로그')) {
                지급로그('출석룰렛회수', $nick, (string)($row['reward_label'] ?? ''), 0, -$take);
            }
            $revoked_qty = $take;
        } else {
            return ['ok' => false, 'data' => '알 수 없는 보상 유형'];
        }

        $admin = addslashes(trim((string)$admin_nick));
        $rnote = addslashes(mb_substr($note !== '' ? $note : '과지급 회수', 0, 240));
        @db_query("
            UPDATE `{$tbl}`
            SET status = 'revoked',
                revoked_at = NOW(),
                revoked_by = '{$admin}',
                revoke_note = '{$rnote}',
                revoke_qty = {$revoked_qty}
            WHERE idx = {$grant_idx}
            LIMIT 1
        ");

        $partial = ($revoked_qty < $qty) ? " (부분회수 {$revoked_qty}/{$qty})" : '';
        return [
            'ok' => true,
            'data' => '회수 완료' . $partial . ' · ' . (string)($row['reward_label'] ?? ''),
            'revoke_qty' => $revoked_qty,
        ];
    }
}

if (!function_exists('attendance_roulette_force_grant_shard')) {
    /**
     * 은총조각 강제 지급 — 목표 보유량까지 재시도 (룰렛 전용 · 실패 없음)
     * @return array{before:int,after:int,added:int,capped:bool}
     */
    function attendance_roulette_force_grant_shard($nick, int $qty): array {
        $qty = max(1, $qty);
        $max = defined('MINING_ORE_EUNCHONG_SHARDS') ? max(1, (int)MINING_ORE_EUNCHONG_SHARDS) : 500;
        $esc = addslashes(trim((string)$nick));

        if (!function_exists('mining_ore_add_shards') && is_file(__DIR__ . '/mining_ore.inc.php')) {
            require_once __DIR__ . '/mining_ore.inc.php';
        }
        if (function_exists('mining_ore_ensure_schema')) {
            mining_ore_ensure_schema();
        }
        if (function_exists('mining_ore_ensure_member_columns')) {
            mining_ore_ensure_member_columns();
        }
        if (function_exists('mining_data_ensure_row')) {
            mining_data_ensure_row($nick);
        }

        $tbl = defined('MINING_TABLE') ? MINING_TABLE : 'tb_member_mining';
        $col = @db_select("SHOW COLUMNS FROM `{$tbl}` LIKE 'mining_eunchong_shard'");
        if (empty($col)) {
            $shardDef = function_exists('mining_ore_shard_column_def')
                ? mining_ore_shard_column_def()
                : "SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '은총조각'";
            @db_query("ALTER TABLE `{$tbl}` ADD COLUMN `mining_eunchong_shard` {$shardDef}");
        }
        @db_query("INSERT IGNORE INTO `{$tbl}` (nick) VALUES ('{$esc}')");

        $row0 = @db_select("SELECT IFNULL(mining_eunchong_shard, 0) AS shard FROM `{$tbl}` WHERE nick = '{$esc}' LIMIT 1");
        $before = max(0, (int)($row0['shard'] ?? 0));
        $target = min($max, $before + $qty);

        if (function_exists('mining_ore_add_shards')) {
            mining_ore_add_shards($nick, $qty);
        }

        $after = $before;
        for ($i = 0; $i < 5; $i++) {
            $row = @db_select("SELECT IFNULL(mining_eunchong_shard, 0) AS shard FROM `{$tbl}` WHERE nick = '{$esc}' LIMIT 1");
            $after = max(0, (int)($row['shard'] ?? 0));
            if ($after >= $target) {
                break;
            }
            // 목표값으로 직접 SET (중복 가산 없음)
            @db_query("
                UPDATE `{$tbl}`
                SET mining_eunchong_shard = {$target}
                WHERE nick = '{$esc}'
                LIMIT 1
            ");
        }

        $rowF = @db_select("SELECT IFNULL(mining_eunchong_shard, 0) AS shard FROM `{$tbl}` WHERE nick = '{$esc}' LIMIT 1");
        $after = max(0, (int)($rowF['shard'] ?? $target));
        // 그래도 미달이면 목표값으로 최종 1회
        if ($after < $target) {
            @db_query("UPDATE `{$tbl}` SET mining_eunchong_shard = {$target} WHERE nick = '{$esc}' LIMIT 1");
            $after = $target;
        }

        return [
            'before' => $before,
            'after' => $after,
            'added' => max(0, $after - $before),
            'capped' => ($before + $qty) > $max,
        ];
    }
}

if (!function_exists('attendance_roulette_grant_reward')) {
    /**
     * 실제 지급 — 룰렛 보상은 실패 없이 무조건 지급(granted=true)
     * @return array{ok:bool,granted:bool,data:string,before?:string,after?:string}
     */
    function attendance_roulette_grant_reward($nick, array $reward): array {
        $nick = trim((string)$nick);
        $type = (string)($reward['type'] ?? '');
        $qty = max(0, (int)($reward['qty'] ?? 0));
        $label = (string)($reward['label'] ?? '');

        if (!ATTENDANCE_ROULETTE_GRANT_ENABLED) {
            return [
                'ok' => true,
                'granted' => true,
                'data' => '지급 보류(테스트 모드) · ' . $label,
                'before' => '',
                'after' => '',
            ];
        }
        if ($nick === '') {
            // 닉 없으면 스핀 자체가 불가 — 여기선 형식만 맞춤
            return ['ok' => true, 'granted' => true, 'data' => $label . ' 지급 완료', 'before' => '', 'after' => ''];
        }

        // 꽝: 티켓만 소모 · 아이템 없음
        if ($type === 'miss') {
            return [
                'ok' => true,
                'granted' => true,
                'data' => '꽝! 다음 기회에…',
                'before' => '',
                'after' => '',
            ];
        }

        if ($qty < 1) {
            return ['ok' => true, 'granted' => true, 'data' => $label . ' 지급 완료', 'before' => '', 'after' => ''];
        }

        $before = attendance_roulette_balance_snapshot($nick, $type);

        if ($type === 'shard') {
            $r = attendance_roulette_force_grant_shard($nick, $qty);
            $after = (string)(int)($r['after'] ?? 0);
            $beforeS = (string)(int)($r['before'] ?? (int)$before);
            $added = max(0, (int)($r['added'] ?? 0));
            $msg = $label . ' 지급 완료';
            if ($added > 0) {
                $msg .= " (+{$added} · 보유 {$after})";
            } elseif (!empty($r['capped'])) {
                $msg .= ' (보유 상한)';
            } else {
                $msg .= " (보유 {$after})";
            }
            return ['ok' => true, 'granted' => true, 'data' => $msg, 'before' => $beforeS, 'after' => $after];
        }

        if ($type === 'eunchong') {
            if (!function_exists('bag_은총_가산') && is_file(__DIR__ . '/../item_bag_enhance.inc.php')) {
                require_once __DIR__ . '/../item_bag_enhance.inc.php';
            }
            for ($i = 0; $i < 5; $i++) {
                if (function_exists('bag_은총_가산')) {
                    $r = bag_은총_가산($nick, $qty);
                    if (!empty($r['ok'])) {
                        break;
                    }
                }
            }
            $after = attendance_roulette_balance_snapshot($nick, $type);
            // 미반영이면 직접 가방/컬럼 경로 한 번 더 시도는 bag 함수에 위임 — 결과는 무조건 granted
            return ['ok' => true, 'granted' => true, 'data' => $label . ' 지급 완료 (보유 ' . $after . ')', 'before' => $before, 'after' => $after];
        }

        if ($type === 'newpoint') {
            // pct 보상이면 지급 직전 한 번 더 확정 (시총 변동 반영)
            if (!empty($reward['pct'])) {
                $reward = attendance_roulette_resolve_reward($reward);
                $qty = max(0, (int)($reward['qty'] ?? 0));
                $label = (string)($reward['label'] ?? $label);
            }
            $esc = addslashes($nick);
            $pay = (int)$qty;
            if ($pay < 1) {
                return ['ok' => true, 'granted' => true, 'data' => $label . ' 지급 완료', 'before' => $before, 'after' => $before];
            }
            for ($i = 0; $i < 5; $i++) {
                @db_query("
                    UPDATE tb_member
                    SET newpoint = IFNULL(newpoint, 0) + {$pay}
                    WHERE name = '{$esc}'
                    LIMIT 1
                ");
                $afterTry = attendance_roulette_balance_snapshot($nick, $type);
                if ((int)$afterTry >= (int)$before + $pay) {
                    break;
                }
            }
            $after = attendance_roulette_balance_snapshot($nick, $type);
            if (function_exists('지급로그')) {
                지급로그('출석룰렛', $nick, $label, 0, $pay);
            }
            return ['ok' => true, 'granted' => true, 'data' => $label . ' 지급 완료 (+' . number_format($pay) . ')', 'before' => $before, 'after' => $after];
        }

        // 알 수 없는 유형도 스핀 기록은 유지 — 실패로 돌리지 않음
        return ['ok' => true, 'granted' => true, 'data' => $label . ' 지급 완료', 'before' => $before, 'after' => $before];
    }
}

if (!function_exists('attendance_roulette_bag_holdings')) {
    /** @return array{newpoint:int,newpoint_fmt:string,eunchong:int,shard:int} */
    function attendance_roulette_bag_holdings($nick): array {
        $nick = trim((string)$nick);
        $np = 0;
        $eunchong = 0;
        $shard = 0;
        if ($nick !== '' && function_exists('db_select')) {
            $esc = addslashes($nick);
            $row = @db_select("SELECT IFNULL(newpoint, 0) AS np FROM tb_member WHERE name = '{$esc}' LIMIT 1");
            $np = (int)floor((float)($row['np'] ?? 0));
        }
        if (!function_exists('bag_은총_수량') && is_file(__DIR__ . '/../item_bag_enhance.inc.php')) {
            require_once __DIR__ . '/../item_bag_enhance.inc.php';
        }
        if (function_exists('bag_은총_수량') && $nick !== '') {
            $eunchong = (int)bag_은총_수량($nick);
        }
        if (!function_exists('mining_ore_shard_count') && is_file(__DIR__ . '/mining_ore.inc.php')) {
            require_once __DIR__ . '/mining_ore.inc.php';
        }
        if (function_exists('mining_ore_shard_count') && $nick !== '') {
            $shard = (int)mining_ore_shard_count($nick);
        }
        $np_fmt = function_exists('wallet_fmt_new')
            ? (string)wallet_fmt_new($np)
            : number_format($np);
        return [
            'newpoint' => $np,
            'newpoint_fmt' => $np_fmt,
            'eunchong' => $eunchong,
            'shard' => $shard,
        ];
    }
}

if (!function_exists('attendance_roulette_state')) {
    /** @return array<string,mixed> */
    function attendance_roulette_state($nick): array {
        $nick = attendance_roulette_nick($nick);
        $tasu = attendance_roulette_tasu_today($nick);
        $raw = (int)($tasu['raw'] ?? 0);
        $earned = attendance_roulette_tickets_for_tasu($raw);
        $spin_used = attendance_roulette_spins_today($nick);
        $dice2_used = attendance_roulette_dice2_ticket_uses_today($nick);
        $used = $spin_used + $dice2_used;
        $left = max(0, $earned - $used);
        $can_spin = $left > 0;
        $results = attendance_roulette_today_results($nick, 10);
        $last = $results[0] ?? null;
        $next = attendance_roulette_next_tier($raw);
        $grants = attendance_roulette_grant_list($nick, 50, true);
        $bag = attendance_roulette_bag_holdings($nick);

        return [
            'nick' => $nick,
            'today' => attendance_roulette_today(),
            'tasu' => $tasu,
            'bag' => $bag,
            'tickets' => [
                'earned' => $earned,
                'used' => $used,
                'spin_used' => $spin_used,
                'dice2_used' => $dice2_used,
                'left' => $left,
                'tiers' => attendance_roulette_ticket_tiers(),
                'next' => $next,
                'reset' => '매일 자정(서울) · 날짜 바뀌면 티켓·사용횟수 초기화 · 빚탕감 .주사위 2 추가사용에도 소모',
            ],
            'spun' => $used > 0 ? 1 : 0,
            'can_spin' => $can_spin ? 1 : 0,
            'grant_enabled' => ATTENDANCE_ROULETTE_GRANT_ENABLED ? 1 : 0,
            'rewards' => attendance_roulette_rewards(),
            'results' => array_map(static function ($row) {
                return [
                    'label' => (string)($row['reward_label'] ?? ''),
                    'granted' => (int)($row['granted'] ?? 0),
                    'at' => (string)($row['created_at'] ?? ''),
                ];
            }, $results),
            'grants' => array_map(static function ($row) {
                return [
                    'idx' => (int)($row['idx'] ?? 0),
                    'label' => (string)($row['reward_label'] ?? ''),
                    'type' => (string)($row['reward_type'] ?? ''),
                    'qty' => (int)($row['reward_qty'] ?? 0),
                    'status' => (string)($row['status'] ?? ''),
                    'before' => (string)($row['balance_before'] ?? ''),
                    'after' => (string)($row['balance_after'] ?? ''),
                    'at' => (string)($row['created_at'] ?? ''),
                    'revoked_at' => (string)($row['revoked_at'] ?? ''),
                    'revoke_qty' => (int)($row['revoke_qty'] ?? 0),
                ];
            }, $grants),
            'result' => $last ? [
                'key' => (string)($last['reward_key'] ?? ''),
                'label' => (string)($last['reward_label'] ?? ''),
                'type' => (string)($last['reward_type'] ?? ''),
                'qty' => (int)($last['reward_qty'] ?? 0),
                'granted' => (int)($last['granted'] ?? 0),
                'at' => (string)($last['created_at'] ?? ''),
            ] : null,
        ];
    }
}

if (!function_exists('attendance_roulette_spin')) {
    /**
     * @return array{ok:bool,data:string,...}
     */
    function attendance_roulette_spin($nick): array {
        $nick = attendance_roulette_nick($nick);
        if ($nick === '') {
            return ['ok' => false, 'data' => '닉네임을 확인할 수 없어요.'];
        }
        attendance_roulette_ensure_table();

        $state = attendance_roulette_state($nick);
        $left = (int)($state['tickets']['left'] ?? 0);
        if ($left < 1) {
            $earned = (int)($state['tickets']['earned'] ?? 0);
            $used = (int)($state['tickets']['used'] ?? 0);
            $raw = (int)($state['tasu']['raw'] ?? 0);
            $next = $state['tickets']['next'] ?? null;
            if ($earned < 1) {
                $msg = "생타 100타부터 티켓이 생겨요. (현재 {$raw}타)";
            } elseif ($next) {
                $add = (($next['mode'] ?? '') === 'add') ? '+' : '';
                $msg = "오늘 티켓을 모두 썼어요. ({$used}/{$earned})\n다음: 생타 {$next['tasu']}타 → 티켓 {$add}{$next['tickets']}장";
            } else {
                $msg = "오늘 티켓을 모두 썼어요. ({$used}/{$earned}) · 자정에 초기화";
            }
            return [
                'ok' => false,
                'data' => $msg,
                'state' => $state,
            ];
        }

        $pick = attendance_roulette_resolve_reward(attendance_roulette_pick());
        $day = attendance_roulette_today();
        $esc = addslashes($nick);
        $key = addslashes((string)$pick['key']);
        $label = addslashes((string)$pick['label']);
        $type = addslashes((string)$pick['type']);
        $qty = (int)$pick['qty'];
        $tbl = ATTENDANCE_ROULETTE_TABLE;

        $rs = db_query("
            INSERT INTO `{$tbl}`
              (nick, spin_date, reward_key, reward_label, reward_type, reward_qty, granted)
            VALUES
              ('{$esc}', '{$day}', '{$key}', '{$label}', '{$type}', {$qty}, 0)
        ");
        global $conn;
        if (!$rs) {
            $err = ($conn instanceof mysqli) ? mysqli_error($conn) : '';
            return [
                'ok' => false,
                'data' => '스핀 저장에 실패했어요.' . ($err !== '' ? " ({$err})" : ''),
                'state' => attendance_roulette_state($nick),
            ];
        }

        $insert_id = ($conn instanceof mysqli) ? (int)mysqli_insert_id($conn) : 0;
        if ($insert_id < 1) {
            return [
                'ok' => false,
                'data' => '스핀 저장에 실패했어요. (티켓 차감 기록 없음)',
                'state' => attendance_roulette_state($nick),
            ];
        }

        $grant = attendance_roulette_grant_reward($nick, $pick);
        // 룰렛 보상은 무조건 지급 처리 (실패 분기 없음)
        $granted = 1;
        if ($insert_id > 0) {
            @db_query("UPDATE `{$tbl}` SET granted = 1 WHERE idx = {$insert_id} LIMIT 1");
        }
        $log_status = !ATTENDANCE_ROULETTE_GRANT_ENABLED ? 'skipped' : 'granted';
        attendance_roulette_grant_log(
            $nick,
            $insert_id,
            $pick,
            $log_status,
            (string)($grant['before'] ?? ''),
            (string)($grant['after'] ?? ''),
            (string)($grant['data'] ?? '')
        );

        $state = attendance_roulette_state($nick);
        $left_after = (int)($state['tickets']['left'] ?? 0);
        $msg = '🎰 ' . $pick['label'] . ' !';
        if (($pick['type'] ?? '') === 'miss') {
            $msg = '💥 꽝! 다음 기회에…';
        } else {
            $msg .= "\n" . (string)($grant['data'] ?? '지급 완료');
        }
        $msg .= "\n남은 티켓 {$left_after}장";

        return [
            'ok' => true,
            'data' => $msg,
            'pick' => $pick,
            'granted' => $granted,
            'grant_msg' => (string)($grant['data'] ?? ''),
            'state' => $state,
        ];
    }
}
