<?
include_once "../common.php";
include_once "../_chk.php";

$ch = curl_init();
$url = 'https://api.innopay.co.kr/api/regAutoCardBill';

$cardNum = $cardNum1.$cardNum2.$cardNum3.$cardNum4;

// 앞 2글자: 월, 뒤 2글자: 년
$_expire = $cardExpire;
$month = substr($_expire, 0, 2); // "10"
$year = substr($_expire, 2, 2);  // "29"
$expire = $year . $month; // "2910"

$data = array(
  "mid" => "pgerrormam",
  "buyerName" => $_POST['buyerName'],
  "cardNum" => $cardNum,
  "cardExpire" => $expire,
  "cardPwd" => $_POST['cardPwd'],
  "idNum" => $_POST['idNum'],
  "moid" => $_POST['moid'],
  "billKey" => "",
  "userId" => $_POST['userId'],
  "arsUseYn" => $_POST['arsUseYn']
);

// 데이터 배열을 JSON 형식으로 인코딩
$jsonData = json_encode($data);

// cURL 옵션 설정
curl_setopt($ch, CURLOPT_URL, $url); // 요청할 URL
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // 응답을 문자열로 반환
curl_setopt($ch, CURLOPT_POST, true); // POST 방식 요청
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData); // POST 데이터
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/json', // Content-Type 헤더 설정
    'Content-Length: ' . strlen($jsonData) // JSON 데이터의 길이 설정
));

// API 요청 실행 및 응답 받기
$response = curl_exec($ch);

// 오류 확인
if(curl_errno($ch)) {
    echo 'cURL Error: ' . curl_error($ch);
} else {
    // 응답 결과 출력
    $jsonDatas = json_encode($response); //json으로 생성후
    $datas = json_decode($jsonDatas); //
    echo $datas;

    // JSON 문자열을 PHP 객체로 디코딩
    $data = json_decode($datas);

    if($data->billKey){
      $sql = "update member set billKey = '{$data->billKey}', pay_config_key = {$pay_config_key}, auto_money = {$auto_amt} where mb_no = {$member['mb_no']} ";
      db_query($sql);
    }

}

// cURL 세션 종료
curl_close($ch);
?>
