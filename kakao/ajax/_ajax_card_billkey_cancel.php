<?
include_once "../common.php";
include_once "../_chk.php";

$ch = curl_init();
$url = 'https://api.innopay.co.kr/api/delAutoCardBill';

$data = array(
  "mid" => "pgerrormam",
  "billKey" => $billkey,
  "userId" => $userid
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
    $sql = "update member set billKey = '', pay_config_key = 0, auto_money = 0, auto_start_day = null, auto_payment_status = 0 where mb_id = '{$userid}' ";
    //echo $sql;
    db_query($sql);
    db_query("delete from tb_auto_bill where userId = '{$userid}' ");
}

// cURL 세션 종료
curl_close($ch);
?>
