<?
#********************* qr.php ****************
 #=> 16글자의 문자를 QR코드로 변환해주는것입니다. 


header("Content-Type: image/jpeg");
$fp = file_get_contents($_GET['images']);
print_r($fp);
// dump the picture and stop the script
fpassthru($fp);
//exit;
?>