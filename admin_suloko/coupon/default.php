<?php
  require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";

  $VAL = $_POST;

  if ($VAL['mode'] == 'all_del') {
    if ($VAL['gubun'] == '') {
      echo json_encode(array("error"=>0, "msg"=>"잘못된 접근입니다."));
      exit;
    }

    $s_type = $VAL['gubun'];
    $func_name = "F_".$s_type;

    include $_SERVER['DOCUMENT_ROOT']."/_library/function_".$s_type.".php";

    if (count($chk_no) > 0) {
      for ($i = 0; $i < count($chk_no); $i++) {
        $VAL = array("mode"=>"delete", $s_type."_NO"=>$chk_no[$i]);
        $info = $func_name(array("mode"=>"read", $s_type."_NO"=>$chk_no[$i]));

        for ($s= 1; $s < 10; $s++) {
          if ($info['file'.$s] == '') {
            continue;
          }

          $link = $_SERVER['DOCUMENT_ROOT']."/upload/".$s_type."/".$info['file'.$s];
          @unlink($link);
        }
        $func_name($VAL);
      }
      echo json_encode(array("error"=>1, "msg"=>"삭제하였습니다."));
    } else {
      echo json_encode(array("error"=>0, "msg"=>"삭제할 항목이 없습니다."));
    }
  }
?>
