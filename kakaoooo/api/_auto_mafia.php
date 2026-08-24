<?php
/**
 * 마피아 페이즈 틱 — 1분마다
 * crontab 예:
 *   * * * * * php /path/to/api/_auto_mafia.php >/dev/null 2>&1
 */
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/game/mafia.inc.php';

$r = mafia_틱();

if (function_exists('db_query')) {
    $adv = !empty($r['advanced']) ? '1' : '0';
    $phase = addslashes((string)($r['phase'] ?? ''));
    @db_query("INSERT INTO cron_log SET content = 'auto_mafia adv={$adv} phase={$phase}', regdate = NOW()");
}
