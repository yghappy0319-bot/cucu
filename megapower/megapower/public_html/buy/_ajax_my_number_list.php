<?php include_once "../lib/function.php";
include_once "../_chk.php";
?>

<div class="wrap">
    <div class="head">
        <p>나의 자주쓰는 번호 📟</p>
        <i class="xi-close close"></i>
    </div>
    <div class="body">
        <div class="scroll">
            <form id="orderFrm">
              <table>
                  <thead>
                      <tr>
                          <th>선택</th>
                          <th>자주쓰는 번호</th>
                      </tr>
                  </thead>
                  <tbody>
                      <!-- <tr>
                          <td>
                              <i class="xi-check on"></i>
                          </td>
                          <td>
                              <div class="ball">
                                  <p>1</p>
                                  <p>3</p>
                                  <p>20</p>
                                  <p>40</p>
                                  <p>50</p>
                                  <p>16</p>
                              </div>
                          </td>
                      </tr> -->

                    <?
                    $sql = "select * from lr_my_number where midx = '{$member['idx']}' and gubun = '{$gubun}' limit 10 ";
                    $result2 = db_query($sql);
                    $cnt = mysqli_num_rows($result2);
                    for($i=0;$row=db_fetch($result2);$i++){
                    ?>
                    <tr>
                      <td>
                        <div class="my_num_chk_wrap">
                          <input type="checkbox" class="my_bookmark_list" name="idxs[]" value="<?=$row['idx']?>" id="my_num_chk_<?=$i?>">
                          <label for="my_num_chk_<?=$i?>">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                              <path d="M23.5 12C23.5 18.3513 18.3513 23.5 12 23.5C5.64873 23.5 0.5 18.3513 0.5 12C0.5 5.64873 5.64873 0.5 12 0.5C18.3513 0.5 23.5 5.64873 23.5 12Z" stroke="#A3A3A3"></path><path d="M7 12.6667L10.3846 16L18 8.5" stroke="#A3A3A3" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                            </svg>
                          </label>
                        </div>
                      </td>
                      <td>
                        <div class="my_number_ball_list">
                          <?
                          if($row['lotto1']){
                              $lot = explode(",", $row['lotto1']);
                              $_lot = count($lot);
                              for($a=0;$a<$_lot;$a++){
                              ?>
                              <span ><?=$lot[$a]?></span>
                            <? } ?>
                          <? } ?>

                          <? if($row['lotto2']){
                              $lot = explode(",", $row['lotto2']);
                              $_lot = count($lot);
                              for($a=0;$a<$_lot;$a++){
                              ?>
                              <span ><?=$lot[$a]?></span>
                            <? } ?>
                          <? } ?>

                        </div>
                        <div>
                          <a href="javascript:go_my_number_del(<?=$row['idx']?>);">삭제</a>
                        </div>
                      </td>

                    </tr>
                    <? } ?>


                  <? if($cnt==0){?>
                    <tr>
                        <td colspan="2">
                            저장된 번호가 없습니다.
                        </td>
                    </tr>
                  <? } ?>

                  </tbody>
              </table>
            </form>

            <div class="btn">
                <a class="close">닫기</a>
                <a href="javascript:go_my_number_set()">확인</a>
            </div>

        </div>
    </div>
</div>
<div class="close"></div>




<script>
var lucky_deal_event = "<?=$lucky_deal_cnt?>";

function go_my_number_show(obj){
  $("#my_number_list button").removeClass('my_nb_active');
  $(".btn"+obj).addClass('my_nb_active');
  $(".myball").css("display","none");
  $(".myball"+obj).show();
  $(".my_number_btn_info").show();
  $("#my_nmber_idx").val(obj);
  $(".my_number_count").css("display","none");
}

function go_my_number_set(){
  var checkedCount = $('.my_bookmark_list:checked').length;
  if(!checkedCount){
    alert("자주쓰는 번호를 선택해주세요.");
    return false;
  }

  if(lucky_deal_event>0 && lucky_deal_event<checkedCount){
      alert("회원님의 등급은 L"+lucky_deal_event+" 입니다!\n"+lucky_deal_event+"건만 담기가 가능합니다.");
      return false;
  }

  var params = jQuery("#orderFrm").serialize();
    $.ajax({
      type : "POST",
      url: "_ajax_my_ball.php",
      async: false,
      data: params,
      success: function(result) {
        //console.log(result);
        go_my_choise_number(result);
        $(".new_popup_my_save_ball").removeClass("on");
      },
      error:function(e) {
        alert(e.responseText);
      }
    });

}

function go_my_number_del(obj){
  $.ajax({
     type : "POST",
     url: "_ajax_my_number_del.php",
     async: false,
     data: {
       idx : obj
     },
     success: function(result) {
        var r = result.trim();
        if(r==1){
          alert("삭제되었습니다.");
          location.reload();
        }

     },
     error:function(e) {
        alert(e.responseText);
     }
  });
}

$(".new_popup_my_save_ball .close").click(function(){
    $(".new_popup_my_save_ball").removeClass("on");
});
</script>
