<?php
include_once "/home/kakao/public_html/lib/_function.php";
include_once "/home/kakao/public_html/api/function.php";

// -------------------------------------
// 기본 변수 설정
// -------------------------------------
//$length = mb_strlen($nick, 'UTF-8');
//if($length == 4 || $length == 2){
  $두자리닉넴 = getTwoCharNick($nick);
  $설정       = db_select("SELECT * FROM config");
  $msg        = trim($msg);
  $길이       = mb_strlen($msg, 'UTF-8');

  // -------------------------------------
  // 타수(tasu) / newpoint 적용
  // 일반: 타수 1·사진 2 → newpoint 0.1·0.2 | 버프: 타수 2·사진 3 → newpoint 0.2·0.3
  // -------------------------------------
  $사진여부 = ($msg == "사진을 보냈습니다.");
  $마법사용중 = db_select("select idx from tb_item_use where nickname = '{$두자리닉넴}' and (item = '마법' || item = '지호') ");
  if (!empty($마법사용중['idx'])) {
    $tasu1 = $사진여부 ? 3 : 2;
    $newpoint1 = $사진여부 ? 0.3 : 0.2;
  } else {
    $tasu1 = $사진여부 ? 2 : 1;
    $newpoint1 = $사진여부 ? 0.2 : 0.1;
  }
  
  // -------------------------------------
  // 타수 제한 적용
  // -------------------------------------
  if ($길이 <= 1) {
      $tasu1 = 0;
      $newpoint1 = 0;
  }

  $patterns = ['ㅈㅈ', 'ㄱㄱ', '.내냥', '.수령', '.임무', '.궁금', '.금고', '.진행', '.자숙', '.초성', '.정답', '.지호', '.금고란', '.금고마감', '.랜덤박스란', '.채굴란', '.강화방법', '.평타', '.도전', '.일방준비', '.사용설명서', '.가이드'];

  foreach ($patterns as $p) {
      if (strpos($msg, $p) !== false) {
          $tasu1 = 0;
          $newpoint1 = 0;
      }
  }

  $nickname = addslashes($두자리닉넴);
  $msg      = addslashes($msg);
  $tasu     = (int)($tasu1);
  $regdate  = date('Y-m-d H:i:s');

  $batch[] = "('$nickname','$msg','$tasu','$newpoint1','$regdate')";
  $table = 'tb_msg';
  $sql = "INSERT INTO $table (nickname,msg,tasu,newpoint,regdate) VALUES ".implode(',', $batch);
  db_query($sql);
  db_query("update tb_member set newpoint = newpoint + {$newpoint1}, tasu = tasu + {$tasu} where name = '{$nickname}' ");



  // db_query("insert into tb_lotto_info set msg = '{$msg}', leverage = 0, regdate = now() ");
//}
