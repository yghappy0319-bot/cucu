<?php
include_once $_SERVER['DOCUMENT_ROOT']."/lib/_function.php";

$회원 = db_select("select * from tb_member where idx = {$midx} ");
$result = db_query("delete from tb_member where idx = {$midx} ");
db_query("delete tb_member_item where midx = {$midx} ");
db_query("delete from tb_point_log where nick = '{$회원['name']}'");
db_query("delete from tb_msg where nickname = '{$회원['name']}'");

echo $result;
