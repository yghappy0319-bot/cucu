<?php
include_once __DIR__ . "/../common.php";
include_once __DIR__ . "/../_chk.php";
$domain = "https://adm.luckybank.kr";
?>
<!DOCTYPE html>
<html lang="ko" data-textdirection="ltr" class="loading">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <meta name="description" content="무통장 입금 자동확인, 무통장 입금 자동화, 실시간 무통장 입금 자동확인 서비스, SMM마케팅 사이트 제작, 마케팅 자동화 패널">
    <meta name="keywords" content="무통장 입금 자동확인, 무통장 입금 자동화, 실시간 무통장 입금 자동확인 서비스, SMM마케팅 사이트 제작, 마케팅 자동화 패널">
    <meta name="author" content="LUCKYBANK">

    <meta property="og:url" content="<?=$domain?>">
     <meta property="og:title" content="무통장 입금 자동확인 서비스 - 럭키뱅크">
     <meta property="og:type" content="website">
     <meta property="og:image" content="<?=$domain?>/assets/img_share.png">
     <meta property="og:description" content="무통장 입금 자동확인, 무통장 입금 자동화, 실시간 무통장 입금 자동확인 서비스, SMM마케팅 사이트 제작, 마케팅 자동화 패널">

    <title>무통장 입금 자동확인 서비스 - 럭키뱅크</title>
    <link rel="apple-touch-icon" sizes="60x60" href="<?=$domain?>/app-assets/images/ico/apple-icon-60.png">
    <link rel="apple-touch-icon" sizes="76x76" href="<?=$domain?>/app-assets/images/ico/apple-icon-76.png">
    <link rel="apple-touch-icon" sizes="120x120" href="<?=$domain?>/app-assets/images/ico/apple-icon-120.png">
    <link rel="apple-touch-icon" sizes="152x152" href="<?=$domain?>/app-assets/images/ico/apple-icon-152.png">
    <link rel="shortcut icon" type="image/x-icon" href="<?=$domain?>/app-assets/images/ico/favicon.ico">
    <link rel="shortcut icon" type="image/png" href="<?=$domain?>/app-assets/images/ico/favicon-32.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-touch-fullscreen" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <!-- BEGIN VENDOR CSS-->
    <link rel="stylesheet" type="text/css" href="<?=$domain?>/app-assets/css/bootstrap.css">
    <!-- font icons-->
    <link rel="stylesheet" type="text/css" href="<?=$domain?>/app-assets/fonts/icomoon.css">
    <link rel="stylesheet" type="text/css" href="<?=$domain?>/app-assets/fonts/flag-icon-css/css/flag-icon.min.css">
    <link rel="stylesheet" type="text/css" href="<?=$domain?>/app-assets/vendors/css/extensions/pace.css">
    <!-- END VENDOR CSS-->
    <!-- BEGIN ROBUST CSS-->
    <link rel="stylesheet" type="text/css" href="<?=$domain?>/app-assets/css/bootstrap-extended.css">
    <link rel="stylesheet" type="text/css" href="<?=$domain?>/app-assets/css/app.css">
    <link rel="stylesheet" type="text/css" href="<?=$domain?>/app-assets/css/colors.css">
    <!-- END ROBUST CSS-->
    <!-- BEGIN Page Level CSS-->
    <link rel="stylesheet" type="text/css" href="<?=$domain?>/app-assets/css/core/menu/menu-types/vertical-menu.css">
    <link rel="stylesheet" type="text/css" href="<?=$domain?>/app-assets/css/core/menu/menu-types/vertical-overlay-menu.css">
    <link rel="stylesheet" type="text/css" href="<?=$domain?>/app-assets/css/pages/login-register.css">
    <!-- END Page Level CSS-->
    <!-- BEGIN Custom CSS-->
    <link rel="stylesheet" type="text/css" href="<?=$domain?>/assets/css/style.css">
    <!-- END Custom CSS-->
    <script>
      var login_id = "<?=$member['mb_id']?>";
    </script>
  </head>

  <?
  $uri = $_SERVER['REQUEST_URI']; //uri를 구합니다.
  // 'adm'이 경로에 포함되어 있는지 확인합니다.
  if (strpos($uri, '/adm/') !== false) {
      echo '<body data-open="click" data-menu="vertical-menu" data-col="2-columns" class="vertical-layout vertical-menu 2-columns  fixed-navbar">';
  } else {
      echo '<body data-open="click" data-menu="vertical-menu" data-col="1-column" class="vertical-layout vertical-menu 1-column  blank-page blank-page">';
  }
  ?>
