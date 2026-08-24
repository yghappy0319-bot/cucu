<?php
$location = "http://49.247.29.193".$_GET['path']$_GET['fn'];
$extTemp = explode('.',basename($location));
$ext = $extTemp[1]; if($ext == 'jpg') { $ext = 'jpeg'; }
header('Content-Type: image/jpg');
readfile($location);
