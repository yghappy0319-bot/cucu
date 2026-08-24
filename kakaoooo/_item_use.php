<?php
include_once $_SERVER['DOCUMENT_ROOT']."/lib/_function.php";

$sql = "select * from tb_member_item where idx = {$idx}  ";
$item = db_select($sql);

$sql = "select * from tb_member where idx = {$item['midx']} ";
$mem = db_select($sql);

$sql = "select * from tb_item_use where nickname = '{$mem['name']}' and item = '프로필변경' ";
$data = db_select($sql);

if($data['idx']){
  $연장 = date("Y-m-d H:i", strtotime($data['enddate']." +3 days "));
  $sql = "update tb_item_use set ";
  $sql.= "enddate = '{$연장}' ";
  $sql.= "where idx = {$data['idx']} ";
  $result = db_query($sql);
  db_query("update tb_member_item set status = 1, usedate = now() where idx = {$idx} ");
}else{
  $오늘 = date("Y-m-d H:i", strtotime("+3 days"));
  $sql = "insert into tb_item_use set ";
  $sql.= "nickname = '{$mem['name']}', ";
  $sql.= "item = '프로필변경', ";
  $sql.= "enddate = '{$오늘}', ";
  $sql.= "regdate = now() ";
  $result = db_query($sql);
  db_query("update tb_member_item set status = 1, usedate = now() where idx = {$idx} ");
}
echo $result;
