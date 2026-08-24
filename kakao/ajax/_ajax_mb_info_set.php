<?php
include_once('../common.php');

$result = db_query("update g5_member set mb_info = '{$mb_info}' where mb_id = '{$mb_id}' ");
echo $result;
