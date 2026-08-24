<?php
include_once $_SERVER['DOCUMENT_ROOT']."/lib/_function.php";

$sql1 = "select * from tb_member where idx = '{$code}' ";

$gender1 = db_select($sql1);

//echo json_encode($status);
?>
<div class="popup-box">
  <input type="hidden" id="number" value="<?=$gender1['idx']?>" />
  <div>
    <div style="padding: 5px 0px;" >
      <select id="name2" >
        <? for ($num = 1; $num <= 70; $num++) { ?>
            <option value='<?=$num?>' <?= $gender1['num']==$num ? "selected":"" ?> ><?=$num?>번</option>
        <? } ?>
      </select> ( 공커 지정시 같은번호로 지정할것! )
        <div>
          <input type="checkbox" name="couple" class="couple" value="2" <?=$gender1['couple']==2 ? "checked":"" ?> />신입 ( 신입끝난경우 체크해제 할것! )
        </div>
        <input type="radio" name="gender" class="gender" id="gender1" value="1" <?=$gender1['gender']==1 ? "checked":"" ?> />남
        <input type="radio" name="gender" class="gender" id="gender2" value="2" <?=$gender1['gender']==2 ? "checked":"" ?> />여


        <div>
          <input type="text" class="name" id="name1" placeholder="일반" value="<?=$gender1['name']?>" />
          <input type="hidden" id="name3" value="<?=$gender1['name']?>" />
        </div>

    </div>

    <div class="regdate" ><?= $gender1['regdate']?></div>
    <div>
      <textarea id="content1" style="width: 100%; height: 200px; margin-top: 5px;" ><?=$gender1['content']?></textarea>
    </div>

  </div>

  <div style="display: flex; justify-content: space-between;">
    <button id="closePopup">닫기</button>
    <button type="button" onclick="_set()" >저장</button>
  </div>

</div>
