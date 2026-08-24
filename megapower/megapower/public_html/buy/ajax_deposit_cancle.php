<?php
include_once "../lib/function.php";

$midx = $_SESSION['midx'];
if(!$midx){
  die("로그인 후 이용해주세요.");
}

$체크 = db_select("select * from lr_deposit_log where idx = {$idx} ");
if($체크['status']==2){
  die("입금처리가 진행되어 취소가 불가합니다.");
}

$result = db_query("update lr_deposit_log set status = 5, status_title = '취소', memo = '고객취소' where idx = {$idx} ");
echo $result;
