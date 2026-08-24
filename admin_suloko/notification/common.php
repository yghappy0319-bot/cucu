<?php
  require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";

  $VAL = $_POST;
  $RES = array("error" => False, "msg" => "");

  if ($VAL['mode'] == 'all_del') {
    include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly-json-1.php"; // 권한체크
    if ($VAL['gubun'] == '') {
      echo json_encode(array("error" => True, "msg" => "잘못된 접근입니다."));
      exit;
    }

    $s_type = $VAL['gubun'];
    $k_type = "TEMPLET";
    $func_name = "F_".$s_type;

    if ($s_type == 'SMSTEMPLET') {
      $k_type = "TEMPLET";
    }

    include $_SERVER['DOCUMENT_ROOT']."/_library/function_".$s_type.".php";

    if (count($chk_no) > 0) {
      for ($i = 0; $i < count($chk_no); $i++) {
        $VAL = array("mode" => "delete", $k_type."_NO" => $chk_no[$i]);
        $func_name($VAL);
      }
      echo json_encode(array("error" => False, "msg" => "삭제하였습니다."));
    } else {
      echo json_encode(array("error" => True, "msg" => "삭제할 항목이 없습니다."));
    }
  }
?>
