<?php
include_once('../common.php');

header('Content-Type: application/json; charset=utf-8');

$panel_idx = isset($panel_idx) ? (int) $panel_idx : 0;
$mb_no = isset($mb_no) ? (int) $mb_no : 0;
$service_day = isset($service_day) ? trim((string) $service_day) : '';

if ($panel_idx <= 0 || $mb_no <= 0) {
    echo json_encode(array('status' => '0', 'msg' => '잘못된 요청입니다.'), JSON_UNESCAPED_UNICODE);
    exit;
}

if (!in_array($service_day, array('+1', '-1', '+7', '+30', '-30'), true)) {
    echo json_encode(array('status' => '0', 'msg' => '기간 값이 올바르지 않습니다.'), JSON_UNESCAPED_UNICODE);
    exit;
}

$row = db_select("select idx, member_no, status, panel_name_ko, panel_name_en, enddate from tb_panel where idx = {$panel_idx} limit 1 ");
if (!$row || !isset($row['idx']) || (int) $row['member_no'] !== $mb_no) {
    echo json_encode(array('status' => '0', 'msg' => '패널을 찾을 수 없습니다.'), JSON_UNESCAPED_UNICODE);
    exit;
}

if ((int) $row['status'] === 2) {
    echo json_encode(array('status' => '0', 'msg' => '삭제 완료된 패널은 변경할 수 없습니다.'), JSON_UNESCAPED_UNICODE);
    exit;
}

$mdata = db_select("select mb_id from member where mb_no = {$mb_no} limit 1 ");
if (!$mdata || !isset($mdata['mb_id'])) {
    echo json_encode(array('status' => '0', 'msg' => '회원 정보를 찾을 수 없습니다.'), JSON_UNESCAPED_UNICODE);
    exit;
}

$end_raw = isset($row['enddate']) ? trim((string) $row['enddate']) : '';
if ($end_raw === '') {
    $base_ts = time();
} else {
    $base_ts = strtotime($end_raw);
    if ($base_ts === false) {
        $base_ts = time();
    }
}

$day_delta = (int) $service_day;
$new_end_ts = strtotime($day_delta . ' days', $base_ts);
$new_end = date('Y-m-d H:i:s', $new_end_ts);

global $conn;
$new_end_esc = mysqli_real_escape_string($conn, $new_end);

mysqli_begin_transaction($conn);

try {
    $sql = "update tb_panel set enddate = '{$new_end_esc}', moddate = now() where idx = {$panel_idx} limit 1 ";
    $result = db_query($sql);
    if (!$result || mysqli_affected_rows($conn) !== 1) {
        throw new Exception('종료일 변경에 실패했습니다.');
    }

    $label_ko = isset($row['panel_name_ko']) ? $row['panel_name_ko'] : '';
    $label_en = isset($row['panel_name_en']) ? $row['panel_name_en'] : '';
    $log_result = 패널수동연장_pay_log(
        $mb_no,
        $mdata['mb_id'],
        $panel_idx,
        $label_ko,
        $label_en,
        $day_delta,
        $base_ts,
        $new_end_ts
    );
    if (!$log_result) {
        throw new Exception('결제내역 기록에 실패했습니다.');
    }

    mysqli_commit($conn);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(array('status' => '0', 'msg' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
    exit;
}

$msg = '패널 종료일이 ' . date('Y-m-d H:i', $new_end_ts) . ' 로 변경되었습니다.';
echo json_encode(array('status' => '1', 'msg' => $msg), JSON_UNESCAPED_UNICODE);
