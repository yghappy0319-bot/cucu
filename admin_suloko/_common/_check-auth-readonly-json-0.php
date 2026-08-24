<?php

$__admin_login = admin_get_session_login();
if (isset($__admin_login["mode"]) && $__admin_login["mode"] == "readonly") {
  echo json_encode(array("error"=>0, "msg"=>"[권한오류] 실행 권한이 없습니다."));
  exit;
}
unset($__admin_login);
