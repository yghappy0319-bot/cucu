<?php
// 모바일 세션 유지 시간 (30일)
ini_set("session.cookie_lifetime", 2592000); // 60*60*24*30 = 2592000초
ini_set('session.gc_maxlifetime', 2592000);
ini_set('session.cache_expire', 43200);  // 60*24*30 = 43200분
//session_set_cookie_params(2592000);

session_start();
?>
