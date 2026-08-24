<?php
require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";
require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_INVOCE.php";
require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_T_WCASH_LOG.php";

$VAL = $_GET;

$mem = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID='{$M_login['user_id']}' LIMIT 1");

if ($mem['USER_ID'] == '') {
  alert_print("존재하지 않은 회원입니다.");
  history_go(-1);
  exit;
}

$fee       = 500;
$request_m = (int) preg_replace('/[^0-9]/', '', isset($VAL['MONEY']) ? (string) $VAL['MONEY'] : '');
$out_m     = $request_m - $fee;
$tot_price = $out_m;
$VAL['MONEY']  = (string) $request_m;
$VAL['MONEY2'] = (string) $out_m;

// 보유당첨금
$money = $mem['WINCASH'];

if ($request_m < 1100) {
  alert_print("최소 출금 신청액은 1,100원 이상입니다.");
  history_go(-1);
  exit;
}
if ($out_m < 0) {
  alert_print("처리할 수 없는 출금 금액입니다.");
  history_go(-1);
  exit;
}
if ($request_m > $money) {
  alert_print("환급 신청액이 신청 가능한 금액을 초과하였습니다.");
  history_go(-1);
  exit;
}

$VAL['mode']      = "insert";
$VAL['USER_ID']   = $mem['USER_ID'];
$VAL['STATUS']    = "N";
$VAL['INVOCE_NO'] = $db->get_data_one("SELECT MAX(INVOCE_NO) as INVOCE_NO FROM INVOCE") + 1;
F_INVOCE($VAL);

// 환급신청로그(차감)
$wcash_log = array();
$wcash_log['mode']      = "insert";
$wcash_log['ORDERS_NO'] = 0;
$wcash_log['INVOCE_NO'] = $VAL['INVOCE_NO'];
$wcash_log['PRICE']     = $VAL['MONEY2'];
$wcash_log['WCASH']     = $VAL['MONEY'];
$wcash_log['N_WCASH']   = $mem['WINCASH'] - $VAL['MONEY'];
$wcash_log['O_WCASH']   = $mem['WINCASH'];
$wcash_log['USER_ID']   = $mem['USER_ID'];
$wcash_log['MEMO']      = "당첨금출금신청";
$wcash_log['STATUS']    = "M";
F_T_WCASH_LOG($wcash_log);

$sql = "
  UPDATE
    MEMBER
  SET
    WINCASH = WINCASH - {$VAL['MONEY']}
  WHERE
    USER_ID = '{$M_login['user_id']}'
";
$db->query($sql);

/*
$TEMPLET_NO  = 6;
$SMSTEMPLET  = $db->get_data("SELECT * FROM SMSTEMPLET WHERE TEMPLET_NO='".$TEMPLET_NO."'");
$from        = $fromHP;
$to          = $mem["HP"];
$CODESK      = "S";
$MEMBER_NO   = $mem['MEMBER_NO'];
$MEMBER_NAME = $mem['NAME'];
$SUBJECT     = $SMSTEMPLET['SUBJECT'];
$SENDMSG     = $SMSTEMPLET['CONTENT'];
$SENDMSG     = str_replace('{MEMBERNAME}', $mem['NAME'], $SENDMSG);
$SENDMSG     = str_replace('{APPCASH}', number_format($tot_price), $SENDMSG);
$SENDMSG     = addslashes($SENDMSG);
$sms         = curl_sms($to, $from, $CODESK, $TEMPLET_NO, $MEMBER_NO, $MEMBER_NAME, $SUBJECT, $SENDMSG,"LMS");
*/

$mem  = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID='".$M_login['user_id']."'");

$msg2 = "출금신청MO | {$M_login['user_id']}\n";
$msg2.= "신청(당첨금차감) : ".number_format($request_m)."원\n";
$msg2.= "실제출금(수수료 {$fee}원 차감) : ".number_format($out_m)."원\n";
$msg2.= "은행 : {$VAL['BANK1']} ({$VAL['BANK3']})\n";
$msg2.= "계좌번호 : {$VAL['BANK2']}\n";
$msg2.= "신청날짜 : ".date("Y-m-d H:i");
$msg2.= "\n----------------";

foreach ($telegram_user_id as $telegram_uid) {
  sendTelegram($telegram_uid, $msg2, $telegram_token);
}

alert_print("당첨금 출금신청 완료");
meta_go("/contents/mypage/cash-history-refund.html");
?>
