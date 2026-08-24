<?php
include_once('../common.php');
// 앱에서 에러리스트 서버로 입금내역 푸시

$sql = "select * from g5_member ";
$_member_result = db_query($sql);

$_은행 = array(
  '카카오뱅크' => '15993333',
  '국민은행' => '11111111'
);

//전체회원검색
foreach($_member_result as $val){
  $_code = "E".$val['mb_no'];

  //입금신청내역
  $sql1 = db_select("select count(*) as cnt from g5_deposit where mb_code = '{$_code}' and mb_chk = 0 ");
  if($sql1['cnt']>0){

      $sql2 = db_select("select * from g5_deposit where mb_code = '{$_code}' and mb_chk = 0 ");
      // 은행/입금자명/금액
      //echo $val['mb_bankname'].$sql2['mb_name'].$sql2['mb_amount'];

      //입금 내역 조회
      $sql3 = db_select("select * from g5_test where de_chk = 0  and test2 like '%{$val['mb_bankname']}%' and test2 like '%{$sql2['mb_name']}%' and test2 like '%".number_format($sql2['mb_amount'])."%' limit 1  ");
      if($sql3['id']>0){
        //api로 결과값을 전송하는 작업을 해야함

        db_query("update g5_test set de_chk = 1 where id = {$sql3['id']} ");
      }
  }


}
