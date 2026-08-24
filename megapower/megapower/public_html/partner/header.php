<?php
include $_SERVER['DOCUMENT_ROOT']."/lib/function.php";
include $_SERVER['DOCUMENT_ROOT']."/lib/config.php";
include $_SERVER['DOCUMENT_ROOT']."/_chk.php";

if(!$파트너기능사용){
  echo "<script>alert('파트너 기능이 활성화 되어 있지 않습니다.'); location.href='/';</script>";
  exit;
}

if(!$member['code']){
  header("Location: /");
  exit;
}

?>
<!DOCTYPE html>
<html lang="ko">

<head>
  <meta charset="utf-8" />
  <link rel="apple-touch-icon" sizes="76x76" href="../assets/img/apple-icon.png">
  <link rel="icon" type="image/png" href="../assets/img/favicon.png">
  <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
  <title>
    메가파워월드 파트너
  </title>
  <meta content='width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0, shrink-to-fit=no' name='viewport' />
  <!--     Fonts and icons     -->
  <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700,200" rel="stylesheet" />
  <link href="https://maxcdn.bootstrapcdn.com/font-awesome/latest/css/font-awesome.min.css" rel="stylesheet">
  <!-- CSS Files -->
  <link href="./assets/css/bootstrap.min.css" rel="stylesheet" />
  <link href="./assets/css/paper-dashboard.css?v=2.0.1" rel="stylesheet" />
  <!-- CSS Just for demo purpose, don't include it in your project -->
  <link href="./assets/demo/demo.css" rel="stylesheet" />
</head>

<body class="">
  <div class="wrapper ">
    <div class="sidebar" data-color="white" data-active-color="danger">
      <div class="logo">

        <a href="/partner" class="simple-text logo-normal">
          PARTNER
          <!-- <div class="logo-image-big">
            <img src="../assets/img/logo-big.png">
          </div> -->
        </a>
      </div>
      <div class="sidebar-wrapper">
        <ul class="nav">
          <li >
            <a href="/partner">
              <i class="nc-icon nc-bank"></i>
              <p>홈</p>
            </a>
          </li>
          <li>
            <a href="/partner/member.html">
              <i class="nc-icon nc-single-02"></i>
              <p>회원관리</p>
            </a>
          </li>
          <li>
            <a href="/partner/point.html">
              <i class="nc-icon nc-money-coins"></i>
              <p>캐시충전내역</p>
            </a>
          </li>
          <li>
            <a href="/partner/exchange.html">
              <i class="nc-icon nc-refresh-69"></i>
              <p>환전</p>
            </a>
          </li>
          <li>
            <a href="/partner/daily.html">
              <i class="nc-icon nc-single-copy-04"></i>
              <p>일매출</p>
            </a>
          </li>
          <li>
            <a href="/partner/sales.html">
              <i class="nc-icon nc-single-copy-04"></i>
              <p>월매출</p>
            </a>
          </li>
          <li>
            <a href="/partner/chart.html">
              <i class="nc-icon nc-single-copy-04"></i>
              <p>추천인</p>
            </a>
          </li>
        </ul>
      </div>
    </div>

    <div class="main-panel">
      <!-- Navbar -->
      <nav class="navbar navbar-expand-lg navbar-absolute fixed-top navbar-transparent">
        <div class="container-fluid">
          <div class="navbar-wrapper">
            <div class="navbar-toggle">
              <button type="button" class="navbar-toggler">
                <span class="navbar-toggler-bar bar1"></span>
                <span class="navbar-toggler-bar bar2"></span>
                <span class="navbar-toggler-bar bar3"></span>
              </button>
            </div>
          </div>
          <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navigation" aria-controls="navigation-index" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-bar navbar-kebab"></span>
            <span class="navbar-toggler-bar navbar-kebab"></span>
            <span class="navbar-toggler-bar navbar-kebab"></span>
          </button>
          <div class="collapse navbar-collapse justify-content-end" id="navigation">

            <ul class="navbar-nav">
              <li class="nav-item">
                <a class="nav-link btn-rotate" href="/">
                  <i class="nc-icon nc-air-baloon"></i>
                  <p>
                    <span class="d-lg-none d-md-block">메가파워월드 메인으로</span>
                  </p>
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link btn-rotate" href="/member/logout.php">
                  <i class="nc-icon nc-button-power"></i>
                  <p>
                    <span class="d-lg-none d-md-block">로그아웃</span>
                  </p>
                </a>
              </li>

              <li class="nav-item">
                <a class="nav-link btn-rotate" href="javascript:;">
                  <i class="nc-icon nc-settings-gear-65"></i>
                  <p>
                    <span class="d-lg-none d-md-block">로그아웃</span>
                  </p>
                </a>
              </li>
            </ul>
          </div>
        </div>
      </nav>
