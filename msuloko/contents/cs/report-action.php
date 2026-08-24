<?php
  include $_SERVER['DOCUMENT_ROOT']."/_common/config.php";
  include $_SERVER['DOCUMENT_ROOT']."/_library/function_TIP_OFF.php";

  // 테이블명 설정
  $VAL      = $_POST;
  $save_dir = $_SERVER['DOCUMENT_ROOT'].'/upload/report';
  $REFERER  = $_SERVER['HTTP_REFERER'];

  if ($M_login['user_id'] == "") {
    history_go();
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

  $VAL['mode'] = "insert";

  // INJECTION
  $VAL['USER_ID'] = $M_login['user_id'];
  $VAL['SUBJECT'] = xss_clean($VAL['SUBJECT']);
  $VAL['CONTENT'] = xss_clean($VAL['CONTENT']);

  for ($i = 1; $i <= 3; $i++) {
    $f  = $_FILES['uploadFile'.$i]['tmp_name'];
    $fn = $_FILES['uploadFile'.$i]['name'];
    $fs = $_FILES['uploadFile'.$i]['size'];

    if ($fn != "" || $mode == "delete") {
      $R_info = fileUploadToAdm(array(
        'mode'     => $mode,
        'save_dir' => $save_dir,
        'f'        => $f,
        'fn'       => $fn,
        'fs'       => $fs,
        'uniq'     => $M_login['user_id'],
        'idx'      => $i,
        'max_size' => 30 * 1024 * 1024,
      ));

      $VAL['FILE'.$i] = $R_info['real'];
      $VAL['REAL_FILENAME'.$i] = $R_info['down'];
    } else {
      $VAL['FILE'.$i]    = $info['FILE'.$i];
      $VAL['REAL_FILENAME'.$i] = $info['REAL_FILENAME'.$i];
    }
  };

  F_TIP_OFF($VAL);

  alert_print("제보가 정상 접수되었습니다.");
  meta_go("/contents/cs/report.html");
?>
