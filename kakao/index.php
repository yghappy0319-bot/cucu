<?php
// https://adm.luckybank.kr/?info=kmong
if($_GET['info']=="kmong"){
    header("Location: https://open.kakao.com/o/soV2H3gf");
    exit;
}

header("Location: /page/login.php");
