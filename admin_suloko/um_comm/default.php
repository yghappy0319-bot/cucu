<?php
require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";
require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_UM_COMM.php";
F_admin_chk($S_login);

$VAL = $_POST;

$RES = array("error" => true, "msg" => "");

if (trim($VAL['mode']) ==  "all_comm_delete") {
  if (count($VAL["chk_no"])==0) {
    $RES["msg"] = "삭제할 정산신청건을 선택해 주세요.";
    echo json_encode($RES);
    exit;
  }

  // 삭제
  for ($i = 0; $i < count($VAL["chk_no"]); $i++) {
    $result = F_UM_COMM(array(
      "mode" => "delete",
      "IDX"  => $VAL["chk_no"][$i]
    ));
  }

  if ($result) {
    $RES["error"] = false; // 성공처리
    $RES["msg"] = "선택 정산요청건을 삭제 하였습니다.";
    echo json_encode($RES);
  } else {
    $RES["msg"] = "선택 정산요청건 삭제 실패!!";
    echo json_encode($RES);
  }
  exit;
} else if (trim($VAL['mode']) ==  "change_status") {
  if (($VAL["no"])=="") {
    $RES["msg"] = "필수 정보가 누락되었습니다.";
    echo json_encode($RES);
    exit;
  }

  $result = F_UM_COMM(array(
    "mode"   => "update",
    "STATUS" => $VAL["status"],
    "IDX"    => $VAL["no"]
  ));

  if ($result) {
    $RES["error"] = false; // 성공처리
    $RES["msg"] = "상태를 변경 하였습니다.";
    echo json_encode($RES);
  } else {
    $RES["msg"] = "상태 변경 실패!!";
    echo json_encode($RES);
  }
  exit;
}
