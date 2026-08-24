<?php
include_once "../../_chk.php";

$receipt_kind = isset($receipt1) ? trim((string)$receipt1) : ''; // ''=신청안함, 1=현금영수증, 2=세금계산서

// service2.html 폼에서 넘어오는 값들
$cash_receipt  = addslashes(trim((string)(isset($cash_receipt) ? $cash_receipt : '')));
$cash_receipt1 = addslashes(trim((string)(isset($cash_receipt1) ? $cash_receipt1 : '')));
$cash_receipt2 = addslashes(trim((string)(isset($cash_receipt2) ? $cash_receipt2 : '')));
$cash_receipt3 = addslashes(trim((string)(isset($cash_receipt3) ? $cash_receipt3 : '')));
$cash_receipt4 = addslashes(trim((string)(isset($cash_receipt4) ? $cash_receipt4 : '')));
$cash_receipt5 = addslashes(trim((string)(isset($cash_receipt5) ? $cash_receipt5 : '')));

// receipt_kind에 따라 member 컬럼에 매핑
$biz_no_in        = ($receipt_kind === '2') ? $cash_receipt3 : ''; // 세금계산서: 사업자번호
$company_name_in = ($receipt_kind === '2') ? $cash_receipt1 : ''; // 세금계산서: 회사명
$ceo_name_in      = ($receipt_kind === '2') ? $cash_receipt2 : ''; // 세금계산서: 대표자명
$tel_in           = ($receipt_kind === '2') ? $cash_receipt4 : (($receipt_kind === '1') ? $cash_receipt : ''); // 현금영수증: 휴대폰/사업자번호(입력값) -> tel에 저장
$email_in         = ($receipt_kind === '2') ? $cash_receipt5 : ''; // 세금계산서: 전자세금계산서 이메일

// 요청 컬럼(일부는 현재 폼에서 입력받지 않음) - 빈 값이면 기존값 유지
$biz_type_in  = '';
$biz_item_in  = '';
$biz_zip_in   = '';
$biz_addr1_in = '';
$biz_addr2_in = '';

$mb_no = (int)$member['mb_no'];

$sql = "
UPDATE `member`
SET
  `biz_no` = CASE WHEN '{$biz_no_in}'='' THEN `biz_no` ELSE '{$biz_no_in}' END,
  `company_name` = CASE WHEN '{$company_name_in}'='' THEN `company_name` ELSE '{$company_name_in}' END,
  `ceo_name` = CASE WHEN '{$ceo_name_in}'='' THEN `ceo_name` ELSE '{$ceo_name_in}' END,
  `biz_type` = CASE WHEN '{$biz_type_in}'='' THEN `biz_type` ELSE '{$biz_type_in}' END,
  `biz_item` = CASE WHEN '{$biz_item_in}'='' THEN `biz_item` ELSE '{$biz_item_in}' END,
  `biz_zip` = CASE WHEN '{$biz_zip_in}'='' THEN `biz_zip` ELSE '{$biz_zip_in}' END,
  `biz_addr1` = CASE WHEN '{$biz_addr1_in}'='' THEN `biz_addr1` ELSE '{$biz_addr1_in}' END,
  `biz_addr2` = CASE WHEN '{$biz_addr2_in}'='' THEN `biz_addr2` ELSE '{$biz_addr2_in}' END,
  `tel` = CASE WHEN '{$tel_in}'='' THEN `tel` ELSE '{$tel_in}' END,
  `email` = CASE WHEN '{$email_in}'='' THEN `email` ELSE '{$email_in}' END
WHERE `mb_no` = {$mb_no}
";

$result = db_query($sql);
echo $result ? '1' : '0';
