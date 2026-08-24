<?php
  require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";
  //=>	테이블명을 설정합니다.
  $VAL = $_GET;

  $db->query("UPDATE PATNER SET STOPYN='N' WHERE PATNER_NO='".$VAL['no']."'");

  alert_print("파트너를 승인하였습니다.");
  meta_go("./partner.html");
?>
