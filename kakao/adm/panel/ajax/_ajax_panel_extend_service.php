<?php
include_once __DIR__ . '/../../../common.php';

header('Content-Type: text/plain; charset=UTF-8');

function panel_extend_plan($days)
{
    $days = (int) $days;
    $plans = array(
        30 => array('days' => 30, 'cost' => 30000, 'base' => 30000),
        180 => array('days' => 180, 'cost' => 174600, 'base' => 180000),
        360 => array('days' => 360, 'cost' => 342000, 'base' => 360000),
    );
    return isset($plans[$days]) ? $plans[$days] : null;
}

if (!isset($_SESSION['midx']) || (int) $_SESSION['midx'] <= 0) {
    die('로그인이 필요합니다.');
}

$member = 로그인정보($_SESSION['midx']);
if (!isset($member['mb_no'])) {
    die('회원 정보를 확인할 수 없습니다.');
}

$idx = isset($_POST['idx']) ? (int) $_POST['idx'] : 0;
$extend_days = isset($_POST['days']) ? (int) $_POST['days'] : 30;
$plan = panel_extend_plan($extend_days);
if ($idx <= 0) {
    die('패널을 선택할 수 없습니다.');
}
if (!$plan) {
    die('연장 기간을 선택할 수 없습니다.');
}

$panel_extend_days = (int) $plan['days'];
$panel_extend_cost = (int) $plan['cost'];

global $conn;

$row = db_select("select idx, member_no, status, panel_name_ko, panel_name_en, COALESCE(enddate,'') as ed, COALESCE(regdate,'') as rd from tb_panel where idx = {$idx} limit 1 ");
if (!$row || !isset($row['idx'])) {
    die('데이터를 찾을 수 없습니다.');
}
if ((int)$row['status'] === 2) {
    die('삭제 완료된 패널은 서비스를 연장할 수 없습니다.');
}

$owner_no = (int) $row['member_no'];
$is_admin = ($member['mb_id'] === 'admin');
if (!$is_admin && (int) $member['mb_no'] !== $owner_no) {
    die('연장할 권한이 없습니다.');
}

$owner = 로그인정보($owner_no);
if (!$owner || !isset($owner['mb_no'])) {
    die('회원 정보를 불러오지 못했습니다.');
}

$owner_cash = isset($owner['mb_cash']) ? (int) $owner['mb_cash'] : 0;
$admin_no = (int) $member['mb_no'];
$admin_cash = isset($member['mb_cash']) ? (int) $member['mb_cash'] : 0;

// 소유자 캐시 우선, 부족하면 관리자 캐시로 결제
$payer_no = $owner_no;
$payer = $owner;
$paid_by_admin = false;
if ($owner_cash >= $panel_extend_cost) {
    $payer_no = $owner_no;
    $payer = $owner;
} elseif ($is_admin && $admin_cash >= $panel_extend_cost) {
    $payer_no = $admin_no;
    $payer = $member;
    $paid_by_admin = true;
} else {
    $msg = '보유 캐시가 부족합니다. (필요: ' . number_format($panel_extend_cost) . ' 캐시';
    $msg .= ' / 소유자: ' . number_format($owner_cash);
    if ($is_admin) {
        $msg .= ' / 관리자: ' . number_format($admin_cash);
    }
    $msg .= ')';
    die($msg);
}

mysqli_begin_transaction($conn);

try {
    $upd_m = db_query(
        'update member set mb_cash = mb_cash - ' . $panel_extend_cost . ' where mb_no = ' . $payer_no . ' and COALESCE(mb_cash,0) >= ' . $panel_extend_cost
    );
    if (!$upd_m || mysqli_affected_rows($conn) !== 1) {
        throw new Exception('캐시 차감에 실패했습니다. 잔액을 확인해 주세요.');
    }

    $endsql = 'update tb_panel set ';
    $endsql .= 'enddate = DATE_ADD(GREATEST(COALESCE(enddate, NOW()), NOW()), INTERVAL ' . $panel_extend_days . ' DAY), ';
    $endsql .= 'moddate = now() ';
    $endsql .= 'where idx = ' . $idx . ' limit 1';

    $upd_p = db_query($endsql);
    if (!$upd_p || mysqli_affected_rows($conn) !== 1) {
        throw new Exception('종료일 연장에 실패했습니다.');
    }

    $site_sql = 'update site set ';
    $site_sql .= 'luckypanel_bankdata = DATE_ADD(GREATEST(COALESCE(luckypanel_bankdata, NOW()), NOW()), INTERVAL ' . $panel_extend_days . ' DAY) ';
    $site_sql .= 'where luckypanel = 1 and luckypanel_idx = ' . $idx;
    if (!db_query($site_sql)) {
        throw new Exception('무통장 이용 종료일 연장에 실패했습니다.');
    }

    $userid_esc = mysqli_real_escape_string($conn, (string) $payer['mb_id']);
    $label_ko = isset($row['panel_name_ko']) ? $row['panel_name_ko'] : '';
    $label_en = isset($row['panel_name_en']) ? $row['panel_name_en'] : '';
    $goods_label = '패널 서비스 ' . $panel_extend_days . '일 연장 (#' . $idx . ' ' . $label_ko . ' / ' . $label_en . ')';
    if ($paid_by_admin) {
        $goods_label .= ' [관리자 결제]';
    }
    if (function_exists('mb_strlen') && function_exists('mb_substr') && mb_strlen($goods_label, 'UTF-8') > 480) {
        $goods_label = mb_substr($goods_label, 0, 480, 'UTF-8');
    }
    $goods_esc = mysqli_real_escape_string($conn, $goods_label);
    $pv_esc = mysqli_real_escape_string($conn, 'cash/panel_extend/' . $idx . '/' . $panel_extend_days . ($paid_by_admin ? '/admin' : ''));
    $moid_esc = mysqli_real_escape_string($conn, 'pe_' . $idx . '_' . $payer_no . '_' . str_replace('.', '', uniqid('', true)));

    $ins_log = 'insert into tb_pay_log set ';
    $ins_log .= 'pk_pay = 0, ';
    $ins_log .= 'midx = ' . $payer_no . ', ';
    $ins_log .= "userid = '{$userid_esc}', ";
    $ins_log .= 'amt = ' . $panel_extend_cost . ', ';
    $ins_log .= "goodsname = '{$goods_esc}', ";
    $ins_log .= "pay_value = '{$pv_esc}', ";
    $ins_log .= "moid = '{$moid_esc}', ";
    $ins_log .= 'status = 1, ';
    $ins_log .= 'regdate = now() ';
    if (!db_query($ins_log)) {
        throw new Exception('결제내역 기록에 실패했습니다.');
    }

    $order_memo = '패널서비스연장#' . $idx . '_' . $panel_extend_days . '일';
    if ($paid_by_admin) {
        $order_memo .= '_관리자결제';
    }
    주문로그('캐시', $payer_no, $order_memo, $panel_extend_cost, 1);

    mysqli_commit($conn);
} catch (Exception $e) {
    mysqli_rollback($conn);
    die($e->getMessage());
}

$after = db_select("select enddate from tb_panel where idx = {$idx} limit 1 ");
$end_fmt = '';
if ($after && !empty($after['enddate'])) {
    $end_fmt = date('Y-m-d H:i', strtotime($after['enddate']));
}

echo '1|' . $end_fmt;
