<?php
require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";
require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_ORDERS.php";
require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_T_CASH_LOG.php";
require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_T_WCASH_LOG.php";
require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_T_POINT_LOG.php";
require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_T_IPOINT_LOG.php";

$VAL = $_POST;

// debug용 데이터 저장
// syslog(LOG_DEBUG, "[ORDER_\$VAL]=".print_r($VAL,true));

$good     = $db->get_data("SELECT * FROM GOODS WHERE GUBUN='{$VAL['GUBUN']}' AND CNT='1'");
$payment  = $good['PRICE2'] * $VAL['GAMECNT'];

$time_set = timesss($VAL['GUBUN']);

$mem      = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID='{$M_login['user_id']}' LIMIT 1");

// debug용 데이터 저장
// syslog(LOG_DEBUG, "[ORDER_USER_ID]=".$mem['USER_ID']);

if ($mem['USER_ID'] == '') {
  alert_print("존재하지 않은 회원입니다.");
  history_go(-1);
  exit;
}

if ($mem['WINCASH'] < $VAL['WINCASH'] && $VAL['WINCASH'] > 0) {
  alert_print("당첨금이 부족합니다.");
  history_go(-1);
  exit;
}

if ($mem['POINT'] < $VAL['POINT'] && $VAL['POINT'] > 0) {
  alert_print("보너스포인트가 부족합니다.");
  history_go(-1);
  exit;
}

if ($mem['IPOINT'] < $VAL['IPOINT'] && $VAL['IPOINT'] > 0) {
  alert_print("마일리지가 부족합니다.");
  history_go(-1);
  exit;
}

$tot_price = $payment - $VAL['POINT'] - $VAL['IPOINT'] - $VAL['WINCASH']; // 사용될 캐시

if ($tot_price < 0) {
  alert_print("입력한 보너스포인트와 마일리지가 결제금액을 초과하였습니다.");
  history_go(-1);
  exit;
}

if ($mem['CASH'] < $tot_price) {
  alert_print("캐시가 부족합니다.");
  history_go(-1);
  exit;
}

// SLK-356 값검증 추가
if ($payment == 0 || $time_set['gameNo'] == 0 || isset($time_set) == false || strlen($VAL['BALL1'])<=0) {
  alert_print("필수 데이터가 누락되었습니다.");
  history_go(-1);
  exit;
}

$VAL['CASH'] = $tot_price; // 로그 저장에 사용하는 결제 캐시

$CASH    = $VAL['CASH']; // 결제될 캐시
$WINCASH = $VAL['WINCASH']; // 결제될 당첨금
$IPOINT  = $VAL['IPOINT']; // 결제될 마일리지
$POINT   = $VAL['POINT']; // 결제될 보너스포인트

$GAMEINFO = ""; // 이메일 발송용 데이터

