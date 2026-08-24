<?php
/**
 * Excel(csv) Download
 */
include $_SERVER["DOCUMENT_ROOT"]."/_common/config.php";

F_admin_chk($S_login);

if ($S_login['level'] <= 2 || $S_login['user_id'] == "admin301") {
  alert_print("다운로드 권한이 없습니다.");
  self_close();
  exit;
}

function export_csv($file_name, $csv_dump, $cnt, $page_name) {
  global $db;
  global $S_login;
  global $ip_address;

  $db->query("
    INSERT INTO FILE_DOWNLOAD_LOG (
      USER_ID, TYPE, PAGE, ROW, IP
    ) VALUES (
      '{$S_login['user_id']}', 'E', '{$page_name}', $cnt, '$ip_address'
    );
  ");

  // header
  header("Content-Encoding: UTF-8");
  header("Content-Type:text/csv;charset=UTF-8;");
  header("Content-disposition: attachment; filename=".$file_name);
  header("Expires: 0");
  header("Content-Transfer-Encoding: binary");
  header("Cache-Control: private, no-transform, no-store, must-revalidate");
  echo "\xEF\xBB\xBF"; // UTF BOM

  echo $csv_dump;
}
?>
