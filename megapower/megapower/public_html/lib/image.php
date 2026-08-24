<?php
include_once "../lib/function.php";
include_once "../_chk.php";

if($path){
  $_img = db_select("select * from lr_orders where idx = {$path}  ");
}


$location = 'http://49.247.29.193'.$_img['scan_path'].$_img['scan_img'];
$extTemp = explode('.',basename($location));
$ext = $extTemp[1]; if($ext == 'jpg') { $ext = 'jpeg'; }
header('Content-Type: image/jpg');
readfile($location);
?>
