<?php
/**
 * 최신 누적 당첨자 수·누적 당첨금액 (MAIN_ACCUMULATE_INFO 기준)
 * GET /api/main_accumulate.php
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/_common/config.php';

header('Content-Type: application/json; charset=UTF-8', true);

$row = $db->get_data(
  "SELECT WIN_CNT, WIN_MONEY, CREATED_AT
   FROM MAIN_ACCUMULATE_INFO
   ORDER BY IDX DESC
   LIMIT 1"
);

if (!$row || !is_array($row)) {
  echo json_encode(
    [
      'ok' => false,
      'win_cnt' => 0,
      'win_money' => 0,
      'created_at' => null,
    ],
    JSON_UNESCAPED_UNICODE
  );
  exit;
}

echo json_encode(
  [
    'ok' => true,
    'win_cnt' => (int) $row['WIN_CNT'],
    'win_money' => (int) $row['WIN_MONEY'],
    'created_at' => $row['CREATED_AT'],
  ],
  JSON_UNESCAPED_UNICODE
);
