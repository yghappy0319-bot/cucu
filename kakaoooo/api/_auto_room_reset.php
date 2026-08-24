<?php
/**
 * 매월 1일 우리방 시즌 초기화 크론
 * - 기존 _auto_attendance.php 에도 연결되어 있어, 별도 크론이 없어도 동작함
 * - 단독 크론으로 돌리고 싶으면 이 파일을 매분/매시 호출
 */
include_once "/home/kakao/public_html/lib/_function.php";
include_once "/home/kakao/public_html/api/function.php";
require_once __DIR__ . '/room_reset.inc.php';

$결과 = 우리방초기화_월간자동_시도();
if (!empty($결과['ran'])) {
  echo "ok: monthly room reset ran\n";
} else {
  echo "skip: not day 1 or already done\n";
}
