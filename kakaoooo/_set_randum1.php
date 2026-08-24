<?php
include_once $_SERVER['DOCUMENT_ROOT']."/lib/_function.php";

// 회원 목록
$sql = "SELECT * FROM tb_member where status = 0 ORDER BY idx ASC";
$members = [];
$result = db_query($sql);
while($row = db_fetch($result)) {
    $members[] = [
        'idx' => $row['idx'],
        'name' => $row['name']
    ];
}

// 아이템 목록
$sql = "SELECT * FROM tb_item WHERE status = 0";
$items = [];
$result2 = db_query($sql);
while($row = db_fetch($result2)) {
    $items[] = $row['sname'];
}

// 아이템을 랜덤하게 섞음
shuffle($items);

// 매칭
$assignments = [];
for ($i = 0; $i < count($members); $i++) {
    $itemIndex = $i % count($items); // 아이템 인덱스 반복
    $assignments[] = [
        'midx'   => $members[$i]['idx'],
        'member' => $members[$i]['name'],
        'item'   => $items[$itemIndex]
    ];
}

  // 파스텔톤 색상 배열
  $pastel_colors = [
    "#FFB3BA", // 핑크
    "#FFDFBA", // 살구
    "#FFFFBA", // 노랑
    "#BAFFC9", // 민트
    "#BAE1FF", // 하늘
    "#E0BBE4", // 라벤더
    "#F1CBFF", // 연보라
    "#FFD6E0"  // 연분홍
  ];
  db_query("
    INSERT INTO tb_randum_log SET
      regdate = NOW(),
      weekday = CASE DAYOFWEEK(NOW())
        WHEN 1 THEN '일요일'
        WHEN 2 THEN '월요일'
        WHEN 3 THEN '화요일'
        WHEN 4 THEN '수요일'
        WHEN 5 THEN '목요일'
        WHEN 6 THEN '금요일'
        WHEN 7 THEN '토요일'
      END
  ");

  foreach ($assignments as $a):
    // 랜덤 색상 선택
    $bg_color = $pastel_colors[array_rand($pastel_colors)];

      //echo $midx[$a]." -> ".$itemname[$a]."\n";
      $sql = "insert into tb_member_item set ";
      $sql.= "midx = {$a['midx']}, ";
      $sql.= "nick = '{$a['member']}', ";
      $sql.= "status = 0, ";
      $sql.= "itemname = '{$a['item']}', ";
      $sql.= "usedate = '0000-00-00 00:00:00', ";
      $sql.= "regdate = now() ";
      //echo $sql."\n";
      $reuslt = db_query($sql);
?>
  <div class="assignment-item" style="background: <?= $bg_color ?>;">
    <?= htmlspecialchars($a['member']) ?> → <?= htmlspecialchars($a['item']) ?>
  </div>
<?php endforeach; ?>
