<?php
include_once('../common.php');

$_work_chk = ($work_chk=="true" ? "1":"0");

$update = db_query("update g5_write_maintenance set wr_10 = '{$_work_chk}' where wr_id = {$wr_id} ");

echo $update;
