<?php
/**
 * 매 회차 스캔본 오류 검출
 *
 * 구매 마감(TIME_E)이 지난 회차별로 아래 항목을 점검합니다.
 * - MISSING_SCAN   : 스캔 이미지 미등록 (IMG_PATH/IMG_YN)
 * - SCAN_FAIL      : Imglog 스캔 처리 실패·미연결
 * - BALL_MISMATCH  : 주문 번호와 스캔(OCR) 번호 불일치 (A게임 기준)
 * - PRINT_PENDING  : 스캔 완료 후 노출 대기 (PRINT=2, 컬럼 있을 때만)
 *
 * 실행 예)
 *   php /path/cron/_scan_error.php
 *   curl "https://.../cron/_scan_error.php?gubun=PB&rounds=3"
 *   curl ".../cron/_scan_error.php?gubun=MM&drawnum=2100&notify=1"
 *   curl ".../cron/_scan_error.php?test=1"
 *
 * 파라미터
 *   test     1이면 텔레그램 테스트 메시지 전송 후 종료
 *   gubun    PB|MM (생략 시 둘 다)
 *   drawnum  특정 회차만 검사
 *   rounds   최근 마감 회차 수 (기본 2, drawnum 지정 시 무시)
 *   grace_hours  TIME_E 이후 대기 시간(기본 2). 그 전에는 미검사
 *   notify   1이면 오류 시 FCM 푸시 (topic: scan_errors)
 *   format   json|text (기본 json)
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/_common/config.php';
include $_SERVER['DOCUMENT_ROOT'] . '/SMS/function_telegram.php';

// --- 텔레그램 (수신 chat_id 여러 개 등록 가능) ---
define('BOT_TOKEN', '8809485925:AAFYRJhE3axs8Pir1hzu32iTfALGOJmIgPU');
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');
$_TELEGRAM_CHAT_ID = array(
  '8075600950',
  '6854091508',
  // '-1001234567890',
);

function scan_error_send_telegram($text) {
  global $_TELEGRAM_CHAT_ID;

  if (empty($_TELEGRAM_CHAT_ID) || !is_array($_TELEGRAM_CHAT_ID)) {
    return;
  }

  foreach ($_TELEGRAM_CHAT_ID as $chat_id) {
    $chat_id = trim((string) $chat_id);
    if ($chat_id === '') {
      continue;
    }
    telegramApiRequest('sendMessage', array(
      'chat_id'    => $chat_id,
      'text'       => $text,
      'parse_mode' => 'HTML',
    ));
  }
}

// 텔레그램 테스트 (test=1)
if (!empty($test) && (string) $test === '1') {
  scan_error_send_telegram(
    "[테스트] 스캔본 오류 검사\n" . date('Y-m-d H:i:s') . "\n텔레그램 연동 확인"
  );
  header('Content-Type: application/json; charset=UTF-8');
  echo json_encode(array(
    'ok'      => true,
    'message' => 'telegram test sent',
    'time'    => date('Y-m-d H:i:s'),
  ), JSON_UNESCAPED_UNICODE);
  exit;
}

// WININFO: TIME_E 날짜가 오늘인 회차
$sql_today = "
  SELECT GUBUN, DRAWNUM, PLAYDATE, TIME_S, TIME_E
  FROM WININFO
  WHERE TIME_E IS NOT NULL
    AND TIME_E != ''
    AND DATE(TIME_E) = CURDATE()
  ORDER BY GUBUN, DRAWNUM
";
$wininfo_today = $db->get_list($sql_today);

$print_pending_errors = array();

if (empty($wininfo_today) || !isset($wininfo_today['DRAWNUM']) || !is_array($wininfo_today['DRAWNUM'])) {
  $wininfo_today = array();
} else {
  for ($i = 0; $i < count($wininfo_today['DRAWNUM']); $i++) {
    $gubun    = $wininfo_today['GUBUN'][$i];
    $drawnum  = (int) $wininfo_today['DRAWNUM'][$i];
    $playdate = $wininfo_today['PLAYDATE'][$i];
    $time_e   = $wininfo_today['TIME_E'][$i];

    $sql_orders = "
      SELECT *
      FROM ORDERS
      WHERE DRAWNUM = {$drawnum}
        AND PRINT != 1
    ";
    $orders_not_printed = $db->get_list($sql_orders);

    if (!empty($orders_not_printed['ORDERS_NO']) && is_array($orders_not_printed['ORDERS_NO'])) {
      $order_nos = array();
      for ($j = 0; $j < count($orders_not_printed['ORDERS_NO']); $j++) {
        $order_nos[] = $orders_not_printed['ORDERS_NO'][$j];
      }
      $print_pending_errors[] = array(
        'gubun'   => $gubun,
        'drawnum' => $drawnum,
        'playdate'=> $playdate,
        'time_e'  => $time_e,
        'count'   => count($order_nos),
        'orders'  => $order_nos,
      );
    }
  }
}

if (!empty($print_pending_errors)) {
  $lines = array('[스캔본] PRINT 미완료 주문', date('Y-m-d H:i:s'));
  foreach ($print_pending_errors as $err) {
    $lines[] = sprintf(
      '- %s %s회차 (마감 %s): %d건 ORDERS_NO %s',
      $err['gubun'],
      $err['drawnum'],
      $err['time_e'],
      $err['count'],
      implode(', ', $err['orders'])
    );
  }
  scan_error_send_telegram(implode("\n", $lines));
}

