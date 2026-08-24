<?php
include_once "../lib/function.php";
include_once "../_chk.php";

$sql = "update lr_member set ";
$sql.= "push = '{$playerId}' ";
$sql.= "where id = '{$userId}' ";
$result = db_query($sql);
