<?php
/**
 * 채굴 광물 — 관리자 회수 (비정상 획득분)
 */

require_once __DIR__ . '/mining_ore.inc.php';

if (!function_exists('mining_ore_admin_bootstrap')) {
    function mining_ore_admin_bootstrap(): void {
        if (!function_exists('db_select')) {
            include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
        }
        if (!function_exists('wallet_auth')) {
            if (!defined('WALLET_LIB_ONLY')) {
                define('WALLET_LIB_ONLY', true);
            }
            require_once __DIR__ . '/wallet_web.php';
        }
    }
}

if (!function_exists('mining_ore_admin_nick_candidates')) {
    /** @return list<string> */
    function mining_ore_admin_nick_candidates(string $nick): array {
        $nick = trim($nick);
        $out = [];
        $push = static function ($v) use (&$out) {
            $v = trim((string)$v);
            if ($v !== '' && !in_array($v, $out, true)) {
                $out[] = $v;
            }
        };
        $push($nick);
        if (function_exists('getTwoCharNick')) {
            $push(getTwoCharNick($nick));
        }
        return $out;
    }
}

if (!function_exists('mining_ore_admin_allowed_nicks')) {
    /** @return list<string> */
    function mining_ore_admin_allowed_nicks(): array {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }
        mining_ore_admin_bootstrap();
        $set = [];
        $add = static function ($nick) use (&$set) {
            foreach (mining_ore_admin_nick_candidates($nick) as $cand) {
                $set[$cand] = true;
            }
        };

        global $관리자1;
        if (!empty($관리자1) && is_array($관리자1)) {
            foreach ($관리자1 as $nick) {
                $add($nick);
            }
        }
        global $관리자;
        if (!empty($관리자) && is_array($관리자)) {
            foreach ($관리자 as $nick) {
                $add($nick);
            }
        }

        $rs = @db_query("SELECT name FROM tb_member WHERE admin = 1 ORDER BY name");
        if ($rs) {
            while ($row = db_fetch($rs)) {
                $add($row['name'] ?? '');
            }
        }

        $cache = array_keys($set);
        return $cache;
    }
}

