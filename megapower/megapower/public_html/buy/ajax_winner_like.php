<?php include_once "../lib/function.php";
include_once "../_chk.php";
if(!$member['idx']){
  die("login");
}

function 럭키포인트랜덤() {
    return rand(1, 3);
}
$럭키포인트 = 럭키포인트랜덤();


$cnt = db_select("select count(*) as cnt from lr_winner_like where pidx = {$idx} and game = '{$game}' and round = '{$round}' and midx = {$member['idx']}");

if($cnt['cnt']>0){
  die('over');
}

$sql = "insert into lr_winner_like set ";
$sql.= "pidx = {$idx}, ";
$sql.= "midx = {$member['idx']}, ";
$sql.= "game = '{$game}',";
$sql.= "round = '{$round}',";
$sql.= "lucky_point = {$럭키포인트},";
$sql.= "regdate = now() ";
$result = db_query($sql);

db_query("update lr_prize_log set plike = plike + 1 where idx = {$idx} ");


if($result){
 $_result = db_query("update lr_member set lucky_point = lucky_point + {$럭키포인트} where idx = {$member['idx']} ");
  if($_result){
    echo $럭키포인트;
  }else{
    die("error1");
  }
}else{
  die("error2");
}
//echo $_result;
