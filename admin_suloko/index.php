<?php
$time1 = microtime(true);
require_once $_SERVER['DOCUMENT_ROOT']."/inc/meta.html";
require_once $_SERVER['DOCUMENT_ROOT']."/inc/header.html";
include $_SERVER['DOCUMENT_ROOT']."/_library/function_ORDERS.php";

// 등급별 인덱스 분리
if ($indexurl != "/index.php") {
  meta_go($indexurl);
  exit;
}

$time2 = microtime(true);

// 오늘 날자
$sdate0      = date("Y-m-01")." 00:00:00";
$sdate       = date("Y-m-d")." 00:00:00";
$edate       = date("Y-m-d")." 23:59:59";

// 전일 날자
$y_sdate     = date("Y-m-d", strtotime("-1 day"))." 00:00:00";
$y_edate     = date("Y-m-d", strtotime("-1 day"))." 23:59:59";
$ym_sdate    = date("Y-m-01", strtotime("first day of -1 month"))." 00:00:00";
$ym_edate    = date("Y-m-t", strtotime("first day of -1 month"))." 23:59:59";
$ymn_edate   = date("Y-m-d H:i:s", strtotime("-1 month"));
// 전월의 현재시간이 없을경우 ymn_edate는 이번달의 1일값을 가진다.
// 결국 ymn_edate(전월현시간) > ym_edate(전월마지막날) 경우는 마지막날값으로 변경함
if (strtotime($ymn_edate) > strtotime($ym_edate)) {
  $ymn_edate = $ym_edate;
}
$yn_edate = date("Y-m-d H:i:s", strtotime("-1 day"));

// -7일 날짜 (2023-01-11/chabes)
$y_sdate_1w  = date("Y-m-d", strtotime("-7 day"))." 00:00:00";
$y_edate_1w  = date("Y-m-d", strtotime("-7 day"))." 23:59:59";
$yn_edate_1w = date("Y-m-d H:i:s", strtotime("-7 day"));

// 오늘 매출 (22만: 2026-08-07 이후 카드 220000만 제외 / 12만: 132000 제외)
$hide_sales_filter = is_admin205_user() ? '' : get_admin204_main_sales_filter();
$now_sales   = $db->get_data("SELECT SUM(PRICE) AS PRICE, COUNT(PRICE) AS CNT FROM CASH WHERE CON_REG_DATE BETWEEN '".$sdate."' AND '".$edate."' AND IS_USE='Y'".$hide_sales_filter);
$month_sales = $db->get_data("SELECT SUM(PRICE) AS PRICE, COUNT(PRICE) AS CNT FROM CASH WHERE CON_REG_DATE BETWEEN '".$sdate0."' AND '".$edate."' AND IS_USE='Y'".$hide_sales_filter);

// 1일 ~ 전일까지 매출 (22만: 2026-08-07 이후 카드 220000만 제외 / 12만: 132000 제외)
$month_sales_avg = $db->get_data("SELECT SUM(PRICE) AS PRICE, COUNT(PRICE) AS CNT FROM CASH WHERE CON_REG_DATE BETWEEN '".$sdate0."' AND '".$y_edate."' AND IS_USE='Y'".$hide_sales_filter);


$y_sales   = $db->get_data("SELECT SUM(PRICE) AS PRICE, COUNT(PRICE) AS CNT FROM CASH WHERE CON_REG_DATE BETWEEN '".$y_sdate."' AND '".$y_edate."' AND IS_USE='Y'".$hide_sales_filter);
$yn_sales  = $db->get_data("SELECT SUM(PRICE) AS PRICE, COUNT(PRICE) AS CNT FROM CASH WHERE CON_REG_DATE BETWEEN '".$y_sdate."' AND '".$yn_edate."' AND IS_USE='Y'".$hide_sales_filter);
$ym_sales  = $db->get_data("SELECT SUM(PRICE) AS PRICE, COUNT(PRICE) AS CNT FROM CASH WHERE CON_REG_DATE BETWEEN '".$ym_sdate."' AND '".$ym_edate."' AND IS_USE='Y'".$hide_sales_filter);
$ymn_sales = $db->get_data("SELECT SUM(PRICE) AS PRICE, COUNT(PRICE) AS CNT FROM CASH WHERE CON_REG_DATE BETWEEN '".$ym_sdate."' AND '".$ymn_edate."' AND IS_USE='Y'".$hide_sales_filter);

$time3 = microtime(true);

// -7일 매출 (2023-01-11/chabes)
$y_sales_1w           = $db->get_data("SELECT SUM(PRICE) AS PRICE, COUNT(PRICE) AS CNT FROM CASH WHERE CON_REG_DATE BETWEEN '".$y_sdate_1w."' AND '".$y_edate_1w."' AND IS_USE='Y'".$hide_sales_filter);
$yn_sales_1w          = $db->get_data("SELECT SUM(PRICE) AS PRICE, COUNT(PRICE) AS CNT FROM CASH WHERE CON_REG_DATE BETWEEN '".$y_sdate_1w."' AND '".$yn_edate_1w."' AND IS_USE='Y'".$hide_sales_filter);
$y_sales_1w_winmoney  = $db->get_data("SELECT SUM(WINCASH) AS WINMONEY FROM ORDERS WHERE REG_DATE BETWEEN '".$y_sdate_1w."' AND '".$y_edate_1w."' AND WINCASH > 0 AND SIGN_YN != 'C'");
$yn_sales_1w_winmoney = $db->get_data("SELECT SUM(WINCASH) AS WINMONEY FROM ORDERS WHERE REG_DATE BETWEEN '".$y_sdate_1w."' AND '".$yn_edate_1w."' AND WINCASH > 0 AND SIGN_YN != 'C'");

// //-7일 당첨금 (2023-01-11/chabes)

//오늘 당첨금 사용
$now_sales_winmoney = $db->get_data("SELECT SUM(WINCASH) AS WINMONEY FROM ORDERS WHERE REG_DATE BETWEEN '".$sdate."' AND '".$edate."' AND WINCASH > 0 AND SIGN_YN != 'C'");
$y_sales_winmoney   = $db->get_data("SELECT SUM(WINCASH) AS WINMONEY FROM ORDERS WHERE REG_DATE BETWEEN '".$y_sdate."' AND '".$y_edate."' AND WINCASH > 0 AND SIGN_YN != 'C'");
$yn_sales_winmoney  = $db->get_data("SELECT SUM(WINCASH) AS WINMONEY FROM ORDERS WHERE REG_DATE BETWEEN '".$y_sdate."' AND '".$yn_edate."' AND WINCASH > 0 AND SIGN_YN != 'C'");

$time4 = microtime(true);

// 회원가입수
$today_member_count      = $db->get_data_one("SELECT COUNT(*) AS CNT FROM MEMBER WHERE REG_DATE BETWEEN '".$sdate."' AND '".$edate."'"); // 오늘 가입수
$month_member_count      = $db->get_data_one("SELECT COUNT(*) AS CNT FROM MEMBER WHERE REG_DATE BETWEEN '".$sdate0."' AND '".$edate."'"); // 이번달 가입수
$yda_member_count        = $db->get_data_one("SELECT COUNT(*) AS CNT FROM MEMBER WHERE REG_DATE BETWEEN '".$y_sdate."' AND '".$y_edate."'"); // 전일 가입수
$ydan_member_count       = $db->get_data_one("SELECT COUNT(*) AS CNT FROM MEMBER WHERE REG_DATE BETWEEN '".$y_sdate."' AND '".$yn_edate."'"); // 전일 동시간 가입수
$prev_week_member_count  = $db->get_data_one("SELECT COUNT(*) AS CNT FROM MEMBER WHERE REG_DATE BETWEEN '".$y_sdate_1w."' AND '".$y_edate_1w."'"); // 7일전 가입수
$prev_month_member_count = $db->get_data_one("SELECT COUNT(*) AS CNT FROM MEMBER WHERE REG_DATE BETWEEN '".$ym_sdate."' AND '".$ym_edate."'"); // 전월 가입수

