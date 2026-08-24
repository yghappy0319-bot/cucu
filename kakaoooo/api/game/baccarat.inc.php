<?php
/**
 * 냥카라 — 단일 테이블 · 최대 8명 · 게임냥
 * 페이즈: lobby → betting → result → (반복)
 */

if (!defined('BACCARAT_MAX_SEATS')) {
    define('BACCARAT_MAX_SEATS', 8);
    define('BACCARAT_BET_SEC', 12);
    define('BACCARAT_RESULT_SEC', 14);
    define('BACCARAT_LOBBY_SEC', 6);
    define('BACCARAT_IDLE_SEC', 25);
    define('BACCARAT_MIN_BET', 1000);
    define('BACCARAT_TIE_ODDS', 8);
    define('BACCARAT_TIP_DIV', 20); // 딴 금액의 5% (기본)
    define('BACCARAT_X2_PCT', 5);
    define('BACCARAT_ADMIN_NICK', '민호');
    define('BACCARAT_RAKE_PCT', 5);
    define('BACCARAT_RAKE_OFFWORK_PCT', 10);
    /** 플레이어·뱅커 양쪽 배팅 시, 배팅 합이 더 큰 쪽이 이길 확률(%) */
    define('BACCARAT_DUAL_SIDE_WIN_PCT', 59);
}

