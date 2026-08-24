<?php include_once "../lib/function.php";
include_once "../_chk.php";

$_idx = count($idxs);

$myNumber = array();
for($a=0;$a<$_idx;$a++){
  $sql = "SELECT * FROM lr_my_number WHERE idx = {$idxs[$a]}";
  $data = db_select($sql);
        $myNumber[$a] = $data['lotto1'];
}

$_data = array($myNumber);
echo json_encode($_data);
