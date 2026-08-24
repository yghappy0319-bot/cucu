<?php

/** tb_config 5줄 code를 1~50 중 중복 없이 재배치 */
function 지또_당첨번호_재배치($max = 50) {
    $numbers = range(1, $max);
    shuffle($numbers);

    $result = db_query("SELECT idx FROM tb_config ORDER BY idx ASC");
    $rows = [];
    while ($row = db_fetch($result)) {
        $rows[] = (int)$row['idx'];
    }

    $행수 = count($rows);
    if ($행수 <= 0) {
        return;
    }

    $picked = array_slice($numbers, 0, $행수);
    for ($i = 0; $i < $행수; $i++) {
        db_query("UPDATE tb_config SET code = {$picked[$i]} WHERE idx = {$rows[$i]}");
    }
}

/** tb_attendance_code에 이미 등록된 번호 목록 */
function 지또_사용된번호_목록() {
    $exists = [];
    $result = db_query("SELECT code FROM tb_attendance_code");
    while ($row = db_fetch($result)) {
        $exists[] = (int)$row['code'];
    }
    return array_values(array_unique($exists));
}

/** 이미 누른 번호를 제외한 남은 번호 안내 문구 */
function 지또_남은번호_문구($max = 50) {
    $exists = 지또_사용된번호_목록();
    $missing = array_values(array_diff(range(1, $max), $exists));
    sort($missing, SORT_NUMERIC);

    if (empty($missing)) {
        return '(남은 번호 없음)';
    }

    $chunks = array_chunk($missing, 10);
    $lines = array_map(function ($chunk) {
        return implode(' ', $chunk);
    }, $chunks);

    return implode("\n", $lines);
}

if (preg_match_all('/\d+/', $msg, $matches)) {
    $number = isset($matches[0][0]) ? (int)$matches[0][0] : null;
} else {
    $number = null;
}

$max = 50;
$차감포인트 = 1.0;
$차감_sql = number_format($차감포인트, 1, '.', '');

if ((float)($정보['newpoint'] ?? 0) < $차감포인트) {
    $출석포인트 = db_select("select newpoint from tb_member where name = '{$두자리닉넴}' ");
    $msg = "🤣 탕진 🤣 보유" . newpoint표시($출석포인트['newpoint'] ?? 0) . "냥";
    echo 전송($msg);
    exit;
}

if (!$number || $number < 1 || $number > $max) {
    echo 전송("1 ~ {$max} 사이의 숫자를 입력해주세요!\n예) ㅈㄷ3");
    exit;
}

$이미등록된번호 = db_select("select idx from tb_attendance_code where code = {$number} limit 1");
if (!empty($이미등록된번호['idx'])) {
    echo 전송("다른 번호를 입력해줘!\n\n" . 지또_남은번호_문구($max));
    exit;
}

$닉_esc = addslashes($두자리닉넴);
db_query("insert into tb_attendance_code set nick = '{$닉_esc}', code = {$number} ");

db_query("update tb_member set newpoint = newpoint - {$차감_sql} where name = '{$두자리닉넴}' ");

$행운 = db_select("select * from tb_config where code = {$number} order by idx asc limit 1 ");
$도전메세지 = "{$number}번 ";
$당첨포인트 = 0.0;

$포인트당첨 = !empty($행운['idx'])
    && round((float)($행운['point'] ?? 0), 1) > 0
    && (string)($행운['gubun'] ?? '') !== '함정';

if ($포인트당첨) {
    $당첨포인트 = round((float)$행운['point'], 1);
    $당첨_sql = number_format($당첨포인트, 1, '.', '');
    db_query("update tb_member set newpoint = newpoint + {$당첨_sql} where name = '{$두자리닉넴}' ");
    $도전메세지 .= newpoint표시($당첨포인트) . "냥 당첨 🎉";
    지또_당첨번호_재배치($max);
    db_query("delete from tb_attendance_code");
} else {
    $도전메세지 .= "☠️꽝☠️";
}

db_query("insert into tb_winner set round = 0, nick = '{$두자리닉넴}', point = {$차감포인트}, item = '{$당첨포인트}', number = '{$number}', regdate = now() ");

echo 전송($도전메세지);
exit;
