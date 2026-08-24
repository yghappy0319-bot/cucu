<?php
include $_SERVER['DOCUMENT_ROOT']."/_common/config.php";

$query = "select * from BBS where BBS_NO='".$_GET['no']."'";
$tmp = $db->get_data($query);

$file = $tmp["FILE".$_GET['idx']];
$fild_name = "FILE".$_GET['idx'];

if (strstr($file, 'data/') !== False) {
 $pre_file = '/upload/'.$file;
} else {
 $pre_file = $_SERVER['DOCUMENT_ROOT'].'/upload/BBS/'.$file;
}

@unlink($pre_file);

$query = "UPDATE BBS SET ".$fild_name." = '' WHERE BBS_NO='".$_GET['no']."'";
$db->query($query);

history_go();
