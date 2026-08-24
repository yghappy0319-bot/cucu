<?php
/**
 * 보스 레이드 진행 틱 — 1분마다 홍보방(tb_info2_alarm) 현황 알림 / 타임오버 처리
 *
 * crontab 예:
 *   * * * * * php /path/to/api/_auto_boss_raid.php >/dev/null 2>&1
 */
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/game/boss_raid.inc.php';

boss_raid_진행알림_틱();

if (function_exists('db_query')) {
    @db_query("INSERT INTO cron_log SET content = 'auto_boss_raid', regdate = NOW()");
}
