
<div id="popup_coupon_box" style="display:none;" >
  <div  style="text-align: center; " ><a class="b-close" >&#215;</a></div>
  <div class="bpopup_content" >
    <div class="bpopup_title" >
      쿠폰함
    </div>

    <div style="margin: 20px;" >
      <?
      $data = date("Y-m-d H:i:s");
      $sql = "select * from lr_coupon_log where midx = {$member['idx']} and status = 0 and usedate1 > '{$data}' ";
      
      $coupon_result = db_query($sql);
      $coupon_cnt = mysqli_num_rows($coupon_result);
      foreach($coupon_result as $coupon){
      ?>

      <div class="new_coupon_body coupon_content">
        <div class="my_cp_list">
          <div class="coupon_list">
            <div class="my_cp_inner">
              <div class="coupon_box">
                  <div class="coupon_download_ok2"><span style="color:#666;"><?=쿠폰사용가능여부($coupon['status'],$coupon['usedate1'])?></span></div>
                  <div class="cp_name"><?=$coupon['name']?></div>
              </div>
              <div>유효기간 : <?=$coupon['usedate1']?></div>
              <img src="/assets/point30000.png" class="coupon_img img_30000_board ">
            </div>
            <div class="icon"><img src="/images/event_coupon.png"></div>
          </div>
        </div>
        <div>
          <button type="button" class="popup_coupon_btn" onclick="go_set_coupon('<?=$coupon['discount']?>','<?=$coupon['idx']?>')" >사용하기</button>
        </div>
      </div>

      <? } ?>
      <? if($coupon_cnt==0){?>
        사용가능한 쿠폰이 없습니다.
      <? } ?>

    </div>
  </div>
</div>
