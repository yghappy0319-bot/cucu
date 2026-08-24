<?php
/**
 * CASH_CONFIG 금액/보너스포인트 일괄 업데이트
 * 브라우저 또는 CLI에서 1회 실행
 */
include $_SERVER['DOCUMENT_ROOT']."/_common/config.php";

$tiers = array(
  array('name' => '1만',   'amount' => 11000,  'cash' => 10000,  'point' => 0),
  array('name' => '2만',   'amount' => 22000,  'cash' => 20000,  'point' => 0),
  array('name' => '3만',   'amount' => 33000,  'cash' => 30000,  'point' => 3000),   // 10%
  array('name' => '6만',   'amount' => 66000,  'cash' => 60000,  'point' => 9000),   // 15%
  array('name' => '12만',  'amount' => 132000, 'cash' => 120000, 'point' => 24000),  // 20%
  array('name' => '20만',  'amount' => 220000, 'cash' => 200000, 'point' => 50000),  // 25%
  array('name' => '30만',  'amount' => 330000, 'cash' => 300000, 'point' => 90000),  // 30%
  array('name' => '60만',  'amount' => 660000, 'cash' => 600000, 'point' => 210000), // 35%
);

$results = array();

foreach ($tiers as $tier) {
  $exists = $db->get_data("SELECT IDX FROM CASH_CONFIG WHERE AMOUNT = {$tier['amount']} OR CASH = {$tier['cash']} LIMIT 1");

  if (!empty($exists['IDX'])) {
    $sql = "UPDATE CASH_CONFIG SET
      CASH_NAME = '{$tier['name']}',
      AMOUNT = {$tier['amount']},
      CASH = {$tier['cash']},
      POINT = {$tier['point']}
      WHERE IDX = {$exists['IDX']}";
    $db->query($sql);
    $results[] = "UPDATE {$tier['name']} (IDX:{$exists['IDX']})";
  } else {
    $sql = "INSERT INTO CASH_CONFIG (CASH_NAME, AMOUNT, CASH, POINT) VALUES (
      '{$tier['name']}', {$tier['amount']}, {$tier['cash']}, {$tier['point']}
    )";
    $db->query($sql);
    $results[] = "INSERT {$tier['name']}";
  }
}

header('Content-Type: text/plain; charset=UTF-8');
echo "CASH_CONFIG 업데이트 완료\n\n";
echo implode("\n", $results);
