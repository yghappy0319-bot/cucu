<?php
include $_SERVER['DOCUMENT_ROOT']."/inc/meta.html";
include $_SERVER['DOCUMENT_ROOT']."/_library/function_NOTICE.php";

// 파트너 처리 - SLK-647
// cp_id가 있을경우만 함수호출로 변경
// 파트너 처리 - SLK-647
// cp_id가 있을경우만 함수호출로 변경
if (!empty($_GET['cp_id'])) {
  exec_partner($_GET['cp_id'] , 'MOBILE');
  $domain = "https://".$_SERVER['HTTP_HOST']."/";
  meta_go($domain); // 세션 처리후 cp_id 제거
  exit;
}

$info_PB_now = $db->get_data("SELECT * FROM WININFO WHERE GUBUN = 'PB' AND BALL1 != '' ORDER BY PLAYDATE DESC LIMIT 1");
$info_MM_now = $db->get_data("SELECT * FROM WININFO WHERE GUBUN = 'MM' AND BALL1 != '' ORDER BY PLAYDATE DESC LIMIT 1");

// 환율
$won = $db->get_data("SELECT * FROM EXCHANGE WHERE DATE = '".date("Y-m-d")."' LIMIT 1");

if (empty($won['WON']) == false) {
  $won = $db->get_data("SELECT * FROM EXCHANGE ORDER BY DATE DESC LIMIT 1");
} else {
  $won['WON'] = 1;
}

$info_PB_now['DOLPRIZ1'] = $info_PB_now['PRIZ1'];
$info_MM_now['DOLPRIZ1'] = $info_MM_now['PRIZ1'];
$info_PB_now['PRIZ1']    = $info_PB_now['PRIZ1'] * $won['WON'];
$info_MM_now['PRIZ1']    = $info_MM_now['PRIZ1'] * $won['WON'];

$info_PB_now['allprice'] = $info_PB_now['PRIZ1'];
$info_MM_now['allprice'] = $info_MM_now['PRIZ1'];

if ($info_PB_now['PRIZ1'] > 0) {
  $info_PB_now['PRIZ1'] = number_format($info_PB_now['PRIZ1']);
  $info_PB_now['PRIZ1'] = str_replace(",", "", $info_PB_now['PRIZ1']);
  $info_PB_now['PRIZ1'] = str_replace(".", "", $info_PB_now['PRIZ1']);
  $info_PB_now['PRIZ1'] = substr($info_PB_now['PRIZ1'], 0, -8);
  $info_PB_now['PRIZ1'] = number_format($info_PB_now['PRIZ1']);
}

if ($info_MM_now['PRIZ1'] > 0) {
  $info_MM_now['PRIZ1'] = number_format($info_MM_now['PRIZ1']);
  $info_MM_now['PRIZ1'] = str_replace(",", "", $info_MM_now['PRIZ1']);
  $info_MM_now['PRIZ1'] = str_replace(".", "", $info_MM_now['PRIZ1']);
  $info_MM_now['PRIZ1'] = substr($info_MM_now['PRIZ1'], 0, -8);
  $info_MM_now['PRIZ1'] = number_format($info_MM_now['PRIZ1']);
}

$weekee = date("w", strtotime($info_PB_now['PLAYDATE']." +1 day"));
$weeks  = $week_op[$weekee];
$info_PB_now['WINTP']    = win_tp($info_PB_now['IS_TYPE'], $info_PB_now['PLAYDATE'], $info_PB_now['BALLP'], $info_PB_now['PRIZCNT9'], $draw_time_num);
$info_PB_now['PLAYDATE'] = date("Y년 m월 d일 ", strtotime($info_PB_now['PLAYDATE']." +1 day")).$weeks." ".$draw_time;

$weekee = date("w", strtotime($info_MM_now['PLAYDATE']." +1 day"));
$weeks  = $week_op[$weekee];
$info_MM_now['WINTP']    = win_tp($info_MM_now['IS_TYPE'], $info_MM_now['PLAYDATE'], $info_MM_now['BALLP'], $info_MM_now['PRIZCNT9'], $draw_time_num);
$info_MM_now['PLAYDATE'] = date("Y년 m월 d일 ", strtotime($info_MM_now['PLAYDATE']." +1 day")).$weeks." ".$draw_time;

// 최신뉴스
$news = F_NOTICE_list(array(
  "row"         => 4,
  "page"        => 1,
  "order"       => " REG_DATE DESC",
  "find_text"   => $find_text,
  "find_object" => $find_object,
  "add_query"   => " AND GUBUN='NEWS' AND STATUS='Y'",
));

if ($news['total'] == 0) {
  $news['BBS_NO'] = array();
}

// 공지사항
$notice = F_NOTICE_list(array(
  "row" => 4,
  "page" => 1,
  "order" => " PIN DESC, REG_DATE DESC",
  "find_text" => $find_text,
  "find_object" => $find_object,
  "add_query" => " AND GUBUN='NOTICE' AND STATUS='Y'",
));

if ($notice['total'] == 0) {
  $notice['BBS_NO'] = array();
}

// 마지막 추첨결과
$info_PB_last = $db->get_data("SELECT WININFO_NO FROM WININFO WHERE GUBUN='PB' AND BALL1 != '' AND BALL2 !='' AND PRIZ1 > 0 AND PRIZ2 > 0 ORDER BY PLAYDATE DESC LIMIT 1");
$info_MM_last = $db->get_data("SELECT WININFO_NO FROM WININFO WHERE GUBUN='MM' AND BALL1 != '' AND BALL2 !='' AND PRIZ1 > 0 AND PRIZ2 > 0 ORDER BY PLAYDATE DESC LIMIT 1");

// 누적 당첨 정보 (sulokolink API만 사용 — 실패·오류 응답 시 메인에 미표시)
$main_accumulate_ok = false;
$tot_wininfo = array('WIN_MONEY' => 0, 'WIN_CNT' => 0);
$accumulate_as_of = '';

$acc_ctx = stream_context_create(array(
  'http' => array(
    'timeout' => 3,
    'ignore_errors' => true,
    'header' => "Accept: application/json\r\n",
  ),
));
$acc_raw = @file_get_contents('https://sadmin.mekosystem.com/api/main_accumulate.php', false, $acc_ctx);
if ($acc_raw !== false) {
  $acc = json_decode($acc_raw, true);
  if (is_array($acc) && !empty($acc['ok']) && isset($acc['win_cnt'], $acc['win_money'])) {
    $tot_wininfo['WIN_CNT'] = (int) $acc['win_cnt'];
    $tot_wininfo['WIN_MONEY'] = (int) $acc['win_money'];
    $main_accumulate_ok = true;
    if (!empty($acc['created_at'])) {
      $acc_ts = strtotime($acc['created_at']);
      if ($acc_ts !== false) {
        $accumulate_as_of = date('Y-m-d', $acc_ts);
      }
    }
    if ($accumulate_as_of === '') {
      $accumulate_as_of = date('Y-m-d');
    }
  }
}
?>