if (!function_exists('baccarat_schema_ensure')) {
    function baccarat_schema_ensure(): void {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        @db_query("CREATE TABLE IF NOT EXISTS tb_baccarat_table (
            idx TINYINT UNSIGNED NOT NULL DEFAULT 1,
            phase VARCHAR(16) NOT NULL DEFAULT 'lobby',
            round_no INT UNSIGNED NOT NULL DEFAULT 0,
            phase_until DATETIME NULL DEFAULT NULL,
            player_cards VARCHAR(64) NOT NULL DEFAULT '[]',
            banker_cards VARCHAR(64) NOT NULL DEFAULT '[]',
            winner VARCHAR(8) NOT NULL DEFAULT '',
            last_result VARCHAR(255) NOT NULL DEFAULT '',
            last_tip VARCHAR(400) NOT NULL DEFAULT '',
            last_mvp_nick VARCHAR(32) NOT NULL DEFAULT '',
            last_mvp_delta VARCHAR(40) NOT NULL DEFAULT '0',
            reacts TEXT NULL,
            x2_side VARCHAR(8) NOT NULL DEFAULT '',
            x2_force TINYINT UNSIGNED NOT NULL DEFAULT 0,
            road TEXT NOT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (idx)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        @db_query("CREATE TABLE IF NOT EXISTS tb_baccarat_seat (
            nick VARCHAR(32) NOT NULL,
            seat TINYINT UNSIGNED NOT NULL DEFAULT 1,
            bet_round INT UNSIGNED NOT NULL DEFAULT 0,
            bet_side VARCHAR(8) NOT NULL DEFAULT '',
            bet_amount VARCHAR(40) NOT NULL DEFAULT '0',
            bet_p VARCHAR(40) NOT NULL DEFAULT '0',
            bet_b VARCHAR(40) NOT NULL DEFAULT '0',
            bet_t VARCHAR(40) NOT NULL DEFAULT '0',
            last_delta VARCHAR(40) NOT NULL DEFAULT '0',
            last_msg VARCHAR(320) NOT NULL DEFAULT '',
            tip_round INT UNSIGNED NOT NULL DEFAULT 0,
            online_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (nick),
            UNIQUE KEY uq_seat (seat)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $tipCol = @db_select("SHOW COLUMNS FROM tb_baccarat_seat LIKE 'tip_round'");
        if (empty($tipCol)) {
            @db_query("ALTER TABLE tb_baccarat_seat ADD COLUMN tip_round INT UNSIGNED NOT NULL DEFAULT 0");
        }
        $tipFlash = @db_select("SHOW COLUMNS FROM tb_baccarat_table LIKE 'last_tip'");
        if (empty($tipFlash)) {
            @db_query("ALTER TABLE tb_baccarat_table ADD COLUMN last_tip VARCHAR(400) NOT NULL DEFAULT ''");
        }
        $mvpNickCol = @db_select("SHOW COLUMNS FROM tb_baccarat_table LIKE 'last_mvp_nick'");
        if (empty($mvpNickCol)) {
            @db_query("ALTER TABLE tb_baccarat_table ADD COLUMN last_mvp_nick VARCHAR(32) NOT NULL DEFAULT ''");
        }
        $mvpDeltaCol = @db_select("SHOW COLUMNS FROM tb_baccarat_table LIKE 'last_mvp_delta'");
        if (empty($mvpDeltaCol)) {
            @db_query("ALTER TABLE tb_baccarat_table ADD COLUMN last_mvp_delta VARCHAR(40) NOT NULL DEFAULT '0'");
        }
        $reactCol = @db_select("SHOW COLUMNS FROM tb_baccarat_table LIKE 'reacts'");
        if (empty($reactCol)) {
            @db_query("ALTER TABLE tb_baccarat_table ADD COLUMN reacts TEXT NULL");
        }
        $x2SideCol = @db_select("SHOW COLUMNS FROM tb_baccarat_table LIKE 'x2_side'");
        if (empty($x2SideCol)) {
            @db_query("ALTER TABLE tb_baccarat_table ADD COLUMN x2_side VARCHAR(8) NOT NULL DEFAULT ''");
        }
        $x2ForceCol = @db_select("SHOW COLUMNS FROM tb_baccarat_table LIKE 'x2_force'");
        if (empty($x2ForceCol)) {
            @db_query("ALTER TABLE tb_baccarat_table ADD COLUMN x2_force TINYINT UNSIGNED NOT NULL DEFAULT 0");
        }
        foreach (['bet_p', 'bet_b', 'bet_t'] as $betCol) {
            $betColRow = @db_select("SHOW COLUMNS FROM tb_baccarat_seat LIKE '{$betCol}'");
            if (empty($betColRow)) {
                @db_query("ALTER TABLE tb_baccarat_seat ADD COLUMN {$betCol} VARCHAR(40) NOT NULL DEFAULT '0'");
            }
        }
        $msgCol = @db_select("SHOW COLUMNS FROM tb_baccarat_seat LIKE 'last_msg'");
        $msgType = strtolower((string)($msgCol['Type'] ?? ''));
        if ($msgType !== '' && strpos($msgType, 'varchar(320)') === false) {
            @db_query("ALTER TABLE tb_baccarat_seat MODIFY last_msg VARCHAR(320) NOT NULL DEFAULT ''");
        }
        $row = @db_select("SELECT idx FROM tb_baccarat_table WHERE idx = 1 LIMIT 1");
        if (empty($row['idx'])) {
            @db_query("INSERT INTO tb_baccarat_table (idx, phase, round_no, phase_until, player_cards, banker_cards, winner, last_result, road)
                VALUES (1, 'lobby', 0, NULL, '[]', '[]', '', '', '[]')");
        }
        $maxSeats = (int)BACCARAT_MAX_SEATS;
        @db_query("DELETE FROM tb_baccarat_seat WHERE seat > {$maxSeats}");
    }
}

if (!function_exists('baccarat_lock')) {
    function baccarat_lock(int $wait = 5): bool {
        $row = @db_select("SELECT GET_LOCK('baccarat_table', {$wait}) AS ok");
        return isset($row['ok']) && (int)$row['ok'] === 1;
    }
}

if (!function_exists('baccarat_unlock')) {
    function baccarat_unlock(): void {
        @db_select("SELECT RELEASE_LOCK('baccarat_table') AS ok");
    }
}

if (!function_exists('baccarat_int')) {
    function baccarat_int($v): string {
        if (function_exists('냥_정수문자열')) {
            return 냥_정수문자열($v);
        }
        $s = preg_replace('/[^\d]/', '', (string)$v);
        return ltrim((string)$s, '0') ?: '0';
    }
}

if (!function_exists('baccarat_round_nice')) {
    function baccarat_round_nice(string $amt): string {
        $amt = baccarat_int($amt);
        $len = strlen($amt);
        if ($len <= 2) {
            return $amt;
        }
        return substr($amt, 0, 2) . str_repeat('0', $len - 2);
    }
}

if (!function_exists('baccarat_preset_label')) {
    function baccarat_preset_label(string $amt): string {
        if (function_exists('wallet_fmt_game_compact')) {
            return (string)wallet_fmt_game_compact($amt);
        }
        return baccarat_fmt($amt);
    }
}

if (!function_exists('baccarat_preset_bets')) {
    /** @return list<array{amt:string,label:string}> 전체 게임냥 0.001% · 0.01% · 0.1% */
    function baccarat_preset_bets(): array {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }
        $min = (string)BACCARAT_MIN_BET;
        $pcts = [0.001, 0.01, 0.1];
        $out = [];
        $seen = [];
        foreach ($pcts as $pct) {
            $raw = function_exists('전체냥기준금액')
                ? baccarat_int(전체냥기준금액($pct, true))
                : $min;
            if (baccarat_cmp($raw, $min) < 0) {
                $raw = $min;
            }
            $raw = baccarat_round_nice($raw);
            if (isset($seen[$raw])) {
                continue;
            }
            $seen[$raw] = true;
            $out[] = [
                'amt' => $raw,
                'label' => baccarat_preset_label($raw),
            ];
        }
        while (count($out) < 3) {
            $last = $out !== [] ? $out[count($out) - 1]['amt'] : $min;
            $next = baccarat_mul($last, 10);
            if (isset($seen[$next])) {
                break;
            }
            $seen[$next] = true;
            $out[] = [
                'amt' => $next,
                'label' => baccarat_preset_label($next),
            ];
        }
        $cache = $out;
        return $cache;
    }
}

if (!function_exists('baccarat_fmt')) {
    function baccarat_fmt($n): string {
        if (function_exists('wallet_fmt_game')) {
            return (string)wallet_fmt_game($n);
        }
        if (function_exists('게임냥_안전표시')) {
            return (string)게임냥_안전표시($n, '');
        }
        return number_format((float)$n);
    }
}

if (!function_exists('baccarat_cmp')) {
    function baccarat_cmp(string $a, string $b): int {
        if (function_exists('bccomp')) {
            return (int)bccomp($a, $b, 0);
        }
        if (strlen($a) !== strlen($b)) {
            return strlen($a) < strlen($b) ? -1 : 1;
        }
        return $a === $b ? 0 : ($a < $b ? -1 : 1);
    }
}

if (!function_exists('baccarat_add')) {
    function baccarat_add(string $a, string $b): string {
        $a = baccarat_int($a);
        $b = baccarat_int($b);
        if (function_exists('bcadd')) {
            return baccarat_int(bcadd($a, $b, 0));
        }
        return baccarat_int((string)((int)$a + (int)$b));
    }
}

if (!function_exists('baccarat_sub')) {
    function baccarat_sub(string $a, string $b): string {
        $a = baccarat_int($a);
        $b = baccarat_int($b);
        if (baccarat_cmp($a, $b) <= 0) {
            return '0';
        }
        if (function_exists('bcsub')) {
            return baccarat_int(bcsub($a, $b, 0));
        }
        return baccarat_int((string)((int)$a - (int)$b));
    }
}

if (!function_exists('baccarat_pct_of')) {
    function baccarat_pct_of(string $amt, int $pct): string {
        $amt = baccarat_int($amt);
        if ($amt === '0' || $pct <= 0) {
            return '0';
        }
        if (function_exists('bcmul') && function_exists('bcdiv')) {
            return baccarat_int(bcdiv(bcmul($amt, (string)$pct, 0), '100', 0));
        }
        return baccarat_div_floor(baccarat_mul($amt, $pct), 100);
    }
}

if (!function_exists('baccarat_is_offwork')) {
    function baccarat_is_offwork(string $nick): bool {
        $nick = trim($nick);
        if ($nick === '') {
            return false;
        }
        if (function_exists('wallet_nick_is_퇴근')) {
            return (bool)wallet_nick_is_퇴근($nick);
        }
        $esc = addslashes($nick);
        $work = @db_select("SELECT idx FROM tb_work WHERE nick = '{$esc}' LIMIT 1");
        if (!empty($work['idx'])) {
            return true;
        }
        $mem = @db_select("SELECT status FROM tb_member WHERE name = '{$esc}' LIMIT 1");
        return isset($mem['status']) && (int)$mem['status'] === 3;
    }
}

if (!function_exists('baccarat_rake_win')) {
    /** @return array{net:string,vault:string,lotto:string,pct:int} */
    function baccarat_rake_win(string $nick, string $net): array {
        $net = baccarat_int($net);
        $pct = baccarat_is_offwork($nick)
            ? (defined('BACCARAT_RAKE_OFFWORK_PCT') ? (int)BACCARAT_RAKE_OFFWORK_PCT : 10)
            : (defined('BACCARAT_RAKE_PCT') ? (int)BACCARAT_RAKE_PCT : 5);
        if ($net === '0' || $pct <= 0) {
            return ['net' => $net, 'vault' => '0', 'lotto' => '0', 'pct' => $pct];
        }
        $vault = baccarat_pct_of($net, $pct);
        $lotto = baccarat_pct_of($net, $pct);
        $tax = baccarat_add($vault, $lotto);
        if (baccarat_cmp($tax, $net) > 0) {
            $vault = baccarat_pct_of($net, $pct);
            $lotto = baccarat_sub($net, $vault);
            $tax = $net;
        }
        $after = baccarat_sub($net, $tax);
        if ($vault !== '0') {
            $sql = function_exists('냥_SQL정수') ? 냥_SQL정수($vault) : preg_replace('/\D/', '', $vault);
            $sql = ltrim((string)$sql, '0') ?: '0';
            if ($sql !== '0') {
                db_query("UPDATE config SET tax = tax + {$sql}");
            }
        }
        if ($lotto !== '0') {
            $inc = __DIR__ . '/lotto_amount.inc.php';
            if (is_file($inc)) {
                require_once $inc;
            }
            if (function_exists('로또누적_가산')) {
                로또누적_가산($lotto);
            }
        }
        if (function_exists('지급로그') && $tax !== '0') {
            지급로그('냥카라-수수료', $nick, '금고로또', $tax, $after);
        }
        return ['net' => $after, 'vault' => $vault, 'lotto' => $lotto, 'pct' => $pct];
    }
}

if (!function_exists('baccarat_mul')) {
    function baccarat_mul(string $a, int $n): string {
        $a = baccarat_int($a);
        if (function_exists('bcmul')) {
            return baccarat_int(bcmul($a, (string)$n, 0));
        }
        return baccarat_int((string)((int)$a * $n));
    }
}

if (!function_exists('baccarat_div_floor')) {
    function baccarat_div_floor(string $a, int $n): string {
        $a = baccarat_int($a);
        if ($n <= 0 || $a === '0') {
            return '0';
        }
        if (function_exists('냥_문자열나눗셈내림')) {
            return 냥_문자열나눗셈내림($a, (string)$n);
        }
        if (function_exists('bcdiv')) {
            return baccarat_int(bcdiv($a, (string)$n, 0));
        }
        return baccarat_int((string)intdiv((int)$a, $n));
    }
}

if (!function_exists('baccarat_point_raw')) {
    function baccarat_point_raw(string $nick): string {
        $esc = addslashes($nick);
        $row = @db_select("SELECT CAST(CAST(IFNULL(point, 0) AS DECIMAL(65,0)) AS CHAR) AS point FROM tb_member WHERE name = '{$esc}' LIMIT 1");
        return trim((string)($row['point'] ?? '0'));
    }
}

if (!function_exists('baccarat_points_map')) {
    /** @param list<string> $nicks @return array<string,string> */
    function baccarat_points_map(array $nicks): array {
        $clean = [];
        foreach ($nicks as $n) {
            $n = trim((string)$n);
            if ($n !== '') {
                $clean[$n] = true;
            }
        }
        if ($clean === []) {
            return [];
        }
        $in = [];
        foreach (array_keys($clean) as $n) {
            $in[] = "'" . addslashes($n) . "'";
        }
        $rs = @db_query("SELECT name, CAST(CAST(IFNULL(point, 0) AS DECIMAL(65,0)) AS CHAR) AS point
            FROM tb_member WHERE name IN (" . implode(',', $in) . ")");
        $out = [];
        if ($rs) {
            while ($row = db_fetch($rs)) {
                $out[(string)($row['name'] ?? '')] = trim((string)($row['point'] ?? '0'));
            }
        }
        return $out;
    }
}

if (!function_exists('baccarat_can_afford')) {
    function baccarat_can_afford(string $raw, string $need): bool {
        $raw = trim($raw);
        if ($raw === '' || $raw[0] === '-') {
            return false;
        }
        return baccarat_cmp(baccarat_int($raw), baccarat_int($need)) >= 0;
    }
}

if (!function_exists('baccarat_debit')) {
    function baccarat_debit(string $nick, string $amount): bool {
        $amount = baccarat_int($amount);
        if ($amount === '0') {
            return false;
        }
        $before = baccarat_int(baccarat_point_raw($nick));
        if (!baccarat_can_afford($before, $amount)) {
            return false;
        }
        $esc = addslashes($nick);
        $sql = function_exists('냥_SQL정수') ? 냥_SQL정수($amount) : $amount;
        if (!preg_match('/^\d+$/', (string)$sql)) {
            return false;
        }
        $ok = db_query("UPDATE tb_member
            SET point = point - CAST('{$sql}' AS DECIMAL(65,0))
            WHERE name = '{$esc}'
              AND CAST(IFNULL(point, 0) AS DECIMAL(65,0)) >= CAST('{$sql}' AS DECIMAL(65,0))
            LIMIT 1");
        if ($ok === false) {
            return false;
        }
        $after = baccarat_int(baccarat_point_raw($nick));
        if (baccarat_cmp($after, $before) >= 0) {
            return false;
        }
        return true;
    }
}

if (!function_exists('baccarat_debit_force')) {
    /** 잔액 부족해도 차감 (마이너스·신불 허용) */
    function baccarat_debit_force(string $nick, string $amount): bool {
        $amount = baccarat_int($amount);
        if ($amount === '0') {
            return false;
        }
        $esc = addslashes($nick);
        $sql = function_exists('냥_SQL정수') ? 냥_SQL정수($amount) : $amount;
        if (!preg_match('/^\d+$/', (string)$sql)) {
            return false;
        }
        $ok = db_query("UPDATE tb_member
            SET point = CAST(IFNULL(point, 0) AS DECIMAL(65,0)) - CAST('{$sql}' AS DECIMAL(65,0))
            WHERE name = '{$esc}'
            LIMIT 1");
        return $ok !== false;
    }
}

if (!function_exists('baccarat_apply_신불')) {
    function baccarat_apply_신불(string $nick): bool {
        $nick = trim($nick);
        if ($nick === '') {
            return false;
        }
        $esc = addslashes($nick);
        $row = @db_select("SELECT idx, CAST(IFNULL(point, 0) AS CHAR) AS point, IFNULL(title, '') AS title
            FROM tb_member WHERE name = '{$esc}' LIMIT 1");
        if (empty($row['idx'])) {
            return false;
        }
        $raw = trim((string)($row['point'] ?? '0'));
        if ($raw === '' || $raw[0] !== '-') {
            return false;
        }
        $midx = (int)$row['idx'];
        $title = trim((string)($row['title'] ?? ''));
        $first = ($title !== '🆘신불자');
        if ($first) {
            db_query("UPDATE tb_member
                SET title = '🆘신불자',
                    credit = 1,
                    credit_debt_at = NOW(),
                    credit_debt_entered_at = NOW(),
                    credit_recovery_plus3_at = NULL,
                    credit_debt_times = IFNULL(credit_debt_times, 0) + 1
                WHERE idx = {$midx}
                LIMIT 1");
            $abs = ltrim(preg_replace('/[^\d]/', '', $raw), '0') ?: '0';
            $표시 = function_exists('냥축약표시') ? 냥축약표시($abs, '냥') : (baccarat_fmt($abs) . '냥');
            $logMsg = "🆘 {$nick} 신불자 진입 (냥카라)\n현재 마이너스 {$표시}";
            if (function_exists('info2알림_등록')) {
                info2알림_등록($logMsg, $nick);
            }
        } else {
            db_query("UPDATE tb_member SET credit = 1 WHERE idx = {$midx} LIMIT 1");
        }
        return true;
    }
}

if (!function_exists('baccarat_credit')) {
    function baccarat_credit(string $nick, string $amount): void {
        $amount = baccarat_int($amount);
        if ($amount === '0') {
            return;
        }
        $esc = addslashes($nick);
        $sql = function_exists('냥_SQL정수') ? 냥_SQL정수($amount) : $amount;
        db_query("UPDATE tb_member SET point = point + {$sql} WHERE name = '{$esc}' LIMIT 1");
    }
}

if (!function_exists('baccarat_table')) {
    function baccarat_table(): array {
        baccarat_schema_ensure();
        $row = @db_select("SELECT * FROM tb_baccarat_table WHERE idx = 1 LIMIT 1");
        return is_array($row) ? $row : [
            'idx' => 1,
            'phase' => 'lobby',
            'round_no' => 0,
            'phase_until' => null,
            'player_cards' => '[]',
            'banker_cards' => '[]',
            'winner' => '',
            'last_result' => '',
            'last_tip' => '',
            'last_mvp_nick' => '',
            'last_mvp_delta' => '0',
            'reacts' => '[]',
            'x2_side' => '',
            'x2_force' => 0,
            'road' => '[]',
        ];
    }
}

if (!function_exists('baccarat_seats')) {
    /** @return list<array> */
    function baccarat_seats(): array {
        baccarat_schema_ensure();
        $rs = @db_query("SELECT * FROM tb_baccarat_seat ORDER BY seat ASC");
        $out = [];
        if ($rs) {
            while ($row = db_fetch($rs)) {
                $out[] = $row;
            }
        }
        return $out;
    }
}

if (!function_exists('baccarat_set_phase')) {
    function baccarat_set_phase(string $phase, int $sec, array $extra = []): void {
        $phase_esc = addslashes($phase);
        $untilSql = $sec > 0 ? ("'" . addslashes(date('Y-m-d H:i:s', time() + $sec)) . "'") : 'NULL';
        $sets = "phase = '{$phase_esc}', phase_until = {$untilSql}";
        foreach ($extra as $k => $v) {
            $k = preg_replace('/[^a-z_]/', '', (string)$k);
            if ($k === '') {
                continue;
            }
            $sets .= ", {$k} = '" . addslashes((string)$v) . "'";
        }
        db_query("UPDATE tb_baccarat_table SET {$sets} WHERE idx = 1 LIMIT 1");
    }
}

if (!function_exists('baccarat_kick_idle')) {
    function baccarat_kick_idle(): void {
        $cut = addslashes(date('Y-m-d H:i:s', time() - BACCARAT_IDLE_SEC));
        $t = baccarat_table();
        $phase = (string)($t['phase'] ?? 'lobby');
        $round = (int)($t['round_no'] ?? 0);
        if ($phase === 'betting' || $phase === 'result') {
            db_query("DELETE FROM tb_baccarat_seat
                WHERE online_at < '{$cut}'
                  AND (bet_round <> {$round}
                    OR (IFNULL(bet_p,'0') = '0' AND IFNULL(bet_b,'0') = '0' AND IFNULL(bet_t,'0') = '0'
                        AND (bet_side = '' OR bet_amount = '0')))");
        } else {
            db_query("DELETE FROM tb_baccarat_seat WHERE online_at < '{$cut}'");
        }
    }
}

if (!function_exists('baccarat_touch')) {
    function baccarat_touch(string $nick): void {
        $esc = addslashes($nick);
        db_query("UPDATE tb_baccarat_seat SET online_at = NOW() WHERE nick = '{$esc}' LIMIT 1");
    }
}

if (!function_exists('baccarat_card_value')) {
    function baccarat_card_value(int $id): int {
        $rank = $id % 13;
        if ($rank === 0) {
            return 1;
        }
        if ($rank >= 9) {
            return 0;
        }
        return $rank + 1;
    }
}

if (!function_exists('baccarat_card_view')) {
    function baccarat_card_view(int $id): array {
        $suits = ['♠', '♥', '♦', '♣'];
        $ranks = ['A', '2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K'];
        $suit = intdiv($id, 13) % 4;
        $rank = $id % 13;
        return [
            'id' => $id,
            'rank' => $ranks[$rank],
            'suit' => $suits[$suit],
            'red' => ($suit === 1 || $suit === 2),
            'val' => baccarat_card_value($id),
        ];
    }
}

if (!function_exists('baccarat_total')) {
    /** @param list<int> $cards */
    function baccarat_total(array $cards): int {
        $s = 0;
        foreach ($cards as $c) {
            $s += baccarat_card_value((int)$c);
        }
        return $s % 10;
    }
}

if (!function_exists('baccarat_decode_cards')) {
    /** @return list<int> */
    function baccarat_decode_cards($json): array {
        $arr = json_decode((string)$json, true);
        if (!is_array($arr)) {
            return [];
        }
        $out = [];
        foreach ($arr as $v) {
            $out[] = (int)$v;
        }
        return $out;
    }
}

if (!function_exists('baccarat_shuffle_deck')) {
    /** @return list<int> */
    function baccarat_shuffle_deck(): array {
        $deck = range(0, 51);
        for ($i = count($deck) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            $tmp = $deck[$i];
            $deck[$i] = $deck[$j];
            $deck[$j] = $tmp;
        }
        return $deck;
    }
}

if (!function_exists('baccarat_banker_draws')) {
    function baccarat_banker_draws(int $bankerTotal, $playerThird): bool {
        if ($playerThird === null) {
            return $bankerTotal <= 5;
        }
        $p3 = (int)$playerThird;
        if ($bankerTotal <= 2) {
            return true;
        }
        if ($bankerTotal === 3) {
            return $p3 !== 8;
        }
        if ($bankerTotal === 4) {
            return $p3 >= 2 && $p3 <= 7;
        }
        if ($bankerTotal === 5) {
            return $p3 >= 4 && $p3 <= 7;
        }
        if ($bankerTotal === 6) {
            return $p3 === 6 || $p3 === 7;
        }
        return false;
    }
}

if (!function_exists('baccarat_deal_hands')) {
    /** @return array{player:list<int>,banker:list<int>} */
    function baccarat_deal_hands(): array {
        $deck = baccarat_shuffle_deck();
        $i = 0;
        $player = [(int)$deck[$i++], (int)$deck[$i++]];
        $banker = [(int)$deck[$i++], (int)$deck[$i++]];
        $pt = baccarat_total($player);
        $bt = baccarat_total($banker);
        if ($pt >= 8 || $bt >= 8) {
            return ['player' => $player, 'banker' => $banker];
        }
        $p3 = null;
        if ($pt <= 5) {
            $player[] = (int)$deck[$i++];
            $p3 = baccarat_card_value($player[2]);
        }
        if (baccarat_banker_draws(baccarat_total($banker), $p3)) {
            $banker[] = (int)$deck[$i++];
        }
        return ['player' => $player, 'banker' => $banker];
    }
}

if (!function_exists('baccarat_hands_winner')) {
    /** @param list<int> $player @param list<int> $banker */
    function baccarat_hands_winner(array $player, array $banker): string {
        $pt = baccarat_total($player);
        $bt = baccarat_total($banker);
        if ($pt > $bt) {
            return 'P';
        }
        if ($bt > $pt) {
            return 'B';
        }
        return 'T';
    }
}

if (!function_exists('baccarat_force_hands')) {
    /** 재시도 실패 시 점수만 맞춘 패 */
    function baccarat_force_hands(string $want): array {
        $want = strtoupper($want);
        if ($want === 'B') {
            return ['player' => [5, 9], 'banker' => [6, 9]];
        }
        if ($want === 'T') {
            return ['player' => [6, 9], 'banker' => [19, 35]];
        }
        return ['player' => [6, 9], 'banker' => [5, 9]];
    }
}

if (!function_exists('baccarat_deal_until_winner')) {
    function baccarat_deal_until_winner(string $want): array {
        $want = strtoupper($want);
        if (!in_array($want, ['P', 'B', 'T'], true)) {
            return baccarat_deal_hands();
        }
        for ($n = 0; $n < 80; $n++) {
            $hands = baccarat_deal_hands();
            if (baccarat_hands_winner($hands['player'], $hands['banker']) === $want) {
                return $hands;
            }
        }
        return baccarat_force_hands($want);
    }
}

if (!function_exists('baccarat_seat_bets')) {
    /**
     * @return array{P:string,B:string,T:string}
     */
    function baccarat_seat_bets(array $seat, int $round): array {
        $out = ['P' => '0', 'B' => '0', 'T' => '0'];
        if ((int)($seat['bet_round'] ?? 0) !== $round) {
            return $out;
        }
        $out['P'] = baccarat_int($seat['bet_p'] ?? '0');
        $out['B'] = baccarat_int($seat['bet_b'] ?? '0');
        $out['T'] = baccarat_int($seat['bet_t'] ?? '0');
        $legacySide = strtoupper(trim((string)($seat['bet_side'] ?? '')));
        $legacyAmt = baccarat_int($seat['bet_amount'] ?? '0');
        if ($legacyAmt !== '0' && isset($out[$legacySide]) && $out[$legacySide] === '0') {
            $out[$legacySide] = $legacyAmt;
        }
        return $out;
    }
}

if (!function_exists('baccarat_seat_has_bet')) {
    function baccarat_seat_has_bet(array $seat, int $round): bool {
        $b = baccarat_seat_bets($seat, $round);
        return $b['P'] !== '0' || $b['B'] !== '0' || $b['T'] !== '0';
    }
}

if (!function_exists('baccarat_bets_total')) {
    function baccarat_bets_total(array $bets): string {
        return baccarat_add(baccarat_add($bets['P'] ?? '0', $bets['B'] ?? '0'), $bets['T'] ?? '0');
    }
}

if (!function_exists('baccarat_bets_label')) {
    function baccarat_bets_label(array $bets): string {
        $parts = [];
        if (($bets['P'] ?? '0') !== '0') {
            $parts[] = '플 ' . baccarat_fmt($bets['P']);
        }
        if (($bets['B'] ?? '0') !== '0') {
            $parts[] = '뱅 ' . baccarat_fmt($bets['B']);
        }
        if (($bets['T'] ?? '0') !== '0') {
            $parts[] = '타이 ' . baccarat_fmt($bets['T']);
        }
        return implode(' · ', $parts);
    }
}

if (!function_exists('baccarat_bets_primary_side')) {
    function baccarat_bets_primary_side(array $bets): string {
        $on = [];
        foreach (['P', 'B', 'T'] as $s) {
            if (($bets[$s] ?? '0') !== '0') {
                $on[] = $s;
            }
        }
        if ($on === []) {
            return '';
        }
        return count($on) === 1 ? $on[0] : implode('', $on);
    }
}

if (!function_exists('baccarat_round_side_bets')) {
    /**
     * @param list<array> $seats
     * @return array{P:bool,B:bool,T:bool}
     */
    function baccarat_round_side_bets(array $seats, int $round): array {
        $out = ['P' => false, 'B' => false, 'T' => false];
        foreach ($seats as $seat) {
            $bets = baccarat_seat_bets($seat, $round);
            foreach (['P', 'B', 'T'] as $side) {
                if (($bets[$side] ?? '0') !== '0') {
                    $out[$side] = true;
                }
            }
        }
        return $out;
    }
}

if (!function_exists('baccarat_round_side_totals')) {
    /**
     * @param list<array> $seats
     * @return array{P:string,B:string,T:string}
     */
    function baccarat_round_side_totals(array $seats, int $round): array {
        $out = ['P' => '0', 'B' => '0', 'T' => '0'];
        foreach ($seats as $seat) {
            $bets = baccarat_seat_bets($seat, $round);
            foreach (['P', 'B', 'T'] as $side) {
                if (($bets[$side] ?? '0') !== '0') {
                    $out[$side] = baccarat_add($out[$side], $bets[$side]);
                }
            }
        }
        return $out;
    }
}

if (!function_exists('baccarat_dual_want_winner')) {
    /** 플레이어·뱅커 양쪽 배팅이면 금액 많은 쪽 59%(동액이면 50:50). 아니면 null */
    function baccarat_dual_want_winner(array $seats, int $round): ?string {
        $on = baccarat_round_side_bets($seats, $round);
        if (empty($on['P']) || empty($on['B'])) {
            return null;
        }
        $tot = baccarat_round_side_totals($seats, $round);
        $cmp = baccarat_cmp($tot['P'], $tot['B']);
        if ($cmp === 0) {
            return random_int(0, 1) === 1 ? 'P' : 'B';
        }
        $fav = $cmp > 0 ? 'P' : 'B';
        $other = $fav === 'P' ? 'B' : 'P';
        $pct = defined('BACCARAT_DUAL_SIDE_WIN_PCT') ? (int)BACCARAT_DUAL_SIDE_WIN_PCT : 59;
        if ($pct < 1) {
            $pct = 1;
        } elseif ($pct > 99) {
            $pct = 99;
        }
        return random_int(1, 100) <= $pct ? $fav : $other;
    }
}

if (!function_exists('baccarat_side_label')) {
    function baccarat_side_label(string $side): string {
        if ($side === 'P') {
            return '플레이어';
        }
        if ($side === 'B') {
            return '뱅커';
        }
        if ($side === 'T') {
            return '타이';
        }
        if ($side === 'PB') {
            return '양쪽 적중';
        }
        if ($side === 'N') {
            return '양쪽 미적중';
        }
        return $side;
    }
}

if (!function_exists('baccarat_is_minho')) {
    function baccarat_is_minho(string $nick): bool {
        $admin = defined('BACCARAT_ADMIN_NICK') ? BACCARAT_ADMIN_NICK : '민호';
        return trim($nick) === $admin;
    }
}

if (!function_exists('baccarat_x2_roll')) {
    function baccarat_x2_roll(bool $force = false): string {
        if (!$force) {
            $pct = defined('BACCARAT_X2_PCT') ? (int)BACCARAT_X2_PCT : 5;
            if ($pct < 1 || random_int(1, 100) > $pct) {
                return '';
            }
        }
        $sides = ['P', 'B', 'T'];
        return $sides[random_int(0, 2)];
    }
}

if (!function_exists('baccarat_x2_payload')) {
    function baccarat_x2_payload(array $t): ?array {
        $side = strtoupper(trim((string)($t['x2_side'] ?? '')));
        if (!in_array($side, ['P', 'B', 'T'], true)) {
            return null;
        }
        $net = ($side === 'T') ? (BACCARAT_TIE_ODDS * 2) : 2;
        return [
            'side' => $side,
            'label' => baccarat_side_label($side),
            'net' => $net,
            'text' => baccarat_side_label($side) . ' X2배',
        ];
    }
}

if (!function_exists('baccarat_start_betting')) {
    function baccarat_start_betting(): void {
        $t = baccarat_table();
        $round = (int)($t['round_no'] ?? 0) + 1;
        $force = (int)($t['x2_force'] ?? 0) === 1;
        $x2 = baccarat_x2_roll($force);
        $x2_esc = addslashes($x2);
        db_query("UPDATE tb_baccarat_table
            SET phase = 'betting',
                round_no = {$round},
                phase_until = '" . addslashes(date('Y-m-d H:i:s', time() + BACCARAT_BET_SEC)) . "',
                player_cards = '[]',
                banker_cards = '[]',
                winner = '',
                last_result = '',
                last_tip = '',
                x2_side = '{$x2_esc}',
                x2_force = 0
            WHERE idx = 1 LIMIT 1");
        db_query("UPDATE tb_baccarat_seat
            SET bet_round = 0, bet_side = '', bet_amount = '0', bet_p = '0', bet_b = '0', bet_t = '0', last_delta = '0', last_msg = '', tip_round = 0");
    }
}

if (!function_exists('baccarat_settle_one_bet')) {
    /**
     * @return array{kind:string,abs:string,msg:string,신불:bool}
     */
    function baccarat_settle_one_bet(string $nick, string $side, string $amt, string $winner, string $boost): array {
        $amt = baccarat_int($amt);
        if ($winner === 'T' && ($side === 'P' || $side === 'B')) {
            baccarat_credit($nick, $amt);
            if (function_exists('지급로그')) {
                지급로그('냥카라-환급', $nick, '타이', 0, $amt);
            }
            return [
                'kind' => 'push',
                'abs' => '0',
                'msg' => baccarat_side_label($side) . ' 타이 환급 +' . baccarat_fmt($amt),
                '신불' => false,
            ];
        }
        if ($side === $winner) {
            $x2 = ($boost !== '' && $boost === $side);
            if ($winner === 'T') {
                $netN = BACCARAT_TIE_ODDS * ($x2 ? 2 : 1);
            } else {
                $netN = $x2 ? 2 : 1;
            }
            $net = baccarat_mul($amt, $netN);
            $rake = baccarat_rake_win($nick, $net);
            $net = $rake['net'];
            $pay = baccarat_add($amt, $net);
            baccarat_credit($nick, $pay);
            $msg = baccarat_side_label($side) . ($x2 ? ' X2' : '') . ' 적중 +' . baccarat_fmt($net);
            if (($rake['vault'] ?? '0') !== '0' || ($rake['lotto'] ?? '0') !== '0') {
                $msg .= ' · 수수료 ' . (int)$rake['pct'] . '%';
            }
            if (function_exists('지급로그')) {
                지급로그('냥카라-승', $nick, $side, $rake['vault'] ?? '0', $net);
            }
            return [
                'kind' => 'win',
                'abs' => $net,
                'msg' => $msg,
                '신불' => false,
            ];
        }
        $x2miss = ($boost !== '' && $boost === $side);
        $lost = $amt;
        $신불 = false;
        if ($x2miss) {
            $extra = $amt;
            $haveRaw = trim(baccarat_point_raw($nick));
            $short = ($haveRaw === '' || $haveRaw[0] === '-' || !baccarat_can_afford($haveRaw, $extra));
            if ($extra !== '0') {
                if ($short) {
                    baccarat_debit_force($nick, $extra);
                } else {
                    baccarat_debit($nick, $extra);
                }
                $lost = baccarat_mul($amt, 2);
            }
            $신불 = baccarat_apply_신불($nick);
        }
        if (function_exists('지급로그')) {
            지급로그('냥카라-패', $nick, $신불 ? '신불자' : $side, 0, $lost);
        }
        return [
            'kind' => 'lose',
            'abs' => $lost,
            'msg' => baccarat_side_label($side) . ($x2miss ? ' X2' : '') . ' 미적중 -' . baccarat_fmt($lost),
            '신불' => $신불,
        ];
    }
}

if (!function_exists('baccarat_deal_and_settle')) {
    function baccarat_deal_and_settle(): void {
        $t = baccarat_table();
        $round = (int)($t['round_no'] ?? 0);
        $seats = baccarat_seats();
        $want = baccarat_dual_want_winner($seats, $round);
        if ($want !== null) {
            $hands = baccarat_deal_until_winner($want);
            $winner = $want;
        } else {
            $hands = baccarat_deal_hands();
            $winner = baccarat_hands_winner($hands['player'], $hands['banker']);
        }
        $pCards = $hands['player'];
        $bCards = $hands['banker'];
        $pt = baccarat_total($pCards);
        $bt = baccarat_total($bCards);
        if ($winner === 'T') {
            $result = "타이  {$pt} : {$bt}";
        } else {
            $result = baccarat_side_label($winner) . " 승  {$pt} : {$bt}";
        }
        $boost = strtoupper(trim((string)($t['x2_side'] ?? '')));
        $x2hit = ($boost !== '' && $boost === $winner);
        if ($x2hit) {
            $result .= ' · X2';
        }

        $mvpNick = '';
        $mvpDelta = '0';
        $mvpSeat = 99;
        $hadBet = false;
        foreach ($seats as $seat) {
            $nick = trim((string)($seat['nick'] ?? ''));
            if ($nick === '') {
                continue;
            }
            $bets = baccarat_seat_bets($seat, $round);
            if ($bets['P'] === '0' && $bets['B'] === '0' && $bets['T'] === '0') {
                continue;
            }
            $hadBet = true;
            $esc = addslashes($nick);
            $parts = [];
            $profit = '0';
            $loss = '0';
            $신불 = false;
            foreach (['P', 'B', 'T'] as $side) {
                $amt = $bets[$side];
                if ($amt === '0') {
                    continue;
                }
                $one = baccarat_settle_one_bet($nick, $side, $amt, $winner, $boost);
                $parts[] = $one['msg'];
                if (!empty($one['신불'])) {
                    $신불 = true;
                }
                if ($one['kind'] === 'win') {
                    $profit = baccarat_add($profit, $one['abs']);
                } elseif ($one['kind'] === 'lose') {
                    $loss = baccarat_add($loss, $one['abs']);
                }
            }
            if (baccarat_cmp($profit, $loss) >= 0) {
                $delta = baccarat_sub($profit, $loss);
            } else {
                $delta = '-' . baccarat_sub($loss, $profit);
            }
            $msg = implode(' · ', $parts);
            if ($신불) {
                $msg .= ' · 🆘신불자';
            }
            $d_esc = addslashes($delta);
            $m_esc = addslashes($msg);
            db_query("UPDATE tb_baccarat_seat SET last_delta = '{$d_esc}', last_msg = '{$m_esc}' WHERE nick = '{$esc}' LIMIT 1");
            if (strpos($msg, '적중') !== false && $delta !== '' && $delta[0] !== '-') {
                $netWin = baccarat_int($delta);
                if ($netWin !== '0') {
                    $seatNo = (int)($seat['seat'] ?? 99);
                    $better = baccarat_cmp($netWin, $mvpDelta) > 0
                        || (baccarat_cmp($netWin, $mvpDelta) === 0 && $seatNo < $mvpSeat);
                    if ($better) {
                        $mvpDelta = $netWin;
                        $mvpNick = $nick;
                        $mvpSeat = $seatNo;
                    }
                }
            }
        }

        $road = json_decode((string)($t['road'] ?? '[]'), true);
        if (!is_array($road)) {
            $road = [];
        }
        $road[] = $winner;
        if (count($road) > 24) {
            $road = array_slice($road, -24);
        }
        $pJson = addslashes(json_encode($pCards));
        $bJson = addslashes(json_encode($bCards));
        $roadJson = addslashes(json_encode($road));
        $win_esc = addslashes($winner);
        $res_esc = addslashes($result);
        $until = addslashes(date('Y-m-d H:i:s', time() + BACCARAT_RESULT_SEC));
        $mvpSql = ", last_mvp_nick = '', last_mvp_delta = '0'";
        if ($hadBet && $mvpNick !== '' && $mvpDelta !== '0') {
            $mvpSql = ", last_mvp_nick = '" . addslashes($mvpNick) . "', last_mvp_delta = '" . addslashes($mvpDelta) . "'";
        }
        db_query("UPDATE tb_baccarat_table
            SET phase = 'result',
                phase_until = '{$until}',
                player_cards = '{$pJson}',
                banker_cards = '{$bJson}',
                winner = '{$win_esc}',
                last_result = '{$res_esc}',
                road = '{$roadJson}'
                {$mvpSql}
            WHERE idx = 1 LIMIT 1");
    }
}

if (!function_exists('baccarat_tick')) {
    function baccarat_tick(): void {
        baccarat_schema_ensure();
        baccarat_kick_idle();
        $t = baccarat_table();
        $phase = (string)($t['phase'] ?? 'lobby');
        $until = !empty($t['phase_until']) ? strtotime((string)$t['phase_until']) : 0;
        $now = time();
        $seats = baccarat_seats();
        $n = count($seats);

        if ($phase === 'lobby') {
            if ($n < 1) {
                if ($until) {
                    baccarat_set_phase('lobby', 0);
                }
                return;
            }
            if ($until > 0 && $now >= $until) {
                baccarat_start_betting();
            } elseif ($until <= 0) {
                baccarat_set_phase('lobby', BACCARAT_LOBBY_SEC);
            }
            return;
        }
        if ($phase === 'betting') {
            if ($until > 0 && $now >= $until) {
                baccarat_deal_and_settle();
            }
            return;
        }
        if ($phase === 'result') {
            if ($until > 0 && $now >= $until) {
                if ($n >= 1) {
                    baccarat_start_betting();
                } else {
                    baccarat_set_phase('lobby', 0, [
                        'player_cards' => '[]',
                        'banker_cards' => '[]',
                        'winner' => '',
                    ]);
                }
            }
        }
    }
}

if (!function_exists('baccarat_오늘100타출석')) {
    function baccarat_오늘100타출석(string $nick): array {
        $nick = trim($nick);
        if ($nick === '') {
            return ['완료' => false, '현재' => 0];
        }
        if (function_exists('오늘100타출석상태')) {
            $st = 오늘100타출석상태($nick);
            return [
                '완료' => !empty($st['완료']),
                '현재' => (int)($st['현재'] ?? 0),
            ];
        }
        $esc = addslashes($nick);
        $오늘 = date('Y-m-d');
        $출석 = @db_select("
            SELECT COUNT(*) AS cnt
            FROM tb_attendance
            WHERE regdate = '{$오늘}' AND nickname = '{$esc}'
        ");
        if ((int)($출석['cnt'] ?? 0) > 0) {
            return ['완료' => true, '현재' => 100];
        }
        $expr = function_exists('생타_SQL_select_expr')
            ? 생타_SQL_select_expr('msg')
            : 'COUNT(*)';
        $로우 = @db_select("
            SELECT {$expr} AS cnt
            FROM tb_msg
            WHERE nickname = '{$esc}' AND DATE(regdate) = '{$오늘}' AND tasu != 0
        ");
        $현재 = (int)($로우['cnt'] ?? 0);
        return ['완료' => $현재 >= 100, '현재' => $현재];
    }
}

if (!function_exists('baccarat_join')) {
    function baccarat_join(string $nick): array {
        $seats = baccarat_seats();
        foreach ($seats as $s) {
            if ((string)($s['nick'] ?? '') === $nick) {
                return ['ok' => true, 'data' => '이미 앉아 있어요.'];
            }
        }
        $출석 = baccarat_오늘100타출석($nick);
        if (empty($출석['완료'])) {
            $현재 = (int)($출석['현재'] ?? 0);
            return ['ok' => false, 'data' => "오늘 100타 출석을 먼저 해 주세요.\n현재 {$현재}타 / 100타"];
        }
        if (count($seats) >= BACCARAT_MAX_SEATS) {
            return ['ok' => false, 'data' => '자리가 꽉 찼어요. (최대 ' . BACCARAT_MAX_SEATS . '명)'];
        }
        $used = [];
        foreach ($seats as $s) {
            $used[(int)($s['seat'] ?? 0)] = true;
        }
        $seatNo = 0;
        for ($i = 1; $i <= BACCARAT_MAX_SEATS; $i++) {
            if (empty($used[$i])) {
                $seatNo = $i;
                break;
            }
        }
        if ($seatNo < 1) {
            return ['ok' => false, 'data' => '자리가 없어요.'];
        }
        $esc = addslashes($nick);
        $ok = db_query("INSERT INTO tb_baccarat_seat (nick, seat, bet_round, bet_side, bet_amount, online_at, joined_at)
            VALUES ('{$esc}', {$seatNo}, 0, '', '0', NOW(), NOW())");
        if ($ok === false) {
            return ['ok' => false, 'data' => '입장에 실패했어요. 잠시 후 다시 시도해 주세요.'];
        }
        $t = baccarat_table();
        if ((string)($t['phase'] ?? '') === 'lobby' && empty($t['phase_until'])) {
            baccarat_set_phase('lobby', BACCARAT_LOBBY_SEC);
        }
        return ['ok' => true, 'data' => "{$seatNo}번 자리에 앉았어요."];
    }
}

if (!function_exists('baccarat_leave')) {
    function baccarat_leave(string $nick): array {
        $t = baccarat_table();
        $phase = (string)($t['phase'] ?? 'lobby');
        $round = (int)($t['round_no'] ?? 0);
        $esc = addslashes($nick);
        $seat = @db_select("SELECT * FROM tb_baccarat_seat WHERE nick = '{$esc}' LIMIT 1");
        if (empty($seat['nick'])) {
            return ['ok' => true, 'data' => '이미 자리에서 일어났어요.'];
        }
        $hasBet = ($phase === 'betting' || $phase === 'result')
            && baccarat_seat_has_bet($seat, $round);
        if ($hasBet) {
            return ['ok' => false, 'data' => '이번 라운드 배팅 중에는 일어날 수 없어요.'];
        }
        db_query("DELETE FROM tb_baccarat_seat WHERE nick = '{$esc}' LIMIT 1");
        return ['ok' => true, 'data' => '자리에서 일어났어요.'];
    }
}

if (!function_exists('baccarat_start_now')) {
    function baccarat_start_now(string $nick): array {
        $esc = addslashes($nick);
        $seat = @db_select("SELECT nick FROM tb_baccarat_seat WHERE nick = '{$esc}' LIMIT 1");
        if (empty($seat['nick'])) {
            return ['ok' => false, 'data' => '먼저 자리에 앉아 주세요.'];
        }
        $t = baccarat_table();
        if ((string)($t['phase'] ?? '') !== 'lobby') {
            return ['ok' => false, 'data' => '이미 라운드가 진행 중이에요.'];
        }
        baccarat_start_betting();
        return ['ok' => true, 'data' => '배팅을 시작합니다.'];
    }
}

if (!function_exists('baccarat_x2_force')) {
    function baccarat_x2_force(string $nick): array {
        if (!baccarat_is_minho($nick)) {
            return ['ok' => false, 'data' => 'X2 발동은 민호만 할 수 있어요.'];
        }
        $t = baccarat_table();
        if ((int)($t['x2_force'] ?? 0) === 1) {
            return ['ok' => true, 'data' => '다음 판 X2는 이미 예약되어 있어요.'];
        }
        db_query("UPDATE tb_baccarat_table SET x2_force = 1 WHERE idx = 1 LIMIT 1");
        return ['ok' => true, 'data' => '다음 판에 X2를 강제 발동합니다.'];
    }
}

if (!function_exists('baccarat_bet')) {
    function baccarat_bet(string $nick, string $side, $amountRaw): array {
        $side = strtoupper(trim($side));
        if (!in_array($side, ['P', 'B', 'T'], true)) {
            return ['ok' => false, 'data' => '플레이어 / 뱅커 / 타이 중 하나를 골라 주세요.'];
        }
        $amount = function_exists('냥_금액_파싱_문자열')
            ? 냥_금액_파싱_문자열((string)$amountRaw)
            : baccarat_int($amountRaw);
        $amount = baccarat_int($amount);
        if ($amount === '0' || strlen($amount) > 65) {
            return ['ok' => false, 'data' => '배팅 금액을 확인해 주세요.'];
        }
        if (baccarat_cmp($amount, (string)BACCARAT_MIN_BET) < 0) {
            return ['ok' => false, 'data' => '최소 배팅은 ' . baccarat_fmt(BACCARAT_MIN_BET) . '냥이에요.'];
        }
        $esc = addslashes($nick);
        $seat = @db_select("SELECT * FROM tb_baccarat_seat WHERE nick = '{$esc}' LIMIT 1");
        if (empty($seat['nick'])) {
            return ['ok' => false, 'data' => '먼저 자리에 앉아 주세요.'];
        }
        $출석 = baccarat_오늘100타출석($nick);
        if (empty($출석['완료'])) {
            $현재 = (int)($출석['현재'] ?? 0);
            return ['ok' => false, 'data' => "오늘 100타 출석을 먼저 해 주세요.\n현재 {$현재}타 / 100타"];
        }
        $t = baccarat_table();
        if ((string)($t['phase'] ?? '') !== 'betting') {
            return ['ok' => false, 'data' => '지금은 배팅 시간이 아니에요.'];
        }
        $round = (int)($t['round_no'] ?? 0);
        $bets = baccarat_seat_bets($seat, $round);
        $rawHold = trim(baccarat_point_raw($nick));
        if ($rawHold !== '' && $rawHold[0] === '-') {
            return ['ok' => false, 'data' => '신불자는 배팅할 수 없어요. 게임냥을 0 이상으로 회복해 주세요.'];
        }
        $raw = baccarat_int($rawHold);
        if (baccarat_cmp($amount, $raw) > 0) {
            return ['ok' => false, 'data' => '가진 게임냥보다 많이 걸 수 없어요. (보유 ' . baccarat_fmt($raw) . ')'];
        }
        if (!baccarat_debit($nick, $amount)) {
            return ['ok' => false, 'data' => '게임냥이 부족해요. (보유 ' . baccarat_fmt(baccarat_point_raw($nick)) . ')'];
        }
        $bets[$side] = baccarat_add($bets[$side] ?? '0', $amount);
        $total = baccarat_bets_total($bets);
        $primary = baccarat_bets_primary_side($bets);
        $p_esc = addslashes($bets['P']);
        $b_esc = addslashes($bets['B']);
        $t_esc = addslashes($bets['T']);
        $side_esc = addslashes($primary);
        $tot_esc = addslashes($total);
        db_query("UPDATE tb_baccarat_seat
            SET bet_round = {$round}, bet_p = '{$p_esc}', bet_b = '{$b_esc}', bet_t = '{$t_esc}', bet_side = '{$side_esc}', bet_amount = '{$tot_esc}', last_delta = '0', last_msg = ''
            WHERE nick = '{$esc}' LIMIT 1");
        if (function_exists('지급로그')) {
            지급로그('냥카라-배팅', $nick, $side, 0, $amount);
        }
        return ['ok' => true, 'data' => baccarat_side_label($side) . ' ' . baccarat_fmt($amount) . '냥 배팅!'];
    }
}

if (!function_exists('baccarat_seat_won')) {
    function baccarat_seat_won(array $seat): bool {
        $msg = (string)($seat['last_msg'] ?? '');
        if (strpos($msg, '적중') === false) {
            return false;
        }
        $d = (string)($seat['last_delta'] ?? '0');
        if ($d === '' || $d[0] === '-') {
            return false;
        }
        return baccarat_int($d) !== '0';
    }
}

if (!function_exists('baccarat_tip_pct_norm')) {
    function baccarat_tip_pct_norm($pct): string {
        $s = trim((string)$pct);
        $s = str_replace('%', '', $s);
        if ($s === '10' || $s === '10.0') {
            return '10';
        }
        return '5';
    }
}

if (!function_exists('baccarat_tip_per')) {
    function baccarat_tip_per(string $win, string $pct): string {
        $win = baccarat_int($win);
        if ($win === '0') {
            return '0';
        }
        if ($pct === '10') {
            return baccarat_div_floor($win, 10);
        }
        return baccarat_div_floor($win, 20);
    }
}

if (!function_exists('baccarat_tip_info')) {
    /** @param list<array>|null $seats */
    function baccarat_tip_info(string $nick, ?array $t = null, ?array $seats = null, string $pct = '5'): array {
        $empty = [
            'can' => false,
            'done' => false,
            'pct' => '5',
            'per' => '0',
            'per_fmt' => '0',
            'count' => 0,
            'total' => '0',
            'total_fmt' => '0',
            'options' => [],
        ];
        $t = $t ?? baccarat_table();
        $seats = $seats ?? baccarat_seats();
        $pct = baccarat_tip_pct_norm($pct);
        $round = (int)($t['round_no'] ?? 0);
        $phase = (string)($t['phase'] ?? '');
        $me = null;
        $others = [];
        foreach ($seats as $s) {
            $n = trim((string)($s['nick'] ?? ''));
            if ($n === '') {
                continue;
            }
            if ($n === $nick) {
                $me = $s;
            } else {
                $others[] = $n;
            }
        }
        if ($me === null) {
            return $empty;
        }
        $done = $round > 0 && (int)($me['tip_round'] ?? 0) === $round;
        if ($phase !== 'result' || !baccarat_seat_won($me) || $others === []) {
            return array_merge($empty, ['done' => $done]);
        }
        if ($done) {
            return array_merge($empty, ['done' => true]);
        }
        $win = (string)$me['last_delta'];
        $count = count($others);
        $options = [];
        $any = false;
        foreach (['5', '10'] as $p) {
            $per = baccarat_tip_per($win, $p);
            $total = $per === '0' ? '0' : baccarat_mul($per, $count);
            $ok = $per !== '0';
            if ($ok) {
                $any = true;
            }
            $options[] = [
                'pct' => $p,
                'label' => $p . '%',
                'ok' => $ok,
                'per' => $per,
                'per_fmt' => baccarat_fmt($per),
                'total' => $total,
                'total_fmt' => baccarat_fmt($total),
            ];
        }
        if (!$any) {
            return $empty;
        }
        $picked = $options[0];
        foreach ($options as $opt) {
            if ($opt['pct'] === $pct) {
                $picked = $opt;
                break;
            }
        }
        return [
            'can' => true,
            'done' => false,
            'pct' => $picked['pct'],
            'per' => $picked['per'],
            'per_fmt' => $picked['per_fmt'],
            'count' => $count,
            'total' => $picked['total'],
            'total_fmt' => $picked['total_fmt'],
            'options' => $options,
        ];
    }
}

if (!function_exists('baccarat_parse_last_tip')) {
    function baccarat_parse_last_tip($raw): ?array {
        $raw = trim((string)$raw);
        if ($raw === '') {
            return null;
        }
        $j = json_decode($raw, true);
        if (!is_array($j) || empty($j['from']) || empty($j['text'])) {
            return null;
        }
        return [
            'from' => (string)$j['from'],
            'text' => (string)$j['text'],
        ];
    }
}

if (!function_exists('baccarat_tip')) {
    function baccarat_tip(string $nick, $pct = '5'): array {
        $t = baccarat_table();
        $seats = baccarat_seats();
        $pct = baccarat_tip_pct_norm($pct);
        $info = baccarat_tip_info($nick, $t, $seats, $pct);
        if (!empty($info['done'])) {
            return ['ok' => false, 'data' => '이번 판 뽀찌는 이미 줬어요.'];
        }
        if (empty($info['can'])) {
            return ['ok' => false, 'data' => '딴 뒤에, 같이 앉은 친구에게만 뽀찌를 줄 수 있어요.'];
        }
        if (($info['per'] ?? '0') === '0') {
            return ['ok' => false, 'data' => '이 비율로는 뽀찌 금액이 너무 작아요.'];
        }
        $raw = baccarat_point_raw($nick);
        if (!baccarat_can_afford($raw, (string)$info['total'])) {
            return ['ok' => false, 'data' => '게임냥이 부족해요. (필요 ' . $info['total_fmt'] . '냥)'];
        }
        if (!baccarat_debit($nick, (string)$info['total'])) {
            return ['ok' => false, 'data' => '차감에 실패했어요. 잔액을 확인해 주세요.'];
        }
        $per = (string)$info['per'];
        $paid = 0;
        foreach ($seats as $s) {
            $n = trim((string)($s['nick'] ?? ''));
            if ($n === '' || $n === $nick) {
                continue;
            }
            baccarat_credit($n, $per);
            $paid++;
            if (function_exists('지급로그')) {
                지급로그('냥카라-뽀찌받음', $n, $nick, 0, $per);
            }
        }
        if ($paid < 1) {
            baccarat_credit($nick, (string)$info['total']);
            return ['ok' => false, 'data' => '나눠 줄 친구가 없어요.'];
        }
        if ($paid < (int)$info['count']) {
            baccarat_credit($nick, baccarat_mul($per, (int)$info['count'] - $paid));
        }
        $spent = baccarat_mul($per, $paid);
        if (function_exists('지급로그')) {
            지급로그('냥카라-뽀찌', $nick, $paid . '명', 0, $spent);
        }
        $round = (int)($t['round_no'] ?? 0);
        $esc = addslashes($nick);
        db_query("UPDATE tb_baccarat_seat SET tip_round = {$round} WHERE nick = '{$esc}' LIMIT 1");
        $text = $nick . ' 님이 앉은 친구 ' . $paid . '명에게 각 ' . baccarat_fmt($per) . '냥(' . $pct . '%) 뽀찌를 뿌렸어요 🎁';
        $flash = addslashes(json_encode(['from' => $nick, 'text' => $text], JSON_UNESCAPED_UNICODE));
        db_query("UPDATE tb_baccarat_table SET last_tip = '{$flash}' WHERE idx = 1 LIMIT 1");
        return ['ok' => true, 'data' => '친구 ' . $paid . '명에게 각 ' . baccarat_fmt($per) . '냥(' . $pct . '%) 뽀찌!'];
    }
}

if (!function_exists('baccarat_emo_map')) {
    /** @return array<string,string> */
    function baccarat_emo_map(): array {
        return [
            'smile' => '😊',
            'sad' => '😢',
            'smoke' => '🚬',
            'thumb' => '👍',
            'angry' => '😡',
            'please' => '🤲',
        ];
    }
}

if (!function_exists('baccarat_parse_reacts')) {
    /** @return list<array{id:string,nick:string,emo:string}> */
    function baccarat_parse_reacts($raw): array {
        $j = json_decode((string)$raw, true);
        if (!is_array($j)) {
            return [];
        }
        $map = baccarat_emo_map();
        $out = [];
        $cut = time() - 25;
        foreach ($j as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = trim((string)($row['id'] ?? ''));
            $nick = trim((string)($row['nick'] ?? ''));
            $emo = trim((string)($row['emo'] ?? ''));
            $t = (int)($row['t'] ?? 0);
            if ($id === '' || $nick === '' || !isset($map[$emo])) {
                continue;
            }
            if ($t > 0 && $t < $cut) {
                continue;
            }
            $out[] = [
                'id' => $id,
                'nick' => $nick,
                'emo' => $emo,
            ];
        }
        if (count($out) > 12) {
            $out = array_slice($out, -12);
        }
        return $out;
    }
}

if (!function_exists('baccarat_react')) {
    function baccarat_react(string $nick, string $emo): array {
        $emo = trim($emo);
        $map = baccarat_emo_map();
        if (!isset($map[$emo])) {
            return ['ok' => false, 'data' => '이모티콘을 골라 주세요.'];
        }
        $esc = addslashes($nick);
        $seat = @db_select("SELECT nick FROM tb_baccarat_seat WHERE nick = '{$esc}' LIMIT 1");
        if (empty($seat['nick'])) {
            return ['ok' => false, 'data' => '자리에 앉은 뒤에 보낼 수 있어요.'];
        }
        $t = baccarat_table();
        $list = json_decode((string)($t['reacts'] ?? '[]'), true);
        if (!is_array($list)) {
            $list = [];
        }
        $now = time();
        foreach (array_reverse($list) as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (trim((string)($row['nick'] ?? '')) !== $nick) {
                continue;
            }
            if ($now - (int)($row['t'] ?? 0) < 1) {
                return ['ok' => true, 'data' => ''];
            }
            break;
        }
        $list[] = [
            'id' => bin2hex(random_bytes(6)),
            'nick' => $nick,
            'emo' => $emo,
            't' => $now,
        ];
        $cut = $now - 25;
        $keep = [];
        foreach ($list as $row) {
            if (!is_array($row)) {
                continue;
            }
            if ((int)($row['t'] ?? 0) < $cut) {
                continue;
            }
            $keep[] = $row;
        }
        if (count($keep) > 12) {
            $keep = array_slice($keep, -12);
        }
        $json = addslashes(json_encode($keep, JSON_UNESCAPED_UNICODE));
        db_query("UPDATE tb_baccarat_table SET reacts = '{$json}' WHERE idx = 1 LIMIT 1");
        return ['ok' => true, 'data' => ''];
    }
}

if (!function_exists('baccarat_remain_sec')) {
    function baccarat_remain_sec($until): int {
        if (empty($until)) {
            return 0;
        }
        $t = strtotime((string)$until);
        if ($t === false) {
            return 0;
        }
        return max(0, $t - time());
    }
}

if (!function_exists('baccarat_public_view')) {
    function baccarat_public_view(string $nick): array {
        $t = baccarat_table();
        $phase = (string)($t['phase'] ?? 'lobby');
        $showCards = ($phase === 'result');
        $pCards = baccarat_decode_cards($t['player_cards'] ?? '[]');
        $bCards = baccarat_decode_cards($t['banker_cards'] ?? '[]');
        $pView = [];
        $bView = [];
        if ($showCards) {
            foreach ($pCards as $c) {
                $pView[] = baccarat_card_view((int)$c);
            }
            foreach ($bCards as $c) {
                $bView[] = baccarat_card_view((int)$c);
            }
        }
        $seatsOut = [];
        $meSeat = null;
        $seatsRaw = baccarat_seats();
        $nicks = [];
        foreach ($seatsRaw as $s) {
            $sn = trim((string)($s['nick'] ?? ''));
            if ($sn !== '') {
                $nicks[] = $sn;
            }
        }
        if ($nick !== '') {
            $nicks[] = $nick;
        }
        $points = baccarat_points_map($nicks);
        foreach ($seatsRaw as $s) {
            $snick = (string)($s['nick'] ?? '');
            $pt = $points[$snick] ?? '0';
            $roundNo = (int)($t['round_no'] ?? 0);
            $bets = baccarat_seat_bets($s, $roundNo);
            $hasBet = baccarat_seat_has_bet($s, $roundNo);
            $total = baccarat_bets_total($bets);
            $item = [
                'nick' => $snick,
                'seat' => (int)($s['seat'] ?? 0),
                'bet_side' => baccarat_bets_primary_side($bets),
                'bet_amount' => $total,
                'bet_amount_fmt' => baccarat_fmt($total),
                'bet_label' => baccarat_bets_label($bets),
                'bets' => [
                    'P' => $bets['P'],
                    'B' => $bets['B'],
                    'T' => $bets['T'],
                    'P_fmt' => baccarat_fmt($bets['P']),
                    'B_fmt' => baccarat_fmt($bets['B']),
                    'T_fmt' => baccarat_fmt($bets['T']),
                ],
                'has_bet' => $hasBet,
                'all_sides_bet' => $bets['P'] !== '0' && $bets['B'] !== '0' && $bets['T'] !== '0',
                'last_delta' => (string)($s['last_delta'] ?? '0'),
                'last_msg' => (string)($s['last_msg'] ?? ''),
                'point' => $pt,
                'point_fmt' => baccarat_fmt($pt),
                'point_compact' => baccarat_preset_label($pt),
                'me' => ($snick === $nick),
            ];
            $seatsOut[] = $item;
            if ($snick === $nick) {
                $meSeat = $item;
            }
        }
        $road = json_decode((string)($t['road'] ?? '[]'), true);
        if (!is_array($road)) {
            $road = [];
        }
        $mvpNick = trim((string)($t['last_mvp_nick'] ?? ''));
        $mvpDelta = baccarat_int($t['last_mvp_delta'] ?? '0');
        $mvp = ($mvpNick !== '' && $mvpDelta !== '0')
            ? [
                'nick' => $mvpNick,
                'delta' => $mvpDelta,
                'delta_fmt' => baccarat_fmt($mvpDelta),
                'delta_compact' => baccarat_preset_label($mvpDelta),
            ]
            : null;
        $pointRaw = $points[$nick] ?? baccarat_point_raw($nick);
        return [
            'phase' => $phase,
            'round_no' => (int)($t['round_no'] ?? 0),
            'remain' => baccarat_remain_sec($t['phase_until'] ?? null),
            'winner' => (string)($t['winner'] ?? ''),
            'last_result' => (string)($t['last_result'] ?? ''),
            'player_cards' => $pView,
            'banker_cards' => $bView,
            'player_total' => $showCards ? baccarat_total($pCards) : null,
            'banker_total' => $showCards ? baccarat_total($bCards) : null,
            'seats' => $seatsOut,
            'seat_count' => count($seatsOut),
            'max_seats' => BACCARAT_MAX_SEATS,
            'road' => $road,
            'min_bet' => BACCARAT_MIN_BET,
            'min_bet_fmt' => baccarat_fmt(BACCARAT_MIN_BET),
            'presets' => baccarat_preset_bets(),
            'mvp' => $mvp,
            'tip' => baccarat_tip_info($nick, $t, $seatsRaw),
            'last_tip' => baccarat_parse_last_tip($t['last_tip'] ?? ''),
            'reacts' => baccarat_parse_reacts($t['reacts'] ?? '[]'),
            'x2' => baccarat_x2_payload($t),
            'x2_force_queued' => baccarat_is_minho($nick) && (int)($t['x2_force'] ?? 0) === 1,
            'me' => [
                'nick' => $nick,
                'seated' => $meSeat !== null,
                'seat' => $meSeat['seat'] ?? 0,
                'has_bet' => !empty($meSeat['has_bet']),
                'all_sides_bet' => !empty($meSeat['all_sides_bet']),
                'bet_side' => $meSeat['bet_side'] ?? '',
                'bet_amount' => $meSeat['bet_amount'] ?? '0',
                'bets' => $meSeat['bets'] ?? ['P' => '0', 'B' => '0', 'T' => '0'],
                'last_msg' => $meSeat['last_msg'] ?? '',
                'last_delta' => $meSeat['last_delta'] ?? '0',
                'point' => $pointRaw,
                'point_fmt' => baccarat_fmt($pointRaw),
                'is_minho' => baccarat_is_minho($nick),
                'is_offwork' => baccarat_is_offwork($nick),
            ],
        ];
    }
}

if (!function_exists('baccarat_payload')) {
    function baccarat_payload(string $nick, array $extra = []): array {
        baccarat_touch($nick);
        baccarat_tick();
        $base = [
            'ok' => true,
            'nick' => $nick,
            'table' => baccarat_public_view($nick),
        ];
        return array_merge($base, $extra);
    }
}
