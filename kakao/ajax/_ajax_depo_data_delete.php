<?php
include_once "/home/luckybank/public_html/lib/sms_server_function.php";
//sms_db_query();
$chk = $_POST['chk'];

$_chk = count($chk);
for($a=0;$a<$_chk;$a++){
  $sql = "update bank_data_detail set status = 4 where idx = {$chk[$a]} ";
  $result = sms_db_query($sql);
}

echo $result;