// 구매건 분할
for ($gcnt = 1; $gcnt <= $max_gameCount; $gcnt += 5) {
  // 게임값이 없으면 종료
  if ($VAL['BALL'.$gcnt] == "") break;

  // 게임수보다 크면 종료
  if ($gcnt > $VAL['GAMECNT']) break;

  // 구매건별 분할된 변수 초기화
  $split_gamecnt  = 0;
  $split_tot      = 0;
  $split_cash     = 0;
  $split_wincash  = 0;
  $split_ipoint   = 0;
  $split_point    = 0;

  // 게임번호
  for ($i = 1; $i < 6; $i++) {
    $gamecount = $gcnt + $i - 1;
    if (($VAL['BALL'.$gamecount] != '') && ($gamecount <= $VAL['GAMECNT'])) {
      $number_arr = explode(",", $VAL['BALL'.$gamecount]);

      $ballNumP = $number_arr[5];
      $ballNumD = array(
        $number_arr[0],
        $number_arr[1],
        $number_arr[2],
        $number_arr[3],
        $number_arr[4]
      );

      sort($ballNumD);

      // 이메일 발송용 데이터
      $ball['BALL'.$i] = implode(", ", $ballNumD);

      $ballNumD[5] = $ballNumP;
      $newVAL['BALL'.$i]  =  implode(",", $ballNumD);

      // 이메일 발송용 데이터
      $GAMEINFO .= $gamecount."번 게임: ".$ball['BALL'.$i]." / ".$ballNumP."\n";

      $split_gamecnt++;
    } else {
      $newVAL['BALL'.$i]  =  "";
    }
  }

  //__________________금액 계산 시작
  // 구매건 결제금액
  $split_payment = $split_tot = $good['PRICE2'] * $split_gamecnt;

  // 캐시결제금액
  if ($CASH > 0) {
    $split_cash = min($split_tot,$CASH); // 사용캐시
    $CASH -= $split_cash; // 사용캐시 차감
    $split_tot -= $split_cash; // 남은 금액
  }
  // 당첨금결제금액
  if ($WINCASH > 0) {
    $split_wincash = min($split_tot,$WINCASH); // 사용당첨금
    $WINCASH -= $split_wincash; // 사용당첨금 차감
    $split_tot -= $split_wincash; // 남은 금액
  }
  // 마일리지결제금액
  if ($IPOINT > 0) {
    $split_ipoint = min($split_tot,$IPOINT); // 사용마일리지
    $IPOINT -= $split_ipoint; // 사용마일리지 차감
    $split_tot -= $split_ipoint; // 남은 금액
  }
  // 보너스포인트결제금액
  if ($POINT > 0) {
    $split_point = min($split_tot,$POINT); // 사용보너스포인트
    $POINT -= $split_point; // 사용보너스포인트 차감
    $split_tot -= $split_point; // 남은 금액
  }
  //__________________금액 계산 완료

  // $pat = (isset($mem['PATNER_NO'])) ? $mem['PATNER_NO'] : 0;



  $newVAL['mode']         = "insert";
  $newVAL['USER_ID']      = $mem['USER_ID'];
  $newVAL['HP']           = $mem['HP'];
  $newVAL['EMAIL']        = $mem['EMAIL'];
  $newVAL['PAYMENT']      = $split_payment;
  $newVAL['CASH']         = $split_cash;
  $newVAL['WINCASH']      = $split_wincash;
  $newVAL['POINT']        = $split_point;
  $newVAL['IPOINT']       = $split_ipoint;
  $newVAL['VAT']          = 0;
  $newVAL['AUTHNUM']      = '';
  $newVAL['CARDCD']       = '';
  $newVAL['KIOSK_NO']     = $mem['KIOSK_NO'];
  $newVAL['AGENT_NO']     = 0;
  $newVAL['PATNER_NO']    = (isset($mem['PATNER_NO'])) ? $mem['PATNER_NO'] : 0;
  $newVAL['GUBUN']        = $VAL['GUBUN'];
  $newVAL['GUBUNO']       = $VAL['GUBUNO'];
  $newVAL['DRAWNUM']      = $time_set['gameNo'];
  $newVAL['PLAYDATE']     = $time_set['play_date'];
  $newVAL['GAMECNT']      = $split_gamecnt;
  $newVAL['WIN_YN']       = 'R';
  $newVAL['SELLER_GUBUN'] = 'PC';
  $newVAL['DATE']         = date("Y-m-d");
  $newVAL['ORDERS_NO']    = $db->get_data_one("SELECT MAX(ORDERS_NO) as ORDERS_NO FROM ORDERS") + 1;
  $newVAL['UNIQNUM']      = ($VAL['ORDERS_NO'] > 0) ? $VAL['ORDERS_NO'] : $newVAL['ORDERS_NO']; // 주문 고유값으로 처음 ORDERS_NO 저장

  if ($split_tot != 0) {
    syslog(LOG_DEBUG, "[ORDER_".$newVAL['ORDERS_NO']."] 금액오류!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!");
  }

  F_ORDERS($newVAL);

  // 로그 저장용 기준 ORDERS_NO
  if ($gcnt == 1) $VAL['ORDERS_NO'] = $newVAL['ORDERS_NO'];
}

// 캐시사용로그(차감)
$cash_log = array();
$cash_log['mode']         = "insert";
$cash_log['CASH_NO']      = 0;
$cash_log['ORDERS_NO']    = $VAL['ORDERS_NO'];
$cash_log['WINNING_NO']   = 0;
$cash_log['INVOCE_NO']    = 0;
$cash_log['CASH_LOG_NO']  = 0;
$cash_log['PRICE']        = 0;
$cash_log['CASH']         = $VAL['CASH'];
$cash_log['N_CASH']       = $mem['CASH'] - $VAL['CASH'];
$cash_log['O_CASH']       = $mem['CASH'];
$cash_log['USER_ID']      = $mem['USER_ID'];
$cash_log['MEMO']         = "티켓구매사용";
$cash_log['STATUS']       = "M";
F_T_CASH_LOG($cash_log);


