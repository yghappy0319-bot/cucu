<?php
include "/home/luckybank/public_html/lib/function.php";
// 이 크론은 정상수신건에 대한 포인트 차감처리를 진행하는 작업
db_query("insert into tb_bank_cron set text = '럭키뱅크_서버에서_수신포인트차감', regdate = now() ");
//$where = " and midx = 2 and site_idx = 7 ";
$sql = "select * from tb_bank_data where status = 0 {$where} order by regdate desc "; //수신완료된 데이터..!
echo $sql;
$result = db_query($sql);
$data = date("ymdhis");
for($i=0;$row=db_fetch($result);$i++ ){
    $_mem = db_select("select * from member where mb_no = {$row['midx']} ");
    if(!$_mem['mb_10']){
      break;
    }

    $_site = db_select("select * from site where idx = {$row['site_idx']}");

    $남은포인트 = $_mem['mb_point'] - $row['deduct'];

      $sql = "insert into tb_point_log set ";
      $sql.= "midx = {$_mem['mb_no']}, ";
      $sql.= "siteidx = {$row['site_idx']},";
      $sql.= "mb_id = '{$_mem['mb_id']}', ";
      $sql.= "status = 1, "; //차감
      $sql.= "point1 = {$_mem['mb_point']},"; //차감전 보유포인트
      $sql.= "point2 = {$남은포인트}, "; //차감후 포인트
      $sql.= "point3 = {$row['deduct']},"; // 얼마차감되는지?
      $sql.= "regdate = now() ";
      db_query($sql);

      db_query("update member set mb_point = mb_point - {$row['deduct']} where mb_no = {$_mem['mb_no']}  ");
      db_query("update tb_bank_data set status = 1 where id = {$row['id']} ");

}
