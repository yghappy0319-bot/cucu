<?php
/**
 * 창(윈도우) 단위 로그인 분리
 * - 브라우저 창마다 window.name 기반 _win 값을 붙여 서로 다른 로그인 상태를 유지
 * - $_SESSION['S_login_wins'][_win] 에 계정별 세션 저장
 * - _win 이 없으면 기존 $_SESSION['S_login'] 사용 (하위호환)
 */
function admin_session_win_id() {
  static $win = null;
  if ($win !== null) {
    return $win;
  }

  $raw = '';
  if (isset($_GET['_win'])) {
    $raw = $_GET['_win'];
  } else if (isset($_POST['_win'])) {
    $raw = $_POST['_win'];
  }

  $raw = preg_replace('/[^a-zA-Z0-9]/', '', (string)$raw);
  if (strlen($raw) > 32) {
    $raw = substr($raw, 0, 32);
  }
  $win = $raw;
  return $win;
}

function admin_get_session_login() {
  $win = admin_session_win_id();
  if ($win !== '') {
    if (isset($_SESSION['S_login_wins'][$win]) && is_array($_SESSION['S_login_wins'][$win])) {
      return $_SESSION['S_login_wins'][$win];
    }
    return null;
  }
  if (isset($_SESSION['S_login']) && is_array($_SESSION['S_login'])) {
    return $_SESSION['S_login'];
  }
  return null;
}

function admin_apply_session_login() {
  global $S_login;
  $S_login = admin_get_session_login();
}

function admin_save_session_login($login) {
  $win = admin_session_win_id();
  if ($win !== '') {
    if (!isset($_SESSION['S_login_wins']) || !is_array($_SESSION['S_login_wins'])) {
      $_SESSION['S_login_wins'] = array();
    }
    if ($login === null) {
      unset($_SESSION['S_login_wins'][$win]);
    } else {
      $_SESSION['S_login_wins'][$win] = $login;
    }
    return;
  }
  $_SESSION['S_login'] = $login;
}

function admin_url($url) {
  $win = admin_session_win_id();
  if ($win === '') {
    return $url;
  }
  if ($url === '' || $url === null) {
    return '/?_win='.urlencode($win);
  }
  if (preg_match('~^(https?:)?//~i', $url) || strpos($url, 'javascript:') === 0 || strpos($url, '#') === 0) {
    return $url;
  }
  if (preg_match('/(?:\?|&)_win=/', $url)) {
    return $url;
  }
  return $url.((strpos($url, '?') !== false) ? '&' : '?').'_win='.urlencode($win);
}

/**
 * 검색/리다이렉트로 _win 이 빠진 경우 window.name 으로 복구
 */
function admin_win_recover_page() {
  header('Content-Type: text/html; charset=UTF-8');
  echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>세션 복구</title>
<script>
(function () {
  var params = new URLSearchParams(location.search);
  var urlWin = (params.get("_win") || "").replace(/[^a-zA-Z0-9]/g, "").slice(0, 32);
  var namedWin = (window.name && window.name.indexOf("ADMIN_WIN_") === 0)
    ? window.name.replace(/^ADMIN_WIN_/, "")
    : "";
  var winId = namedWin || urlWin;
  if (!winId) {
    location.replace("/login.html");
    return;
  }
  window.name = "ADMIN_WIN_" + winId;
  params.set("_win", winId);
  location.replace(location.pathname + "?" + params.toString() + location.hash);
})();
</script>
</head><body>세션 복구중...</body></html>';
  exit;
}

/** @deprecated 하위호환용 */
function admin_session_slot() {
  return admin_session_win_id() !== '' ? 2 : 1;
}

/** @deprecated 하위호환용 */
function admin_session_login_key() {
  return 'S_login';
}
