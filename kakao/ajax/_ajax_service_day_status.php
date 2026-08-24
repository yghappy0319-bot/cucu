<?php
include_once('../common.php');
$mdata = db_select("select * from member where mb_no = {$mb_no} ");

if (!$mdata['mb_no']) {
  echo json_encode(array("status" => "0", "msg" => "회원 정보를 찾을 수 없습니다."));
  exit;
}

$pay_amt = isset($pay_amt) ? (int) $pay_amt : 0;
if (!in_array($pay_amt, array(11000, 55000), true)) {
  $pay_amt = 0;
}

$pay_start = 수동연장_기준일(isset($pay_start_date) ? $pay_start_date : null);
$log_only = isset($log_only) && ($log_only === '1' || $log_only === 1);

$day_delta = (int) $service_day;

if ($log_only && $service_day === '+30') {
  $result = 수동연장_pay_log($mb_no, $mdata['mb_id'], 30, $pay_amt, $pay_start);
  if ($result) {
    $memo = 자동결제_서비스기간_memo($pay_start, 30);
    echo json_encode(array("status" => "1", "msg" => "결제 기록만 저장되었습니다. ({$memo})"));
  } else {
    echo json_encode(array("status" => "0", "msg" => "결제 기록 저장에 실패했습니다."));
  }
  exit;
}

if(!$mdata['mb_10']){ //mb_10에 날짜가 비어 있으면
  $오늘날짜 = date("Y-m-d");
  $서비스일 = date("Y-m-d",strtotime($오늘날짜.$service_day."days"));

  $sql = "update member set service_start_date = '{$오늘날짜}' where mb_no = {$mb_no} ";
  db_query($sql); //서비스 종료날짜 기록

  $sql = "update member set mb_9 = 'month', mb_10 = '{$서비스일}' where mb_no = {$mb_no} ";
  $result = db_query($sql);
  if($result){
    회원사이트_서비스활성화($mb_no);
    자동결제_청구일_맞추기($mb_no, $서비스일);
    if ($day_delta !== 0) {
      $log_start = ($service_day === '+30') ? $pay_start : $오늘날짜;
      수동연장_pay_log($mb_no, $mdata['mb_id'], $day_delta, $pay_amt, $log_start);
    }
    echo json_encode(array("status" => "1", "msg" => "서비스일자 비어있는계정 {$서비스일} 연장 완료"));
  }
}else{

  $서비스일 = date("Y-m-d",strtotime($mdata['mb_10'].$service_day."days"));

  $sql = "update member set service_start_date = '{$mdata['mb_10']}' where mb_no = {$mb_no} ";
  db_query($sql); //서비스 종료날짜 기록

  $sql = "update member set mb_9 = 'month', mb_10 = '{$서비스일}' where mb_no = {$mb_no} ";
  $result = db_query($sql);
  if($result){
    회원사이트_서비스활성화($mb_no);
    자동결제_청구일_맞추기($mb_no, $서비스일);
    if ($day_delta !== 0) {
      $log_start = ($service_day === '+30') ? $pay_start : $mdata['mb_10'];
      수동연장_pay_log($mb_no, $mdata['mb_id'], $day_delta, $pay_amt, $log_start);
    }
    echo json_encode(array("status" => "1", "msg" => "기존계정_{$서비스일} 연장 완료"));
  }

}
