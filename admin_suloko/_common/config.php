<?php
  require_once __DIR__."/session_slot.php";
  if (session_status() === PHP_SESSION_NONE) {
    session_start();
  }

  header("Content-Type: text/html; charset=UTF-8");

  $src_root = $_SERVER["DOCUMENT_ROOT"];

  $version = "1.84";

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

  // 멀티로그인 슬롯에 맞는 계정 세션 적용
  admin_apply_session_login();

  // DB 연결계정 정보
  $Database_Host = "15.165.37.71";
  $Database_Name = "super";
  $Database_User = "root";
  $Database_Password = "tnfhzh09*&^%";

  require_once $src_root."/_common/db_class.php";
  require_once $src_root."/_common/common.php";

  // *SQL INJECTION  체크
  include($src_root."/_common/g_sql_injection_filter.php");

  // fcm 푸쉬메시지 전송키 (superlottokorea)
  $FCM_URL = 'https://fcm.googleapis.com/v1/projects/suloko/messages:send';
  $FCM_PRIVATE_KEY = $_SERVER["DOCUMENT_ROOT"] ."/_common/suloko-firebase-adminsdk-bzgej-e143c38f23.json";
  $ICON_URL = 'https://m.surokolink.com/common/images/cropped-favicon-32x32.png';
  $ACTION_URL = 'https://m.surokolink.com';

  /** AWS 정보 */
  $awsRegion    = "ap-northeast-2";
  $awsAccessKey = "AKIAS4MXFN5OUDAMDS6V";
  $awsSecretKey = "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";

  /** USA(voyageX) Info */
  $usIpAddress = "";
  $usApiDomain = "";
  $usClientId  = "";
  $usClientKey = "";

  /** Client IP address */
  $clientIpAddress = getRealClientIp();

  $s3_ticket_url = "";

  $PB_init_num = 962;
  $PB_init_date = "2022-03-14";

  $MM_init_num = 1746;
  $MM_init_date = "2022-03-15";

  $price_point1 = 1650;
  $price_point2 = 3000;

  $invoce_price = 1000;

  // 새로운 메가밀리언 규칙 적용일시
  $new_megamillion_play_date = "2025-04-05 09:00:00"; // 판매금액 변경일시
  $new_megamillion_draw_date = "2025-04-09 12:00:00"; // 추첨금액 변경일시

  $fname = "슈로코";
  $fmail = "send_only@superlottokorea.com";

  $partner_domain = "https://partner.suloko7.com";

  $limit_op = array(
    "20"   => "20뷰",
    "50"   => "50뷰",
    "100"  => "100뷰",
    "300"  => "300뷰",
    "500"  => "500뷰",
    "1000" => "1000뷰"
  );

  $level_op = array(
    "1" => "US admin",
    "2" => "Staff admin",
    "3" => "Main admin",
    "4" => "Super admin"
  );

  $stop_op = array(
    "N" => "사용",
    "Y" => "중지",
  );

  $patner_stop_op = array(
    "N" => "사용중",
    "Y" => "중지",
    "R" => "가입요망",
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
    "Y" => "당첨",
  );

  $winyn_op2 = array(
    "N" => "낙첨",
    "Y" => "당첨",
  );

  $print_op2 = array(
    "N" => "미출력",
    "Y" => "완료",
  );

  $yn_op = array(
    "N" => "N",
    "Y" => "Y",
  );

  $withdraw_op = array(
    "N" => "예정",
    "D" => "취소",
    "Y" => "완료"
  );

  $search_date_op = array(
    "1D"  => "오늘",
    "7D"  => "일주일",
    "15D" => "15일",
    "1M"  => "1개월",
    "3M"  => "3개월",
    "6M"  => "6개월",
    "1Y"  => "1년"
  );

  $coupon_op = array(
    "O" => "1회",
    "D" => "다회"
  );

  $target_op = array(
    "_blank" => "_blank",
    "_self"  => "_self"
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
    "4" => "플래티넘"
  );

  $banner_level_op = array(
    "0" => "모든 고객",
    "1" => "비회원",
    "2" => "회원"
  );

  $gubuno_op = array(
    "M" => "수동",
    "A" => "자동",
    "A2" => "자동2",
    "A3" => "자동3",
    "S" => "내번호불러오기"
  );

  $smssender_op = array(
    "smsnori" => "문자노리",
    "moashot" => "모아샷",
    "ppurio"  => "뿌리오",
    "aligo"   => "알리고"
  );

  $use_op = array(
    "Y" => "사용",
    "N" => "중지"
  );

  $gender_op = array(
    "" => "",
    "M" => "남",
    "F" => "여"
  );

  $must_op = array(
    "Y" => "필수",
    "N" => "-"
  );

  $qna_category_op = array(
    "복권문의" => "복권문의",
    "캐시문의" => "캐시문의",
    "쿠폰문의" => "쿠폰문의",
    "출금문의" => "출금문의",
    "결제문의" => "결제문의",
    "기타" => "기타"
  );

  $country_op = array(
    "kr" => "한국",
    "os" => "외국"
  );

  $ranking_op = array(
    "1" => "1등",
    "2" => "2등",
    "3" => "3등",
    "4" => "4등",
    "5" => "5등",
    "6" => "6등",
    "7" => "7등",
    "8" => "8등",
    "9" => "9등",
  );

  $cash_op = array(
    array(
      "price"        => 6600,
      "ipoint"       => 0,
      "ipoint_text"  => "없음",
      "ipoint_text2" => "1게임 이용 가능",
      "cash"         => 6000,
    ),
    array(
      "price"        => 11000,
      "ipoint"       => 300,
      "ipoint_text"  => "+ 300 마일리지",
      "ipoint_text2" => "2게임 이용 가능",
      "cash"         => 10000,
    ),
    array(
      "price"        => 13200,
      "ipoint"       => 300,
      "ipoint_text"  => "+ 300 마일리지",
      "ipoint_text2" => "2게임 이용 가능",
      "cash"         => 12000,
    ),
    array(
      "price"        => 19800,
      "ipoint"       => 450,
      "ipoint_text"  => "+ 450 마일리지",
      "ipoint_text2" => "3게임 이용 가능",
      "cash"         => 18000,
    ),
    array(
      "price"        => 22000,
      "ipoint"       => 600,
      "ipoint_text"  => "+ 600 마일리지",
      "ipoint_text2" => "4게임 이용 가능",
      "cash"         => 20000,
    ),
    array(
      "price"        => 26400,
      "ipoint"       => 600,
      "ipoint_text"  => "+ 600 마일리지",
      "ipoint_text2" => "4게임 이용 가능",
      "cash"         => 24000,
    ),
    array(
      "price"        => 33000,
      "ipoint"       => 750,
      "ipoint_text"  => "+ 750 마일리지",
      "ipoint_text2" => "5게임 이용 가능",
      "cash"         => 30000,
    ),
    array(
      "price"        => 66000,
      "ipoint"       => 1500,
      "ipoint_text"  => "+ 1500 마일리지",
      "ipoint_text2" => "10게임 이용 가능",
      "cash"         => 60000,
    ),
    array(
      "price"        => 132000,
      "ipoint"       => 5000,
      "ipoint_text"  => "+ 5000 마일리지",
      "ipoint_text2" => "20게임 이용 가능",
      "cash"         => 120000,
    ),
    array(
      "price"        => 220000,
      "ipoint"       => 5000,
      "ipoint_text"  => "+ 5000 마일리지",
      "ipoint_text2" => "33게임 이용 가능",
      "cash"         => 200000,
    ),
    array(
      "price"        => 330000,
      "ipoint"       => 7500,
      "ipoint_text"  => "+ 7500 마일리지",
      "ipoint_text2" => "50게임 이용 가능",
      "cash"         => 300000,
    ),
    array(
      "price"        => 660000,
      "ipoint"       => 5000,
      "ipoint_text"  => "+ 5000 마일리지",
      "ipoint_text2" => "100게임 이용 가능",
      "cash"         => 600000,
    )
  );

  // SMS (LMS)
  $sms = $db->get_data("SELECT SENDER, HP FROM SMSCONFIG WHERE HOMEPAGE_USE = 'Y' LIMIT 1");
  $sms_sender = $sms["SENDER"];
  $fromHP     = $sms["HP"];
  unset($sms);

  if (!empty($_SERVER['REMOTE_ADDR'])) {
    $ip_address = $_SERVER['REMOTE_ADDR'];
  } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
  } else {
    $ip_address = $_SERVER['HTTP_CLIENT_IP'];
  }

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

  // csv 최대 출력 개수
  $csv_max_row = "300000";

  // csv 메모리
  $csv_memory = "2048M";

  // Set limit
  $limit = 20;
  if (isset($_GET['limit']) && $_GET['limit']) {
    $S_login['limit'] = $_GET['limit'];
    admin_save_session_login($S_login);
  }

  //비밀번호 확인용 정규표현식
  $pwRegexp = '/^(?=.*[a-zA-Z])(?=.*[0-9]).{8,20}$/';

  if (getSecondDifference($new_megamillion_draw_date) >= 0) {
    $megaPrizes = [
      0 => 0,
      1 => 50000000, // 시작 금액
      2 => 1000000,
      3 => 10000,
      4 => 500,
      5 => 200,
      6 => 10,
      7 => 10,
      8 => 7,
      9 => 5
    ];
  } else {
    $megaPrizes = [
      0 => 0,
      1 => 20000000, // 시작 금액
      2 => 1000000,
      3 => 10000,
      4 => 500,
      5 => 200,
      6 => 10,
      7 => 10,
      8 => 4,
      9 => 2
    ];
  }

  $powerPrizes = [
    0 => 0,
    1 => 20000000, // 시작 금액
    2 => 1000000,
    3 => 50000,
    4 => 100,
    5 => 100,
    6 => 7,
    7 => 7,
    8 => 4,
    9 => 4
  ];
?>
