<?php
  require_once $_SERVER['DOCUMENT_ROOT']."/_common/session_start.php"; // session start
  include $_SERVER['DOCUMENT_ROOT']."/_common/config.php";

  $M_login = null;
  session_register($M_login);
  $_SESSION['M_login'] = $M_login;

  meta_go("/");
?>
