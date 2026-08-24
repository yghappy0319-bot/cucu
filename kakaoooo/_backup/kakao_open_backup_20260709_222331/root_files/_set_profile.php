<?php
include_once $_SERVER['DOCUMENT_ROOT']."/lib/_function.php";

$닉네임 = count($nickname);

for($i=0;$i<$닉네임;$i++){

  $update_sql = "update tb_member set content = '{$content[$i]}' where name = '{$nickname[$i]}' ";
  $result = db_query($update_sql);
}
echo $result;
