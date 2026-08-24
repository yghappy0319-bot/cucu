<?
  /* ------------------------------------------------------------------------
    입금 SMS 관련 처리
  ------------------------------------------------------------------------
  */


function 국민_계좌번호_제거후_문자내용($inputString, $pattern) {
  $outputString = preg_replace('/' . preg_quote($pattern, '/') . '/', '', $inputString);
  return $outputString;
}

function 국민_입금자명_옆_입금상태제거($inputString) {
  // 함수 내부에 키워드 정의
  $keywordsToRemove = array("전자금융", "간편이체", "스마트폰", "타행", "CD공동","오픈뱅킹");

  // 키워드를 순회하면서 해당 부분 제거
  foreach ($keywordsToRemove as $keyword) {
    $inputString = str_ireplace($keyword, '', $inputString);
  }

  return $inputString;
}
function 문자열중간에소괄호체크($inputString) {
  $posParenthesis = strpos($inputString, '(');
  $posFBS = strpos($inputString, 'FBS');

  // 둘 중 어떤 것이 먼저 나오는지 확인
  if ($posParenthesis !== false && ($posFBS === false || $posParenthesis < $posFBS)) {
    // 소괄호가 먼저 나오는 경우
    return substr($inputString, 0, $posParenthesis);
  } elseif ($posFBS !== false && ($posParenthesis === false || $posFBS < $posParenthesis)) {
    // "FBS"가 먼저 나오는 경우, "FBS" 이전의 부분을 추출
    return substr($inputString, 0, $posFBS);
  }

  // 둘 다 없거나 동시에 나온 경우
  return $inputString;
}
function 국민_입금자명만추출($inputString) {
  // 정규식을 사용하여 맨 앞의 2자리 숫자 제거
  $outputString = preg_replace('/^\d{2}/', '', $inputString);

  return $outputString;
}
function 국민_문자열맨끝_숫자형식만추출($inputString) {
  // 정규식을 사용하여 문자열의 맨 끝에 있는 숫자형식 데이터 추출 (콤마 포함)
  preg_match('/[\d,]+$/', $inputString, $matches);

  // 추출된 숫자를 정리하여 반환, 없을 경우 기본값인 0 반환
  if (isset($matches[0])) {
    $numberString = str_replace(',', '', $matches[0]); // 콤마 제거
    return (int)$numberString;
  } else {
    return 0;
  }
}


  ## 폰으로 수신된 APP에서 정보 추출
  function get_sms_receive_info_app($msg) {
    if ($msg) {

      $msg_replace = trim($msg);
      $msg_replace = str_replace("  ", " ", $msg_replace);
      $msg_tmp = explode(" ", $msg_replace);

      ## 입금이 아닐경우 exit
      if (strpos($msg_tmp[5], "입금") === false) {
        if (strpos($msg_tmp[5], "이체") === false) {  //e.g. CD이체
          syslog(LOG_DEBUG, "[SMS] 입금내역 문자가 아님");
          syslog(LOG_DEBUG, "[SMS] END");

          ## debuging
          $msg_tele = "[SMS] 입금내역 문자가 아님\n";
          $msg_tele .= $msg;
          $_TELEGRAM_CHAT_ID_DEV = array('');

          foreach ($_TELEGRAM_CHAT_ID_DEV AS $_TELEGRAM_CHAT_ID_STR) {
              $_TELEGRAM_QUERY_STR    = array(
                  'chat_id' => $_TELEGRAM_CHAT_ID_STR,
                  'text'    => $msg_tele,
                  'parse_mode' => "HTML"
              );
              telegramApiRequest("sendMessage", $_TELEGRAM_QUERY_STR);
          }
          exit();
        }
      }

      ## 일시
      $SMS_DATE_PART = $msg_tmp[1];
      if (preg_match('/\[KB\](\d{2}\/\d{2})/', $msg_tmp[1], $dateMatch)) {
        $SMS_DATE_PART = $dateMatch[1];
      }
      $SMS_DATE_INFO = date("Y")."-".$SMS_DATE_PART." ".$msg_tmp[2] ;
      $SMS_DATE = str_replace("/", "-", $SMS_DATE_INFO);

      ## 입금자명
      # VVIP 회원 처리 (예외처리)
      $tmp = get_exception($msg_tmp[4]);
      $SMS_NAME = $tmp["SMS_NAME"];

      # 이름에 특수문자 이후 삭제 처리 - ex) 한용운(기
      $SMS_NAME_tmp = explode("(", trim($SMS_NAME));
      if (count($SMS_NAME_tmp) > 0) {
        $SMS_NAME = trim($SMS_NAME_tmp[0]);
      }

      #입금액
      $SMS_PRICE = (INT) trim(str_replace(",", "", str_replace(" 입금", "", trim($msg_tmp[6]))));

      # 입금계좌
      $SMS_BANK_NUMBER = trim($msg_tmp[3]);

      $SMS["SMS_DATE"] = $SMS_DATE;
      $SMS["SMS_NAME"] = $SMS_NAME;
      $SMS["SMS_NAME_ORG"] = trim($msg_tmp[4]);
      $SMS["SMS_PRICE"] = $SMS_PRICE;
      $SMS["SMS_BANK_NUMBER"] = $SMS_BANK_NUMBER;

      #file_put_contents("../log/app.json", json_encode($SMS));
      #exit();

      return $SMS;
    }
  }


  ## 폰으로 수신된 문자에서 정보 추출
  function get_sms_receive_info($msg, $acount) {
    global $db;
    if ($msg) {

      $msg_norm = preg_replace('/\s+/u', ' ', trim($msg));

      ## KB 신규 형식 (공백 구분)
      ## e.g. [Web발신] [KB]06/14 13:58 538801**478 장영관 입금 6,600
      if (preg_match('/\[KB\]\d{2}\/\d{2}\s+\d{2}:\d{2}/', $msg_norm)) {
        $parts = explode(' ', $msg_norm);
        $depositIdx = array_search('입금', $parts, true);

        if ($depositIdx !== false && isset($parts[$depositIdx + 1]) && $depositIdx >= 4) {
          if (strpos($msg_norm, "입금취소") === false) {
            $SMS_BANK_NUMBER = trim($parts[$depositIdx - 2]);
            $SMS_NAME_ORG = trim($parts[$depositIdx - 1]);
            $SMS_PRICE = (INT) str_replace(",", "", trim($parts[$depositIdx + 1]));

            $SMS_DATE_PART = '';
            $SMS_TIME_PART = '';
            for ($i = 0; $i < $depositIdx - 2; $i++) {
              if (preg_match('/\[KB\](\d{2}\/\d{2})/', $parts[$i], $dateMatch)) {
                $SMS_DATE_PART = $dateMatch[1];
              }
              if (preg_match('/^\d{2}:\d{2}$/', $parts[$i], $timeMatch)) {
                $SMS_TIME_PART = $timeMatch[0];
              }
            }

            if ($SMS_DATE_PART && $SMS_TIME_PART && $SMS_NAME_ORG && $SMS_PRICE > 0) {
              $SMS_DATE = str_replace("/", "-", date("Y")."-".$SMS_DATE_PART." ".$SMS_TIME_PART);

              $tmp = get_exception($SMS_NAME_ORG);
              $SMS_NAME = $tmp["SMS_NAME"];
              $SMS_NAME_tmp = explode("(", trim($SMS_NAME));
              if (count($SMS_NAME_tmp) > 0) {
                $SMS_NAME = trim($SMS_NAME_tmp[0]);
              }

              $SMS["SMS_DATE"] = $SMS_DATE;
              $SMS["SMS_NAME"] = $SMS_NAME;
              $SMS["SMS_NAME_ORG"] = $SMS_NAME_ORG;
              $SMS["SMS_PRICE"] = $SMS_PRICE;
              $SMS["SMS_BANK_NUMBER"] = $SMS_BANK_NUMBER;

              syslog(LOG_DEBUG, "[SMS] parsed(new) NAME=".$SMS_NAME." PRICE=".$SMS_PRICE." DATE=".$SMS_DATE." ACCT=".$SMS_BANK_NUMBER);

              return $SMS;
            }
          }
        }
      }

      ## KB 구 형식 (붙어서 수신)
      ## e.g. [Web발신][KB]06/1413:58538801**478김민석입금6,600
      $_kb계좌번호_추출후문자열 = preg_replace('/538801\*\*\d{3}/', '', $msg);
      if ($acount) {
        $_kb계좌번호_추출후문자열 = 국민_계좌번호_제거후_문자내용($_kb계좌번호_추출후문자열, $acount);
      }
      $_kb_입금상태제거 = 국민_입금자명_옆_입금상태제거($_kb계좌번호_추출후문자열);
      $_특수문자기준으로분할 = explode(":", $_kb_입금상태제거);
      $_입금단어앞입금자명추출 = explode("입금", $_특수문자기준으로분할[1]);

      $입금자명 = 문자열중간에소괄호체크(국민_입금자명만추출($_입금단어앞입금자명추출[0]));
      $입금액 = 국민_문자열맨끝_숫자형식만추출($_kb_입금상태제거);

      $SMS["SMS_DATE"] = date("Y-m-d H:i:00");
      $SMS["SMS_NAME"] = $입금자명;
      $SMS["SMS_NAME_ORG"] = $입금자명;
      $SMS["SMS_PRICE"] = $입금액;
      $SMS["SMS_BANK_NUMBER"] = $acount;

      syslog(LOG_DEBUG, "[SMS] parsed(old) NAME=".$입금자명." PRICE=".$입금액." DATE=".$SMS["SMS_DATE"]." ACCT=".$acount);

      /**
      ## [Web발신] 문구가 누락되는 경우가 있어서 해당행은 아예 삭제처리
      $msg_replace = str_replace("[Web발신]\n", "", $msg);
      $msg_tmp = explode("\n", $msg_replace);


      ## 입금이 아닐경우 exit
      if (strpos($msg_tmp[5], "입금") === false || strpos($msg_tmp[5], "입금취소") !== false) {  //e.g. 입금취소
        if (strpos($msg_tmp[5], "이체") === false) {  //e.g. CD이체
          syslog(LOG_DEBUG, "[SMS] 입금내역 문자가 아님");
          syslog(LOG_DEBUG, "[SMS] END");

          ## debuging
          $msg_tele = "[SMS] 입금내역 문자가 아님\n";
          $msg_tele .= $msg;
          $_TELEGRAM_CHAT_ID_DEV = array('');

          foreach ($_TELEGRAM_CHAT_ID_DEV AS $_TELEGRAM_CHAT_ID_STR) {
              $_TELEGRAM_QUERY_STR    = array(
                  'chat_id' => $_TELEGRAM_CHAT_ID_STR,
                  'text'    => $msg_tele,
                  'parse_mode' => "HTML"
              );
              //telegramApiRequest("sendMessage", $_TELEGRAM_QUERY_STR);
          }
          exit();
        }
      }

      ## 일시
      $SMS_DATE_tmp_1 = explode("[KB]", trim($msg_tmp[2]));
      $SMS_DATE_tmp = explode(" ", $SMS_DATE_tmp_1[1]);
      $SMS_DATE_INFO = $SMS_DATE_tmp[0];
      $SMS_DATE_TIME = $SMS_DATE_tmp[1];
      $SMS_DATE_INFO = date("Y")."-".$SMS_DATE_tmp[0]." ".$SMS_DATE_TIME ;
      $SMS_DATE = str_replace("/", "-", $SMS_DATE_INFO);

      ## 입금자명
      # VVIP 회원 처리 (예외처리)
      $tmp = get_exception($msg_tmp[4]);
      $SMS_NAME = $tmp["SMS_NAME"];

      # 이름에 특수문자 이후 삭제 처리 - ex) 한용운(기
      $SMS_NAME_tmp = explode("(", trim($SMS_NAME));
      if (count($SMS_NAME_tmp) > 0) {
        $SMS_NAME = trim($SMS_NAME_tmp[0]);
      }

      #입금액
      $SMS_PRICE = (INT) trim(str_replace(",", "", str_replace(" 입금", "", trim($msg_tmp[6]))));

      # 입금계좌
      $SMS_BANK_NUMBER = trim($msg_tmp[3]);

      $SMS["SMS_DATE"] = $SMS_DATE;
      $SMS["SMS_NAME"] = $SMS_NAME;
      $SMS["SMS_NAME_ORG"] = trim($msg_tmp[4]);
      $SMS["SMS_PRICE"] = $SMS_PRICE;
      $SMS["SMS_BANK_NUMBER"] = $SMS_BANK_NUMBER;

       **/



      return $SMS;
    }
  }

  ## 입금자 예외처리
  function get_exception($v) {
    global $db;
    if ($v) {
      $query = "SELECT SMS_TO_NAME FROM CASH_SMS_EXCEPTION ";
      $query .= " WHERE SMS_FROM_NAME = '".$v."' ";
      $tmp_1			=	$db->get_data($query);

      if ($tmp_1["SMS_TO_NAME"]) {
        $SMS_NAME["SMS_NAME"] = $tmp_1["SMS_TO_NAME"];
      } else {
        $SMS_NAME["SMS_NAME"] = $v;
      }
      $SMS_NAME["SMS_NAME_ORG"] = $v;
      return $SMS_NAME;
    }
  }

  ## 동일SMS 갯수
  function get_sms_count($SMS_BANK, $SMS_BANK_NUMBER, $SMS_DATE, $SMS_PRICE, $SMS_NAME, $SMS_REGDATE) {
    global $db;

    $query = " SELECT COUNT(*) AS CNT FROM CASH_SMS_LOG ";
    $query .= " WHERE SMS_BANK = '".$SMS_BANK."' ";
    $query .= " AND SMS_BANK_NUMBER = '".$SMS_BANK_NUMBER."' ";
    $query .= " AND SMS_DATE = '".$SMS_DATE."' ";
    $query .= " AND SMS_PRICE = '".$SMS_PRICE."' ";
    $query .= " AND SMS_NAME = '".$SMS_NAME."' ";
    if ($SMS_REGDATE == "Y") {
      $query .= "AND REG_DATE >= date_add(now(), interval -2 day)";
    }
    $num_chk		=	$db->get_data($query);

    $SMS["CNT"] = $num_chk["CNT"];

    return $SMS;
  }

  # SMS 정보
  function get_sms_info($SMS_BANK, $SMS_BANK_NUMBER, $SMS_DATE, $SMS_PRICE, $SMS_NAME ,$msg) {
    global $db;

    $whereis .= " WHERE SMS_BANK = '".$SMS_BANK."'  ";
    $whereis .= " AND SMS_BANK_NUMBER = '".$SMS_BANK_NUMBER."'  ";
    $whereis .= " AND SMS_DATE = '".$SMS_DATE."'  ";
    $whereis .= " AND SMS_NAME = '".$SMS_NAME."'  ";
    $whereis .= " AND SMS_TXT = '".$msg."' ";

    $query = " SELECT idx FROM CASH_SMS_LOG ";
    $query .= $whereis;
    $query .= " ORDER BY idx DESC LIMIT 1";
    $log_tmp			=	$db->get_data($query);

    $log["idx"] = $log_tmp["idx"];

    return $log;
  }

  # 수신 SMS UPDATE
  function exec_sms_update($STATUS, $CASH_NO, $MEMBER_NO, $USER_ID, $SMS_LOG_IDX) {
    global $db;

    if ($CASH_NO == "") $CASH_NO = 0;
    if ($MEMBER_NO == "") $MEMBER_NO = 0;

    $fields = " STATUS = '".$STATUS."', CASH_NO = '".$CASH_NO."', MEMBER_NO = '".$MEMBER_NO."', USER_ID = '".$USER_ID."' ";
    $query = " UPDATE CASH_SMS_LOG SET ".$fields;
    $query .= " WHERE idx = ".$SMS_LOG_IDX;

    $db->query($query);
  }


  # SMS 발송
  function exec_send_sms($TEMPLET_NO, $to, $from, $MEMBER_NO, $MEMBER_NAME, $USER_ID, $ORDER_PRICE="", $ORDER_PRICE_TOTAL = "", $DEPOSIT_PRICE="" ) {
    global $db;

    if (!$TEMPLET_NO) $TEMPLET_NO   = 19;

    $SMSTEMPLET   = $db->get_data("SELECT * FROM SMSTEMPLET WHERE TEMPLET_NO='".$TEMPLET_NO."'");

    $CODESK       = "S";

    $SUBJECT      = str_replace('{PRICE}', $DEPOSIT_PRICE, $SMSTEMPLET['SUBJECT']); // 추가 입금해야 할 입금액
    $SENDMSG      = $SMSTEMPLET['CONTENT'];

    $SENDMSG      = str_replace('{USERID}', $USER_ID, $SENDMSG);
    $SENDMSG      = str_replace('{NAME}', $MEMBER_NAME, $SENDMSG);
    $SENDMSG      = str_replace('{ORDER_PRICE}', $ORDER_PRICE, $SENDMSG); // 주문서금액
    $SENDMSG      = str_replace('{PRICE}', $DEPOSIT_PRICE, $SENDMSG); // 추가 입금해야 할 입금액
    $SENDMSG      = str_replace('{ORDER_PRICE_TOTAL}', $ORDER_PRICE_TOTAL, $SENDMSG); // 현재까지 입금액
    $SENDMSG      = addslashes($SENDMSG);
    #syslog(LOG_DEBUG, "[SMS]".$SUBJECT);

    $sms          = curl_sms($to, $from, $CODESK, $TEMPLET_NO, $MEMBER_NO, $MEMBER_NAME, $SUBJECT, $SENDMSG,"LMS");
  }

  # 이메일 발송
  function exec_send_email($TEMPLET_NO, $to, $type, $MEMBER_NAME, $MEMBER_NO) {
    global $db;

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

  # 캐시 업데이트
  function exec_cash_update($CASH_NO, $USER_ID, $fields) {
    global $db;

    if ($CASH_NO) {
      $query = "UPDATE CASH SET ".$fields;
      $query .= " WHERE USER_ID = '".$USER_ID."' ";
      $query .= " AND CASH_NO = ".$CASH_NO;
      $db->query($query);
    }

  }

  # 회원 캐시 업데이트
  function exec_member_cash_update($CASH, $USER_ID, $CASH_NO) {
    global $db;

    if ($USER_ID) {
      //입금액 비교가 아니라 .. 캐시로 지급되는 액으로 비교를 한다
      $지급포인트 = get_cash_config_by_cash($CASH);
      $grantPoint = !empty($지급포인트['POINT']) ? (int)$지급포인트['POINT'] : 0;

      $query = "UPDATE MEMBER SET CASH = CASH + ".$CASH;
      if ($grantPoint > 0) {
        $query .= ", POINT = POINT + {$grantPoint} ";
      }
      $query .= " WHERE USER_ID = '".$USER_ID."' ";
      $db->query($query);

    }
  }

  # 주문서 정보
  function get_cash_info($SMS_NAME, $SMS_PRICE, $SMS_REGDATE="N") {
    global $db;

    $whereIs = " WHERE C.CARDNAME='무통장' ";
    $whereIs .= " AND C.IS_USE='N' ";
    $whereIs .= " AND M.NAME = '".$SMS_NAME."' ";
    if ($SMS_PRICE) {
      $whereIs .= " AND C.PRICE=".$SMS_PRICE;
    }
    if ($SMS_REGDATE == "Y") {
      $whereIs .= " AND C.REG_DATE >= date_add(now(), interval -2 day)";
    }

    $fields = " M.MEMBER_NO, M.USER_ID, M.NAME, M.PARTNER_ID, M.REG_DATE, M.CASH AS MEMBER_CASH, M.POINT AS MEMBER_POINT, ";
    $fields .= " M.PARTNER_ID, M.HP, M.EMAIL, C.CASH_NO, C.CASH, PRICE, C.REG_DATE ";

    $query = " SELECT ".$fields ." FROM CASH AS C INNER JOIN  MEMBER AS M";
    $query .= " ON C.MEMBER_NO=M.MEMBER_NO";
    $query .= $whereIs;
    $tmp		=	$db->get_data($query);

    $CASH["CASH_NO"] = $tmp["CASH_NO"];
    $CASH["MEMBER_NO"] = $tmp['MEMBER_NO'];
    $CASH["USER_ID"] = $tmp["USER_ID"];
    $CASH["REG_DATE"] = $tmp["REG_DATE"];
    $CASH["CASH"] = $tmp["CASH"];
    $CASH["PARTNER_ID"] = $tmp["PARTNER_ID"];
    $CASH["HP"] = $tmp["HP"];
    $CASH["EMAIL"] = $tmp["EMAIL"];
    $CASH["PRICE"] = $tmp["PRICE"];
    $CASH["CASH"] = $tmp["CASH"];
    $CASH["MEMBER_CASH"] = $tmp["MEMBER_CASH"];
    $CASH["MEMBER_POINT"] = $tmp["MEMBER_POINT"];
    $CASH["MEMBER_PARTNER_ID"] = $tmp["PARTNER_ID"];

    // SLK-1440 이벤트 대상 확인
    $CASH["IS_EVENT_USER"] = false;//chk_event_user($CASH["USER_ID"]);

    return $CASH;
  }

  # 일치하는 주문서 갯수
  function get_cash_count($SMS_NAME, $SMS_PRICE, $SMS_REGDATE="N") {
    global $db;

    $whereIs = " WHERE C.CARDNAME='무통장' ";
    $whereIs .= " AND C.IS_USE='N' ";
    $whereIs .= " AND M.NAME = '".$SMS_NAME."' ";
    if ($SMS_PRICE) {
      $whereIs .= " AND C.PRICE=".$SMS_PRICE;
    }
    if ($SMS_REGDATE == "Y") {
      $whereIs .= " AND C.REG_DATE >= date_add(now(), interval -2 day)";
    }

    $fields = " COUNT(*) AS CNT ";
    $query = " SELECT ".$fields ." FROM CASH AS C INNER JOIN  MEMBER AS M";
    $query .= " ON C.MEMBER_NO=M.MEMBER_NO";
    $query .= $whereIs;
    $tmp			=	$db->get_data($query);

    $CASH["CNT"] = $tmp["CNT"];

    return $CASH;
  }

  # 캐시 지급
  function exec_member_cash($USER_ID, $PARTNER_ID, $REG_DATE, $CASH, $MEMBER_CASH, $MEMBER_POINT, $CASH_NO, $SMS_PRICE ) {
    global $db;

    ## 파트너 첫결제
    if ($PARTNER_ID) {
      $RESULT_PARTNER_ID = exec_partner_firstsale($USER_ID, $PARTNER_ID, $REG_DATE);
    }

    ## 파트너 summary 처리
    if ($RESULT_PARTNER_ID) {
      exec_partner_summary($PARTNER_ID, "PAY");
    }

    //회원의 결제수
    $CASH_CNT = $db->get_data_one("SELECT COUNT(*) AS CNT FROM CASH WHERE USER_ID = '{$USER_ID}' AND IS_USE='Y'") + 1;

    ## CASH table UPDATE
    $fields = " IS_USE = 'Y', CON_REG_DATE = NOW(), PARTNER_ID = '{$RESULT_PARTNER_ID}', CASH_CNT = '{$CASH_CNT}' ";
    exec_cash_update($CASH_NO, $USER_ID, $fields);

    ## T_CASH_LOG INSERT
    $_L['mode']    = "insert";
    $_L['CASH_NO']      = $CASH_NO;
    $_L['ORDERS_NO']    = 0;
    $_L['WINNING_NO']   = 0;
    $_L['INVOCE_NO']    = 0;
    $_L['CASH_LOG_NO']  = 0;
    $_L['PRICE']        = $SMS_PRICE;
    $_L['CASH']         = $CASH;
    $_L['N_CASH']       = $MEMBER_CASH + $CASH; //지급(차감) 후 캐시
    $_L['O_CASH']       = $MEMBER_CASH; // 기브(차감) 전 캐시
    $_L['USER_ID']      = $USER_ID;
    $_L['MEMO']         = "캐시구매충전(무통장)";
    $_L['STATUS']       = "P";
    F_T_CASH_LOG($_L);


    $POINT = 0;

    $지급포인트 = get_cash_config_by_cash($CASH);
    $POINT = !empty($지급포인트['POINT']) ? (int)$지급포인트['POINT'] : 0;

    if ($POINT > 0) {

      $point_log = array();
      $point_log["mode"] = "insert";
      $point_log["COUPON_NO"] = 0;
      $point_log["ORDERS_NO"] = 0;
      $point_log["CASH_LOG_NO"] = $CASH_NO;
      $point_log["POINT"] = $POINT;
      $point_log["N_POINT"] = $MEMBER_POINT + $POINT;
      $point_log["O_POINT"] = $MEMBER_POINT;
      $point_log["USER_ID"] = $USER_ID;
      $point_log["MEMO"] = "캐시충전보너스";
      $point_log["STATUS"] = "P";

      F_T_POINT_LOG($point_log);
    }

  }

  # 관리자메모 추가
  function exec_order_admin_memo($SMS_MEMO, $CASH_NO, $USER_ID) {
    global $db;

    # 이미 관리자 메모가 존재하면 UPDATE, 아니면 INSERT
    $whereIs = " WHERE CASH_NO = ".$CASH_NO;
    $whereIs .= " AND USER_ID = '".$USER_ID."'";

    $query = " SELECT IDX FROM CASH_BANK_MEMO ";
    $query .= $whereIs;
    $tmp			=	$db->get_data($query);

    if ($tmp["IDX"]) {
      $query = " UPDATE CASH_BANK_MEMO SET MEMO = CONCAT(MEMO, '\n---------------------\n".$SMS_MEMO."') ";
      $query .= $whereIs;
      $query .= " AND IDX = ".$tmp["IDX"];
    } else {
      $query = " INSERT INTO CASH_BANK_MEMO (CASH_NO, USER_ID, MEMO) VALUES ('".$CASH_NO."', '".$USER_ID."', '".$SMS_MEMO."' ) ";
    }
    $db->query($query);
  }

  # 부족액 입금 내역 체크
  function get_sms_deposit($SMS_NAME, $TOTAL_PRICE, $SMS_DEPOSIT_PRICE, $USER_ID) {
    global $db;
    if ($SMS_NAME && $TOTAL_PRICE) {
      $query = " SELECT COUNT(*) AS CNT FROM CASH_SMS_LOG_SENDINFO ";
      $query .= " WHERE SMS_NAME = '".$SMS_NAME."' ";
      $query .= " AND SMS_TOTAL_PRICE = ".$TOTAL_PRICE;
      $query .= " AND SMS_DEPOSIT_PRICE = ".$SMS_DEPOSIT_PRICE;
      $query .= " AND SMS_USER_ID = '".$USER_ID."'" ;
      $query .= " AND REG_DATE >= date_add(now(), interval -2 hour)";
      $tmp			=	$db->get_data($query);
      return $tmp["CNT"];
    }
  }

  # 부족액 입금 발송 내역 저장
  function exec_sms_deposit($SMS_NAME, $TOTAL_PRICE, $SMS_DEPOSIT_PRICE, $USER_ID) {
    global $db;
    if ($SMS_NAME && $TOTAL_PRICE) {
      $Fields = " '".$SMS_NAME."', ".$TOTAL_PRICE.", ".$SMS_DEPOSIT_PRICE.", '".$USER_ID."' ";
      $query = " INSERT INTO CASH_SMS_LOG_SENDINFO (SMS_NAME, SMS_TOTAL_PRICE, SMS_DEPOSIT_PRICE, SMS_USER_ID) VALUES (".$Fields.") " ;
      $db->query($query);
    }
  }

?>
