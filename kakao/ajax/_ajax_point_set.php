<?php
include_once('../common.php');
// 회원가입 포인트 부여
if($event_point_chk=="true"){
  $status = insert_point_cron($mb_id, $point, $content, '@event', $mb_id, $content);
}else{
  $status = insert_point($mb_id, $point, $content, '@error', $mb_id, $content);

}

?>
<div>* 최근 100건만 보실 수 있습니다.</div>
  <div style="border: 1px solid; padding: 5px; height: 170px; overflow: hidden; overflow-y: scroll;" >
    <ul>
    <?
    $sql = "select * from g5_point where mb_id = '{$mb_id}' order by po_datetime desc limit 100 ";
    $point_result = db_query($sql);
    for($a=0;$arow=db_fetch($point_result);$a++){
    ?>
      <li><?=$arow['po_datetime']?> <?=$arow['po_point']?> <?=$arow['po_content']?> </li>
    <? } ?>
    </ul>
  </div>
</div>
