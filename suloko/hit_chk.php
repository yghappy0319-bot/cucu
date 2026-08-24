<?php
  session_start();
  include $_SERVER['DOCUMENT_ROOT']."/_common/config.php";

  $fild  = addslashes($_POST['fild']);
  $table = addslashes($_POST['table']);
  $no    = addslashes($_POST['no']);
  $num   = $no."1|";

  if (empty($table) || empty($no)) {
    exit;
  }

  $k = 0;

  if ($fild == '') {
    $fild = "NO";
  } else {
    $fild = $fild."_NO";
  }

  if ($_COOKIE[$table] != '') {
    if (!strstr($_COOKIE[$table], $num)) {
      $_COOKIE[$table] .= $num;
      $db->query("UPDATE ".$table." SET HIT=HIT+1 WHERE ".$fild."=$no");
    }
  } else {
    $_COOKIE[$table] .= $num;
    $db->query("UPDATE ".$table." SET HIT=HIT+1 WHERE ".$fild."=$no");

  }

  $date = date("Y-m-d")."23:59:59";
  setcookie($table, $_COOKIE[$table], strtotime($date));

  echo json_encode(array("error"=>"ok"));
?>
