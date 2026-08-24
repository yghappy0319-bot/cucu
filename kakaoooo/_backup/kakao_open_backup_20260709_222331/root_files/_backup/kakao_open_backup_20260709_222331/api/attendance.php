<?php
include_once __DIR__ . '/function.php';
$sql = "select count(*) as cnt from tb_attendance
        where regdate = '{$오늘}' and nickname = '{$두자리닉넴}' ";
$cnt = db_select($sql);
if(!$cnt['cnt']){ //출석데이터가 없는경우에 동작
  // 어제 날짜
  $yesterday = date('Y-m-d', strtotime('-1 day'));

  // 연속 출석 계산
  if ($정보['last_att_date'] == $yesterday) {
      // 연속 성공
      $newStreak = $정보['streak'] + 1;
  } else {
      // 끊김 → 1일로 초기화
      $newStreak = 1;
  }

  $sql = "insert into tb_attendance set ";
  $sql.= "regdate = '{$오늘}', ";
  $sql.= "nickname = '{$두자리닉넴}' ";
  db_query($sql);

  $onePercent = floor($정보['point'] * $계급출석비율);

  // 연속출석 보상: 1일차 10만, 2일차 20만, 3일차 30만 ... (일차 × 10만)
  $연속출석보상 = $newStreak * 100000;

  $출석지급 = $onePercent;
  $출석계급합 = $onePercent + $연속출석보상;
  db_query("update tb_member set point = point + {$출석계급합}, streak = {$newStreak}, last_att_date = '{$오늘}', attendance = 1 where name = '{$두자리닉넴}' ");

  지급로그($계급['name'].' 출석', $두자리닉넴, $두자리닉넴, 0, $출석지급);
  지급로그('연속출석', $두자리닉넴, $두자리닉넴, 0, $연속출석보상);

  $출석포인트 = db_select("select * from tb_member where name = '{$두자리닉넴}' ");
  $msg = "{$계급['name']} {$두자리닉넴} 어서와🎉\n계급보상 ".number_format($출석지급) . "냥\n";
  $msg.= "연속출석보상({$newStreak}일째)\n" . number_format($연속출석보상) . "냥";
  echo 전송($msg);
}else{
  $msg = "";
  $msg.= $계급['name']." ".$두자리닉넴."❤️ {$정보['streak']}회 ‍출석완료!";
  // $총출석 = db_select("select count(*) as cnt from tb_attendance where nickname = '{$두자리닉넴}'   ");
  // $msg.= "\n\n📅 총 출석일 : {$총출석['cnt']}일";
  // $sql = "select * from tb_member where name = '{$두자리닉넴}' ";
  // $받는친구 = db_select($sql);
  // $msg.= "\n🔁 연속 출석일 : {$받는친구['streak']}일째";
  // $출석포인트 = db_select("select * from tb_member where name = '{$두자리닉넴}' ");
  echo 전송($msg);
}
