<?
### 무통장 입금 자돋처리
### KB국민은행
include $_SERVER['DOCUMENT_ROOT']."/_common/config.php";
include $_SERVER['DOCUMENT_ROOT']."/_library/function_T_CASH_LOG.php";
include $_SERVER['DOCUMENT_ROOT']."/_library/function_T_POINT_LOG.php";
include $_SERVER['DOCUMENT_ROOT']."/_common/pay_function.php";
include $_SERVER['DOCUMENT_ROOT']."/SMS/function_sms.php";

$SMS_BANK = "KB국민은행";
$msg = trim($_POST["msg"]);

if ($msg) {
  syslog(LOG_DEBUG, "<============[SMS] START=============");
  syslog(LOG_DEBUG, "============[SMS] $msg=============");

  $msg_tmp = explode("\n", $msg);

  syslog(LOG_DEBUG, "============[SMS] $msg_tmp[0] / $msg_tmp[1] / $msg_tmp[2] / $msg_tmp[3] / $msg_tmp[4] / $msg_tmp[5] / $msg_tmp[6] / $msg_tmp[7] =============");

  ## 입금이 아닐경우 exit
  if (strpos($msg_tmp[6], "입금") === false) {
    syslog(LOG_DEBUG, "============[SMS] 입급내역X=============");
    syslog(LOG_DEBUG, "============[SMS] END=============");
    exit();
  }

  # 일시
  $SMS_DATE_tmp_1 = explode("[KB]", $msg_tmp[3]);

  $SMS_DATE_tmp = explode(" ", $SMS_DATE_tmp_1[1]);
  $SMS_DATE_INFO = $SMS_DATE_tmp[0];
  $SMS_DATE_TIME = $SMS_DATE_tmp[1];
  $SMS_DATE_INFO = date("Y")."-".$SMS_DATE_tmp[0]." ".$SMS_DATE_TIME ;
  $SMS_DATE = str_replace("/", "-", $SMS_DATE_INFO);


  $SMS_BANK_NUMBER = trim($msg_tmp[4]);

  # 입금자명이 거상MFC 일 경우 박상선으로 동일처리 (VVIP 회원)
  $SMS_NAME = str_replace("거상MFC", "박상선", trim($msg_tmp[5]));
  $SMS_NAME = str_replace("LIULONGSH", "류용산", trim($msg_tmp[5]));
  $SMS_PRICE = (INT) trim(str_replace(",", "", str_replace(" 입금", "", $msg_tmp[7])));

  # 주문건 확인
  # 2일 이내 건만 확인
  $fields = " COUNT(*) AS CNT ";
  $query = " SELECT ".$fields ." FROM CASH AS C INNER JOIN  MEMBER AS M";
  $query .= " ON C.MEMBER_NO=M.MEMBER_NO";
  $query .= " WHERE C.CARDNAME='무통장' ";
  $query .= " AND C.IS_USE='N' ";
  $query .= " AND M.NAME = '".$SMS_NAME."' ";
  $query .= " AND C.PRICE=".$SMS_PRICE;
  $query .= " AND C.REG_DATE >= date_add(now(), interval -2 day)";
  $tmp			=	$db->get_data($query);

  $tmp_count = $tmp['CNT'];
  syslog(LOG_DEBUG, "============ COUNT : $tmp_count =============");

  # 1건만 있을 경우에만 CASH처리
  if ($tmp["CNT"] == 1) {

    $fields = " M.MEMBER_NO, M.USER_ID, M.NAME, M.PARTNER_ID, M.REG_DATE, M.CASH AS MEMBER_CASH, M.POINT AS MEMBER_POINT, M.PARTNER_ID, M.HP, M.EMAIL, C.CASH_NO, C.CASH";
    $query = " SELECT ".$fields ." FROM CASH AS C INNER JOIN  MEMBER AS M";
    $query .= " ON C.MEMBER_NO=M.MEMBER_NO";
    $query .= " WHERE C.CARDNAME='무통장' ";
    $query .= " AND C.IS_USE='N' ";
    $query .= " AND M.NAME = '".$SMS_NAME."' ";
    $query .= " AND C.PRICE=".$SMS_PRICE;
    $query .= " AND C.REG_DATE >= date_add(now(), interval -2 day)";
    $tmp			=	$db->get_data($query);

    ## 자동 입금확인 CASH 로그 저장
    $STATUS = "Y";
    $fields = "SMS_BANK, SMS_BANK_NUMBER, SMS_DATE, SMS_PRICE, SMS_NAME, SMS_TXT,  CASH_NO, STATUS";
    $values = "'".$SMS_BANK."', '".$SMS_BANK_NUMBER."', '".$SMS_DATE."', '".$SMS_PRICE."', '".$SMS_NAME."', '".$msg."', '".$tmp['CASH_NO']."', '".$STATUS."' ";
    $query = " INSERT INTO CASH_SMS_LOG (".$fields.") VALUES (".$values.") ";
    $db->query($query);


    ## CASH table UPDATE + 캐시/보너스포인트 지급
    exec_member_cash($tmp['USER_ID'], $tmp['PARTNER_ID'], $tmp['REG_DATE'], $tmp['CASH'], $tmp['MEMBER_CASH'], $tmp['MEMBER_POINT'], $tmp['CASH_NO'], $SMS_PRICE);
    exec_member_cash_update($tmp['CASH'], $tmp['USER_ID'], $tmp['CASH_NO']);

    syslog(LOG_DEBUG, "============[SMS] 처리 완료 =============>");

   ## 입금확인 SMS 문자 발송
    $TEMPLET_NO   = 19;
    $SMSTEMPLET   = $db->get_data("SELECT * FROM SMSTEMPLET WHERE TEMPLET_NO='".$TEMPLET_NO."'");

    $from         = $fromHP;
    $to           = $tmp["HP"];
    $CODESK       = "S";
    $MEMBER_NO    = $tmp['MEMBER_NO'];
    $MEMBER_NAME  = $tmp['NAME'];

    $SUBJECT      = $SMSTEMPLET['SUBJECT'];
    $SENDMSG      = $SMSTEMPLET['CONTENT'];

    $SENDMSG      = str_replace('{USERID}', $tmp['USER_ID'], $SENDMSG);
    $SENDMSG      = addslashes($SENDMSG);
    $sms          = curl_sms($to, $from, $CODESK, $TEMPLET_NO, $MEMBER_NO, $MEMBER_NAME, $SUBJECT, $SENDMSG,"LMS");

    ## EMAIL
    $TEMPLET_NO   = 6;
    $EMAILTEMPLET = $db->get_data("SELECT * FROM EMAILTEMPLET WHERE TEMPLET_NO='".$TEMPLET_NO."'");
    $to           = $tmp['EMAIL'];
    $subject      = $EMAILTEMPLET['SUBJECT'];
    $content      = $EMAILTEMPLET['CONTENT'];
    $type         = "2";

    if ($EMAILTEMPLET['STOPYN'] == 'N') {
      // mailer($fname, $fmail, $to, $subject, $content, $type, $tmp['NAME'], $tmp['MEMBER_NO'], $TEMPLET_NO);
      // AWS SES 클라이언트 생성
      $sesClient = initializeSesClient();
      if ($sesClient) {
        sendMail($sesClient, $fname, $fmail, $to, $subject, $content, $type, $tmp['NAME'], $tmp['MEMBER_NO'], $TEMPLET_NO);
      }
    }

  }
  syslog(LOG_DEBUG, "============[SMS] END =============>");
} else {
  syslog(LOG_DEBUG, "<============[SMS] Invalid Request =============>");
}
?>
