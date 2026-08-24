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
  // 일반: 타수 1 → newpoint 0.1 | 버프: 타수 2 → newpoint 0.2
  // 「사진을 보냈습니다」는 생타·버프타·본방냥 모두 제외
  // -------------------------------------
  $사진여부 = (strpos($msg, '사진을 보냈습니다') !== false);
  if ($사진여부) {
    $tasu1 = 0;
    $newpoint1 = 0;
  } else {
    $마법사용중 = db_select("select idx from tb_item_use where nickname = '{$두자리닉넴}' and (item = '마법' || item = '지호') ");
    if (!empty($마법사용중['idx'])) {
      $tasu1 = 2;
      $newpoint1 = 0.2;
    } else {
      $tasu1 = 1;
      $newpoint1 = 0.1;
    }
  }
  
  // -------------------------------------
  // 타수 제한 적용
  // -------------------------------------
  if ($길이 <= 1) {
      $tasu1 = 0;
      $newpoint1 = 0;
  }

  $patterns = ['ㅈㅈ', 'ㄱㄱ', '.내냥', '.내무기', '.수령', '.채굴수리', '.임무', '.궁금', '.금고', '.진행', '.자숙', '.초성', '.정답', '.지호', '.금고란', '.랜덤박스란', '.채굴란', '.강화방법', '.평타', '.도전', '.일방준비', '.사용설명서', '.가이드'];

  foreach ($patterns as $p) {
      if (strpos($msg, $p) !== false) {
          $tasu1 = 0;
          $newpoint1 = 0;
      }
  }

  $nickname = addslashes($두자리닉넴);
  $msg      = addslashes($msg);
  // 0.1타 등 소수 허용 (정수면 정수로)
  $tasuNum = round((float)$tasu1, 1);
  if (abs($tasuNum - (int)$tasuNum) < 0.0001) {
    $tasu = (string)(int)$tasuNum;
  } else {
    $tasu = number_format($tasuNum, 1, '.', '');
  }
  $newpoint1 = round((float)$newpoint1, 2);
  $regdate  = date('Y-m-d H:i:s');

  // 소수 타수 저장용 (최초 1회)
  static $tasuColEnsured = false;
  if (!$tasuColEnsured) {
    $tasuColEnsured = true;
    $info = @db_select("SHOW COLUMNS FROM tb_msg LIKE 'tasu'");
    $type = strtolower((string)($info['Type'] ?? ''));
    if ($type !== '' && !preg_match('/decimal|float|double/', $type)) {
      @db_query("ALTER TABLE tb_msg MODIFY COLUMN tasu DECIMAL(12,1) NOT NULL DEFAULT 0");
    }
    $info2 = @db_select("SHOW COLUMNS FROM tb_member LIKE 'tasu'");
    $type2 = strtolower((string)($info2['Type'] ?? ''));
    if ($type2 !== '' && !preg_match('/decimal|float|double/', $type2)) {
      @db_query("ALTER TABLE tb_member MODIFY COLUMN tasu DECIMAL(16,1) NOT NULL DEFAULT 0");
    }
  }

  $batch[] = "('$nickname','$msg','$tasu','$newpoint1','$regdate')";
  $table = 'tb_msg';
  $sql = "INSERT INTO $table (nickname,msg,tasu,newpoint,regdate) VALUES ".implode(',', $batch);
  db_query($sql);
  db_query("update tb_member set newpoint = newpoint + {$newpoint1}, tasu = tasu + {$tasu} where name = '{$nickname}' ");



  // db_query("insert into tb_lotto_info set msg = '{$msg}', leverage = 0, regdate = now() ");
//}
