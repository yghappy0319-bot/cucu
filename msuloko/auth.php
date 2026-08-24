<?php

$blockedUserIds = [
  'test@naver.com', 'skavud123',
  'test@naver.com-', 'skavud123-',
  'angel777'
];

$session = $_SESSION["M_login"];

if (isset($session['user_id'])) {
  $userId = $session['user_id'];
  if (in_array($userId, $blockedUserIds)) {
    syslog(LOG_DEBUG, "{$userId} - Forbidden");
    header("HTTP/1.1 403 Forbidden");
    exit;
  }
}
?>
