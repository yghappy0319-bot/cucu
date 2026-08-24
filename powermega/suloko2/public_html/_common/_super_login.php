<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";


$referer = $_SERVER['HTTP_REFERER'] ?? '';

// 레퍼러 확인 (부분 매칭)
if (strpos($referer, "https://adm.powermegakorea.net") === 0) {

    $sql = "SELECT * FROM MEMBER WHERE USER_ID = '{$mid}' ";
    $_member = $db->get_data($sql); // ← prepare 사용 가정

    if (!empty($_member['MEMBER_NO'])) {
        $_SESSION['M_login'] = [
            'user_id' => $_member['USER_ID'],
            'name'    => $_member['NAME'],
            'hp'      => $_member['HP'],
            'lgbn'    => "",
        ];

        setcookie("c_user_id", '', time() - 3600); // 쿠키 삭제
    }
}

// 무조건 이동
header("Location: https://powermegakorea.net");
exit;