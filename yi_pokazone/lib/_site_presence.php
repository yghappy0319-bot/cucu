<?php
/**
 * 사이트 실시간 접속자 — 세션별 마지막 활동 시각 기록
 */

const SITE_PRESENCE_IDLE_MINUTES = 5;
const SITE_PRESENCE_PING_SEC     = 60;

function site_presence_table_ready(): bool
{
    return db_table_exists('tb_site_presence');
}

function site_presence_should_track(): bool
{
    if (php_sapi_name() === 'cli') {
        return false;
    }
    if (defined('PZ_API_JSON') && PZ_API_JSON) {
        return false;
    }
    if (is_admin_login()) {
        return false;
    }
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    if ($uri !== '' && (strpos($uri, '/admin/') === 0 || strpos($uri, '/admin') === 0)) {
        return false;
    }
    if (strpos($uri, '/cron/') !== false) {
        return false;
    }

    return true;
}

function site_presence_maybe_ping(): void
{
    if (!site_presence_should_track() || !site_presence_table_ready()) {
        return;
    }
    $now  = time();
    $last = (int) ($_SESSION['pz_presence_ping'] ?? 0);
    if ($now - $last < SITE_PRESENCE_PING_SEC) {
        return;
    }
    $_SESSION['pz_presence_ping'] = $now;
    site_presence_ping();
}

function site_presence_ping(): void
{
    if (!site_presence_table_ready() || is_admin_login()) {
        return;
    }
    $sid = session_id();
    if ($sid === '') {
        return;
    }

    $mb     = login_member();
    $mb_idx = ($mb && (int) ($mb['mb_idx'] ?? 0) > 0) ? (int) $mb['mb_idx'] : null;
    $mb_sql = $mb_idx !== null ? (string) $mb_idx : 'NULL';
    $sid_esc = db_escape($sid);

    db_query("
        INSERT INTO tb_site_presence (sp_session_id, mb_idx, sp_last_seen_at)
        VALUES ('{$sid_esc}', {$mb_sql}, NOW())
        ON DUPLICATE KEY UPDATE
            mb_idx = {$mb_sql},
            sp_last_seen_at = NOW()
    ");

    if (mt_rand(1, 50) === 1) {
        site_presence_cleanup();
    }
}

function site_presence_cleanup(): void
{
    if (!site_presence_table_ready()) {
        return;
    }
    $keep = SITE_PRESENCE_IDLE_MINUTES * 3;
    db_query("DELETE FROM tb_site_presence WHERE sp_last_seen_at < DATE_SUB(NOW(), INTERVAL {$keep} MINUTE)");
}

/**
 * @return array{total:int, members:int, guests:int, ready:bool, idle_minutes:int}
 */
function site_presence_stats(int $idle_minutes = SITE_PRESENCE_IDLE_MINUTES): array
{
    $idle_minutes = max(1, min(30, $idle_minutes));
    $empty = [
        'total'        => 0,
        'members'      => 0,
        'guests'       => 0,
        'ready'        => false,
        'idle_minutes' => $idle_minutes,
    ];
    if (!site_presence_table_ready()) {
        return $empty;
    }

    $total = (int) db_result("
        SELECT COUNT(*)
        FROM tb_site_presence
        WHERE sp_last_seen_at >= DATE_SUB(NOW(), INTERVAL {$idle_minutes} MINUTE)
    ");
    $members = (int) db_result("
        SELECT COUNT(*)
        FROM tb_site_presence
        WHERE sp_last_seen_at >= DATE_SUB(NOW(), INTERVAL {$idle_minutes} MINUTE)
          AND mb_idx IS NOT NULL AND mb_idx > 0
    ");

    return [
        'total'        => $total,
        'members'      => $members,
        'guests'       => max(0, $total - $members),
        'ready'        => true,
        'idle_minutes' => $idle_minutes,
    ];
}
