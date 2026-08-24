<?php
/* ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
    ┃ 데이터베이스 관련 함수                                                                                                                           ┃
    ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛*/
$lb_conn = mysqli_connect("49.247.171.110","root","Dbwns0226!","bank");
mysqli_query($lb_conn, "set names utf8mb4");

//SQL 쿼리 실행 함수
function lb_db_query($sql){
    global $lb_conn;

    $rs = mysqli_query($lb_conn, $sql);
    return $rs;
}

//개별데이터
function lb_db_select($sql){
    $rs = lb_db_query($sql);
    return @lb_db_fetch($rs);
}

//데이터를 배열로 가져오기
function lb_db_fetch($rs){
    return @mysqli_fetch_array($rs);
}

function lb_db_result($sql){
    $rs = lb_db_query($sql);
    $row = mysqli_fetch_array($rs);
    if($row ) return @$row[0];
}
