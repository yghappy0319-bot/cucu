<?php
include_once "../common.php";
include_once "../_chk.php";

$data = array(
  "amt" => $_POST['amt'],
  "arsConnType" => "02",
  "billKey" => $_POST['billKey'],
  "buyerHp" => $_POST['buyerHp'],
  "buyerName" => $_POST['buyerName'],
  "goodsName" => $_POST['goodsName'],
  "mid" => $_POST['mid'],
  "moid" => $_POST['moid'],
  "payExpDate" => $_POST['payExpDate'],
  "userId" => $_POST['userId'],
  "buyerEmail" => $_POST['buyerEmail'],
  "cardQuota" => $_POST['cardQuota'],
);

$response = 자동결제($data);

// 오류 확인
if(curl_errno($ch)) {
    echo 'cURL Error: ' . curl_error($ch);
} else {
  // 응답 결과 출력
  $jsonDatas = json_encode($response); //json으로 생성후
  $datas = json_decode($jsonDatas); //
  echo $datas;

  // JSON 문자열을 PHP 객체로 디코딩
  $data = json_decode($response);

  if($data->resultCode=="0000"){
    $오늘 = date("Y-m-d");
    $서비스일 = date("Y-m-d", strtotime("+30 days"));
    $startdate = date("Y-m-d H:i:s");
    $enddate = date("Y-m-d H:i:s", strtotime("+30 days"));
    $mem = db_select("select * from member where mb_no = {$mb_no}  ");
    $설정 = db_select("select * from tb_pay_config where idx = {$mem['pay_config_key']} ");

    $sql = "insert into tb_auto_bill set ";
    $sql.= "mb_no = '{$mb_no}', ";
    $sql.= "amt = '{$amt}', ";
    $sql.= "arsConnType = '{$arsConnType}', ";
    $sql.= "billKey = '{$billKey}', ";
    $sql.= "buyerHp = '{$buyerHp}', ";
    $sql.= "buyerName = '{$buyerName}', ";
    $sql.= "goodsName = '{$goodsName}', ";
    $sql.= "mid = '{$mid}', ";
    $sql.= "moid = '{$moid}', ";
    $sql.= "payExpDate = '{$payExpDate}', ";
    $sql.= "userId = '{$userId}', ";
    $sql.= "buyerEmail = '{$buyerEmail}', ";
    $sql.= "cardQuota = '{$cardQuota}', ";
    $sql.= "startdate = '{$startdate}', ";
    $sql.= "enddate = '{$enddate}', ";
    $sql.= "regdate = now() ";
    db_query($sql);

    // auto_payment_stats = 0 //자동결제 미사용
    // auto_payment_stats = 1 //자동결제 사용중
    $sql = "update member set ";
    $sql.= "mb_point = {$설정['point']}, ";
    $sql.= "service_start_date = '{$오늘}', ";
    $sql.= "mb_9 = 'month', ";
    $sql.= "mb_10 = '{$서비스일}', ";
    $sql.= "auto_start_day = now(), ";
    $sql.= "auto_payment_status = 1 ";
    $sql.= "where mb_id = '{$_POST['userId']}' ";
    db_query($sql);
    회원사이트_서비스활성화($mb_no);

    자동결제_pay_log(
      $mb_no,
      $mem['mb_id'],
      $amt,
      $goodsName,
      자동결제_pay_value($설정, $amt),
      $moid,
      $오늘
    );

    $제목 = "럭키뱅크 자동결제 설정 완료";
    $내용 = "{$mem['mb_id']} / ".number_format($amt)."원 자동결제 설정";
    다이랙트샌드('01022934444', $제목, $내용);
  }

}

// cURL 세션 종료
curl_close($ch);
?>
