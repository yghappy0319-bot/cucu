<?php
include_once $_SERVER['DOCUMENT_ROOT']."/admin/lib/function.php";
include_once $_SERVER['DOCUMENT_ROOT']."/admin/_chk.php";

if (!isset($_SESSION['aidx']) || $admin['grade'] == 2) {
    echo 0;
    exit;
}

set_time_limit(0);

$action = isset($action) ? $action : '';
$search = isset($search) ? $search : '';
$_search = "";

if ($search != "") {
    $search_esc = mysqli_real_escape_string($conn, $search);
    $_search = " AND (a.name LIKE '%{$search_esc}%' OR a.phone LIKE '%{$search_esc}%' OR a.id LIKE '%{$search_esc}%') ";
}

$no_charge_join = "
LEFT JOIN (
  SELECT midx, COUNT(*) AS bank_cnt
  FROM lr_deposit_log
  WHERE status = 2
    AND IFNULL(deposit_type, '') != 'reward'
  GROUP BY midx
) AS bank_log ON bank_log.midx = a.idx
LEFT JOIN (
  SELECT midx, COUNT(*) AS card_cnt
  FROM lr_card_log
  WHERE pg_status1 = '정상'
  GROUP BY midx
) AS card_log ON card_log.midx = a.idx
";

$sql = "UPDATE lr_member a
{$no_charge_join}
SET
  a.grade = 0
, a.grade_date = ''
, a.social = 0
, a.code = ''
, a.email = ''
, a.password = ''
, a.recommender = ''
, a.birthday = ''
, a.phone_auth = 0
, a.cash = 0
, a.point = 0
, a.accumulate = 0
, a.lucky_point = 0
, a.prize_money = 0
, a.ads = ''
, a.access = ''
, a.push = ''
, a.status = ''
, a.marketing = 0
, a.acount = ''
, a.acount_name = ''
, a.acount_number = ''
, a.cut_off = 1
, a.manage_cut_off = 1
, a.new_pass_date = ''
, a.new_pass = 0
WHERE a.cut_off = 0
  AND IFNULL(bank_log.bank_cnt, 0) = 0
  AND IFNULL(card_log.card_cnt, 0) = 0
";

if ($action == "one") {
    $midx = (int)$midx;
    if ($midx < 1) {
        echo 0;
        exit;
    }
    $sql .= " AND a.idx = {$midx} ";
} else if ($action == "all") {
    $sql .= $_search;
} else {
    echo 0;
    exit;
}

$result = db_query($sql);
if (!$result) {
    echo 0;
    exit;
}

echo (int) mysqli_affected_rows($conn);
