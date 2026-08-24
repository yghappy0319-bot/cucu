<?php
/* =====================================
 * 공통 함수 정의
 *
 * =====================================*/
use Aws\Ses\SesClient;
use Aws\Exception\AwsException;

include_once $_SERVER["DOCUMENT_ROOT"]."/vendor/autoload.php";

// 기존의 mmaker DB 클래스 연결
if (!isset($db)) {
  $db = new MysqlDb($Database_Host, $Database_User, $Database_Password);
  // $db->MysqlDb($Database_Host, $Database_User, $Database_Password);
  $db->SELECTDb($Database_Name);
}

function sub_eregi($str, $data) {
  $return = preg_match('/'.$str.'/', $data);
  return $return;
}

function sub_eregiReplace($str, $replace_str, $data) {
  $return = preg_replace('/&page=[0-9]{1,}/', $replace_str, $data);
  return $return;
}

// 특정 파일을 불러들여 변수에 넣기
function file_load($File) { // 파일 불러오기
  $Fileload = fopen($File, 'r');
  $skin = fread($Fileload, filesize($File));
  fclose($Fileload);
  return $skin;
}

// 특정 파일로 쓰기
function file_write($File, $source) { // 파일 쓰기
  $Fileload = fopen($File, 'w');
  fwrite($Fileload, $source);
  fclose($Fileload);
  return $skin;
}

// 텍스트 길이 자르기 UTF 방식 ... 을 넣는다.
function str_utf_cut($str, $maxlen) {
  // 중국어 자르기용
  $Return_str = substr($str, 0, $maxlen);
    $cnt = 0;
    for ($i = 0; $i < strlen($Return_str); $i++) {
      if (ord($Return_str[$i]) > 127) {
        $cnt++;
      }
    }
    $Return_str = substr($Return_str, 0, $maxlen - ($cnt % 3));
  if ($maxlen < strlen($str)) {
    return $Return_str."...";
  } else {
    return $Return_str;
  }
}


// 텍스트 길이 자르기 UTF 방식 ... 을 넣는다.
function str_han_cut($str, $maxlen) {
  $Return_str = substr($str, 0, $maxlen);
    $cnt = 0;
    for ($i = 0; $i < strlen($Return_str); $i++) {
      if (ord($Return_str[$i]) > 127) {
        $cnt++;
      }
    }
    $Return_str = substr($Return_str, 0, $maxlen - ($cnt % 3));
  if ($maxlen < strlen($str)) {
    return $Return_str."...";
  } else {
    return $Return_str;
  }
}

// 텍스트 길이 자르기 euc_kr 방식 ... 을 넣지 않는다.
function str_han_cut2($str, $maxlen) {
  $len = strlen($str);
  if ($len <= $maxlen) return $str;

  for ($i = 0; $i < $len; $i++) {
    if (ord(substr($str, $i, 1)) < 128) {
      $Return_str .= substr($str, $i, 1);
    } else {
      $Return_str .= substr($str, $i, 2);
      $i++;
    }
    if (++$cnt >= $maxlen) break;
  }
  return $Return_str;
}

// 텍스트 길이 자르기 ver2
function cutString($string, $length, $suffix = '...') {
  // 멀티바이트 문자열 길이 체크
  if (mb_strlen($string, 'UTF-8') <= $length) {
      return $string; // 자를 필요가 없는 경우 원문 반환
  }

  // 지정된 길이만큼 자르고 접미사 추가
  return mb_substr($string, 0, $length, 'UTF-8') . $suffix;
}

// 배열로 option 태그를 만들어준다.
function option_make($array, $sel) { // 선택된 option 태그 만들기
  $str = "";
  while (@list($keys, $vals) = each($array)) {
    $str .= "<option value='".$keys."'";
    if ($keys == $sel) $str .= " SELECTED";
    $str .= ">".$vals."</option>\n";
  }
  return $str;
}

// CK에디터에서 특수문자 변환시 사용함
function ckedit($text) {
  $result = str_replace("&lt;", "<", $text);
  $result = str_replace("&gt;", ">", $result);
  return $result;
}

