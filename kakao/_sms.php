<?php
include "/home/luckybank/public_html/lib/function.php";




$sql = "select * from member where mb_10 = ''";
$result = db_query($sql);
for($a=0;$row=db_fetch($result);$a++){
  $msg = "안녕하세요, 럭키뱅크입니다.
  회원님께서 오랫동안 로그인하지 않아 현재 사용 중이지 않은 계정({$row['mb_id']})이 확인되었습니다.

  보안 및 개인정보 보호를 위해 3일 후(2025-11-06) 럭키뱅크에 가입된 계정이 삭제될 예정입니다.
  계정을 유지하시려면 3일 이내에 로그인하여 서비스 이용을 연장해 주세요.\n감사합니다.\n\n https://adm.luckybank.kr 럭키뱅크 바로가기";


  //다이랙트샌드($row['mb_hp'], "럭키뱅크 미사용 계정 삭제 안내", $msg);
}
