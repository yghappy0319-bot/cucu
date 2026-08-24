<?php
require $_SERVER['DOCUMENT_ROOT'].'/vendor/autoload.php';

use Google\Auth\Credentials\ServiceAccountCredentials;

// 서비스 계정에서 액세스 토큰을 받는 함수

function accessToken(){
    $serviceAccountFile = '/home/lotto/public_html/_common/suloko-firebase-adminsdk-bzgej-e143c38f23.json';
    $credentials = new ServiceAccountCredentials(
        'https://www.googleapis.com/auth/cloud-platform', // 기본 권한 설정
        $serviceAccountFile
    );

    $accessToken = $credentials->fetchAuthToken();
    return isset($accessToken['access_token']) ? $accessToken['access_token'] : "Error fetching access token.";
}

// FCM 토큰으로 푸시 알림을 보내는 함수
function sendPushNotificationToToken($fcmToken, $accessToken) {
    $data = [
        'message' => [
            'token' => $fcmToken,  // FCM 토큰을 사용하여 직접 발송
            'notification' => [
                'title' => '슈로코 푸시 테스트',
                'body' => '푸시테스트12345'
            ]
        ]
    ];

    // Firebase 프로젝트 ID
    $projectId = 'suloko';  // Firebase 프로젝트 ID를 여기에 입력하세요
    $url = 'https://fcm.googleapis.com/v1/projects/' . $projectId . '/messages:send';

    // cURL 요청
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

// 예시: FCM 토큰으로 푸시 알림 전송
$fcmToken = 'f2Py-5u4XQucIyShJ4B0J0:APA91bF_9rlAXqcwPMtlHLA1plFlxB7oW5yz9mmC_kVQVU2FUdBH_NkNBo1jjkbtRbWKAtIdbohCoa3IRbBMPbJoBFyofZt6K_-y_TLgUvui62QUQ-JnkKY';
$accessToken = accessToken();  // 액세스 토큰 발급받기
sendPushNotificationToToken($fcmToken, $accessToken);
?>
