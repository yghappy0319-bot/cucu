<?php
$s_type = "GOODS";
require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";
require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_".$s_type.".php";

include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly.php"; // 권한체크

// 테이블명을 설정합니다.
$VAL = $_POST;
$S_table_name = $s_type;

if ($mode == 'insert') {
  $VAL['GOODS_NO'] = $db->get_data_one("SELECT MAX(GOODS_NO) FROM `".$S_table_name."`") + 1;
} else {
  $info = $db->get_data("SELECT * FROM `".$S_table_name."` WHERE GOODS_NO='".$VAL['GOODS_NO']."'");
}

if ($VAL['GUBUN'] == '') {
  $VAL['GUBUN'] = "PB";
}

$func_name = "F_".$s_type;

$func_name($VAL);

meta_go("./exchange.html");
?>