// 전체 회원가입수
$total_cnt  =  $db->get_data_one("SELECT COUNT(*) AS CNT FROM MEMBER WHERE 1");

if (!isset($today_member_count)) $today_member_count = 0;
if (!isset($total_cnt)) $total_cnt = 0;
if ($today_member_count == '') $today_member_count = 0;
if ($total_cnt == '') $total_cnt = 0;

// 탈퇴회원 (SLK-490)
$member_out_all_cnt = $db->get_data_one("SELECT COUNT(MEMBER_NO) AS CNT FROM OUT_MEMBER");

// 오늘 탈퇴 회원수
$member_out_today_cnt = $db->get_data_one("SELECT COUNT(MEMBER_NO) AS CNT FROM OUT_MEMBER WHERE REG_DATE BETWEEN '".$sdate."' AND '".$edate."'");

// 전일탈퇴 회원수
$member_out_yes_cnt = $db->get_data_one("SELECT COUNT(MEMBER_NO) AS CNT FROM OUT_MEMBER WHERE REG_DATE BETWEEN '".$y_sdate."' AND '".$y_edate."'");

$time5_1 = microtime(true);

// 메가밀리언, 파워볼, 전체
$mega   = array();
$power  = array();
$total  = array();

$list = $db->get_list("
  SELECT GUBUN, DATE, SUM(PAYMENT) AS PAYMENT
  FROM ORDERS
  WHERE REG_DATE > (CURDATE()-INTERVAL 5 DAY) AND SIGN_YN != 'C'
  GROUP BY GUBUN, DATE
  ORDER BY DATE DESC, GUBUN ASC
");

$j = 0;
for ($i = 0; $i < 5; $i++) {
  $mega[$i] = array();
  $mega[$i]['date'] = $list['DATE'][$j];
  $mega[$i]['price'] = $list['PAYMENT'][$j];

  $power[$i] = array();
  $power[$i]['date'] = $list['DATE'][($j+1)];
  $power[$i]['price'] = $list['PAYMENT'][($j+1)];

  $total[$i]  = array();
  $total[$i]['date'] = $list['DATE'][($j)];
  $total[$i]['price'] = $list['PAYMENT'][$j] + $list['PAYMENT'][($j+1)];
  $j+=2;
}

$time5 = microtime(true);

// 최근 구매내역
$list = F_ORDERS_list(array(
  "row"         => 50,
  "page"        => 1,
  "order"       => $order,
  "find_text"   => $find_text,
  "find_object" => $find_object,
  "add_query"   => " AND SIGN_YN != 'C'",
));

if ($list['total'] == 0) {
  $list['ORDERS_NO'] = array();
}
?>

<?php
// 매출 퍼센트(2023-01-11/chabes)
function getPercent($s, $e) {
  if ($e == 0 || is_null($e)) return 0;
  $val['N'] = $e - $s;
  $val['P'] = round(($s/$e) * 100 ,1);
  $val['P'] .= "%";
  return $val;
}

function act($v, $s) {
  if ($v > 0) {
    $val = "<font style='font-size:8pt;color:red'>";
    if ($s != "Y") {
      $val .= "▲";
    }
    $val .= $v."</font>";
  } else if ($v < 0 ) {
    $val = "<font style='font-size:8pt;color:blue'>";
    if ($s != "Y") {
      $val .= "▼";
    }
    $val .= $v."</font>";
  } else {
    $val = "<font style='font-size:8pt;color:#000000'>-</font>";
  }
  return $val;
}

/**  오늘 매출 + 당첨금 사용 */
// 전일
$Sales_point_today = $now_sales['PRICE'] + $now_sales_winmoney['WINMONEY'];
$Sales_point_yesterday_1 = $yn_sales['PRICE'] + $yn_sales_winmoney['WINMONEY'];
$Sales_point_yesterday_total = $y_sales['PRICE'] + $y_sales_winmoney['WINMONEY'];

// 7일
$Sales_point_1w_today = $now_sales['PRICE'] + $now_sales_winmoney['WINMONEY'];
$Sales_point_1w_yesterday_1 = $yn_sales_1w['PRICE'] + $yn_sales_1w_winmoney['WINMONEY'];
$Sales_point_1w_yesterday_total = $y_sales_1w['PRICE'] + $y_sales_1w_winmoney['WINMONEY'];

$v_sales_point_1 = getPercent($Sales_point_yesterday_1, $Sales_point_today);
$v_sales_point_2 = getPercent($Sales_point_yesterday_total, $Sales_point_today);
$v_sales_point_3 = getPercent($Sales_point_1w_yesterday_1, $Sales_point_today);
$v_sales_point_4 = getPercent($Sales_point_1w_yesterday_total, $Sales_point_today);

/** 오늘의 매출 */
$Sales_today = $now_sales['PRICE'];
$Sales_yesterday_1 = $yn_sales['PRICE'];
$Sales_yesterday_total = $y_sales['PRICE'];

// 7일
$Sales_1w_today = $now_sales['PRICE'];
$Sales_1w_yesterday_1 = $yn_sales_1w['PRICE'];
$Sales_1w_yesterday_total = $y_sales_1w['PRICE'];

$v_sales_1 = getPercent($Sales_yesterday_1, $Sales_today);
$v_sales_2 = getPercent($Sales_yesterday_total, $Sales_today);
$v_sales_3 = getPercent($Sales_1w_yesterday_1, $Sales_today);
$v_sales_4 = getPercent($Sales_1w_yesterday_total, $Sales_today);


/** 이달의 매출 */
$v_sales_monthly_1 = getPercent($ymn_sales['PRICE'], $month_sales['PRICE']);
$v_sales_monthly_1_count = getPercent($ymn_sales['CNT'], $month_sales['CNT']);
$v_sales_monthly_2 = getPercent($ym_sales['PRICE'], $month_sales['PRICE']);
$v_sales_monthly_2_count = getPercent($ym_sales['CNT'], $month_sales['CNT']);


/** 제휴 결제 건수/금액 */
// 오늘
$query = "SELECT SUM(C.PRICE) AS PRICE, COUNT(*) AS CNT FROM CASH AS C INNER JOIN MEMBER AS M ON C.USER_ID = M.USER_ID WHERE ";
$whereis = " C.CON_REG_DATE BETWEEN '".$sdate."' AND '".$edate."' AND C.IS_USE='Y' AND `REFERRAL_NO` > 0 ";
$now_refer_order = $db->get_data($query.$whereis);

// 전일
$whereis = " C.CON_REG_DATE BETWEEN '".$y_sdate."' AND '".$y_edate."' AND C.IS_USE='Y' AND `REFERRAL_NO` > 0 ";
$yes_refer_order = $db->get_data($query.$whereis);

// 전일비율
$yes_refer_order_count_1 = getPercent($yes_refer_order['CNT'], $now_refer_order['CNT']);
$yes_refer_order_amount_1 = getPercent($yes_refer_order['PRICE'], $now_refer_order['PRICE']);

// 당월
$whereis = " C.CON_REG_DATE BETWEEN '".$sdate0."' AND '".$edate."' AND C.IS_USE='Y' AND `REFERRAL_NO` > 0 ";
$month_refer_order = $db->get_data($query.$whereis);

/** 제휴 누적 결제금액/건수 */
$whereIs = " C.IS_USE='Y' AND REFERRAL_NO > 0";
$ref_price = $db->get_data($query.$whereIs);

/** 일평균 매출익 */
// 저번달 말일
$d = mktime(0,0,0, date("m"), 1, date("Y"));
$prev_month = strtotime("-1 month", $d); // 한달전
$prev_day = date("t", $prev_month); // 지난달 말일

$time6 = microtime(true);

// echo $month_sales_avg['PRICE']."/".date('j', strtotime('-1 day'));
$avg_this_month_amount = round($month_sales_avg['PRICE'] / (int)date('j', strtotime('-1 day')), 0);
$avg_this_month_cnt = round($month_sales_avg['CNT'] / (int)date('j', strtotime('-1 day')), 0);

// 전월
$avg_last_month_amount = round($ym_sales['PRICE'] / (int)$prev_day, 0);
$avg_last_month_cnt = round($ym_sales['CNT'] / (int)$prev_day, 0);

// 증감
$avg_last_month_amount_1 = getPercent($avg_last_month_amount, $avg_this_month_amount);
$avg_last_month_cnt_1 = getPercent($avg_last_month_cnt, $avg_this_month_cnt);

$time7 = microtime(true);
?>

<!--app-content open-->
<div class="app-content">
  <div class="side-app">
    <!-- ROW-1 -->
    <div class="row" style="padding-top: 15px">
      <div class="col-lg-12 col-md-12 col-sm-12 col-xl-12">
        <div class="row">
          <?php
            // 전체 동의 회원 수
            $query = "SELECT COUNT(DISTINCT(USER_ID)) FROM MEMBER_TOKEN";
            $token_member_total = $db->get_data_one($query);
            // 3일간 알림 동의 회원 수
            $query = "
              SELECT LEFT(CREATED_AT,10) AS CREATED_AT,COUNT(*) AS CNT
              FROM (SELECT USER_ID, MIN(CREATED_AT) AS CREATED_AT FROM MEMBER_TOKEN GROUP BY USER_ID) GROUP_USER
              WHERE CREATED_AT > (CURDATE() - INTERVAL 2 DAY)
              GROUP BY LEFT(CREATED_AT,10)
              ORDER BY CREATED_AT DESC
            ";
            $token_member = $db->get_list($query);
            // 선택 날짜의 회원 cnt
            function select_token_member_cnt($sdate) {
              global $token_member;
              for ($i = 0; $i < count($token_member); $i++) {
                if ($token_member['CREATED_AT'][$i] == $sdate) {
                  return $token_member['CNT'][$i];
                }
              }
              return 0;
            }
          ?>
          <div class="col-lg-6 col-md-12 col-sm-12 col-xl-4">
            <div class="card overflow-hidden">
              <div class="card-header">
                <h3 class="card-title">앱 2.0 알림 동의</h3>
              </div>
              <div class="card-body">
                <div class="row">
                  <div class="col-6" style="display: flex; align-items: baseline;">
                    <div class="pe-2" style="font-size:9pt">총</div>
                    <h6 class="mb-2 number-font"><?=number_format($token_member_total)?> 명</h6>
                  </div>
                  <div class="col-6" style="display: flex; align-items: baseline;">
                    <div class="pe-2" style="font-size:9pt">전일</div>
                    <h6 class="mb-2 number-font"><?=number_format(select_token_member_cnt(date("Y-m-d", strtotime("-1 day"))))?> 명</h6>
                  </div>
                </div>
                <div class="row">
                  <div class="col-6" style="display: flex; align-items: baseline;">
                    <div class="pe-2" style="font-size:9pt">오늘</div>
                    <h6 class="mb-2 number-font"><?=number_format(select_token_member_cnt(date("Y-m-d")))?> 명</h6>
                  </div>
                  <div class="col-6" style="display: flex; align-items: baseline;">
                    <div class="pe-2" style="font-size:9pt">3일전</div>
                    <h6 class="mb-2 number-font"><?=number_format(select_token_member_cnt(date("Y-m-d", strtotime("-2 day"))))?> 명</h6>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-lg-6 col-md-12 col-sm-12 col-xl-4">
            <div class="card overflow-hidden">
              <div class="card-body">
                <div class="row">
                  <div class="col-12">
                    <div style="font-size:9pt">오늘 매출 + 당첨금 사용 <i class="ti-help-alt" data-bs-toggle="tooltip" title="카드+무통장+당첨금 합계금액"></i></div>
                    <h6 style="font-size:16pt;font-weight:bold">₩ <?php echo number_format($now_sales['PRICE'] + $now_sales_winmoney['WINMONEY']); ?></h6>
                    <hr></hr>
                  </div>
                  <div class="col-6">
                    <div style="font-size:9pt">전일 동시간</div>
                    <h6 class="mb-2 number-font">₩ <?php echo number_format($yn_sales['PRICE'] + $yn_sales_winmoney['WINMONEY']); ?><br>(<?=act(number_format($v_sales_point_1["N"]), "")?>)</h6>
                  </div>
                  <div class="col-6">
                    <div style="font-size:9pt">전일 최종</div>
                    <h6 class="mb-1 number-font">₩ <?php echo number_format($y_sales['PRICE'] + $y_sales_winmoney['WINMONEY']); ?><br>(<?=act(number_format($v_sales_point_2["N"]), "")?>)</h6>
                  </div>
                  <div class="col-6">
                    <div style="font-size:9pt">7일전 동시간</div>
                    <h6 class="mb-2 number-font">₩ <?php echo number_format($yn_sales_1w['PRICE'] + $yn_sales_1w_winmoney['WINMONEY']); ?><br>(<?=act(number_format($v_sales_point_3["N"]), "")?>)</h6>
                  </div>
                  <div class="col-6">
                    <div style="font-size:9pt">7일전 최종</div>
                    <h6 class="mb-2 number-font">₩ <?php echo number_format($y_sales_1w['PRICE'] + $y_sales_1w_winmoney['WINMONEY']); ?><br>(<?=act(number_format($v_sales_point_4["N"]), "")?>)</h6>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-lg-6 col-md-12 col-sm-12 col-xl-4">
            <div class="card overflow-hidden">
              <div class="card-body">
                <div class="row">
                  <div class="col-12">
                    <div style="font-size:9pt">오늘의 매출 <i class="ti-help-alt" data-bs-toggle="tooltip" title="카드+무통장 합계금액"></i></div>
                    <h6 style="font-size:16pt;font-weight:bold">₩ <?=number_format($now_sales['PRICE'])?></h6>
                    <hr></hr>
                  </div>
                  <div class="col-6">
                    <div style="font-size:9pt">전일 동시간 매출</div>
                    <h6 class="mb-2 number-font">₩ <?=number_format($yn_sales['PRICE'])?><br>(<?=act(number_format($v_sales_1["N"]), "")?>)</h6>
                    <div style="font-size:9pt">7일전 동시간</div>
                    <h6 class="mb-2 number-font">₩ <?=number_format($yn_sales_1w['PRICE'])?><br>(<?=act(number_format($v_sales_3["N"]), "")?>)</h6>
                  </div>
                  <div class="col-6">
                    <div style="font-size:9pt">전일 매출</div>
                    <h6 class="mb-2 number-font">₩ <?=number_format($y_sales['PRICE'])?><br>(<?=act(number_format($v_sales_2["N"]), "")?>)</h6>
                    <div style="font-size:9pt">7일전 최종</div>
                    <h6 class="mb-2 number-font">₩ <?=number_format($y_sales_1w['PRICE'])?><br>(<?=act(number_format($v_sales_4["N"]), "")?>)</h6>
                  </div>
                  <div class="col-6"></div>
                </div>
              </div>
            </div>
          </div>
          <?php
            // 첫결제, 재결제 (22만: 2026-08-07 이후 카드 220000만 제외)
            $hide_sales_filter = is_admin205_user() ? '' : get_admin204_main_sales_filter();
            $td_sales_summary = $db->get_data("
              SELECT
                SUM(CASE WHEN CASH_CNT = 1 THEN PRICE END) AS FIRST_PRICE,
                SUM(CASE WHEN CASH_CNT = 1 THEN 1 END) AS FIRST_COUNT,
                SUM(CASE WHEN CASH_CNT > 1 THEN PRICE END) AS RECUR_PRICE,
                SUM(CASE WHEN CASH_CNT > 1 THEN 1 END) AS RECUR_COUNT
              FROM CASH
              WHERE CON_REG_DATE > '{$sdate}' AND IS_USE = 'Y'{$hide_sales_filter}
            ");

            $yd_sales_summary = $db->get_data("
              SELECT
                SUM(CASE WHEN CASH_CNT = 1 THEN PRICE END) AS FIRST_PRICE,
                SUM(CASE WHEN CASH_CNT = 1 THEN 1 END) AS FIRST_COUNT,
                SUM(CASE WHEN CASH_CNT > 1 THEN PRICE END) AS RECUR_PRICE,
                SUM(CASE WHEN CASH_CNT > 1 THEN 1 END) AS RECUR_COUNT
              FROM CASH
              WHERE CON_REG_DATE BETWEEN '{$y_sdate}' AND '{$yn_edate}' AND IS_USE = 'Y'{$hide_sales_filter}
            ");

            $w1_sales_summary = $db->get_data("
              SELECT
                SUM(CASE WHEN CASH_CNT = 1 THEN PRICE END) AS FIRST_PRICE,
                SUM(CASE WHEN CASH_CNT = 1 THEN 1 END) AS FIRST_COUNT,
                SUM(CASE WHEN CASH_CNT > 1 THEN PRICE END) AS RECUR_PRICE,
                SUM(CASE WHEN CASH_CNT > 1 THEN 1 END) AS RECUR_COUNT
              FROM CASH
              WHERE CON_REG_DATE BETWEEN '{$y_sdate_1w}' AND '{$yn_edate_1w}' AND IS_USE = 'Y'{$hide_sales_filter}
            ");

            // 첫결제, 재결제 비율
            if (($td_sales_summary["FIRST_PRICE"] + $td_sales_summary["RECUR_PRICE"]) != 0) {
              $td_first_sales_p = $td_sales_summary["FIRST_PRICE"] / ($td_sales_summary["FIRST_PRICE"] + $td_sales_summary["RECUR_PRICE"]) * 100;
              $td_recur_sales_p = $td_sales_summary["RECUR_PRICE"] / ($td_sales_summary["FIRST_PRICE"] + $td_sales_summary["RECUR_PRICE"]) * 100;
            } else {
              $td_first_sales_p = $td_recur_sales_p = 0;
            }

            if (($yd_sales_summary["FIRST_PRICE"] + $yd_sales_summary["RECUR_PRICE"]) != 0) {
              $yd_first_sales_p = $yd_sales_summary["FIRST_PRICE"] / ($yd_sales_summary["FIRST_PRICE"] + $yd_sales_summary["RECUR_PRICE"]) * 100;
              $yd_recur_sales_p = $yd_sales_summary["RECUR_PRICE"] / ($yd_sales_summary["FIRST_PRICE"] + $yd_sales_summary["RECUR_PRICE"]) * 100;
            } else {
              $yd_first_sales_p = $yd_recur_sales_p = 0;
            }

            if (($w1_sales_summary["FIRST_PRICE"] + $w1_sales_summary["RECUR_PRICE"]) != 0) {
              $w1_first_sales_p = $w1_sales_summary["FIRST_PRICE"] / ($w1_sales_summary["FIRST_PRICE"] + $w1_sales_summary["RECUR_PRICE"]) * 100;
              $w1_recur_sales_p = $w1_sales_summary["RECUR_PRICE"] / ($w1_sales_summary["FIRST_PRICE"] + $w1_sales_summary["RECUR_PRICE"]) * 100;
            } else {
              $w1_first_sales_p = $w1_recur_sales_p = 0;
            }

            // 전일대비
            $p_first_sales = getPercent($yd_sales_summary["FIRST_PRICE"], $td_sales_summary['FIRST_PRICE']);
            $p_first_sales = index_act(number_format($p_first_sales['N']), "");

            $p_recur_sales = getPercent($yd_sales_summary["RECUR_PRICE"], $td_sales_summary['RECUR_PRICE']);
            $p_recur_sales = index_act(number_format($p_recur_sales['N']), "");

            // 전주대비
            $w_first_sales = getPercent($w1_sales_summary["FIRST_PRICE"], $td_sales_summary['FIRST_PRICE']);
            $w_first_sales = index_act(number_format($w_first_sales['N']), "");

            $w_recur_sales = getPercent($w1_sales_summary["RECUR_PRICE"], $td_sales_summary['RECUR_PRICE']);
            $w_recur_sales = index_act(number_format($w_recur_sales['N']), "");
          ?>
          <div class="col-lg-6 col-md-12 col-sm-12 col-xl-4">
            <div class="card overflow-hidden">
              <div class="card-body">
                <div class="row">
                  <div class="col-6">
                    <div style="font-size:9pt"> 오늘의 첫결제금액</div>
                    <h6 style="font-size:14pt;font-weight:bold">₩ <?=number_format($td_sales_summary['FIRST_PRICE'])?> (<?=number_format($td_first_sales_p,2)?>%)</h6>
                    <div style="font-size:9pt">오늘의 첫결제수</div>
                    <h6 class="mb-2 number-font"><?=number_format($td_sales_summary['FIRST_COUNT'])?><hr></h6>
                  </div>
                  <div class="col-6">
                    <div style="font-size:9pt">오늘의 재결제금액</div>
                    <h6 style="font-size:14pt;font-weight:bold">₩ <?=number_format($td_sales_summary['RECUR_PRICE'])?> (<?=number_format($td_recur_sales_p,2)?>%)</h6>
                    <div style="font-size:9pt">오늘의 재결제수</div>
                    <h6 class="mb-2 number-font"><?=number_format($td_sales_summary['RECUR_COUNT'])?><hr></h6>
                  </div>
                  <div class="col-6">
                    <div style="font-size:9pt">전일 동시간 첫결제금액</div>
                    <h6 class="mb-2 number-font">₩ <?=number_format($yd_sales_summary['FIRST_PRICE'])?> (<?=number_format($yd_first_sales_p,2)?>%)<br>(<?=$p_first_sales?>)</h6>
                    <div style="font-size:9pt">전일 동시간 첫결제수</div>
                    <h6 class="mb-2 number-font"><?=number_format($yd_sales_summary['FIRST_COUNT'])?></h6>
                  </div>
                  <div class="col-6">
                    <div style="font-size:9pt">전일 동시간 재결제금액</div>
                    <h6 class="mb-2 number-font">₩ <?=number_format($yd_sales_summary['RECUR_PRICE'])?> (<?=number_format($yd_recur_sales_p,2)?>%)<br>(<?=$p_recur_sales?>)</h6>
                    <div style="font-size:9pt">전일 동시간 재결제수</div>
                    <h6 class="mb-2 number-font"><?=number_format($yd_sales_summary['RECUR_COUNT'])?></h6>
                  </div>
                  <div class="col-6">
                    <div style="font-size:9pt">7일전 동시간 첫결제금액</div>
                    <h6 class="mb-2 number-font">₩ <?=number_format($w1_sales_summary['FIRST_PRICE'])?> (<?=number_format($w1_first_sales_p,2)?>%)<br>(<?=$w_first_sales?>)</h6>
                    <div style="font-size:9pt">7일전 동시간 첫결제수</div>
                    <h6 class="mb-2 number-font"><?=number_format($w1_sales_summary['FIRST_COUNT'])?></h6>
                  </div>
                  <div class="col-6">
                    <div style="font-size:9pt">7일전 동시간 재결제금액</div>
                    <h6 class="mb-2 number-font">₩ <?=number_format($w1_sales_summary['RECUR_PRICE'])?> (<?=number_format($w1_recur_sales_p,2)?>%)<br>(<?=$w_recur_sales?>)</h6>
                    <div style="font-size:9pt">7일전 동시간 재결제수</div>
                    <h6 class="mb-2 number-font"><?=number_format($w1_sales_summary['RECUR_COUNT'])?></h6>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <?php
            // 당월 당첨금 사용
            // SELECT SUM(CASH) AS WINMONEY FROM T_CASH_LOG WHERE MEMO = '당첨금 사용' AND STATUS = 'M' AND
            $month_sales_winmoney = $db->get_data("SELECT SUM(WINCASH) AS WINMONEY, COUNT(*) AS WINMONEY_CNT FROM ORDERS WHERE REG_DATE BETWEEN '".$sdate0."' AND '".$edate."' AND WINCASH > 0 AND SIGN_YN != 'C'"); // 당월
            $ymn_sales_winmoney   = $db->get_data("SELECT SUM(WINCASH) AS WINMONEY, COUNT(*) AS WINMONEY_CNT FROM ORDERS WHERE REG_DATE BETWEEN '".$ym_sdate."' AND '".$ymn_edate."' AND WINCASH > 0 AND SIGN_YN != 'C'"); // 전월동시간
            $ym_sales_winmoney    = $db->get_data("SELECT SUM(WINCASH) AS WINMONEY, COUNT(*) AS WINMONEY_CNT FROM ORDERS WHERE REG_DATE BETWEEN '".$ym_sdate."' AND '".$ym_edate."' AND WINCASH > 0 AND SIGN_YN != 'C'"); // 전월

            $v_sales_monthly_winmoney = getPercent(($ymn_sales['PRICE'] + $ymn_sales_winmoney['WINMONEY']), ($month_sales['PRICE'] + $month_sales_winmoney['WINMONEY']));
            $v_sales_monthly_winmoney_count = getPercent($ymn_sales['CNT'] + $ymn_sales_winmoney['WINMONEY_CNT'], $month_sales['CNT'] + $month_sales_winmoney['WINMONEY_CNT']);
            $v_sales_nmonthly_winmoney = getPercent($ym_sales['PRICE'] + $ym_sales_winmoney['WINMONEY'], $month_sales['PRICE'] + $month_sales_winmoney['WINMONEY']);
            $v_sales_nmonthly_winmoney_count = getPercent($ym_sales['CNT'] + $ym_sales_winmoney['WINMONEY_CNT'], $month_sales['CNT'] + $month_sales_winmoney['WINMONEY_CNT']);
          ?>
          <div class="col-lg-6 col-md-12 col-sm-12 col-xl-4">
            <div class="card">
              <div class="card-body">
                <div class="row">
                  <div class="col-6">
                    <div style="font-size:9pt">당월 매출 + 당첨금 사용</div>
                    <h6 style="font-size:14pt;font-weight:bold">₩ <?php echo number_format($month_sales['PRICE'] + $month_sales_winmoney['WINMONEY'])?> </h6>
                    <hr></hr>
                  </div>
                  <div class="col-6">
                    <div style="font-size:9pt">당월 결제 건수 + 당첨금</div>
                    <h6 style="font-size:14pt;font-weight:bold"><?php echo number_format($month_sales['CNT'] + $month_sales_winmoney['WINMONEY_CNT'])?></h6>
                    <hr></hr>
                  </div>
                  <div class="col-6">
                    <div style="font-size:9pt">전월 동시간 매출</div>
                    <h6 class="mb-2 number-font">₩ <?php echo number_format($ymn_sales['PRICE'] + $ymn_sales_winmoney['WINMONEY'])?> (<?=act(number_format($v_sales_monthly_winmoney["N"]), "")?>)</h6>
                  </div>
                  <div class="col-6">
                    <div style="font-size:9pt">전월 동시간 건수</div>
                    <h6 class="mb-2 number-font"><?php echo number_format($ymn_sales['CNT'] + $ymn_sales_winmoney['WINMONEY_CNT'])?> (<?=act(number_format($v_sales_monthly_winmoney_count["N"]), "")?>)</h6>
                  </div>
                  <div class="col-6">
                    <div style="font-size:9pt">전월 매출 합계</div>
                    <h6 class="mb-2 number-font">₩ <?php echo number_format($ym_sales['PRICE'] + $ym_sales_winmoney['WINMONEY']); ?> (<?=act(number_format($v_sales_nmonthly_winmoney["N"]), "")?>)</h6>
                  </div>
                  <div class="col-6">
                    <div style="font-size:9pt">전월 건수 합계</div>
                    <h6 class="mb-2 number-font">
                    <?=number_format($ym_sales['CNT'] + $ym_sales_winmoney['WINMONEY_CNT'])?>(<?=act(number_format($v_sales_nmonthly_winmoney_count["N"]), "")?>)</h6>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <?php $time8 = microtime(true); ?>
          <div class="col-lg-6 col-md-12 col-sm-12 col-xl-4">
            <div class="card">
              <div class="card-body">
                <div class="row">
                  <div class="col-6">
                    <div style="font-size:9pt">당월 매출</div>
                    <h6 style="font-size:14pt;font-weight:bold">₩ <?=number_format($month_sales['PRICE'])?> </h6>
                    <hr></hr>
                  </div>
                  <div class="col-6">
                    <div style="font-size:9pt">당월 결제 건수</div>
                    <h6 style="font-size:14pt;font-weight:bold"><?=number_format($month_sales['CNT'])?></h6>
                    <hr></hr>
                  </div>
                  <div class="col-6">
                    <div style="font-size:9pt">전월 동시간 매출 </div>
                    <h6 class="mb-2 number-font">₩ <?=number_format($ymn_sales['PRICE'])?> (<?=act(number_format($v_sales_monthly_1["N"]), "")?>)</h6>
                  </div>
                  <div class="col-6">
                    <div style="font-size:9pt">전월 동시간 건수</div>
                    <h6 class="mb-2 number-font"><?=number_format($ymn_sales['CNT'])?> (<?=act(number_format($v_sales_monthly_1_count["N"]), "")?>)</h6>
                  </div>
                  <div class="col-6">
                    <div style="font-size:9pt">전월 매출 합계</div>
                    <h6 class="mb-2 number-font">₩ <?=number_format($ym_sales['PRICE'])?> (<?=act(number_format($v_sales_monthly_2["N"]), "")?>)</h6>
                  </div>
                  <div class="col-6">
                    <div style="font-size:9pt">전월 건수 합계</div>
                    <h6 class="mb-2 number-font">
                    <?=number_format($ym_sales['CNT'])?>(<?=act(number_format($v_sales_monthly_2_count["N"]), "")?>)</h6>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <?php
            # 당월 전일까지 당첨금 사용금액 / 건수
            $month_sales_winmoney_avg = $db->get_data("SELECT SUM(WINCASH) AS WINMONEY, COUNT(*) AS WINMONEY_CNT FROM ORDERS WHERE WINCASH > 0 AND REG_DATE BETWEEN '".$sdate0."' AND '".$y_edate."' AND SIGN_YN != 'C'");

            # 일평균 매출익 + 당첨금사용
            $avg_this_month_amount2 = round(($month_sales_avg['PRICE'] + $month_sales_winmoney_avg['WINMONEY']) / (int)date('j', strtotime('-1 day')), 0);
            $avg_this_month_cnt2 = round(($month_sales_avg['CNT'] + $month_sales_winmoney_avg['WINMONEY_CNT']) / (int)date('j', strtotime('-1 day')), 0);

            # 전월
            $avg_last_month_amount2    = round(($ym_sales['PRICE'] + $ym_sales_winmoney['WINMONEY']) / (int)$prev_day, 0);
            $avg_last_month_cnt2 = round(($ym_sales['CNT'] + $ym_sales_winmoney['WINMONEY_CNT']) / (int)$prev_day, 0);

            # 증감
            $avg_last_month_amount2_1 = getPercent($avg_last_month_amount2, $avg_this_month_amount2);
            $avg_last_month_cnt2_1 = getPercent($avg_last_month_cnt2, $avg_this_month_cnt2);
          ?>
          <div class="col-lg-6 col-md-12 col-sm-12 col-xl-4">
            <div class="card">
              <div class="card-body">
                <div class="row">
                  <div class="col-6">
                    <div style="font-size:9pt">당월 일평균 매출 + 당첨금 <i class="ti-help-alt" data-bs-toggle="tooltip" title="1일부터 전일까지 일평균 매출액 + 당첨금사용액"></i></div>
                    <h6 style="font-size:14pt;font-weight:bold">₩ <?=number_format($avg_this_month_amount2)?></a><hr> </h6>
                  </div>
                  <div class="col-6">
                    <div style="font-size:9pt">당월 일평균 결제수 + 당첨금 <i class="ti-help-alt" data-bs-toggle="tooltip" title="1일부터 전일까지 일평균 매출액 + 당첨금사용액"></i></div>
                    <h6 style="font-size:14pt;font-weight:bold"><?=number_format($avg_this_month_cnt2)?></a><hr></h6>
                  </div>
                  <div class="col-6">
                    <div style="font-size:9pt">전월 일평균 매출 + 당첨금 </div>
                    <h6 class="mb-2 number-font">₩ <?=number_format($avg_last_month_amount2)?><br>(<?=act(number_format($avg_last_month_amount2_1["N"]), "")?>) </h6>
                  </div>
                  <div class="col-6">
                    <div style="font-size:9pt">전월 일평균 결제수 + 당첨금</div>
                    <h6 class="mb-2 number-font"><?=number_format($avg_last_month_cnt2)?><br> (<?=act(number_format($avg_last_month_cnt2_1["N"]), "")?>) </h6>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-lg-6 col-md-12 col-sm-12 col-xl-4">
            <div class="card">
              <div class="card-body">
                <div class="row">
                  <div class="col-6">
                    <div style="font-size:9pt">당월 일평균 매출액 <i class="ti-help-alt" data-bs-toggle="tooltip" title="1일부터 전일까지 일평균 매출액"></i></div>
                    <h6 style="font-size:14pt;font-weight:bold">₩ <?=number_format($avg_this_month_amount)?></a><hr> </h6>
                  </div>
                  <div class="col-6">
                    <div style="font-size:9pt">당월 일평균 결제건수 <i class="ti-help-alt" data-bs-toggle="tooltip" title="1일부터 전일까지 일평균 매출액"></i></div>
                    <h6 style="font-size:14pt;font-weight:bold"><?=number_format($avg_this_month_cnt)?></a><hr></h6>
                  </div>
                  <div class="col-6">
                    <div style="font-size:9pt">전월 일평균 매출액 </div>
                    <h6 class="mb-2 number-font">₩ <?=number_format($avg_last_month_amount)?><br>(<?=act(number_format($avg_last_month_amount_1["N"]), "")?>) </h6>
                  </div>
                  <div class="col-6">
                    <div style="font-size:9pt">전월 일평균 결제건수</div>
                    <h6 class="mb-2 number-font"><?=number_format($avg_last_month_cnt)?><br> (<?=act(number_format($avg_last_month_cnt_1["N"]), "")?>) </h6>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <?php
            // 당월 사용 당첨금
            $this_month_wincash_M = $db->get_data("SELECT SUM(WINCASH) AS WINCASH FROM ORDERS WHERE REG_DATE > '{$sdate0}' AND SIGN_YN != 'C'");
            // 당월 적립 당첨금
            $this_month_wincash_P = $db->get_data("SELECT SUM(WCASH) AS WINCASH FROM T_WCASH_LOG WHERE MEMO = '당첨금적립' AND REG_DATE > '{$sdate0}'");
            // 전월 사용 당첨금
            $prev_month_wincash_M = $db->get_data("SELECT SUM(WINCASH) AS WINCASH FROM ORDERS WHERE REG_DATE BETWEEN '{$ym_sdate}' AND '{$ym_edate}' AND SIGN_YN != 'C'");
            // 전월 적립 당첨금
            $prev_month_wincash_P = $db->get_data("SELECT SUM(WCASH) AS WINCASH FROM T_WCASH_LOG WHERE MEMO = '당첨금적립' AND REG_DATE BETWEEN '{$ym_sdate}' AND '{$ym_edate}'");
            //$prev_month_wincash_P_old = $db->get_data("SELECT SUM(CASH) AS WINCASH FROM T_CASH_LOG WHERE MEMO = '당첨금 적립' AND REG_DATE BETWEEN '{$ym_sdate}' AND '{$ym_edate}'"); //2023.04 경우 T_CASH_LOG,T_WCASH_LOG 모두 사용함
            //$prev_month_wincash_P['WINCASH'] = $prev_month_wincash_P['WINCASH'] + $prev_month_wincash_P_old['WINCASH'];
            // 당월 출금 당첨금
            $this_month_invoce  = $db->get_data("SELECT SUM(MONEY) AS MONEY FROM INVOCE WHERE STATUS = 'Y' AND REG_DATE > '{$sdate0}'");
            // 전월 출금 당첨금
            $prev_month_invoce  = $db->get_data("SELECT SUM(MONEY) AS MONEY FROM INVOCE WHERE STATUS = 'Y' AND REG_DATE BETWEEN '{$ym_sdate}' AND '{$ym_edate}'");
            // 사용가능(잔여) 당첨금
            $ava_wincash = $db->get_data("SELECT SUM(WINCASH) AS WINCASH FROM MEMBER");
            // 누적 당첨금
            $tot_wincash = $db->get_data("SELECT SUM(WIN_MONEY) AS WINCASH FROM ORDERS WHERE SIGN_YN != 'C'");
          ?>
          <div class="col-lg-6 col-md-12 col-sm-12 col-xl-4">
            <div class="card">
              <div class="card-body">
                <div class="row">
                  <div class="col-6">
                    <div style="font-size:9pt">당월 누적 당첨금</div>
                    <h6 style="font-size:14pt;font-weight:bold">₩ <?=number_format($this_month_wincash_P['WINCASH'])?></h6>
                  </div>
                  <div class="col-6">
                    <div style="font-size:9pt">전월 누적 당첨금</div>
                    <h6 style="font-size:14pt;font-weight:bold">₩ <?=number_format($prev_month_wincash_P['WINCASH'])?> (<?=act(number_format($this_month_wincash_P['WINCASH'] - $prev_month_wincash_P['WINCASH']), "")?>)</h6>
                  </div>
                  <div class="col-6">
                    <div style="font-size:9pt">당월 사용 당첨금</div>
                    <h6 class="mb-2 number-font">₩ <?=number_format($this_month_wincash_M['WINCASH'])?></h6>
                  </div>
                  <div class="col-6">
                    <div style="font-size:9pt">전월 사용 당첨금</div>
                    <h6 class="mb-2 number-font">₩ <?=number_format($prev_month_wincash_M['WINCASH'])?> (<?=act(number_format($this_month_wincash_M['WINCASH'] - $prev_month_wincash_M['WINCASH']), "")?>)</h6>
                  </div>
                  <div class="col-6">
                    <div style="font-size:9pt">당월 출금 당첨금</div>
                    <h6 class="mb-2 number-font">₩ <?=number_format($this_month_invoce['MONEY']); ?></h6>
                  </div>
                  <div class="col-6">
                    <div style="font-size:9pt">전월 출금 당첨금</div>
                    <h6 class="mb-2 number-font">₩ <?=number_format($prev_month_invoce['MONEY'])?> (<?=act(number_format($this_month_invoce['MONEY'] - $prev_month_invoce['MONEY']), "")?>)</h6>
                  </div>
                  <div class="col-6">
                    <div style="font-size:9pt">당첨금 잔액</div>
                    <h6 class="mb-2 number-font">₩ <?=number_format($ava_wincash['WINCASH'])?></h6>
                  </div>
                  <div class="col-6">
                    <div style="font-size:9pt">누적 당첨금</div>
                    <h6 class="mb-2 number-font">₩ <?=number_format($tot_wincash['WINCASH'])?></h6>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <?php $time9 = microtime(true); ?>
          <div class="col-lg-6 col-md-12 col-sm-12 col-xl-4">
            <div class="card overflow-hidden">
              <div class="card-body">
                <div class="row">
                  <div class="col-6">
                    <div style="font-size:9pt">총 회원수</div>
                    <h6 style="font-size:15pt;font-weight:bold"><?=number_format($total_cnt)?> 명</h6>
                    <hr></hr>
                  </div>
                  <div class="col-6">
                    <div style="font-size:9pt">누적 탈퇴 회원수</div>
                    <h6 style="font-size:15pt;font-weight:bold"><?=number_format($member_out_all_cnt)?> 명</h6>
                    <hr></hr>
                  </div>
                </div>
                <div class="row">
                  <div class="col-6">
                    <div style="font-size:9pt">오늘 회원가입수</div>
                    <h6 class="mb-2 number-font"><?=number_format($today_member_count)?></h6>
                    <div style="font-size:9pt">전일 회원가입수</div>
                    <h6 class="mb-2 number-font"><?=number_format($yda_member_count)?></h6>
                    <div style="font-size:9pt">7일전 회원가입수</div>
                    <h6 class="mb-2 number-font"><?=number_format($prev_week_member_count)?></h6>
                    <div style="font-size:9pt">오늘 탈퇴 회원수</div>
                    <h6 class="mb-2 number-font"><?=number_format($member_out_today_cnt)?></h6>
                  </div>
                  <div class="col-6">
                  <div style="font-size:9pt">전일 동시간 회원가입수</div>
                    <h6 class="mb-2 number-font"><?=number_format($ydan_member_count)?></h6>
                    <div style="font-size:9pt">이달 회원가입수</div>
                    <h6 class="mb-2 number-font"><?=number_format($month_member_count)?></h6>
                    <div style="font-size:9pt">전월 회원가입수</div>
                    <h6 class="mb-2 number-font"><?=number_format($prev_month_member_count)?></h6>
                    <div style="font-size:9pt">전일 탈퇴 회원수</div>
                    <h6 class="mb-2 number-font"><?=number_format($member_out_yes_cnt)?></h6>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <?php $time10 = microtime(true); ?>
          <?php
            // 파트너 summary 조회
            $query = "
              SELECT
                SUM(VISIT_CNT) AS VISIT_CNT,
                SUM(MEMBER_CNT) AS MEMBER_CNT,
                SUM(OMEMBER_CNT) AS OMEMBER_CNT,
                SUM(CASH_CNT) AS CASH_CNT,
                SUM(CCASH_CNT) AS CCASH_CNT
              FROM UM_SUMMARY
              WHERE LEFT(SUMM_DATE,7) = '".date("Y-m")."'
            ";
            $summary = $db->get_data($query);
            // 파트너 수
            $partner_cnt = $db->get_data("SELECT COUNT(*) AS CNT FROM UM_PARTNER WHERE REG_DATE >= '{$sdate0}'");

            $um_comm_array = array("A=>0, W=>0, C=0, Y=0, P=0, D=0"); // A:전체, W:대기, C:취소, Y:완료, P:보류, D:삭제
            // 파트너 이번달 결제금액 조회
            $um_price = $db->get_data("SELECT SUM(PRICE) AS PRICE FROM CASH WHERE LENGTH(PARTNER_ID) > 0 AND IS_USE ='Y' AND CON_REG_DATE >= '{$sdate0}'");
            $um_comm_array['A'] = $um_price['PRICE'];
            unset($um_price);
            // 파트너 이번달 정산신청금액 조회
            $um_comm = $db->get_list("SELECT STATUS, SUM(AMOUNT) AS AMOUNT FROM UM_COMM WHERE REG_DATE >= '{$sdate0 }' GROUP BY STATUS");
            if (is_array($um_comm)) {
              for ($i = 0; $i < count($um_comm['STATUS']); $i++) {
                $um_comm_array[$um_comm['STATUS'][$i]] = $um_comm['AMOUNT'][$i];
              }
            }
            unset($um_comm);
          ?>
          <div class="col-lg-6 col-md-12 col-sm-12 col-xl-4">
            <div class="card overflow-hidden">
              <div class="card-header">
                <h3 class="card-title">이번달 파트너 활동 현황</h3>
              </div>
              <div class="card-body">
                <div class="row">
                  <div class="col-6">
                    <div style="font-size:9pt">방문자수(회)</div>
                    <h6 class="mb-2 number-font"><?=number_format($summary['VISIT_CNT'])?>회</h6>
                  </div>
                  <div class="col-6">
                    <div style="font-size:9pt">가입자수(명)</div>
                    <h6 class="mb-2 number-font"><?=number_format($summary['MEMBER_CNT'] - $summary['OMEMBER_CNT'])?>명</h6>
                  </div>
                </div>
                <div class="row">
                  <div class="col-6">
                    <div style="font-size:9pt">결제수(건)</div>
                    <h6 class="mb-2 number-font"><?=number_format($summary['CASH_CNT'] - $summary['CCASH_CNT'])?>건</h6>
                  </div>
                  <div class="col-6">
                    <div style="font-size:9pt">파트너수(명)</div>
                    <h6 class="mb-2 number-font"><?=number_format($partner_cnt['CNT'])?>명</h6>
                  </div>
                </div>
                <div class="row">
                  <div class="col-6">
                    <div style="font-size:9pt">누적 수익금</div>
                    <h6 class="mb-2 number-font">₩ <?=number_format($um_comm_array['A'])?></h6>
                  </div>
                  <div class="col-6">
                    <div style="font-size:9pt">출금 된 수익금</div>
                    <h6 class="mb-2 number-font">₩ <?=number_format($um_comm_array['Y'])?></h6>
                  </div>
                </div>
                <div class="row">
                  <div class="col-6">
                    <div style="font-size:9pt">출금 대기 수익금</div>
                    <h6 class="mb-2 number-font">₩ <?=number_format($um_comm_array['W'])?> </h6>
                  </div>
                  <div class="col-6">
                    <div style="font-size:9pt">출금 보류 수익금</div>
                    <h6 class="mb-2 number-font">₩ <?=number_format($um_comm_array['P'])?></h6>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-lg-6 col-md-12 col-sm-12 col-xl-4">
            <div class="card">
              <div class="card-body">
                <div class="row">
                  <div class="col-6">
                    <div style="font-size:9pt">오늘 제휴 결제수</div>
                    <h6 style="font-size:14pt;font-weight:bold"><?=number_format($now_refer_order['CNT'])?></a><hr> </h6>
                  </div>
                  <div class="col-6">
                    <div style="font-size:9pt">오늘 제휴 결제금액</div>
                    <h6 style="font-size:14pt;font-weight:bold">₩ <?=number_format($now_refer_order['PRICE'])?></a><hr></h6>
                  </div>
                  <div class="col-6">
                    <div style="font-size:9pt">전일 제휴 결제수<i class="ti-help-alt" data-bs-toggle="tooltip" title="전일 대비 제휴 결제수"></i></div>
                    <h6 class="mb-2 number-font"> <?=number_format($yes_refer_order['CNT'])?> (<?=act(number_format($yes_refer_order_count_1["N"]), "")?>)</h6>
                  </div>
                  <div class="col-6">
                    <div style="font-size:9pt">전일 제휴 결제금액<i class="ti-help-alt" data-bs-toggle="tooltip" title="전일 대비 제휴 결제금액"></i></div>
                    <h6 class="mb-2 number-font">₩ <?=number_format($yes_refer_order['PRICE'])?> (<?=act(number_format($yes_refer_order_amount_1["N"]), "")?>)</h6>
                  </div>
                  <div class="col-6">
                    <div style="font-size:9pt">당월 제휴 결제수<i class="ti-help-alt" data-bs-toggle="tooltip" title="전월 대비 제휴 결제수"></i></div>
                    <h6 class="mb-2 number-font"> <?=number_format($month_refer_order['CNT'])?></h6>
                  </div>
                  <div class="col-6">
                    <div style="font-size:9pt">당월 제휴 결제금액<i class="ti-help-alt" data-bs-toggle="tooltip" title="전월 대비 제휴 결제금액"></i></div>
                    <h6 class="mb-2 number-font">₩ <?=number_format($month_refer_order['PRICE'])?></h6>
                  </div>
                  <div class="col-6">
                    <div style="font-size:9pt">누적 제휴 결제수<i class="ti-help-alt" data-bs-toggle="tooltip" title="전체 제휴 결제수(첫결제 포함)"></i></div>
                    <h6 class="mb-2 number-font"> <?=number_format($ref_price['CNT'])?></h6>
                  </div>
                  <div class="col-6">
                    <div style="font-size:9pt">누적 제휴 결제금액<i class="ti-help-alt" data-bs-toggle="tooltip" title="전체 제휴 결제금액(첫결제 포함)"></i></div>
                    <h6 class="mb-2 number-font">₩ <?=number_format($ref_price['PRICE'])?></h6>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- ROW-3 -->
    <div class="row">
      <div class="col-xl-4 col-md-12">
        <div class="card">
          <div class="card-header">
            <h4 class="card-title fw-semibold">메가밀리언</h4>
          </div>
          <div class="card-body pb-0">
            <ul class="task-list">
              <?php
                $cla = "bg-primary";
                for ($i = 0; $i < 5; $i++) {
              ?>
              <li>
                <i class="task-icon <?=$cla?>"></i>
                <h6>P <?=number_format($mega[$i]['price'])?><span class="text-muted fs-11 ms-2"><?=$mega[$i]['date']?></span></h6>
                <p class="text-muted fs-12">&nbsp;&nbsp;</p>
                <!--<p class="text-muted fs-12">Adam Berry finished task on<a href="#" class="fw-semibold"> Project Management</a></p>-->
              </li>
              <?php
                if ($cla == 'bg-primary') {
                  $cla = 'bg-secondary';
                } else {
                  $cla = 'bg-primary';
                }
              }
              ?>
            </ul>
          </div>
        </div>
      </div>
      <div class="col-xl-4 col-md-12">
        <div class="card">
          <div class="card-header">
            <h4 class="card-title fw-semibold">파워볼</h4>
          </div>
          <div class="card-body pb-0">
            <ul class="task-list">
              <?php
                $cla = "bg-primary";
                for ($i = 0; $i < 5; $i++) {
              ?>
              <li>
                <i class="task-icon <?=$cla?>"></i>
                <h6>P <?=number_format($power[$i]['price'])?><span class="text-muted fs-11 ms-2"><?=$power[$i]['date']?></span></h6>
                <p class="text-muted fs-12">&nbsp;&nbsp;</p>
                <!--<p class="text-muted fs-12">Adam Berry finished task on<a href="#" class="fw-semibold"> Project Management</a></p>-->
              </li>
              <?php
                if ($cla == 'bg-primary') {
                  $cla = 'bg-secondary';
                } else {
                  $cla = 'bg-primary';
                }
              }
              ?>
            </ul>
          </div>
        </div>
      </div>
      <div class="col-xl-4 col-md-12">
        <div class="card">
          <div class="card-header">
            <h4 class="card-title fw-semibold">전체</h4>
          </div>
          <div class="card-body pb-0">
            <ul class="task-list">
              <?php
                $cla = "bg-primary";
                for ($i = 0; $i < 5; $i++) {
              ?>
              <li>
                <i class="task-icon <?=$cla?>"></i>
                <h6>P <?=number_format($total[$i]['price'])?><span class="text-muted fs-11 ms-2"><?=$total[$i]['date']?></span></h6>
                <p class="text-muted fs-12">&nbsp;&nbsp;</p>
                <!--<p class="text-muted fs-12">Adam Berry finished task on<a href="#" class="fw-semibold"> Project Management</a></p>-->
              </li>
              <?php
                if ($cla == 'bg-primary') {
                  $cla = 'bg-secondary';
                } else {
                  $cla = 'bg-primary';
                }
              }
              ?>
            </ul>
          </div>
        </div>
      </div>
    </div><!-- COL END -->
    <!-- ROW-3 END -->
    <?php $time11 = microtime(true); ?>
    <!-- ROW-5 -->
    <div class="row">
      <div class="col-12 col-sm-12">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title mb-0 mr-3">최근 구매내역</h3>
            <a href="/order/orders_list.html" class="text-gray-dark">더보기 ></a>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-bordered mb-0">
                <thead class="border-top">
                <tr class="text-center">
                  <th>No</th>
                  <th>복권명</th>
                  <th>주문번호</th>
                  <th>게임수량</th>
                  <th>추첨일자</th>
                  <th>결제금액</th>
                  <th>구매일자</th>
                  <th>구매경로</th>
                </tr>
                </thead>
                <tbody>
                  <?php
                    $no = 0;
                    for ($i = 0; $i < count($list['ORDERS_NO']); $i++) {
                      $no++;
                      $num = $no;

                      if ($no < 10) {
                        $num = "0".$no;
                      }
                  ?>
                  <tr class="border-bottom text-center">
                    <td><?=$num?></td>
                    <td><?=$ball_op[$list['GUBUN'][$i]]?></td>
                    <td><?=$list['ORDERS_NO'][$i]?></td>
                    <td><?=$list['GAMECNT'][$i]?></td>
                    <td><?=$list['PLAYDATE'][$i]?></td>
                    <td class="text-end"><?=number_format($list['PAYMENT'][$i])?></td>
                    <td><?=$list['REG_DATE'][$i]?></td>
                    <td><?=$list['SELLER_GUBUN'][$i]?></td>
                  </tr>
                  <?php } ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div><!-- COL END -->
    </div><!-- ROW-5 END -->
  </div>
</div> <!-- CONTAINER END -->

<?php require_once $_SERVER['DOCUMENT_ROOT']."/inc/footer.html"; ?>

<?php $time12 = microtime(true); ?>

<!--
<?php
echo "runtime2 : ". ($time2-$time1) . "\n";
echo "runtime3 : ". ($time3-$time2) . "\n";
echo "runtime4 : ". ($time4-$time3) . "\n";
echo "runtime5 : ". ($time5_1-$time4) . "\n";
echo "runtime5.1 : ". ($time5-$time5_1) . "\n";
echo "runtime6 : ". ($time6-$time5) . "\n";
echo "runtime7 : ". ($time7-$time6) . "\n";
echo "runtime8 : ". ($time8-$time7) . "\n";
echo "runtime9 : ". ($time9-$time8) . "\n";
echo "runtime10 : ". ($time10-$time9) . "\n";
echo "runtime11 : ". ($time11-$time10) . "\n";
echo "runtime12 : ". ($time12-$time11) . "\n";
echo "total runtime : ".($time12 - $time1) . "\n";
?>
-->
