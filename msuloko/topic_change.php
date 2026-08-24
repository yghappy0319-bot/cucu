<?php
require_once "/home/sulokomo/public_html/_common/new_function.php";
require_once "/home/sulokomo/public_html/vendor/autoload.php"; // Google API Client
//error_reporting(E_ALL);
//ini_set("display_errors", 1);

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\RegistrationToken;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Exception\FirebaseException;

$sql = "
SELECT *
FROM MEMBER_TOKEN2 mt
WHERE mt.STATUS = 'Y' 
  AND mt.CREATED_AT = (
      SELECT MAX(m2.CREATED_AT)
      FROM MEMBER_TOKEN2 m2
      WHERE m2.USER_ID = mt.USER_ID
        AND m2.STATUS = 'Y'
  );

";
echo $sql."\n";
$result = db_query($sql);

foreach ($result as $data){
    echo $data['USER_ID']."\n";
    fcm_topic($data['USER_ID'], $data['TOKEN'], 'yghppay');
}

fcm_topic($data['USER_ID'], $data['TOKEN'], 'yghppay');

function fcm_topic($userid, $token, $topic){

    if (!$token || !$topic) {
        http_response_code(400);
        echo "FCM 토큰과 토픽 이름이 필요합니다.";
        exit;
    }

    $sqls = " SELECT COUNT(*) as cnt FROM MEMBER_TOPIC2 WHERE USER_ID = '{$userid}' ";
    $info = db_select($sqls);
    if($info['cnt']==0){
        //$projectId = 'my-test-project-7f600';  // Firebase 프로젝트 ID를 여기 입력하세요
        //Firebase 서비스 계정 키 경로
        //$serviceAccountPath = '/home/sulokomo/public_html/'.$projectId.'-9eb3599e46d6.json';
        $serviceAccountPath = '/home/sulokomo/public_html/suloko-firebase-adminsdk-bzgej-e143c38f23.json';

        $firebase = (new Factory)->withServiceAccount($serviceAccountPath);
        $messaging = $firebase->createMessaging();

            try {
                $messaging->subscribeToTopic($topic, [$token]);
                $sql = "insert into MEMBER_TOPIC2 set ";
                $sql.= "USER_ID = '{$userid}', ";
                $sql.= "TOPIC_IDX = 1, ";
                $sql.= "STATUS = 'Y', ";
                $sql.= "CREATED_AT = now(), ";
                $sql.= "UPDATED_AT = now() ";
                //db_query($sql);
                echo "성공적으로 토픽 '{$topic}'에 구독되었습니다.\n";

            } catch (MessagingException | FirebaseException $e) {
                http_response_code(500);
                echo "구독 실패: " . $e->getMessage();
            }
    }
}