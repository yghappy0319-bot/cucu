<?php
/**_________________________________________________
 * 추첨 영상 업데이트
 * - 12시부터 15시까지 매시 7분, 17분, 27분, 37분, 47분, 57분 / 일, 화, 수, 목, 토
 * _________________________________________________
*/
require_once $_SERVER['DOCUMENT_ROOT'].'/_common/config.php';

$db->query("insert into CRON_LOG set FILE = 'video', REG_DATE = now() ");

// Constants
define('MEGA_CHANNEL_ID', "UCOKAdrQ0sKR9H1hi88RmQkA");
define('POWER_CHANNEL_ID', "UCVIYpabA5_ec95kyEqq0uyw");
define('DEFAULT_URL', "https://www.youtube.com/watch?v=");

// 공통 사용 변수
$type = null;

$now      = new DateTime();
$week     = (int)$now->format('w');
$hour     = (int)$now->format('H');
$drawdate = $now->modify('-1 day')->format('Y-m-d');

// 수동 실행 여부 (브라우저: ?type=MM&drawdate=2026-02-27)
$pb_manual = false;
$mm_manual = false;
$is_browser_manual = false;

if (!empty($_GET['type']) && !empty($_GET['drawdate'])) {
  $manual_type = strtoupper(trim($_GET['type']));
  $manual_drawdate = trim($_GET['drawdate']);

  if (in_array($manual_type, ['MM', 'PB'], true) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $manual_drawdate)) {
    $type = $manual_type;
    $drawdate = $manual_drawdate;
    $is_browser_manual = true;

    if ($type === 'PB') {
      $pb_manual = true;
    } else {
      $mm_manual = true;
    }
  }
}

// 파워볼: 일요일, 화요일, 목요일, 12시부터 15시까지
if ($type === null && ((in_array($week, [0, 2, 4]) && ($hour >= 12 && $hour < 15)) || $pb_manual)) {
  $type = "PB";
}

// 메가밀리언: 수요일, 토요일, 12시부터 15시까지
if ($type === null && ((in_array($week, [3, 6]) && ($hour >= 12 && $hour < 15)) || $mm_manual)) {
  $type = "MM";
}

if ($type !== null) {
  syslog(LOG_DEBUG, "========== Lottery Video Start =========>");
  syslog(LOG_DEBUG, "");

  syslog(LOG_DEBUG, "----------------------");
  syslog(LOG_DEBUG, "playDate: {$drawdate}");
  syslog(LOG_DEBUG, "****week: {$week}");
  syslog(LOG_DEBUG, "****hour: {$hour}");
  syslog(LOG_DEBUG, "----------------------");
  syslog(LOG_DEBUG, "");

  $query = "SELECT WININFO_NO, PLAYDATE, YUTUBE FROM WININFO WHERE GUBUN='{$type}' AND PLAYDATE='{$drawdate}' LIMIT 1";
  $data = $db->get_data($query);

  if (empty($data)) {
    $message = "{$type} WININFO 없음 (PLAYDATE={$drawdate})";
    syslog(LOG_DEBUG, $message);
    if ($is_browser_manual) {
      echo $message;
    }
  } else if (!is_null($data["YUTUBE"]) && $data["YUTUBE"] !== '') {
    $message = "{$type} 업데이트 할 내용 없음 (이미 등록됨: {$data['YUTUBE']})";
    syslog(LOG_DEBUG, $message);
    if ($is_browser_manual) {
      echo $message;
    }
  } else {
    syslog(LOG_DEBUG, "{$type} API 시작");

    $channelId = ($type === "MM") ? MEGA_CHANNEL_ID : POWER_CHANNEL_ID;
    $videoList = getChannelVideoList($channelId);

    if ($type === "MM") {
      $updated = updateMegaMillion($videoList, $drawdate, $data['WININFO_NO']);
    } else if ($type === "PB") {
      $updated = updatePowerBall($videoList, $drawdate, $data['WININFO_NO']);
    }

    if (!empty($updated)) {
      $message = "{$type} 저장 성공: {$updated}";
      syslog(LOG_DEBUG, "{$type} 저장 성공");
    } else {
      $message = "{$type} 채널 영상에서 PLAYDATE={$drawdate} 와 일치하는 영상 없음";
      syslog(LOG_DEBUG, $message);
    }

    if ($is_browser_manual) {
      echo $message;
    }
  }

  syslog(LOG_DEBUG, "========== Lottery Video End =========>");
  syslog(LOG_DEBUG, "");
}

// 메가밀리언 업데이트
function updateMegaMillion($videoList, $drawdate, $winInfoNo) {
  global $db;

  foreach ($videoList->entry as $entry) {
    $videoTitle = (string) $entry->title;
    $playDate = parseMegaMillionPlayDate($videoTitle);

    if ($drawdate !== $playDate) {
      continue;
    }

    $videoId = explode(":", (string) $entry->id)[2];
    $youtubeUrl = DEFAULT_URL . $videoId;
    $query = "UPDATE WININFO SET YUTUBE='{$youtubeUrl}' WHERE WININFO_NO='{$winInfoNo}' AND GUBUN='MM' AND PLAYDATE='{$drawdate}'";
    $db->query($query);
    return $youtubeUrl;
  }

  return null;
}

// 파워볼 업데이트
function updatePowerBall($videoList, $drawdate, $winInfoNo) {
  global $db;

  foreach ($videoList->entry as $entry) {
    $videoTitle = (string) $entry->title;

    if (strpos($videoTitle, "DP") !== false) {
      continue;
    }

    $playDate = parsePowerBallPlayDate($videoTitle);

    if ($drawdate !== $playDate) {
      continue;
    }

    $videoId = explode(":", (string) $entry->id)[2];
    $youtubeUrl = DEFAULT_URL . $videoId;
    $query = "UPDATE WININFO SET YUTUBE='{$youtubeUrl}' WHERE WININFO_NO='{$winInfoNo}' AND GUBUN='PB' AND PLAYDATE='{$drawdate}'";
    $db->query($query);
    return $youtubeUrl;
  }

  return null;
}

function parseMegaMillionPlayDate($videoTitle) {
  if (preg_match('/MM(\d{2})(\d{2})(\d{4})/', $videoTitle, $matches)) {
    return $matches[3] . '-' . $matches[1] . '-' . $matches[2];
  }

  return null;
}

function parsePowerBallPlayDate($videoTitle) {
  if (preg_match('/(\d{2})-(\d{2})-(\d{4})$/', $videoTitle, $matches)) {
    return $matches[3] . '-' . $matches[1] . '-' . $matches[2];
  }

  return null;
}

// Youtube 채널의 영상 목록 가져오기
function getChannelVideoList($channelId) {
  $url = "https://www.youtube.com/feeds/videos.xml?channel_id={$channelId}";
  $curl_connection = curl_init($url);

  curl_setopt($curl_connection, CURLOPT_CONNECTTIMEOUT, 30);
  curl_setopt($curl_connection, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($curl_connection, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($curl_connection, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

  $result = curl_exec($curl_connection);
  curl_close($curl_connection);

  return simplexml_load_string($result);
}