if ($VAL['WINCASH'] > 0) {
  // 당첨금사용로그(차감)
  $wcash_log = array();
  $wcash_log['mode']      = "insert";
  $wcash_log['ORDERS_NO'] = $VAL['ORDERS_NO'];
  $wcash_log['WCASH']     = $VAL['WINCASH'];
  $wcash_log['N_WCASH']   = $mem['WINCASH'] - $VAL['WINCASH'];
  $wcash_log['O_WCASH']   = $mem['WINCASH'];
  $wcash_log['USER_ID']   = $mem['USER_ID'];
  $wcash_log['MEMO']      = "당첨금사용";
  $wcash_log['STATUS']    = "M";
  F_T_WCASH_LOG($wcash_log);
}

if ($VAL['POINT'] > 0) {
  $coupon = $db->get_data("SELECT * FROM COUPON_LOG WHERE MEMBER_NO='{$mem['MEMBER_NO']}' LIMIT 1");

  // 보유포인트사용로그(차감)
  $point_log = array();
  $point_log['mode']        = "insert";
  $point_log['COUPON_NO']   = $coupon['COUPON_NO'];
  $point_log['ORDERS_NO']   = $VAL['ORDERS_NO'];
  $point_log['CASH_LOG_NO'] = 0;
  $point_log['POINT']       = $VAL['POINT'];
  $point_log['N_POINT']     = $mem['POINT'] - $VAL['POINT'];
  $point_log['O_POINT']     = $mem['POINT'];
  $point_log['USER_ID']     = $mem['USER_ID'];
  $point_log['MEMO']        = "티켓구매사용";
  $point_log['STATUS']      = "M";
  F_T_POINT_LOG($point_log);
}

if ($VAL['IPOINT'] > 0) {
  // 마일리지사용로그(차감)
  $ipoint_log = array();
  $ipoint_log['mode']        = "insert";
  $ipoint_log['ORDERS_NO']   = $VAL['ORDERS_NO'];
  $ipoint_log['CASH_LOG_NO'] = 0;
  $ipoint_log['WINNING_NO']  = 0;
  $ipoint_log['IPOINT']      = $VAL['IPOINT'];
  $ipoint_log['N_IPOINT']    = $mem['IPOINT'] - $VAL['IPOINT'];
  $ipoint_log['O_IPOINT']    = $mem['IPOINT'];
  $ipoint_log['USER_ID']     = $mem['USER_ID'];
  $ipoint_log['MEMO']        = "티켓구매사용";
  $ipoint_log['STATUS']      = "M";
  F_T_IPOINT_LOG($ipoint_log);
}

$sql = "
  UPDATE MEMBER
  SET
    POINT    = POINT - {$VAL['POINT']},
    IPOINT   = IPOINT - {$VAL['IPOINT']},
    CASH     = CASH - {$VAL['CASH']},
    WINCASH  = WINCASH - {$VAL['WINCASH']},
    TOTPRICE = TOTPRICE + {$VAL['CASH']},
    TOTCNT   = TOTCNT + {$VAL['GAMECNT']}
  WHERE USER_ID  = '{$M_login['user_id']}'
";
$db->query($sql);

/* (Start) 마일리지 적립 */
$in_ipoint   = 0;
$in_ipoint_p = 0;

// 브라운 0.5,실버2.5, 골드 5, 플래티넘 8 마일리지 적립
// 적립대상 - CASH(캐시) + WINCASH(당첨금)
switch($mem['LEVEL']) {
  case 2:
    $in_ipoint   = ceil(($VAL['CASH'] + $VAL['WINCASH']) / 100 * 2.5);
    $in_ipoint_p = 2.5;
    break;
  case 3:
    $in_ipoint   = ceil(($VAL['CASH'] + $VAL['WINCASH']) / 100 * 5);
    $in_ipoint_p = 5;
    break;
  case 4:
    $in_ipoint   = ceil(($VAL['CASH'] + $VAL['WINCASH']) / 100 * 8);
    $in_ipoint_p = 8;
    break;
  default:
    $in_ipoint   = ceil(($VAL['CASH'] + $VAL['WINCASH']) / 100 * 0.5);
    $in_ipoint_p = 0.5;
    break;
}

if ($VAL['IPOINT'] == '') {
  $VAL['IPOINT'] = 0;
}

