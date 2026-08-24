<?php
include_once('../common.php');

function ajax_pay_reply_3001($resultmsg) {
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode(array('resultcode' => '3001', 'resultmsg' => (string)$resultmsg), JSON_UNESCAPED_UNICODE);
	exit;
}

if(!$midx){
  die("midx null");
}
// 이노페이 결제후 결제창을 완전히 닫았을경우에 실행됨


// $idata[0] = 1 ? 1개월 알뜰
// $idata[0] = 2 ? 1개월 무제한
// $idata[0] = 1 3 포인트충전
//$midx = 2;
$resultcode = isset($_POST['resultcode']) ? trim((string)$_POST['resultcode']) : '';
$resultmsg = isset($_POST['resultmsg']) ? (string)$_POST['resultmsg'] : '';

if ($resultcode !== '3001') {
	header('Content-Type: text/plain; charset=utf-8');
	echo $resultmsg;
	exit;
}


$완료체크 = db_select("select * from tb_pay_log where moid = '{$_moid}' and status = 1 ");
if($완료체크['idx']>0){
	ajax_pay_reply_3001($resultmsg);
}

$_data = db_select("select * from tb_pay_log where moid = '{$_moid}' and status = 0 ");
if($_data['idx']>0){
	db_query("update tb_pay_log set status = 1 where moid = '{$_moid}' ");

  $회원 = db_select("select * from member where mb_no = {$midx} ");
  $idata = explode("/",$idata);
  //echo $idata[0];
  if($idata[0] == "알뜰형" || $idata[0] == "무제한"){
    $서비스상태 = 날짜비교($회원['mb_10']); //기간체크

    $현재날자 = date("Y-m-d");
    if($서비스상태==1){
      //이미 서비스 기간이 지난회원의경우..
      $서비스시작일 = $현재날자;
      $서비스종료일 = date("Y-m-d",strtotime($서비스시작일." +{$idata[1]} days"));
    }else{
      //서비스 기간이 남아있는상태에서 연장
      $서비스시작일 = $회원['mb_10'];
      $서비스종료일 = date("Y-m-d",strtotime($서비스시작일." +{$idata[1]} days"));
    }

    if($회원['mb_8']==2 and $idata[0]=="무제한"){
      //무제한으로 이용하다가 알뜰로 변경시.. 기존 포인트 초기화
      db_query("update member set mb_point = 0 where mb_no = {$midx} ");
    }

    $sql = "update member set ";
    if($idata[0]=="알뜰형"){ //알뜰형
      $sql.= "mb_point = {$idata[2]} ";
    }else if($idata[0]=="무제한"){ //무제한
      $sql.= "mb_point = 99999 ";
    }
    $sql.= ", service_start_date = '{$서비스시작일}' ";
    $sql.= ", mb_8 = '{$idata[0]}' ";
    $sql.= ", mb_10 = '{$서비스종료일}' ";
    $sql.= ", deldate = '' ";
    $sql.= "where mb_no = {$midx}";
    //echo $sql;
    db_query($sql);
    회원사이트_서비스활성화($midx);

    $sql = "insert into tb_orderlist set ";
    $sql.= "midx = {$midx}, ";
    $sql.= "subject = '{$GoodsName}', ";
    $sql.= "amount = {$Amt}, ";
    $sql.= "status = {$resultcode},";
    $sql.= "regdate = now() ";
    $result = db_query($sql);
    주문로그('카드',$midx,"서비스_기간연장_{$서비스시작일}_{$서비스종료일}", $Amt, $resultcode);

    $site = db_query("select * from site where member_no = {$midx} ");
    $내용 = "";
    $내용.= $회원['mb_name']."(".$회원['mb_id'].")\n";
    foreach($site as $st){
      $내용.= $st['site']."\n";
    }
    $내용.= "이용료 : ".$Amt."원 결제완료";
    다이랙트샌드("01022934444", '럭키뱅크 서비스 연장', $내용);
    ajax_pay_reply_3001($resultmsg);
  }else if($idata[0]=="포인트"){ // 포인트충전만 실행

    $sql = "update member set ";
    $sql.= "mb_point = mb_point + {$idata[2]} ";
    $sql.= ", mb_8 = '1' ";
    $sql.= "where mb_no = {$midx}";
    $result = db_query($sql);
    주문로그('카드',$midx,"포인트충전 {$idata[2]}",0,0);
    ajax_pay_reply_3001($resultmsg);
  }else if($idata[0]=="캐시"){
    $충전액 = isset($idata[1]) ? (int)$idata[1] : 0;
    if($충전액 < 10000 || $충전액 % 10000 !== 0 || $충전액 > 90000000){
      ajax_pay_reply_3001($resultmsg);
    }
    $sql = "update member set ";
    $sql.= "mb_cash = COALESCE(mb_cash, 0) + {$충전액} ";
    $sql.= "where mb_no = {$midx}";
    db_query($sql);
    주문로그('카드',$midx,"패널캐시충전 {$충전액}",0,0);
    $sms내용 = $회원['mb_name']."(".$회원['mb_id'].")\n";
    $sms내용 .= "패널캐시 ".number_format($충전액)."원 충전완료\n";
    $sms내용 .= "(카드 ".number_format($Amt)."원)";
    다이랙트샌드('01022934444', '럭키뱅크 패널캐시 충전', $sms내용);
    ajax_pay_reply_3001($resultmsg);
  }
}
ajax_pay_reply_3001($resultmsg);
