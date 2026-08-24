<?php
include_once('../common.php');
function containsHttps($str) {
    $position = strpos($str, 'https://');
    return $position !== false;
}

if (!containsHttps($site)) {
    die("'https://'을 포함한 전체주소를 입력해주세요.");
}

if(!$luckypanel && $website!=="0" && $website!=="1" && $telegram!=="0" && $telegram!=="1"){
  die("타입 선택에서 럭키패널, 퍼팩트패널, 웹사이트, 텔레그램 중 하나를 선택해주세요.");
}

if($status=="insert"){
  $_site = db_select("select count(*) as cnt from site where site like '%{$site}%' ");
  if($_site['cnt']>0){
    die("이미 등록된 주소가 있습니다.");
  }

  if(!$phone){
    die("연락처를 입력해주세요.");
  }
  if(!$errorlist_number){
    die("발급받은 입금 통보번호를 입력해주세요.");
  }
}

if($panel_type=="lucky"){
  $luckypanel = 1;
  $website = 0;
  $telegram = 0;
}else if($panel_type=="perfect"){
  $luckypanel = 0;
  $website = 0;
  $telegram = 0;
}else if($panel_type=="website"){
  $luckypanel = 0;
  $website = 1;
  $telegram = 0;
}else if($panel_type=="telegram"){
  $luckypanel = 0;
  $website = 0;
  $telegram = 1;
}

if($status=="insert"){
  $sql = "insert into site set ";
  $sql.= "member_no = {$member_no}, ";
  $sql.= "mpoint = 100,";
  $sql.= "luckypanel = {$luckypanel}, ";
  $sql.= "website = {$website}, ";
  $sql.= "telegram = {$telegram}, ";
  $sql.= "site = '{$site}', ";
  $sql.= "bank = '{$bank}', ";
  $sql.= "acount = '{$acount}', ";
  $sql.= "o_acount = '{$o_acount}',";
  $sql.= "acount_name = '{$acount_name}', ";
  $sql.= "api_key = '{$api_key}', ";
  $sql.= "api_url = '{$api_url}', ";
  $sql.= "api_key_v2 = '{$api_key_v2}', ";
  $sql.= "phone = '{$phone}', ";
  $sql.= "errorlist_number = '{$errorlist_number}', ";
  $sql.= "biz_status = 1,";
  $sql.= "biz_number = '{$biz_number}', ";
  $sql.= "biz_company = '{$biz_company}', ";
  $sql.= "biz_ceoname = '{$biz_ceoname}', ";
  $sql.= "biz_etc1 = '{$biz_etc1}', ";
  $sql.= "biz_etc2 = '{$biz_etc2}', ";
  $sql.= "biz_address = '{$biz_address}', ";
  $sql.= "biz_manager_name = '{$biz_manager_name}', ";
  $sql.= "biz_manager_email = '{$biz_manager_email}', ";
  $sql.= "biz_manager_phone = '{$biz_manager_phone}', ";
  $sql.= "popbill_id = '{$popbill_id}', ";
  $sql.= "regdate = now() ";
  $result = db_query($sql);

}else if($status=="update"){
  $sql = "update site set ";
  $sql.= "status = {$site_status}, ";
  $sql.= "luckypanel = {$luckypanel}, ";
  $sql.= "website = {$website}, ";
  $sql.= "telegram = {$telegram}, ";  
  $sql.= "site = '{$site}', ";
  $sql.= "bank = '{$bank}', ";
  $sql.= "acount = '{$acount}', ";
  $sql.= "o_acount = '{$o_acount}',";
  $sql.= "acount_name = '{$acount_name}', ";
  $sql.= "api_key = '{$api_key}', ";
  $sql.= "api_url = '{$api_url}', ";
  $sql.= "api_key_v2 = '{$api_key_v2}', ";
  $sql.= "biz_status = {$biz_status} ";
  $sql.= "where idx = {$idx} ";

  $result = db_query($sql);

}else if($status=="delete"){
  $sql = "update site set ";
  $sql.= "status = 0 ";
  $sql.= "where idx = {$idx} ";
  $result = db_query($sql);
}




echo $result;
