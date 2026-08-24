<?php
include_once $_SERVER['DOCUMENT_ROOT']."/lib/_function.php";

$회원pk = count($midx);
for($a=0;$a<$회원pk;$a++){
  //echo $midx[$a]."<br />";
  $mem = db_select("select name from tb_member where idx = {$midx[$a]} ");
  for($i=0;$i<$cnts;$i++){
    $sql = "insert into tb_member_item set midx = {$midx[$a]}, nick = '{$mem['name']}', itemname = '{$item}', usedate = '0000-00-00 00:00:00', regdate = now()  ";
    //echo $sql."\n";
    $result = db_query($sql);
  }

}
echo $result;
