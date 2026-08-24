<?php
include_once __DIR__ . '/../../common.php';

header('Content-Type: text/plain; charset=UTF-8');
set_time_limit(0);

if (!isset($_SESSION['midx']) || (int)$_SESSION['midx'] <= 0) {
    die('로그인이 필요합니다.');
}

$member = 로그인정보($_SESSION['midx']);
if (!isset($member['mb_no'])) {
    die('회원 정보를 확인할 수 없습니다.');
}
if ($member['mb_id'] !== 'admin') {
    die('발송 권한이 없습니다.');
}

global $conn;

$title = isset($_POST['title']) ? trim($_POST['title']) : '';
$content = isset($_POST['content']) ? trim($_POST['content']) : '';

if ($title === '') {
    die('제목을 입력해 주세요.');
}
if (mb_strlen($title, 'UTF-8') > 40) {
    die('제목은 40자 이내로 입력해 주세요.');
}
if ($content === '') {
    die('내용을 입력해 주세요.');
}

$target_where = "mb_10 != '' AND IFNULL(mb_hp,'') != ''";
$result = db_query("select mb_no, mb_id, mb_hp from member where {$target_where} order by mb_no asc");

$receivers = array();
$members = array();
$skip = 0;

while ($row = db_fetch($result)) {
    $phone = preg_replace('/[^0-9]/', '', (string)$row['mb_hp']);
    if ($phone === '') {
        $skip++;
        continue;
    }
    $receivers[] = array('mobile' => $phone);
    $members[] = array(
        'mb_no' => (int)$row['mb_no'],
        'phone' => $phone,
    );
}

$total = count($members);
if ($total < 1) {
    die('발송 대상 회원이 없습니다.');
}

$payload = array(
    'title' => $title,
    'message' => str_replace("\xc2\xa0", ' ', $content),
    'sender' => '01022934444',
    'username' => 'dudrhks0319',
    'key' => 'zjuT7SQ1FZTgMjn',
    'receiver' => $receivers,
);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://directsend.co.kr/index.php/api_v2/sms_change_word');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
curl_setopt($ch, CURLOPT_TIMEOUT, 180);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'cache-control: no-cache',
    'content-type: application/json; charset=utf-8',
));

$response = curl_exec($ch);
$curl_err = curl_errno($ch) ? curl_error($ch) : '';
curl_close($ch);

if ($curl_err !== '') {
    die('문자 서버 연결 실패: ' . $curl_err);
}

$decoded = json_decode($response, true);
$status = isset($decoded['status']) ? (string)$decoded['status'] : '';

// DirectSend: status 0 = 정상 수신(발송 요청 성공)
if ($status !== '0') {
    $msg = isset($decoded['message']) ? $decoded['message'] : $response;
    if ($msg === '' || $msg === null) {
        $msg = 'status=' . ($status !== '' ? $status : 'unknown');
    }
    die('발송 실패(API): ' . (is_string($msg) ? $msg : json_encode($msg, JSON_UNESCAPED_UNICODE)));
}

$년월일오늘 = date('Y-m-d');
$title_esc = mysqli_real_escape_string($conn, $title);
foreach ($members as $m) {
    $phone_esc = mysqli_real_escape_string($conn, $m['phone']);
    $mb_no = (int)$m['mb_no'];
    db_query("insert into tb_sms_send set mb_no = {$mb_no}, status = '전체문자', code1 = '{$phone_esc}', code2 = '{$title_esc}', senddate = '{$년월일오늘}', regdate = now() ");
}

echo 'OK|' . $total . '|' . $total . '|' . $skip;
