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
  $return = preg_match("/".$str."/", $data);
  return $return;
}

function sub_eregiReplace($str, $replace_str, $data) {
  $return = preg_replace('/&page=[0-9]{1,}/', $replace_str, $data);
  return $return;
}

// SQL 로 받아 배열변수 만든후 <option value='key'>value<option>  로 만들기
function F_get_option($query, $select) {
  $Array = F_get_op($query);
  $String = option_make($Array, $select);
  return $String;
}

// 쿼리를 통해 배열정보를 만드는 함수 - 첫번째 필드: key, 두번째 필드: value // ex) SELECT key,value FROM 테이블
function F_get_op($sql) {
  global $db;
  $query_result = $db->query($sql);
  $data_cnt = @mysqli_num_rows($query_result) - 1;
  if ($data_cnt < "0") {
    return;
  }
  $i = 0;
  while ($row = mysqli_fetch_row($query_result)) {
    $result_op[$row[0]] = $row[1];
  }
  return $result_op;
}

// 관리자 로그인 확인
function F_admin_chk() {
  global $PHP_SELF, $QUERY_STRING, $S_login;

  $has_login = (isset($S_login["user_id"]) && trim($S_login["user_id"]) != "");
  if ($has_login) {
    return;
  }

  // 멀티로그인 세션은 있는데 _win 파라미터가 빠진 경우 (검색/location.replace 등)
  if (admin_session_win_id() === '' && !empty($_SESSION['S_login_wins']) && is_array($_SESSION['S_login_wins'])) {
    admin_win_recover_page();
  }

  meta_go("/login.html");
  exit;
}

// 페이지별 관리자 권한 체크
function F_admin_url_auth_chk() {
  global $sub_menu, $menuAuth, $indexurl;

  $page_auth = "deny";

  if ($_SERVER['PHP_SELF'] == "/" || $_SERVER['PHP_SELF'] == "/index.php" || $_SERVER['PHP_SELF'] == $indexurl) {
    $page_auth = "allow";
  } else {
    foreach ($menuAuth as $sub_key) {
      foreach ($sub_menu[$sub_key] as $auth_url) {
        if ($_SERVER['PHP_SELF'] == $auth_url) $page_auth = "allow";
      }
    }
  }

  if ($page_auth == "deny") {
    alert_print("페이지 권한이 없습니다.\\n(".$_SERVER['PHP_SELF'].")");
    history_go();
    exit;
  }
}

// 특정 파일을 불러와서 변수에 넣기
function file_load($File) {
  $Fileload = fopen($File, "r");
  $skin = fread($Fileload, filesize($File));
  fclose($Fileload);
  return $skin;
}

// 파일 쓰기
function file_write($File, $source) {
  $Fileload = fopen($File, "w");
  fwrite($Fileload, $source);
  fclose($Fileload);
  return $skin;
}

// 텍스트 길이 자르기 UTF 방식 '...'을 넣는다.
function str_utf_cut($str, $maxlen) {
  $Return_str = substr($str, 0, $maxlen);
  $cnt = 0;
  for ($i = 0; $i < strlen($Return_str); $i++) {
    if (ord($Return_str[$i]) > 127) {
      $cnt++;
    }
  }
  $Return_str = substr($Return_str, 0, $maxlen - ($cnt % 3));
  if ($maxlen < strlen($str)) {
    return $Return_str."..." ;
  } else {
    return $Return_str;
  }
}

// 텍스트 길이 자르기 UTF 방식 '...'을 넣지않는다.
function str_utf_cut2($str, $maxlen) {
  $Return_str = substr($str, 0, $maxlen);
  $cnt = 0;
  for ($i = 0; $i < strlen($Return_str); $i++) {
    if (ord($Return_str[$i]) > 127) {
      $cnt++;
    }
  }
  $Return_str = substr($Return_str, 0, $maxlen - ($cnt % 3));
  return $Return_str;
}

// 텍스트 길이 자르기 euc_kr 방식 '...'을 넣는다.
function str_han_cut($str, $maxlen) {
  $len = strlen($str);
  if ($len <= $maxlen) {
    return $str;
  }

  for ($i = 0; $i < $len; $i++) {
    if (ord(substr($str, $i, 1)) < 128) {
      $Return_str .= substr($str, $i, 1);
    } else {
      $Return_str .= substr($str,$i,2);
      $i++;
    }
    if (++$cnt >= $maxlen) {
      break;
    }
  }
  return $Return_str."..." ;
}

// 텍스트 길이 자르기 utf-8 방식 '...'을 넣는다.
function str_han_cut_utf($str, $maxlen) {
  $len = strlen($str);
  if ($len <= $maxlen) {
    return $str;
  }

  $Return_str = iconv_substr($str, 0, $maxlen, "utf-8");

  return $Return_str."..." ;
}

// 배열로 option 태그 생성
function option_make($array, $sel) {
  $str = "";
  foreach ($array as $keys => $vals) {
    $str .= "<option value='".$keys."'";
    if ($keys == $sel) $str .= " SELECTED";
    $str .= ">".$vals."</option>\n";
  }
  return $str;
}

