<?php
require_once $_SERVER["DOCUMENT_ROOT"]."/_common/config.php";
require_once '/home/sulokomo/public_html/vendor/autoload.php'; // Google API Client
//error_reporting(E_ALL);
//ini_set("display_errors", 1);

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\RegistrationToken;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Exception\FirebaseException;

// POST로 전달된 값 받기
$token = $_POST['token'] ?? '';
$topic = $_POST['topic'] ?? '';

if (!$token || !$topic) {
    http_response_code(400);
    echo "FCM 토큰과 토픽 이름이 필요합니다.";
    exit;
}

$userid = $M_login["user_id"];

// Firebase 서비스 계정 키 경로
    $serviceAccountPath = '/home/sulokomo/public_html/suloko-firebase-adminsdk-bzgej-e143c38f23.json';

    $firebase = (new Factory)->withServiceAccount($serviceAccountPath);
    $messaging = $firebase->createMessaging();
  
    try {
        $messaging->subscribeToTopic($topic, [$token]);
        $sqls = " SELECT COUNT(*) as cnt FROM MEMBER_TOPIC WHERE USER_ID = '{$userid}' ";
        $info = $db->get_data_one($sqls);
        if($info['cnt']==0) {
            $sql = "insert into MEMBER_TOPIC set ";
            $sql.= "USER_ID = '{$userid}', ";
            $sql.= "TOPIC_IDX = 1, ";
            $sql.= "STATUS = 'Y', ";
            $sql.= "CREATED_AT = now(), ";
            $sql.= "UPDATED_AT = now() ";
            //echo $sql;
            $result = $db->query($sql);
        }

        echo "success {$topic}";
    } catch (MessagingException|FirebaseException $e) {
        http_response_code(500);
        echo "fail : " . $e->getMessage();
    }

