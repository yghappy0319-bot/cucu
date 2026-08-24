<?php
/**
 * 채굴 웹 버튼 매크로 의심 감지 — tb_member_mining.mining_upgrade_guard (JSON)
 */

require_once __DIR__ . '/mining_storage.inc.php';

if (!defined('MINING_UPGRADE_ANTIMACRO_MIN_MS')) {
    define('MINING_UPGRADE_ANTIMACRO_MIN_MS', 50);
}
if (!defined('MINING_UPGRADE_ANTIMACRO_RECORD_MS')) {
    define('MINING_UPGRADE_ANTIMACRO_RECORD_MS', 100);
}
if (!defined('MINING_UPGRADE_ANTIMACRO_BLOCK_SEC')) {
    define('MINING_UPGRADE_ANTIMACRO_BLOCK_SEC', 60);
}
if (!defined('MINING_UPGRADE_ANTIMACRO_SAMPLES')) {
    define('MINING_UPGRADE_ANTIMACRO_SAMPLES', 28);
}
if (!defined('MINING_UPGRADE_ANTIMACRO_MIN_SAMPLES')) {
    define('MINING_UPGRADE_ANTIMACRO_MIN_SAMPLES', 24);
}
if (!defined('MINING_UPGRADE_ANTIMACRO_STDDEV_MS')) {
    define('MINING_UPGRADE_ANTIMACRO_STDDEV_MS', 5);
}
if (!defined('MINING_UPGRADE_ANTIMACRO_REGULAR_MAX_MS')) {
    define('MINING_UPGRADE_ANTIMACRO_REGULAR_MAX_MS', 4000);
}
if (!defined('MINING_UPGRADE_ANTIMACRO_REGULAR_MIN_MS')) {
    define('MINING_UPGRADE_ANTIMACRO_REGULAR_MIN_MS', 80);
}

if (!function_exists('mining_action_antimacro_exempt')) {
    /** status/sync/release 등 폴링·세션 전용 */
    function mining_action_antimacro_exempt(string $action): bool {
        return in_array($action, ['status', 'sync', 'release'], true);
    }
}

