<?php
include_once $_SERVER['DOCUMENT_ROOT']."/lib/_function.php";

$상태 = db_select("select * from tb_member where idx = {$obj2}");

if($obj1=="oneroom"){
  if($상태['oneroom']==0){
    db_query("update tb_member set oneroom = 1 where idx = {$obj2} ");
  }else{
    db_query("update tb_member set oneroom = 0 where idx = {$obj2} ");
  }
}
if($obj1=="couple"){
  if($상태['couple']==0){
    db_query("update tb_member set couple = 1 where idx = {$obj2} ");
  }else{
    db_query("update tb_member set couple = 0 where idx = {$obj2} ");
  }
}
