<?php
include_once "../lib/function.php";

header('Content-Type: application/json; charset=utf-8');

$game = $_GET['game'];
$round = $_GET['round'];

if (!$game || !$round) {
    echo json_encode(['error' => true]);
    exit;
}

$sql = "select * from lr_result where game = '{$game}' and round = '{$round}' ";
$data = db_select($sql);

if (!$data['idx']) {
    echo json_encode(['error' => true]);
    exit;
}

$서머타임 = 미국서머타임();
$alert = '';

if ($data['idx'] < 180) {
    $alert = '해당 당첨 결과는 텍사스 주 결과 입니다.';
}

ob_start();
include "prize_result_detail.php";
$html = ob_get_clean();

echo json_encode(['html' => $html, 'alert' => $alert]);
