<?php include_once "../lib/function.php";
include_once "../_chk.php";
$event_coupon = 0;

$출석 = db_select("select count(*) as cnt from event_attendance where midx = {$midx} and date = '{$date}'");
if($출석['cnt']>0){
  $data = array('status' => 0, 'msg' => $date."은 이미 출석완료 되었습니다!");
  echo json_encode($data);
  exit;
}

$출석순위 = db_select("select count(*) as cnt from event_attendance where date = '{$date}'");
$rank = $출석순위['cnt'] + 1;

$랜덤숫자 = mt_rand(1, 100);
if($rank >= 1 and $rank <= 3){ //1,2,3등 인경우
  if ($랜덤숫자 <= 90) {
    $포인트 = mt_rand(100, 130);
  }else{
    $포인트 = mt_rand(100, 200);
  }
}else{ // 1,2,3등이 아닌경우
  if ($랜덤숫자 <= 90) {
    $포인트 = mt_rand(30, 60);
  }else{
    $포인트 = mt_rand(50, 100);
  }
}

$sql = "insert into event_attendance set ";
$sql.= "midx = {$midx}, ";
$sql.= "title = '{$title}',";
$sql.= "rank = {$rank}, ";
$sql.= "point = {$포인트}, ";
$sql.= "event = 0,";
$sql.= "date = '{$date}', ";
$sql.= "coupon = 0,";
$sql.= "regdate = now() ";
$result = db_query($sql);

function 출석체크_칠일체크($date, $cnt){
  $endDate = new DateTime($date); // 끝 날짜 (2024-01-03)
  $startDate = clone $endDate; // 시작 날짜 (끝 날짜 복사)
  $startDate->modify('-'.$cnt.' days'); // 7일 전부터 시작, 6일을 빼면 7일 간의 날짜
  $dateArray = [];
  while ($startDate <= $endDate) {
      $dateArray[] = "'" . $startDate->format('Y-m-d') . "'";
      $startDate->modify('+1 day');
  }
  $dateString = implode(', ', $dateArray);
  return $dateString;
}

if($event_coupon){

  $칠일데이터 = 출석체크_칠일체크($date, 6);
  $십사일데이터 = 출석체크_칠일체크($date, 13);
  $이십일일데이터 = 출석체크_칠일체크($date, 20);

  $sql = "select count(*) as cnt from event_attendance where midx = {$midx} and date IN ($칠일데이터) and coupon = 0 ";
  $칠일쿠폰 = db_select($sql);
  if($칠일쿠폰['cnt']==7){ //7일 쿠폰 지급
   db_query("update event_attendance set coupon = 7 where midx = {$midx} ");
   쿠폰만들기("할인쿠폰", $midx, "연속 출석 7일 달성 10% 할인쿠폰", 10, "+1 month", 0);
  }

  $sql = "select count(*) as cnt from event_attendance where midx = {$midx} and date IN ($십사일데이터) and (coupon = 0 || coupon = 7) ";
  //echo $sql;
  $십사일쿠폰 = db_select($sql);
  if($십사일쿠폰['cnt']==14){ //14일 쿠폰 지급
   db_query("update event_attendance set coupon = 14 where midx = {$midx} ");
   쿠폰만들기("할인쿠폰", $midx, "연속 출석 14일 달성 20% 할인쿠폰", 20, "+1 month", 0);
  }

  $sql = "select count(*) as cnt from event_attendance where midx = {$midx} and date IN ($이십일일데이터) and (coupon = 0 || coupon = 7 || coupon = 14) ";
  //echo $sql;
  $이십일일쿠폰 = db_select($sql);
  if($이십일일쿠폰['cnt']==21){ //14일 쿠폰 지급
   db_query("update event_attendance set coupon = 14 where midx = {$midx} ");
   쿠폰만들기("할인쿠폰", $midx, "연속 출석 21일 달성 30% 할인쿠폰", 30, "+1 month", 0);
  }
}

$log = array(
  "midx" => $midx,
  "grade" => $member['grade'],
  "buy_idx" => 0,
  "gubun" => '지급',
  "name" => $_웹사이트명.'포인트',
  "etc" => "{$date} 출석체크",
  "status" => 1,
  "point" => $포인트
);
게임구매_럭키포인트_지급로그($log);

$data = array('status' => $result, 'rank' => $rank, 'point' => $포인트);
echo json_encode($data);