if (!function_exists('mining_ore_admin_is_nick')) {
    function mining_ore_admin_is_nick(string $nick): bool {
        $cands = mining_ore_admin_nick_candidates($nick);
        if (empty($cands)) {
            return false;
        }
        foreach (mining_ore_admin_allowed_nicks() as $allowed) {
            if (in_array($allowed, $cands, true)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('mining_ore_admin_nick_from_auth')) {
    function mining_ore_admin_nick_from_auth(array $auth): string {
        if (!empty($auth['nick'])) {
            return trim((string)$auth['nick']);
        }
        return trim((string)($auth['name'] ?? ''));
    }
}

if (!function_exists('mining_ore_admin_resolve_member')) {
    /** @return ?array{row:array,nick:string,code:string} */
    function mining_ore_admin_resolve_member(string $code): ?array {
        mining_ore_admin_bootstrap();
        $code = trim($code);
        if ($code === '') {
            return null;
        }
        if (function_exists('wallet_member_refresh')) {
            $ref = wallet_member_refresh($code);
            if (!empty($ref['row']) && !empty($ref['nick'])) {
                return [
                    'row' => $ref['row'],
                    'nick' => trim((string)$ref['nick']),
                    'code' => trim((string)($ref['code'] ?? $code)),
                ];
            }
        }
        $auth = wallet_auth($code);
        if (empty($auth)) {
            return null;
        }
        $nick = mining_ore_admin_nick_from_auth($auth);
        if ($nick === '') {
            return null;
        }
        return ['row' => $auth, 'nick' => $nick, 'code' => $code];
    }
}

if (!function_exists('mining_ore_admin_member_from_code')) {
    function mining_ore_admin_member_from_code($code): ?array {
        $resolved = mining_ore_admin_resolve_member(trim((string)$code));
        if ($resolved === null || !mining_ore_admin_is_nick($resolved['nick'])) {
            return null;
        }
        return [
            'idx' => (int)($resolved['row']['idx'] ?? 0),
            'nick' => $resolved['nick'],
            'point' => (int)($resolved['row']['point'] ?? 0),
            'code' => $resolved['code'],
        ];
    }
}

if (!function_exists('mining_ore_admin_auth_state')) {
    /**
     * @return array{
     *   code:string,
     *   member:?array,
     *   admin:?array,
     *   is_admin:bool,
     *   deny_reason:string
     * }
     */
    function mining_ore_admin_auth_state(): array {
        mining_ore_admin_bootstrap();

        $code = mining_ore_admin_code_resolve();
        $member = null;
        $admin = null;
        $deny_reason = '';

        if ($code === '') {
            $deny_reason = 'code_required';
        } else {
            $resolved = mining_ore_admin_resolve_member($code);
            if ($resolved === null) {
                $deny_reason = 'invalid_code';
            } else {
                $member = $resolved['row'];
                $nick = $resolved['nick'];
                $code = $resolved['code'];
                if (!mining_ore_admin_is_nick($nick)) {
                    $deny_reason = 'not_admin';
                } else {
                    $admin = [
                        'idx' => (int)($member['idx'] ?? 0),
                        'nick' => $nick,
                        'point' => (int)($member['point'] ?? 0),
                        'code' => $code,
                    ];
                    if (function_exists('wallet_코드_쿠키_저장')) {
                        wallet_코드_쿠키_저장($code);
                    }
                }
            }
        }

        return [
            'code' => $code,
            'member' => $member,
            'admin' => $admin,
            'is_admin' => (bool)$admin,
            'deny_reason' => $deny_reason,
        ];
    }
}

if (!function_exists('mining_ore_admin_code_resolve')) {
    function mining_ore_admin_code_resolve(): string {
        if (!empty($GLOBALS['wallet_preauth']['code'])) {
            return trim((string)$GLOBALS['wallet_preauth']['code']);
        }
        mining_ore_admin_bootstrap();
        if (function_exists('wallet_code_candidates')) {
            foreach (wallet_code_candidates() as $candidate) {
                if (wallet_auth($candidate)) {
                    return $candidate;
                }
            }
        }
        return function_exists('wallet_코드_해석') ? wallet_코드_해석() : '';
    }
}

if (!function_exists('mining_ore_admin_hour_bucket')) {
    /** @deprecated 스케줄 정렬 버킷으로 대체 — 하위 호환 */
    function mining_ore_admin_hour_bucket($found_at): int {
        $ts = strtotime((string)$found_at);
        if ($ts <= 0) {
            return 0;
        }
        return (int)floor($ts / 3600) * 3600;
    }
}

if (!function_exists('mining_ore_admin_schedule_anchor_ts')) {
    /**
     * 크론 mining_ore_window_start 정렬 기준 (없으면 0 → 벽시계 정렬)
     */
    function mining_ore_admin_schedule_anchor_ts(string $nick): int {
        static $cache = [];
        $nick = trim($nick);
        if ($nick === '') {
            return 0;
        }
        if (array_key_exists($nick, $cache)) {
            return $cache[$nick];
        }
        $nick_esc = function_exists('mining_data_nick_esc')
            ? mining_data_nick_esc($nick)
            : addslashes($nick);
        if ($nick_esc === '') {
            return $cache[$nick] = 0;
        }
        $tbl = defined('MINING_TABLE') ? MINING_TABLE : 'tb_member_mining';
        $row = @db_select("
            SELECT mining_ore_window_start
            FROM `{$tbl}`
            WHERE nick = '{$nick_esc}'
            LIMIT 1
        ");
        $ts = strtotime((string)($row['mining_ore_window_start'] ?? ''));
        return $cache[$nick] = ($ts > 0 ? $ts : 0);
    }
}

if (!function_exists('mining_ore_admin_align_bucket')) {
    /**
     * 크론 고정 출현 창과 동일하게 found_at → bucket 정렬
     * (첫 발견 시각부터의 rolling 창이 아님 — 그 방식은 다음 창 초반 광물을 이전 창에 끌어와 오판정함)
     */
    function mining_ore_admin_align_bucket(int $found_ts, int $anchor_ts, int $window_sec): int {
        $window_sec = max(1, $window_sec);
        if ($found_ts <= 0) {
            return 0;
        }
        if ($anchor_ts > 0) {
            $delta = $found_ts - $anchor_ts;
            $steps = (int)floor($delta / $window_sec);
            return $anchor_ts + ($steps * $window_sec);
        }
        return (int)floor($found_ts / $window_sec) * $window_sec;
    }
}

if (!function_exists('mining_ore_admin_group_rolling_windows')) {
    /**
     * 스케줄 창 키(schedule_window_start) 기준 그룹
     * 구행(NULL)만 found_at → align_bucket fallback
     *
     * @param list<array> $items
     * @return list<array{nick:string,bucket:int,limit:int,items:list<array>}>
     */
    function mining_ore_admin_group_rolling_windows(array $items, array &$tool_level_cache = []): array {
        if (empty($items)) {
            return [];
        }
        usort($items, static function ($a, $b) {
            $ta = strtotime((string)($a['found_at'] ?? ''));
            $tb = strtotime((string)($b['found_at'] ?? ''));
            if ($ta === $tb) {
                return ((int)($a['idx'] ?? 0)) <=> ((int)($b['idx'] ?? 0));
            }
            return $ta <=> $tb;
        });

        $window_sec = function_exists('mining_ore_window_seconds')
            ? mining_ore_window_seconds()
            : 3600;
        $windows = [];
        foreach ($items as $item) {
            $ts = strtotime((string)($item['found_at'] ?? ''));
            if ($ts <= 0) {
                continue;
            }
            $nick = (string)($item['nick'] ?? '');
            $enh = max(0, (int)($item['weapon_enhance'] ?? 0));
            if ($nick !== '' && !isset($tool_level_cache[$nick])) {
                $tool_level_cache[$nick] = mining_tool_level_for_nick($nick);
            }
            $limit = (int)($item['hourly_limit'] ?? mining_ore_effective_hourly_find_count(
                $enh,
                (int)($tool_level_cache[$nick] ?? 0)
            ));

            $stored_win = trim((string)($item['schedule_window_start'] ?? ''));
            $stored_ts = $stored_win !== '' ? strtotime($stored_win) : 0;
            if ($stored_ts > 0) {
                $bucket = $stored_ts;
            } else {
                // 구행: 현재 스케줄 앵커로 fallback (오판정 가능 · 신규는 schedule_window_start 사용)
                $anchor = $nick !== '' ? mining_ore_admin_schedule_anchor_ts($nick) : 0;
                $bucket = mining_ore_admin_align_bucket($ts, $anchor, $window_sec);
            }
            $key = $nick . '|' . $bucket;

            if (!isset($windows[$key])) {
                $windows[$key] = [
                    'nick' => $nick,
                    'bucket' => $bucket,
                    'limit' => $limit,
                    'items' => [],
                ];
            }
            $windows[$key]['items'][] = $item;
            // 창 안 최고 허용량 사용 (강화/장비 변경 시 과도한 오판정 완화)
            if ($limit > (int)$windows[$key]['limit']) {
                $windows[$key]['limit'] = $limit;
            }
        }
        return array_values($windows);
    }
}

if (!function_exists('mining_ore_admin_scan')) {
    /**
     * @return array{
     *   pending:list<array>,
     *   claimed:list<array>,
     *   nick_summary:list<array>,
     *   stats:array
     * }
     */
    function mining_ore_admin_scan(int $claimed_days = 7): array {
        mining_ore_ensure_schema();
        if (function_exists('mining_ore_ensure_find_columns')) {
            mining_ore_ensure_find_columns();
        }
        $claimed_days = max(1, min(30, $claimed_days));

        $rs = @db_query("
            SELECT f.idx, f.nick, f.ore_key, f.qty, f.pending_value, f.weapon_item, f.weapon_enhance,
                   f.status, f.found_at, f.expire_at, f.claimed_at, f.schedule_window_start,
                   d.icon, d.label
            FROM tb_mining_ore_find f
            LEFT JOIN tb_mining_ore_def d ON d.ore_key = f.ore_key
            WHERE f.status = 'pending'
               OR (f.status = 'claimed' AND f.claimed_at >= DATE_SUB(NOW(), INTERVAL {$claimed_days} DAY))
            ORDER BY FIELD(f.status, 'pending', 'claimed'), f.nick ASC, f.found_at ASC, f.idx ASC
        ");

        $pending = [];
        $claimed = [];
        $all_items = [];
        $tool_level_cache = [];

        if ($rs) {
            while ($row = db_fetch($rs)) {
                $item = mining_ore_admin_row_payload($row, $tool_level_cache);
                $status = (string)($row['status'] ?? '');
                $all_items[] = $item;
                if ($status === 'claimed') {
                    $claimed[] = $item;
                } else {
                    $pending[] = $item;
                }
            }
        }

        $by_nick_items = [];
        foreach ($all_items as $item) {
            $nick = (string)($item['nick'] ?? '');
            if ($nick === '') {
                continue;
            }
            if (!isset($by_nick_items[$nick])) {
                $by_nick_items[$nick] = [];
            }
            $by_nick_items[$nick][] = $item;
        }
        $by_nick_bucket = [];
        foreach ($by_nick_items as $nick => $nick_items) {
            foreach (mining_ore_admin_group_rolling_windows($nick_items, $tool_level_cache) as $group) {
                $by_nick_bucket[$nick . '|' . $group['bucket']] = $group;
            }
        }

        $abnormal_ids = [];
        foreach ($by_nick_bucket as $group) {
            $limit = max(0, (int)$group['limit']);
            $items = $group['items'];
            if ($limit <= 0) {
                foreach ($items as $it) {
                    $abnormal_ids[(int)$it['idx']] = true;
                }
                continue;
            }
            if (count($items) <= $limit) {
                continue;
            }
            $excess = array_slice($items, $limit);
            foreach ($excess as $it) {
                $abnormal_ids[(int)$it['idx']] = true;
            }
        }

        foreach ($pending as $i => $item) {
            $pending[$i]['abnormal'] = !empty($abnormal_ids[(int)$item['idx']]);
        }
        foreach ($claimed as $i => $item) {
            $claimed[$i]['abnormal'] = !empty($abnormal_ids[(int)$item['idx']]);
        }

        $claimed_abnormal_total = 0;
        $nick_map = [];
        $touch_nick = static function (array $item, bool $is_claimed) use (&$nick_map, &$claimed_abnormal_total, &$tool_level_cache) {
            $nick = (string)$item['nick'];
            if (!isset($nick_map[$nick])) {
                $nick_map[$nick] = [
                    'nick' => $nick,
                    'pending_total' => 0,
                    'claimed_total' => 0,
                    'abnormal_total' => 0,
                    'claimed_abnormal' => 0,
                    'hourly_limit' => 0,
                ];
            }
            if ($is_claimed) {
                $nick_map[$nick]['claimed_total']++;
            } else {
                $nick_map[$nick]['pending_total']++;
            }
            if (!empty($item['abnormal'])) {
                $nick_map[$nick]['abnormal_total']++;
                if ($is_claimed) {
                    $nick_map[$nick]['claimed_abnormal']++;
                    $claimed_abnormal_total++;
                }
            }
            $nick_map[$nick]['hourly_limit'] = max(
                $nick_map[$nick]['hourly_limit'],
                mining_ore_effective_hourly_find_count(
                    (int)$item['weapon_enhance'],
                    (int)($tool_level_cache[$nick] ?? mining_tool_level_for_nick($nick))
                )
            );
        };
        foreach ($pending as $item) {
            $touch_nick($item, false);
        }
        foreach ($claimed as $item) {
            $touch_nick($item, true);
        }

        $nick_summary = array_values($nick_map);
        usort($nick_summary, static function ($a, $b) {
            return ($b['abnormal_total'] <=> $a['abnormal_total'])
                ?: ($b['claimed_abnormal'] <=> $a['claimed_abnormal'])
                ?: ($b['pending_total'] <=> $a['pending_total']);
        });

        return [
            'pending' => $pending,
            'claimed' => $claimed,
            'nick_summary' => $nick_summary,
            'stats' => [
                'pending_total' => count($pending),
                'abnormal_total' => count($abnormal_ids),
                'claimed_total' => count($claimed),
                'claimed_abnormal_total' => $claimed_abnormal_total,
                'nick_count' => count($nick_summary),
            ],
        ];
    }
}

if (!function_exists('mining_ore_admin_row_payload')) {
    function mining_ore_admin_row_payload(array $row, array &$tool_level_cache = []): array {
        $pending_value = (float)($row['pending_value'] ?? 0);
        $left_sec = 0;
        if (($row['status'] ?? '') === 'pending') {
            $left_sec = max(0, strtotime((string)($row['expire_at'] ?? '')) - time());
        }
        $nick = (string)($row['nick'] ?? '');
        if ($nick !== '' && !isset($tool_level_cache[$nick])) {
            $tool_level_cache[$nick] = mining_tool_level_for_nick($nick);
        }
        $tool_lv = (int)($tool_level_cache[$nick] ?? 0);
        return [
            'idx' => (int)($row['idx'] ?? 0),
            'nick' => $nick,
            'ore_key' => (string)($row['ore_key'] ?? ''),
            'icon' => (string)($row['icon'] ?? ''),
            'label' => (string)($row['label'] ?? ''),
            'qty' => max(1, (int)($row['qty'] ?? 1)),
            'pending_value' => $pending_value,
            'pending_value_fmt' => mining_fmt_ore_value($pending_value),
            'weapon_item' => (string)($row['weapon_item'] ?? ''),
            'weapon_enhance' => (int)($row['weapon_enhance'] ?? 0),
            'hourly_limit' => mining_ore_effective_hourly_find_count((int)($row['weapon_enhance'] ?? 0), $tool_lv),
            'status' => (string)($row['status'] ?? ''),
            'found_at' => (string)($row['found_at'] ?? ''),
            'schedule_window_start' => (string)($row['schedule_window_start'] ?? ''),
            'expire_at' => (string)($row['expire_at'] ?? ''),
            'claimed_at' => (string)($row['claimed_at'] ?? ''),
            'left_sec' => $left_sec,
            'left_fmt' => $left_sec > 0 ? gmdate($left_sec >= 3600 ? 'H:i:s' : 'i:s', $left_sec) : '만료',
            'abnormal' => false,
        ];
    }
}

if (!function_exists('mining_ore_admin_deduct_claimed_value')) {
    /**
     * 수령분 회수: mining_pending 우선 차감, 부족분은 tb_member.newpoint 차감
     *
     * @return array{from_pending:float,from_newpoint:float,memo_parts:list<string>}
     */
    function mining_ore_admin_deduct_claimed_value(string $nick, float $amount): array {
        $amount = mining_pending_round(max(0, (float)$amount));
        if ($amount <= 0) {
            return ['from_pending' => 0.0, 'from_newpoint' => 0.0, 'memo_parts' => []];
        }

        if (function_exists('mining_data_ensure_row')) {
            mining_data_ensure_row($nick);
        }

        $nick_esc = mining_data_nick_esc($nick);
        $tbl = MINING_TABLE;
        $mrow = db_select("
            SELECT IFNULL(mining_pending, 0) AS mining_pending
            FROM `{$tbl}`
            WHERE nick = '{$nick_esc}'
            LIMIT 1
        ");
        $pending = mining_pending_round((float)($mrow['mining_pending'] ?? 0));
        $from_pending = min($pending, $amount);
        $remain = mining_pending_round($amount - $from_pending);
        $from_newpoint = 0.0;

        if ($from_pending > 0) {
            $pend_sub_sql = mining_pending_sql($from_pending);
            db_query("
                UPDATE `{$tbl}`
                SET mining_pending = GREATEST(0, IFNULL(mining_pending, 0) - {$pend_sub_sql})
                WHERE nick = '{$nick_esc}'
                LIMIT 1
            ");
        }

        if ($remain > 0) {
            $from_newpoint = $remain;
            $np_sub_sql = mining_pending_sql($from_newpoint);
            db_query("
                UPDATE tb_member
                SET newpoint = IFNULL(newpoint, 0) - {$np_sub_sql}
                WHERE name = '{$nick_esc}'
                LIMIT 1
            ");
        }

        $memo_parts = [];
        if ($from_pending > 0) {
            $memo_parts[] = '채굴량 -' . mining_fmt_ore_value($from_pending);
        }
        if ($from_newpoint > 0) {
            $memo_parts[] = '본방냥 -' . mining_fmt_ore_value($from_newpoint);
        }

        return [
            'from_pending' => $from_pending,
            'from_newpoint' => $from_newpoint,
            'memo_parts' => $memo_parts,
        ];
    }
}

if (!function_exists('mining_ore_admin_revoke_find')) {
    /**
     * @return array{ok:bool,msg:string}
     */
    function mining_ore_admin_revoke_find(int $find_idx, string $admin_nick, bool $reverse_claimed = false): array {
        mining_ore_ensure_schema();
        $find_idx = (int)$find_idx;
        $admin_nick = trim($admin_nick);
        if ($find_idx < 1 || $admin_nick === '') {
            return ['ok' => false, 'msg' => '요청 정보가 올바르지 않습니다.'];
        }

        $row = mining_ore_find_row_by_idx($find_idx);
        if (empty($row)) {
            return ['ok' => false, 'msg' => '광물 기록을 찾을 수 없습니다.'];
        }

        $status = (string)($row['status'] ?? '');
        if ($status === 'expired') {
            return ['ok' => false, 'msg' => '이미 소멸 처리된 광물입니다.'];
        }
        if ($status === 'claimed' && !$reverse_claimed) {
            return ['ok' => false, 'msg' => '수령 완료 광물은 «수령분 회수» 옵션으로 실행하세요.'];
        }

        $nick = (string)($row['nick'] ?? '');
        $nick_esc = mining_data_nick_esc($nick);
        $ore_key = (string)($row['ore_key'] ?? '');
        $qty = max(1, (int)($row['qty'] ?? 1));
        $pending_add = (float)($row['pending_value'] ?? 0);
        $weapon_item = (string)($row['weapon_item'] ?? '');
        $weapon_enhance = (int)($row['weapon_enhance'] ?? 0);
        $memo = '관리자 회수(' . $admin_nick . ')';
        $deduct_detail = '';

        if ($status === 'claimed' && $reverse_claimed) {
            if ($ore_key === 'eunchong_shard') {
                if (function_exists('mining_data_ensure_row')) {
                    mining_data_ensure_row($nick);
                }
                $mrow = db_select("
                    SELECT IFNULL(mining_eunchong_shard, 0) AS shard
                    FROM `" . MINING_TABLE . "`
                    WHERE nick = '{$nick_esc}'
                    LIMIT 1
                ");
                $shard = max(0, (int)($mrow['shard'] ?? 0) - $qty);
                db_query("
                    UPDATE `" . MINING_TABLE . "`
                    SET mining_eunchong_shard = {$shard}
                    WHERE nick = '{$nick_esc}'
                    LIMIT 1
                ");
                $memo .= ' · 은총조각 -' . $qty;
            } elseif ($pending_add > 0) {
                $deduct = mining_ore_admin_deduct_claimed_value($nick, $pending_add);
                if (!empty($deduct['memo_parts'])) {
                    $memo .= ' · ' . implode(' · ', $deduct['memo_parts']);
                    $deduct_detail = ' (' . implode(', ', $deduct['memo_parts']) . ')';
                }
            }
        }

        global $conn;
        db_query("
            UPDATE tb_mining_ore_find
            SET status = 'expired'
            WHERE idx = {$find_idx}
              AND status IN ('pending', 'claimed')
            LIMIT 1
        ");
        $ok = ($conn instanceof mysqli) ? ((int)mysqli_affected_rows($conn) > 0) : true;
        if (!$ok) {
            return ['ok' => false, 'msg' => '회수 처리에 실패했습니다.'];
        }

        mining_ore_log_insert(
            $nick,
            $ore_key,
            'expired',
            $find_idx,
            $qty,
            $status === 'claimed' ? $pending_add : 0,
            $weapon_item,
            $weapon_enhance,
            null,
            $memo
        );

        $label = trim((string)($row['label'] ?? $ore_key));
        return [
            'ok' => true,
            'msg' => $nick . ' · ' . $label . ' #' . $find_idx . ' 회수 완료' . $deduct_detail,
        ];
    }
}

if (!function_exists('mining_ore_admin_revoke_batch')) {
    /**
     * @param list<int> $find_ids
     * @return array{ok:bool,msg:string,revoked:int,failed:int}
     */
    function mining_ore_admin_revoke_batch(array $find_ids, string $admin_nick, bool $reverse_claimed = false): array {
        $revoked = 0;
        $failed = 0;
        foreach ($find_ids as $id) {
            $res = mining_ore_admin_revoke_find((int)$id, $admin_nick, $reverse_claimed);
            if (!empty($res['ok'])) {
                $revoked++;
            } else {
                $failed++;
            }
        }
        return [
            'ok' => $revoked > 0,
            'msg' => "회수 {$revoked}건" . ($failed > 0 ? " · 실패 {$failed}건" : ''),
            'revoked' => $revoked,
            'failed' => $failed,
        ];
    }
}

if (!function_exists('mining_ore_admin_revoke_nick_abnormal')) {
    /** 닉당 1시간 창 초과·대기 초과분만 회수 */
    function mining_ore_admin_revoke_nick_abnormal(string $nick, string $admin_nick): array {
        $scan = mining_ore_admin_scan();
        $ids = [];
        foreach ($scan['pending'] as $item) {
            if (($item['nick'] ?? '') === $nick && !empty($item['abnormal'])) {
                $ids[] = (int)$item['idx'];
            }
        }
        if (empty($ids)) {
            return ['ok' => false, 'msg' => $nick . ' — 회수할 비정상 대기 광물이 없습니다.', 'revoked' => 0, 'failed' => 0];
        }
        return mining_ore_admin_revoke_batch($ids, $admin_nick, false);
    }
}

if (!function_exists('mining_ore_admin_revoke_all_claimed_abnormal')) {
    /** 최근 수령분 중 비정상 판정 건만 수령분 회수 */
    function mining_ore_admin_revoke_all_claimed_abnormal(string $admin_nick): array {
        $scan = mining_ore_admin_scan();
        $ids = [];
        foreach ($scan['claimed'] as $item) {
            if (!empty($item['abnormal'])) {
                $ids[] = (int)$item['idx'];
            }
        }
        if (empty($ids)) {
            return ['ok' => false, 'msg' => '회수할 비정상 수령 광물이 없습니다.', 'revoked' => 0, 'failed' => 0];
        }
        return mining_ore_admin_revoke_batch($ids, $admin_nick, true);
    }
}

if (!function_exists('mining_ore_admin_revoke_nick_all_pending')) {
    function mining_ore_admin_revoke_nick_all_pending(string $nick, string $admin_nick): array {
        mining_ore_ensure_schema();
        $nick_esc = mining_data_nick_esc($nick);
        if ($nick_esc === '') {
            return ['ok' => false, 'msg' => '닉네임이 올바르지 않습니다.', 'revoked' => 0, 'failed' => 0];
        }
        $rs = @db_query("
            SELECT idx FROM tb_mining_ore_find
            WHERE nick = '{$nick_esc}' AND status = 'pending'
            ORDER BY idx ASC
        ");
        $ids = [];
        if ($rs) {
            while ($row = db_fetch($rs)) {
                $ids[] = (int)($row['idx'] ?? 0);
            }
        }
        if (empty($ids)) {
            return ['ok' => false, 'msg' => $nick . ' — 대기 중 광물이 없습니다.', 'revoked' => 0, 'failed' => 0];
        }
        return mining_ore_admin_revoke_batch($ids, $admin_nick, false);
    }
}
