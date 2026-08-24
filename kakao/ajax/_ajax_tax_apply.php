<?php
include_once('../common.php');

$id = isset($id) ? (int)$id : 0;
$sidx = isset($sidx) ? (int)$sidx : 0; // 참고용(현재는 미사용)
$types = isset($types) ? trim((string)$types) : ''; // 1=현금영수증, 2=세금계산서 (현재는 미사용)

if(!$id){
  die("id null");
}

$row = db_select("select * from tb_pay_log where idx = {$id} ");
if(!$row || !$row['idx']){
  die("신청 대상이 없습니다.");
}

if((int)$row['status'] !== 1){
  die("입금 확인 후 신청 가능합니다.");
}

$receipt_status_val = (isset($row['receipt_status']) && $row['receipt_status'] !== '' && $row['receipt_status'] !== null)
  ? (int)$row['receipt_status'] : null;

if($receipt_status_val === 0){
  die("이미 신청 처리된 건 입니다.");
}
if($receipt_status_val === 1){
  die("이미 발급 완료된 건 입니다.");
}

$sql = "update tb_pay_log set receipt_status = 0 where idx = {$id} ";
$result = db_query($sql);
echo $result ? '1' : '0';
?>
