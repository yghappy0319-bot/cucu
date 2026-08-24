<?php
include_once('../common.php');

if(!$idx){
  die("사이트 정보를 우선 등록해주세요.");
}

$sql = "update site set ";
$sql.= "biz_status = 2,"; //1은 신청 2는 사용가능
$sql.= "biz_number = '{$biz_number}', ";
$sql.= "biz_company = '{$biz_company}', ";
$sql.= "biz_ceoname = '{$biz_ceoname}', ";
$sql.= "biz_etc1 = '{$biz_etc1}', ";
$sql.= "biz_etc2 = '{$biz_etc2}', ";
$sql.= "biz_address = '{$biz_address}', ";
$sql.= "biz_manager_name = '{$biz_manager_name}', ";
$sql.= "biz_manager_email = '{$biz_manager_email}', ";
$sql.= "biz_manager_phone = '{$biz_manager_phone}', ";
$sql.= "popbill_id = '{$popbill_id}' ";
$sql.= "where idx = {$idx} ";

$result = db_query($sql);
echo $result;
