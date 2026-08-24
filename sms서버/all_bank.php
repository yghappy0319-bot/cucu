<?php

include("/home/sms/public_html/lib/function.php"); // sms db
include("/home/sms/public_html/lib/errorlist_function.php");
include("/home/sms/public_html/lib/luckybank_function.php");
include("/home/sms/public_html/lib/perfectpanel.php");

db_query("insert into log set log = 'new_all_bank_v1', regdate = now() ");

// ------V1만 실행

$_하루전 = date("Y-m-d H:i:s", strtotime("-1 days"));
//echo " 24시간 데이터만 확인함 ".$_하루전."<br />";

$오늘 = date("Y-m-d");
$bank_list_sql = "select a.*, b.mb_10 from site a left join member b on a.member_no = b.mb_no
where b.mb_10 != '' and b.mb_10 >= '{$오늘}' and a.website = 0 and luckypanel = 1 order by mb_10 desc";

echo $bank_list_sql."<br />";

$bank_list_result = lb_db_query($bank_list_sql);
for($i=0;$row=lb_db_fetch($bank_list_result);$i++){
  if(luckypanel_bankdata_전송중단($row['luckypanel'], $row['luckypanel_bankdata'])){
    echo $row['site']."_luckypanel_bankdata 전송중단<br />";
    continue;
  }
  
  echo $row['site']."_<br />";

    $가공된데이터sql = "select * from bank_data_detail where status = 0 and (memo is null or memo = '') and site = '{$row['site']}' order by regdate desc ";
    echo $가공된데이터sql."<br />";
    $result = db_query($가공된데이터sql); // 입금처리가 확인된 데이터며 1차 가공
        foreach($result as $data){

          $_계좌확인 = 포함여부($data['acount'], $row['acount']); //가공된 데이터랑 입금 계좌번호 비교
          if($_계좌확인){ //계좌번호가 같으면..

            //무통장 신청건 조회
            $신청sql = "select * from bank_request where status = 0 and name = '{$data['name']}' and amount = {$data['amount']} and regdate > '{$_하루전}' order by regdate desc limit 1  ";
            echo $신청sql."<br />";
            $_입금신청데이터 = lb_db_select($신청sql); //가공된 데이터와 신청서가 맞으면..

            //계좌는 같은데 사이트는 다를경우가 있네.. 확인처리는 입금내역을 기준으로..
            $site_sql = "select site, luckypanel, luckypanel_bankdata, api_key  from site where site = '{$_입금신청데이터['site']}' ";
            //echo $site_sql."<br />";
            $입금내역기준조회 = lb_db_select($site_sql);


              if($_입금신청데이터['user_id']!=""){ //무조건 아이디가 있어야 가능

                  if(luckypanel_bankdata_전송중단($입금내역기준조회['luckypanel'], $입금내역기준조회['luckypanel_bankdata'])){
                    echo $입금내역기준조회['site']."_luckypanel_bankdata 전송중단<br />";
                    continue;
                  }

                  if($입금내역기준조회['luckypanel']){
                    //럭키패널일 경우 api url 끝에 / << 하나추가해야함..
                    $api_url = $입금내역기준조회['site']."/adminapi/v1/";
                  }else{
                    $api_url = $입금내역기준조회['site']."/adminapi/v1";
                  }
                  echo "api_url :: ".$api_url;
                  //퍼팩트패널 api
                  $api = new Api();
                  $_api_data = array($api_url, "{$입금내역기준조회['api_key']}");
                  $payment = $api->addPayment($_api_data, $_입금신청데이터['user_id'], $_입금신청데이터['point'], "계좌이체");
                  var_dump($payment);
                  //신청건이 접수되고.. 입금이 진행되면 ...
                  //신청건을 찾아 매칭한다

                  if ($payment->status == 'success') {
                      echo 'balance added';
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

                  }

              }
          }
      }
}
