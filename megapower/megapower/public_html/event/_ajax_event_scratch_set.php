<?php include_once "../lib/function.php";
include_once "../_chk.php";

function 랜덤포인트지급() {
    // 가능한 포인트 값 배열
    $포인트옵션 = [1000, 2000, 3000, 4000, 5000, 6000];

    // 배열에서 무작위로 하나 선택
    $랜덤포인트 = $포인트옵션[array_rand($포인트옵션)];

    return $랜덤포인트;
}

// 예제 사용:
$랜덤포인트 = 랜덤포인트지급();
if($midx>0){
  $오늘 = date("Y-m-d");
  $_도전카운트 = db_select("select idx from event_scratch where midx = {$midx} and status = 0 order by regdate limit 1 ");
  if($_도전카운트['idx']){
      $sql = "update event_scratch set ";
      $sql.= "status = 1, ";
      $sql.= "point = {$랜덤포인트},";
      $sql.= "event = '{$_웹사이트명}포인트( {$랜덤포인트}P )지급!',";
      $sql.= "event_date = '{$오늘}' ";
      $sql.= "where idx = {$_도전카운트['idx']} ";
      $result = db_query($sql);


      $log = array(
        "midx" => $midx,
        "grade" => $member['grade'],
        "buy_idx" => 0,
        "gubun" => '지급',
        "name" => $_웹사이트명.'포인트',
        "etc" => $_웹사이트명."스크레치 {$오늘}",
        "status" => 1,
        "point" => $랜덤포인트
      );
      게임구매_럭키포인트_지급로그($log);

      $data = array('status' => 1, 'point' => $랜덤포인트);
      echo json_encode($data);
  }else{
      $_도전카운트 = db_select("select count(*) as cnt from event_scratch where midx = {$midx} and status = 0 ");
      $data = array('status' => 2, 'point' => $_도전카운트['cnt']);
      echo json_encode($data);
  }

}
