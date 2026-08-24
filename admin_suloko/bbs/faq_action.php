<?php
$s_type = "BBS";
require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";
require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_".$s_type.".php";

include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly.php"; // 권한체크

// 테이블명을 설정합니다.
$VAL = $_POST;
$S_table_name = $s_type;
$save_dir = $_SERVER['DOCUMENT_ROOT'].'/upload/'.$s_type;

if ($mode == 'insert') {
 $VAL['BBS_NO'] = $db->get_data_one("SELECT MAX(BBS_NO) FROM `".$S_table_name."`") + 1;
} else {
 $info = $db->get_data("SELECT * FROM `".$S_table_name."` WHERE BBS_NO='".$VAL['BBS_NO']."'");
}

$VAL['GUBUN'] = "FAQ";
$VAL['REPLY_DATE'] = "0000-00-00 00:00:00";
$VAL['REPLY_YN'] = "N";
$VAL['FWIDTH'] = 0;
$VAL['FWIDTH_TYPE'] = "%";
$VAL['PIN'] = 0;
$VAL['CONTENT'] = addslashes($VAL['content']);
$VAL['content'] = addslashes($VAL['content']);

$func_name = "F_".$s_type;

$func_name($VAL);

meta_go("./faq.html");
?>
