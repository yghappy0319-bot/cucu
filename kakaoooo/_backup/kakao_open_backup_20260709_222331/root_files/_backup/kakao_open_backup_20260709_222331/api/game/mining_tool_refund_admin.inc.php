<?php
/**
 * 채굴 장비 강화비 환불 (관리자)
 */

require_once __DIR__ . '/mining_ore_admin.inc.php';
require_once __DIR__ . '/mining_storage.inc.php';
require_once __DIR__ . '/mining_ore.inc.php';
require_once __DIR__ . '/mining_weapon.inc.php';

if (!function_exists('mining_tool_refund_spend_statuses')) {
    /** @return list<string> */
    function mining_tool_refund_spend_statuses(): array {
        return ['채굴강화성공', '채굴강화실패'];
    }
}

if (!function_exists('mining_tool_refund_log_status')) {
    function mining_tool_refund_log_status(): string {
        return '채굴강화환불';
    }
}

if (!function_exists('mining_tool_refund_newpoint_log_status')) {
    function mining_tool_refund_newpoint_log_status(): string {
        return '채굴강화환불본방';
    }
}

if (!function_exists('mining_tool_refund_newpoint_per_nick')) {
    /** 닉당 본방냥 환불액 (시도 횟수 무관) */
    function mining_tool_refund_newpoint_per_nick(): int {
        return 15000;
    }
}

if (!function_exists('mining_tool_refund_mark_suffix')) {
    function mining_tool_refund_mark_suffix(): string {
        return '|환불';
    }
}

if (!function_exists('mining_tool_refund_date_where')) {
    function mining_tool_refund_date_where(?string $date_from, ?string $date_to): string {
        $parts = [];
        $date_from = trim((string)$date_from);
        $date_to = trim((string)$date_to);
        if ($date_from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
            $parts[] = "regdate >= '" . addslashes($date_from) . " 00:00:00'";
        }
        if ($date_to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
            $parts[] = "regdate <= '" . addslashes($date_to) . " 23:59:59'";
        }
        return $parts ? (' AND ' . implode(' AND ', $parts)) : '';
    }
}

