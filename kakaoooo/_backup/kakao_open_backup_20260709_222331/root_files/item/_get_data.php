<?php
exit;
include_once $_SERVER['DOCUMENT_ROOT']."/lib/_function.php";

// 필요한 데이터 조회
$where = '';
if ($search) {
    $where .= " and name = '{$search}' ";
}

if ($gender) {
    $where .= " and gender = {$gender} ";
}

// tb_member 데이터를 가져옵니다.
$sql = "SELECT * FROM tb_member WHERE 1=1 {$where} ORDER BY gender ASC, regdate ASC";
$members = db_query($sql);

// tb_item 데이터를 가져옵니다.
$items = db_query("SELECT * FROM tb_item WHERE status = 0 ORDER BY sort ASC");

// 결과를 JSON 형식으로 준비
$data = [];
foreach ($members as $member) {
    $memberData = [
        'idx' => $member['idx'],
        'name' => $member['name'],
        'gender' => $member['gender'],
        'items' => []
    ];

    foreach ($items as $item) {
        $itemctnsql = "SELECT COUNT(*) AS cnt FROM tb_member_item WHERE midx = {$member['idx']} AND itemname = '{$item['sname']}' AND status = 0";
        $itemcnt = db_select($itemctnsql);
        $memberData['items'][] = [
            'itemname' => $item['sname'],
            'count' => ($itemcnt['cnt'] > 0 ? $itemcnt['cnt'] : '')
        ];
    }
    $data[] = $memberData;
}

// JSON 형식으로 출력
header('Content-Type: application/json'); // Content-Type을 JSON으로 설정
echo json_encode([
    'members' => $data,
    'items' => $items
], JSON_UNESCAPED_UNICODE); // JSON_UNESCAPED_UNICODE로 한글 처리
?>
