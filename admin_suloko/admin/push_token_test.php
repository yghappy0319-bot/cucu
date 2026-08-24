<?php
require_once "/home/lotto/public_html/admin/lib/function.php";
require_once '/home/lotto/public_html/vendor/autoload.php';

use Google\Auth\Credentials\ServiceAccountCredentials;

db_query("insert into CRON_LOG set FILE = '푸시5분체크', REG_DATE = now() ");

function accessToken(){
    $serviceAccountFile = '/home/lotto/public_html/_common/suloko-firebase-adminsdk-bzgej-e143c38f23.json';

    $credentials = new ServiceAccountCredentials(
        'https://www.googleapis.com/auth/cloud-platform',
        $serviceAccountFile
    );

    $accessToken = $credentials->fetchAuthToken();
    return isset($accessToken['access_token']) ? $accessToken['access_token'] : null;
}

function sendPushNotificationToToken($제목, $내용, $idx, $fcmToken, $accessToken) {
    $projectId = 'suloko';
    $url = 'https://fcm.googleapis.com/v1/projects/' . $projectId . '/messages:send';

    $data = [
        'message' => [
            'token' => $fcmToken,
            'notification' => [
                'title' => $제목,
                'body' => $내용
            ]
        ]
    ];

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

    $decoded = json_decode($response, true);

    if (isset($decoded['error']['status'])) {
        $errorCode = $decoded['error']['status'];
        echo "[ERROR][$errorCode] Token idx: {$idx} / Token: {$fcmToken}\n";

        // 예: UNREGISTERED 토큰이라면 DB에서 비활성화 처리
        if ($errorCode === 'NOT_FOUND' || $decoded['error']['details'][0]['errorCode'] === 'UNREGISTERED') {
            // 예시: 상태를 'N'으로 업데이트
            $updateSql = "UPDATE MEMBER_TOKEN SET ACTIVE = '0' WHERE IDX = '{$idx}'";
            db_query($updateSql);
        }
    } else {
        echo "✅ Sent to token idx: {$idx} / Token: {$fcmToken}\n";
    }
}

// 최신 토큰만 추출
//$test = " AND mt.USER_ID = 'dudrhks0319' ";
$sql = "
SELECT mt.IDX, mt.USER_ID, mt.TOKEN, mt.ACTIVE, mt.CREATED_AT
FROM MEMBER_TOKEN mt
JOIN (
    SELECT USER_ID, MAX(CREATED_AT) AS LATEST
    FROM MEMBER_TOKEN
    WHERE STATUS = 'Y' AND ACTIVE = 1
    GROUP BY USER_ID
) latest_mt
ON mt.USER_ID = latest_mt.USER_ID AND mt.CREATED_AT = latest_mt.LATEST
WHERE mt.STATUS = 'Y' AND mt.ACTIVE = 1 {$test}
ORDER BY mt.CREATED_AT DESC;
";

$result = db_query($sql);

// 토큰 배열 생성
$tokens = [];
foreach ($result as $data){
    if (!empty($data['TOKEN'])) {
        $tokens[] = [
            'IDX' => $data['IDX'],
            'TOKEN' => $data['TOKEN']
        ];
    }
}

// 액세스 토큰 발급
$accessToken = accessToken();
if (!$accessToken) {
    exit("Access token 발급 실패");
}

$예약푸시 = db_select("SELECT * FROM NOTI_RESERVATION WHERE (TOKEN is null || TOKEN = '') ORDER BY RESERVED_AT ASC LIMIT 1");
echo "예약 푸시 제목: ".$예약푸시['TITLE']."\n";
$발송예약일 = date("Y-m-d H:i", strtotime($예약푸시['RESERVED_AT']));
echo "예약 발송일: " . $발송예약일 . "\n";
$현재시간 = date("Y-m-d H:i");

// 비교
if ($현재시간 >= $발송예약일) {

    $체크 = db_select("select count(*) as cnt from CRON_LOG where FILE = '푸시발송_시작'");
    if($체크['cnt']){ // 실행중
        echo "대기중...";
        exit;
    }else{
        echo "발송시작";
        db_query("insert into CRON_LOG set FILE = '푸시발송_시작', REG_DATE = now() ");
        // 푸시 발송 코드 실
        $delayMicroseconds = 100000; // 100ms 푸시 발송 (단건 + 딜레이)
        foreach ($tokens as $t) {
            $제목 = $예약푸시['TITLE'];
            $내용 = $예약푸시['CONTENT'];

            sendPushNotificationToToken($제목, $내용, $t['IDX'], $t['TOKEN'], $accessToken);
            usleep($delayMicroseconds);
        }
        db_query("update NOTI_RESERVATION set TOKEN = 1 where IDX = {$예약푸시['IDX']}  ");
        db_query("delete from CRON_LOG where FILE = '푸시발송_시작' ");
        db_query("delete from MEMBER_TOKEN where ACTIVE = '0'");
    }
} else {
    echo "🕒 아직 발송 시간이 아닙니다.\n";
    db_query("insert into CRON_LOG set FILE = '푸시 발송 시간이 아닙니다.', REG_DATE = now() ");
}


?>
