<?php
include_once $_SERVER['DOCUMENT_ROOT']."/lib/_function.php";

$today = new DateTime();
$year = $today->format('Y');
$month = $today->format('m');
$firstDay = new DateTime("$year-$month-01");
$lastDay = (clone $firstDay)->modify('last day of this month');

$weeks = [];
$weekNumber = 1;
$startOfWeek = clone $firstDay;
if ($startOfWeek->format('N') != 1) {
    $startOfWeek = (clone $startOfWeek)->modify('last monday');
}

while ($startOfWeek <= $lastDay) {
    $endOfWeek = (clone $startOfWeek)->modify('next sunday');
    if ($endOfWeek > $lastDay) $endOfWeek = clone $lastDay;

    $weeks[$weekNumber] = [
        'start' => $startOfWeek->format('Y-m-d'),
        'end'   => $endOfWeek->format('Y-m-d')
    ];

    $startOfWeek = (clone $endOfWeek)->modify('+1 day');
    $weekNumber++;
}

// 현재 주차 찾기
$currentWeek = 0;
$currentRange = [];
foreach ($weeks as $weekNum => $range) {
    if ($today >= new DateTime($range['start']) && $today <= new DateTime($range['end'])) {
        $currentWeek = $weekNum;
        $currentRange = $range;
        break;
    }
}

if ($currentWeek == 0) {
    echo "현재 날짜가 이번 달 범위에 없습니다.";
    exit;
}

// 현재 주차 날짜 범위
$startDay = date('j', strtotime($currentRange['start']));
$endDay   = date('j', strtotime($currentRange['end']));

// 멤버별 tasu 합계 배열에 저장
$membersData = [];
$members = db_query("SELECT name FROM tb_member");
foreach ($members as $member) {
    $name = $member['name'];
    $sql = "
        SELECT SUM(tasu) AS sum_tasu
        FROM tb_msg
        WHERE nickname = '{$name}'
          AND regdate >= '{$currentRange['start']}'
          AND regdate <= '{$currentRange['end']}'
    ";
    $sum = db_select($sql);
    $tasu = $sum['sum_tasu'] ?? 0;
    $membersData[] = [
        'name' => $name,
        'tasu' => $tasu
    ];
}

// tasu 기준 내림차순 정렬
usort($membersData, function($a, $b) {
    return $b['tasu'] <=> $a['tasu'];
});

// 출력
$msg = "{$month}월 {$currentWeek}주차 {$startDay}일~{$endDay}일<br />";
foreach ($membersData as $m) {
    $msg .= "{$m['name']} - {$m['tasu']}<br />";
}
echo $msg;
?>
