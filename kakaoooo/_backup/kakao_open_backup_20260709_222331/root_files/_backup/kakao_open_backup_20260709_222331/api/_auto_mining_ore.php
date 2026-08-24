<?php
/**
 * 채굴 광물 크론 — 매 1분 권장
 *
 * 1) expire_at 지난 pending → expired
 * 2) 무기 장착 + 스케줄 없음 → seed
 * 3) mining_ore_next_spawn_at <= NOW() 인 row만 spawn
 *
 * crontab 예:
 *   * * * * * php /home/kakao/public_html/api/_auto_mining_ore.php >/dev/null 2>&1
 */

$root = dirname(__DIR__);
if (is_file($root . '/lib/_function.php')) {
    include_once $root . '/lib/_function.php';
} else {
    include_once '/home/kakao/public_html/lib/_function.php';
}
if (is_file($root . '/api/function.php')) {
    include_once $root . '/api/function.php';
} else {
    include_once '/home/kakao/public_html/api/function.php';
}

require_once __DIR__ . '/game/mining_ore_cron.inc.php';

$result = mining_ore_cron_run();

db_query("INSERT INTO cron_log SET content = 'auto_mining_ore', regdate = NOW()");

if (php_sapi_name() === 'cli' || !empty($_GET['debug'])) {
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
}
