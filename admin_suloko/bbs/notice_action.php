<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$s_type = "BBS";require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";
require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_".$s_type.".php";

include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly.php"; // 권한체크
// 테이블명을 설정합니다.
$VAL          = $_POST;
$S_table_name = $s_type;
$save_dir     = $_SERVER['DOCUMENT_ROOT'].'/upload/'.$s_type;
$info         = array();

if ($mode == 'insert') {
  $VAL['BBS_NO'] = $db->get_data_one("SELECT MAX(BBS_NO) FROM `".$S_table_name."`") + 1;
} else {
  $info = $db->get_data("SELECT * FROM `".$S_table_name."` WHERE BBS_NO='".$VAL['BBS_NO']."'");
}

$VAL['GUBUN'] = "NOTICE";
$VAL['REPLY_DATE'] = "0000-00-00 00:00:00";
$VAL['REPLY_YN'] = "N";
$VAL['QNA_TYPE'] = "";
$VAL['REPLY'] = "";
$VAL['REPLY_NAME'] = "";
$VAL['HP'] = "";
$VAL['YUTUBE'] = "";
if (!isset($VAL['FILE1'])) $VAL['FILE1'] = "";
$VAL['FILE2'] = "";
$uniq = $VAL['BBS_NO'];
for ($i = 1; $i < 2; $i++) {
  $f        = $_FILES['file'.$i]['tmp_name'];
  $fn       = $_FILES['file'.$i]['name'];
  $fs       = $_FILES['file'.$i]['size'];
  $year_mon = date("Y-m");


  $pre_file = isset($info['FILE'.$i]) ? $info['FILE'.$i] : '';
  if ($fn != '' || $mode == 'delete') {
    $R_info[$i] = F_file_upload(array(
      'mode'          => $mode,
      'del_file_path' => $save_dir.'/'.$pre_file,
      'save_dir'      => $save_dir,
      'f'             => $f,
      'year_mon'      => $year_mon,
      'fn'            => $fn,
      'fs'            => $fs,
      'uniq'          => $uniq,
      'idx'           => $i,
      'max_size'      => 3000000,
      'thumnail'      => false,
      'thum_width'    => 132,
      'thum_height'   => 96,
      'thum_path'     => '',
      'pre_file'      => '',
    ));

    $VAL['FILE'.$i]          = $R_info[$i]['real'];
    $VAL['real_filename'.$i] = $R_info[$i]['down'];
  } else {
    $VAL['FILE'.$i]          = isset($info['FILE'.$i]) ? $info['FILE'.$i] : '';
    $VAL['real_filename'.$i] = isset($info['real_filename'.$i]) ? $info['real_filename'.$i] : '';
  }
}

$VAL['CONTENT'] = addslashes(isset($VAL['content']) ? $VAL['content'] : '');

if ($VAL['FWIDTH'] == '') {
  $VAL['FWIDTH'] = 0;
}

if ($VAL['FWIDTH_TYPE'] == '') {
  $VAL['FWIDTH_TYPE'] = "%";
}

if ($VAL['PIN'] == '') {
  $VAL['PIN'] = 0;
}

$func_name = "F_".$s_type;

$func_name($VAL);

meta_go("./notice.html");
?>
