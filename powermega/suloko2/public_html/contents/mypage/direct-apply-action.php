<?php
include $_SERVER['DOCUMENT_ROOT']."/_common/config.php";
include $_SERVER['DOCUMENT_ROOT']."/_library/function_BBS.php";

// 테이블명을 설정합니다.
$VAL      = $_POST;
$save_dir = $_SERVER['DOCUMENT_ROOT'].'/upload/qna';
$REFERER  = $_SERVER['HTTP_REFERER'];
$is_debug = !empty($_POST['debug']) || !empty($_GET['debug']);

function json_response($error, $msg, $url = '', $extra = []) {
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(array_merge(['error' => $error, 'msg' => $msg, 'url' => $url], $extra));
  exit;
}

if ($M_login['user_id'] == '') {
  json_response(true, '로그인이 필요합니다.', '/');
}

if ($M_login['user_id'] === 'scmabc99') {
  json_response(true, '문의 점검중 입니다.');
}

if (empty($REFERER)) {
  json_response(true, '잘못된 요청입니다.', '/');
}
if (empty($VAL['SUBJECT'])) {
  json_response(true, '제목을 입력해 주세요.');
}
if (empty($VAL['CONTENT'])) {
  json_response(true, '내용을 입력해 주세요.');
}
if (empty($VAL['QNA_TYPE'])) {
  json_response(true, '구분을 선택해 주세요.');
}

if (mb_strlen($VAL['SUBJECT'], 'UTF-8') > 50) {
  json_response(true, '제목은 50자 이내로 작성해 주세요.');
}

$VAL['mode']   = "insert";
$VAL['BBS_NO'] = $db->get_data_one("SELECT MAX(BBS_NO) FROM BBS") + 1;

$con = strip_tags($VAL['CONTENT']);

// INJECTION
$SUBJECT = xss_clean($VAL['SUBJECT']);
$CONTENT = xss_clean($VAL['CONTENT']);
$QNA_TYPE = xss_clean($VAL['QNA_TYPE']);

$VAL['in_ip']    = $ip_address;
$VAL['SUBJECT']  = addslashes($SUBJECT);
$VAL['CONTENT']  = addslashes($CONTENT);
$VAL['GUBUN']    = "QNA";
$VAL['REPLY_YN'] = "N";
$VAL['USER_ID']  = $M_login['user_id'];
$VAL['NAME']     = $M_login['name'];
F_BBS($VAL);

$telegram_message = "1:1문의(PC) {$QNA_TYPE}\n";
$telegram_message.= "{$M_login['user_id']} {$VAL['HP']}\n";
$telegram_message.= "-제목\n{$SUBJECT}\n";
$telegram_message.= "-내용\n{$SUBJECT}";

// 이메일/텔레그램은 항상 워커에서 전송 (FPM에서 요청 종료 후 스크립트가 끊겨도 전송 보장)
$queue_data = [
  'user_id' => $M_login['user_id'],
  'subject' => $SUBJECT,
  'content_plain' => $con,
  'qna_type' => $QNA_TYPE,
  'hp' => isset($VAL['HP']) ? $VAL['HP'] : '',
  'telegram_message' => $telegram_message
];
$json_data = json_encode($queue_data, JSON_UNESCAPED_UNICODE);

// 1) contents/mypage/queue 시도 → 2) 실패 시 upload/qna/queue → 3) 실패 시 시스템 임시폴더
$queue_file = null;
foreach ([__DIR__ . '/queue', $save_dir . '/queue', sys_get_temp_dir() . '/direct_apply_queue'] as $queue_dir) {
  if (!is_dir($queue_dir)) {
    @mkdir($queue_dir, 0755, true);
  }
  if (!is_dir($queue_dir) || !is_writable($queue_dir)) {
    continue;
  }
  $path = $queue_dir . '/qna_' . uniqid('', true) . '.json';
  if (@file_put_contents($path, $json_data) !== false) {
    $queue_file = $path;
    break;
  }
}

$df = (string)ini_get('disable_functions');
$exec_disabled = (defined('DIRECT_APPLY_FORCE_INLINE') && DIRECT_APPLY_FORCE_INLINE)
  || ($df !== '' && in_array('exec', array_map('trim', explode(',', $df)), true));

if ($queue_file !== null) {
  if ($exec_disabled) {
    // exec() 비활성화 시: 이 요청 안에서 바로 텔레그램/이메일 전송 (cron 불필요)
    $telegram_message = $queue_data['telegram_message'];
    foreach ($telegram_user_id as $telegram_uid) {
      sendTelegram($telegram_uid, $telegram_message, $telegram_token);
    }
    
    $mem = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID='" . addslashes($queue_data['user_id']) . "'");
    $EMAILTEMPLET = $db->get_data("SELECT * FROM EMAILTEMPLET WHERE TEMPLET_NO='5'");
    if ($EMAILTEMPLET && $EMAILTEMPLET['STOPYN'] == 'N' && $mem) {
      $vendor_autoload = $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';
      if (is_file($vendor_autoload)) {
        $sesClient = @initializeSesClient();
        if ($sesClient) {
          $email_content = str_replace('{{문의내용}}', $queue_data['content_plain'], $EMAILTEMPLET['CONTENT']);
          sendMail($sesClient, $fname, $fmail, '', $EMAILTEMPLET['SUBJECT'], $email_content, '2', $mem['NAME'], $mem['MEMBER_NO'], 5);
        }
      }
    }
    @unlink($queue_file);
  } else {
    // exec() 사용 가능: 워커를 백그라운드로 실행 (cron 불필요)
    $worker_path = __DIR__ . '/direct-apply-worker.php';
    $worker = escapeshellarg($worker_path);
    $queue_arg = escapeshellarg($queue_file);

    $php_bin = 'php';
    if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
      $try_paths = ['/usr/bin/php', '/usr/local/bin/php', 'php'];
      foreach ($try_paths as $p) {
        if ($p === 'php') {
          $php_bin = 'php';
          break;
        }
        if (is_executable($p)) {
          $php_bin = $p;
          break;
        }
      }
    }

    $cmd = "{$php_bin} {$worker} {$queue_arg}";
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
      pclose(popen("start /B {$cmd}", 'r'));
    } else {
      // 셸을 명시해 백그라운드(&)가 동작하도록 함
      @exec('/bin/sh -c ' . escapeshellarg($cmd . ' > /dev/null 2>&1 &'));
    }
  }
}

// 사용자에게 성공 응답 (반드시 출력해야 AJAX가 성공으로 처리함)
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
  'error' => false,
  'msg'   => '문의가 접수되었습니다.',
  'url'   => '/contents/cs/direct.html'
]);
?>
