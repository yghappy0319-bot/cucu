<?php include_once "../lib/function.php";
include_once "../_chk.php";
// error_reporting(E_ALL);
// ini_set("display_errors", 1);

if($gubun=="지급"){
  $point = $point1; // - 1000; 수수료가 있는경우만 주석을 해제할것
  $msg = "전환 가능한";
}else{
  $point = $point2 - 500;
  $msg = "출금 가능한";
}

if($member['prize_money']==0){
  $res = array(
    "status" => "no",
    "gubun" => $gubun,
    "msg" => "{$msg} 당첨금은 ".number_format($member['prize_money'])."원 입니다."
  );
  echo json_encode($res);
  exit;
}

if($point > 0 and $point > $member['prize_money']){
  $res = array(
    "status" => "no",
    "gubun" => $gubun,
    "msg" => "{$msg} 당첨금은 ".number_format($member['prize_money'])."원 입니다."
  );
  echo json_encode($res);
  exit;
}

if($gubun=="지급"){ // 전환하기
  $result = 포인트로그($member['idx'],$gubun,'당첨금',$point); // 포인트 로그에
}else{
  if($point2 > $member['prize_money']){
    $res = array("status" => false, "msg" => "출금가능한 당첨금이 부족합니다.(수수료 포함)");
    echo json_encode($res);
    exit;
  }

  //당첨금 출금
  $data = array(
    "midx" => $member['idx'],
    "userid" => $member['id'],
    "status" => 0,
    "point" => $point,
    "gubun" => "대기"
  );
  $result = 당첨금차감_출금($data);
}

if($gubun=="지급"){
  $_res = db_query("update lr_member set lucky_point = lucky_point + {$point}, prize_money = prize_money - {$point} where idx = {$member['idx']} ");
  $msg = "포인트로 지급 되었습니다.";

  $data = array(
    "midx" => $member['idx'],
    "userid" => $member['id'],
    "status" => 3,
    "point" => $point,
    "gubun" => "차감"
  );
  $result = 당첨금차감_출금($data);

}else{

  $_res = db_query("update lr_member set prize_money = prize_money - {$point2} where idx = {$member['idx']} ");
  $msg = "출금신청 되었습니다.\n(관리자 확인 후 회원님 계좌로 즉시 출금 해드립니다.)";

  $msg2 = "출금신청 | {$member['id']}\n";
  $msg2.= "출금신청금액 : {$point}원\n";
  $msg2.= "은행 : {$member['acount']} ({$member['acount_name']})\n";
  $msg2.= "계좌번호 : {$member['acount_number']}\n";
  $msg2.= "신청날짜 : ".date("Y-m-d H:i");
  $msg2.= "\n----------------";
  sendTelegramToMany($msg2);
}

if($_res){
    $res = array("status" => "ok", "gubun" => $gubun, "msg" => "당첨금 ".number_format($point)."원이 {$msg}");
  echo json_encode($res);
  exit;
}
