<?php
  $s_type = "COUPON";
  require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";
  require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_".$s_type.".php";

  //=>  테이블명을 설정합니다.
  $VAL = $_POST;
  $S_table_name = $s_type;
  $save_dir = $_SERVER['DOCUMENT_ROOT'].'/upload/'.$s_type;

  $func_name = "F_".$s_type;

  $f = $_FILES['file1']['tmp_name'];
  $fn = $_FILES['file1']['name'];
  $fs = $_FILES['file1']['size'];
  $year_mon = date("Y-m");
  $pre_file = "";

  if ($fn != '') {
    $R_info[1] = F_file_upload(array(
      'mode' => $mode,
      'del_file_path' => $save_dir.'/'.$pre_file,
      'save_dir' => $save_dir,
      'f' => $f,
      'year_mon' => $year_mon,
      'fn' => $fn,
      'fs' => $fs,
      'uniq' => $uniq,
      'idx' => 1,
      'max_size' => 3000000,
      'thumnail' => false,
      'thum_width' => 132,
      'thum_height' => 96,
      'thum_path' => '',
      'pre_file' => '',
    ));
    $file_url = $save_dir."/".$R_info[1]['real'];
  } else {
    $file_url = '';
  }

  $file = fopen($file_url, "r");
  $k = 0;
  $n = 0;

  while (! feof($file)) {
    $NUM = fgets($file);
    trim($NUM);

    if ($NUM == '') {
      continue;
    }

    $NUM = str_replace(" ", "", $NUM);
    //$NUM = str_replace(array("\r", "\n", "\r\n"), '', $NUM);
    $NUM = str_replace(PHP_EOL, '', $NUM);

    if ($NUM == '') {
      continue;
    }

    $cnt = $db->get_data("SELECT COUNT(*) AS CNT FROM COUPON WHERE NUM = '".$NUM."'");

    if ($cnt['cnt'] > 0) {
      $n++;
      continue;
    }

    $VAL['GUBUN'] = "O";
    $VAL['TIME_CNT'] = 1;
    $VAL['NUM_CNT'] = 1;
    $VAL['ONOFF'] = "ON";
    $VAL['NUM'] = $NUM;
    $func_name($VAL);
    $k++;
  }

  alert_print($k."개 등록하고 ".$n."개 중복된 쿠폰입니다.");
  meta_go("./coupon.html");
?>
