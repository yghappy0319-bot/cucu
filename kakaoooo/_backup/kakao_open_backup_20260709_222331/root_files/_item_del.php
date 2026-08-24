<?php
include_once $_SERVER['DOCUMENT_ROOT']."/lib/_function.php";

$result = db_query("update tb_member_item set status = 1, usedate = now() where idx = {$idx}");
echo $result;
