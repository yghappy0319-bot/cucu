<?php
include_once "../lib/function.php";
include_once "../_chk.php";

$sql = "select count(*) as cnt from lr_sms_log where code = '{$smscode}' and phone = '{$phone}' ";
//echo $sql;
$result = db_select($sql);
if($sendtype=="mail"){
  $id = db_select("select id from lr_member where email = '{$phone}' ");
}else{
  $id = db_select("select id from lr_member where phone = '{$phone}' ");
}


if($ac_page=="member"){
  if($result['cnt']){
    db_query("update lr_member set phone_auth = 1 where idx = {$member['idx']} ");
    $res = array("msg" => "success");
    echo json_encode($res);
    exit;
  }else{
    $res = array("msg" => "failed");
    echo json_encode($res);
    exit;
  }
}else{
  if($result['cnt']){

    $res = array(
      "member_id" => $id['id'],
      "msg" => "success"
    );
    echo json_encode($res);
    exit;
  }else{
    $res = array("msg" => "failed");
    echo json_encode($res);
    exit;
  }

}
