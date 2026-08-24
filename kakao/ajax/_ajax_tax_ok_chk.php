<?php
include_once('../common.php');
$sql = "update bank_request set tax_status = 3 where id = {$id} ";
$result = db_query($sql);
echo $result;