// js 함수 alert 자바스크립트
function alert_print($string) {
  echo('
    <script language="javascript">
      alert("'.$string.'");
    </script>
  ');
}

// js 함수 history.go()
function history_go() {
  echo ("
    <script language='javascript'>
    history.go(-1)
    </script>
  ");
  exit;
}

// js함수 경로 이동
function meta_go($url) {
  echo("
    <script language='javascript'>
      self.location.replace('$url');
    </script>
  ");
  exit;
}

// db에 넣기전 ' " 배열째로 addslashes 하기
function F_add_slashes($_L) { // 배열 addslashes
  $_RL = array();
  foreach($_L as $key => $val) {
    $_RL[$key] = $val;
  }
  return $_RL;
}

// db에 빼와서 ' " 배열째로 addslashes 하기
function F_strip_slashes($_L) { // 배열 stripslashes
  while (@list($keys, $vals) = each($_L)) {
    $_RL[$keys] = stripslashes($vals);
  }
  return $_RL;
}

// print_page_num() 페이지 번호 출력하는 함수
function print_page_num($page_info) {
  // print " $page_info["cur"], $page_info[row] , $page_info[total] ";
  // 현재 페이지, 페이지당 출력 수, 전체 레코드 수
  global $PHP_SELF, $PARAM, $QUERY_STRING;

  $page_string = "";
  $PARAM2 = sub_eregiReplace("&page=[0-9]{1,}", "", $QUERY_STRING);
  $PARAM2 = str_replace("&page=", "", $PARAM2);

  if (!isset($page_info["cur"])) $page_info["cur"] = 1;
  $page_row_t = intval($page_info["total"] / $page_info["row"]) + 1; // 전체페이지수
  if (($page_row_t - 1) == ($page_info["total"] / $page_info["row"])) $page_row_t--;

  // 10개씩 자른 페이지 중 현재 페이지가 속한 그룹의 첫번째 를 구하기 위함
  $page_10 = intval($page_info["cur"] / 5);
  if ($page_10 == ($page_info["cur"] / 5) ) $page_10--;
  $page_first = $page_10*5 + 1; // 현재페이지가 속한 10개중 첫번째 페이지
  $page_n = $page_first + 5; // 다음10개페이지(첫번째 페이지)
  $page_p = $page_first - 5; // 이전10개페이지(첫번째 페이지)

  $i = 1; // 순서 초기화

  $page_status = $page_first;
  while($page_status <= $page_row_t && $i <= 5) {
    if ($page_status < 5) {
      $num = $page_status;
    } else {
      $num = $page_status;
    }

    if ($page_info["cur"] == $page_status) {
      $page_string .= '
        <li class="page-item active">
          <a class="page-link" href="#">'.$num.'</a>
        </li>';
    } else { // 현재페이지인 경우
      $page_string .= '
        <li class="page-item">
          <a class="page-link" href="'.$PHP_SELF.'?'.$PARAM2.'&page='.$page_status.'">'.$num.'</a>
        </li>';
    }

    $i++;
    $page_status++;
  }

  if ($page_p <= 0) {
    $page_p = 1;
  }

  if ($page_n > $page_row_t) {
    $page_n = $page_row_t;
  }

  $page_s = $page_info["cur"] - 1;

  if ($page_s <= 0) {
    $page_s = 1;
  }

  $page_e = $page_info["cur"] + 1;

  if ($page_e > $page_row_t) {
    $page_e = $page_row_t;
  }

  // 위의 페이지 수와 이전 10페이지 이후 10페이지를 판단하여 붙인다.
  $page_string = '
    <li class="page-item">
      <a aria-label="Next" class="page-link" href="'.$PHP_SELF.'?'.$PARAM2.'&page='.$page_s.'"><i class="fa fa-angle-left"></i></a>
    </li>'.$page_string;

  $page_string = '
    <li class="page-item">
      <a aria-label="Last" class="page-link" href="'.$PHP_SELF.'?'.$PARAM2.'&page='.$page_p.'"><i class="fa fa-angle-double-left"></i></a>
    </li>'.$page_string;

  $page_string = $page_string.'
    <li class="page-item">
      <a aria-label="Next" class="page-link" href="'.$PHP_SELF.'?'.$PARAM2.'&page='.$page_e.'"><i class="fa fa-angle-right"></i></a>
    </li>';

  $page_string = $page_string.'
    <li class="page-item">
      <a aria-label="Last" class="page-link" href="'.$PHP_SELF.'?'.$PARAM2.'&page='.$page_n.'"><i class="fa fa-angle-double-right"></i></a>
    </li>';

  return '<ul class="pagination justify-content-center">'.$page_string.'</ul>';
}

// print_page_num() 페이지 번호 출력하는 함수
function print_page_num1($page_info) {
  // print " $page_info["cur"], $page_info[row] , $page_info[total] ";
  // 현재 페이지, 페이지당 출력 수, 전체 레코드 수

  global $PHP_SELF, $PARAM, $QUERY_STRING;

  $PARAM2 = sub_eregiReplace("&page=[0-9]{1,}", "", $QUERY_STRING);
  $PARAM2 = str_replace("&page=", "", $PARAM2);

  if (!$page_info["cur"]) $page_info["cur"] = 1;
  $page_row_t = intval($page_info["total"] / $page_info["row"]) + 1; // 전체페이지수
  if (($page_row_t - 1) == ($page_info["total"] / $page_info["row"])) $page_row_t--;

  // 10개씩 자른 페이지 중 현재 페이지가 속한 그룹의 첫번째를 구하기 위함
  $page_10 = intval($page_info["cur"] / 5);
  if ($page_10 == ($page_info["cur"] / 5)) $page_10--;
  $page_first = $page_10 * 5 + 1; // 현재 페이지가 속한 10개중 첫번째 페이지
  $page_n = $page_first + 5; // 다음10개페이지(첫번째 페이지)
  $page_p = $page_first - 5; // 이전10개페이지(첫번째 페이지)

  $i = 1; // 순서 초기화

  $page_status = $page_first;

  while($page_status <= $page_row_t && $i <= 5) {
    if ($page_status < 5) {
      $num = $page_status;
    } else {
      $num = $page_status;
    }

    if ($page_info["cur"] == $page_status) {
      $page_string .= "<li class='on'><a>".$num."</a></li>";
    } else { // 현재페이지인 경우
      $page_string .= "<li><a href='$PHP_SELF?$PARAM2&page=$page_status$PARAM'>".$num."</a></li>";
    }

    $i++;
    $page_status++;
  }

  if ($page_p <= 0) {
    $page_p = 1;
  }

  if ($page_n > $page_row_t) {
    $page_n = $page_row_t;
  }

  $page_s = $page_info["cur"] - 1;

  if ($page_s <= 0) {
    $page_s = 1;
  }

  $page_e = $page_info["cur"] + 1;

  if ($page_e > $page_row_t) {
    $page_e = $page_row_t;
  }

  $page_string = "<ol class=''>".$page_string."</ol>";

  // 위의 페이지 수와 이전 10페이지 이후 10페이지를 판단하여 붙인다.
  $page_string = "<a href='$PHP_SELF?$PARAM2&page=$page_s$PARAM' class='prev'><span class='hide'>게시판이전페이지</span></a>".$page_string;
  $page_string = "<a href='$PHP_SELF?$PARAM2&page=$page_e$PARAM' class='next'><span class='hide'>게시판다음페이지</span></a>".$page_string;

  return $page_string;
}

// 메일발송함수 - 일반방식
function mailer($fname, $fmail, $to, $subject, $content, $type, $mname, $mno, $tno, $file="", $charset="UTF-8", $cc="", $bcc="") {
  // type : text=0, html=1, text+html=2
  $type = 2;
  $content = str_replace("/upload/nse/", "/contents/cs/imgsvc.html?u=", $content);

  $title   = $subject;
  $fname   = "=?$charset?B?" . base64_encode($fname) . "?=";
  $subject = "=?$charset?B?" . base64_encode($subject) . "?=";
  $charset = ($charset != "") ? "charset=$charset" : "";

  $header  = "Return-Path: <".$fmail.">\n";
  $header .= "From: $fname <".$fmail.">\n";
  $header .= "Reply-To: <".$fmail.">\n";

  if ($cc)  $header .= "Cc: ".$cc."\n";
  if ($bcc) $header .= "Bcc: ".$bcc."\n";

  $header .= "MIME-Version: 1.0\n";
  $header .= "X-Mailer: SiR Mailer 1.0\n";

  if ($type) {
    $header .= "Content-Type:text/html; ".$charset."\n";
    if ($type == 2) {
      $content = stripslashes($content);
      $subject = stripslashes($subject);
    }
  } else {
    $header .= "Content-Type:TEXT/PLAIN; ".$charset."\n";
    $content = stripslashes($content);
  }

  $header.="\n";

  $mailResult = mail($to, $subject, $content, $header);

  if ($mailResult) {
    $is_type = "Y";
  } else {
    $is_type = "N";
  }

  $mail_log = array();
  $mail_log["TOEMAIL"]     = $to;
  $mail_log["TEMPLET_NO"]  = $tno;
  $mail_log["MEMBER_NO"]   = $mno;
  $mail_log["MEMBER_NAME"] = $mname;
  $mail_log["SUBJECT"]     = $title;
  $mail_log["CONTENT"]     = $content;
  $mail_log["STATUS"]      = $is_type;
  F_mail_log($mail_log);
}

// 메일 발송후 발송로그
function F_mail_log($_L) {
  global $db;

  $_L['CONTENT'] = addslashes($_L['CONTENT']);
  $db->query("INSERT INTO EMAILSENDLOG (TOEMAIL, TEMPLET_NO, MEMBER_NO, MEMBER_NAME, SUBJECT, CONTENT, STATUS, REG_DATE) VALUES ('".$_L['TOEMAIL']."','".$_L['TEMPLET_NO']."','".$_L['MEMBER_NO']."','".$_L['MEMBER_NAME']."','".$_L['SUBJECT']."','".$_L['CONTENT']."','".$_L['STATUS']."',NOW());");
}

$customer_code = "999999";
$customer_pw   = "";

// 문자발송 함수
function F_SMS($to, $from, $msg) {
  global $HTTP_HOST, $customer_code, $customer_pw;
  $to2       = $to;
  $from2     = $from;
  $host_name = str_replace("www.", "", $HTTP_HOST);
  $to        = sub_eregiReplace("[^0-9]", "", $to);
  $from      = sub_eregiReplace("[^0-9]", "", $from);
  $to        = chop($to);
  $from      = chop($from);
  $msg       = iconv("UTF-8", "EUC-KR", $msg);
  $msg       = substr($msg, 0, 89);
  // if (strlen($msg) > 80) {
  //  F_LMS($to, $from, $msg); // 장문발송 - 현재 미사용중입니다.
  //  return;
  // }
  $msg       = substr($msg, 0, 89);
  $DEST_INFO = $customer_code."^".$to;
  $microtime = microtime().rand(11111,99999);
  $ext       = explode(" ", $microtime);
  $RESERVED1 = $ext[1].$ext[0]*100000000;

  // 로그데이터 입력용
  // 주의 : 아래의 코드를 조작하여  SMS를 무단으로 사용하는 경우를 점검하기 위하여
  // 정기적으로 총구매건수, 총사용건수, 총잔여건수 를 확인하고 있습니다.
  // 의심되는 부분이 발견되는 경우에는
  // 무단사용에 대한 책임 및 모든 강제수단을 강구할 예정이오니
  // 임의로 SMS 발송 코드를 조작하는 일은 없도록 주의해 주시기 바랍니다.
  $cnt_db = mysqli_connect('ez.skihouse.co.kr', 'npro', 'npro');
  mysqli_SELECT_db('npro');

  $row = mysqli_fetch_array(mysqli_query("SELECT sms FROM customer WHERE no = '$customer_code' AND pass = '$customer_pw' ", $cnt_db));
  if ($row[0] < 1) {
    alert_print("SMS 오류: 사용불가능한 코드이거나 SMS 사용이 소진되었습니다. 충전후(신용카드결제만 가능) 사용하세요");
    alert_print("충전하실때는 http://sms.naeils.co.kr 에서 실시간으로 충전하실수 있습니다.");
    // echo "<script>top.location.replace('http://sms.naeils.co.kr/sms_company.html?customer_code=$customer_code');</script>";
    exit;
  }
  $update_query = "UPDATE customer SET sms = sms - 1, sms_use = sms_use + 1 WHERE no = '$customer_code'";
  $result2 = mysqli_query($update_query, $cnt_db);

  // 개별 발송의 경우입니다.
    $insert_query = "
      INSERT INTO MSG_DATA (
        CUR_STATE,
        REQ_DATE,
        CALL_TO,
        CALL_FROM,
        SMS_TXT,
        MSG_TYPE,
        domain
      ) VALUES (
        '0',
        null,
        '".$to."',
        '".$from."',
        '".$msg."',
        '4',
        '".$host_name."'
      )
    ";
    $result = mysqli_query($insert_query, $cnt_db);

    F_sms_log(array(
      "mode"     => 'insert',
      "name"     => $name,
      "from_num" => $from,
      "to_num"   => $to,
      "msg"      => $msg
    ));

  return $result;
}

// 문자발송로그
function F_sms_log($_L) {
  global $db;
  $_L['msg'] = iconv("EUC-KR", "UTF-8", $_L['msg']);

  if ($_L['mode'] == 'read') {
    $info = $db->get_data("SELECT * FROM SMSSENDLOG WHERE LOG_NO = '".$_L['no']."'");
    $info['msg'] = stripslashes($info['msg']);
    return  $info;
  }

  $_L['msg'] = addslashes($_L['msg']);
  if ($_L['mode'] == 'insert') {
    $query = "
      INSERT INTO SMSSENDLOG(
        CODESK,
        TOHP,
        TEMPLET_NO,
        MEMBER_NO,
        MEMBER_NAME,
        SUBJECT,
        SENDMSG,
        REG_DATE
      ) VALUES (
        '".$_L['CODESK']."',
        '".$_L['TOHP']."',
        '".$_L['TEMPLET_NO']."',
        '".$_L['MEMBER_NO']."',
        '".$_L['MEMBER_NAME']."',
        '".$_L['SUBJECT']."',
        '".$_L['SENDMSG']."',
        NOW()
      )
    ";
  }
  if ($_L['mode'] == 'delete') {
    $query = "DELETE FROM SMSSENDLOG WHERE LOG_NO = '".$_L['no']."'";
  }

  $db->query($query);
}

// 무질서 랜덤
function str_rand($length = 4, $char = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ') {
  if (!is_int($length) || $length < 0) {
    return false;
  }

  $string = '';
  for ($i = $length; $i > 0; $i--) {
    $string .= $char[mt_rand(0, strlen($char) - 1)];
  }
  return $string;
}

// 모바일여부체크
function isMobile() {
  if (isset($_SERVER['HTTP_X_WAP_PROFILE'])) {
    return false;
  }

  if (isset($_SERVER['HTTP_VIA'])) {
    return stristr($_SERVER['HTTP_VIA'], "wap") ? true : false;
  }

  if (isset($_SERVER['HTTP_USER_AGENT'])) {
    $clientkeywords = array ('nokia', 'sony', 'ericsson', 'mot', 'samsung', 'htc', 'sgh', 'lg', 'sharp', 'sie-',
      'philips', 'panasonic', 'alcatel', 'lenovo', 'iphone', 'ipod', 'blackberry','meizu', 'android', 'netfront',
      'symbian', 'ucweb', 'windowsce', 'palm', 'operamini', 'operamobi', 'openwave', 'nexusone', 'cldc', 'midp', 'wap', 'mobile'
    );

    if (preg_match("/google/i", strtolower($_SERVER['HTTP_USER_AGENT']))) { // 구글봇은 302 사용안함 (Googlebot, Googlebot-Mobile)
      return false;
    }

    if (preg_match("/(" . implode('|', $clientkeywords) . ")/i", strtolower($_SERVER['HTTP_USER_AGENT']))) {
      return true;
    }
  }

  if (isset($_SERVER['HTTP_ACCEPT'])) {
    if ((strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') !== false) && (strpos($_SERVER['HTTP_ACCEPT'], 'text/html') === false || (strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') < strpos($_SERVER['HTTP_ACCEPT'], 'text/html')))) {
      return true;
    }
  }
  return false;
}

function getLaTime($dateTimeLa=null) {
  if (empty($dateTimeLa) == true) {
    $DateTime    = new DateTime("now", new DateTimeZone('America/Los_Angeles'));
    $getDateTime = $DateTime->format('Y-m-d H:i:s');
    $timeLa      = strtotime($getDateTime);
    $korTime     = time();
  } else {
    $DateTimeKor = new DateTimeZone('Asia/Seoul');
    $DateTime    = new DateTime($dateTimeLa, new DateTimeZone('America/Los_Angeles'));
    $DateTime->setTimezone($DateTimeKor);

    $getDateTime = $DateTime->format('Y-m-d H:i:s');
    $timeLa      = strtotime($dateTimeLa);
    $korTime     = strtotime($getDateTime);
  }

  $timeDiff = floor(($korTime - $timeLa) / 3600);

  if ($timeDiff < 17 ) $summerYN  = 'Y';
  else $summerYN = 'N';

  $rtns['laTime']      = $timeLa;
  $rtns['summerYN']    = $summerYN;
  $rtns['timeDiff']    = $timeDiff;
  $rtns['korTime']     = $korTime;
  $rtns['laDateTime']  = date('Y-m-d H:i:s', $timeLa);
  $rtns['korDateTime'] = date('Y-m-d H:i:s', $korTime);

  return  $rtns;
}


// 회차타임 및 당청금 얻기
function timesss($codeLK, $pdate = '') {
  global $db, $price_est_per, $draw_time, $draw_time_num, $deadline_time, $PB_reset_price, $MM_reset_price;
  include_once $_SERVER['DOCUMENT_ROOT']."/_library/function_WININFO.php";

  $laTimes = getLaTime();
  $laTime  = $laTimes['laTime'];

  if ($pdate != '') {
    $laTime = strtotime($pdate);
  }

  $endDateTime  = date("Y-m-d H:i:s");
  $playDate     = date('Y-m-d', $laTime);
  $playDateTime = date('Y-m-d H:i:s', $laTime);

  $week_op = array(
    '0'=>'일',
    '1'=>'월',
    '2'=>'화',
    '3'=>'수',
    '4'=>'목',
    '5'=>'금',
    '6'=>'토',
  );

  $info = $db->get_data("SELECT * FROM WININFO WHERE GUBUN='".$codeLK."' AND PLAYDATE >= '".$playDate."' AND TIME_E > '".$endDateTime."' LIMIT 1");

  $koTimes = getLaTime($info['TIME_S']);
  $koTime  = $koTimes['korTime'];
  $day     = date("Y.m.d", strtotime($info['TIME_S']));
  $weeke   = date("w", strtotime($info['TIME_S']));
  $week    = $week_op[$weeke];
  $time    = date("A g:i", strtotime($info['TIME_S']));
  $date    = $day." (".$week.") ".$time;
  $daye    = date("m", $koTime)."월".date("d", $koTime)."일";
  $weekee  = date("w", $koTime);
  $weeks   = $week_op[$weekee];
  $times   = "AM".date(" g:i", $koTime);

  // 썸머타임 적용 시작 3월 14일 부터
  if ($times == 'AM 11:59') {
    $times = '낮 12시';
  }
  if ($times == 'AM 12:00') {
    $times = '낮 12시';
  }
  if ($times == 'PM 12:00') {
    $times = '낮 12시';
  }
  // 섬머타임 적용 끝 11월 7일까지

  // 일반타임
  if ($times == 'AM 12:59') {
    $times = '오후 1시';
  }
  if ($times == 'AM 1:00') {
    $times = '오후 1시';
  }
  // 일반타임

  $datee     = $daye." ".$weeks."요일 ".$times;
  $play_date = strtotime($info['TIME_S']);
  $end_time  = strtotime($info['TIME_E']);
  $play_day  = $info['PLAYDATE'];
  $ee_week   = date("w", $end_time);
  $e_week    = $week_op[$ee_week];
  $e_date    = date("m월 d일 ", $end_time).$e_week."요일 ".$draw_time;
  $es_date   = date("m월 d일 ", $end_time).$e_week."요일 ".$deadline_time;
  $eo_date   = date("Y/m/d", $end_time)." ".$deadline_time.":00";

  // 예상 당첨금 업데이트 유무로 컨트롤
  $nomal_style    = "block"; // 기본 안내 페이지 출력유무
  $deadline_style = "none"; // 마감후 안내 페이지 출력유무
  $draw_now_date  = date("Y년 m월 d일", strtotime($endDateTime))." ".($draw_time_num+2)."시"; // 당첨자 확정 공지 일시

  if ($info['PRIZ1'] == 0) { // 예상 당첨금 업데이트 전까지 출력
    $nomal_style    = "none";
    $deadline_style = "block";
  }

  $ss_week = date("w", $play_date);
  $s_week  = $week_op[$ss_week];
  $s_date  = date("m월 d일 ", $play_date).$s_week."요일 ".date("H:i", $play_date);

  $begin_time = strtotime("now");

  $timediff = 0;
  if ($begin_time < $end_time) {
    $timediff = ($end_time - $begin_time) * 1000;
  }

  $price = $info['PRIZ1'];

  if (!$price) {
    $sql   = "SELECT PRIZ1 FROM WININFO WHERE GUBUN='".$codeLK."' AND PLAYDATE <= '".$playDate."' AND PRIZ1 > 0 ORDER BY PLAYDATE DESC LIMIT 1";
    $price = $db->get_data_one($sql);
  }

  if (!$price) {
    $price = 0;
  }

  // 환율
  $won = $db->get_data("SELECT * FROM EXCHANGE WHERE DATE = '".date("Y-m-d")."' LIMIT 1");

  if (!isset($won['WON'])) {
    $won = $db->get_data("SELECT * FROM EXCHANGE WHERE 1 ORDER BY DATE DESC LIMIT 1");
  }

  $priceDollar = $price;
  $price = $price * $won['WON'];

  // 당첨금이 3천억 이상인 경우 - 예상 금액 비율 조정 (15% > 8%)
  $expectedWinnings = round($priceDollar * $won['WON'], -6);
  if ($expectedWinnings >= 300000000000) {
    $price_est_per = 1.08;
  }

  $price_est = round($priceDollar * $price_est_per * $won['WON'], -6);
  $price_fst = round(${$codeLK."_reset_price"}[1] * $won['WON'], -6);

  if ($price != '') {
    $price     = number_format($price);
    $price_est = number_format($price_est);
    $price_fst = number_format($price_fst);
  }

  $gameNo = $info['DRAWNUM'];
  $gameNo_prev = $gameNo - 1;

  $time_set = array(
    "play_date"      => $date,
    "play_k_date"    => $datee,
    "gameNo"         => $gameNo,
    "price"          => $price,
    "s_date"         => $s_date,
    "e_date"         => $e_date,
    "es_date"        => $es_date,
    "eo_date"        => $eo_date,
    "priceDollar"    => $priceDollar,
    "timediff"       => $timediff,
    "remain"         => $end_time - time(),
    "price_est"      => $price_est,
    "price_fst"      => $price_fst,
    "gameNo_prev"    => $gameNo_prev,
    "nomal_style"    => $nomal_style,
    "deadline_style" => $deadline_style,
    "draw_now_date"  => $draw_now_date,
    "won_rate"       => $won['WON']
  );
  return $time_set;
}

// 파워볼 자동선택
function num_range2($num, $type="") {
  global $db;
  if ($type != "A") {
    // 직전회차 당첨번호 가져오기
    $info = $db->get_data("
      SELECT BALL1, BALL2, BALL3, BALL4, BALL5, BALLP
      FROM WININFO
      WHERE GUBUN = 'PB' AND BALL1 != '' AND BALL2 != ''
      ORDER BY PLAYDATE DESC
      LIMIT 1
    ");
    $win_five_number = array($info['BALL1'], $info['BALL2'], $info['BALL3'], $info['BALL4'], $info['BALL5']);
    $win_one_number  = array($info['BALLP']);
  }
  $numbers = array();
  for ($i = 0; $i < $num; $i++) {
    if ($type == "A2") {
      $five_number = array_diff(range(1, 69), $win_five_number);
      $five_number = array_values($five_number);
      $one_number  = array_diff(range(1, 26), $win_one_number);
      $one_number  = array_values($one_number);
    } else if ($type == "A3") {
      $five_number = range(1, 69);
      $one_number  = array_diff(range(1, 26), $win_one_number);
      $one_number  = array_values($one_number);
    } else {
      $five_number = range(1, 69);
      $one_number  = range(1, 26);
    }
    // 화이트볼 선택
    shuffle($five_number);
    $number1 = array_slice($five_number, 0, 5);
    sort($number1);
    // 파워볼 선택
    shuffle($one_number);
    $number2 = array_slice($one_number, 0, 1);
    $numbers[$i] = $number1[0].",".$number1[1].",".$number1[2].",".$number1[3].",".$number1[4].",".$number2[0];
  }

  // 중복 체크를 위한 배열에서 중복 제거
  $chk_arr = array_unique($numbers);

  if (count($numbers) == count($chk_arr)) {
    return $numbers;
  } else {
    num_range2($num);
  }
}

// 메가볼 자동선택
function num_range3($num, $type="") {
  global $db;
  if ($type != "A") {
    // 직전회차 당첨번호 가져오기
    $info = $db->get_data("
      SELECT BALL1, BALL2, BALL3, BALL4, BALL5, BALLP
      FROM WININFO
      WHERE GUBUN = 'MM' AND BALL1 != '' AND BALL2 != ''
      ORDER BY PLAYDATE DESC
      LIMIT 1
    ");
    $win_five_number = array($info['BALL1'], $info['BALL2'], $info['BALL3'], $info['BALL4'], $info['BALL5']);
    $win_one_number  = array($info['BALLP']);
  }
  $numbers = array();
  for ($i = 0; $i < $num; $i++) {
    if ($type == "A2") {
      $five_number = array_diff(range(1, 70), $win_five_number);
      $five_number = array_values($five_number);
      $one_number  = array_diff(range(1, 25), $win_one_number);
      $one_number  = array_values($one_number);
    } else if ($type == "A3") {
      $five_number = range(1, 70);
      $one_number  = array_diff(range(1, 25), $win_one_number);
      $one_number  = array_values($one_number);
    } else {
      $five_number = range(1, 70);
      $one_number  = range(1, 25);
    }
    // 화이트볼 선택
    shuffle($five_number);
    $number1 = array_slice($five_number, 0, 5);
    sort($number1);
    // 메가볼 선택
    shuffle($one_number);
    $number2 = array_slice($one_number, 0, 1);
    $numbers[$i] = $number1[0].",".$number1[1].",".$number1[2].",".$number1[3].",".$number1[4].",".$number2[0];
  }

  // 중복체크를 위한 배열에서 중복 제거
  $chk_arr = array_unique($numbers);

  if (count($numbers) == count($chk_arr)) {
    return $numbers;
  } else {
    num_range3($num);
  }
}

/*
  문자발송
    뿌리오 추가
    모아샷 추가 (2023-02-21)
    문자노리 추가 SLK-697
*/
function curl_sms($to, $from, $CODESK, $TEMPLET_NO, $MEMBER_NO, $MEMBER_NAME, $SUBJECT, $SENDMSG, $msg_type="SMS") {
  global $HTTP_HOST, $db, $sms_sender;

  $from = str_replace("-", "", $from);
  $to   = str_replace("-", "", $to);

  $name = $to;
  $testmode_yn = "";


  if ($MEMBER_NO != '') {
    $mem  = $db->get_data("SELECT * FROM MEMBER WHERE MEMBER_NO='".$MEMBER_NO."'");
    $name = $mem["NAME"];
  }

  if ($TEMPLET_NO != '') {
    // 템플릿이 중지일경우 체크 SLK-595
    $templet = $db->get_data("SELECT COUNT(*) AS CNT FROM SMSTEMPLET WHERE TEMPLET_NO='".$TEMPLET_NO."' AND STOPYN = 'Y'");

    if ($templet['CNT'] > 0) {
      $retArr = json_decode('{"result_code":"2"}');
      return $retArr;
      exit;
    }
  }

  // 문자노리
  if ($sms_sender == "smsnori") {
    require_once $_SERVER['DOCUMENT_ROOT']."/_library/MotHead.lib.php";

    $MH_rd = array();
    $MH_rd['U_CODE']      = ""; // 발급받은 키 사이트의 기업연동->연동하기를 통해 발급받으세요.
    $MH_rd['U_FROM_NUM']  = $from; // 발신자번호
    $MH_rd['U_TO_NUM']    = $to; // 받는사람번호 (여러개 인 경우 ','로 구분) 최대 100개
    $MH_rd['U_SUBJECT']   = $SUBJECT; // LMS, MMS 일때 문자 제목
    $MH_rd['U_MSG']       = stripslashes($SENDMSG); // 문자내용

    $MH_rd['U_SEND_DATE'] = ""; // 발송예약일 현재시각 기준 30분 이후로 설정 가능 (Beta)
    // $MH_rd['U_TYPE']      = 1; // 문자 종류 1:단문 2:장문
    $MH_rd['U_TYPE']      = (strlen($MH_rd['U_MSG']) > 90 || $msg_type == 'LMS') ? 2 : 1;
    $MH_ed['U_VAL']       = ""; // 사용자 임의 변수 U_VAL 로 받을수 있음

    $MotHead = new MotHead_Send();
    $S_SR = $MotHead->Send($MH_rd);

    $S_status = $S_SR['status']; // 서버통신 성공여부 true , false
    $S_code   = $S_SR['code'];   // 해당 요청건의 고유코드 (Report 받을시 사용)
    $S_result = $S_SR['result']; // 0:실패 1:성공
    $S_msg    = $S_SR['msg'];    // 에러메세지
    $S_count  = $S_SR['count'];  // 전송 요청 건수 -중복 자동 제거됨
    $S_u_val  = $S_SR['U_VAL'];  // 사용자 임의변수

    // return 값을 알리고 형태로 변환
    $retArr = json_decode('{"result_code":"'.($S_result==1 ? $S_result : $S_msg).'"}');

  // 모아샷
  } else if ($sms_sender =="moashot") {
    /* 사용자 인증정보 */
    $sms_url = "http://biz.moashot.com/EXT/URLASP/mssendUTF.asp"; // 전송요청 URL
    $sms['uid'] = "suloko"; // SMS 아이디
    //$sms['pwd'] = "suloko7952@@";
    $sms['pwd'] = "tnfhzh8787@";

    /* 발송 내용 */
    $sms['contents']   = stripslashes($SENDMSG);
    $sms['toNumber']   = $to; // 수신번호
    $sms['fromNumber'] = $from; // 발신번호
    $sms['title']      = $SUBJECT;
    $sms['returnType'] = 3;

    /* sendType
    · 3 : 단문문자(SMS)
    · 5 : 장문문자(LMS)
    · 6 : 이미지를 포함한 문자(MMS)
    */
    $len = strlen($sms['contents']);
    if ($len > 90 || $msg_type == 'LMS') {
      $sms['sendType']  =  "5";
    } else {
      $sms['sendType']  =  "3";
    }

    /* post request */
    $postdata = http_build_query($sms);
    $opts = array('http' =>
      array(
        'method' => 'POST',
        'header' => 'Content-type: application/x-www-form-urlencoded',
        'content' => $postdata
      )
    );
    $context = stream_context_create($opts);
    $result  = file_get_contents($sms_url, false, $context);

    // return 값을 알리고 형태로 변환
    $retArr = json_decode('{"result_code":"'.($result == "SUCCESS:" ? "1" : "Err").'"}');

  // 뿌리오
  } else if ($sms_sender == "ppurio") {
    $_api_url = 'https://message.ppurio.com/api/send_utf8_json.php'; // UTF-8 인코딩과 JSON 응답용 호출 페이지

    $_param['userid']   = '';                 // [필수] 뿌리오 아이디
    $_param['callback'] = $from;                    // [필수] 발신번호 - 숫자만
    $_param['phone']    = $to;                      // [필수] 수신번호 - 여러명일 경우 |로 구분 '010********|010********|010********'
    $_param['msg']      = stripslashes($SENDMSG);   // [필수] 문자내용 - 이름(names)값이 있다면 [*이름*]가 치환되서 발송됨
    // $_param['names'] = '홍길동';                    // [선택] 이름 - 여러명일 경우 |로 구분 '홍길동|이순신|김철수' -> 내용에 치환된다.
    // $_param['appdate'] = '20190502093000';        // [선택] 예약발송 (현재시간 기준 10분이후 예약가능)
    $_param['subject']  = $SUBJECT;                 // [선택] 제목 (30byte)
    // $_param['file1'] = '@이미지파일경로;type=image/jpg'; // [선택] 포토발송 (jpg, jpeg만 지원  300 K  이하)

    $_curl = curl_init();
    curl_setopt($_curl, CURLOPT_URL, $_api_url);
    curl_setopt($_curl, CURLOPT_POST, true);
    curl_setopt($_curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($_curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($_curl, CURLOPT_POSTFIELDS, $_param);
    $_result = curl_exec($_curl);
    curl_close($_curl);

    $_result = json_decode($_result);

    // return 값을 알리고 형태로 변환
    $retArr = json_decode('{"result_code":"'.($_result->result=="ok" ? "1" : $_result->result).'"}');

  // 기본 - 알리고
  } else {
    $sms_url            = "https://apis.aligo.in/send/"; // 전송요청 URL
    $sms['user_id']     = ""; // SMS 아이디
    $sms['key']         = ""; // 인증키
    $sms['msg']         = stripslashes($SENDMSG);
    $sms['receiver']    = $to; // 수신번호
    $sms['destination'] = $name; // 수신자
    $sms['sender']      = $from; // 발신번호
    $sms['rdate']       = $rdate; // 예약일자
    $sms['rtime']       = $rtime; // 예약시간
    $sms['testmode_yn'] = empty($testmode_yn) ? '' : $testmode_yn;
    $sms['title']       = $SUBJECT;
    $sms['msg_type']    = $msg_type; // SMS, LMS, MMS등 메세지 타입을 지정

    $len = strlen($sms['msg']);
    if ($len > 120 || $msg_type == 'LMS') {
      $sms['msg_type'] = "LMS";
    } else {
      $sms['msg_type'] = "SMS";
    }

    $host_info = explode("/", $sms_url);
    $port = $host_info[0] == 'https:' ? 443 : 80;

    $oCurl = curl_init();
    curl_setopt($oCurl, CURLOPT_PORT, $port);
    curl_setopt($oCurl, CURLOPT_URL, $sms_url);
    curl_setopt($oCurl, CURLOPT_POST, 1);
    curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($oCurl, CURLOPT_POSTFIELDS, $sms);
    curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, FALSE);
    $ret = curl_exec($oCurl);
    curl_close($oCurl);

    $retArr = json_decode($ret); // 결과배열
  }

  F_sms_log(array(
    "mode"        => 'insert',
    "CODESK"      => $CODESK,
    "TOHP"        => $to,
    "TEMPLET_NO"  => $TEMPLET_NO,
    "MEMBER_NO"   => $MEMBER_NO,
    "MEMBER_NAME" => $MEMBER_NAME,
    "SUBJECT"     => $SUBJECT,
    "SENDMSG"     => $SENDMSG
  ));
  return $retArr;
}

function isAdult($birth_year, $birth_month, $birth_day) {
  $today_year  = date("Y");
  $today_month = date("m");
  $today_day   = date("d");
  $adult       = 1; // 성인

  if ($birth_year + 19 <= $today_year) {
    if ($birth_year + 19 == $today_year) {
      if ($birth_month <= $today_month) {
        if ($birth_month == $today_month) {
          $adult = $birth_day > $today_day ? 0 : 1;
        } else {
          $adult = 0;
        }
      }
    }
  } else {
    $adult = 0;
  }
  return $adult == 0 ? FALSE : TRUE;
}

// 숫자에 조 단위 추가
function getNumberStringKorean($num_value) {
  // 1 ~ 9 한글 표시
  $arrNumberWord = array("0", "1", "2", "3", "4", "5", "6", "7", "8", "9");
  // 10, 100, 100 자리수 한글 표시
  $arrDigitWord = array("", "", "", ",");
  // 만단위 한글 표시
  $arrManWord = array("억", "조", "");

  $num_length = strlen($num_value);
  $han_value  = "";
  $man_count  = 0; // 만단위 0이 아닌 금액 카운트.

  if ($num_length > 4) {
    for ($i = 0; $i < $num_length; $i++) {
      // 1단위의 문자로 표시.. (0은 제외)
      $strTextWord = $arrNumberWord[substr($num_value, $i, 1)];
      // 0이 아닌경우만, 십/백/천 표시
      if ($strTextWord != "") {
        $man_count++;
        $strTextWord = $strTextWord . $arrDigitWord[($num_length - ($i+1)) % 4];
      }

      // 만단위마다 표시 (0인경우에도 만단위는 표시한다)
      if ($man_count != 0 && ($num_length - ($i+1)) % 4 == 0) {
        $man_count = 0;
        $strTextWord = $strTextWord . $arrManWord[($num_length - ($i+1)) / 4];
      }

      if ($han_value != '' && $strTextWord == 0) {
        $end = explode("조", $han_value);
        $end = end($end);
      } else {
        $end = 1;
      }

      if ($strTextWord != '0,' && $end != '') {
        $han_value = $han_value . $strTextWord;
      }
    }
  } else {
    $han_value = number_format($num_value)."억";
  }

  if ($num_value != 0) {
    $han_value . "";
  }

  return $han_value;
}

function fix_json_encode() {
  function json_encode($var) {
    switch (gettype($var)) {
      case 'boolean':
        return $var ? 'true' : 'false'; // Lowercase necessary!
      case 'integer':
      case 'double':
        return $var;
      case 'resource':
      case 'string':
        return '"'. str_replace(array("\r", "\n", "<", ">", "&"),
          array('\r', '\n', '\x3c', '\x3e', '\x26'),
          addslashes($var)) .'"';
      case 'array':
        // Arrays in JSON can't be associative. If the array is empty or if it
        // has sequential whole number keys starting with 0, it's not associative
        // so we can go ahead and convert it as an array.
        if (empty ($var) || array_keys($var) === range(0, sizeof($var) - 1)) {
            $output = array();
            foreach ($var as $v) {
                $output[] = json_encode($v);
            }
            return '[ '. implode(', ', $output) .' ]';
        }
        // Otherwise, fall through to convert the array as an object.
      case 'object':
        $output = array();
        foreach ($var as $k => $v) {
            $output[] = json_encode(strval($k)) .': '. json_encode($v);
        }
        return '{ '. implode(', ', $output) .' }';
      default:
        return 'null';
    }
  }

  function json_decode($json, $assoc = true) {
    $comment = false;
    $out     = '$x=';
    $json = preg_replace('/:([^"}]+?)([,|}])/i', ':"\1″\2', $json);
    for ($i = 0; $i < strlen($json); $i++) {
      if (!$comment) {
        if (($json[$i] == '{') || ($json[$i] == '[')) {
          $out .= 'array(';
        } else if (($json[$i] == '}') || ($json[$i] == ']')) {
          $out .= ')';
        } else if ($json[$i] == ':') {
          $out .= '=>';
        } else if ($json[$i] == ',') {
          $out .= ',';
        } else if ($json[$i] == '"') {
          $out .= '"';
        }
      } else {
        $out .= $json[$i] == '$' ? '\$' : $json[$i];
      }

      if ($json[$i] == '"' && $json[($i-1)] != '\\') {
        $comment = !$comment;
      }
    }
    eval($out. ';');
    return $x;
  }
}

function fix_session_register() {
  function session_register() {
    $args = func_get_args();
    foreach ($args as $key) {
      $_SESSION[$key] = $GLOBALS[$key];
    }
  }
  function session_is_registered($key) {
    return isset($_SESSION[$key]);
  }
  function session_unregister($key) {
    unset($_SESSION[$key]);
  }
}

// 추첨결과
function win_tp($is_type, $playdate, $ballP, $prizcnt9, $draw_time_num) {
  $now    = date("Y-m-d", strtotime("-1 day"));
  $n_time = date("H");

  // array(당첨번호 리스트, 당첨번호상단, 메인하단, 동그라미 스타일이름)
  $win = array("<span class='color_red3'>이월</span>","당첨</br>이월","당첨</br>이월","label_red");

  if ($now == $playdate) { // 추첨일 당일일 경우
    if ($ballP =='') { // 추천번호 업데이트 전
      if ($n_time < $draw_time_num) { // 추천시간 이전
        $win = array("추첨전", "추첨전", "추첨전", "label");
      } else { // 추천시간 이후
        $win = array("<span class='blink'>집계중</span>", "집계중", "집계중", "label");
      }
    } else {
      if ($prizcnt9 <= 0) { // 당첨금 업데이트 전
        $win = array("<span class='blink'>집계중</span>", "집계중", "집계중", "label");
      }
    }
  }

  if ($is_type == 'Y') {
    $win = array("당첨", "당첨", "당첨","label");
  }

  return $win;
}

// INJECTION 임시 함수 (SLK-434)
function xss_clean($data) {
    // 1. 대소문자 구별 없이 SQL 및 의심스러운 키워드 제거
    $data = str_ireplace(
        array('select ', 'from ', 'union ', 'where ', 'ord(', 'xor(i', 'sleep(', 'receive_message', 'chr('),
        '',
        $data
    );

    // 2. HTML 엔티티 정리
    $data = preg_replace('/(&#*\w+)[\x00-\x20]+;/u', '$1;', $data);
    $data = preg_replace('/(&#x*[0-9A-F]+);*/iu', '$1;', $data);
    $data = html_entity_decode($data, ENT_COMPAT, 'UTF-8');

    // 3. 특수 문자 변환
    $data = str_replace(array('&', '<', '>'), array('&amp;', '&lt;', '&gt;'), $data);

    // 4. 의심스러운 속성 제거 (on 이벤트와 xmlns 속성)
    $data = preg_replace('#(<[^>]+?[\x00-\x20"\'])(?:on|xmlns)[^>]*+>#iu', '$1>', $data);

    // 5. JavaScript 및 VBScript 프로토콜 방지
    $data = preg_replace(
        '#([a-z]*)[\x00-\x20]*=[\x00-\x20]*([`\'"]*)[\x00-\x20]*(?:j[\x00-\x20]*a[\x00-\x20]*v[\x00-\x20]*a[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t|v[\x00-\x20]*b[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t)[\x00-\x20]*:#iu',
        '$1=$2nojavascript...',
        $data
    );

    // 6. 스타일 속성에서 위험한 표현식 제거
    $data = preg_replace(
        '#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?(?:expression|behaviour|s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t)[\x00-\x20]*\([^>]*+>#i',
        '$1>',
        $data
    );

    // 7. 네임스페이스 태그 제거
    $data = preg_replace('#</*\w+:\w[^>]*+>#i', '', $data);

    // 8. 반복적으로 의심스러운 태그 제거
    do {
        $old_data = $data;
        $data = preg_replace(
            '#</*(?:applet|b(?:ase|gsound|link)|embed|frame(?:set)?|i(?:frame|layer)|l(?:ayer|ink)|meta|object|s(?:cript|tyle)|title|xml)[^>]*+>#i',
            '',
            $data
        );
    } while ($old_data !== $data);

    // 9. 최종 반환
    return $data;
}

// 파트너 코드 처리-SLK-647
function exec_partner($data , $device) {
  global $db;

  if ($data) {
    $data = xss_clean($data);
    $_REFERER = $_SERVER['HTTP_REFERER'];
    $IS_PARTNER = false;

    // 기존 세션파트너 아이디와 cp_id가 다를경우에만 세션 초기화
    if ($_SESSION["PARTNER_ID"] && $data != $_SESSION["PARTNER_ID"]) {
      session_unregister("PARTNER_ID");
    }

    // 정상 partner idx 확인
    $cnt = $db->get_data("SELECT COUNT(IDX) AS CNT FROM `UM_PARTNER` WHERE `PARTNER_ID`= '".$data."'");
    if ($cnt['CNT'] > 0) {
      $IS_PARTNER = true;
    }

    // 접속 정보 확인 및 저장 (최초 접속)
    if ($IS_PARTNER && !$_SESSION["PARTNER_ID"] ) {
      $SQL = "INSERT INTO `UM_TRAFFIC_LOG` (`PARTNER_ID`, `IP`, `REFERRAL`, `IN_TYPE`) VALUES ('".$data."', '".$_SERVER["REMOTE_ADDR"]."', '".$_REFERER."', '".$device."')";
      $db->query($SQL);

      // session 등록
      $_SESSION["PARTNER_ID"] = $data;

      // summary 처리
      exec_partner_summary($data, "VISIT");
    }
  }
}

// 파트너 summary 처리
function exec_partner_summary($_PARTNER_ID, $_MODE) {
  global $db;

  $Visit_Date = date("Y-m-d");
  $Visit_Time = date("H");

  if ($_PARTNER_ID) {
    switch ($_MODE) {
      case "VISIT": // 방문시
        $_FIELDS = "VISIT_CNT";
        break;
      case "REG": // 회원가입시
        $_FIELDS = "MEMBER_CNT";
        break;
      case "REG_OUT": // 회원가입시
        $_FIELDS = "OMEMBER_CNT";
        break;
      case "PAY": // 결제시 (카드결제만)
        $_FIELDS = "CASH_CNT";
        break;
    }

    $WhereIs = " WHERE PARTNER_ID= '".$_PARTNER_ID."'";
    $WhereIs .= " AND SUMM_DATE = '".$Visit_Date."'";
    $WhereIs .= " AND SUMM_TIME = ".$Visit_Time;
    $SQL = "SELECT COUNT(IDX) AS CNT FROM UM_SUMMARY ";
    $SQL .= $WhereIs;
    $cnt = $db->get_data($SQL);

    // 정보 업데이트 또는 추가
    if ($cnt['CNT'] > 0 ) {
      $SQL = "UPDATE UM_SUMMARY SET ".$_FIELDS." = ".$_FIELDS." + 1";
      $SQL .= $WhereIs;
    } else {
      $SQL = "INSERT INTO UM_SUMMARY (PARTNER_ID, SUMM_DATE, SUMM_TIME, ".$_FIELDS.") VALUES ('".$_PARTNER_ID."', '".$Visit_Date."', '".$Visit_Time."', 1) ";
    }
    $db->query($SQL);
  }
}

// 파트너 첫결제 처리
function exec_partner_firstsale($_MEM_USER_ID, $_PARTNER_ID, $_MEM_REG_DATE) {
  global $db;

  $is_first = false;

  // 첫번째 결제인지 확인(회원)
  $query = "SELECT COUNT(CASH_NO) AS CNT FROM CASH WHERE USER_ID = '".$_MEM_USER_ID."' AND IS_USE = 'Y' ";
  $is_first_sale = $db->get_data($query);
  if ($is_first_sale['CNT'] <= 0) {
    $is_first = true;
  }

  // 첫번째 결제일때만 처리
  if ($is_first) {
    // 파트너 설정 정보
    $query = " SELECT FIRST_SALE FROM UM_COMM_POLICY C INNER JOIN UM_PARTNER AS P ON C.IDX=P.COMM_POLICY_IDX ";
    $query.= " WHERE P.PARTNER_ID = '".$_PARTNER_ID."'";
    $partner_first_sale = $db->get_data($query);
    $FIRST_SALE = $partner_first_sale['FIRST_SALE']; // 첫결제 기준(null:당일 / nullX:단위:일)

    $mem_regdate = date("Y-m-d", strtotime($_MEM_REG_DATE));
    $now_date = date("Y-m-d");
    $diff_time = abs(strtotime($mem_regdate)-strtotime($now_date));
    $diff_day = ceil($diff_time / (60*60*24)); // 가입일 및 현재 일까지 지난 일수(설정된 첫결제값과 비교 용도)

    // 첫 결제기준이 null일 경우 당일, 이외일 경우(숫자)는 가입일로 부터 +설정 값
    if ($FIRST_SALE) { // 첫 결제 기준이 일단위
      if ($diff_day <= $FIRST_SALE) {
        $PARTNER_ID = $_PARTNER_ID;
      } else {
        $PARTNER_ID = "";
      }
    } else { // 첫 결제 기준이 당일
      if ($diff_day == 0) {
        $PARTNER_ID = $_SESSION['PARTNER_ID'];
      } else {
        $PARTNER_ID = "";
      }
    }
  } else {
    $PARTNER_ID = "";
  }
  return $PARTNER_ID;
}

// 추천인 처리
function exec_partner_recomment_mileage($_PARTNER_ID, $_MEM_USER_ID) {
  global $db;

  if ($_PARTNER_ID) {
    // 파트너 여부 체크
    $query = "SELECT COUNT(IDX) AS CNT FROM UM_PARTNER WHERE PARTNER_ID = '".$_PARTNER_ID."'";
    $is_partner = $db->get_data($query);

    if ($is_partner) {
      // 파트너 커미션 설정 중 마일리지 설정 확인
      $query = " SELECT MILEAGE FROM UM_COMM_POLICY C INNER JOIN UM_PARTNER AS P ON C.IDX=P.COMM_POLICY_IDX ";
      $query.= " WHERE P.PARTNER_ID = '".$_PARTNER_ID."'";
      $partner_mileage = $db->get_data($query);

      if ($partner_mileage['MILEAGE'] > 0) {
        // 추천한 회원에게 마일리지 부여
        $sql = "
          UPDATE MEMBER
          SET IPOINT=IPOINT+".$partner_mileage['MILEAGE']."
          WHERE USER_ID = '".$_MEM_USER_ID."'
        ";
        $db->query($sql);

        // 마일리지사용로그
        $ipoint_log = array();
        $ipoint_log['mode']        = "insert";
        $ipoint_log['ORDERS_NO']   = "";
        $ipoint_log['CASH_LOG_NO'] = 0;
        $ipoint_log['WINNING_NO']  = 0;
        $ipoint_log['IPOINT']      = $partner_mileage['MILEAGE'];
        $ipoint_log['N_IPOINT']    = $partner_mileage['MILEAGE'];
        $ipoint_log['O_IPOINT']    = 0;
        $ipoint_log['USER_ID']     = $_MEM_USER_ID;
        $ipoint_log['MEMO']        = "추천인아이디-".$_PARTNER_ID;
        $ipoint_log['STATUS']      = "P";
        F_T_IPOINT_LOG($ipoint_log);
      }
    }
  }
}

// 파트너 여부 체크
function exec_partner_is_exist($_PARTNER_ID) {
  global $db;

  // 파트너 여부 체크
  $query = "SELECT COUNT(IDX) AS CNT FROM UM_PARTNER WHERE PARTNER_ID = '".$_PARTNER_ID."'";
  $is_partner = $db->get_data($query);

  if ($is_partner['CNT'] > 0) {
    return true;
  } else {
    return false;
  }
}

// 당첨금 정보 원화 계산
function setPriceKRW($price) {
  $price = str_replace(array(",", "."), "", $price);
  return getNumberStringKorean(substr($price, 0, -8));
}

function fileUploadToAdm($_L) {
  // 파일 첨부를 이용한 해킹 방지
  $php_str = $_L['fn'];

  // 이진 수정 (이미지 업로드시 파일 체크 오류 해결)
  $eArr = array(".ht", ".phtm", ".php", ".inc", ".pl", ".perl", "htaccess", "exe");
  for ($i = 0; $i < count($eArr); $i++) {
    if (strpos($php_str, $eArr[$i]) !== false) {
      unlink($_L['f']);
      alert_print("html, php 등의 파일은 첨부가 불가능합니다");
      break;
      return;
    }
  }

  if ($_L['fn'] != "" && $_L['fs'] > 1) {
    if (!file_exists($_L['save_dir'])) {
      mkdir($_L['save_dir']);
      chmod($_L['save_dir'], 0777);
    }

    $size1 = $_L['fs'];
    $filename_exe1 = explode('.', $_L['fn']);
    $file1 = $f_date.'/'.$_L['uniq']."_".$_L['idx']."_".time().".".$filename_exe1[1];

    if ($size1 > $_L['max_size']) {
      alert_print('첨부된 파일 크기가 '.$_L['max_size'].'를 초과합니다.');
      return false;
    } else {
      $full_save1 = $_L['save_dir']."/".$file1; // 경로와 파일명 합치기 (이미지파일)
      if ($_L['f'] == "") {
        $_L['f'] = $_L['ftmp'];
      }

      move_uploaded_file($_L['f'], $full_save1);
      chmod($full_save1, 0777);
      @unlink($_L['f']);
      @unlink($_L['ftmp']);
      $R['real'] = $file1;
      $R['down'] = $_L['fn'];

      // ftp upload
      $host = "";
      $port = "";
      $ftp_user_name = "";
      $ftp_user_pass = "";

      $ftp = ftp_connect($host, $port);
      $login_result = ftp_login($ftp, $ftp_user_name, $ftp_user_pass);

      ftp_set_option($ftp, FTP_USEPASVADDRESS, false);
      ftp_pasv($ftp, true);

      $filePath = $_L['save_dir'];
      $filename = $file1;
      $localfile = $filePath.$filename;

      $uploaddir = '/admin/upload/report';
      $serverfile = $uploaddir.$filename;

      ftp_put($ftp, $serverfile, $localfile, FTP_BINARY);
      ftp_close($ftp);

      // del localfile
      @unlink($localfile);

      return $R;
    }
  } // 첨부파일이 있는경우
}

// LA 시간 기준 DST 여부 확인 - 1 dst, 0 nomal
function check_dst($d) {
  date_default_timezone_set("America/Los_Angeles"); // 시간대 변경
  $dst = date("I", strtotime($d)); // DST 체크
  date_default_timezone_set("Asia/Seoul"); // 시간대 원복
  return $dst;
}

// DST 적용 추첨시간 조회
function get_dst_drawtime($d) {
  return (check_dst($d) ? "12:00" : "13:00");
}

if (!function_exists('session_register')) fix_session_register();
if (!function_exists('json_encode')) fix_json_encode();

/**
 * Shop에서 사용하는 공통함수 Start
 */
function dbQuery($query) {
  global $db;

  $result = $db->query($query);
  if (!$result) {
    echo "$query<br>".mysqli_error($dbconn);
    exit;
  }
  return $result;
}

function selectAll($table, $field = "*", $where = "", $debug = "0") {
  global $db;
  // $List = array();
  if (is_array($field)) {
    $field=implode(" , ", $field);
  }
  $query = "SELECT ".$field." FROM ".$table." ".$where;
  // if ($debug=='1') print $query;
  $result = dbQuery($query);

  while ($data = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
    foreach ($data as $key => $value) {
      $List[$key][] = $value;
    }
  }
  return $List; // example) <loop>print list[field][$i]</loop>;
}

function selectOne($table, $field = "*", $where = "", $debug = "0") {
  global $db;
  if (is_array($field)) {
    $field = implode(" , ", $field);
  }
  $query = "SELECT ".$field." FROM ".$table." ".$where;
  // if ($debug=='1') print $query;
  $return = mysqli_fetch_array(dbQuery($query), MYSQLI_ASSOC);
  return $return;
}
// Shop에서 사용하는 공통함수 End

function sendRequestToUSAServer($path, $postData) {
  global $usApiDomain, $usClientKey;

  $url = $usApiDomain . "{$path}.php";
  $headers = [
    "Content-Type: application/json; charset=UTF-8",
    "token: {$usClientKey}"
  ];

  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_POST           => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_USERAGENT      => $_SERVER["HTTP_USER_AGENT"],
    CURLOPT_POSTFIELDS     => $postData,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT        => 10,
  ]);

  $response = curl_exec($ch);
  curl_close($ch);

  return $response;
}

/**
 * AWS SES Client initialize
 */
function initializeSesClient() {
  global $awsRegion, $awsAccessKey, $awsSecretKey;

  try {
    return new SesClient([
      "region"      => $awsRegion,
      "version"     => "latest",
      "credentials" => [
        "key"    => $awsAccessKey,
        "secret" => $awsSecretKey
      ],
    ]);
  } catch (AwsException $e) {
    sysecho("SES Client Initialization Error: " . $e->getMessage());
    return null;
  }
}

/**
 * 메일 발송 (AWS SES)
 */
function sendMail($sesClient, $fname, $fmail, $to, $subject, $content, $type, $mname, $mno, $tno) {
  $char_set = 'UTF-8';
  try {
    $result = $sesClient->sendEmail([
      "Destination" => [
        "ToAddresses" => [$to],
      ],
      "ReplyToAddresses" => [$fmail],
      "Source" => "$fname <$fmail>",
      "Message" => [
        "Body" => [
          "Html" => [
            "Charset" => $char_set,
            "Data" => $content,
          ],
        ],
        "Subject" => [
          "charset" => $char_set,
          "Data" => $subject,
        ],
      ],
    ]);

    $message = $result["MessageId"];
    $status = true;
  } catch (AwsException $e) {
    $errorCode = $e->getAwsErrorCode();
    $errorMessage = $e->getAwsErrorMessage() ?: $e->getMessage();
    $message = "$errorCode: " . substr($errorMessage, 0, 200);
    $status = false;
  }

  $is_type = ($status) ? "Y" : "N";

  $mail_log = array();
  $mail_log["TOEMAIL"]     = $to;
  $mail_log["TEMPLET_NO"]  = $tno;
  $mail_log["MEMBER_NO"]   = $mno;
  $mail_log["MEMBER_NAME"] = $mname;
  $mail_log["SUBJECT"]     = $subject;
  $mail_log["CONTENT"]     = $content;
  $mail_log["STATUS"]      = $is_type;
  F_mail_log($mail_log);
}

function sendInjectionMail($to, $subject, $content) {
  $char_set = 'UTF-8';
  $fname = 'suloko';
  $fmail = "root@{$_SERVER['HTTP_HOST']}";

  $sesClient = initializeSesClient();

  if (!$sesClient) {
    return false;
  }

  try {
    $result = $sesClient->sendEmail([
      "Destination" => [
        "ToAddresses" => [$to],
      ],
      "ReplyToAddresses" => [$fmail],
      "Source" => "$fname <$fmail>",
      "Message" => [
        "Body" => [
          "Html" => [
            "Charset" => $char_set,
            "Data" => $content,
          ],
        ],
        "Subject" => [
          "charset" => $char_set,
          "Data" => $subject,
        ],
      ],
    ]);

    $message = $result["MessageId"];
    $status = true;
  } catch (AwsException $e) {
    $errorCode = $e->getAwsErrorCode();
    $errorMessage = $e->getAwsErrorMessage() ?: $e->getMessage();
    $message = "$errorCode: " . substr($errorMessage, 0, 200);
    $status = false;
  }
}

# client ip address
function getRealClientIp() {
  $ipaddress = '';
  if (getenv('HTTP_CLIENT_IP')) {
      $ipaddress = getenv('HTTP_CLIENT_IP');
  } else if(getenv('HTTP_X_FORWARDED_FOR')) {
      $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
  } else if(getenv('HTTP_X_FORWARDED')) {
      $ipaddress = getenv('HTTP_X_FORWARDED');
  } else if(getenv('HTTP_FORWARDED_FOR')) {
      $ipaddress = getenv('HTTP_FORWARDED_FOR');
  } else if(getenv('HTTP_FORWARDED')) {
      $ipaddress = getenv('HTTP_FORWARDED');
  } else if(getenv('REMOTE_ADDR')) {
      $ipaddress = getenv('REMOTE_ADDR');
  } else {
      $ipaddress = 'UNKNOWN';
  }
  return substr($ipaddress,0,15);
}

## 결제 이벤트 대상 확인
## 미접속자
function chk_event_user_a($u) {
  global $db;

  $start_date = "2025-05-13 12:00:00"; // 이벤트 시작일시
  $current_date = date("Y-m-d H:i:s"); // 현재시간

  $result = false;
  if ($current_date >= $start_date) { // 이벤트 기간 내에 있는지 확인
    $info = $db->get_data("SELECT USER_ID, CASH_NO, EVENT_AT FROM MEMBER_EVENT_250513_a WHERE USER_ID='{$u}'");
    if (isset($info["USER_ID"]) === true && is_null($info["CASH_NO"]) === true && $current_date <= $info["EVENT_AT"]) { // 이벤트 참여자인지 확인 (CASH_NO가 NULL인 경우만 참여 가능)
      $result = true; // 이벤트 참여 가능
    }
  }
  return $result;
}
## 소액 - 6000
function chk_event_user_b1($u) {
  global $db;

  $start_date = "2025-05-13 12:00:00"; // 이벤트 시작일시
  $current_date = date("Y-m-d H:i:s"); // 현재시간

  $result = false;
  if ($current_date >= $start_date) { // 이벤트 기간 내에 있는지 확인
    $info = $db->get_data("SELECT USER_ID, CASH_NO, EVENT_AT FROM MEMBER_EVENT_250513_b1 WHERE USER_ID='{$u}'");
    if (isset($info["USER_ID"]) === true && is_null($info["CASH_NO"]) === true && $current_date <= $info["EVENT_AT"]) { // 이벤트 참여자인지 확인 (CASH_NO가 NULL인 경우만 참여 가능)
      $result = true; // 이벤트 참여 가능
    }
  }
  return $result;
}
## 소액 - 15000
function chk_event_user_b2($u) {
  global $db;

  $start_date = "2025-05-13 12:00:00"; // 이벤트 시작일시
  $current_date = date("Y-m-d H:i:s"); // 현재시간

  $result = false;
  if ($current_date >= $start_date) { // 이벤트 기간 내에 있는지 확인
    $info = $db->get_data("SELECT USER_ID, CASH_NO, EVENT_AT FROM MEMBER_EVENT_250513_b2 WHERE USER_ID='{$u}'");
    if (isset($info["USER_ID"]) === true && is_null($info["CASH_NO"]) === true && $current_date <= $info["EVENT_AT"]) { // 이벤트 참여자인지 확인 (CASH_NO가 NULL인 경우만 참여 가능)
      $result = true; // 이벤트 참여 가능
    }
  }
  return $result;
}
## 소액 - 30000
function chk_event_user_b3($u) {
  global $db;

  $start_date = "2025-05-13 12:00:00"; // 이벤트 시작일시
  $current_date = date("Y-m-d H:i:s"); // 현재시간

  $result = false;
  if ($current_date >= $start_date) { // 이벤트 기간 내에 있는지 확인
    $info = $db->get_data("SELECT USER_ID, CASH_NO, EVENT_AT FROM MEMBER_EVENT_250513_b3 WHERE USER_ID='{$u}'");
    if (isset($info["USER_ID"]) === true && is_null($info["CASH_NO"]) === true && $current_date <= $info["EVENT_AT"]) { // 이벤트 참여자인지 확인 (CASH_NO가 NULL인 경우만 참여 가능)
      $result = true; // 이벤트 참여 가능
    }
  }
  return $result;
}
function select_event_cash_op($u) {
  global $cash_op, $event_cash_op_a, $event_cash_op_b1, $event_cash_op_b2, $event_cash_op_b3;
  if (chk_event_user_a($u)) {
    $cash_op = $event_cash_op_a;
  } else if (chk_event_user_b1($u)) {
    $cash_op = $event_cash_op_b1;
  } else if (chk_event_user_b2($u)) {
    $cash_op = $event_cash_op_b2;
  } else if (chk_event_user_b3($u)) {
    $cash_op = $event_cash_op_b3;
  }
  return $cash_op;
}
// function chk_event_user($u) {
//   global $db;

//   $start_date = "2025-03-10 19:00:00"; // 이벤트 시작일시
//   $end_date = "2025-03-31 23:59:59"; // 이벤트 종료일
//   $current_date = date("Y-m-d H:i:s"); // 현재시간

//   $result = false;
//   if ($current_date >= $start_date && $current_date <= $end_date) { // 이벤트 기간 내에 있는지 확인
//     $result = true; // 이벤트 참여 가능
//   }
//   return $result;
// }

# 날짜 간격 출력
function dateDifference($start, $end) {
  $startDate = new DateTime($start);
  $endDate = new DateTime($end);
  $diff = $startDate->diff($endDate);

  return "{$diff->d}일 {$diff->h}시간 {$diff->i}분";
}

# Description 정리
function stripDescription($str) {
  $str = str_replace("&nbsp;", "", $str);
  $str = str_replace("\r\n", "", $str);
  $str = str_replace("\"", "", $str);
  $str = str_replace("안녕하세요! 슈퍼로또코리아 입니다.", "", $str);
  $str = str_replace("안녕하세요! 슈로코 입니다.", "", $str);
  $str = preg_replace('/\{\{.*?\}\}/', '', $str);
  $str = preg_replace('/\{.*?\}/', '', $str);
  $str = str_replace("  ", "", $str);
  return $str;
}

/**
 * 날짜비교
 * 입력된 날짜와 현재날짜를 초단위로 비교
 * 입력시간 > 현재시간 = -초,
 * 입력시간 = 현재시간 = 0,
 * 입력시간 < 현재시간 = +초
 */
function getSecondDifference($datetime) {
  $now = new DateTime(); // 현재 날짜와 시간
  $inputDateTime = new DateTime($datetime); // 입력된 날짜와 시간

  // 두 시간의 Unix 타임스탬프 차이 계산
  $seconds = $now->getTimestamp() - $inputDateTime->getTimestamp();

  return (int) $seconds; // 정수 반환
}

function sendTelegram($chat_id, $message, $token) {

    $url = "https://api.telegram.org/bot{$token}/sendMessage";

    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => [
            'chat_id' => $chat_id,
            'text' => $message
        ]
    ]);

    $response = curl_exec($curl);
    curl_close($curl);

    return $response;
}

function sendTelegramGroup($chat_id, $message, $token) {

  // 그룹 ID 체크 (-100으로 시작하는지)
  if (strpos($chat_id, '-100') !== 0) {
      return "그룹 ID가 아닙니다.";
  }

  $url = "https://api.telegram.org/bot{$token}/sendMessage";

  $data = [
      'chat_id' => $chat_id,
      'text' => $message,
      'parse_mode' => 'HTML' // HTML 사용 가능
  ];

  $curl = curl_init();
  curl_setopt_array($curl, [
      CURLOPT_URL => $url,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST => true,
      CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
      CURLOPT_POSTFIELDS => json_encode($data)
  ]);

  $response = curl_exec($curl);
  $error = curl_error($curl);
  curl_close($curl);

  if ($error) {
      return "Curl Error: " . $error;
  }

  return $response;
}