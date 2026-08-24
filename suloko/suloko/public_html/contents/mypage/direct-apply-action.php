<?php
include $_SERVER['DOCUMENT_ROOT']."/_common/config.php";
include $_SERVER['DOCUMENT_ROOT']."/_library/function_BBS.php";

// 테이블명을 설정합니다.
$VAL      = $_POST;
$save_dir = $_SERVER['DOCUMENT_ROOT'].'/upload/qna';
$REFERER  = $_SERVER['HTTP_REFERER'];

if ($M_login['user_id'] == '') {
  meta_go('/');
  exit;
}

if (empty($REFERER)) {
  meta_go('/');
  exit;
}
if (empty($VAL['SUBJECT'])) {
  meta_go('/');
  exit;
}
if (empty($VAL['CONTENT'])) {
  meta_go('/');
  exit;
}
if (empty($VAL['QNA_TYPE'])) {
  meta_go('/');
  exit;
}

if (mb_strlen($VAL['SUBJECT'], 'UTF-8') > 50) {
  alert_print("제목은 50자 이내로 작성해 주세요.");
  history_go();
  exit;
}

$VAL['mode']   = "insert";
$VAL['BBS_NO'] = $db->get_data_one("SELECT MAX(BBS_NO) FROM BBS") + 1;

$con = strip_tags($VAL['CONTENT']);

// INJECTION
$SUBJECT = xss_clean($VAL['SUBJECT']);
$CONTENT = xss_clean($VAL['CONTENT']);
$QNA_TYPE = xss_clean($VAL['QNA_TYPE']);

$VAL['in_ip']    = $ip_address;
$VAL['SUBJECT']  = addslashes($SUBJECT);
$VAL['CONTENT']  = addslashes($CONTENT);
$VAL['GUBUN']    = "QNA";
$VAL['REPLY_YN'] = "N";
$VAL['USER_ID']  = $M_login['user_id'];
$VAL['NAME']     = $M_login['name'];
F_BBS($VAL);

$mem = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID='".$M_login['user_id']."'");

$TEMPLET_NO = 5;

$EMAILTEMPLET = $db->get_data("SELECT * FROM EMAILTEMPLET WHERE TEMPLET_NO='".$TEMPLET_NO."'");
$to           = "";
$subject      = $EMAILTEMPLET['SUBJECT'];
$content      = $EMAILTEMPLET['CONTENT'];
$content      = str_replace('{{문의내용}}', $con, $content);
$type         = "2";

if ($EMAILTEMPLET['STOPYN'] == 'N') {
  // mailer($fname, $fmail, $to, $subject, $content, $type, $mem['NAME'], $mem['MEMBER_NO'], $TEMPLET_NO);
  // AWS SES 클라이언트 생성
  $sesClient = initializeSesClient();
  if ($sesClient) {
    sendMail($sesClient, $fname, $fmail, $to, $subject, $content, $type, $mem['NAME'], $mem['MEMBER_NO'], $TEMPLET_NO);
  }
}

$message = "1:1문의(PC) {$VAL['QNA_TYPE']}\n";
$message.= "{$M_login['user_id']} {$VAL['HP']}\n";
$message.= "-제목\n{$SUBJECT}\n";
$message.= "-내용\n{$SUBJECT}";

sendTelegram("6777050656", $message, "8388467259:AAGlliJQbmaPs9FR-iZH9kH6JHO-cagklZ4");
sendTelegram("7603862766", $message, "8388467259:AAGlliJQbmaPs9FR-iZH9kH6JHO-cagklZ4");
sendTelegram("8075600950", $message, "8388467259:AAGlliJQbmaPs9FR-iZH9kH6JHO-cagklZ4");
sendTelegram("6854091508", $message, "8388467259:AAGlliJQbmaPs9FR-iZH9kH6JHO-cagklZ4");

// meta_go("./list.html?no=".$VAL['no']);
alert_print("문의가 접수되었습니다.");
meta_go("/contents/cs/direct.html");
?>
