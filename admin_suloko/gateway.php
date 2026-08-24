<?php
// check url
if (!isset($_GET['url'])) {
  echo "error";
  exit;
}

$url = $_GET['url'];

$url = urldecode($url);

// api request
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url); // URL 지정하기
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.1) Gecko/20061204 Firefox/2.0.0.1");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // 요청 결과를 문자열로 반환
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // connection timeout 10초
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 원격 서버의 인증서가 유효한지 검사 안함
$data = curl_exec($ch);
curl_close($ch);

echo $data;

?>
