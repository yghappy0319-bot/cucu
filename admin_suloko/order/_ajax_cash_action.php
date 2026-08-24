<?php
include $_SERVER['DOCUMENT_ROOT']."/_common/config.php";

if($uidx>0){
    $sql = "update CASH_CONFIG set ";
    $sql.= "CASH_NAME = '{$CASH_NAME}', ";
    $sql.= "AMOUNT = '{$AMOUNT}', ";
    $sql.= "CASH = '{$CASH}', ";
    $sql.= "POINT = '{$POINT}' ";
    $sql.= "where IDX = {$uidx} ";
    $result = $db->query($sql);
}
echo $result;

