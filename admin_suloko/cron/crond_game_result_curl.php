<?php
include "/home/lotto/public_html/admin/lib/function.php";

$sql = "select * from WININFO where 
STATUS = 0 and BALL1 != '' and BALL2 != '' and BALL3 != '' and BALL4 != '' and BALL5 != ''
ORDER by PLAYDATE ASC LIMIT 1 ";
$data = db_select($sql);

db_query("insert into CRON_LOG set FILE = '{$data['GUBUN']}_추첨', REG_DATE = now() ");
$url = "https://sadmin.mekosystem.com/cron/crond_game_result.php?game=".$data['GUBUN']."&round=".$data['DRAWNUM'];

// cURL 세션 초기화
$ch = curl_init();

// cURL 옵션 설정
curl_setopt($ch, CURLOPT_URL, $url);              // 요청할 URL
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);    // 응답을 문자열로 반환
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);    // 리디렉션을 따르도록 설정
curl_setopt($ch, CURLOPT_TIMEOUT, 30);             // 타임아웃 시간 설정 (초 단위)

// cURL 요청 실행
$response = curl_exec($ch);

// cURL 요청이 실패했을 경우 에러 메시지 출력
if(curl_errno($ch)) {
    echo 'cURL Error: ' . curl_error($ch);
} else {
    // 성공적으로 응답을 받은 경우
    echo "응답 결과: " . $response;
}

// cURL 세션 종료
curl_close($ch);
