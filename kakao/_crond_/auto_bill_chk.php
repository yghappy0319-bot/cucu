<?php
include "/home/luckybank/public_html/lib/function.php";

db_query("insert into tb_bank_cron set text = '자동결제 체크', regdate = now() ");

$sql = "select * from tb_auto_bill  ";
$result = db_query($sql);
$오늘 = date("Y-m-d");
for($i=0;$row=db_fetch($result);$i++){
  //$row['enddate'] = "2025-06-27 17:49:37";
  $종료기간 = date("Y-m-d", strtotime($row['enddate']));
  $종료삼일전 = date("Y-m-d", strtotime($종료기간." -3 days")); //문자용

  $mem = db_select("select * from member where mb_no = {$row['mb_no']} ");
  if($종료삼일전==$오늘){ // 종료전
    $제목 = "무통장 자동화 서비스 종료";
    $내용 = "럭키뱅크 자동결제 안내\n";
    $내용.= "서비스 결제 3일 전 입니다.\n";
    $내용.= "- 자동화 서비스 1개월\n";
    $내용.= "결제일 : ".$종료기간."\n";
    $내용.= "결제금액 : ".number_format($row['amt'])."원\n";
    $내용.= "미결제 상태가 7일 이상 지속되면 자동결제 정보와 함께 아이디 및 결제내역이 모두 삭제됩니다. 지속적인 서비스 이용을 원하실 경우 기간 내 결제를 완료해 주시길 바랍니다. 감사합니다.";

    $수신번호 = $mem['mb_hp'];
    다이랙트샌드($수신번호, $제목, $내용);
  }

  if($오늘==$종료기간){
    $data2 = array(
      "amt" => $row['amt'],
      "arsConnType" => "02",
      "billKey" => $row['billKey'],
      "buyerHp" => $row['buyerHp'],
      "buyerName" => $row['buyerName'],
      "goodsName" => $row['goodsName'],
      "mid" => $row['mid'],
      "moid" => $row['moid'],
      "payExpDate" => $row['payExpDate'],
      "userId" => $row['userId'],
      "buyerEmail" => $row['buyerEmail'],
      "cardQuota" => $row['cardQuota']
    );

    $response = 자동결제($data2);
    // JSON 문자열을 PHP 객체로 디코딩
    $data = json_decode($response);
    var_dump($data)."<br />";

    if($data->resultCode=="0000"){
      $설정 = db_select("select * from tb_pay_config where idx = {$mem['pay_config_key']} ");

      $service_day = " +30";
      $서비스일 = date("Y-m-d",strtotime($row['enddate'].$service_day."days"));

      $sql = "update member set ";
      $sql.= "mb_point = {$설정['point']}, ";
      $sql.= "service_start_date = '{$오늘}', ";
      $sql.= "mb_9 = 'month', ";
      $sql.= "mb_10 = '{$서비스일}' ";
      $sql.= "where mb_no = {$mem['mb_no']} ";
      db_query($sql);
      회원사이트_서비스활성화($mem['mb_no']);

      // 결제내역()
      $sql = "insert into tb_orderlist set ";
      $sql.= "midx = {$row['mb_no']}, ";
      $sql.= "subject = '{$설정['product']}', ";
      $sql.= "amount = {$row['amt']}, ";
      $sql.= "status = 1, ";
      $sql.= "regdate = now() ";
      db_query($sql);

      $pay_log_moid = $row['userId'].date('YmdHis');
      $pay_log_goodsname = $row['goodsName'] ? $row['goodsName'] : $설정['product'];
      자동결제_pay_log(
        $row['mb_no'],
        $mem['mb_id'],
        $row['amt'],
        $pay_log_goodsname,
        자동결제_pay_value($설정, $row['amt']),
        $pay_log_moid,
        $오늘
      );

      db_query("update tb_auto_bill set enddate = '{$서비스일}' where idx = {$row['idx']} ");

      $제목 = "럭키뱅크 자동결제 완료";
      $내용 = "{$mem['mb_id']} / ".number_format($row['amt'])."원 자동결제";
      다이랙트샌드('01022934444', $제목, $내용);

    }else{ // 결제실패

    }
  }else{
    echo "";
  }
  echo "종료일 :: ".$종료기간."___종료3일전문자 :: ".$종료삼일전."<br />";

}
