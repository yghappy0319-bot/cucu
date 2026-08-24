<?php
  include $_SERVER['DOCUMENT_ROOT']."/_library/function_BBS.php";

  // 파트너 처리 - SLK-647 (cp_id가 있을경우만 함수호출로 변경)
  if (!empty($_GET['cp_id'])) {
    exec_partner($_GET['cp_id'] , 'PC');
    $domain = "https://".$_SERVER['HTTP_HOST']."/";
    meta_go($domain); //세션 처리후 cp_id 제거
    exit;
  }

  // 배너 컨트롤을 위한 토큰 체크
  $is_app_event_banner = $is_event_user = $is_event_user_b = false;
  if ($M_login['user_id']) {
    $is_token = $db->get_data_one("SELECT TOKEN FROM MEMBER WHERE USER_ID = '{$M_login['user_id']}' LIMIT 1");
    if ($is_token == "N") $is_app_event_banner = true;

    // SLK-1440 결제이벤트
    // SLK-1440 이벤트 참여마감시간 업데이트
    // $db->query("UPDATE MEMBER_EVENT_250207 SET EVENT_AT = DATE_ADD(NOW(), INTERVAL 3 DAY) WHERE USER_ID = '{$M_login['user_id']}' AND EVENT_AT IS NULL");
    $is_event_user = chk_event_user_a($M_login['user_id']);
    if ($is_event_user_b === false) $is_event_user_b = chk_event_user_b1($M_login['user_id']);
    if ($is_event_user_b === false) $is_event_user_b = chk_event_user_b2($M_login['user_id']);
    if ($is_event_user_b === false) $is_event_user_b = chk_event_user_b3($M_login['user_id']);
    // if ($is_event_user) {
    //   $event_end_date = $db->get_data_one("SELECT EVENT_AT FROM MEMBER_EVENT_250207 WHERE USER_ID='{$M_login['user_id']}'");
    //   $event_end_text = dateDifference(date("Y-m-d H:i:s"), $event_end_date);
    // }
  }

  $info_PB_now = $db->get_data("SELECT * FROM WININFO WHERE GUBUN = 'PB' AND BALL1 != '' ORDER BY PLAYDATE DESC LIMIT 1");
  $info_MM_now = $db->get_data("SELECT * FROM WININFO WHERE GUBUN = 'MM' AND BALL1 != '' ORDER BY PLAYDATE DESC LIMIT 1");

  // 환율
  $won = $db->get_data("SELECT * FROM EXCHANGE WHERE DATE = '".date("Y-m-d")."' LIMIT 1");

  if (!isset($won['WON'])) {
    $won = $db->get_data("SELECT * FROM EXCHANGE ORDER BY DATE DESC ");
  }

  // 달러 당첨금
  $info_PB_now['DOLPRIZ1'] = $info_PB_now['PRIZ1'];
  $info_MM_now['DOLPRIZ1'] = $info_MM_now['PRIZ1'];

  // 환율 적용 원화 당첨금
  $info_PB_now['PRIZ1'] = $info_PB_now['PRIZ1'] * $won['WON'];
  $info_MM_now['PRIZ1'] = $info_MM_now['PRIZ1'] * $won['WON'];

  $info_PB_now['allprice'] = $info_PB_now['PRIZ1'];
  $info_MM_now['allprice'] = $info_MM_now['PRIZ1'];

  if ($info_PB_now['PRIZ1'] > 0) {
    $info_PB_now['PRIZ1'] = number_format($info_PB_now['PRIZ1']);
    $info_PB_now['PRIZ1'] = str_replace(",", "", $info_PB_now['PRIZ1']);
    $info_PB_now['PRIZ1'] = str_replace(".", "", $info_PB_now['PRIZ1']);
    $info_PB_now['PRIZ1'] = substr($info_PB_now['PRIZ1'], 0, -8);
    // $info_PB_now['PRIZ1'] = number_format($info_PB_now['PRIZ1']);
  }

  if ($info_MM_now['PRIZ1'] > 0) {
    $info_MM_now['PRIZ1'] = number_format($info_MM_now['PRIZ1']);
    $info_MM_now['PRIZ1'] = str_replace(",", "", $info_MM_now['PRIZ1']);
    $info_MM_now['PRIZ1'] = str_replace(".", "", $info_MM_now['PRIZ1']);
    $info_MM_now['PRIZ1'] = substr($info_MM_now['PRIZ1'], 0, -8);
    // $info_MM_now['PRIZ1'] = number_format($info_MM_now['PRIZ1']);
  }

  $weekee = date("w", strtotime($info_PB_now['PLAYDATE']." +1 day"));
  $weeks = $week_op[$weekee];
  $info_PB_now['WINTP'] = win_tp($info_PB_now['IS_TYPE'], $info_PB_now['PLAYDATE'], $info_PB_now['BALLP'], $info_PB_now['PRIZCNT9'], $draw_time_num);
  $info_PB_now['PLAYDATE'] = date("Y년 m월 d일 ", strtotime($info_PB_now['PLAYDATE']." +1 day")).$weeks." ".$draw_time;

  $weekee = date("w", strtotime($info_MM_now['PLAYDATE']." +1 day"));
  $weeks  = $week_op[$weekee];
  $info_MM_now['WINTP'] = win_tp($info_MM_now['IS_TYPE'], $info_MM_now['PLAYDATE'], $info_MM_now['BALLP'], $info_MM_now['PRIZCNT9'], $draw_time_num);
  $info_MM_now['PLAYDATE'] = date("Y년 m월 d일 ", strtotime($info_MM_now['PLAYDATE']." +1 day")).$weeks." ".$draw_time;

  // 최신뉴스
  $news = F_BBS_list(array(
    "row"         => 5,
    "page"        => 1,
    "order"       => " REG_DATE DESC",
    "find_text"   => $find_text,
    "find_object" => $find_object,
    "add_query"   => " AND GUBUN = 'NEWS' AND STATUS = 'Y'",
  ));

  if ($news['total'] == 0) {
    $news['BBS_NO'] = array();
  }

  // 미국복권 구매대행 A TO Z
  $atoz = F_BBS_list(array(
    "row"         => 5,
    "page"        => 1,
    "order"       => " REG_DATE DESC",
    "find_text"   => $find_text,
    "find_object" => $find_object,
    "add_query"   => " AND GUBUN = 'ATOZ' AND STATUS = 'Y'",
  ));

  if ($atoz['total'] == 0) {
    $atoz['BBS_NO'] = array();
  }

  // 공지사항
  $notice = F_BBS_list(array(
    "row"         => 5,
    "page"        => 1,
    "order"       => " PIN DESC, REG_DATE DESC",
    "find_text"   => $find_text,
    "find_object" => $find_object,
    "add_query"   => " AND GUBUN = 'NOTICE' AND STATUS = 'Y'",
  ));

  if ($notice['total'] == 0) {
    $notice['BBS_NO'] = array();
  }

  // 최근 추첨영상
  $youtube_MM = $db->get_data("SELECT * FROM WININFO WHERE YUTUBE != '' AND GUBUN = 'MM' ORDER BY PLAYDATE DESC LIMIT 1");
  $youtube_PB = $db->get_data("SELECT * FROM WININFO WHERE YUTUBE != '' AND GUBUN = 'PB' ORDER BY PLAYDATE DESC LIMIT 1");

  $youtube_MM['PRIZ1'] = $youtube_MM['PRIZ1'] * $won['WON'];

  if ($youtube_MM['PRIZ1'] > 0) {
    $youtube_MM['PRIZ1'] = number_format($youtube_MM['PRIZ1']);
    $youtube_MM['PRIZ1'] = str_replace(",", "", $youtube_MM['PRIZ1']);
    $youtube_MM['PRIZ1'] = str_replace(".", "", $youtube_MM['PRIZ1']);
    $youtube_MM['PRIZ1'] = substr($youtube_MM['PRIZ1'], 0, -8);
    $youtube_MM['PRIZ1'] = number_format($youtube_MM['PRIZ1']);
  }

  $youtube_PB['PRIZ1'] = $youtube_PB['PRIZ1'] * $won['WON'];

  if ($youtube_PB['PRIZ1'] > 0) {
    $youtube_PB['PRIZ1'] = number_format($youtube_PB['PRIZ1']);
    $youtube_PB['PRIZ1'] = str_replace(",", "", $youtube_PB['PRIZ1']);
    $youtube_PB['PRIZ1'] = str_replace(".", "", $youtube_PB['PRIZ1']);
    $youtube_PB['PRIZ1'] = substr($youtube_PB['PRIZ1'], 0, -8);
    $youtube_PB['PRIZ1'] = number_format($youtube_PB['PRIZ1']);
  }

  $youtube_MM['PLAYDATE'] = date("m월 d일 ", strtotime($youtube_MM['PLAYDATE']." +1 day"));
  $youtube_PB['PLAYDATE'] = date("m월 d일 ", strtotime($youtube_PB['PLAYDATE']." +1 day"));

  // 마지막 추첨결과
  $info_PB_last = $db->get_data("SELECT WININFO_NO FROM WININFO WHERE GUBUN = 'PB' AND BALL1 != '' AND BALL2 !='' AND PRIZ1 > 0 AND PRIZ2 > 0 ORDER BY PLAYDATE DESC LIMIT 1");
  $info_MM_last = $db->get_data("SELECT WININFO_NO FROM WININFO WHERE GUBUN = 'MM' AND BALL1 != '' AND BALL2 !='' AND PRIZ1 > 0 AND PRIZ2 > 0 ORDER BY PLAYDATE DESC LIMIT 1");

  // 누적 당첨 정보
  $tot_wininfo = $db->get_data("SELECT WIN_MONEY, WIN_CNT FROM MAIN_ACCUMULATE_INFO ORDER BY CREATED_AT DESC LIMIT 1");
?>
