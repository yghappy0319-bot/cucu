<?php
//문자로 발송된 링크 눌렀을시 해당 페이지로 접속됨
$cookie_name = "redirect";
$cookie_value = "/adm/service.htm";
$cookie_expire = time() + 3600; // 현재 시간 + 3600초 (1시간)

// 쿠키를 설정합니다.
setcookie($cookie_name, $cookie_value, $cookie_expire, "/");
header("Location: /");
exit();
