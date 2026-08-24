<?php
/*
$qr_level	-> ECC
$qr_size    -> Size
$qrMsg      -> QR Content
*/



//set it to writable location, a place for temp generated PNG files
$PNG_TEMP_DIR = $_SERVER['DOCUMENT_ROOT']."/upload/qrcode/";

//html PNG location prefix
$PNG_WEB_DIR = '/upload/qrcode/';

include "qrlib.php";    

//ofcourse we need rights to create temp dir
if (!file_exists($PNG_TEMP_DIR))
	mkdir($PNG_TEMP_DIR);


$filename = $PNG_TEMP_DIR.'agent.png';

//processing form input
//remember to sanitize user input in real-life solution !!!
$errorCorrectionLevel = 'H';
if (isset($qr_level) && in_array($qr_level, array('L','M','Q','H')))
	$errorCorrectionLevel = $qr_level;    

$matrixPointSize = 5;
if (isset($qr_size))
	$matrixPointSize = min(max((int)$qr_size, 1), 10);


if (isset($qrMsg)) { 

	//it's very important!
	if (trim($qrMsg) == '')
		die('data cannot be empty! <a href="?">back</a>');
		
	// user data
	$filename = $PNG_TEMP_DIR.'agent'.md5($qrMsg.'|'.$errorCorrectionLevel.'|'.$matrixPointSize).'.png';
	QRcode::png($qrMsg, $filename, $errorCorrectionLevel, $matrixPointSize, 2);
	$qr_image_url	=	basename($filename);
 } else {    

	//default data
	//echo 'You can provide data in GET parameter: <a href="?data=like_that">like that</a><hr/>';    
	//QRcode::png('PHP QR Code :)', $filename, $errorCorrectionLevel, $matrixPointSize, 2);    
	$qr_image_url = "";
 }    
	
//display generated file


	
// benchmark
QRtools::timeBenchmark();    

