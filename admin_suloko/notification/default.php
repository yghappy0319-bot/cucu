<?php
  // Table Name
  $s_type = "SMSTEMPLET";
  require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";
  require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_".$s_type.".php";

  include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly.php"; // 권한체크

  $VAL = $_POST;
  $S_table_name = $s_type;

  if ($mode == 'insert') {
    $VAL['TEMPLET_NO'] = $db->get_data_one("SELECT MAX(TEMPLET_NO) FROM {$S_table_name}") + 1;
  } else {
    $info = $db->get_data("SELECT * FROM {$S_table_name} WHERE TEMPLET_NO ='{$VAL['TEMPLET_NO']}'");
  }

  if ($VAL['STOPYN'] == '') {
    $VAL['STOPYN'] = 'N';
  }

  if ($VAL['CODESK'] == '') {
    $VAL['CODESK'] = 'S';
  }

  $VAL['CONTENT'] = stripslashes($VAL['CONTENT']);
  $VAL['CONTENT'] = addslashes($VAL['CONTENT']);
  $VAL['SUBJECT'] = addslashes($VAL['SUBJECT']);

  $func_name	= "F_".$s_type;
  $func_name($VAL);
  meta_go("./templat.html");
?>
