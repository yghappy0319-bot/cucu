<?php
include_once('../common.php');

$id = isset($_POST['idx']) ? (int) $_POST['idx'] : 0;
$mb_no = isset($_POST['mb_no']) ? (int) $_POST['mb_no'] : 0;

$midx_session = isset($_SESSION['midx']) ? (int) $_SESSION['midx'] : 0;
if (!$midx_session) {
  echo json_encode(array('status' => '0', 'msg' => '로그인이 필요합니다.'));
  exit;
}

$member = 로그인정보($midx_session);
if ((int) $member['mb_level'] < 10) {
  echo json_encode(array('status' => '0', 'msg' => '권한이 없습니다.'));
  exit;
}

if (!$id || !$mb_no) {
  echo json_encode(array('status' => '0', 'msg' => '잘못된 요청입니다.'));
  exit;
}

$row = db_select("select idx from tb_pay_log where idx = {$id} and midx = {$mb_no} ");
if (!$row['idx']) {
  echo json_encode(array('status' => '0', 'msg' => '내역을 찾을 수 없습니다.'));
  exit;
}

$result = db_query("delete from tb_pay_log where idx = {$id} and midx = {$mb_no} limit 1 ");
echo json_encode(array(
  'status' => $result ? '1' : '0',
  'msg' => $result ? '결제내역이 삭제되었습니다.' : '삭제에 실패했습니다.'
));
