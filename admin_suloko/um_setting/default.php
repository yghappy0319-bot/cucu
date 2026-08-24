<?php
require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";
F_admin_chk($S_login);

$VAL = $_POST;

$RES = array("error" => true, "msg" => "");

if (trim($VAL['mode']) ==  "comm_policy_submit") {
  require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_UM_COMM_POLICY.php";

  $mode = is_numeric($VAL["IDX"]) ? "update" : "insert";

  if ($VAL["NAME"] == "") {
    $RES["msg"] = "커미션 그룹명을 입력해 주세요.";
    $RES["focus"] = "NAME";
    echo json_encode($RES);
    exit;
  }

  $add_query = "";
  if ($mode == "update") {
    $add_query = " AND IDX != {$VAL["IDX"]} ";
  }
  $chk = $db->get_data("SELECT COUNT(*) AS CNT FROM UM_COMM_POLICY WHERE NAME = '".trim($VAL["NAME"])."' {$add_query}");
  if ($chk["CNT"] > 0) {
    $RES["msg"] = "이미 사용중인 커미션 그룹명 입니다.";
    $RES["focus"] = "NAME";
    echo json_encode($RES);
    exit;
  }

  if ($VAL["FIRST_SALE_YN"] == "Y" && $VAL["FIRST_SALE"] == "") {
    $RES["msg"] = "첫결제 기준을 입력해 주세요.";
    $RES["focus"] = "FIRST_SALE";
    echo json_encode($RES);
    exit;
  }

  if ($VAL["MILEAGE_YN"] == "Y" && $VAL["MILEAGE"] == "") {
    $RES["msg"] = "지급할 마일리지를 입력해 주세요.";
    $RES["focus"] = "MILEAGE";
    echo json_encode($RES);
    exit;
  }

  if ($VAL["PAYMENT_RATE_YN"] == "Y" && $VAL["PAYMENT_RATE"] == "") {
    $RES["msg"] = "최소 결제율 기준을 입력해 주세요.";
    $RES["focus"] = "PAYMENT_RATE";
    echo json_encode($RES);
    exit;
  }

  if ($VAL["SUBSCRIBER_YN"] == "Y" && $VAL["SUBSCRIBER"] == "") {
    $RES["msg"] = "신규 가입자(누적) 기준을 입력해 주세요.";
    $RES["focus"] = "SUBSCRIBER";
    echo json_encode($RES);
    exit;
  }

  if ($VAL["AVERAGE_SUBSCRIBER_YN"] == "Y" && $VAL["AVERAGE_SUBSCRIBER"] == "") {
    $RES["msg"] = "하루 평균 신규 가입자 기준을 입력해 주세요.";
    $RES["focus"] = "AVERAGE_SUBSCRIBER";
    echo json_encode($RES);
    exit;
  }

  if ($VAL["AMOUNT_YN"] == "Y" && $VAL["AMOUNT"] == "") {
    $RES["msg"] = "신청 최소 금액 기준을 입력해 주세요.";
    $RES["focus"] = "AMOUNT";
    echo json_encode($RES);
    exit;
  }

  if (count($VAL["UNIT_PAYMENT_RATE"]) != count($VAL["UNIT_PRICE"])) {
    $RES["msg"] = "커미션 기준 설정에 오류가 있습니다.";
    echo json_encode($RES);
    exit;
  }

  for ($i = 0; $i < count($VAL["UNIT_PAYMENT_RATE"]); $i++) {
    if ($VAL["UNIT_PAYMENT_RATE"][$i] == "" || !is_numeric($VAL["UNIT_PAYMENT_RATE"][$i]) || $VAL["UNIT_PAYMENT_RATE"][$i] < 0) {
      $RES["msg"] = "커미션 기준은 숫자로 입력해 주세요. ({$VAL["UNIT_PAYMENT_RATE"][$i]})";
      echo json_encode($RES);
      exit;
    }
  }

  for ($i = 0; $i < count($VAL["UNIT_PRICE"]); $i++) {
    if ($VAL["UNIT_PRICE"][$i] == "" || !is_numeric($VAL["UNIT_PRICE"][$i]) || $VAL["UNIT_PRICE"][$i] < 0) {
      $RES["msg"] = "커미션 단가는 숫자로 입력해 주세요. ({$VAL["UNIT_PRICE"][$i]})";
      echo json_encode($RES);
      exit;
    }
  }


  //커미션 그룹 설정
  $policy = array();
  $policy['mode']               = $mode;
  $policy['IDX']                = trim($VAL["IDX"]);
  $policy['NAME']               = trim($VAL["NAME"]);
  $policy['STATUS']             = trim($VAL["STATUS"]);
  $policy['DEFAULT_YN']         = trim($VAL["DEFAULT_YN"]);
  $policy['FIRST_SALE']         = $VAL["FIRST_SALE_YN"] == "Y" ? trim($VAL["FIRST_SALE"]) : NULL;
  $policy['MILEAGE']            = $VAL["MILEAGE_YN"] == "Y" ? trim($VAL["MILEAGE"]) : NULL;
  $policy['PAYMENT_RATE']       = $VAL["PAYMENT_RATE_YN"] == "Y" ? trim($VAL["PAYMENT_RATE"]) : NULL;
  $policy['SUBSCRIBER']         = $VAL["SUBSCRIBER_YN"] == "Y" ? trim($VAL["SUBSCRIBER"]) : NULL;
  $policy['AVERAGE_SUBSCRIBER'] = $VAL["AVERAGE_SUBSCRIBER_YN"] == "Y" ? trim($VAL["AVERAGE_SUBSCRIBER"]) : NULL;
  $policy['AMOUNT']             = $VAL["AMOUNT_YN"] == "Y" ? trim($VAL["AMOUNT"]) : NULL;
  $policy['UNIT_IDX']           = $VAL["UNIT_IDX"];
  $policy['UNIT_PAYMENT_RATE']  = $VAL["UNIT_PAYMENT_RATE"];
  $policy['UNIT_PRICE']         = $VAL["UNIT_PRICE"];

  $result = F_UM_COMM_POLICY($policy);

  if ($result) {
    $RES["error"] = false;
    $RES["msg"]   = "커미션 설정을 저장하였습니다.";
    echo json_encode($RES);
  } else {
    $RES["msg"]   = "커미션 설정 저장 실패!!";
    echo json_encode($RES);
  }
  exit;

} else if (trim($VAL['mode']) ==  "all_policy_delete") {
  require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_UM_COMM_POLICY.php";

  if (count($VAL["chk_no"])==0) {
    $RES["msg"] = "삭제할 그룹을 선택해 주세요.";
    echo json_encode($RES);
    exit;
  }

  $chk = $db->get_data("SELECT COUNT(*) AS CNT FROM UM_COMM_POLICY WHERE DEFAULT_YN = 'Y' AND IDX IN (".implode(",",$VAL["chk_no"]).")");
  if ($chk["CNT"] > 0) {
    $RES["msg"] = "기본설정으로 사용중인 커미션 그룹은 삭제할 수 없습니다.";
    echo json_encode($RES);
    exit;
  }

  //커미션 그룹 설정
  $policy = array();
  $policy['mode'] = "delete";
  $policy['CHK_IDX']  = $VAL["chk_no"];

  $result = F_UM_COMM_POLICY($policy);

  if ($result) {
    $RES["error"] = false; // 성공처리
    $RES["msg"]   = "선택 그룹을 삭제 하였습니다.";
    echo json_encode($RES);
  } else {
    $RES["msg"]   = "선택 그룹 삭제 실패!!";
    echo json_encode($RES);
  }
  exit;
}
?>
