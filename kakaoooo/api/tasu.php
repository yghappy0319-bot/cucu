<?php


if($설정['냥두배'] == 2){
  $이벤트 = "x2 이벤트중";
}else{
  $사용중여부 = db_select("select idx from tb_item_use where nickname = '{$두자리닉넴}' and item = '지호1' ");
  if($사용중여부['idx']){
    $설정['냥두배'] = 2;
    $이벤트 = "x2 지호 적용중";
  }
}

  $sql = "select sum(tasu) as cnt from tb_msg where nickname = '{$두자리닉넴}' AND
  regdate >= CURDATE() AND regdate < CURDATE() + INTERVAL 1 DAY ";
  $타수 = db_select($sql);

  $rewardRanges = 타수보상조건();
  // 기본 메시지 초기화
  $msg = "";

  // ✅ 조건 반복 체크
  foreach ($rewardRanges as $r) {
      if ($타수['cnt'] > $r['min'] && $타수['cnt'] < $r['max']) {
          if ($r['point'] === 0) {
              $msg = $r['msg']; // 기본 안내문
          } else {
              $지급포 = $r['point'] * $설정['냥두배'];
              $result = 포인트지급("⌨️".$r['label'], $두자리닉넴, "", $지급포, 0);

              if ($result) {
                  // ⭐ 개수는 포인트 크기에 따라 자동 생성
                  $stars = str_repeat('⭐️', min(3, ceil($r['point'] / 2)));
                  $감탄 = ($r['point'] >= 5) ? "!!" : "!";
                  $msg = "\n{$stars} +{$지급포} {$단위} 획득{$감탄} {$이벤트}";
              }
          }
          break; // 해당 구간 찾으면 반복 종료
      }
  }
  $정보 = db_select("select * from tb_member where name = '{$두자리닉넴}' ");
  $타수계급 = 계급($정보['point']);

  $타수 = $타수['cnt'] ? $타수['cnt']:0;
  $msg1 = $타수계급['name']." ".$두자리닉넴." {$타수} 타{$msg}";
  echo 전송($msg1);
  exit;