// js 함수 alert 자바스크립트
function alert_print($string) {
  echo("
    <script language='javascript'>
    alert('".$string."');
    </script>
  ");
  }

// js 함수 부모창 열기 (익스11에서 작동하지 않음)
function open_opener() {
  echo("
    <script language='javascript'>
    opener.document.location.reload();
    </script>
  ");
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

// js 함수 self.close()
function self_close() {
  echo ("
    <script language='javascript'>
    self.close();
    </script>
  ");
}

// js 함수 경로 이동
function meta_go($url) {
  $url = admin_url($url);
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
  foreach ($_L as $key => $val) {
    $_RL[$key] = $val;
  }
  return $_RL;
}

function F_add_slashes_real($_L) { // 배열 addslashes
  $_RL = array();
  foreach ($_L as $key => $val) {
    $_RL[$key] = addslashes($val);
  }
  return $_RL;
}

// db에 빼와서 ' " 배열째로 addslashes 하기
function F_strip_slashes($_L) { // 배열 stripslashes
  foreach ($_L as $keys => $vals) {
    $_RL[$keys] = stripslashes($vals);
  }
  return $_RL;
}

// print_page_num() 페이지 번호 출력하는 함수
function print_page_num($page_info) {
  // print "$page_info["cur"], $page_info[row], $page_info[total]";
  // 현재 페이지, 페이지당 출력 수, 전체 레코드 수
  global $PHP_SELF, $PARAM, $QUERY_STRING;

  $page_string = "";
  $PARAM2 = sub_eregiReplace("&page=[0-9]{1,}", "", $PARAM);
  $PARAM2 = str_replace("&page=", "", $PARAM2);

  if (!isset($page_info["cur"])) {
    $page_info["cur"] = 1;
  }
  $page_row_t = intval($page_info["total"] / $page_info["row"]) + 1; // 전체페이지수
  if (($page_row_t - 1) == ($page_info["total"] / $page_info["row"])) {
    $page_row_t--;
  }

  // 10개씩 자른 페이지 중 현재 페이지가 속한 그룹의 첫번째 를 구하기 위함
  $page_10 = intval($page_info["cur"] / 5);
  if ($page_10 == ($page_info["cur"]/5)) {
    $page_10--;
  }
  $page_first = $page_10*5 + 1; // 현재 페이지가 속한 10개 중 첫번째 페이지
  $page_n=$page_first + 5; // 다음 10개 페이지(첫번째 페이지)
  $page_p=$page_first - 5; // 이전 10개 페이지(첫번째 페이지)

  $i = 1; // 순서 초기화

  $page_status = $page_first;

  while ($page_status <= $page_row_t && $i <= 5) {
    if ($page_status < 5) {
      $num = $page_status;
    } else {
      $num = $page_status;
    }

    if ($page_info["cur"] == $page_status) {
      $page_string .= '<li class="page-item active">
        <a class="page-link" href="#">'.$num.'</a>
        </li>';
    } else { // 현재페이지인 경우
      $page_string .= '<li class="page-item">
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
  $page_string = '<li class="page-item"><a aria-label="Next" class="page-link" href="'.$PHP_SELF.'?'.$PARAM2.'&page='.$page_s.'"><i class="fa fa-angle-left"></i></a></li>'.$page_string;
  $page_string = '<li class="page-item"><a aria-label="Last" class="page-link" href="'.$PHP_SELF.'?'.$PARAM2.'&page='.$page_p.'"><i class="fa fa-angle-double-left"></i></a></li>'.$page_string;
  if ($page_info["cur"] != 1) {
    $page_string = '<li class="page-item"><a aria-label="Last" class="page-link" href="'.$PHP_SELF.'?'.$PARAM2.'&page=1">1 … </a></li>'.$page_string;
  }

  $page_string = $page_string.'<li class="page-item"><a aria-label="Next" class="page-link" href="'.$PHP_SELF.'?'.$PARAM2.'&page='.$page_e.'"><i class="fa fa-angle-right"></i></a></li>';
  $page_string = $page_string.'<li class="page-item"><a aria-label="Last" class="page-link" href="'.$PHP_SELF.'?'.$PARAM2.'&page='.$page_n.'"><i class="fa fa-angle-double-right"></i></a></li>';
  if ($page_info["cur"] != $page_row_t) {
    $page_string = $page_string.'<li class="page-item"><a aria-label="Last" class="page-link" href="'.$PHP_SELF.'?'.$PARAM2.'&page='.$page_row_t.'"> … '.$page_row_t.'</a></li>';
  }
  return '<ul class="pagination justify-content-center">'.$page_string.'</ul>';
}

// print_page_num() 페이지 번호 출력하는 함수
function print_page_num1($page_info) {
  // print "$page_info["cur"], $page_info[row], $page_info[total]";
  // 현재 페이지, 페이지당 출력 수, 전체 레코드 수
  global $PHP_SELF, $PARAM, $QUERY_STRING;

  $page_string = "";
  $PARAM2 = sub_eregiReplace("&page=[0-9]{1,}", "", $QUERY_STRING);
  $PARAM2 = str_replace("&page=", "", $PARAM2);

  if (!isset($page_info["cur"])) {
    $page_info["cur"] = 1;
  }
  $page_row_t = intval($page_info["total"] / $page_info["row"]) + 1; // 전체페이지수
  if (($page_row_t - 1) == ($page_info["total"] / $page_info["row"])) {
    $page_row_t--;
  }

  // 10개씩 자른 페이지 중 현재 페이지가 속한 그룹의 첫번째 를 구하기 위함
  $page_10 = intval($page_info["cur"] / 5);
  if ($page_10 == ($page_info["cur"] / 5)) {
    $page_10--;
  }
  $page_first = $page_10 * 5 + 1; // 현재 페이지가 속한 10개 중 첫번째 페이지
  $page_n = $page_first + 5; // 다음 10개 페이지(첫번째 페이지)
  $page_p = $page_first - 5; // 이전 10개 페이지(첫번째 페이지)

  $i = 1; // 순서 초기화

  $page_status = $page_first;

  while($page_status <= $page_row_t && $i <= 5) {
    if ($page_status < 5) {
      $num = $page_status;
    } else {
      $num = $page_status;
    }

    if ($page_info["cur"] == $page_status) {
      $page_string .= '<li class="page-item active">
        <a class="page-link page_btn" style="cursor:pointer;" data-no="'.$num.'">'.$num.'</a>
        </li>';
    } else { // 현재페이지인 경우
      $page_string .= '<li class="page-item">
        <a class="page-link page_btn" style="cursor:pointer;" data-no="'.$page_status.'">'.$num.'</a>
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
  $page_string = '<li class="page-item">
    <a aria-label="Next" class="page-link page_btn" style="cursor:pointer;" data-no="'.$page_s.'"><i class="fa fa-angle-left"></i></a>
    </li>'.$page_string;
  $page_string = '<li class="page-item">
    <a aria-label="Last" class="page-link page_btn" style="cursor:pointer;" data-no="'.$page_p.'"><i class="fa fa-angle-double-left"></i></a>
    </li>'.$page_string;
  $page_string = $page_string.'<li class="page-item">
    <a aria-label="Next" class="page-link page_btn" style="cursor:pointer;" data-no="'.$page_e.'"><i class="fa fa-angle-right"></i></a>
    </li>';
  $page_string = $page_string.'<li class="page-item">
    <a aria-label="Last" class="page-link page_btn" style="cursor:pointer;" data-no="'.$page_n.'"><i class="fa fa-angle-double-right"></i></a>
    </li>';
  return '<ul class="pagination justify-content-center">'.$page_string.'</ul>';
}

// 메일발송함수 - 일반방식
function mailer($fname, $fmail, $to, $subject, $content, $type, $mname, $mno, $tno, $file="", $charset="UTF-8", $cc="", $bcc="") {
  global $S_login, $clientIpAddress;
  // type: text=0, html=1, text+html=2
  $type = 2;
  $content = str_replace("/upload/nse/","https://www.suloko7.com/contents/cs/imgsvc.html?u=",$content);

  $title  = $subject;
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

  if ($mail_log["TEMPLET_NO"] == 10) {
    //인증QR메일 발송은 별도 로그에 저장
    $filename = "../log/qrcode_send_log.txt";
    $now = date('Y-m-d H:i:s ', time());
    $str = $now."\t".$S_login['user_id']."\t".$clientIpAddress."\t".$to."\tMEMBERNO:".$mno."\t".$_SERVER["HTTP_USER_AGENT"]."\n";
    $fp = fopen($filename, 'a+');
    fwrite($fp, $str);
    fclose($fp);
  } else {
    F_mail_log($mail_log);
  }
}

// 메일 발송후 발송로그
function F_mail_log($_L) {
  global $db;
  $_L['CONTENT']  = addslashes($_L['CONTENT']);
  $db->query("INSERT INTO EMAILSENDLOG (TOEMAIL, TEMPLET_NO, MEMBER_NO, MEMBER_NAME, SUBJECT, CONTENT, STATUS, REG_DATE) VALUES ('".$_L['TOEMAIL']."','".$_L['TEMPLET_NO']."','".$_L['MEMBER_NO']."','".$_L['MEMBER_NAME']."','".$_L['SUBJECT']."','".$_L['CONTENT']."','".$_L['STATUS']."',NOW());");
}

$customer_code = "999999";
$customer_pw = "";

// 문자발송 함수
function F_SMS($to, $from, $msg) {
  global $HTTP_HOST, $customer_code, $customer_pw;
  $to2       = $to;
  $from2     = $from;
  $host_name = str_replace("www.","", $HTTP_HOST);
  $to        = sub_eregiReplace("[^0-9]", "", $to);
  $from      = sub_eregiReplace("[^0-9]", "", $from);
  $to        = chop($to);
  $from      = chop($from);
  $msg       = iconv("UTF-8","EUC-KR",$msg);
  $msg       = substr($msg, 0, 89);
  // if (strlen($msg) > 80) {
  //   F_LMS($to, $from, $msg); // 장문발송 - 현재 미사용중입니다.
  //   return;
  // }
  $msg       = substr($msg, 0, 89);
  $DEST_INFO = $customer_code."^".$to;
  $microtime = microtime().rand(11111,99999);
  $ext       = explode(" ", $microtime);
  $RESERVED1 = $ext[1].$ext[0]*100000000;

  // 로그데이터 입력용

  // 주의 : 아래의 코드를 조작하여  SMS를 무단으로 사용하는 경우를 점검하기 위하여
  //   정기적으로 총구매건수, 총사용건수, 총잔여건수 를 확인하고 있습니다.
  //   의심되는 부분이 발견되는 경우에는
  //   무단사용에 대한 책임 및 모든 강제수단을 강구할 예정이오니
  //   임의로 SMS 발송 코드를 조작하는 일은 없도록 주의해 주시기 바랍니다.
  $cnt_db = mysqli_connect("ez.skihouse.co.kr", "npro", "npro");
  mysqli_SELECT_db("npro");

  $row = mysqli_fetch_array(mysqli_query("SELECT sms FROM customer WHERE no = '$customer_code' AND pass = '$customer_pw' ",$cnt_db));
  if ($row[0] < 1) {
    alert_print("SMS 오류:사용불가능한 코드이거나 SMS 사용이 소진되었습니다. 충전후(신용카드결제만 가능) 사용하세요");
    alert_print("충전하실때는 http://sms.naeils.co.kr 에서 실시간으로 충전하실수 있습니다.");
    // echo "<script>top.location.replace('http://sms.naeils.co.kr/sms_company.html?customer_code=$customer_code');</script>";
    exit;
  }
  $update_query = "UPDATE customer SET sms = sms - 1, sms_use = sms_use + 1 WHERE no = '$customer_code'";
  $result2 = mysqli_query($update_query,$cnt_db);

  // 개별 발송의 경우입니다.
  $insert_query  = "
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
    "mode"   => 'insert',
    "name"   => $name,
    "from_num"  => $from,
    "to_num"  => $to,
    "msg"   => $msg
  ));
  return $result;
}

// 문자발송로그
function F_sms_log($_L) {
  global $db;

  if (!isset($_L["msg"])) {
    $_L["msg"] = "";
  }

  $_L["msg"] = iconv("EUC-KR", "UTF-8", $_L['msg']);

  if ($_L["mode"] == "read") {
    $info = $db->get_data("SELECT * FROM SMSSENDLOG WHERE LOG_NO  = '".$_L['no']."'");
    $info["msg"] = stripslashes($info["msg"]);
    return $info;
  }

  $_L["msg"] = addslashes($_L["msg"]);
  if ($_L["mode"] == "insert") {
    $query = "
      INSERT INTO SMSSENDLOG (
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
  if ($_L["mode"] == "delete") {
    $query = "DELETE FROM SMSSENDLOG WHERE LOG_NO = '".$_L['no']."'";
  }
  $db->query($query);
}

// 썸네일 함수 : GD가 배경 GIF는 투명하게 JPG는 흰색으로 처리된 완전한 함수
function F_resize_img($Resize_info) {
  $File = $Resize_info[org_file_name];
  $File_Writer = $Resize_info[save_file_name];
  $data_dir = $Resize_info[path];
  $width = $Resize_info[width];
  $height = $Resize_info[height];

  $size = getImageSize($File);

  if ($size[2] == 1) { // gif의 경우
    $isreturn = exec("gifsicle --resize $width"."x"."$height $File > $data_dir/$File_Writer");
    chmod("$data_dir/$File_Writer", 0777);
    if ($isreturn == true) {
      return 0;
    } else {
      $src_img = ImageCreateFromGIF($File);
    }
  } else if ($size[2] == 2) {
    $src_img = ImageCreateFromJPEG($File);
  } else if ($size[2] == 3) {
    $src_img = ImageCreateFromPNG($File);
  } else {
    return 0;
  }

  $img_width = $size[0];
  $img_height = $size[1];

  if ($img_width < $width && $img_height < $height) {
    $thumb_w = $img_width;
    $thumb_h = $img_height;
  } else {
    if ($img_width > $img_height) {
    $thumb_w = $width;
    $thumb_h = ceil($img_height * $width / $img_width);
    } else if ($img_width < $img_height) {
      $thumb_w = ceil($img_width * $height / $img_height);
      $thumb_h = $height;
    }

    if ($thumb_w > $width) {
      $thumb_w = $width;
      $thumb_h = ceil($img_height * $width / $img_width);
    }
    if ($thumb_h > $height) {
      $thumb_w = ceil($img_width * $height / $img_height);
      $thumb_h = $height;
    }
    if ($thumb_w == "") {
      $thumb_w  = $width;
    }
    if ($thumb_h == "") {
      $thumb_h  = $height;
    }
  }

  if ($size[2] == 1) { // gif의 경우는 ImageCreate 해주어야 하고 jpg 등은 imagecreatetruecolor 해줘야 깨끗하다.
    $dst_img = ImageCreate($width, $height);
    ImageColorAllocate($dst_img, 255, 255, 255);
  } else {
    $dst_img = imagecreatetruecolor($width, $height);
    $bgColor = ImageColorAllocate($dst_img, 255, 255, 255);
    ImageFilledRectangle($dst_img, 0, 0, $width, $height, $bgColor);
  }

  $dst_x = ($width - $thumb_w) / 2;
  $dst_y = ($height - $thumb_h) / 2;

  ImageCopyResized($dst_img, $src_img, $dst_x, $dst_y, 0, 0, $thumb_w, $thumb_h, $img_width, $img_height);

  // print $data_dir;
  if ($size[2] == 1) {
    ImageGIF($dst_img, $data_dir.$File_Writer);
  } else if ($size[2] == 2) {
    ImageInterlace($dst_img);
    ImageJPEG($dst_img, $data_dir.$File_Writer);
  } else if ($size[2] == 3) {
    ImagePNG($dst_img, $data_dir.$File_Writer);
  }

  // if ($size[2] == 2) {
  //   ImageInterlace($dst_img);
  //   ImageJPEG($dst_img, $data_dir.$File_Writer);
  // } else if ($size[2] == 3) {
  //   ImagePNG($dst_img, $data_dir.$File_Writer);
  // }

  ImageDestroy($dst_img);
  ImageDestroy($src_img);
  chmod($data_dir.$File_Writer, 0777);
} // resize_img() 종료

// 썸네일 함수: GD가 배경 GIF는 투명하게 JPG는 흰색으로 처리된 완전한 함수
function F_resize_img2($Resize_info) {
  $File = $Resize_info[org_file_name];
  $File_Writer = $Resize_info[save_file_name];
  $data_dir = $Resize_info[path];
  $width = $Resize_info[width];
  $height = $Resize_info[height];

  $size = getImageSize($File);

  $img_width = $size[0];
  $img_height = $size[1];

  if ($img_width >= $img_height) {
    $thumb_w = $img_height;
    $thumb_h = $img_height;
    $width = $img_height;
    $height = $img_height;
  } else {
    $thumb_w = $img_width;
    $thumb_h = $img_width;
    $width = $img_width;
    $height = $img_width;
  }

  if ($size[2] == 1) { //gif의 경우
    $isreturn = exec("gifsicle --resize $width"."x"."$height $File > $data_dir/$File_Writer");
    chmod("$data_dir/$File_Writer", 0777);
    if ($isreturn == true) {
      return 0;
    } else {
      $src_img = ImageCreateFromGIF($File);
    }
  } else if ($size[2] == 2) {
    $src_img = ImageCreateFromJPEG($File);
  } else if ($size[2] == 3) {
    $src_img = ImageCreateFromPNG($File);
  } else {
    return 0;
  }

  if ($size[2] == 1) { // gif의 경우는 ImageCreate 해주어야 하고 jpg 등은 imagecreatetruecolor 해줘야 깨끗하다.
    $dst_img = ImageCreate($width, $height);
    ImageColorAllocate($dst_img, 255, 255, 255);
  } else {
    $dst_img = imagecreatetruecolor($width, $height);
    $bgColor = ImageColorAllocate($dst_img, 255, 255, 255);
    ImageFilledRectangle($dst_img, 0, 0, $width, $height, $bgColor);
  }

  $src_x = ($img_width - $thumb_w) / 2;
  $src_y = ($img_height - $thumb_h) / 2;

  ImageCopyResized($dst_img, $src_img, 0, 0, $src_x, $src_y, $thumb_w, $thumb_h, $width, $height);

  // print $data_dir;
  if ($size[2] == 1) {
    ImageGIF($dst_img, $data_dir.$File_Writer);
  } else if ($size[2] == 2) {
    ImageInterlace($dst_img);
    ImageJPEG($dst_img, $data_dir.$File_Writer);
  } else if ($size[2] == 3) {
    ImagePNG($dst_img, $data_dir.$File_Writer);
  }

  // if ($size[2] == 2) {
  //   ImageInterlace($dst_img);
  //   ImageJPEG($dst_img, $data_dir.$File_Writer);
  // } else if ($size[2] == 3) {
  //   ImagePNG($dst_img, $data_dir.$File_Writer);
  // }

  ImageDestroy($dst_img);
  ImageDestroy($src_img);
  chmod($data_dir.$File_Writer, 0777);
} // resize_img() 종료

function F_file_upload($_L) {
  // 수정/삭제시 기존 첨부파일을 삭제한다.
  if ($_L["del_file_path"] && ($_L["mode"] == "delete" || $_L["mode"] == "update")) {
    @unlink($_L["del_file_path"]);
    @unlink($_L["del_file_path2"]);
  }

  // 파일첨부를 이용한 해킹 방지
  $php_str = $_L["fn"];

  // 이진 수정 (이미지 업로드시 파일 체크 오류 해결)
  $eArr = array(".ht", ".phtm", ".php", ".inc", ".pl", ".perl", "htaccess", "exe");
  for ($i = 0; $i < count($eArr); $i++) {
    if (strpos($php_str, $eArr[$i]) !== false) {
      unlink($_L["f"]);
      alert_print("html, php 등의 파일은 첨부가 불가능합니다");
      break;
      return;
    }
  }

  // $php_str = $_L["fn"];
  // if (eregi(".ht", $php_str) || eregi(".phtm", $php_str) || eregi(".php", $php_str) || eregi(".inc", $php_str) ||  eregi(".perl", $php_str)|| eregi("htaccess", $php_str)) {
  //   unlink($_L["ftmp"]);
  //   alert_print("html, php 등의 파일은 첨부가 불가능합니다");
  //   return;
  // }

  if ($_L["fn"] != "" && $_L["fs"] > 1) {
    $f_date = $_L["year_mon"];
    if (!file_exists($_L["save_dir"])) {
      mkdir($_L["save_dir"]);
      chmod($_L["save_dir"], 0777);
    }
    if (!file_exists($_L["save_dir"]."/".$f_date)) {
      mkdir($_L["save_dir"]."/".$f_date);
      chmod($_L["save_dir"]."/".$f_date, 0777);
    }
    $size1 = $_L["fs"];
    $filename_exe1 = explode(".",$_L["fn"]);
    $file1 = $f_date."/".$_L["uniq"]."_".$_L["idx"]."_".time().".".$filename_exe1[1];
    $file1_thum = $f_date."/".$_L["uniq"]."_".$_L["idx"]."_".time()."_thum.".$filename_exe1[1];

    if ($size1 > $_L["max_size"]) {
      alert_print("첨부된 파일 크기가 ".$_L["max_size"]."를 초과합니다.");
      return false;
    } else {
      $full_save1 = $_L["save_dir"]."/".$file1; // 경로와 파일명 합치기 (이미지파일)
      if ($_L["f"] == "") {
        $_L["f"] = $_L["ftmp"];
      }

      move_uploaded_file($_L["f"], $full_save1);
      chmod($full_save1, 0777);
      @unlink($_L["f"]);
      @unlink($_L["ftmp"]);
      $R["real"] = $file1;
      $R["thum"] = $file1_thum;
      $R["down"] = $_L["fn"];

      // 썸네일
      if ($_L["thumnail"] == true) {
        $Resize_info[org_file_name] = $_L["save_dir"]."/".$file1;
        $Resize_info[save_file_name] = $_L["save_dir"]."/".$file1_thum;
        $Resize_info[path] = $_L["thum_path"];
        $Resize_info[width] = $_L["thum_width"];
        $Resize_info[height] = $_L["thum_height"];
        F_resize_img2($Resize_info);
      }
      return $R;
    }
  } // 첨부파일이 있는경우
}

// 무질서 랜덤
function str_rand($length = 4, $char = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ") {
  if (!is_int($length) || $length < 0) {
    return false;
  }
  $string = "";
  for ($i = $length; $i > 0; $i--) {
    $string .= $char[mt_rand(0, strlen($char) - 1)];
  }
  return $string;
}

// 날짜 사이 월별계산
function getMonthNum($date1, $date2, $tags="-") {
  $date1 = explode($tags, $date1);
  $date2 = explode($tags, $date2);
  return abs($date1[0] - $date2[0]) * 12 - $date2[1] + abs($date1[1]);
}

// POST 방식 함수
function curl_post($url, $fields, $headers=null, $type=null) {
  /*
  데이터 형태
  $fields = array(
    'MY_EMAIL'=>$MY_EMAIL,
    'MY_KEY'=>$MY_KEY,
    'MY_NAME'=>$MY_NAME
  );

  $headers = array(
    "data1: test1",
    "data2: test2",
    "data3: test3"
  );
  */
  if ($type == "json") {
    $post_field_string = json_encode($fields);
  } else {
    $post_field_string = http_build_query($fields, '', '&');
  }
  $ch = curl_init();                                        // curl 초기화
  curl_setopt($ch, CURLOPT_URL, $url);                      // url 지정하기
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);           // 요청결과를 문자열로 반환
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);             // connection timeout : 10초
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);          // 원격 서버의 인증서가 유효한지 검사 여부
  if (!is_null($headers)) {
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);         // HEADER
  }
  curl_setopt($ch, CURLOPT_POSTFIELDS, $post_field_string); // POST DATA
  curl_setopt($ch, CURLOPT_POST, true);                     // POST 전송 여부
  $response = curl_exec($ch);
  if ($response === false) {
    $response = curl_error($ch);
  }
  curl_close ($ch);
  return $response;
}

// GET 방식 함수
// url = "/auth/confirm?tel=01012345678&confirm=1234"
function curl_get($url) {
  $src = "http://49.247.3.56";
  $url = $src.$url;
  $header = array(
    "Accept: application/json",
  );
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_HEADER, 0);
  curl_setopt($ch, CURLOPT_TIMEOUT, 1);
  // curl_setopt($ch, CURLOPT_TIMEOUT_MS, 500);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
  $response = curl_exec($ch);
  if (curl_error($ch)) {
    $response = array("result"=>false,"message"=>"잘못된 접근입니다.");
  }
  curl_close ($ch);
  return $response;
}

// 모바일여부체크
function isMobile() {
  if (isset($_SERVER["HTTP_X_WAP_PROFILE"])) {
    return false;
  }
  if (isset($_SERVER["HTTP_VIA"])) {
    return stristr($_SERVER["HTTP_VIA"], "wap") ? true : false;
  }
  if (isset($_SERVER["HTTP_USER_AGENT"])) {
    $clientkeywords = array(
      "nokia", "sony", "ericsson", "mot", "samsung", "htc", "sgh", "lg", "sharp", "sie-", "philips", "panasonic", "alcatel", "lenovo", "iphone", "ipod", "blackberry",
      "meizu", "android", "netfront", "symbian", "ucweb", "windowsce", "palm", "operamini", "operamobi", "openwave", "nexusone", "cldc", "midp", "wap", "mobile"
    );
    if (preg_match("/(".implode("|", $clientkeywords).")/i", strtolower($_SERVER["HTTP_USER_AGENT"]))) {
      return true;
    }
  }
  if (isset($_SERVER["HTTP_ACCEPT"])) {
    if ((strpos($_SERVER["HTTP_ACCEPT"], "vnd.wap.wml") !== false) &&
        (strpos($_SERVER["HTTP_ACCEPT"], "text/html") === false ||
        (strpos($_SERVER["HTTP_ACCEPT"], "vnd.wap.wml") < strpos($_SERVER["HTTP_ACCEPT"], "text/html")))) {
      return true;
    }
  }
  return false;
}

function getLaTime($dateTimeLa=null) {
  if (empty($dateTimeLa) == true) {
    $DateTime = new DateTime("now", new DateTimeZone("America/Los_Angeles"));
    $getDateTime = $DateTime->format("Y-m-d H:i:s");
    $timeLa = strtotime($getDateTime);
    $korTime = time();
  } else {
    $DateTimeKor = new DateTimeZone("Asia/Seoul");
    $DateTime = new DateTime($dateTimeLa, new DateTimeZone("America/Los_Angeles"));
    $DateTime->setTimezone($DateTimeKor);

    $getDateTime = $DateTime->format("Y-m-d H:i:s");
    $timeLa = strtotime($dateTimeLa);
    $korTime = strtotime($getDateTime);
  }

  $timeDiff = floor(($korTime - $timeLa) / 3600);

  if ($timeDiff < 17 ) {
    $summerYN = "Y";
  } else {
    $summerYN = "N";
  }

  $rtns["laTime"] = $timeLa;
  $rtns["summerYN"] = $summerYN;
  $rtns["timeDiff"] = $timeDiff;
  $rtns["korTime"] = $korTime;
  $rtns["laDateTime"]  = date("Y-m-d H:i:s", $timeLa);
  $rtns["korDateTime"] = date("Y-m-d H:i:s", $korTime);

  return $rtns;
}

/**
 * 문자발송
 * 뿌리오 업체 추가
 * 모아샷 업체 추가 (2023-02-21)
 * 뿌리오 업체 추가로 함수 변경
 */
function curl_sms($to, $from, $CODESK, $TEMPLET_NO, $MEMBER_NO, $MEMBER_NAME, $SUBJECT, $SENDMSG, $msg_type="SMS") {
  global $HTTP_HOST, $db, $sms_sender, $ICON_URL, $ACTION_URL;

  $from        = str_replace("-", "", $from);
  $to          = str_replace("-", "", $to);
  $name        = $to;
  $userId      = "";
  $testmode_yn = "";

  if ($MEMBER_NO != "") {
    $mem  = $db->get_data("SELECT * FROM MEMBER WHERE MEMBER_NO='{$MEMBER_NO}'");
    $name = $mem["NAME"];
    $userId = $mem["USER_ID"];
  }

  if ($TEMPLET_NO != "") {
    // 템플릿이 중지일 경우 체크 SLK-595
    $templet = $db->get_data("
      SELECT
        COUNT(*) AS CNT,
        (SELECT MUST FROM SMSTEMPLET WHERE TEMPLET_NO='{$TEMPLET_NO}') AS MUST
      FROM
        SMSTEMPLET
      WHERE
        TEMPLET_NO='{$TEMPLET_NO}' AND STOPYN = 'Y'
      LIMIT 1
    ");

    if ($templet["CNT"] > 0) {
      $retArr = json_decode('{"result_code":"2"}');
      return $retArr;
      exit;
    }
  }

  // 알림 발송 (알림 발송 성공 시 'false' return)
  $IS_CONTINUE = sendNotification($userId, $SUBJECT, $SENDMSG, $ICON_URL, $ACTION_URL);

  // 1:1문의 답글 등록 시 webpush만 발송 SLK-972
  if ($TEMPLET_NO == "49") {
    return false;
  }

  // SMS 발송 여부 체크
  if (!$IS_CONTINUE && $templet['MUST'] == 'N') {
    return false;
    exit;
  }

  // 문자노리
  if ($sms_sender == "smsnori") {
    require_once $_SERVER["DOCUMENT_ROOT"]."/_library/MotHead.lib.php";

    $MH_rd = array();
    $MH_rd["U_CODE"]      = "";   // 발급받은 키 사이트의 기업연동->연동하기를 통해 발급받으세요.
    $MH_rd["U_FROM_NUM"]  = $from;                  // 발신자번호
    $MH_rd["U_TO_NUM"]    = $to;                    // 받는사람번호 여러개인경우 ','로 구분 최대 100개
    $MH_rd["U_SUBJECT"]   = $SUBJECT;               // LMS, MMS 일때 문자 제목
    $MH_rd["U_MSG"]       = stripslashes($SENDMSG); // 문자내용

    $MH_rd["U_SEND_DATE"] = "";                     // 발송예약일 현재시각 기준 30분 이후로 설정 가능 (Beta)
    //$MH_rd["U_TYPE"]    = 1;                      //문자 종류 1:단문 2:장문
    $MH_rd["U_TYPE"]      = (strlen($MH_rd["U_MSG"]) > 90 || $msg_type == "LMS") ? 2 : 1;
    $MH_ed["U_VAL"]       = "";                     //사용자 임의 변수 U_VAL 로 받을수 있음

    $MotHead = new MotHead_Send();

    $S_SR = $MotHead->Send($MH_rd);

    $S_status = $S_SR["status"]; // 서버통신 성공여부 true, false
    $S_code   = $S_SR["code"];   // 해당 요청건의 고유코드 (Report 받을시 사용)
    $S_result = $S_SR["result"]; // 0:실패 1:성공
    $S_msg    = $S_SR["msg"];    // 에러메세지
    $S_count  = $S_SR["count"];  // 전송 요청 건수 -중복 자동 제거됨
    $S_u_val  = $S_SR["U_VAL"];  // 사용자 임의변수

    // return 값을 알리고 형태로 변환
    $retArr = json_decode('{"result_code":"'.($S_result==1 ? $S_result : $S_msg).'"}');

  // 모아샷
  } else if ($sms_sender =="moashot") {
    /* 사용자 인증정보 */
    $sms_url           = "http://biz.moashot.com/EXT/URLASP/mssendUTF.asp"; // 전송요청 URL
    $sms["uid"]        = "suloko"; // SMS 아이디
    $sms["pwd"]        = "tnfhzh8787@";

    // $sms["commCode"]   = "";//인증키 md5
    // $sms["commType"]   = "1"; -> 발송시 비밀번호 에러가 남


    /* 발송 내용 */
    $sms["contents"]   = stripslashes($SENDMSG);
    $sms["toNumber"]   = $to; // 수신번호
    $sms["fromNumber"] = $from; // 발신번호
    // $sms["startTime"]  = $rdate; // 예약일자 yyyymmddhhmiss(20160101093000)
    $sms["title"]      = $SUBJECT;
    $sms["returnType"] = 3;

    /* sendType
    · 3 : 단문문자(SMS)
    · 5 : 장문문자(LMS)
    · 6 : 이미지를 포함한 문자(MMS)
    */
    $len = strlen($sms["contents"]);
    if ($len > 90 || $msg_type == "LMS") {
      $sms["sendType"] = "5";
    } else {
      $sms["sendType"] = "3";
    }

    /* post request */
    $postdata = http_build_query($sms);
    $opts = array("http" =>
      array(
        "method" => "POST",
        "header" => "Content-type: application/x-www-form-urlencoded",
        "content" => $postdata
      )
    );
    $context = stream_context_create($opts);
    $result = file_get_contents($sms_url, false, $context);
    //echo $result;

    // return 값을 알리고 형태로 변환
    $retArr = json_decode('{"result_code":"'.($result=="SUCCESS:" ? "1" : "Err").'"}');

  // 뿌리오
  } else if ($sms_sender == "ppurio") {
    $_api_url = "https://message.ppurio.com/api/send_utf8_json.php"; // UTF-8 인코딩과 JSON 응답용 호출 페이지
    $_param["userid"]   = "";                 // [필수] 뿌리오 아이디
    $_param["callback"] = $from;                    // [필수] 발신번호 - 숫자만
    $_param["phone"]    = $to;                      // [필수] 수신번호 - 여러명일 경우 |로 구분 "010********|010********|010********"
    $_param["msg"]      = stripslashes($SENDMSG);   // [필수] 문자내용 - 이름(names)값이 있다면 [*이름*]가 치환되서 발송됨
    //$_param["names"] = "홍길동";                    // [선택] 이름 - 여러명일 경우 |로 구분 "홍길동|이순신|김철수" -> 내용에 치환된다.
    //$_param["appdate"] = "20190502093000";        // [선택] 예약발송 (현재시간 기준 10분이후 예약가능)
    $_param["subject"]  = $SUBJECT;                 // [선택] 제목 (30byte)
    //$_param["file1"] = "@이미지파일경로;type=image/jpg"; // [선택] 포토발송 (jpg, jpeg만 지원  300 K  이하)

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
    $sms_url = "https://apis.aligo.in/send/"; // 전송요청 URL
    $sms["user_id"] = ""; // SMS 아이디
    $sms["key"] = ""; // 인증키

    $sms["msg"]         = stripslashes($SENDMSG);
    $sms["receiver"]    = $to;       // 수신번호
    $sms["destination"] = $name;     // 수신자
    $sms["sender"]      = $from;     // 발신번호
    $sms["rdate"]       = $rdate;    // 예약일자
    $sms["rtime"]       = $rtime;    // 예약시간
    $sms["testmode_yn"] = empty($testmode_yn) ? "" : $testmode_yn;
    $sms["title"]       = $SUBJECT;
    $sms["msg_type"]    = $msg_type; // SMS, LMS, MMS등 메세지 타입을 지정

    $len = strlen($sms["msg"]);
    if ($len > 120 || $msg_type == "LMS") {
      $sms["msg_type"] = "LMS";
    } else {
      $sms["msg_type"] = "SMS";
    }

    $host_info = explode("/", $sms_url);
    $port = $host_info[0] == "https:" ? 443 : 80;

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
    //print_r($retArr); // Response 출력 (연동작업시 확인용)
  }

  F_sms_log(array(
    "mode"        => "insert",
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

function fix_json_encode() {
  function json_encode($var) {
    switch (gettype($var)) {
      case "boolean":
        return $var ? "true" : "false"; // Lowercase necessary!
      case "integer":
      case "double":
        return $var;
      case "resource":
      case "string":
        return '"'. str_replace(array("\r", "\n", "<", ">", "&"),
          array('\r', '\n', '\x3c', '\x3e', '\x26'),
          addslashes($var)) .'"';
      case "array":
        // Arrays in JSON can't be associative. If the array is empty or if it
        // has sequential whole number keys starting with 0, it's not associative
        // so we can go ahead and convert it as an array.
        if (empty ($var) || array_keys($var) === range(0, sizeof($var) - 1)) {
          $output = array();
          foreach ($var as $v) {
            $output[] = json_encode($v);
          }
          return "[ ".implode(", ", $output)." ]";
        }
        // Otherwise, fall through to convert the array as an object.
      case "object":
        $output = array();
        foreach ($var as $k => $v) {
          $output[] = json_encode(strval($k)) .": ". json_encode($v);
        }
        return "{ ".implode(", ", $output)." }";
      default:
        return "null";
    }
  }

  function json_decode($json, $assoc = true) {
    $comment = false;
    $out     = "$x=";
    $json    = preg_replace('/:([^"}]+?)([,|}])/i', ':"\1″\2', $json);
    for ($i = 0; $i < strlen($json); $i++) {
      if (!$comment) {
        if (($json[$i] == "{") || ($json[$i] == "[")) {
          $out .= "array(";
        } else if (($json[$i] == "}") || ($json[$i] == "]")) {
          $out .= ")";
        } else if ($json[$i] == ":") {
          $out .= "=>";
        } else if ($json[$i] == ",") {
          $out .= ",";
        } else if ($json[$i] == '"') {
          $out .= '"';
        }
      } else {
        $out .= $json[$i] == "$" ? "\$" : $json[$i];
      }
      if ($json[$i] == '"' && $json[($i-1)] != "\\") {
        $comment = !$comment;
      }
    }
    eval($out. ";");
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

if (!function_exists("session_register")) {
  fix_session_register();
}
if (!function_exists("json_encode")) {
  fix_json_encode();
}

// INJECTION 임시 함수 (SLK-434)
function xss_clean($data) {
  $data = str_replace(array('select', 'from', 'union', 'where', 'ORD'), array('', '', '', '', ''), $data);
  $data = str_replace(array('SELECT', 'FROM', 'UNION', 'WHERE', 'ORD'), array('', '', '', '', ''), $data);
  $data = str_replace(array('&', '<', '>'), array('&amp;', '&lt;', '&gt;'), $data);
  $data = str_replace(array('XOR(i', 'sleep(', 'RECEIVE_MESSAGE', 'CHR(', 'ORD'), array('', '', '', '', ''), $data);
  $data = preg_replace('/(&#*\w+)[\x00-\x20]+;/u', '$1;', $data);
  $data = preg_replace('/(&#x*[0-9A-F]+);*/iu', '$1;', $data);
  $data = html_entity_decode($data, ENT_COMPAT, 'UTF-8');

  //Remove any attribute starting with "on" or xmlns
  $data = preg_replace('#(<[^>]+?[\x00-\x20"\'])(?:on|xmlns)[^>]*+>#iu', '$1>', $data);

  //Remove javascript: and vbscript: protocols
  $data = preg_replace('#([a-z]*)[\x00-\x20]*=[\x00-\x20]*([`\'"]*)[\x00-\x20]*j[\x00-\x20]*a[\x00-\x20]*v[\x00-\x20]*a[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu', '$1=$2nojavascript...', $data);
  $data = preg_replace('#([a-z]*)[\x00-\x20]*=([\'"]*)[\x00-\x20]*v[\x00-\x20]*b[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu', '$1=$2novbscript...', $data);
  $data = preg_replace('#([a-z]*)[\x00-\x20]*=([\'"]*)[\x00-\x20]*-moz-binding[\x00-\x20]*:#u', '$1=$2nomozbinding...', $data);

  //Only works in IE: <span style="width: expression(alert('Ping!'));"></span>
  $data = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?expression[\x00-\x20]*\([^>]*+>#i', '$1>', $data);
  $data = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?behaviour[\x00-\x20]*\([^>]*+>#i', '$1>', $data);
  $data = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:*[^>]*+>#iu', '$1>', $data);

  //Remove namespaced elements (we do not need them)
  $data = preg_replace('#</*\w+:\w[^>]*+>#i', '', $data);

  do {
    //Remove really unwanted tags
    $old_data = $data;
    $data = preg_replace('#</*(?:applet|b(?:ase|gsound|link)|embed|frame(?:set)?|i(?:frame|layer)|l(?:ayer|ink)|meta|object|s(?:cript|tyle)|title|xml)[^>]*+>#i', '', $data);
  }
  while($old_data !== $data);

  //we are done...
  return $data;
}

// 매출 퍼센트(2023-01-11/chabes)
function get_Percent($s, $e) {
  $val["N"] = $e - $s;
  $val["P"] = round(($s/$e) * 100 ,1);
  $val["P"] .= "%";
  return $val;
}

function index_act($v, $s) {
  if ($v > 0) {
    $val = "<font style='font-size: 8pt; color: red;'>";
    if ($s != "Y") {
    $val .= "▲";
    }
    $val .= $v."</font>";
  } else if ($v < 0 ) {
    $val = "<font style='font-size: 8pt; color: blue;'>";
    if ($s != "Y") {
      $val .= "▼";
    }
    $val .= $v."</font>";
  } else {
    $val = "<font style='font-size: 8pt; color: #000000;'>-</font>";
  }
  return $val;
}

// 퍼센트 계산 ()
function simple_percent($num, $sum) {
  if ($num == 0 || $sum ==0) return 0;
  return round($num / $sum * 100, 1);
}

// 파트너 코드 처리-SLK-647
function exec_partner($data, $device) {
  global $db;
  if ($data) {
    $data = xss_clean($data);
    $_REFERER = $_SERVER["HTTP_REFERER"];
    $IS_PARTNER = false;

    // 기존 세션파트너 아이디와 cp_id가 다를 경우에만 세션 초기화
    if ($_SESSION["PARTNER_ID"] && $data != $_SESSION["PARTNER_ID"]) {
      session_unregister("PARTNER_ID");
    }

    // 정상 partner idx 확인
    $cnt = $db->get_data("SELECT COUNT(IDX) AS CNT FROM `UM_PARTNER` WHERE `PARTNER_ID`= '".$data."'");
    if ($cnt["CNT"] > 0) {
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
      case "VISIT":   // 방문시
        $_FIELDS = "VISIT_CNT";
        break;
      case "REG":     // 회원가입시
        $_FIELDS = "MEMBER_CNT";
        break;
      case "REG_OUT": // 회원탈퇴시
        $_FIELDS = "OMEMBER_CNT";
        break;
      case "PAY":     // 결제시
        $_FIELDS = "CASH_CNT";
        break;
      case "PAY_OUT": // 결제취소시
        $_FIELDS = "CCASH_CNT";
        break;
    }

    $WhereIs = " WHERE PARTNER_ID= '".$_PARTNER_ID."'";
    $WhereIs .= " AND SUMM_DATE = '".$Visit_Date."'";
    $WhereIs .= " AND SUMM_TIME = ".$Visit_Time;
    $SQL = "SELECT COUNT(IDX) AS CNT FROM UM_SUMMARY ";
    $SQL .= $WhereIs;
    $cnt = $db->get_data($SQL);

    // 정보 업데이트 또는 추가
    if ($cnt["CNT"] > 0 ) {
      $SQL = "UPDATE UM_SUMMARY SET ".$_FIELDS." = ".$_FIELDS." + 1";
      $SQL .= $WhereIs;
    } else {
      $SQL = "INSERT INTO UM_SUMMARY (PARTNER_ID, SUMM_DATE, SUMM_TIME, ".$_FIELDS.") VALUES ('".$_PARTNER_ID."', '".$Visit_Date."', '".$Visit_Time."', 1)";
    }
    $db->query($SQL);
  }
}

// 파트너 첫결제 처리
function exec_partner_firstsale($_MEM_USER_ID, $_PARTNER_ID, $_MEM_REG_DATE) {
  global $db;
  $is_first = false;

  // 첫번째 결제인지 확인(회원)
  $query = "SELECT COUNT(CASH_NO) AS CNT FROM CASH WHERE USER_ID = '".$_MEM_USER_ID."' AND IS_USE = 'Y'";
  $is_first_sale = $db->get_data($query);
  if ($is_first_sale["CNT"] <= 0) {
    $is_first = true;
  }

  // 첫번째 결제일때만 처리
  if ($is_first) {
    // 파트너 설정 정보
    $query = "SELECT FIRST_SALE FROM UM_COMM_POLICY C INNER JOIN UM_PARTNER AS P ON C.IDX=P.COMM_POLICY_IDX";
    $query.= " WHERE P.PARTNER_ID = '".$_PARTNER_ID."'";
    $partner_first_sale = $db->get_data($query);
    $FIRST_SALE = $partner_first_sale["FIRST_SALE"]; // 첫결제 기준(null: 당일 / nullX: 단위:일)

    $mem_regdate = date("Y-m-d", strtotime($_MEM_REG_DATE));
    $now_date = date("Y-m-d");
    $diff_time = abs(strtotime($mem_regdate) - strtotime($now_date));
    $diff_day = ceil($diff_time / (60*60*24)); // 가입일 및 현재 일까지 지난 일수(설정된 첫결제값과 비교 용도)

    // 첫 결제 기준이 null일 경우 당일, 이외일 경우(숫자)는 가입일로 부터 +설정값
    if ($FIRST_SALE) { // 첫결제 기준이 일단위
      if ($diff_day <= $FIRST_SALE) {
        $PARTNER_ID = $_PARTNER_ID;
      } else {
        $PARTNER_ID = "";
      }
    } else { // 첫결제 기준이 당일
      if ($diff_day == 0) {
        $PARTNER_ID = $_SESSION["PARTNER_ID"];
      } else {
        $PARTNER_ID = "";
      }
    }
  } else {
    $PARTNER_ID = "";
  }
  return $PARTNER_ID;
}

// 숫자에 조 단위 추가
function getNumberStringKorean($num_value) {
  // 1 ~ 9 한글 표시
  $arrNumberWord = array("0", "1", "2", "3", "4", "5", "6", "7", "8", "9");
  // 10, 100, 1000 자리수 한글 표시
  $arrDigitWord = array("", "", "", ",");
  // 만단위 한글 표시
  $arrManWord = array("억", "조", "");

  $num_length = strlen($num_value);
  $han_value = "";
  $man_count = 0; // 만단위 0이 아닌 금액 카운트.

  if ($num_length > 4) {
    for ($i = 0; $i < $num_length; $i++) {
      // 1단위의 문자로 표시.. (0은 제외)
      $strTextWord = $arrNumberWord[substr($num_value, $i, 1)];
      // 0이 아닌경우만, 십/백/천 표시
      if ($strTextWord != "") {
        $man_count++;
        $strTextWord = $strTextWord.$arrDigitWord[($num_length - ($i + 1)) % 4];
      }

      // 만단위마다 표시 (0인경우에도 만단위는 표시한다)
      if ($man_count != 0 && ($num_length - ($i + 1)) % 4 == 0) {
        $man_count = 0;
        $strTextWord = $strTextWord.$arrManWord[($num_length - ($i + 1)) / 4];
      }

      if ($han_value != '' && $strTextWord == 0) {
        $end = explode("조", $han_value);
        $end = end($end);
      } else {
        $end = 1;
      }

      if ($strTextWord != '0,' && $end != '') {
        $han_value = $han_value.$strTextWord;
      }
    }
  } else {
    $han_value = number_format($num_value)."억";
  }

  if ($num_value != 0)
    $han_value . "";

  return $han_value;
}

/**
 * Send notification & save log
 */
function sendNotification($USER_ID, $SUBJECT, $SENDMSG, $ICON_URL, $ACTION_URL) {
  global $db;

  // SMS 발송 여부
  $IS_CONTINUE = true;

  // Get Token List
  $IS_TOKEN = $db->get_list("SELECT DISTINCT TOKEN FROM MEMBER_TOKEN WHERE USER_ID = '{$USER_ID}' AND STATUS = 'Y'");

  if (empty($IS_TOKEN) === false) {
    if (count($IS_TOKEN['TOKEN']) > 0) {
      require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_NOTIFICATION_LOG.php";

      // 단건(token) 발송 시 true
      $isToken = true;

      foreach ($IS_TOKEN['TOKEN'] as $key => $value) {

        // Default status
        $is_success = 'Y';
        $error_code = NULL;
        $message    = NULL;

        $result = sendFcmPush($value, $SUBJECT, $SENDMSG, $ICON_URL, $ACTION_URL, $isToken);

        // 발송 로그 저장
        $res = json_decode($result);

        // 발송 결과 처리
        if (is_null($res)) { // curl 오류
          $is_success = 'N';
          $error_code = "500";
          $message    = $result; // 오류메세지를 리턴받음
        } else if (isset($res->error)) { // push 실패시
          $is_success = 'N';
          $error_code = $res->error->code;
          $message    = $res->error->status;

          // 유효하지 않은 Token의 상태 업데이트
          $db->query("UPDATE MEMBER_TOKEN SET STATUS = 'N' WHERE USER_ID = '{$USER_ID}' AND TOKEN = '{$value}'");
        } else if (isset($res->name)) {
          // Token 발송이 한 건이라도 성공하면 SMS 발송 중지
          $IS_CONTINUE = false;
        }

        // 로그 저장
        $push_log = array();
        $push_log['mode'] = 'insert';
        $push_log['TYPE'] = 'S';
        $push_log['USER_ID'] = $USER_ID;
        $push_log['TOKEN'] = $value;
        $push_log['TOPIC'] = null;
        $push_log['TITLE'] = $SUBJECT;
        $push_log['CONTENT'] = $SENDMSG;
        $push_log['URL'] = $ACTION_URL;
        $push_log['IMG_URL'] = $ICON_URL;
        $push_log['IS_SUCCESS'] = $is_success;
        $push_log['ERROR_CODE'] = $error_code;
        $push_log['ERROR_MESSAGE'] = $message;
        F_NOTIFICATION_LOG($push_log);
      };
    }
  }
  return $IS_CONTINUE;
}

/**
 * Send Web Push(FCM)
 */
function sendFcmPush($to, $title, $content, $ICON_URL, $ACTION_URL, $isToken = false) {
  global $FCM_PRIVATE_KEY, $FCM_URL;

  putenv("GOOGLE_APPLICATION_CREDENTIALS={$FCM_PRIVATE_KEY}");
  $scope = 'https://www.googleapis.com/auth/firebase.messaging';
  $client = new Google_Client();
  $client->useApplicationDefaultCredentials();

  $client->setScopes($scope);
  $auth_key = $client->fetchAccessTokenWithAssertion();

  //echo $auth_key['access_token'];

  // Token이 아니면 Topic으로 발송
  $sendto = empty($isToken) ? 'topic' : 'token';

  $HEADER = array(
    "Authorization: Bearer {$auth_key['access_token']}",
    "Content-Type: application/json"
  );

  $NOTIFICATION = array(
    'title'  => $title,
    'body'   => $content
  );

  $ANDROID = array(
    'notification' => array(
      'icon'          => $ICON_URL,
      'click_action'  => $ACTION_URL,
      'default_sound' => true
    ),
    'priority' => 'high',
    'direct_boot_ok' => true
  );

  $WEBPUSH = array(
    'notification' => array(
      'icon'          => $ICON_URL,
      'click_action'  => $ACTION_URL,
      'default_sound' => true
    )
  );

  $MESSAGE = array(
    $sendto => $to,
    'notification' => $NOTIFICATION,
    'android' => $ANDROID,
    'webpush' => $WEBPUSH
  );

  $DATA = array (
    'message' => $MESSAGE
  );

  // __________(S) SEND PUSH
  $result = curl_post($FCM_URL, $DATA, $HEADER, "json");
  // __________(E) SEND PUSH

  return $result;
}

function setBetweenDate($schdate) {
  $toTime = time();

  switch($schdate) {
    case '1D':
      $timeS  = $toTime - (0);
      break;
    case '2D':
      $timeS  = $toTime - (86400);
      break;
    case 'YD':
      $timeS = $toTime;
      $toTime = $timeS;
      break;
    case '7D':
      $timeS = $toTime - (86400 * 6);
      break;
    case '15D':
      $timeS = $toTime - (86400 * 14);
      break;
    case '1M':
      $timeS = mktime(0, 0, 0, date('m') - 1, date('d') - 1, date('Y'));
      break;
    case '3M':
      $timeS = mktime(0, 0, 0, date('m') - 3, date('d') - 1, date('Y'));
      break;
    case '6M':
      $timeS = mktime(0, 0, 0, date('m') - 6, date('d') - 1, date('Y'));
      break;
    case '1Y':
      $timeS = mktime(0, 0, 0, date('m'), date('d') - 1, date('Y') - 1);
      break;
  }

  $time_set = array(
    "sdate" => date('Y-m-d', $timeS),
    "edate" => date('Y-m-d', $toTime)
  );

  return $time_set;
}

// LA 시간 기준 DST 여부 확인 - 1 dst, 0 nomal
function check_dst($d) {
  date_default_timezone_set("America/Los_Angeles"); // 시간대 변경
  $dst = date("I", strtotime($d)); // DST 체크
  date_default_timezone_set("Asia/Seoul"); // 시간대 원복
  return $dst;
}

// curl 이용한 api 호출
function callApi($method, $url, $query, $headers = null) {
  $method = strtoupper($method);
  $curl   = curl_init();

  if ($method !== "GET") {
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
    if ($query) curl_setopt($curl, CURLOPT_POSTFIELDS, $query);
  } else if ($query) {
    $url = sprintf("%s?%s", $url, http_build_query($query));
  }

  if (!is_null($headers)) {
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers );
  }

  curl_setopt_array($curl, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT        => 30
  ]);

  $response = [
    curl_exec($curl),
    curl_errno($curl),
    curl_error($curl),
    curl_getinfo($curl, CURLINFO_HTTP_CODE)
  ];

  curl_close($curl);
  return $response;
}

// USA 전송
function sendRequestToUSAServer($type, $postData) {
  global $usApiDomain, $usClientKey;

  $url = $usApiDomain . "{$type}.php";
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
    CURLOPT_CONNECTTIMEOUT => 20,
    CURLOPT_TIMEOUT        => 20,
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
    syslog(LOG_DEBUG, "SES Client Initialization Error: " . $e->getMessage());
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


## 결제 이벤트 대상 확인
// function chk_event_user($u) {
//   global $db;

//   $start_date = "2025-03-10 19:00:00"; // 이벤트 시작일시
//   $current_date = date("Y-m-d H:i:s"); // 현재시간

//   $result = false;
//   if ($current_date >= $start_date) { // 이벤트 기간 내에 있는지 확인
//     $info = $db->get_data("SELECT USER_ID, CASH_NO FROM MEMBER_EVENT_250310 WHERE USER_ID='{$u}'");
//     if (isset($info["USER_ID"]) === true && is_null($info["CASH_NO"]) === true) { // 이벤트 참여자인지 확인 (CASH_NO가 NULL인 경우만 참여 가능)
//       $result = true; // 이벤트 참여 가능
//     }
//   }
//   return $result;
// }
## 미접속자
function chk_event_user_a($u) {
  global $db;

  $start_date = "2025-05-13 12:00:00"; // 이벤트 시작일시
  $current_date = date("Y-m-d H:i:s"); // 현재시간

  $result = false;
  if ($current_date >= $start_date) { // 이벤트 기간 내에 있는지 확인
    $info = $db->get_data("SELECT USER_ID, CASH_NO, EVENT_AT FROM MEMBER_EVENT_250513_a WHERE USER_ID='{$u}'");
    if (isset($info["USER_ID"]) === true && is_null($info["CASH_NO"]) === true) { // 이벤트 참여자인지 확인 (CASH_NO가 NULL인 경우만 참여 가능)
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
    if (isset($info["USER_ID"]) === true && is_null($info["CASH_NO"]) === true) { // 이벤트 참여자인지 확인 (CASH_NO가 NULL인 경우만 참여 가능)
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
    if (isset($info["USER_ID"]) === true && is_null($info["CASH_NO"]) === true) { // 이벤트 참여자인지 확인 (CASH_NO가 NULL인 경우만 참여 가능)
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
    if (isset($info["USER_ID"]) === true && is_null($info["CASH_NO"]) === true) { // 이벤트 참여자인지 확인 (CASH_NO가 NULL인 경우만 참여 가능)
      $result = true; // 이벤트 참여 가능
    }
  }
  return $result;
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
  if (strpos($ipaddress, " ") !== false) $ipaddress = explode(" ", $ipaddress)[0]; // 공백을 포한할 경우 첫번째 인자만 사용 (프록시 사용시)
  return str_replace(",", "", substr($ipaddress,0,15));
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


/**
 * 게임번호 스타일
 * ball 복권번호
 * type 복권종류
 */
function gamenumber_style($ball,$type) {
  $color = ($type == "MM") ? "blue" : "red";
  $ball = explode(",",$ball);
  $lastKey = count($ball) - 1;
  $ball[$lastKey] = "<span style='color:{$color};font-weight: 500;'>{$ball[$lastKey]}</span>";
  $ball = implode(",",$ball);
  return $ball;
}

/**
 * 입금금액(AMOUNT)으로 CASH_CONFIG 조회
 */
function get_cash_config_by_amount($amount) {
  global $db;

  $amount = (int)$amount;
  if ($amount <= 0) {
    return null;
  }

  $config = $db->get_data("SELECT * FROM CASH_CONFIG WHERE AMOUNT = {$amount} LIMIT 1");
  if (!empty($config)) {
    return $config;
  }

  $cash = round($amount / 1.1);
  $config = $db->get_data("SELECT * FROM CASH_CONFIG WHERE CASH = {$cash} LIMIT 1");
  return !empty($config) ? $config : null;
}

/**
 * 캐시금액으로 CASH_CONFIG 조회
 */
function get_cash_config_by_cash($cash) {
  global $db;

  $cash = (int)$cash;
  if ($cash <= 0) {
    return null;
  }

  $config = $db->get_data("SELECT * FROM CASH_CONFIG WHERE CASH = {$cash} LIMIT 1");
  return !empty($config) ? $config : null;
}

/**
 * 입금금액 기준 지급 캐시 계산 (CASH_CONFIG 우선, 없으면 VAT 역산)
 */
function calc_cash_supply_by_price($price, $is_event_user = false) {
  if ($is_event_user === true) {
    return round($price / 0.75 / 1.1);
  }

  $config = get_cash_config_by_amount($price);
  if (!empty($config) && (int)$config['CASH'] > 0) {
    return (int)$config['CASH'];
  }

  return round($price / 1.1);
}

/**
 * CASH_CONFIG 금액→캐시 매핑 (JS용)
 */
function get_cash_amount_map() {
  global $db;

  $map = array();
  $list = $db->get_list("SELECT AMOUNT, CASH FROM CASH_CONFIG ORDER BY AMOUNT ASC");
  if (!empty($list['AMOUNT'])) {
    for ($i = 0; $i < count($list['AMOUNT']); $i++) {
      $map[(int)$list['AMOUNT'][$i]] = (int)$list['CASH'][$i];
    }
  }
  return $map;
}

/**
 * 관리자별 카드결제 숨김설정 (admin205가 관리)
 * hide_card_wayup : 22만 카드결제 숨김 (기본: admin204만 Y)
 * hide_card_120k  : 12만 카드결제 숨김 (기본 해지, 상품 주문내역 제외)
 */
function get_staff_hide_config_defaults($user_id = '') {
  // 기존 admin204 기본값 유지(22만만), 12만은 기본 해지(N)
  $default_card = ($user_id === 'admin204') ? 'Y' : 'N';
  return array(
    'hide_bank_200k'  => 'N',
    'hide_card_wayup' => $default_card,
    'hide_card_120k'  => 'N',
    'hide_main_sales' => 'N',
  );
}

function ensure_staff_hide_config_table() {
  global $db;
  static $ready = false;
  if ($ready) {
    return true;
  }

  $db->query("
    CREATE TABLE IF NOT EXISTS STAFF_HIDE_CONFIG (
      USER_ID VARCHAR(50) NOT NULL,
      HIDE_CARD_WAYUP CHAR(1) NOT NULL DEFAULT 'N',
      HIDE_CARD_120K CHAR(1) NOT NULL DEFAULT 'N',
      UPDATED_AT DATETIME DEFAULT NULL,
      PRIMARY KEY (USER_ID)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  ");

  // 기존 테이블에 컬럼 추가 (query 실패 시 exit 되므로 사전 체크)
  $col_cnt = (int)$db->get_data_one("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'STAFF_HIDE_CONFIG' AND COLUMN_NAME = 'HIDE_CARD_120K'");
  if ($col_cnt < 1) {
    $db->query("ALTER TABLE STAFF_HIDE_CONFIG ADD COLUMN HIDE_CARD_120K CHAR(1) NOT NULL DEFAULT 'N' AFTER HIDE_CARD_WAYUP");
  }

  // 기존 ADMIN204_HIDE_CONFIG → admin204 계정 설정 이전
  try {
    $cnt = (int)$db->get_data_one("SELECT COUNT(*) FROM STAFF_HIDE_CONFIG WHERE USER_ID = 'admin204'");
    if ($cnt < 1) {
      $old = $db->get_data("SELECT HIDE_CARD_WAYUP FROM ADMIN204_HIDE_CONFIG WHERE ID = 1 LIMIT 1");
      if (!empty($old) && isset($old['HIDE_CARD_WAYUP'])) {
        $hide = ($old['HIDE_CARD_WAYUP'] == 'Y') ? 'Y' : 'N';
        $db->query("INSERT INTO STAFF_HIDE_CONFIG (USER_ID, HIDE_CARD_WAYUP, HIDE_CARD_120K, UPDATED_AT) VALUES ('admin204', '{$hide}', 'N', NOW())");
      }
    }
  } catch (Exception $e) {
    // ignore migrate errors
  }

  $ready = true;
  return true;
}

function get_staff_hide_config($user_id) {
  global $db;
  $user_id = trim((string)$user_id);
  $default = get_staff_hide_config_defaults($user_id);
  if ($user_id === '') {
    return $default;
  }

  try {
    ensure_staff_hide_config_table();
    $safe_id = addslashes($user_id);
    $row = $db->get_data("SELECT HIDE_CARD_WAYUP, HIDE_CARD_120K FROM STAFF_HIDE_CONFIG WHERE USER_ID = '{$safe_id}' LIMIT 1");
    if (!empty($row)) {
      return array(
        'hide_bank_200k'  => 'N',
        'hide_card_wayup' => ($row['HIDE_CARD_WAYUP'] == 'Y') ? 'Y' : 'N',
        'hide_card_120k'  => (isset($row['HIDE_CARD_120K']) && $row['HIDE_CARD_120K'] == 'Y') ? 'Y' : 'N',
        'hide_main_sales' => 'N',
      );
    }
  } catch (Exception $e) {
    // fallback
  }
  return $default;
}

function save_staff_hide_config($user_id, $cfg) {
  global $db;
  $user_id = trim((string)$user_id);
  if ($user_id === '') {
    return false;
  }

  $hide_card = (isset($cfg['hide_card_wayup']) && $cfg['hide_card_wayup'] == 'Y') ? 'Y' : 'N';
  $hide_120k = (isset($cfg['hide_card_120k']) && $cfg['hide_card_120k'] == 'Y') ? 'Y' : 'N';
  ensure_staff_hide_config_table();
  $safe_id = addslashes($user_id);
  $db->query("
    INSERT INTO STAFF_HIDE_CONFIG (USER_ID, HIDE_CARD_WAYUP, HIDE_CARD_120K, UPDATED_AT)
    VALUES ('{$safe_id}', '{$hide_card}', '{$hide_120k}', NOW())
    ON DUPLICATE KEY UPDATE
      HIDE_CARD_WAYUP = '{$hide_card}',
      HIDE_CARD_120K = '{$hide_120k}',
      UPDATED_AT = NOW()
  ");
  return true;
}

// 하위호환 래퍼 (기존 호출부 유지)
function get_admin204_hide_config_defaults() {
  return get_staff_hide_config_defaults('admin204');
}
function ensure_admin204_hide_config_table() {
  return ensure_staff_hide_config_table();
}
function get_admin204_hide_config() {
  return get_staff_hide_config('admin204');
}
function save_admin204_hide_config($cfg) {
  return save_staff_hide_config('admin204', $cfg);
}

/**
 * 현재 로그인 관리자의 카드결제 숨김설정 여부
 */
function is_admin204_hide($key) {
  global $S_login;
  if (!isset($S_login['user_id']) || $S_login['user_id'] === '') {
    return false;
  }
  $cfg = get_staff_hide_config($S_login['user_id']);
  return isset($cfg[$key]) && $cfg[$key] == 'Y';
}

/**
 * 카드결제 숨김 적용 시작일시
 * - 22만 숨김: 이 일시 이상인 카드 220000만 매출에서 제외 (이전 22만은 집계 유지)
 */
function get_staff_hide_from_datetime() {
  return '2026-08-07 00:00:00';
}

/**
 * 카드결제 숨김 SQL 조건 모음
 *
 * [22만 카드결제 숨김 ON] hide_card_wayup
 *  - 2026-08-07 이후 카드 PRICE 220000만 제외 (그 이전 22만은 집계 유지, 무통장 유지)
 *  - 캐시관리: 적용일 이후 PRICE 220000 또는 CASH 200000 제외
 *  - 월별통계(month2): 적용일 이후 CASH 200000 제외
 *  - 상품 주문내역: 적용일 이후 결제금액 123000 제외
 *
 * [12만 카드결제 숨김 ON] hide_card_120k  ※ 상품 주문내역 페이지 제외, 날짜 제한 없음
 *  - 카드 PRICE 132000 / 캐시관리 PRICE 132000·CASH 120000 / month2 CASH 120000 제외
 */

/**
 * 메인·카드결제로그·통계용 CASH.PRICE 필터
 * @param string $prefix 컬럼 prefix
 * @param string|null $date_expr 날짜 컬럼 (null이면 CON_REG_DATE 우선)
 */
function get_admin204_main_sales_filter($prefix = '', $date_expr = null) {
  $p = $prefix;
  if ($date_expr === null || $date_expr === '') {
    $date_expr = "IFNULL({$p}CON_REG_DATE, {$p}REG_DATE)";
  }
  $from = get_staff_hide_from_datetime();
  $conds = array();

  // 22만: 적용일 이후 카드 220000만 제외
  if (is_admin204_hide('hide_card_wayup')) {
    $conds[] = "(IFNULL({$p}CARDNAME,'') != '무통장' AND IFNULL({$p}PRICE,0) = 220000 AND {$date_expr} >= '{$from}')";
  }
  // 12만: 카드 132000 제외 (날짜 제한 없음)
  if (is_admin204_hide('hide_card_120k')) {
    $conds[] = "(IFNULL({$p}CARDNAME,'') != '무통장' AND IFNULL({$p}PRICE,0) = 132000)";
  }
  if (empty($conds)) {
    return '';
  }
  return " AND NOT (".implode(' OR ', $conds).")";
}

function is_admin205_user() {
  global $S_login;
  return isset($S_login['user_id']) && $S_login['user_id'] === 'admin205';
}

/**
 * 매출현황용 숨김 필터
 * admin205는 숨김설정과 무관하게 실제 카드매출/취소액을 집계한다.
 */
function get_sales_page_hide_filter($prefix = '') {
  if (is_admin205_user()) {
    return '';
  }
  return get_staff_hide_card_payment_filter($prefix);
}

/** 카드결제 목록용 필터 (c. 테이블 alias) */
function get_staff_hide_card_payment_filter($prefix = 'c.') {
  if ($prefix === 'c.' || $prefix === 'c') {
    $date_expr = "{$prefix}REG_DATE";
  } else {
    $date_expr = "IFNULL({$prefix}CON_REG_DATE, {$prefix}REG_DATE)";
  }
  return get_admin204_main_sales_filter($prefix, $date_expr);
}

/** 캐시관리(T_CASH_LOG)용 필터 */
function get_staff_hide_cash_log_filter($prefix = 'l.') {
  $p = $prefix;
  $from = get_staff_hide_from_datetime();
  $sql = '';

  // 22만: 적용일 이후만 제외
  if (is_admin204_hide('hide_card_wayup')) {
    $sql .= " AND NOT (
      IFNULL({$p}MEMO,'') NOT LIKE '%무통장%'
      AND (IFNULL({$p}PRICE,0) = 220000 OR IFNULL({$p}CASH,0) = 200000)
      AND {$p}REG_DATE >= '{$from}'
    )";
  }
  // 12만: 날짜 제한 없음
  if (is_admin204_hide('hide_card_120k')) {
    $sql .= " AND NOT (
      IFNULL({$p}MEMO,'') NOT LIKE '%무통장%'
      AND (IFNULL({$p}PRICE,0) = 132000 OR IFNULL({$p}CASH,0) = 120000)
    )";
  }
  return $sql;
}

/** 월별 금액통계(month2) CASH 필터 */
function get_staff_hide_month2_cash_filter() {
  $from = get_staff_hide_from_datetime();
  $sql = '';

  // 22만: 적용일 이후 CASH 200000만 제외 (열 전체 삭제가 아니라 해당 일자 이후 건만)
  if (is_admin204_hide('hide_card_wayup')) {
    $sql .= " AND NOT (CASH = 200000 AND IFNULL(CON_REG_DATE, REG_DATE) >= '{$from}')";
  }
  // 12만: CASH 120000 열 제외 (날짜 제한 없음)
  if (is_admin204_hide('hide_card_120k')) {
    $sql .= " AND CASH != 120000";
  }
  return $sql;
}

/**
 * 승인 후 취소된 결제 (카드: IS_USE=N, 무통장: IS_USE=C)
 * 대기건 취소는 CON_REG_DATE가 없어 매출에 넣지 않는다.
 */
function get_cash_sales_canceled_sql() {
  return "(
    IFNULL(CON_REG_DATE, '0000-00-00 00:00:00') > '0000-00-00 00:00:00'
    AND (
      (IFNULL(CARDNAME,'') != '무통장' AND IS_USE = 'N')
      OR (IFNULL(CARDNAME,'') = '무통장' AND IS_USE = 'C')
    )
  )";
}
