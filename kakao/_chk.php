<?php
include_once "common.php";
include_once "config.php";
$page = array(
  "/page/register.php",
  "/page/pwfind.php",
  "/page/login.php"
);
$current_page = $_SERVER["PHP_SELF"];
if (in_array($current_page, $page) && $_SESSION['midx'] > 0) {
  header("Location: /adm");
  exit(); // 리다이렉션 후 스크립트를 종료합니다.
}

$nopage = array(
  "/adm/list.htm",
  "/adm/index.php",
  "/adm/sitelist.htm",
  "/adm/siteview.htm",
  "/adm/sitereg.htm",
  "/adm/list2.htm",
  "/adm/mypage.htm",
  "/adm/service.htm",
  "/adm/tax_reg.htm",
  "/adm/tax_mng.htm",
  "/adm/pay_mng.htm",
  "/adm/panel/list.html",
  "/adm/panel/about.htm",
  "/adm/panel/register.htm",
  "/adm/cache_charge.htm",
  "/adm/notice/list.htm",
  "/adm/notice/register.htm",
  "/adm/sms_send.htm"
);
//echo $_SESSION['midx'];
$member = 로그인정보($_SESSION['midx']);
//var_dump($member);
if(in_array($current_page, $nopage)){
  if(!isset($member['mb_no']) ){
    // header("Location: /member/login.html");
    session_destroy();
    header("Location: /page/login.php");
  }
}
