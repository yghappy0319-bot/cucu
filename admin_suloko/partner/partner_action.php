<?php
  $s_type = "PATNER";
  require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";
  require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_".$s_type.".php";

  //=>	테이블명을 설정합니다.
  $VAL = $_POST;
  $S_table_name = $s_type;

  if ($mode == 'insert') {
    $cnt = $db->get_data("SELECT COUNT(*) AS cnt FROM PATNER WHERE USER_ID='".$VAL['USER_ID']."'");

    if ($cnt['cnt'] > 0) {
      alert_print("아이디가 존재합니다.");
      history_go();
      exit;
    }
    $VAL['PATNER_NO'] = $db->get_data_one("SELECT MAX(PATNER_NO) FROM `".$S_table_name."`") + 1;
  } else {
    $cnt = $db->get_data("SELECT COUNT(*) AS cnt FROM PATNER WHERE USER_ID='".$VAL['USER_ID']."' AND PATNER_NO != '".$VAL['PATNER_NO']."'");

    if ($cnt['cnt'] > 0) {
      alert_print("아이디가 존재합니다.");
      history_go();
      exit;
    }
    $info = $db->get_data("SELECT * FROM `".$S_table_name."` WHERE PATNER_NO='".$VAL['PATNER_NO']."'");
  }

  $VAL['STOPYN'] = "N";
  $VAL['QRCODE'] = "";
  $VAL['LAN'] = "";
  $VAL['LOT'] = "";
  $VAL['SDATE'] = "";
  $VAL['EDATE'] = "";
  $VAL['JUMP1'] = "";
  $VAL['JUMP2'] = "";

  $func_name = "F_".$s_type;
  $VAL['USER_ID'] = trim($VAL['USER_ID']);
  $VAL['USER_ID'] = str_replace(" ", "", $VAL['USER_ID']);
  $func_name($VAL);

  /*if ($mode == 'insert' || $info['QRCODE'] == '') {
    $qrMsg = "https://m.bigheet.com/qr_login.php?agtNo=".$VAL['PATNER_NO'];


    require_once $_SERVER['DOCUMENT_ROOT']."/phpqrcode/qrcode_add.php";

    if ($qr_image_url != '') {
      $sql = "UPDATE PATNER SET QRCODE='".$qr_image_url."' WHERE PATNER_NO='".$VAL['PATNER_NO']."'";
      $db->query($sql);
    }
  }*/
  if ($mode == 'insert') {
    $type = "등록";
  } else {
    $type	=	"수정";
  }

  alert_print("파트너를 ".$type."하였습니다.");
  meta_go("./partner.html");
?>