if (!function_exists('mining_tool_refund_scan')) {
    /**
     * @return array{
     *   nick_summary:list<array<string,mixed>>,
     *   logs:list<array<string,mixed>>,
     *   stats:array<string,int|float>
     * }
     */
    function mining_tool_refund_scan(?string $date_from = null, ?string $date_to = null): array {
        mining_ore_admin_bootstrap();

        $statuses = mining_tool_refund_spend_statuses();
        $in = "'" . implode("','", array_map('addslashes', $statuses)) . "'";
        $date_sql = mining_tool_refund_date_where($date_from, $date_to);

        $nick_summary = [];
        $rs = db_query("
            SELECT
                nick,
                COUNT(*) AS attempt_count,
                SUM(CASE WHEN status = '채굴강화성공' THEN 1 ELSE 0 END) AS success_count,
                SUM(CASE WHEN status = '채굴강화실패' THEN 1 ELSE 0 END) AS fail_count,
                SUM(point) AS total_spent,
                MIN(regdate) AS first_at,
                MAX(regdate) AS last_at
            FROM tb_point_log
            WHERE status IN ({$in})
            {$date_sql}
            GROUP BY nick
            ORDER BY total_spent DESC, nick ASC
        ");
        if ($rs) {
            while ($row = db_fetch($rs)) {
                $spent = (int)($row['total_spent'] ?? 0);
                if ($spent <= 0) {
                    continue;
                }
                $nick_summary[] = [
                    'nick' => (string)($row['nick'] ?? ''),
                    'attempt_count' => (int)($row['attempt_count'] ?? 0),
                    'success_count' => (int)($row['success_count'] ?? 0),
                    'fail_count' => (int)($row['fail_count'] ?? 0),
                    'total_spent' => $spent,
                    'total_spent_fmt' => number_format($spent),
                    'first_at' => (string)($row['first_at'] ?? ''),
                    'last_at' => (string)($row['last_at'] ?? ''),
                ];
            }
        }

        $refunded_map = [];
        $refund_status = addslashes(mining_tool_refund_log_status());
        $rs_ref = db_query("
            SELECT nick, SUM(point) AS refunded
            FROM tb_point_log
            WHERE status = '{$refund_status}'
            GROUP BY nick
        ");
        if ($rs_ref) {
            while ($row = db_fetch($rs_ref)) {
                $refunded_map[(string)($row['nick'] ?? '')] = (int)($row['refunded'] ?? 0);
            }
        }

        $np_per_nick = mining_tool_refund_newpoint_per_nick();
        $pending_total = 0;
        $pending_newpoint_total = 0;
        $pending_nicks = 0;
        foreach ($nick_summary as &$item) {
            $already = (int)($refunded_map[$item['nick']] ?? 0);
            $pending_newpoint = $np_per_nick;
            $item['already_refunded'] = $already;
            $item['already_refunded_fmt'] = number_format($already);
            $item['pending_refund'] = $item['total_spent'];
            $item['pending_refund_fmt'] = number_format($item['pending_refund']);
            $item['pending_newpoint_refund'] = $pending_newpoint;
            $item['pending_newpoint_refund_fmt'] = number_format($pending_newpoint);
            $pending_total += $item['pending_refund'];
            $pending_newpoint_total += $pending_newpoint;
            $pending_nicks++;
        }
        unset($item);

        $logs = [];
        $rs_logs = db_query("
            SELECT idx, nick, status, receiver, point, regdate
            FROM tb_point_log
            WHERE status IN ({$in})
            {$date_sql}
            ORDER BY regdate DESC, idx DESC
            LIMIT 200
        ");
        if ($rs_logs) {
            while ($row = db_fetch($rs_logs)) {
                $logs[] = [
                    'idx' => (int)($row['idx'] ?? 0),
                    'nick' => (string)($row['nick'] ?? ''),
                    'status' => (string)($row['status'] ?? ''),
                    'receiver' => (string)($row['receiver'] ?? ''),
                    'point' => (int)($row['point'] ?? 0),
                    'point_fmt' => number_format((int)($row['point'] ?? 0)),
                    'regdate' => (string)($row['regdate'] ?? ''),
                ];
            }
        }

        return [
            'nick_summary' => $nick_summary,
            'logs' => $logs,
            'stats' => [
                'nick_count' => $pending_nicks,
                'pending_total' => $pending_total,
                'pending_total_fmt' => number_format($pending_total),
                'pending_newpoint_total' => $pending_newpoint_total,
                'pending_newpoint_total_fmt' => number_format($pending_newpoint_total),
                'newpoint_per_nick' => $np_per_nick,
                'newpoint_per_nick_fmt' => number_format($np_per_nick),
                'attempt_total' => array_sum(array_column($nick_summary, 'attempt_count')),
                'log_preview' => count($logs),
            ],
        ];
    }
}

if (!function_exists('mining_tool_refund_reset_nick')) {
    /**
     * 채굴 상태 전체 초기화 (장비 Lv0 · 누적 · 광물 · 무기장착 등)
     *
     * @return array{ok:bool,ore_find_deleted:int,ore_log_deleted:int}
     */
    function mining_tool_refund_reset_nick(string $nick): array {
        mining_ore_admin_bootstrap();
        mining_data_ensure_table();
        mining_ore_ensure_schema();
        mining_ore_ensure_member_columns();
        mining_weapon_ensure_columns();

        $nick_esc = mining_data_nick_esc($nick);
        if ($nick_esc === '') {
            return ['ok' => false, 'ore_find_deleted' => 0, 'ore_log_deleted' => 0];
        }

        mining_data_ensure_row($nick);
        $tbl = MINING_TABLE;

        db_query("
            UPDATE `{$tbl}`
            SET mining_tool = 0,
                mining_pending = 0,
                mining_sync_at = NULL,
                mining_lease_token = NULL,
                mining_lease_until = NULL,
                mining_weapon_equipped = 0,
                mining_ore_roll_acc = 0,
                mining_ore_roll_targets = '',
                mining_eunchong_shard = 0,
                mining_upgrade_attempts = 0
            WHERE nick = '{$nick_esc}'
            LIMIT 1
        ");

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

        return [
            'ok' => true,
            'ore_find_deleted' => $ore_find_deleted,
            'ore_log_deleted' => $ore_log_deleted,
        ];
    }
}

if (!function_exists('mining_tool_refund_execute')) {
    /**
     * @param list<string>|null $nicks null이면 스캔 조건 전체
     * @return array{ok:bool,msg:string,refunded_nicks?:int,refunded_total?:int,refunded_newpoint_total?:int,reset_nicks?:int}
     */
    function mining_tool_refund_execute(
        string $admin_nick,
        ?array $nicks = null,
        ?string $date_from = null,
        ?string $date_to = null,
        bool $reset_mining = false
    ): array {
        mining_ore_admin_bootstrap();

        $admin_nick = trim($admin_nick);
        if ($admin_nick === '') {
            return ['ok' => false, 'msg' => '관리자 정보가 없습니다.'];
        }

        $scan = mining_tool_refund_scan($date_from, $date_to);
        $targets = [];
        $nick_filter = null;
        if (is_array($nicks) && count($nicks) > 0) {
            $nick_filter = [];
            foreach ($nicks as $nick) {
                $nick = trim((string)$nick);
                if ($nick !== '') {
                    $nick_filter[$nick] = true;
                }
            }
        }

        foreach ($scan['nick_summary'] as $row) {
            $nick = (string)$row['nick'];
            $amount = (int)$row['pending_refund'];
            if ($amount <= 0) {
                continue;
            }
            if ($nick_filter !== null && empty($nick_filter[$nick])) {
                continue;
            }
            $attempt_count = (int)$row['attempt_count'];
            $targets[] = [
                'nick' => $nick,
                'amount' => $amount,
                'attempt_count' => $attempt_count,
                'newpoint_amount' => mining_tool_refund_newpoint_per_nick(),
            ];
        }

        if (count($targets) === 0) {
            return ['ok' => false, 'msg' => '환불할 대상이 없습니다.'];
        }

        if (!function_exists('지급로그')) {
            if (!defined('WALLET_LIB_ONLY')) {
                define('WALLET_LIB_ONLY', true);
            }
            require_once __DIR__ . '/wallet_web.php';
        }

        $statuses = mining_tool_refund_spend_statuses();
        $in = "'" . implode("','", array_map('addslashes', $statuses)) . "'";
        $suffix = addslashes(mining_tool_refund_mark_suffix());
        $refund_status = mining_tool_refund_log_status();
        $date_sql = mining_tool_refund_date_where($date_from, $date_to);
        $batch_id = date('YmdHis');

        $refunded_nicks = 0;
        $refunded_total = 0;
        $refunded_newpoint_total = 0;
        $reset_nicks = 0;
        $reset_ore_find_deleted = 0;
        $reset_ore_log_deleted = 0;
        $newpoint_log_status = mining_tool_refund_newpoint_log_status();
        $errors = [];

        foreach ($targets as $target) {
            $nick = (string)$target['nick'];
            $amount = (int)$target['amount'];
            $newpoint_amount = (int)($target['newpoint_amount'] ?? 0);
            if ($amount <= 0 && $newpoint_amount <= 0) {
                continue;
            }

            $nick_esc = addslashes($nick);
            $member = db_select("SELECT point FROM tb_member WHERE name = '{$nick_esc}' LIMIT 1");
            if (empty($member)) {
                $errors[] = $nick . '(회원없음)';
                continue;
            }

            if ($amount > 0) {
                db_query("UPDATE tb_member SET point = point + {$amount} WHERE name = '{$nick_esc}' LIMIT 1");
            }
            if ($newpoint_amount > 0) {
                db_query("UPDATE tb_member SET newpoint = IFNULL(newpoint, 0) + {$newpoint_amount} WHERE name = '{$nick_esc}' LIMIT 1");
            }

            $receiver = '일괄환불·' . $admin_nick . '·' . $batch_id . '·' . (int)$target['attempt_count'] . '회';
            if ($amount > 0) {
                지급로그($refund_status, $nick, $receiver, 0, $amount);
            }
            if ($newpoint_amount > 0) {
                지급로그($newpoint_log_status, $nick, $receiver, 0, $newpoint_amount);
            }

            db_query("
                UPDATE tb_point_log
                SET status = CONCAT(status, '{$suffix}')
                WHERE nick = '{$nick_esc}'
                  AND status IN ({$in})
                {$date_sql}
            ");

            if ($reset_mining) {
                $reset_result = mining_tool_refund_reset_nick($nick);
                if (!empty($reset_result['ok'])) {
                    $reset_nicks++;
                    $reset_ore_find_deleted += (int)($reset_result['ore_find_deleted'] ?? 0);
                    $reset_ore_log_deleted += (int)($reset_result['ore_log_deleted'] ?? 0);
                }
            }

            $refunded_nicks++;
            $refunded_total += $amount;
            $refunded_newpoint_total += $newpoint_amount;
        }

        if ($refunded_nicks === 0) {
            $msg = '환불에 실패했습니다.';
            if (count($errors) > 0) {
                $msg .= ' (' . implode(', ', $errors) . ')';
            }
            return ['ok' => false, 'msg' => $msg];
        }

        $msg = '환불 완료 · ' . $refunded_nicks . '명 · 게임냥 ' . number_format($refunded_total)
            . '냥 · 본방냥 ' . number_format($refunded_newpoint_total) . '냥';
        if ($reset_mining && $reset_nicks > 0) {
            $msg .= ' · 채굴 초기화 ' . $reset_nicks . '명';
            if ($reset_ore_find_deleted > 0 || $reset_ore_log_deleted > 0) {
                $msg .= '(광물 ' . number_format($reset_ore_find_deleted) . '건 · 이력 ' . number_format($reset_ore_log_deleted) . '건 삭제)';
            }
        }
        if (count($errors) > 0) {
            $msg .= ' (제외: ' . implode(', ', $errors) . ')';
        }

        return [
            'ok' => true,
            'msg' => $msg,
            'refunded_nicks' => $refunded_nicks,
            'refunded_total' => $refunded_total,
            'refunded_newpoint_total' => $refunded_newpoint_total,
            'reset_nicks' => $reset_nicks,
        ];
    }
}
