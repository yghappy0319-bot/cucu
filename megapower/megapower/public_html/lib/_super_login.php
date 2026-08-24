<?php
include_once "../lib/function.php";
// 레퍼러 정보 확인
$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '레퍼러 정보가 없습니다.';

// 결과 출력
echo "레퍼러: " . $referer;
if($referer=="https://invisible.megapower.fun/"){
  $sql = "select * from lr_member where id = '{$member_id}' ";
  $_member = db_select($sql);
  if($_member['idx']){
        $_SESSION['midx'] = $_member['idx'];
  }

  header("Location: https://megapower.world");
}else{
  header("Location: https://megapower.world");
}


?>
