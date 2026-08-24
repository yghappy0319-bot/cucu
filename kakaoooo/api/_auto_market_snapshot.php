<?php
/**
 * 시세 기준 총량 스냅샷 갱신 (선택 · 참고용)
 *
 * 단소·상점·벌금 등 경제 시세는 실시간 SUM(시세기준_실시간_로드)을 쓰므로
 * 이 크론은 필수가 아님. config 스냅샷/로그용으로만 유지.
 *
 * crontab 예:
 * 0 * * * * /usr/bin/php /home/kakao/public_html/api/_auto_market_snapshot.php >> /home/kakao/logs/market_snapshot.log 2>&1
 *
 * 추석 등: $시세스냅샷_자동갱신_중지 / 시세스냅샷_자동갱신_중지 상수 true 이면 갱신 안 함.
 * 재개 후에도 관리방 `.스냅샷` 수동 갱신은 항상 가능.
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
// config 미로드 시 function.php 상수 시세스냅샷_자동갱신_중지 기본값 사용

if (function_exists('시세기준_스냅샷_자동갱신_중지인가') && 시세기준_스냅샷_자동갱신_중지인가()) {
  echo date('Y-m-d H:i:s') . " market_snapshot paused (manual .스냅샷 only)\n";
  exit(0);
}

$result = 시세기준_스냅샷_갱신(true);
$line = date('Y-m-d H:i:s')
  . ' market_snapshot ok='
  . (!empty($result['ok']) ? '1' : '0')
  . ' paused=' . (!empty($result['paused']) ? '1' : '0')
  . ' np=' . ($result['본방냥'] ?? 0)
  . ' pt=' . ($result['게임냥'] ?? 0)
  . ' at=' . ($result['갱신시각'] ?? '')
  . "\n";
echo $line;
