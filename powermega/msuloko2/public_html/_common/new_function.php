<?php
header('Content-Type: text/html; charset=UTF-8');
@session_start();
extract($_GET);
extract($_POST);
date_default_timezone_set('Asia/Seoul');

/* ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
    ┃ 데이터베이스 관련 함수                                                                                                                           ┃
    ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛*/

// DB 연결계정 정보
$db_host = "15.165.37.71";
$db_database = "super";
$db_user = "root";
$db_pass = "tnfhzh09*&^%";

$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_database);
mysqli_query($conn, "set names utf8mb4");
//DB 접속
// function db_connect($db_host, $db_user, $db_pass, $db_name){
//     $result = mysqli_connect($db_host, $db_user, $db_pass) or die(mysql_error());
//     mysqli_select_db($db_name) or die(mysql_error());
//     mysqli_query($conn, "set names utf8");
//     return $result;
// }

//SQL 쿼리 실행 함수
function db_query($sql){
    global $conn;
    $rs = mysqli_query($conn, $sql);
    return $rs;
}

//개별데이터
function db_select($sql, $params = []){
    $rs = db_query($sql, $params);
    return db_fetch($rs);
}

//데이터를 배열로 가져오기
function db_fetch($rs){
    return @mysqli_fetch_array($rs);
}
function db_assoc($rs){
    return @mysqli_fetch_assoc($rs);
}


function db_result($sql){
    $rs = db_query($sql);
    $row = mysqli_fetch_array($rs);
    if($row ) return @$row[0];
}