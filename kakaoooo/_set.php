<?php
include_once $_SERVER['DOCUMENT_ROOT']."/lib/_function.php";

$data = array();

// k1 ~ k45 중 POST로 들어온 값만 처리
for ($i = 1; $i <= 45; $i++) {
    $key = "k" . $i;
    if (isset($_POST[$key])) {
        $nickname = $_POST[$key];
        $result = db_query("update kakao set $key = '{$_POST[$key]}' where idx = 1 ");
        if($nickname){
          if($content){
            $up_content = "update tb_member set gender = '{$gender}', content = '{$content}' where name = '{$nickname}' ";
            db_query($up_content);
          }



          if($content){
              db_query("insert into tb_member_profile set name = '{$nickname}', content = '{$content}', regdate = now() ");
          }

        }


        if($commuter){
            $출퇴 = db_select("select count(*) as cnt from tb_commuter where knumber = {$number} ");
            if($출퇴['cnt']==0){
                db_query("insert into tb_commuter set knumber = {$number}, commuter = 1 ");
            }else{
              db_query("delete from tb_commuter where knumber = {$number}");
            }
        }

        if($nickname){
          $공커일방 = explode("|", $nickname);
          for($a=0;$a<count($공커일방);$a++){
            $sql1 = "select count(*) as cnt from tb_member where name = '{$공커일방[$a]}' and status = 0 ";

            $맴버 = db_select($sql1);
            //echo $맴버['cnt'];
            if($맴버['cnt']==0){
              $sqls1 = "insert into tb_member set status = 0, code = '0', num = '{$key}', gender = '{$gender}', name = '{$공커일방[$a]}', content = '{$content}', regdate = now() ";
              db_query($sqls1);
              if($content){
                  db_query("insert into tb_member_profile set name = '{$nickname}', content = '{$content}', regdate = now() ");
              }

            }else{
              //db_query("update tb_member set  where name = '{$_POST[$key]}' ");
            }
          }
        }




    }
}
echo $result;
