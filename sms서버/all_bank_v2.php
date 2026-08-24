<?php
include("/home/sms/public_html/lib/function.php"); // sms db
include("/home/sms/public_html/lib/errorlist_function.php");
include("/home/sms/public_html/lib/luckybank_function.php");
include("/home/sms/public_html/lib/perfectpanel.php");

// V2만 실행
$_하루전 = date("Y-m-d H:i:s", strtotime("-1 days"));
//echo " 24시간 데이터만 확인함 ".$_하루전."<br />";

$오늘 = date("Y-m-d");
$bank_list_sql = "select a.*, b.mb_10 from site a left join member b on a.member_no = b.mb_no
where b.mb_10 != '' and b.mb_10 >= '{$오늘}' and a.website = 0 and a.api_key_v2 != '' and luckypanel = 0 order by mb_10 desc";

echo "처리가능한 사이트 조회<br> ".$bank_list_sql."<br />";

$bank_list_result = lb_db_query($bank_list_sql);
for($i=0;$row=lb_db_fetch($bank_list_result);$i++){

    $가공된데이터sql = "select * from bank_data_detail where status = 0 and (memo is null or memo = '') and site = '{$row['site']}' order by regdate desc ";
    $result = db_query($가공된데이터sql); // 입금처리가 확인된 데이터며 1차 가공
        foreach($result as $data){

          $_계좌확인 = 포함여부($data['acount'], $row['acount']); //가공된 데이터랑 입금 계좌번호 비교
          if($_계좌확인){ //계좌번호가 같으면..

            //무통장 신청건 조회
            $신청sql = "select * from bank_request where status = 0 and name = '{$data['name']}' and amount = {$data['amount']} and regdate > '{$_하루전}' order by regdate desc limit 1  ";
            echo $신청sql."<br />";
            $_입금신청데이터 = lb_db_select($신청sql); //가공된 데이터와 신청서가 맞으면..

            //계좌는 같은데 사이트는 다를경우가 있네.. 확인처리는 입금내역을 기준으로..
            $site_sql = "select site, api_key_v2 from site where site = '{$_입금신청데이터['site']}' ";
            //echo $site_sql."<br />";
            $입금내역기준조회 = lb_db_select($site_sql);


              if($_입금신청데이터['user_id']!=""){ //무조건 아이디가 있어야 가능
                  db_query("update bankdata set status = 1 where id = {$data['id']} ");
                  //입금 기준 데이터에도 확인처리 한다
                  $입금내역기준데이터에도_업데이트 = "update bank_data_detail set ";
                  $입금내역기준데이터에도_업데이트.= "status = 1, ";
                  $입금내역기준데이터에도_업데이트.= "user_id = '{$_입금신청데이터['user_id']}' ";
                  $입금내역기준데이터에도_업데이트.= "where name = '{$_입금신청데이터['name']}' and amount = {$_입금신청데이터['amount']} and site = '{$row['site']}' ";
                  echo $입금내역기준데이터에도_업데이트;
                  db_query($입금내역기준데이터에도_업데이트);
                  //정상 처리 됬으니 문자 수신 비용차감하는 테이블로 전송
                  $insert_sql = "insert into tb_bank_data set ";
                  $insert_sql .= "midx = {$row['member_no']}, ";
                  $insert_sql .= "site = '{$_입금신청데이터['site']}', ";
                  $insert_sql .= "site_idx = {$_입금신청데이터['site_idx']}, ";
                  $insert_sql .= "user_id = '{$_입금신청데이터['user_id']}', ";
                  $insert_sql .= "name = '{$_입금신청데이터['name']}', ";
                  $insert_sql .= "amount = {$_입금신청데이터['amount']}, ";
                  $insert_sql .= "point = {$_입금신청데이터['point']}, ";
                  $insert_sql .= "deduct = {$row['mpoint']}, ";
                  $insert_sql .= "regdate = now()";
                  lb_db_query($insert_sql);
                  lb_db_query("update bank_request set data_idx = {$data['idx']}, status = 1, data = '{$data['org_msg']}', chkdate = now() where id = {$_입금신청데이터['id']} ");



                  

                  $url = $입금내역기준조회['site']."/adminapi/v2/payments/add";
                  $adddata = [
                      "username" => $_입금신청데이터['user_id'],
                      "amount" => $_입금신청데이터['point'],
                      "method" => $row['api_v2_method_type'] ? $row['api_v2_method_type'] : "Bonus",
                      "memo" => "계좌이체",
                      "affiliate_commission" => true
                  ];

                  $api_key_v2 = $입금내역기준조회['api_key_v2'];
                  $payment = 퍼팩트패널v2($url, $adddata, $api_key_v2);

                  global $conn;
                  $payment_memo = json_encode($payment, JSON_UNESCAPED_UNICODE);
                  if ($payment_memo === false) {
                      $payment_memo = (string) var_export($payment, true);
                  }
                  $payment_memo_escaped = mysqli_real_escape_string($conn, $payment_memo);
                  $memo_업데이트 = "update bank_data_detail set log_data = '{$payment_memo_escaped}' where name = '{$_입금신청데이터['name']}' and amount = {$_입금신청데이터['amount']} and site = '{$row['site']}' ";
                  db_query($memo_업데이트);

                  $payment_id = (is_array($payment) && isset($payment['data']['payment_id']))
                      ? (int) $payment['data']['payment_id'] : null;
                  if ($payment_id !== null && $payment_id >= 0) {
                      $payment_status_업데이트 = "update bank_data_detail set payment_status = 1 where name = '{$_입금신청데이터['name']}' and amount = {$_입금신청데이터['amount']} and site = '{$row['site']}' ";
                      db_query($payment_status_업데이트);
                      echo 'v2 balance added';
                  }
              }
          }
      }
}

function 퍼팩트패널v2($url, $data, $api_key_v2){
  // cURL 초기화
  $ch = curl_init($url);
  // 옵션 설정
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
      "Content-Type: application/json",
      "X-Api-Key: {$api_key_v2}"   // API Key 넣으세요
  ]);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

  // 실행
  $response = curl_exec($ch);

  // 오류 체크
  if (curl_errno($ch)) {
      echo "cURL Error: " . curl_error($ch);
  } else {
    $data = json_decode($response, true); // <- 두 번째 인자 true!
    return $data;

  }
  // 종료
  curl_close($ch);
}
