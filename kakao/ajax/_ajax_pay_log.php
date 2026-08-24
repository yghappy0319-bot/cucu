<?php
include_once('../common.php');
//자동결제 주문 // 미리 등록해놓기
// 상품결제는 payid(상품 idx), 캐시충전 등 비상품은 0
global $conn;

$payid = isset($payid) ? (int) $payid : 0;
$midx = isset($midx) ? (int) $midx : 0;
$Amt = isset($Amt) ? (float) $Amt : 0;

$pay_method_raw = isset($pay_method) ? trim((string) $pay_method) : '';
$deposit_name_raw = isset($deposit_name) ? trim((string) $deposit_name) : '';
$deposit_name_esc = mysqli_real_escape_string($conn, $deposit_name_raw);

$userid_esc = mysqli_real_escape_string($conn, isset($userid) ? (string) $userid : '');
$goodsname_esc = mysqli_real_escape_string($conn, isset($GoodsName) ? (string) $GoodsName : '');
$pay_value_esc = mysqli_real_escape_string($conn, isset($idata) ? (string) $idata : '');
$moid_esc = mysqli_real_escape_string($conn, isset($_moid) ? (string) $_moid : '');

$receipt_type = 0;
$cr_phone = '';
$cr_company = '';
$cr_ceo = '';
$cr_bizno = '';
$cr_contact = '';
$cr_email = '';

if ($pay_method_raw === 'bank') {
  $receipt_kind = isset($receipt1) ? trim((string) $receipt1) : '';
  if ($receipt_kind === '1') {
    $receipt_type = 1;
    $cr_phone = isset($cash_receipt) ? trim((string) $cash_receipt) : '';
    if ($cr_phone === '') {
      die('현금영수증 발행 정보(연락처 또는 사업자번호)를 입력해 주세요.');
    }
  } elseif ($receipt_kind === '2') {
    $receipt_type = 2;
    $cr_company = isset($cash_receipt1) ? trim((string) $cash_receipt1) : '';
    $cr_ceo = isset($cash_receipt2) ? trim((string) $cash_receipt2) : '';
    $cr_bizno = isset($cash_receipt3) ? trim((string) $cash_receipt3) : '';
    $cr_contact = isset($cash_receipt4) ? trim((string) $cash_receipt4) : '';
    $cr_email = isset($cash_receipt5) ? trim((string) $cash_receipt5) : '';
    if ($cr_company === '' || $cr_ceo === '' || $cr_bizno === '' || $cr_contact === '' || $cr_email === '') {
      die('세금계산서 발행 정보를 모두 입력해 주세요.');
    }
  }
}

// 무통장: 발행안함=부가세 없음 / 현금영수증·세금계산서=부가세 10%
// 카드: 항상 부가세 10%
$cash_val = 0;
if (isset($idata)) {
  $idata_s = (string) $idata;
  if (strpos($idata_s, '캐시/') === 0) {
    $idata_parts = explode('/', $idata_s);
    if (isset($idata_parts[1]) && ctype_digit((string) $idata_parts[1])) {
      $cash_val = (int) $idata_parts[1];
    } elseif ($Amt > 0) {
      $apply_vat = ($pay_method_raw !== 'bank') || ($receipt_type > 0);
      $cash_val = $apply_vat ? (int) round($Amt / 1.10) : (int) round($Amt);
    }
    if ($cash_val < 10000 || $cash_val % 10000 !== 0 || $cash_val > 90000000) {
      $cash_val = 0;
    } elseif ($cash_val > 0 && $Amt > 0) {
      $apply_vat = ($pay_method_raw !== 'bank') || ($receipt_type > 0);
      $expected_amt = $apply_vat ? (float) round($cash_val * 1.10, 2) : (float) $cash_val;
      if (abs($Amt - $expected_amt) > 0.01) {
        die('결제 금액(부가세)이 올바르지 않습니다.');
      }
    }
  }
}

$cr_phone_esc = mysqli_real_escape_string($conn, $cr_phone);
$cr_company_esc = mysqli_real_escape_string($conn, $cr_company);
$cr_ceo_esc = mysqli_real_escape_string($conn, $cr_ceo);
$cr_bizno_esc = mysqli_real_escape_string($conn, $cr_bizno);
$cr_contact_esc = mysqli_real_escape_string($conn, $cr_contact);
$cr_email_esc = mysqli_real_escape_string($conn, $cr_email);

$sql = "insert into tb_pay_log set ";
$sql .= "pk_pay = {$payid} ";
$sql .= ",midx = {$midx} ";
$sql .= ",userid = '{$userid_esc}' ";
$sql .= ",amt = {$Amt} ";
$sql .= ",goodsname = '{$goodsname_esc}' ";
$sql .= ",pay_value = '{$pay_value_esc}' ";
$sql .= ",deposit_name = '{$deposit_name_esc}' ";
$sql .= ",cash = {$cash_val} ";
$sql .= ",receipt_type = {$receipt_type} ";
$sql .= ",cr_phone = '{$cr_phone_esc}' ";
$sql .= ",cr_company = '{$cr_company_esc}' ";
$sql .= ",cr_ceo = '{$cr_ceo_esc}' ";
$sql .= ",cr_bizno = '{$cr_bizno_esc}' ";
$sql .= ",cr_contact = '{$cr_contact_esc}' ";
$sql .= ",cr_email = '{$cr_email_esc}' ";
$sql .= ",moid = '{$moid_esc}' ";
$sql .= ",status = 0 ";
if ($receipt_type > 0) {
  $sql .= ",receipt_status = 0 ";
}
$sql .= ",regdate = now() ";

$result = db_query($sql);
if ($result) {
  echo '1';
} else {
  echo '저장에 실패했습니다. ' . mysqli_error($conn);
}
