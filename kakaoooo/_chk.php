<?php

// 쿠키 이름
$cookie_name = "view";

// 1️⃣ 쿠키가 존재하는지 확인
if (isset($_COOKIE[$cookie_name])) {
    $saved_time = $_COOKIE[$cookie_name];
    $current_time = time();

    // 쿠키 만료 여부 확인
    if ($current_time > $saved_time) {
        echo "접속종료";
        // 만료된 쿠키 삭제
        setcookie($cookie_name, "", time() - 3600, "/");
        echo "쿠키를 삭제했습니다.";
    }
} else {

    if($mng==1 || $a==1){
      // 2️⃣ 쿠키가 없을 경우 새로 생성 (현재시간 + 5분)
      $expire_time = time() + (5 * 60);
      setcookie($cookie_name, $expire_time, $expire_time, "/");
    }else{

    }

    //echo "🍪 쿠키가 없어서 새로 만들었습니다.<br>";
    //echo "만료시각: " . date("Y-m-d H:i:s", $expire_time);
}
