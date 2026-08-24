<?php
include_once('../common.php');

$code = 랜덤코드_영문대소문자();

$status = isset($status) ? $status:0;
$card = isset($card) ? $card:0;
$cash_resip = isset($cash_resip) ? $cash_resip:0;
$card_resip = isset($card_resip) ? $card_resip:0;
$tax_status1 = isset($tax_status1) ? $tax_status1:0;
$tax_status2 = isset($tax_status2) ? $tax_status2:0;
$tax_info_save = isset($tax_info_save) ? $tax_info_save:0;
$luckybank_link = isset($luckybank_link) ? $luckybank_link:0;
$form_url = isset($form_url) ? $form_url:1;
$paycnt = db_select("select * from tb_pay_form_config where sidx = {$sidx} and midx = {$midx} ");
$first_form = $first_form=="card" ? "card":"bank";

$bank_persent = isset($bank_persent) && $bank_persent !== '' ? floatval($bank_persent) : 0;
$card_persent = isset($card_persent) && $card_persent !== '' ? floatval($card_persent) : 0;

if($form_url==2){
  $_action_url = $action_url;
}else{
  $_action_url = "/bank/request.php";
}

if($paycnt['idx']){
  $sql = "update tb_pay_form_config set ";
  $sql.= "site = '{$site}',";
  $sql.= "submit_url = '{$_action_url}', ";
  $sql.= "status = {$status},";
  $sql.= "title = '{$title}',";
  $content1 = addslashes($top_content);
  $content2 = addslashes($bottom_content);
  $sql.= "top_content = '{$content1}',";
  $sql.= "bottom_content = '{$content2}',";
  $sql.= "card = {$card},";
  $sql.= "card_url = '{$card_url}',";
  $sql.= "amount_title = '{$amount_title}',";
  $sql.= "amount = '{$amount}',";
  $sql.= "amount_value_title = '{$amount_value_title}',";
  $sql.= "cash_resip = {$cash_resip},";
  $sql.= "card_resip = {$card_resip},";
  $sql.= "tax_status1 = {$tax_status1},";
  $sql.= "tax_status2 = {$tax_status2}, ";
  $sql.= "tax_info_save = {$tax_info_save}, ";
  $sql.= "luckybank_link = {$luckybank_link}, ";
  $sql.= "first_form = '{$first_form}', ";
  $sql.= "popup_pay_btn_name = '{$popup_pay_btn_name}', ";
  $sql.= "forminfo1 = '{$forminfo1}', ";
  $sql.= "forminfo2 = '{$forminfo2}', ";
  $sql.= "forminfo3 = '{$forminfo3}', ";
  $sql.= "bank_event = '{$bank_event}', ";
  $sql.= "card_event = '{$card_event}', ";
  $sql.= "bank_persent = {$bank_persent}, ";
  $sql.= "card_persent = {$card_persent} ";
  $sql.= "where idx = {$paycnt['idx']} ";
  //echo $sql;
  $result = db_query($sql);
}else{


  $sql = "insert into tb_pay_form_config set ";
  $sql.= "midx = {$midx},";
  $sql.= "sidx = {$sidx},";
  $sql.= "site = '{$site}',";
  $sql.= "submit_url = '{$_action_url}', ";
  $sql.= "code = '{$code}',";
  $sql.= "status = {$status},";
  $sql.= "title = '{$title}',";
  $content1 = addslashes($top_content);
  $content2 = addslashes($bottom_content);
  $sql.= "top_content = '{$content1}',";
  $sql.= "bottom_content = '{$content2}',";
  $sql.= "card = {$card},";
  $sql.= "card_url = '{$card_url}',";
  $sql.= "amount_title = '{$amount_title}',";
  $sql.= "amount = '{$amount}',";
  $sql.= "amount_value_title = '{$amount_value_title}',";
  $sql.= "cash_resip = {$cash_resip},";
  $sql.= "card_resip = {$card_resip},";
  $sql.= "tax_status1 = {$tax_status1},";
  $sql.= "tax_status2 = {$tax_status2}, ";
  $sql.= "tax_info_save = {$tax_info_save}, ";
  $sql.= "luckybank_link = {$luckybank_link}, ";
  $sql.= "first_form = '{$first_form}', ";
  $sql.= "popup_pay_btn_name = '{$popup_pay_btn_name}', ";
  $sql.= "forminfo1 = '{$forminfo1}', ";
  $sql.= "forminfo2 = '{$forminfo2}', ";
  $sql.= "forminfo3 = '{$forminfo3}', ";
  $sql.= "bank_event = '{$bank_event}', ";
  $sql.= "card_event = '{$card_event}', ";
  $sql.= "bank_persent = {$bank_persent}, ";
  $sql.= "card_persent = {$card_persent} ";
  //echo $sql;
  $result = db_query($sql);
}
echo $result;
//$sql.= "midx = ,";
//$sql.= "midx = '',";
