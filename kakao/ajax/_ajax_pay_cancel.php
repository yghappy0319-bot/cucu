<?php
include_once('../common.php');

$id = isset($_POST['idx']) ? (int) $_POST['idx'] : 0;
if (!$id) {
  die('잘못된 요청입니다.');
}

$midx_session = isset($_SESSION['midx']) ? (int) $_SESSION['midx'] : 0;
if (!$midx_session) {
  die('로그인이 필요합니다.');
}

$member = 로그인정보($midx_session);
if (!isset($member['mb_no'])) {
  die('로그인이 필요합니다.');
}

$row = db_select("select * from tb_pay_log where idx = {$id} ");
if (!$row || !$row['idx']) {
  die('내역을 찾을 수 없습니다.');
}

if ((int) $row['status'] !== 0) {
  die('미결제 건만 취소할 수 있습니다.');
}

$is_admin = ($member['mb_id'] === 'admin');
if (!$is_admin && (int) $row['midx'] !== (int) $member['mb_no']) {
  die('권한이 없습니다.');
}

$result = db_query("delete from tb_pay_log where idx = {$id} limit 1 ");
echo $result ? '1' : '0';
