<?php
$serverKey = 'AAAAAGX7VLI:APA91bGjleoER6LfRbCC20RleW5_S22jP_oFUkPaALEddZdJcBzXjxfO9XRTjML0VHvarN0ye6F0PjkznBCikRW6cSdPZQZ-04neIKxLoq4e2dAgCRpjvbi1sv1LrC3STegD2yQGYa7m';  // 🔑 Firebase 프로젝트의 서버 키
$topic = 'basic';  // 푸시 보낼 토픽명

$headers = [
    'Authorization: key=' . $serverKey,
    'Content-Type: application/json'
];

$msg = "
■ 1등 당첨금 [3,858억]
■ 2등 당첨금 [27억~139억]
■ 3등 당첨금 [2,780만원~1억3천]
■ 4등 당첨금 [139만원~695만원]
■ 5등 당첨금 [55만원~278만원]
■ 6등 당첨금 [2.7만원~13만원]
■ 7등 당첨금 [2.7만원~13만원]
■ 8등 당첨금 [1.9만원~9.7만원]
■ 9등 당첨금 [1.3만원~6.9만원]

■ 메가밀리언 2107회차 주문 마감까지 [10시간] 남았습니다. (17회 연속 이월 중) 

회원님의 당첨을 대한민국 TOP100 브랜드 슈로코가 응원합니다~♡";

$data = [
    'to' => '/topics/' . $topic,
    'notification' => [
        'title' => '메가밀리언 [3,858억] 주문마감 10시간 전',
        'body' => $msg,
        'sound' => 'default'
    ],
    'data' => [
        'custom_key' => 'custom_value'  // 필요시 추가 데이터
    ]
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
$response = curl_exec($ch);
curl_close($ch);

echo "Response from FCM: " . $response;
