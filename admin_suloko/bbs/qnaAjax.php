<?php
require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";
require_once $_SERVER['DOCUMENT_ROOT']."/_common/common.php";
require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_BBS.php";

$mode = xss_clean($_POST['mode']);
$category = xss_clean($_POST['category']);
$flag = xss_clean($_POST['flag']);

switch($mode) {
  case "cateChange":
    if ($flag == 'first') {
      // Qna 1차 카테고리 선택시 2차 내용 리스트 가져오기
      $qnaList = $db->get_list("SELECT `title`,`idx` FROM `BBS_MACRO_DIRECT` WHERE `category` = '".$category."'");
      $cateList = "";
      for ($i = 0; $i < count($qnaList['title']); $i++) {
        if ($i < (count($qnaList['title']) - 1)) {
          $comma = ",";
        } else {
          $comma = "";
        }
        $cateList .= '{
          "title" : "'.addslashes(trim($qnaList['title'][$i])).'",
          "idx" : "'.$qnaList['idx'][$i].'"
        }'.$comma;
      }

      $jsonCode = '{
        "state" : "ok",
        "data" : [
          '.$cateList.'
        ]
      }';

      echo $jsonCode;
    } else if ($flag == 'second') {
      // 2차 내용 클릭시 contents 내용 가져오기
      $qnaList = $db->get_data(" SELECT `contents` FROM `BBS_MACRO_DIRECT` WHERE `idx` = '".$category."' ");

      $jsonCode = '{
        "state" : "ok",
        "html" : "'.addslashes(str_replace("'",'"',$qnaList['contents'])).'"
      }';

      echo $jsonCode;
    }
    break;
}
?>
