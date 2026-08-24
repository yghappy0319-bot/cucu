<?php
require_once $_SERVER['DOCUMENT_ROOT']."/_common/session_start.php"; // session start
require_once $_SERVER["DOCUMENT_ROOT"]."/_common/config.php";

$VAL = $_POST;
$RES = array("error" => False, "msg" => "");

if ($VAL["mode"] == "qna_user_read") {
  // 작성자 ID 가져오기
  $info = $db->get_data("SELECT USER_ID, REPLY_YN FROM BBS WHERE GUBUN = 'QNA' AND BBS_NO = '{$VAL["no"]}' LIMIT 1");

  // 작성자 본인이고 답변이 완료된 문의의 읽은 날짜 업데이트 (마지막 읽은 날짜로 처리됨)
  if ($info['USER_ID'] == $M_login['user_id'] && $info['REPLY_YN'] == 'Y') {
    $query = "UPDATE BBS SET USER_READ_DATE = NOW() WHERE GUBUN = 'QNA' AND BBS_NO = '{$VAL["no"]}'";
    $db->query($query);
  }
  exit;
}
?>
