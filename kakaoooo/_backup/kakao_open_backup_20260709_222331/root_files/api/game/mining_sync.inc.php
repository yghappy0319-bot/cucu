<?php
/**
 * 채굴 미저장 냥 동기화 + 단일 활성 세션(lease)
 * — tb_member_mining 기준, PC/모바일 공유
 */

require_once __DIR__ . '/mining_tool.inc.php';

if (!defined('MINING_LEASE_SEC')) {
    define('MINING_LEASE_SEC', 25);
}

if (!function_exists('mining_sync_table')) {
    function mining_sync_table(): string {
        return MINING_TABLE;
    }
}

if (!function_exists('mining_sync_now')) {
    function mining_sync_now(): int {
        return time();
    }
}

if (!function_exists('mining_sync_ts')) {
    function mining_sync_ts($dt): int {
        if ($dt === null || $dt === '' || $dt === '0000-00-00 00:00:00') {
            return mining_sync_now();
        }
        $t = strtotime((string)$dt);
        return ($t !== false && $t > 0) ? $t : mining_sync_now();
    }
}

if (!function_exists('mining_sync_finalize_expired')) {
    /** lease 만료·비정상 종료 시 마지막 구간 pending 확정 */
    function mining_sync_finalize_expired($nick) {
        mining_data_ensure_table();
        $nick_esc = mining_data_nick_esc($nick);
        if ($nick_esc === '') {
            return null;
        }

        $tbl = mining_sync_table();
        $row = db_select("
            SELECT mining_tool, mining_pending, mining_sync_at,
                   mining_lease_token, mining_lease_until,
                   IFNULL(mining_upgrade_attempts, 0) AS mining_upgrade_attempts
            FROM `{$tbl}`
            WHERE nick = '{$nick_esc}'
            LIMIT 1
        ");
        if (!$row) {
            return null;
        }

        $token = trim((string)($row['mining_lease_token'] ?? ''));
        $lease_until = mining_sync_ts($row['mining_lease_until'] ?? '');
        if ($token === '' || $lease_until <= 0) {
            return $row;
        }
        if (mining_sync_lease_active($row)) {
            return $row;
        }

        $sync_at = mining_sync_ts($row['mining_sync_at'] ?? '');
        $elapsed = max(0, mining_sync_now() - $sync_at);
        $pending = mining_pending_round((float)($row['mining_pending'] ?? 0) + ($elapsed * mining_sync_rate($row, $nick)));
        $pending_sql = mining_pending_sql($pending);

        db_query("
            UPDATE `{$tbl}`
            SET mining_pending = {$pending_sql},
                mining_sync_at = NOW(),
                mining_lease_token = NULL,
                mining_lease_until = NULL
            WHERE nick = '{$nick_esc}'
              AND mining_lease_token = '" . addslashes($token) . "'
            LIMIT 1
        ");

        if ($elapsed > 0) {
            // 광물 spawn 은 _auto_mining_ore.php 크론 전담
        }

        return mining_sync_row($nick);
    }
}

if (!function_exists('mining_sync_row')) {
    function mining_sync_row($nick) {
        mining_data_ensure_table();
        mining_upgrade_attempts_ensure_column();
        $nick_esc = mining_data_nick_esc($nick);
        if ($nick_esc === '') {
            return null;
        }
        if (!mining_data_ensure_row($nick)) {
            return null;
        }

        $tbl = mining_sync_table();
        $row = db_select("
            SELECT mining_tool, mining_pending, mining_sync_at,
                   mining_lease_token, mining_lease_until,
                   IFNULL(mining_upgrade_attempts, 0) AS mining_upgrade_attempts
            FROM `{$tbl}`
            WHERE nick = '{$nick_esc}'
            LIMIT 1
        ");
        if (!$row) {
            return null;
        }
        if (!mining_sync_lease_active($row) && trim((string)($row['mining_lease_token'] ?? '')) !== '') {
            return mining_sync_finalize_expired($nick) ?: $row;
        }
        return $row;
    }
}

if (!function_exists('mining_sync_rate')) {
    function mining_sync_rate(array $row, $nick = ''): float {
        $attempts = (int)($row['mining_upgrade_attempts'] ?? 0);
        if (!mining_yield_unlocked_for_attempts($attempts)) {
            return 0.0;
        }
        $level = max(0, (int)($row['mining_tool'] ?? 0));
        $rate = mining_tool_rate_for_level($level);
        $nick = trim((string)$nick);
        if ($nick !== '' && mining_eunchong_active_for_nick($nick)) {
            $rate *= mining_eunchong_yield_mult(true);
        }
        return (float)$rate;
    }
}

if (!function_exists('mining_sync_yield_mult_for_nick')) {
    function mining_sync_yield_mult_for_nick($nick): float {
        return mining_eunchong_active_for_nick($nick)
            ? mining_eunchong_yield_mult(true)
            : 1.0;
    }
}

if (!function_exists('mining_sync_lease_active')) {
    function mining_sync_lease_active(array $row): bool {
        return mining_sync_ts($row['mining_lease_until'] ?? '') > mining_sync_now();
    }
}

if (!function_exists('mining_sync_lease_owner')) {
    function mining_sync_lease_owner(array $row, $token): bool {
        $token = trim((string)$token);
        if ($token === '' || !mining_sync_lease_active($row)) {
            return false;
        }
        return hash_equals(trim((string)($row['mining_lease_token'] ?? '')), $token);
    }
}

if (!function_exists('mining_sync_display_amount')) {
    /** offline 포함 — sync_at 이후 경과분 항상 반영 */
    function mining_sync_display_amount(array $row, $nick = ''): float {
        $pending = mining_pending_round($row['mining_pending'] ?? 0);
        $sync_at = mining_sync_ts($row['mining_sync_at'] ?? '');
        $elapsed = max(0, mining_sync_now() - $sync_at);
        return mining_pending_round($pending + ($elapsed * mining_sync_rate($row, $nick)));
    }
}

if (!function_exists('mining_sync_commit_elapsed')) {
    /** 경과분을 pending에 확정 (offline 적립 · 저장/강화 전) */
    function mining_sync_commit_elapsed($nick) {
        mining_data_ensure_table();
        $nick_esc = mining_data_nick_esc($nick);
        if ($nick_esc === '') {
            return null;
        }
        if (!mining_data_ensure_row($nick)) {
            return null;
        }

        $row = db_select("
            SELECT mining_tool, mining_pending, mining_sync_at,
                   mining_lease_token, mining_lease_until,
                   IFNULL(mining_upgrade_attempts, 0) AS mining_upgrade_attempts
            FROM `" . mining_sync_table() . "`
            WHERE nick = '{$nick_esc}'
            LIMIT 1
        ");
        if (!$row) {
            return null;
        }

        $sync_at = mining_sync_ts($row['mining_sync_at'] ?? '');
        $elapsed_sec = max(0, mining_sync_now() - $sync_at);

        $old_pending = mining_pending_round((float)($row['mining_pending'] ?? 0));
        $pending = mining_sync_display_amount($row, $nick);
        $delta = max(0, mining_pending_round($pending - $old_pending));
        $pending_sql = mining_pending_sql($pending);
        $tbl = mining_sync_table();
        db_query("
            UPDATE `{$tbl}`
            SET mining_pending = {$pending_sql},
                mining_sync_at = NOW()
            WHERE nick = '{$nick_esc}'
            LIMIT 1
        ");

        if ($elapsed_sec > 0) {
            // 광물 spawn 은 _auto_mining_ore.php 크론 전담
        }

        return mining_sync_row($nick);
    }
}

if (!function_exists('mining_sync_new_token')) {
    function mining_sync_new_token(): string {
        return bin2hex(random_bytes(16));
    }
}

if (!function_exists('mining_sync_payload')) {
    function mining_sync_payload(array $row, $is_leader, $token = '', $nick = '') {
        $nick = trim((string)$nick);
        $amount = mining_sync_display_amount($row, $nick);
        $level = max(0, (int)($row['mining_tool'] ?? 0));
        $yield_mult = mining_sync_yield_mult_for_nick($nick);
        $rate = mining_tool_rate_payload($level, $yield_mult);
        if (!mining_yield_unlocked_for_attempts((int)($row['mining_upgrade_attempts'] ?? 0))) {
            $rate = [
                'rate' => 0.0,
                'rate_fmt' => '0',
                'hourly_yield' => 0.0,
                'hourly_yield_fmt' => '0',
                'daily_yield' => 0.0,
                'daily_yield_fmt' => '0',
            ];
        }
        $required = mining_upgrade_attempts_required();
        $attempts = (int)($row['mining_upgrade_attempts'] ?? 0);
        return array_merge([
            'pending' => $amount,
            'pending_fmt' => function_exists('mining_fmt_pending')
                ? mining_fmt_pending($amount)
                : (string)$amount,
            'sync_at' => (int)mining_sync_ts($row['mining_sync_at'] ?? ''),
            'is_leader' => (bool)$is_leader,
            'lease_token' => $is_leader ? trim((string)$token) : '',
            'lease_active' => mining_sync_lease_active($row),
            'lease_sec' => (int)MINING_LEASE_SEC,
            'mining_tool' => $level,
            'upgrade_attempts' => $attempts,
            'upgrade_attempts_required' => $required,
            'yield_unlocked' => mining_yield_unlocked_for_attempts($attempts),
            'unlock_hint' => mining_yield_unlocked_for_attempts($attempts)
                ? ''
                : "강화 {$required}회 후 채굴냥 적립 (현재 {$attempts}/{$required})",
        ], $rate);
    }
}

if (!function_exists('mining_sync_init_sync_at')) {
    function mining_sync_init_sync_at($nick_esc) {
        $tbl = mining_sync_table();
        @db_query("
            UPDATE `{$tbl}`
            SET mining_sync_at = NOW()
            WHERE nick = '{$nick_esc}'
              AND (mining_sync_at IS NULL OR mining_sync_at = '0000-00-00 00:00:00')
        ");
    }
}

if (!function_exists('mining_sync_flush')) {
    /**
     * lease 보유 중 경과분을 pending에 반영 후 sync_at 갱신
     * @return array|null 갱신된 row
     */
    function mining_sync_flush($nick, $token) {
        mining_data_ensure_table();
        $nick_esc = mining_data_nick_esc($nick);
        $token = trim((string)$token);
        if ($nick_esc === '' || $token === '') {
            return null;
        }

        $row = mining_sync_row($nick);
        if (!$row || !mining_sync_lease_owner($row, $token)) {
            return mining_sync_commit_elapsed($nick);
        }

        $pending = mining_sync_display_amount($row, $nick);
        $pending_sql = mining_pending_sql($pending);
        $token_esc = addslashes($token);
        $lease_sec = (int)MINING_LEASE_SEC;
        $tbl = mining_sync_table();

        db_query("
            UPDATE `{$tbl}`
            SET mining_pending = {$pending_sql},
                mining_sync_at = NOW(),
                mining_lease_token = '{$token_esc}',
                mining_lease_until = DATE_ADD(NOW(), INTERVAL {$lease_sec} SECOND)
            WHERE nick = '{$nick_esc}'
              AND mining_lease_token = '{$token_esc}'
            LIMIT 1
        ");

        return mining_sync_row($nick);
    }
}

if (!function_exists('mining_sync_acquire')) {
    function mining_sync_acquire($nick) {
        mining_data_ensure_table();
        $nick_esc = mining_data_nick_esc($nick);
        if ($nick_esc === '') {
            return ['ok' => false, 'data' => '회원 정보가 없습니다.'];
        }
        if (!mining_data_ensure_row($nick)) {
            return ['ok' => false, 'data' => '회원을 찾을 수 없습니다.'];
        }

        mining_sync_init_sync_at($nick_esc);
        $row = mining_sync_row($nick);
        if (!$row) {
            return ['ok' => false, 'data' => '회원을 찾을 수 없습니다.'];
        }

        if (mining_sync_lease_active($row)) {
            return [
                'ok' => true,
                'type' => 'sync',
                'active_elsewhere' => true,
                'sync' => mining_sync_payload($row, false, '', $nick),
            ];
        }

        $token = mining_sync_new_token();
        $token_esc = addslashes($token);
        $lease_sec = (int)MINING_LEASE_SEC;
        $tbl = mining_sync_table();
        db_query("
            UPDATE `{$tbl}`
            SET mining_lease_token = '{$token_esc}',
                mining_lease_until = DATE_ADD(NOW(), INTERVAL {$lease_sec} SECOND)
            WHERE nick = '{$nick_esc}'
              AND (mining_lease_until IS NULL OR mining_lease_until <= NOW())
            LIMIT 1
        ");

        global $conn;
        $got = ($conn instanceof mysqli) ? ((int)mysqli_affected_rows($conn) > 0) : true;
        $row = mining_sync_row($nick);
        if (!$got || !$row || !mining_sync_lease_owner($row, $token)) {
            if ($row && mining_sync_lease_active($row)) {
                return [
                    'ok' => true,
                    'type' => 'sync',
                    'active_elsewhere' => true,
                    'sync' => mining_sync_payload($row, false, '', $nick),
                ];
            }
            return ['ok' => false, 'data' => '채굴 세션을 시작할 수 없습니다.'];
        }

        return [
            'ok' => true,
            'type' => 'sync',
            'active_elsewhere' => false,
            'sync' => mining_sync_payload($row, true, $token, $nick),
        ];
    }
}

if (!function_exists('mining_sync_ping')) {
    function mining_sync_ping($nick, $token) {
        mining_sync_commit_elapsed($nick);
        $row = mining_sync_row($nick);
        if (!$row) {
            return mining_sync_acquire($nick);
        }

        $token = trim((string)$token);
        if ($token !== '' && mining_sync_lease_owner($row, $token)) {
            $token_esc = addslashes($token);
            $lease_sec = (int)MINING_LEASE_SEC;
            $nick_esc = mining_data_nick_esc($nick);
            $tbl = mining_sync_table();
            db_query("
                UPDATE `{$tbl}`
                SET mining_lease_token = '{$token_esc}',
                    mining_lease_until = DATE_ADD(NOW(), INTERVAL {$lease_sec} SECOND)
                WHERE nick = '{$nick_esc}'
                  AND mining_lease_token = '{$token_esc}'
                LIMIT 1
            ");
            $row = mining_sync_row($nick);
            return [
                'ok' => true,
                'type' => 'sync',
                'active_elsewhere' => false,
                'sync' => mining_sync_payload($row, true, $token, $nick),
            ];
        }

        if (mining_sync_lease_active($row)) {
            return [
                'ok' => true,
                'type' => 'sync',
                'active_elsewhere' => true,
                'sync' => mining_sync_payload($row, false, '', $nick),
            ];
        }

        return mining_sync_acquire($nick);
    }
}

if (!function_exists('mining_sync_release')) {
    function mining_sync_release($nick, $token) {
        mining_sync_commit_elapsed($nick);

        $nick_esc = mining_data_nick_esc($nick);
        $tbl = mining_sync_table();
        db_query("
            UPDATE `{$tbl}`
            SET mining_lease_token = NULL,
                mining_lease_until = NULL
            WHERE nick = '{$nick_esc}'
            LIMIT 1
        ");
        $row = mining_sync_row($nick);

        return [
            'ok' => true,
            'type' => 'release',
            'sync' => $row ? mining_sync_payload($row, false, '', $nick) : null,
        ];
    }
}

if (!function_exists('mining_sync_read')) {
    function mining_sync_read($nick, $token = '') {
        $row = mining_sync_row($nick);
        if (!$row) {
            return ['ok' => false, 'data' => '회원을 찾을 수 없습니다.'];
        }
        $is_leader = mining_sync_lease_owner($row, $token);
        return [
            'ok' => true,
            'type' => 'sync',
            'active_elsewhere' => mining_sync_lease_active($row) && !$is_leader,
            'sync' => mining_sync_payload($row, $is_leader, $is_leader ? $token : '', $nick),
        ];
    }
}

if (!function_exists('mining_sync_claim_amount')) {
    /** commit 후 pending 전량 (본방 저장용) */
    function mining_sync_claim_amount($nick, $token = '') {
        $row = mining_sync_commit_elapsed($nick);
        if (!$row) {
            return ['ok' => false, 'data' => '회원을 찾을 수 없습니다.'];
        }
        return ['ok' => true, 'amount' => mining_pending_round($row['mining_pending'] ?? 0)];
    }
}

if (!function_exists('mining_pending_live_amount')) {
    /** offline 포함 현재 채굴량 (commit 반영) */
    function mining_pending_live_amount($nick): float {
        $row = mining_sync_commit_elapsed($nick);
        if (!$row) {
            return 0.0;
        }
        return mining_pending_round($row['mining_pending'] ?? 0);
    }
}

if (!function_exists('mining_pending_live_fmt')) {
    function mining_pending_live_fmt($nick): string {
        return mining_fmt_pending(mining_pending_live_amount($nick));
    }
}

if (!function_exists('mining_claim_to_newpoint')) {
    /**
     * 채굴량(mining_pending) → tb_member.newpoint(본방냥)
     * FOR UPDATE + 트랜잭션으로 동시 수령(이중 지급) 방지
     *
     * @param float|null $min_amount null이면 MINING_CHAT_CLAIM_MIN
     * @param bool $round_pay_to_int true면 본방냥 지급액을 정수 반올림 (홍보방 `.수령`)
     * @return array<string,mixed>
     */
    function mining_claim_to_newpoint($nick, $min_amount = null, $round_pay_to_int = false) {
        mining_data_ensure_table();

        $nick = trim((string)$nick);
        $nick_esc = mining_data_nick_esc($nick);
        if ($nick_esc === '') {
            return ['ok' => false, 'data' => '회원 정보를 확인할 수 없습니다.', 'claimed' => 0];
        }

        if (!mining_data_ensure_row($nick)) {
            return ['ok' => false, 'data' => '채굴 정보를 준비할 수 없습니다.', 'claimed' => 0];
        }

        if (!mining_yield_unlocked_for_nick($nick)) {
            $attempts = mining_upgrade_attempts_for_nick($nick);
            $required = mining_upgrade_attempts_required();
            return [
                'ok' => false,
                'data' => "채굴냥은 강화 {$required}회 후 수령할 수 있어요. (현재 {$attempts}/{$required})",
                'claimed' => 0,
            ];
        }

        $min = ($min_amount === null) ? (float)MINING_CHAT_CLAIM_MIN : (float)$min_amount;
        $zero_sql = mining_pending_sql(0.0);
        $tbl = mining_sync_table();
        $member_esc = addslashes($nick);

        global $conn;
        if (!($conn instanceof mysqli)) {
            return ['ok' => false, 'data' => 'DB 연결을 확인할 수 없습니다.', 'claimed' => 0];
        }

        $elapsed_sec = 0;
        $amount = 0.0;
        $pay_amount = 0.0;

        mysqli_begin_transaction($conn);
        try {
            $row = db_select("
                SELECT mining_tool, mining_pending, mining_sync_at,
                       IFNULL(mining_upgrade_attempts, 0) AS mining_upgrade_attempts
                FROM `{$tbl}`
                WHERE nick = '{$nick_esc}'
                LIMIT 1
                FOR UPDATE
            ");
            if (!$row) {
                throw new RuntimeException('회원을 찾을 수 없습니다.');
            }

            if (!mining_yield_unlocked_for_attempts((int)($row['mining_upgrade_attempts'] ?? 0))) {
                $attempts = (int)($row['mining_upgrade_attempts'] ?? 0);
                $required = mining_upgrade_attempts_required();
                throw new RuntimeException("채굴냥은 강화 {$required}회 후 수령할 수 있어요. (현재 {$attempts}/{$required})");
            }

            $sync_at_ts = mining_sync_ts($row['mining_sync_at'] ?? '');
            $elapsed_sec = max(0, mining_sync_now() - $sync_at_ts);
            $amount = mining_pending_round(mining_sync_display_amount($row, $nick));

            if ($amount <= 0) {
                throw new RuntimeException('수령할 채굴량이 없어요.');
            }
            if ($amount + 1e-12 < $min) {
                throw new RuntimeException(
                    mining_fmt_pending($min) . '냥 이상 모였을 때 수령할 수 있어요. (현재 ' . mining_fmt_pending($amount) . '냥)'
                );
            }

            $pay_amount = $round_pay_to_int ? (float)round($amount, 0) : $amount;
            if ($pay_amount <= 0) {
                throw new RuntimeException('수령할 채굴량이 없어요.');
            }

            $pay_sql = $round_pay_to_int
                ? (string)(int)$pay_amount
                : mining_pending_sql($pay_amount);

            db_query("
                UPDATE `{$tbl}`
                SET mining_pending = {$zero_sql},
                    mining_sync_at = NOW()
                WHERE nick = '{$nick_esc}'
                LIMIT 1
            ");

            db_query("
                UPDATE tb_member
                SET newpoint = IFNULL(newpoint, 0) + {$pay_sql}
                WHERE name = '{$member_esc}'
                LIMIT 1
            ");
            $member_updated = ((int)mysqli_affected_rows($conn) > 0);
            if (!$member_updated) {
                throw new RuntimeException('회원 정보를 찾을 수 없어요.');
            }

            mysqli_commit($conn);
        } catch (RuntimeException $e) {
            mysqli_rollback($conn);
            $msg = $e->getMessage();
            $pending = ($amount > 0 && $amount + 1e-12 < $min) ? $amount : 0.0;
            $out = ['ok' => false, 'data' => $msg, 'claimed' => 0];
            if ($pending > 0) {
                $out['pending'] = $pending;
            }
            return $out;
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            return ['ok' => false, 'data' => '수령 처리에 실패했어요.', 'claimed' => 0];
        }

        if ($elapsed_sec > 0) {
            // 광물 spawn 은 _auto_mining_ore.php 크론 전담
        }

        if (function_exists('지급로그')) {
            지급로그('채굴', $nick, '', 0, $pay_amount);
        }

        $fresh = db_select("SELECT newpoint FROM tb_member WHERE name = '{$member_esc}' LIMIT 1");
        $newpoint = (float)($fresh['newpoint'] ?? 0);
        $claimed_fmt = $round_pay_to_int
            ? number_format((int)$pay_amount)
            : mining_fmt_pending($pay_amount);

        return [
            'ok' => true,
            'claimed' => $pay_amount,
            'claimed_fmt' => $claimed_fmt,
            'newpoint' => $newpoint,
            'data' => '채굴냥 ' . $claimed_fmt . '냥을 본방냥으로 수령했어요.',
        ];
    }
}

if (!function_exists('mining_sync_reset_pending')) {
    function mining_sync_reset_pending($nick) {
        $nick_esc = mining_data_nick_esc($nick);
        $tbl = mining_sync_table();
        db_query("
            UPDATE `{$tbl}`
            SET mining_pending = 0,
                mining_sync_at = NOW(),
                mining_lease_token = NULL,
                mining_lease_until = NULL
            WHERE nick = '{$nick_esc}'
            LIMIT 1
        ");
    }
}
