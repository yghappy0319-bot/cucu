<?php
// 모바일 세션 유지 시간 (3시간)
ini_set("session.cookie_lifetime", 10800); // 60*60*3 = 10800초
ini_set('session.gc_maxlifetime', 10800);
ini_set('session.cache_expire', 180);  // 60*3 = 180분
//session_set_cookie_params(10800);

session_start();
?>
