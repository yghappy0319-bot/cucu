<?php
include_once "../lib/function.php";
$member = 로그인정보($_SESSION['midx']);

// 세션 시작
session_start();

// 로그인된 사용자인지 확인
if(isset($member['idx'])){
    // 로그인된 사용자라면 세션을 파기하여 로그아웃 처리
    session_destroy();
}
header("Location: /");
?>
