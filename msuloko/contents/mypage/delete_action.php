<?php
  require_once $_SERVER['DOCUMENT_ROOT']."/_common/session_start.php"; // session start
  require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";
  require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_MEMBER.php";
  require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_OUT_MEMBER.php";

  $VAL = $_POST;

  if ($M_login['user_id'] == '') {
    alert_print("로그인을 해주세요.");
    history_go();
    exit;
  }

  if (trim($VAL['user_passwd']) == '') {
    alert_print("비밀번호를 입력해주세요.");
    history_go();
    exit;
  }

  $tmp = $db->get_data("
    SELECT *
    FROM
      MEMBER
    WHERE
      USER_ID='{$M_login['user_id']}' AND PASSWD=PASSWORD('{$VAL['user_passwd']}')
  ");

  if (!isset($tmp['USER_ID'])) {
    alert_print("정확한 비밀번호를 입력해주세요.");
    history_go();
    exit;
  }

  $out_member = $tmp;
  $out_member['mode']       = "insert";
  $out_member['MEMBER_NO']  = "";
  $out_member['REG_DATE']   = "";
  $out_member['LASTDATE']   = $tmp['REG_DATE'];
  $out_member['OUT_MEMO']   = stripslashes($VAL['user_content']);
  $out_member['PATNER_NO']  = ($tmp['PATNER_NO'] == "" ? 0 : $tmp['PATNER_NO']);
  F_OUT_MEMBER($out_member);

  $tmp['mode'] = "delete";
  F_MEMBER($tmp);

  // summary 처리
  exec_partner_summary($out_member['PARTNER_ID'], "REG_OUT");

  $M_login = null;
  session_register($M_login);
  $_SESSION['M_login'] = $M_login;

  // meta_go("./list.html?no=".$VAL['no']);
  alert_print("탈퇴 처리되었습니다.");
  meta_go("/");
?>
