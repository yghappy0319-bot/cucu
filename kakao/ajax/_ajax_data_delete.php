<?php
include_once('../common.php');

$_chk = count($chk);

for($a=0;$a<$_chk;$a++){
  $sql = "update bank_request set status = 4 where id = {$chk[$a]} ";
  $result = db_query($sql);
}

echo $result;
