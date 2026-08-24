<?php
  $version = "3.47";

  $domain = getDomain();

  function getDomain() {
    $parts = explode('.', $_SERVER['HTTP_HOST']);
    $lastDomain = end($parts);

    if ($lastDomain === 'kr') {
      return $domain = "superlottokorea.co.kr";;
    }

    if (($lastDomain === 'com' || $lastDomain === 'net') && count($parts) >= 2) {
      $domain = $parts[count($parts) - 2] . '.' . $lastDomain;
      return $domain;
    }
  }

  if (!function_exists('session_start_samesite')) {
    function session_start_modify_cookie() {
      $headers = headers_list();
      krsort($headers);
      foreach ($headers as $header) {
        if (!preg_match('~^Set-Cookie: PHPSESSID=~', $header)) continue;
        $header = preg_replace('~; secure(; HttpOnly)?$~', '', $header) . '; secure; SameSite=None';
        header($header, false);
        break;
      }
    }

    function session_start_samesite($options = []) {
      $res = session_start($options);
      session_start_modify_cookie();
      return $res;
    }

    function session_regenerate_id_samesite($delete_old_session = false) {
      $res = session_regenerate_id($delete_old_session);
      session_start_modify_cookie();
      return $res;
    }
  }

  if ($session_start_samesite == "Y") {
    session_start_samesite();
  } else {
    require_once $_SERVER['DOCUMENT_ROOT']."/_common/session_start.php"; // session start
  }

  header("Content-Type: text/html; charset=UTF-8");

  $src_root = $_SERVER['DOCUMENT_ROOT'];

  require_once $_SERVER['DOCUMENT_ROOT']."/auth.php";

  if (count($_GET) > 0) {
    foreach ($_GET as $k => $v) {
      ${$k} = $v;
    }
  }

  if (count($_POST) > 0) {
    foreach ($_POST as $k => $v) {
      ${$k} = $v;
    }
  }

  if (count($_SESSION) > 0) {
    foreach ($_SESSION as $k => $v) {
      ${$k} = $v;
    }
  }

  /** VoyageX 정보 */
  $usIpAddress = "";
  $usDomain    = "https://ororaearth.com";
  $usApiDomain = "";
  $usClientId  = "";
  $usClientKey = "";

  /** AWS 정보 */
  $awsRegion    = "";
  $awsAccessKey = "";
  $awsSecretKey = "";


  $domain = "sulokolink.com";
  $imgdomain = "mekosystem.com";
  /** 이미지 정보 */
  $ticketImageURI  = "https://img.{$imgdomain}/ticket/";
  $commonImageURI  = "https://img.{$imgdomain}/images/web/";

  // DB 연결계정
  $Database_Host = "15.165.37.71";
  $Database_Name = "super";
  $Database_User = "root";
  $Database_Password = "tnfhzh09*&^%";

  require_once $src_root.'/_common/db_class.php';
  require_once $src_root.'/_common/common.php';

  // *SQL INJECTION  체크
  include($src_root."/_common/g_sql_injection_filter.php");

  if ($not_mobile != true) {
    if (isMobile()) {
      header("Location: https://m.".$domain."/");
      exit;
    }
  }

  //사이트 설정
  $site = $db->get_data("SELECT * FROM SITE");

  //SMS (LMS)
  $sms = $db->get_data("SELECT SENDER, HP FROM SMSCONFIG WHERE HOMEPAGE_USE = 'Y' LIMIT 1");
  $sms_sender = $sms['SENDER'];
  $fromHP = $sms['HP'];
  unset($sms);

  $fname = "슈로코";
  $fmail = "send_only@superlottokorea.com";

  $level_op = array(
    "1" => "US admin",
    "2" => "Staff admin",
    "3" => "Main admin",
    "4" => "Super admin"
  );

  $stop_op = array(
    "N" => "중지안함",
    "Y" => "중지",
  );

  $codesk_op = array(
    "S" => "SMS",
    "P" => "PUSH",
  );

  $ball_op = array(
    "PB" => "파워볼",
    "MM" => "메가밀리언",
  );

  $print_op = array(
    "N" => "대기",
    "Y" => "완료",
  );

  $winyn_op = array(
    "N" => "미당첨",
    "Y" => "당첨자",
  );

  $guunno_op = array(
    "M" => "수동",
    "A" => "자동",
    "S" => "저장번호",
  );

  $search_date_op = array(
    "1D" => "오늘",
    "7D" => "일주일",
    "15D" => "15일",
    "1M" => "1개월",
    "3M" => "3개월",
    "6M" => "6개월",
    "1Y" => "1년"
  );

  $week_op = array(
    '0' => '일',
    '1' => '월',
    '2' => '화',
    '3' => '수',
    '4' => '목',
    '5' => '금',
    '6' => '토',
  );

  // 회원 등급
  $grade_op = [
    "platinum" => ["name" => "플래티넘", "eng" => "Platinum", "cash" => 200, "percent" => 8],
    "gold"     => ["name" => "골드", "eng" => "Gold", "cash" => 100, "percent" => 5],
    "silver"   => ["name" => "실버", "eng" => "Silver", "cash" => 50, "percent" => 2.5],
    "brown"    => ["name" => "브라운", "eng" => "Brown", "cash" => 0, "percent" => 0.5],
  ];

  $cash_op = array(
    array(
      "itemname"    => "6,000",
      "price"       => 6600,
      "cash"        => 6000,
      "games"       => 1,
      "cash_text"   => "1게임 이용 가능",
      "ipoint"      => 0,
      "ipoint_text" => "",
      "point"       => 0,
      "point_text"  => "",
    ),
    array(
      "itemname"    => "15,000",
      "price"       => 16500,
      "cash"        => 15000,
      "games"       => 2,
      "cash_text"   => "2게임 이용 가능",
      "ipoint"      => 0,
      "ipoint_text" => "",
      "point"       => 0,
      "point_text"  => "",
    ),
    array(
      "itemname"    => "30,000",
      "price"       => 33000,
      "cash"        => 30000,
      "games"       => 5,
      "cash_text"   => "5게임 이용 가능",
      "ipoint"      => 0,
      "ipoint_text" => "",
      "point"       => 1500,
      "point_text"  => "(5% UP↑)",
    ),
    array(
      "itemname"    => "60,000",
      "price"       => 66000,
      "cash"        => 60000,
      "games"       => 11,
      "cash_text"   => "11게임 이용 가능",
      "ipoint"      => 0,
      "ipoint_text" => "",
      "point"       => 6000,
      "point_text"  => "(10% UP↑)",
    ),
    array(
      "itemname"    => "120,000",
      "price"       => 132000,
      "cash"        => 120000,
      "games"       => 22,
      "cash_text"   => "20게임 이용 가능",
      "ipoint"      => 0,
      "ipoint_text" => "",
      "point"       => 18000,
      "point_text"  => "(15% UP↑)",
    ),
    array(
      "itemname"    => "300,000",
      "price"       => 330000,
      "cash"        => 300000,
      "games"       => 58,
      "cash_text"   => "50게임 이용 가능",
      "ipoint"      => 0,
      "ipoint_text" => "",
      "point"       => 60000,
      "point_text"  => "(20% UP↑)",
    ),
    array(
      "itemname"    => "600,000",
      "price"       => 660000,
      "cash"        => 600000,
      "games"       => 120,
      "cash_text"   => "100게임 이용 가능",
      "ipoint"      => 0,
      "ipoint_text" => "",
      "point"       => 180000,
      "point_text" => "(30% UP↑)",
    ),
  );
  $cash_op = array_reverse($cash_op);

  $event_cash_op_a = array(
    array(
      "itemname"    => "6,000(E)",
      "price"       => 6600,
      "cash"        => 6000,
      "games"       => 1,
      "cash_text"   => "1게임 이용 가능",
      "ipoint"      => 0,
      "ipoint_text" => "",
      "point"       => 3000,
      "point_text"  => "50% UP↑",
    ),
    array(
      "itemname"    => "15,000(E)",
      "price"       => 16500,
      "cash"        => 15000,
      "games"       => 2,
      "cash_text"   => "2게임 이용 가능",
      "ipoint"      => 0,
      "ipoint_text" => "",
      "point"       => 7500,
      "point_text"  => "50% UP↑",
    ),
    array(
      "itemname"    => "30,000(E)",
      "price"       => 33000,
      "cash"        => 30000,
      "games"       => 5,
      "cash_text"   => "5게임 이용 가능",
      "ipoint"      => 0,
      "ipoint_text" => "",
      "point"       => 15000,
      "point_text"  => "50% UP↑",
    ),
    array(
      "itemname"    => "60,000(E)",
      "price"       => 66000,
      "cash"        => 60000,
      "games"       => 11,
      "cash_text"   => "11게임 이용 가능",
      "ipoint"      => 0,
      "ipoint_text" => "",
      "point"       => 30000,
      "point_text"  => "50% UP↑",
    ),
    array(
      "itemname"    => "120,000(E)",
      "price"       => 132000,
      "cash"        => 120000,
      "games"       => 22,
      "cash_text"   => "20게임 이용 가능",
      "ipoint"      => 0,
      "ipoint_text" => "",
      "point"       => 60000,
      "point_text"  => "50% UP↑",
    ),
    array(
      "itemname"    => "300,000(E)",
      "price"       => 330000,
      "cash"        => 300000,
      "games"       => 58,
      "cash_text"   => "50게임 이용 가능",
      "ipoint"      => 0,
      "ipoint_text" => "",
      "point"       => 150000,
      "point_text"  => "50% UP↑",
    ),
    array(
      "itemname"    => "600,000(E)",
      "price"       => 660000,
      "cash"        => 600000,
      "games"       => 120,
      "cash_text"   => "100게임 이용 가능",
      "ipoint"      => 0,
      "ipoint_text" => "",
      "point"       => 300000,
      "point_text" => "50% UP↑",
    ),
  );
  $event_cash_op_a = array_reverse($event_cash_op_a);

  $event_cash_op_b1 = array(
    array(
      "itemname"    => "6,000",
      "price"       => 6600,
      "cash"        => 6000,
      "games"       => 1,
      "cash_text"   => "1게임 이용 가능",
      "ipoint"      => 0,
      "ipoint_text" => "",
      "point"       => 0,
      "point_text"  => "",
    ),
    array(
      "itemname"    => "15,000(E)",
      "price"       => 16500,
      "cash"        => 15000,
      "games"       => 2,
      "cash_text"   => "2게임 이용 가능",
      "ipoint"      => 0,
      "ipoint_text" => "",
      "point"       => 6000,
      "point_text"  => "40% UP↑",
    ),
    array(
      "itemname"    => "30,000(E)",
      "price"       => 33000,
      "cash"        => 30000,
      "games"       => 5,
      "cash_text"   => "5게임 이용 가능",
      "ipoint"      => 0,
      "ipoint_text" => "",
      "point"       => 12000,
      "point_text"  => "40% UP↑",
    ),
    array(
      "itemname"    => "60,000(E)",
      "price"       => 66000,
      "cash"        => 60000,
      "games"       => 11,
      "cash_text"   => "11게임 이용 가능",
      "ipoint"      => 0,
      "ipoint_text" => "",
      "point"       => 24000,
      "point_text"  => "40% UP↑",
    ),
    array(
      "itemname"    => "120,000(E)",
      "price"       => 132000,
      "cash"        => 120000,
      "games"       => 22,
      "cash_text"   => "20게임 이용 가능",
      "ipoint"      => 0,
      "ipoint_text" => "",
      "point"       => 48000,
      "point_text"  => "40% UP↑",
    ),
    array(
      "itemname"    => "300,000(E)",
      "price"       => 330000,
      "cash"        => 300000,
      "games"       => 58,
      "cash_text"   => "50게임 이용 가능",
      "ipoint"      => 0,
      "ipoint_text" => "",
      "point"       => 120000,
      "point_text"  => "40% UP↑",
    ),
    array(
      "itemname"    => "600,000(E)",
      "price"       => 660000,
      "cash"        => 600000,
      "games"       => 120,
      "cash_text"   => "100게임 이용 가능",
      "ipoint"      => 0,
      "ipoint_text" => "",
      "point"       => 240000,
      "point_text" => "40% UP↑",
    ),
  );
  $event_cash_op_b1 = array_reverse($event_cash_op_b1);

  $event_cash_op_b2 = array(
    array(
      "itemname"    => "6,000",
      "price"       => 6600,
      "cash"        => 6000,
      "games"       => 1,
      "cash_text"   => "1게임 이용 가능",
      "ipoint"      => 0,
      "ipoint_text" => "",
      "point"       => 0,
      "point_text"  => "",
    ),
    array(
      "itemname"    => "15,000",
      "price"       => 16500,
      "cash"        => 15000,
      "games"       => 2,
      "cash_text"   => "2게임 이용 가능",
      "ipoint"      => 0,
      "ipoint_text" => "",
      "point"       => 0,
      "point_text"  => "",
    ),
    array(
      "itemname"    => "30,000(E)",
      "price"       => 33000,
      "cash"        => 30000,
      "games"       => 5,
      "cash_text"   => "5게임 이용 가능",
      "ipoint"      => 0,
      "ipoint_text" => "",
      "point"       => 12000,
      "point_text"  => "40% UP↑",
    ),
    array(
      "itemname"    => "60,000(E)",
      "price"       => 66000,
      "cash"        => 60000,
      "games"       => 11,
      "cash_text"   => "11게임 이용 가능",
      "ipoint"      => 0,
      "ipoint_text" => "",
      "point"       => 24000,
      "point_text"  => "40% UP↑",
    ),
    array(
      "itemname"    => "120,000(E)",
      "price"       => 132000,
      "cash"        => 120000,
      "games"       => 22,
      "cash_text"   => "20게임 이용 가능",
      "ipoint"      => 0,
      "ipoint_text" => "",
      "point"       => 48000,
      "point_text"  => "40% UP↑",
    ),
    array(
      "itemname"    => "300,000(E)",
      "price"       => 330000,
      "cash"        => 300000,
      "games"       => 58,
      "cash_text"   => "50게임 이용 가능",
      "ipoint"      => 0,
      "ipoint_text" => "",
      "point"       => 120000,
      "point_text"  => "40% UP↑",
    ),
    array(
      "itemname"    => "600,000(E)",
      "price"       => 660000,
      "cash"        => 600000,
      "games"       => 120,
      "cash_text"   => "100게임 이용 가능",
      "ipoint"      => 0,
      "ipoint_text" => "",
      "point"       => 240000,
      "point_text" => "40% UP↑",
    ),
  );
  $event_cash_op_b2 = array_reverse($event_cash_op_b2);

  $event_cash_op_b3 = array(
    array(
      "itemname"    => "6,000",
      "price"       => 6600,
      "cash"        => 6000,
      "games"       => 1,
      "cash_text"   => "1게임 이용 가능",
      "ipoint"      => 0,
      "ipoint_text" => "",
      "point"       => 0,
      "point_text"  => "",
    ),
    array(
      "itemname"    => "15,000",
      "price"       => 16500,
      "cash"        => 15000,
      "games"       => 2,
      "cash_text"   => "2게임 이용 가능",
      "ipoint"      => 0,
      "ipoint_text" => "",
      "point"       => 0,
      "point_text"  => "",
    ),
    array(
      "itemname"    => "30,000",
      "price"       => 33000,
      "cash"        => 30000,
      "games"       => 5,
      "cash_text"   => "5게임 이용 가능",
      "ipoint"      => 0,
      "ipoint_text" => "",
      "point"       => 0,
      "point_text"  => "",
    ),
    array(
      "itemname"    => "60,000(E)",
      "price"       => 66000,
      "cash"        => 60000,
      "games"       => 11,
      "cash_text"   => "11게임 이용 가능",
      "ipoint"      => 0,
      "ipoint_text" => "",
      "point"       => 24000,
      "point_text"  => "40% UP↑",
    ),
    array(
      "itemname"    => "120,000(E)",
      "price"       => 132000,
      "cash"        => 120000,
      "games"       => 22,
      "cash_text"   => "20게임 이용 가능",
      "ipoint"      => 0,
      "ipoint_text" => "",
      "point"       => 48000,
      "point_text"  => "40% UP↑",
    ),
    array(
      "itemname"    => "300,000(E)",
      "price"       => 330000,
      "cash"        => 300000,
      "games"       => 58,
      "cash_text"   => "50게임 이용 가능",
      "ipoint"      => 0,
      "ipoint_text" => "",
      "point"       => 120000,
      "point_text"  => "40% UP↑",
    ),
    array(
      "itemname"    => "600,000(E)",
      "price"       => 660000,
      "cash"        => 600000,
      "games"       => 120,
      "cash_text"   => "100게임 이용 가능",
      "ipoint"      => 0,
      "ipoint_text" => "",
      "point"       => 240000,
      "point_text" => "40% UP↑",
    ),
  );
  $event_cash_op_b3 = array_reverse($event_cash_op_b3);

  $card_op = array(
    "01" => "비씨",
    "02" => "국민",
    "03" => "하나",
    "04" => "삼성",
    "06" => "신한",
    "07" => "현대",
    "08" => "롯데",
    "12" => "농협"
  );

  $bank_op = array(
    "KDB산업은행" => "KDB산업은행",
    "IBK기업은행" => "IBK기업은행",
    "KB국민은행" => "KB국민은행",
    "SH수협은행" => "SH수협은행",
    "한국수출입은행" => "한국수출입은행",
    "NH농협은행" => "NH농협은행",
    "우리은행" => "우리은행",
    "SC제일은행" => "SC제일은행",
    "한국씨티은행" => "한국씨티은행",
    "대구은행" => "대구은행",
    "부산은행" => "부산은행",
    "광주은행" => "광주은행",
    "제주은행" => "제주은행",
    "전북은행" => "전북은행",
    "경남은행" => "경남은행",
    "새마을금고" => "새마을금고",
    "신협" => "신협",
    "KEB하나은행" => "KEB하나은행",
    "신한은행" => "신한은행",
    "케이뱅크" => "케이뱅크",
    "카카오뱅크" => "카카오뱅크",
    "우체국" => "우체국",
    "저축은행" => "저축은행",
    "토스뱅크" => "토스뱅크",
    "산림조합" => "산림조합"
  );

  $win_where_op = array(
    "1" => "화이트볼 5개 + 메가볼 1개",
    "2" => "화이트볼 5개",
    "3" => "화이트볼 4개 + 메가볼 1개",
    "4" => "화이트볼 4개",
    "5" => "화이트볼 3개 + 메가볼 1개",
    "6" => "화이트볼 3개",
    "7" => "화이트볼 2개 + 1개 메가볼 1개",
    "8" => "화이트볼 1개 + 1개 메가볼 1개",
    "9" => "메가볼 1개"
  );

  $pb_win_where_op = array(
    "1" => "화이트볼 5개 + 파워볼 1개",
    "2" => "화이트볼 5개",
    "3" => "화이트볼 4개 + 파워볼 1개",
    "4" => "화이트볼 4개",
    "5" => "화이트볼 3개 + 파워볼 1개",
    "6" => "화이트볼 3개",
    "7" => "화이트볼 2개 + 파워볼 1개",
    "8" => "화이트볼 1개 + 파워볼 1개",
    "9" => "파워볼 1개"
  );

  $faq_op = array(
    "FAQ1" => "복권구매",
    "FAQ2" => "이용안내",
    "FAQ3" => "캐시",
    "FAQ4" => "마일리지, 포인트, 쿠폰",
    "FAQ5" => "당첨금",
    "FAQ6" => "기타"
  );

  $mlevel_op = array(
    "1" => "브라운",
    "2" => "실버",
    "3" => "골드",
    "4" => "플래티넘",
  );

  $mlevel_image_op = array(
    "1" => "/common/images/img_brown.png",
    "2" => "/common/images/img_silver.png",
    "3" => "/common/images/img_gold.png",
    "4" => "/common/images/img_platinum.png",
  );

  $mail_status_op = array(
    "N" => "배송신청",
    "T" => "접수완료",
    "D" => "취소",
    "R" => "배송중",
    "Y" => "배송완료"
  );

  $price_point1 = 1650;
  $price_point2 = 3300;

  $max_gameCount = 100;

  $ip_address = getRealClientIp();

  $week_op = array(
    0 => "일요일",
    1 => "월요일",
    2 => "화요일",
    3 => "수요일",
    4 => "목요일",
    5 => "금요일",
    6 => "토요일"
  );

  // 추첨, 마감시간
  // SLK-1297 DST 체크방식 변경
  if (check_dst("Now")) {
    $draw_time         = "12:00";
    $draw_time_num     = 12;
    $deadline_time     = "09:00";
    $deadline_time_num = 9;
  } else {
    $draw_time         = "13:00";
    $draw_time_num     = 13;
    $deadline_time     = "10:00";
    $deadline_time_num = 10;
  }

  // 당첨금 예상 금액 - 계산 비율 15%
  $price_est_per = 1.15;

  // PG 결제 비율
  $pg_rate = array(
    'coam'=>20,
    'wayup'=>80
  );

  // 새로운 메가밀리언 규칙 적용일시
  $new_megamillion_play_date = "2025-04-05 09:00:00"; // 판매금액 변경일시
  $new_megamillion_draw_date = "2025-04-09 12:00:00"; // 추첨금액 변경일시

  // 당첨 후 초기화 금액 $
  $PB_reset_price = array(
    1 =>  20000000,
    2 =>  "$ 1,000,000",
    3 =>  "$ 50,000",
    4 =>  "$ 100",
    5 =>  "$ 100",
    6 =>  "$ 7",
    7 =>  "$ 7",
    8 =>  "$ 4",
    9 =>  "$ 4"
  );
  if (getSecondDifference($new_megamillion_play_date) >= 0) {
    $MM_reset_price = array(
      1 =>  50000000,
      2 =>  "$200만 ~ $1천만",
      3 =>  "$2만 ~ $10만",
      4 =>  "$1천 ~ $5천",
      5 =>  "$400 ~ $2천",
      6 =>  "$20 ~ $100",
      7 =>  "$20 ~ $100",
      8 =>  "$14 ~ $70",
      9 =>  "$10 ~ $50"
    );
  } else {
    $MM_reset_price = array(
      1 =>  20000000,
      2 =>  1000000,
      3 =>  10000,
      4 =>  500,
      5 =>  200,
      6 =>  10,
      7 =>  10,
      8 =>  4,
      9 =>  2
    );
  }

  $koreanRegexp  = '/^[가-힣]+$/';
  $englishRegexp = '/^[a-zA-Z\s]+$/';
  $pwRegexp      = "/^(?!.*[&'<>])(?=.*[a-zA-Z])(?=.*[0-9]).{8,20}$/";

  $slide_power_op = array(
    0 => array(
      "일요일 ~ 월요일",
      "1회차. AM 02:00 ~ PM 24:00 신청",
      "1회차. 익일 AM 06:00 이후 확인가능",
      "2회차. AM 00:00 ~ AM 02:00 신청",
      "2회차. 당일 AM 06:00 이후 확인가능",
      "< 일 AM 02:00이후 주문은 차회차로 구매 됩니다. >"),
    1 => array(
      "월요일~ 화요일",
      "1회차. AM 02:00 ~ PM 24:00 신청",
      "1회차. 익일 AM 06:00 이후 확인가능",
      "2회차. AM 00:00 ~ AM 02:00 신청",
      "2회차. 당일 AM 06:00 이후 확인가능",
      ""),
    2 => array(
      "화요일 ~ 수요일",
      "1회차. AM 02:00 ~ PM 24:00 신청",
      "1회차. 익일 AM 06:00 이후 확인가능",
      "2회차. AM 00:00 ~ AM 02:00 신청",
      "2회차. 당일 AM 06:00 이후 확인가능",
      "< 화 AM 02:00이후 주문은 차회차로 구매 됩니다. >"),
    3 => array(
      "수요일 ~ 목요일",
      "1회차. AM 02:00 ~ PM 24:00 신청",
      "1회차. 익일 AM 06:00 이후 확인가능",
      "2회차. AM 00:00 ~ AM 02:00 신청",
      "2회차. 당일 AM 06:00 이후 확인가능",
      ""),
    4 => array(
      "목요일 ~ 금요일",
      "1회차. AM 02:00 ~ PM 24:00 신청",
      "1회차. 익일 AM 06:00 이후 확인가능",
      "2회차. AM 00:00 ~ AM 02:00 신청",
      "2회차. 당일 AM 06:00 이후 확인가능",
      "< 목 AM 02:00이후 주문은 차회차로 구매 됩니다. >"),
    5 => array(
      "금요일 ~ 토요일",
      "1회차. AM 02:00 ~ PM 24:00 신청",
      "1회차. 익일 AM 06:00 이후 확인가능",
      "2회차. AM 00:00 ~ AM 02:00 신청",
      "2회차. 당일 AM 06:00 이후 확인가능",
      ""),
    6 => array(
      "토요일 ~ 일요일",
      "1회차. AM 02:00 ~ PM 24:00 신청",
      "1회차. 익일 AM 06:00 이후 확인가능",
      "2회차. AM 00:00 ~ AM 02:00 신청",
      "2회차. 당일 AM 06:00 이후 확인가능",
      "")
  );

  $slide_mega_op = array(
    0 => array(
      "일요일 ~ 월요일",
      "1회차. AM 02:00 ~ PM 24:00 신청",
      "1회차. 익일 AM 06:00 이후 확인가능",
      "2회차. AM 00:00 ~ AM 02:00 신청",
      "2회차. 당일 AM 06:00 이후 확인가능",
      ""),
    1 => array(
      "월요일~ 화요일",
      "1회차. AM 02:00 ~ PM 24:00 신청",
      "1회차. 익일 AM 06:00 이후 확인가능",
      "2회차. AM 00:00 ~ AM 02:00 신청",
      "2회차. 당일 AM 06:00 이후 확인가능",
      ""),
    2 => array(
      "화요일 ~ 수요일",
      "1회차. AM 02:00 ~ PM 24:00 신청",
      "1회차. 익일 AM 06:00 이후 확인가능",
      "2회차. AM 00:00 ~ AM 02:00 신청",
      "2회차. 당일 AM 06:00 이후 확인가능",
      ""),
    3 => array(
      "수요일 ~ 목요일",
      "1회차. AM 02:00 ~ PM 24:00 신청",
      "1회차. 익일 AM 06:00 이후 확인가능",
      "2회차. AM 00:00 ~ AM 02:00 신청",
      "2회차. 당일 AM 06:00 이후 확인가능",
      "< 수 AM 02:00이후 주문은 차회차로 구매 됩니다. >"),
    4 => array(
      "목요일 ~ 금요일",
      "1회차. AM 02:00 ~ PM 24:00 신청",
      "1회차. 익일 AM 06:00 이후 확인가능",
      "2회차. AM 00:00 ~ AM 02:00 신청",
      "2회차. 당일 AM 06:00 이후 확인가능",
      ""),
    5 => array(
      "금요일 ~ 토요일",
      "1회차. AM 02:00 ~ PM 24:00 신청",
      "1회차. 익일 AM 06:00 이후 확인가능",
      "2회차. AM 00:00 ~ AM 02:00 신청",
      "2회차. 당일 AM 06:00 이후 확인가능",
      ""),
    6 => array(
      "토요일 ~ 일요일",
      "1회차. AM 02:00 ~ PM 24:00 신청",
      "1회차. 익일 AM 06:00 이후 확인가능",
      "2회차. AM 00:00 ~ AM 02:00 신청",
      "2회차. 당일 AM 06:00 이후 확인가능",
      "< 토 AM 02:00이후 주문은 차회차로 구매 됩니다. >")
  );
