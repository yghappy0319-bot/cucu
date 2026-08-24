<?php include_once "../lib/function.php";
include_once "../_chk.php";



$cnt = db_select("select count(*) as cnt from lr_my_number where midx = {$member['idx']} and gubun = '{$order_game_name}' ");
if($cnt['cnt']>9){
  die(($order_game_name=="pb" ? "파워볼":"메가밀리언")." 최대 10건만 저장 가능 합니다.");
}

$lottoColumns = array("lotto1", "lotto2", "lotto3", "lotto4", "lotto5", "lotto6", "lotto7", "lotto8", "lotto9", "lotto10");
$_lotto = count($lotto);

$선택값 = array();
$aa=1;
for($a=0;$a<$_lotto;$a++){
  if($lotto[$a]>0){
    $선택값[$a] = $lotto[$a];
    $aa++;
  }
}

$newLottoArray = array();
foreach ($선택값 as $key => $value) {
  $numbers = explode(",", $value);
  $lastNumber = array_pop($numbers);
  sort($numbers);
  $numbers[] = $lastNumber;
  $newString = implode(",", $numbers);
  $newLottoArray[$key] = $newString;
}

$저장개수 = count($newLottoArray);

$최종저장개수 = 0;
for($c=0;$c<5;$c++){
  if($newLottoArray[$c]){
    
    $sql = "insert into lr_my_number set ";
    $sql.= "midx = {$member['idx']}, ";
    $sql.= "lotto1 = '{$newLottoArray[$c]}',";
    $sql.= "gubun = '{$order_game_name}',";
    $sql.= "regdate = now() ";
    $result = db_query($sql);
    $최종저장개수++;
  }

}
//echo $result;

if($저장개수>5){
  echo "자주쓰는 번호는 한번에 최대 5개까지 가능합니다.\n상위 {$최종저장개수}게임의 번호가 저장됩니다.";
}else{
  echo $result;
}

//
// $_newLotto = count($newLottoArray);
// $fullLoops = floor($_newLotto / count($lottoColumns)); // $_lotto를 5로 나눈 몫 (모든 루프가 5개 값으로 채워질 때까지)
// $partialLoop = $_newLotto % count($lottoColumns); // 나머지 루프 (부분 루프로 채울 값 개수)
//
// $_번호 = $cnt['cnt'] + 1;
// for ($i = 0; $i < $fullLoops + ($partialLoop > 0 ? 1 : 0); $i++) {
//   $loopSize = ($i < $fullLoops) ? count($lottoColumns) : $partialLoop;
//
//   for ($j1 = 0; $j1 < $loopSize; $j1++) {
//       $valueIndex1 = $i * count($lottoColumns) + $j1;
//       $value1 = $newLottoArray[$valueIndex1];
//       $sql1.= $lottoColumns[$j1]." = '{$value1}' and ";
//   }
//
//     $_중복번호sql = "select count(*) as cnt from lr_my_number where {$sql1} midx = {$member['idx']} and gubun = '{$order_game_name}' ";
//     $_중복번호체크 = db_select($_중복번호sql);
//     if($_중복번호체크['cnt']>0){
//       die("이미 저장된 번호 입니다. 다른 번호를 저장해 주세요.");
//     }
//
//     // /echo "Loop " . ($i + 1) . "\n";
//     $sql = "insert into lr_my_number set ";
//     $sql.= "midx = {$member['idx']}, ";
//     $sql.= "num = {$_번호},";
//
//     for ($j = 0; $j < $loopSize; $j++) {
//         $valueIndex = $i * count($lottoColumns) + $j;
//         $value = $newLottoArray[$valueIndex];
//         $sql.= $lottoColumns[$j]." = '{$value}', ";
//     }
//
//
//     $sql.= "gubun = '{$order_game_name}',";
//     $sql.= "regdate = now() ";
//     $result = db_query($sql);
// }
//
//
