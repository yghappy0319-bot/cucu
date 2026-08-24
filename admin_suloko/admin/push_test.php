<?php
require_once $_SERVER["DOCUMENT_ROOT"]."/_common/config.php";
require $_SERVER['DOCUMENT_ROOT'].'/vendor/autoload.php';

//토픽에 푸시 발송

function accessToken(){
    global $FCM_PRIVATE_KEY;

    putenv("GOOGLE_APPLICATION_CREDENTIALS={$FCM_PRIVATE_KEY}");
    $scope = 'https://www.googleapis.com/auth/firebase.messaging';
    $client = new Google_Client();
    $client->useApplicationDefaultCredentials();

    $client->setScopes($scope);
    $accessToken = $client->fetchAccessTokenWithAssertion();

    if (isset($accessToken['access_token'])) {
        return $accessToken['access_token'];
    } else {
        return "Error fetching access token.";
    }
}

function sendPushNotificationToTopic($topic, $accessToken) {
    $data = [
        'message' => [
            'topic' => $topic,  // 구독된 토픽
            'notification' => [
                'title' => '슈로코',
                'body' => '전체발송 테스트 입니다.'
            ]
        ]
    ];

    // Firebase 프로젝트 ID
    $projectId = 'suloko';  // Firebase 프로젝트 ID를 여기 입력하세요

    $url = 'https://fcm.googleapis.com/v1/projects/' . $projectId . '/messages:send';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    $response = curl_exec($ch);
    curl_close($ch);

    echo "Response from FCM: " . $response;
}

// 예시: 'basic'라는 토픽에 푸시 알림 전송
sendPushNotificationToTopic('basic', accessToken());
?>
