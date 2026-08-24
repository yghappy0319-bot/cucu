<?php
$page = array(
  "/member/join.html",
  "/member/login.html",
  "/member/idpass.html"
);

$current_page = $_SERVER["PHP_SELF"];
if (in_array($current_page, $page) && ($_SESSION['midx'] ?? 0) > 0) {
  header("Location: /");
  exit(); // 리다이렉션 후 스크립트를 종료합니다.
}
$nopage = array(
  "/member/mypage.html",
  "/member/info.html",
  "/member/buy.html",
  "/member/withdraw.html",
  "/member/passchk.html",
  "/member/ajax_temporary_password.php",
  "/new/ajax_new_pass_update.php",
  "/member/history.html",
  "/member/withdrawal.html"
);

$member = 로그인정보($_SESSION['midx'] ?? 0);

if(in_array($current_page, $nopage)){
  if(!isset($member['idx']) ){
    $_SESSION['new_pass'] = 0;
    // header("Location: /member/login.html");
    session_destroy();
    header("Location: /");
  }
}

$아이피 = $_SERVER['REMOTE_ADDR'];
$config = 사이트설정();
$포인트충전문자발송 = true;
$발송타입 = "sms"; // sms
$아이디찾기 = "바로찾기"; // 바로찾기 or 인증번호
