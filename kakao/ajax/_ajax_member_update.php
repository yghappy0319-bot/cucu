<?php
include_once('../common.php');

$sql = "update member set mb_memo = '{$mb_memo}' where mb_no = {$idx} ";
$result = db_query($sql);
echo $result;
