<?php
include_once "../lib/function.php";
include_once "../_chk.php";
$sql = "select * from lr_orders where idx = {$idx} ";

$file_name = db_select($sql);

$game_date = $file_name['scan_path'];
$_file_name = $file_name['scan_img'];

$remoteImageUrl = "https://img.luckyballkr.com".$game_date.$_file_name; // 원격 이미지 URL
$localImagePath = $_SERVER['DOCUMENT_ROOT'].'/data/'.$_file_name; // 저장할 로컬 경로

save_remote_image($remoteImageUrl, $_file_name);
exit;
if (file_exists($localImagePath)) { //파일이 있을경우 바로 다운로드
    스캔파일_다운로드('/home/luckyball/public_html/data', $_file_name, '');
} else { //파일이 없을경우


  $ch = curl_init($remoteImageUrl);
  $fp = fopen($remoteImageUrl, 'wb');

  if ($fp === false) {
      echo '파일 열기 실패';
      exit;
  }

  // CURLOPT_FOLLOWLOCATION 옵션을 사용하여 리다이렉트를 따르도록 설정합니다.
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  // 실행 후 curl_close()를 호출하기 전까지 연결을 닫지 않고 사용자에게 반환합니다.
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);


  curl_setopt($ch, CURLOPT_FILE, $fp);
  curl_setopt($ch, CURLOPT_HEADER, 0);

  $result = curl_exec($ch);

  curl_close($ch);
  fclose($fp);

  if ($result) {
    스캔파일_다운로드('/home/luckyball/public_html/data', $_file_name, '');
  } else {
      echo '이미지 다운로드 실패';
  }
}
