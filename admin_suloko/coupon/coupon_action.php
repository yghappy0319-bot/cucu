<?php
  $s_type = "COUPON";
  require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";
  require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_".$s_type.".php";

  // 테이블명을 설정합니다.
  $VAL = $_POST;
  $S_table_name = $s_type;
  $NUM = $VAL['NUM'];
  $func_name = "F_".$s_type;
  if ($mode == "insert") {
    $cnt = $db->get_data("SELECT COUNT(*) AS CNT FROM COUPON WHERE NUM = '".$NUM."'");

    if ($cnt['cnt'] > 0) {
      alert_print("중복된 쿠폰번호입니다.");
      history_go();
      exit;
    }

    // $cnt = $db->get_data("SELECT MAX(NUM_CNT) AS NUM_CNT FROM COUPON WHERE NUM LIKE '".$NUM."%'");
    // if ($cnt['NUM_CNT'] == "") {
    //   $cnt['NUM_CNT'] = 0;
    // }
    // if ($cnt['NUM_CNT'] >= 99999999) {
    //   alert_print("대응하는 쿠폰번호는 이미 추가횟수가 없습니다.");
    //   history_go();
    //   exit;
    // }
    // $e = 99999999 - $cnt['NUM_CNT'];
    // $n = $e - $VAL['CNTS'];
    // if ($n < 0) {
    //   alert_print("대응하는 쿠폰번호는 추가횟수가 ".$e."회밖에 남지 않았습니다.");
    //   history_go();
    //   exit;
    // }

    if ($VAL['GUBUN'] == '') {
      $VAL['GUBUN'] = "O";
    }

    if ($VAL['GUBUN'] == "O") {
      $VAL['TIME_CNT'] = 1;
    }

    $VAL['ONOFF'] = "ON";

    for ($i = 1; $i <= $VAL['CNTS']; $i++) {
      $VAL['NUM_CNT'] = $cnt['NUM_CNT'] + $i;
      $len = 8 - strlen($VAL['NUM_CNT']);
      $num_cnt = $VAL['NUM_CNT'];
      for ($s = 0; $s < $len; $s++) {
        $num_cnt = "0".$num_cnt;
      }
      // $VAL['NUM'] = $VAL['NUM1'].$VAL['NUM2'].$num_cnt.str_rand(1, "0123456798");
      $func_name($VAL);
    }
  } else {
    $func_name($VAL);
  }
  meta_go("./coupon.html");
?>
