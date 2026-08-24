<?php
include_once('../common.php');
// 앱에서 에러리스트 서버로 입금내역 푸시

$sql = "insert into g5_test set test1 = '{$test1}', test2 = '{$test2}', regdate = now() ";
$result = db_query($sql);
