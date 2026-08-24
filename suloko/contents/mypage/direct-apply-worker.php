<?php
/**
 * CLI 전용: 문의 접수 후 이메일/텔레그램 발송 (FPM 없을 때 백그라운드 실행)
 * 사용: php direct-apply-worker.php /path/to/queue_file.json
 */
if (php_sapi_name() !== 'cli' || $argc < 2) {
  exit(1);
}

// CLI 실행 시 DOCUMENT_ROOT가 없으므로 반드시 설정 (config 로드용)
$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../..');
$_SERVER['HTTP_HOST'] = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'sulokolink.com';
$_SERVER['HTTP_USER_AGENT'] = 'CLI';

require_once $_SERVER['DOCUMENT_ROOT'] . '/_common/config.php';

$queue_file = $argv[1];
if (!is_file($queue_file)) {
  if (php_sapi_name() === 'cli') fwrite(STDERR, "Worker: queue file not found: " . $queue_file . "\n");
  exit(1);
}

$raw = file_get_contents($queue_file);
$data = json_decode($raw, true);
if (!$data || empty($data['telegram_message'])) {
  @unlink($queue_file);
  if (php_sapi_name() === 'cli') fwrite(STDERR, "Worker: invalid queue data\n");
  exit(1);
}

$user_id = $data['user_id'];
$subject = isset($data['subject']) ? $data['subject'] : '';
$content_plain = isset($data['content_plain']) ? $data['content_plain'] : '';
$telegram_message = $data['telegram_message'];

$mem = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID='" . addslashes($user_id) . "'");
$TEMPLET_NO = 5;
$EMAILTEMPLET = $db->get_data("SELECT * FROM EMAILTEMPLET WHERE TEMPLET_NO='" . (int)$TEMPLET_NO . "'");

if (is_array($EMAILTEMPLET)) {
  $to = "";
  $email_subject = $EMAILTEMPLET['SUBJECT'];
  $email_content = isset($EMAILTEMPLET['CONTENT']) ? $EMAILTEMPLET['CONTENT'] : '';
  $email_content = str_replace('{{문의내용}}', $content_plain, $email_content);
  $type = "2";
  $vendor_autoload = $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';
  if (is_file($vendor_autoload) && (!empty($EMAILTEMPLET['STOPYN']) && $EMAILTEMPLET['STOPYN'] == 'N') && $mem) {
    $sesClient = @initializeSesClient();
    if ($sesClient) {
      sendMail($sesClient, $fname, $fmail, $to, $email_subject, $email_content, $type, $mem['NAME'], $mem['MEMBER_NO'], $TEMPLET_NO);
    }
  }
}

$token = "8388467259:AAGlliJQbmaPs9FR-iZH9kH6JHO-cagklZ4";
sendTelegram("6777050656", $telegram_message, $token);
sendTelegram("7603862766", $telegram_message, $token);
sendTelegram("8075600950", $telegram_message, $token);
sendTelegram("6854091508", $telegram_message, $token);

@unlink($queue_file);
if (php_sapi_name() === 'cli') echo "OK Telegram sent\n";
