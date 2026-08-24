<?php
/**
 * 워커 수동 실행 테스트 (브라우저 접속용)
 * 사용: /contents/mypage/queue/test-worker.php
 * - queue 폴더에 있는 .json 파일 하나를 골라 워커에 넘겨 실행합니다.
 * 확인 후 이 파일 삭제 권장.
 */
header('Content-Type: text/html; charset=utf-8');

$queue_dir = dirname(__DIR__) . '/queue';
$worker_path = dirname(__DIR__) . '/direct-apply-worker.php';

if (!is_dir($queue_dir)) {
  echo '<p>queue 폴더가 없습니다: ' . htmlspecialchars($queue_dir) . '</p>';
  exit;
}

$files = glob($queue_dir . '/qna_*.json');
if (empty($files)) {
  echo '<p>처리할 큐 파일이 없습니다. 문의를 한 건 등록한 뒤 다시 열어보세요.</p>';
  echo '<p>queue_dir: ' . htmlspecialchars($queue_dir) . '</p>';
  exit;
}

$queue_file = $files[0];
$php_bin = is_executable('/usr/bin/php') ? '/usr/bin/php' : 'php';
$cmd = $php_bin . ' ' . escapeshellarg($worker_path) . ' ' . escapeshellarg($queue_file);

echo '<h3>워커 수동 실행</h3>';
echo '<p>queue_file: ' . htmlspecialchars($queue_file) . '</p>';
echo '<p>실행 명령: <code>' . htmlspecialchars($cmd) . '</code></p>';

ob_start();
passthru($cmd, $code);
$out = ob_get_clean();

echo '<p>종료코드: ' . (int)$code . '</p>';
if ($out !== '') {
  echo '<pre>' . htmlspecialchars($out) . '</pre>';
}
echo '<p>위 명령을 서버 SSH에서 직접 실행해 보면 워커 오류를 확인할 수 있습니다.</p>';
echo '<p><small>확인 후 이 파일(test-worker.php)을 삭제하세요.</small></p>';
