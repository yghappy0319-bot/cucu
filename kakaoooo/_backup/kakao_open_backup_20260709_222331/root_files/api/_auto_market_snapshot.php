<?php
/**
 * 시세 기준 총량 스냅샷 갱신 (크론 1시간)
 *
 * crontab 예:
 * 0 * * * * /usr/bin/php /home/kakao/public_html/api/_auto_market_snapshot.php >> /home/kakao/logs/market_snapshot.log 2>&1
 */
$rootCandidates = [
  dirname(__DIR__),
  '/home/kakao/public_html',
];
if (!empty($_SERVER['DOCUMENT_ROOT'])) {
  $rootCandidates[] = rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/');
}

$root = null;
foreach ($rootCandidates as $candidate) {
  if (is_file($candidate . '/lib/_function.php')) {
    $root = $candidate;
    break;
  }
}
if ($root === null) {
  fwrite(STDERR, "market_snapshot: lib/_function.php not found\n");
  exit(1);
}

include_once $root . '/lib/_function.php';
include_once $root . '/api/function.php';

$result = 시세기준_스냅샷_갱신(true);
$line = date('Y-m-d H:i:s')
  . ' market_snapshot ok='
  . (!empty($result['ok']) ? '1' : '0')
  . ' np=' . ($result['본방냥'] ?? 0)
  . ' pt=' . ($result['게임냥'] ?? 0)
  . ' at=' . ($result['갱신시각'] ?? '')
  . "\n";
echo $line;
