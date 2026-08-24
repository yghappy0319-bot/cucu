<?php
/**
 * DB·공통 함수 로드 (DOCUMENT_ROOT 경로와 무관하게 동작)
 */
if (!function_exists('db_query')) {
  $libCandidates = [
    dirname(__DIR__) . '/lib/_function.php',
    '/home/kakao/public_html/lib/_function.php',
  ];
  if (!empty($_SERVER['DOCUMENT_ROOT'])) {
    $libCandidates[] = rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/') . '/lib/_function.php';
  }
  foreach ($libCandidates as $path) {
    if (is_file($path)) {
      include_once $path;
      break;
    }
  }
}

if (!function_exists('db_ensure_connection')) {
  function db_ensure_connection() {
    global $conn, $db_admin_host, $db_admin_user, $db_admin_pass, $db_admin_database;

    if (isset($conn) && $conn instanceof mysqli) {
      if (@mysqli_ping($conn)) {
        return true;
      }
      @mysqli_close($conn);
      $conn = null;
    }

    if (empty($db_admin_host)) {
      $siteCandidates = [
        dirname(__DIR__) . '/site_info.php',
        '/home/kakao/public_html/site_info.php',
      ];
      if (!empty($_SERVER['DOCUMENT_ROOT'])) {
        $siteCandidates[] = rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/') . '/site_info.php';
      }
      foreach ($siteCandidates as $site) {
        if (is_file($site)) {
          include_once $site;
          break;
        }
      }
    }

    if (empty($db_admin_host)) {
      return false;
    }

    $conn = @mysqli_connect($db_admin_host, $db_admin_user, $db_admin_pass, $db_admin_database);
    if ($conn instanceof mysqli) {
      mysqli_set_charset($conn, 'utf8mb4');
      return true;
    }

    return false;
  }
}

if (!function_exists('db_connection_error')) {
  function db_connection_error() {
    $err = function_exists('mysqli_connect_error') ? mysqli_connect_error() : '';
    return $err !== '' ? $err : 'mysqli_connect failed';
  }
}

db_ensure_connection();

if (!function_exists('nick_파라미터')) {
  include_once __DIR__ . '/function.php';
}
if (function_exists('tb_member_게임포기_컬럼_보장')) {
  tb_member_게임포기_컬럼_보장();
}
if (!function_exists('item_trade_log_기록')) {
  include_once __DIR__ . '/item_trade_log.inc.php';
}
