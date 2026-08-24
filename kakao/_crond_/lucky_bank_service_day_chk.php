<?php
include "/home/luckybank/public_html/lib/function.php";

db_query("insert into tb_bank_cron set text = '서비스 기간 체크_문자발송', regdate = now() ");
//다이랙트샌드('01022934444', 'test', 'test문자테스트');
$오늘 = date("Y-m-d");
//자동결제는 제외
$smm_user = db_query("select mb_no, mb_id, mb_hp, mb_10, auto_payment_status, deldate from member where mb_10 != '' and auto_payment_status = 0 order by mb_10 asc");
foreach($smm_user as $smm){

  $하루전 = date("Y-m-d", strtotime($smm['mb_10']." -2 days")); // 종료3일전 문자 보냄
//  echo $smm['mb_id']."<br />2일전__".$하루전."__종료일__".$smm['mb_10']."<br />";
  $now = new DateTime();
  // 시작일과 종료일 설정
  $startDate = new DateTime($하루전);
  $endDate = new DateTime($smm['mb_10']);

  // 현재 날짜가 날짜 범위 내에 있는지 확인
  if ($now >= $startDate && $now <= $endDate) {
      echo "현재 날짜는 범위 내에 있습니다: " . $startDate->format('Y-m-d') . "부터 " . $endDate->format('Y-m-d') . "까지.<br />";

      $수신번호 = $smm['mb_hp'];
      $제목 = "무통장 서비스종료 안내";
      $내용 = "무통장 입금확인 서비스\n종료 안내\n";
      $내용.= "종료일 : ".$smm['mb_10']."\n\n";
      $내용.= "아래 주소로 접속하여 서비스기간을 연장해주세요.\n\n";
      $내용.= "https://adm.luckybank.kr/adm/service2.html";
      다이랙트샌드($수신번호,$제목,$내용);

  } else {
      echo "현재 날짜는 범위 밖에 있습니다: " . $startDate->format('Y-m-d') . "부터 " . $endDate->format('Y-m-d') . "까지.<br />";
  }

  // 현재 날짜가 종료일 이후인지 확인
  if ($now > $endDate) {

        $세달뒤삭제될날짜 = date("Y-m-d", strtotime("+2 month"));
        db_query("update member set mb_10 = '', deldate = '{$세달뒤삭제될날짜}' where mb_no = {$smm['mb_no']} ");
  }

}