if ($in_ipoint > 0) {
  // 마일리지사용로그(적립)
  $in_ipoint_log = array();
  $in_ipoint_log['mode']      = "insert";
  $in_ipoint_log['ORDERS_NO'] = $VAL['ORDERS_NO'];
  $in_ipoint_log['IPOINT']    = $in_ipoint;
  $in_ipoint_log['N_IPOINT']  = $mem['IPOINT'] - $VAL['IPOINT'] + $in_ipoint;
  $in_ipoint_log['O_IPOINT']  = $mem['IPOINT'] - $VAL['IPOINT'];
  $in_ipoint_log['USER_ID']   = $mem['USER_ID'];
  $in_ipoint_log['MEMO']      = "티켓구매 회원레벨 적립 ".$in_ipoint_p."%";
  $in_ipoint_log['STATUS']    = "P";
  F_T_IPOINT_LOG($in_ipoint_log);
}

$sql = "
  UPDATE MEMBER
  SET IPOINT = IPOINT + {$in_ipoint}
  WHERE USER_ID = '{$M_login['user_id']}'
";
$db->query($sql);
/* (End) 마일리지 적립 */

/* (Start) SMS,EMAIL 발송 */
$TEMPLET_NO = 1;
$SMSTEMPLET = $db->get_data("SELECT * FROM SMSTEMPLET WHERE TEMPLET_NO='{$TEMPLET_NO}' LIMIT 1");

$ORDERTIME   = date("Y년 m월 d일 H시 i분");
$CURRENTHOUR = date('H');

// 오전 2시 이전인지 확인
if ($CURRENTHOUR < 2) {
  $SCANTIME = date("Y년 m월 d일");
} else {
  $SCANTIME = date("Y년 m월 d일", strtotime("+1 days"));
}

$from        = $fromHP;
$to          = $mem["HP"];
$CODESK      = "S";
$MEMBER_NO   = $mem['MEMBER_NO'];
$MEMBER_NAME = $mem['NAME'];
$SUBJECT     = $SMSTEMPLET['SUBJECT'];
$SENDMSG     = $SMSTEMPLET['CONTENT'];
$GUBUN_SMS   = ($VAL['GUBUN'] == 'MM') ? "메가밀리언" : "파워볼"; // 게임명
$GAMECNT_SMS = $VAL['GAMECNT']; // 게임수
$DRAWNUM_SMS = $time_set['gameNo']; // 회차
$SENDMSG     = addslashes($SENDMSG);
$SENDMSG     = str_replace('{GUBUN}', $GUBUN_SMS, $SENDMSG);
$SENDMSG     = str_replace('{GAMENO}', $DRAWNUM_SMS, $SENDMSG);
$SENDMSG     = str_replace('{GAMECNT}', $GAMECNT_SMS, $SENDMSG);
$sms         = curl_sms($to, $from, $CODESK, $TEMPLET_NO, $MEMBER_NO, $MEMBER_NAME, $SUBJECT, $SENDMSG);

if ($mem['EMAIL'] != '') {
  $TEMPLET_NO   = 7;
  $EMAILTEMPLET = $db->get_data("SELECT * FROM EMAILTEMPLET WHERE TEMPLET_NO='{$TEMPLET_NO}' LIMIT 1");
  $to           = $mem['EMAIL'];
  $subject      = $EMAILTEMPLET['SUBJECT'];
  $content      = $EMAILTEMPLET['CONTENT'];
  $content      = addslashes($content);
  $content      = str_replace('{GUBUN}', $GUBUN_SMS, $content);
  $content      = str_replace('{GAMENO}', $DRAWNUM_SMS, $content);
  $content      = str_replace('{ORDERTIME}', $ORDERTIME, $content);
  $content      = str_replace('{SCANTIME}', $SCANTIME, $content);
  $content      = str_replace('{GAMEINFO}', $GAMEINFO, $content);
  $type         = "2";

  if ($EMAILTEMPLET['STOPYN'] == 'N') {
    // mailer($fname, $fmail, $to, $subject, $content, $type, $mem['NAME'], $mem['MEMBER_NO'], $TEMPLET_NO);
    // AWS SES 클라이언트 생성
    $sesClient = initializeSesClient();
    if ($sesClient) {
      sendMail($sesClient, $fname, $fmail, $to, $subject, $content, $type, $mem['NAME'], $mem['MEMBER_NO'], $TEMPLET_NO);
    }
  }
}
/* (End) SMS,EMAIL 발송 */

alert_print($GUBUN_SMS." ".$DRAWNUM_SMS."회차 [".$GAMECNT_SMS.
  "]게임 구매 신청이 완료되었습니다.\\n\\n[마이페이지 -> 구매내역확인]에서 선택하신 번호를 확인할 수 있습니다.\\n\\n상세내역은 이메일을 확인해주세요.");

meta_go("/contents/mypage/history.html");
?>
