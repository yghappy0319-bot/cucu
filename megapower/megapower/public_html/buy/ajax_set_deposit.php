<?php
include_once "../lib/function.php";
include_once "../lib/config.php";
include_once "../_chk.php";

$midx = $_SESSION['midx'];
$data = array("status" => false, "msg" => "현재 시스템 점검으로 캐시 충전이 일시 중단되었습니다.");
echo json_encode($data);
exit;

if(!$midx){
  $data = array("status" => false, "msg" => "로그인 후 이용해주세요.");
  echo json_encode($data);
  exit;
}

if(!$amount){
  $data = array("status" => false, "msg" => "point_null");
  echo json_encode($data);
  exit;
}

if($cash_idx){
  $_입금확인 = db_select("select status from lr_deposit_log where midx = {$midx} and idx = {$cash_idx} order by regdate desc limit 1");
  if($_입금확인['status']==2){
    $data = array("status" => false, "msg" => "처리가 진행된 건 입니다.\n마이페이지에서 충전된 내역을 확인해주세요!");
    echo json_encode($data);
    exit;
  }
}


$deposit_cnt = db_select("select count(*) as cnt from lr_deposit_log where midx = {$midx} and status = 1 ");
if($deposit_cnt['cnt']>0){
    $data = array("status" => false, "msg" => "입금 신청내역이 있습니다.\n신청내역을 변경하시려면 이전 내역을 취소해 주세요!");
    echo json_encode($data);
    exit;
}

if($cash_tax){
  $_receipts = $cash_tax;
}else{
  $_receipts = 0;
}
$_신청금액 = number_format($amount);

$sql = "insert into lr_deposit_log set ";
$sql.= "cash_config_idx = {$cash_config_idx},";
$sql.= "status = 1,";
$sql.= "deposit_type = 'bank',";
$sql.= "midx = {$midx},";
$sql.= "receipts = {$_receipts},";
$sql.= "cash = {$amount},";
$sql.= "point = {$cash},";
$sql.= "name = '{$org_name}',";
$deposit_name = preg_replace('/\s+/', '', $deposit_name);
$sql.= "deposit_name = '{$deposit_name}', ";
$sql.= "status_title = '처리중(대기)', ";
$sql.= "memo = '입금대기', ";
$sql.= "regdate = now() ";
$result = db_query($sql);
$depoidx = mysqli_insert_id($conn);
if($result){
  $data = array("status" => 1, "msg" => "캐시 충전 신청이 접수 되었습니다.", "deposit_idx" => $depoidx);
  echo json_encode($data);
  exit;
}else{
  $data = array("status" => false, "msg" => "캐시 충전 신청 오류/관리자에게 문의하세요.");
  echo json_encode($data);
  exit;
}
