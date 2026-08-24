<?php
include_once "../common.php";

header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(array(
        "status" => false,
        "msg" => "POST 요청만 허용됩니다."
    ));
    exit;
}

global $conn;

$datas = $_POST;

$입금자명 = isset($datas['name']) ? trim($datas['name']) : '';
$입금금액 = isset($datas['amount']) ? (int)$datas['amount'] : 0;

// 수신 로그 저장 (입금자명 / 금액)
$log_text = addslashes($입금자명) . "/" . $입금금액;
$sql  = "insert into bank_cron set ";
$sql .= "text = '{$log_text}', ";
$sql .= "regdate = now() ";
$result = db_query($sql);

if (!$result) {
    http_response_code(500);
    echo json_encode(array(
        "status" => false,
        "msg" => "DB INSERT 실패",
        "error" => mysqli_error($conn)
    ));
    exit;
}

$insert_id = mysqli_insert_id($conn);

if ($입금자명 === '') {
    echo json_encode(array(
        "status" => false,
        "msg" => "입금자명이 비어있어 자동매칭 불가",
        "insert_id" => $insert_id
    ));
    exit;
}

if ($입금금액 <= 0) {
    echo json_encode(array(
        "status" => true,
        "msg" => "bank_cron 저장 완료 (입금 금액 없음)",
        "insert_id" => $insert_id
    ));
    exit;
}

$name_escaped = mysqli_real_escape_string($conn, $입금자명);
$amt_int = (int)$입금금액;

$base_where = "
  status = 0
  and cast(amt as unsigned) = {$amt_int}
  and deposit_name = '{$name_escaped}'
  and cash > 0
  and pay_value like '캐시/%'
";

$cnt_row = db_select("select count(*) as cnt from tb_pay_log where {$base_where} ");
$match_cnt = isset($cnt_row['cnt']) ? (int)$cnt_row['cnt'] : 0;

if ($match_cnt === 0) {
    echo json_encode(array(
        "status" => true,
        "msg" => "bank_cron 저장 완료 (일치하는 패널 캐시 무통장 신청 없음)",
        "insert_id" => $insert_id
    ));
    exit;
}

if ($match_cnt > 1) {
    echo json_encode(array(
        "status" => false,
        "msg" => "동일 조건 무통장 신청이 {$match_cnt}건 존재하여 자동처리 불가 (입금자·금액 확인)",
        "insert_id" => $insert_id
    ));
    exit;
}

$pay_log = db_select("select * from tb_pay_log where {$base_where} order by regdate asc limit 1 ");

if (!isset($pay_log['idx']) || (int)$pay_log['idx'] <= 0) {
    echo json_encode(array(
        "status" => false,
        "msg" => "tb_pay_log 조회 실패",
        "insert_id" => $insert_id
    ));
    exit;
}

$midx = (int)$pay_log['midx'];
$cash_credit = (int)$pay_log['cash'];
$log_idx = (int)$pay_log['idx'];

if ($midx <= 0 || $cash_credit < 10000 || $cash_credit % 10000 !== 0 || $cash_credit > 90000000) {
    echo json_encode(array(
        "status" => false,
        "msg" => "캐시 충전 금액 검증 실패 (신청 데이터 오류)",
        "insert_id" => $insert_id
    ));
    exit;
}

$회원 = db_select("select * from member where mb_no = {$midx} limit 1 ");
if (!isset($회원['mb_no']) || (int)$회원['mb_no'] <= 0) {
    echo json_encode(array(
        "status" => false,
        "msg" => "회원번호 {$midx} 를 찾을 수 없음",
        "insert_id" => $insert_id
    ));
    exit;
}

mysqli_begin_transaction($conn);

$upd_mem = db_query("update member set mb_cash = COALESCE(mb_cash, 0) + {$cash_credit} where mb_no = {$midx} ");

if (!$upd_mem) {
    mysqli_rollback($conn);
    echo json_encode(array(
        "status" => false,
        "msg" => "member mb_cash 업데이트 실패",
        "error" => mysqli_error($conn),
        "insert_id" => $insert_id
    ));
    exit;
}

$upd_log = db_query("update tb_pay_log set status = 1 where idx = {$log_idx} and status = 0 limit 1 ");

if (!$upd_log || mysqli_affected_rows($conn) !== 1) {
    mysqli_rollback($conn);
    echo json_encode(array(
        "status" => false,
        "msg" => "tb_pay_log 상태 갱신 실패 (중복 처리 가능성)",
        "error" => mysqli_error($conn),
        "insert_id" => $insert_id
    ));
    exit;
}

mysqli_commit($conn);

주문로그('무통장', $midx, "패널캐시충전 {$cash_credit}", $입금금액, 3001);

$sms내용 = $회원['mb_name']."(".$회원['mb_id'].")\n";
$sms내용 .= "패널캐시 ".number_format($cash_credit)."원 충전완료\n";
$sms내용 .= "(무통장 ".number_format($입금금액)."원)";
다이랙트샌드('01022934444', '럭키뱅크 패널캐시 충전', $sms내용);

echo json_encode(array(
    "status" => true,
    "msg" => "자동처리 완료: {$입금자명} / 캐시 +" . number_format($cash_credit) . "원 / mb_no {$midx}",
    "mb_no" => $midx,
    "cash" => $cash_credit,
    "pay_log_idx" => $log_idx,
    "insert_id" => $insert_id
));
