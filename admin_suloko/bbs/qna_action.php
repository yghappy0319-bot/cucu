<?php
$s_type = "BBS";
require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";

include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly.php"; // 권한체크

$VAL = $_POST;

//content 처리
$VAL['content'] = addslashes($VAL['content']);

$sql = "
  UPDATE
    BBS
  SET
    REPLY = '{$VAL['content']}',
    REPLY_NAME = '{$S_login['name']}',
    REPLY_DATE = NOW()
  WHERE
    BBS_NO = '{$VAL['BBS_NO']}'
";

$db->query($sql);

meta_go("./qna.html");
?>
