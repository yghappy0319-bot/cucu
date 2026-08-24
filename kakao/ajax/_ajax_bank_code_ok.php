<?
include_once "/home/luckybank/public_html/lib/sms_server_function.php";
include_once "../lib/authentication_number.php";
include_once "../_chk.php";
if($idx>0){
  sms_db_query("update bankdata set status = 9 where id = {$idx} ");
}

if($bank=="기업은행"){
    echo 기업은행_계좌_마스킹($acount);
}else if($bank=="농협은행"){
  echo 농협은행_계좌_마스킹($acount);
}else if($bank=="신한은행"){
  echo 신한은행_계좌_마스킹($acount);
}else if($bank=="국민은행"){
  echo 국민은행_계좌_마스킹($acount);
}else if($bank=="우리은행"){
  echo 우리은행_계좌_마스킹($acount);
}else if($bank=="하나은행"){
  echo 하나은행_계좌_마스킹($acount);
}else if($bank=="SC제일은행"){
  echo SC제일은행_계좌_마스킹($acount);
}else if($bank=="케이뱅크"){
  echo substr(preg_replace('/[^0-9]/', '', $acount), -4);
}else if($bank=="수협"){
  echo "";
}
