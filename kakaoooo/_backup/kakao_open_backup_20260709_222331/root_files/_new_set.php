<?php
include_once $_SERVER['DOCUMENT_ROOT']."/lib/_function.php";

$couple = $couple > 0 ? $couple:0;

if($couple==2){
  $높번 = db_select("select max(num) as nmax from tb_member");
  $num = $높번['nmax'] + 1;
}

// $번호체크 = db_select("select count(*) as cnt from tb_member where num = {$num} and status = 0 ");
// if($번호체크['cnt']>0){
//   echo "다른 번호를 선택해주세요.";
//   exit;
// }

// name3 이 원래 닉, name이 변경닉
if($name3 != $name){ //변경되는경우
  db_query("update tb_msg set nickname = '{$name}' where nickname = '{$name3}' "); //메세지
  db_query("update tb_attendance set nickname = '{$name}' where nickname = '{$name3}' "); //출석
  db_query("update tb_member_item set nick = '{$name}' where nick = '{$name3}' "); //아이템
  db_query("update tb_point_log set nick = '{$name}' where nick = '{$name3}' "); //포인트 로그
  db_query("update tb_winner set nick = '{$name}' where nick = '{$name3}' "); // 지또로그
  db_query("update tb_battle set nick1 = '{$name}' where nick1 = '{$name3}' "); // 맞짱로그
  db_query("update tb_item_use set nickname = '{$name}' where nickname = '{$name3}' "); //프변로그
}

$sql = "update tb_member set ";
$sql.= "couple = '{$couple}', ";
$sql.= "name = '{$name}', ";
$sql.= "num = {$num}, ";
$sql.= "gender = '{$gender}', ";
$sql.= "content = '{$content}' ";
$sql.= "where idx = {$number} ";
$result = db_query($sql);
echo $result;

if($content){
    db_query("insert into tb_member_profile set name = '{$name}', content = '{$content}', regdate = now() ");
}
