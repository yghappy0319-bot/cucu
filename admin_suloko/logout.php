<?php
  require_once $_SERVER['DOCUMENT_ROOT']."/_common/session_slot.php";
  if (session_status() === PHP_SESSION_NONE) {
    session_start();
  }
  require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";

  admin_save_session_login(null);
  meta_go("/login.html");
?>
