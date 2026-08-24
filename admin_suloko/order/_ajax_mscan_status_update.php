<?php
include $_SERVER['DOCUMENT_ROOT']."/_common/config.php";


$sql = "update ORDERS set PRINT = 1 where ORDERS_NO = {$orderno} ";
$result = $db->query($sql);

echo $result;