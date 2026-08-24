<?php
/* ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
    ┃ 데이터베이스 관련 함수                                                                                                                           ┃
    ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛*/
$conn1 = mysqli_connect("141.164.63.162","sms","Gkstlr59!","sms");
mysqli_query($conn1, "set names utf8mb4");
//DB 접속

// function db_connect($db_host, $db_user, $db_pass, $db_name){
//     $result = mysqli_connect($db_host, $db_user, $db_pass) or die(mysql_error());
//     mysqli_select_db($db_name) or die(mysql_error());
//     mysqli_query($conn, "set names utf8");
//     return $result;
// }


//SQL 쿼리 실행 함수
function sms_db_query($sql){
    global $conn1;

    $rs1 = mysqli_query($conn1, $sql);
    return $rs1;
}

//개별데이터
function sms_db_select($sql){
    $rs1 = sms_db_query($sql);
    return @sms_db_fetch($rs1);
}

//데이터를 배열로 가져오기
function sms_db_fetch($rs1){
    return @mysqli_fetch_array($rs1);
}
