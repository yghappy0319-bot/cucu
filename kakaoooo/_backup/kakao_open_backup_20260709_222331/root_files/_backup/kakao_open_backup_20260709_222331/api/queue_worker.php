<?php
exit;
date_default_timezone_set('Asia/Seoul');
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

$LOGFILE = '/home/kakao/worker_cron.log';

// 로깅 헬퍼
function wlog($msg){
    global $LOGFILE;
    $line = '['.date('Y-m-d H:i:s').'] '.$msg.PHP_EOL;
    file_put_contents($LOGFILE, $line, FILE_APPEND | LOCK_EX);
}

@include_once "/home/kakao/public_html/lib/_function.php";
if (!function_exists('db_query')) {
    wlog("db_query 함수 없음. _function.php 확인 필요");
    exit(1);
}

$redis = new Redis();
try {
    $redis->connect('127.0.0.1', 6379, 1.0);
    if ($redis->ping()) wlog("Redis 연결 성공");
} catch (Exception $e) {
    wlog("Redis 연결 실패: ".$e->getMessage());
    exit(1);
}

$table = 'tb_msg';
$batch = [];
$BATCH_SIZE = 300;

// 최대 300건씩 처리
for ($i=0; $i<$BATCH_SIZE; $i++){
    $item = $redis->rPop('msg_queue');
    if (!$item) break;

    $data = json_decode($item,true);
    if (!$data) {
        wlog("잘못된 JSON: ".$item);
        continue;
    }

    $nickname = addslashes($data['nickname'] ?? '');
    $msg      = addslashes($data['msg'] ?? '');
    $tasu     = (int)($data['tasu']);
    $regdate  = $data['regdate'] ?? date('Y-m-d H:i:s');

    $batch[] = "('$nickname','$msg','$tasu','$regdate')";
}

if (count($batch) > 0){
    $sql = "INSERT INTO $table (nickname,msg,tasu,regdate) VALUES ".implode(',', $batch);
    try {
        db_query($sql);
        wlog("INSERT OK, count=".count($batch));
    } catch (Exception $e) {
        wlog("INSERT 실패: ".$e->getMessage()." SQL: ".substr($sql,0,500));
    }
} else {
    wlog("큐에 처리할 데이터 없음");
}