if (!function_exists('mining_upgrade_antimacro_ensure')) {
    function mining_upgrade_antimacro_ensure(): void {
        static $done = false;
        if ($done || !function_exists('db_query')) {
            return;
        }
        $done = true;
        mining_data_ensure_table();
        $tbl = MINING_TABLE;
        $col = @db_select("SHOW COLUMNS FROM `{$tbl}` LIKE 'mining_upgrade_guard'");
        if (empty($col)) {
            @db_query("
                ALTER TABLE `{$tbl}`
                ADD COLUMN `mining_upgrade_guard` TEXT NULL
                COMMENT '채굴 버튼 매크로 감지 JSON'
                AFTER `mining_lease_until`
            ");
        }
    }
}

if (!function_exists('mining_upgrade_antimacro_default')) {
    function mining_upgrade_antimacro_default(): array {
        return [
            'last_ms' => 0,
            'intervals' => [],
            'token' => '',
            'blocked_until_ms' => 0,
            'strikes' => 0,
        ];
    }
}

if (!function_exists('mining_upgrade_antimacro_decode')) {
    function mining_upgrade_antimacro_decode($raw): array {
        $state = mining_upgrade_antimacro_default();
        if (!is_string($raw) || trim($raw) === '') {
            return $state;
        }
        $parsed = json_decode($raw, true);
        if (!is_array($parsed)) {
            return $state;
        }
        $state['last_ms'] = max(0, (int)($parsed['last_ms'] ?? 0));
        $state['blocked_until_ms'] = max(0, (int)($parsed['blocked_until_ms'] ?? 0));
        $state['token'] = trim((string)($parsed['token'] ?? ''));
        $state['strikes'] = max(0, (int)($parsed['strikes'] ?? 0));
        $intervals = [];
        if (!empty($parsed['intervals']) && is_array($parsed['intervals'])) {
            foreach ($parsed['intervals'] as $iv) {
                $iv = (int)$iv;
                if ($iv > 0) {
                    $intervals[] = $iv;
                }
            }
        }
        $max = max(1, (int)MINING_UPGRADE_ANTIMACRO_SAMPLES);
        if (count($intervals) > $max) {
            $intervals = array_slice($intervals, -$max);
        }
        $state['intervals'] = $intervals;
        return $state;
    }
}

if (!function_exists('mining_upgrade_antimacro_encode')) {
    function mining_upgrade_antimacro_encode(array $state): string {
        $payload = [
            'last_ms' => max(0, (int)($state['last_ms'] ?? 0)),
            'intervals' => array_values(array_map('intval', $state['intervals'] ?? [])),
            'token' => trim((string)($state['token'] ?? '')),
            'blocked_until_ms' => max(0, (int)($state['blocked_until_ms'] ?? 0)),
            'strikes' => max(0, (int)($state['strikes'] ?? 0)),
        ];
        $max = max(1, (int)MINING_UPGRADE_ANTIMACRO_SAMPLES);
        if (count($payload['intervals']) > $max) {
            $payload['intervals'] = array_slice($payload['intervals'], -$max);
        }
        return json_encode($payload, JSON_UNESCAPED_UNICODE);
    }
}

if (!function_exists('mining_upgrade_antimacro_load')) {
    function mining_upgrade_antimacro_load($nick): array {
        mining_upgrade_antimacro_ensure();
        $nick_esc = mining_data_nick_esc($nick);
        if ($nick_esc === '') {
            return mining_upgrade_antimacro_default();
        }
        mining_data_ensure_row($nick);
        $row = @db_select("SELECT mining_upgrade_guard FROM `" . MINING_TABLE . "` WHERE nick = '{$nick_esc}' LIMIT 1");
        return mining_upgrade_antimacro_decode($row['mining_upgrade_guard'] ?? '');
    }
}

if (!function_exists('mining_upgrade_antimacro_save')) {
    function mining_upgrade_antimacro_save($nick, array $state): bool {
        mining_upgrade_antimacro_ensure();
        $nick_esc = mining_data_nick_esc($nick);
        if ($nick_esc === '') {
            return false;
        }
        mining_data_ensure_row($nick);
        $json = addslashes(mining_upgrade_antimacro_encode($state));
        return (bool)@db_query("
            UPDATE `" . MINING_TABLE . "`
            SET mining_upgrade_guard = '{$json}'
            WHERE nick = '{$nick_esc}'
            LIMIT 1
        ");
    }
}

if (!function_exists('mining_upgrade_antimacro_now_ms')) {
    function mining_upgrade_antimacro_now_ms(): int {
        return (int)round(microtime(true) * 1000);
    }
}

if (!function_exists('mining_upgrade_antimacro_new_token')) {
    function mining_upgrade_antimacro_new_token(): string {
        try {
            return bin2hex(random_bytes(8));
        } catch (Throwable $e) {
            return md5(uniqid('mug', true));
        }
    }
}

if (!function_exists('mining_action_antimacro_block_msg')) {
    function mining_action_antimacro_block_msg(int $remain_sec): string {
        $remain_sec = max(1, $remain_sec);
        return "⏳ 의심 행동이 감지되어 1분간 채굴 버튼을 사용할 수 없어요. (남은 {$remain_sec}초)";
    }
}

if (!function_exists('mining_upgrade_antimacro_block_msg')) {
    function mining_upgrade_antimacro_block_msg(int $remain_sec): string {
        return mining_action_antimacro_block_msg($remain_sec);
    }
}

if (!function_exists('mining_action_antimacro_client_token')) {
    function mining_action_antimacro_client_token(): string {
        return trim((string)($_REQUEST['action_token'] ?? $_REQUEST['upgrade_token'] ?? ''));
    }
}

if (!function_exists('mining_action_antimacro_reject')) {
    /** 1분 차단 없이 해당 요청만 거절 (연타·토큰 경합 등) */
    function mining_action_antimacro_reject(array $state, string $message, string $reason = ''): array {
        $token = trim((string)($state['token'] ?? ''));
        $payload = [
            'ok' => false,
            'data' => $message,
            'action_token' => $token,
            'upgrade_token' => $token,
        ];
        if ($reason !== '') {
            $payload['action_reject_reason'] = $reason;
            $payload['upgrade_reject_reason'] = $reason;
        }
        return $payload;
    }
}

if (!function_exists('mining_action_antimacro_fail')) {
    function mining_action_antimacro_fail($nick, array $state, string $reason = 'macro'): array {
        $now = mining_upgrade_antimacro_now_ms();
        $block_ms = max(0, (int)($state['blocked_until_ms'] ?? 0));
        if ($block_ms <= $now) {
            $block_ms = $now + ((int)MINING_UPGRADE_ANTIMACRO_BLOCK_SEC * 1000);
            $state['blocked_until_ms'] = $block_ms;
            $state['strikes'] = min(99, (int)($state['strikes'] ?? 0) + 1);
        }
        mining_upgrade_antimacro_save($nick, $state);
        $remain = (int)ceil(($block_ms - $now) / 1000);
        return mining_action_antimacro_payload_blocked($state, $remain, $reason, false);
    }
}

if (!function_exists('mining_upgrade_antimacro_fail')) {
    function mining_upgrade_antimacro_fail($nick, array $state, string $reason = 'macro'): array {
        return mining_action_antimacro_fail($nick, $state, $reason);
    }
}

if (!function_exists('mining_upgrade_antimacro_is_regular')) {
    function mining_upgrade_antimacro_is_regular(array $intervals): bool {
        $need = max(2, (int)MINING_UPGRADE_ANTIMACRO_MIN_SAMPLES);
        if (count($intervals) < $need) {
            return false;
        }
        $slice = array_slice($intervals, -$need);
        $sum = 0;
        foreach ($slice as $v) {
            $sum += $v;
        }
        $avg = $sum / count($slice);
        if ($avg > (float)MINING_UPGRADE_ANTIMACRO_REGULAR_MAX_MS) {
            return false;
        }
        if ($avg < (float)MINING_UPGRADE_ANTIMACRO_REGULAR_MIN_MS) {
            return false;
        }
        $var = 0.0;
        foreach ($slice as $v) {
            $d = $v - $avg;
            $var += $d * $d;
        }
        $std = sqrt($var / count($slice));
        return $std <= (float)MINING_UPGRADE_ANTIMACRO_STDDEV_MS;
    }
}

if (!function_exists('mining_action_antimacro_payload_blocked')) {
    function mining_action_antimacro_payload_blocked(array $state, ?int $remain_sec = null, string $reason = '', bool $ok = false): array {
        $now = mining_upgrade_antimacro_now_ms();
        $until = (int)($state['blocked_until_ms'] ?? 0);
        if ($until <= $now) {
            return [];
        }
        if ($remain_sec === null) {
            $remain_sec = (int)ceil(($until - $now) / 1000);
        }
        $token = trim((string)($state['token'] ?? ''));
        $payload = [
            'ok' => $ok,
            'data' => mining_action_antimacro_block_msg($remain_sec),
            'action_blocked' => true,
            'upgrade_blocked' => true,
            'blocked_until_ms' => $until,
            'blocked_sec' => max(1, $remain_sec),
            'action_token' => $token,
            'upgrade_token' => $token,
        ];
        if ($reason !== '') {
            $payload['action_block_reason'] = $reason;
            $payload['upgrade_block_reason'] = $reason;
        }
        return $payload;
    }
}

if (!function_exists('mining_upgrade_antimacro_payload_blocked')) {
    function mining_upgrade_antimacro_payload_blocked(array $state): ?array {
        $payload = mining_action_antimacro_payload_blocked($state);
        return $payload ?: null;
    }
}

if (!function_exists('mining_action_antimacro_gate')) {
    /**
     * 채굴 조작 버튼 공통 검사 · 상태 갱신 · 새 토큰 발급
     * @return array{ok:bool,data?:string,action_token?:string,upgrade_token?:string,action_blocked?:bool,upgrade_blocked?:bool,blocked_until_ms?:int,blocked_sec?:int}
     */
    function mining_action_antimacro_gate($nick, string $client_token = ''): array {
        mining_upgrade_antimacro_ensure();
        $nick = trim((string)$nick);
        if ($nick === '') {
            return ['ok' => false, 'data' => '❌ 회원 정보를 확인할 수 없어요.'];
        }

        $state = mining_upgrade_antimacro_load($nick);
        $blocked = mining_upgrade_antimacro_payload_blocked($state);
        if ($blocked) {
            return $blocked;
        }

        $now = mining_upgrade_antimacro_now_ms();
        $last = (int)($state['last_ms'] ?? 0);
        $server_token = trim((string)($state['token'] ?? ''));
        $client_token = trim((string)$client_token);

        if ($last > 0 && $server_token !== '' && $client_token !== '') {
            if (!hash_equals($server_token, $client_token)) {
                return mining_action_antimacro_reject($state, '⏳ 처리 중이에요. 잠시 후 다시 시도해 주세요.', 'token');
            }
        }

        if ($last > 0) {
            $interval = $now - $last;
            if ($interval < (int)MINING_UPGRADE_ANTIMACRO_MIN_MS) {
                return mining_action_antimacro_reject($state, '⏳ 너무 빨라요. 잠시만 기다려 주세요.', 'fast');
            }
            if ($interval >= (int)MINING_UPGRADE_ANTIMACRO_RECORD_MS) {
                $intervals = $state['intervals'] ?? [];
                $intervals[] = $interval;
                $max = max(1, (int)MINING_UPGRADE_ANTIMACRO_SAMPLES);
                if (count($intervals) > $max) {
                    $intervals = array_slice($intervals, -$max);
                }
                $state['intervals'] = $intervals;
                if (mining_upgrade_antimacro_is_regular($intervals)) {
                    return mining_action_antimacro_fail($nick, $state, 'regular');
                }
            }
        }

        $state['last_ms'] = $now;
        $state['token'] = mining_upgrade_antimacro_new_token();
        mining_upgrade_antimacro_save($nick, $state);

        return [
            'ok' => true,
            'action_token' => $state['token'],
            'upgrade_token' => $state['token'],
        ];
    }
}

if (!function_exists('mining_upgrade_antimacro_gate')) {
    /** @deprecated mining_action_antimacro_gate() 사용 */
    function mining_upgrade_antimacro_gate($nick, ?int $times = null, string $client_token = ''): array {
        return mining_action_antimacro_gate($nick, $client_token);
    }
}

if (!function_exists('mining_action_antimacro_status')) {
    function mining_action_antimacro_status($nick): array {
        $state = mining_upgrade_antimacro_load($nick);
        $now = mining_upgrade_antimacro_now_ms();
        $until = (int)($state['blocked_until_ms'] ?? 0);
        $token = trim((string)($state['token'] ?? ''));
        if ($until <= $now) {
            return [
                'action_blocked' => false,
                'upgrade_blocked' => false,
                'blocked_until_ms' => 0,
                'blocked_sec' => 0,
                'action_token' => $token,
                'upgrade_token' => $token,
            ];
        }
        $remain = (int)ceil(($until - $now) / 1000);
        return [
            'action_blocked' => true,
            'upgrade_blocked' => true,
            'blocked_until_ms' => $until,
            'blocked_sec' => $remain,
            'action_token' => $token,
            'upgrade_token' => $token,
        ];
    }
}

if (!function_exists('mining_upgrade_antimacro_status')) {
    function mining_upgrade_antimacro_status($nick): array {
        return mining_action_antimacro_status($nick);
    }
}

if (!function_exists('mining_action_antimacro_attach')) {
    /** API 응답에 차단·토큰 필드 병합 */
    function mining_action_antimacro_attach(array $payload, $nick): array {
        return array_merge($payload, mining_action_antimacro_status($nick));
    }
}
