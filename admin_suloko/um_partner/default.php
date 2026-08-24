<?php
require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";
require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_UM_PARTNER.php";
F_admin_chk($S_login);

$VAL = $_POST;
$RES = array("error" => true, "msg" => "");

if (trim($VAL['mode']) ==  "search_partner_info") {
  if ($VAL['no'] == '') {
    $RES["msg"] = "필수값이 누락되었습니다.";
    echo json_encode($RES);
    exit;
  }

  $result = F_UM_PARTNER(array(
    "mode" => "read",
    "IDX"  => trim($VAL['no'])
  ));

  // URL 완료
  $result['PASS_BOOK_URL'] = $partner_domain."/static/user/".$result['PARTNER_ID']."/".$result['PASS_BOOK_URL'];
  $result['ID_CARD_URL']   = $partner_domain."/static/user/".$result['PARTNER_ID']."/".$result['ID_CARD_URL'];

  if ($result) {
    $RES["error"] = false;
    echo json_encode($result);
  } else {
    $RES["msg"] = "파트너 회원 정보 로딩 실패!!";
    echo json_encode($RES);
  }
  exit;

} else if (trim($VAL['mode']) ==  "update_partner_info") {
  if (trim($VAL['IDX']) == '' || trim($VAL['COMM_POLICY_IDX']) == '' || trim($VAL['STATUS']) == '') {
    $RES["msg"] = "필수값이 누락되었습니다.";
    echo json_encode($RES);
    exit;
  }

  $result = F_UM_PARTNER(array(
    "mode"             => "update",
    "IDX"              => trim($VAL['IDX']),
    "COMM_POLICY_IDX"  => trim($VAL['COMM_POLICY_IDX']),
    "NAME"             => trim($VAL['NAME']),
    "TEL"              => trim($VAL['TEL']),
    "EMAIL"            => trim($VAL['EMAIL']),
    "BANK_NAME"        => trim($VAL['BANK_NAME']),
    "BANK_ACCOUNT_NUM" => trim($VAL['BANK_ACCOUNT_NUM']),
    "STATUS"           => trim($VAL['STATUS']),
    "PASSWD"           => trim($VAL['PASSWD'])
  ));

  if ($result) {
    $RES["error"] = false;
    $RES["msg"] = "파트너 회원 정보를 저장하였습니다.";
    echo json_encode($RES);
  } else {
    $RES["msg"] = "파트너 회원 정보 저장 실패!!";
    echo json_encode($RES);
  }
  exit;
} else if (trim($VAL['mode']) ==  "delete_partner") {
  if (trim($VAL['no']) == '') {
    $RES["msg"] = "필수값이 누락되었습니다.";
    echo json_encode($RES);
    exit;
  }

  $result = F_UM_PARTNER(array(
    "mode" => "delete",
    "IDX"  => trim($VAL['no'])
  ));

  if ($result) {
    $RES["error"] = false;
    $RES["msg"] = "파트너 회원 정보를 삭제하였습니다.";
    echo json_encode($RES);
  } else {
    $RES["msg"] = "파트너 회원 정보 저장 실패!!";
    echo json_encode($RES);
  }
  exit;
}
?>
