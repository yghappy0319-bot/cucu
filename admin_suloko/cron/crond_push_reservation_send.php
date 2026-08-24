<?php
/**_________________________________________________
 * 푸시 예약건 발송
 * - 1분 단위로 확인 및 발송 (crontab)
 * _________________________________________________
*/
require_once $_SERVER["DOCUMENT_ROOT"]."/_common/config.php";
require_once $_SERVER["DOCUMENT_ROOT"]."/_library/function_NOTIFICATION_LOG.php";

// 체크일시
$chkDateTime = date("Y-m-d H:i");

syslog(LOG_DEBUG, "========== Reservation Push Start =========>");
syslog(LOG_DEBUG, "");

syslog(LOG_DEBUG, "----------------------");
syslog(LOG_DEBUG, "Check Time: {$chkDateTime}");
syslog(LOG_DEBUG, "----------------------");
syslog(LOG_DEBUG, "");

define("MAX_RETRIES", 5); // 최대 재발송 횟수

$list = $db->get_list("SELECT * FROM NOTI_RESERVATION WHERE LEFT(RESERVED_AT, 16) = '{$chkDateTime}' AND STATUS = 'S'");

if ($list) {
  foreach ($list["IDX"] as $index => $idx) {
    $retries = 0;
    $isSuccess = "N";

    $topic = $list["TOPIC"][$index];
    $title = $list["TITLE"][$index];
    $content = $list["CONTENT"][$index];
    $type = $list["TYPE"][$index];

    // 푸시 알림 전송
    while ($retries <= MAX_RETRIES && $isSuccess === "N") {
      $result = processPush($idx, $topic, $title, $content, $ICON_URL, $ACTION_URL);
      $isSuccess = logPushResult($idx, $type, $topic, $title, $content, $ACTION_URL, $ICON_URL, $result);

      // 실패 시 재시도
      if ($isSuccess === 'N') {
        $retries++;
        handleRetry($idx, $retries);
      }
    }

    // 모든 재시도가 실패했을 경우 실패 처리
    if ($isSuccess === "N") {
      handleFailure();
    }
  }
}

syslog(LOG_DEBUG, "========== Reservation Push End =========>");
syslog(LOG_DEBUG, "");

// 푸시 알림 전송 함수
function processPush($idx, $topic, $title, $content, $iconUrl, $actionUrl) {
  updateStatus($idx, "P"); // 진행중

  // SEND PUSH
  $result = sendFcmPush($topic, $title, $content, $iconUrl, $actionUrl);
  syslog(LOG_DEBUG, "[Reservation PUSH] IDX = " . $idx . " => " . $result);

  updateStatus($idx, "E"); // 종료

  return $result;
}

// 상태 업데이트 함수
function updateStatus($idx, $status) {
  global $db;
  $db->query("UPDATE NOTI_RESERVATION SET STATUS = '{$status}' WHERE IDX = '{$idx}'");
}

// 푸시 알림 전송 결과 로그 함수
function logPushResult($idx, $type, $topic, $title, $content, $actionUrl, $iconUrl, $result) {
  $response = json_decode($result);

  if (is_null($response)) { // curl 오류
    $isSuccess = "N";
    $errorCode = "500";
    $message   = $result;
  } else if (isset($response->error)) {
    $isSuccess = "N";
    $errorCode = $response->error->code;
    $message   = $response->error->status;
  } else {
    $isSuccess = "Y";
    $errorCode = NULL;
    $message   = NULL;
  }

  $push_log = [
    "mode"          => "insert",
    "TYPE"          => $type,
    "TOPIC"         => $topic,
    "TITLE"         => $title,
    "CONTENT"       => $content,
    "URL"           => $actionUrl,
    "IMG_URL"       => $iconUrl,
    "IS_SUCCESS"    => $isSuccess,
    "ERROR_CODE"    => $errorCode,
    "ERROR_MESSAGE" => $message
  ];
  F_NOTIFICATION_LOG($push_log);

  return $isSuccess;
}

// 재시도 처리 함수
function handleRetry($idx, $retries) {
  if ($retries > MAX_RETRIES) {
    syslog(LOG_DEBUG, "[Reservation PUSH] IDX = " . $idx . " failed after " . MAX_RETRIES . " retries.");
  } else {
    sleep(3);
  }
}

// 실패 처리 함수
function handleFailure() {
  global $fromHP;

  $toArr = [];

  foreach ($toArr as $to) {
    $sms = curl_sms($to, $fromHP, "S", "", "", "", "푸시 예약 발송 실패", "웹 푸시 예약 발송 실패");
  }
}
