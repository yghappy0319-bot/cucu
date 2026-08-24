<?php
include("/home/sms/public_html/lib/function.php"); // sms db

db_query("insert into log set log = '24시간 지난데이터 삭제처리', regdate = now() ");

$가공된데이터sql = "select * from bank_data_detail where status = 0 order by regdate desc ";
$_가공데이터 = db_query($가공된데이터sql); //가공된 데이터 기반으로 조회
foreach($_가공데이터 as $가공데이터){
  // 주어진 날짜와 시간
  $givenDateTime = new DateTime($가공데이터['deposit_date']);
  // 현재 날짜와 시간
  $currentDateTime = new DateTime();
  // 24시간 이상 경과했는지 확인
  $timeDifference = $currentDateTime->diff($givenDateTime);
  // $timeDifference 객체에서 days, hours, minutes, seconds 등을 확인하여 24시간이 지났는지 판단할 수 있습니다.
  if ($timeDifference->days > 0 || $timeDifference->h >= 24) {
      db_query("update bank_data_detail set status = 2 where idx = {$가공데이터['idx']} "); //입금후 24시간 지난 데이터는 처리안함
  }
}
