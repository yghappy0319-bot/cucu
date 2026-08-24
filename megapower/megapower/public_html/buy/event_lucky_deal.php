
<style>
.lucky_beal_info{position: absolute; background-color: #fff; border: 1px solid #ccc; border-radius: 10px; padding: 10px; z-index: 9999;}
.lucky_beal_info .lucky_level{    margin: 6px 0px;}
.lucky_beal_info .lucky_level.acvite {font-size: 1.2rem;}
.lucky_beal_info .lucky_level.acvite h4{ background-color: #194A9C; color: #fff; padding: 4px;}
.lucky_beal_info .lucky_level.my_grade{    font-size: 1.5rem;}
.luckydeal_request_btn {display: flex;    justify-content: space-between;}
.dealinfo_btn{ background-color: #666; color: #fff; font-weight: bold; border: 0px; border-radius: 50%; padding: 2px 6px;}
.luckydeal_request_btn button{    width: 100%;
  background-color: #eee;
     font-weight: bold;
     border: 0px;
     border-radius: 10px;
     font-size: 1.2rem;
     padding: 10px 0px;
     position: relative;cursor: grab;
}
.lucky_btn{position: absolute;
    z-index: 9;
    top: 7px;
    background-color: #CC1F3B;
    color: #fff;
    font-size: 16px;
    border-radius: 10px;
    padding: 5px;
    margin-left: 5px;}
.deal_title1 {margin-bottom: 10px;}
.deal_cencel {color: #fff;  font-size: 14px;}
@media screen and (max-width: 768px) {
  .lucky_beal_info{z-index: 9999; }
  .deal_title1 {font-size: 1rem;}
  .deal_title {display: inline-block;}
  .luckydeal_request_btn {margin-bottom: 10px;}
  .luckydeal_request_btn button {font-size: 0.8rem !important;    width: 100%;}
  .lucky_btn {position: absolute;
      z-index: 9;
      top: -10px;
      background-color: #CC1F3B;
      color: #fff;
      font-size: 12px;
      border-radius: 10px;
      padding: 2px;}
}
</style>
<?
$my_buy_grade = array(
"0" => "0",
"1" => "1게임 ".number_format($_amount)."캐시에 구매가능",
"2" => "2게임 ".number_format($_amount*2)."캐시에 구매가능",
"3" => "3게임 ".number_format($_amount*3)."캐시에 구매가능",
"4" => "4게임 ".number_format($_amount*4)."캐시에 구매가능",
"5" => "5게임 ".number_format($_amount*5)."캐시에 구매가능"
);
?>
<div style="margin-top: 10px;position: relative;" >
  <div >
    <h2 class="deal_title1 blinking2">❗️매일❗️<span class="deal_title">1게임 3,700캐시!</span>
      <!-- <span style="color: red; font-size: 1rem;">37%할인 적용!</span> -->
      <button type="button" class="dealinfo_btn" onclick="go_lucky_deal_info()">?</button>
    </h2>
  </div>
  <div>
  <?
    $luckydeal_sql = "select count(*) as cnt from lr_orders where midx = {$member['idx']} and event = 'lucky_deal' and date_format(regdate,'%Y-%m-%d') = '".date("Y-m-d")."' ";
    $luckydeal = db_select($luckydeal_sql);
    if($luckydeal['cnt']>0){
      if($buy>0){
  ?>
    <script>
      alert('<?=date("Y-m-d")?>'+' 럭키딜 구매완료!\n'+'내일(<?=date("Y-m-d",strtotime("+1 days"))?>) 또 참여해 주세요!');
      location.href="/";
    </script>
  <? }else{ ?>
    <? if($luckydeal['cnt']>0){ ?>
      <div style="margin-bottom: 5px;text-align: center; background-color: #666; color: #fff;"><?=date("Y-m-d")?> 럭키딜 참여하셨습니다.</div>
    <? } ?>
  <? } ?>
  <? }else{ ?>
    <div class="luckydeal_request_btn" >

      <div style="width: 49%; position: relative;">
        <div class="lucky_btn">럭키딜!</div>
        <button type="button" class="<?=$buy>0 ? "deal_mm":"" ?>" onclick="location.href='/buy/megamillion.html?buy=<?=$member['grade']?>'">
          <div>
          메가밀리언 <?= $member['grade'] ? $member['grade']:"1" ?>게임 구매하기
          </div>
          <? if($buy>0 && $_game_name=="mm"){?>
            <div style="    position: absolute; top: 9px;right: 6px;" >
                <a class="deal_cencel" href="/buy/megamillion.html">[취소]</a>
            </div>
          <? } ?>
        </button>
      </div>

      <div style="width: 49%; position: relative;">
        <div class="lucky_btn" >럭키딜!</div>
        <button type="button" class="<?=$buy>0 ? "deal_pb":"" ?>" onclick="location.href='/buy/power_ball.html?buy=<?=$member['grade']?>'">

          <div>
            파워볼 <?= $member['grade'] ? $member['grade']:"1" ?>게임 구매하기
          </div>
          <? if($buy>0 && $_game_name=="pb"){?>
            <div style="    position: absolute; top: 9px;right: 6px;" >
              <a class="deal_cencel" href="/buy/power_ball.html">[취소]</a>
            </div>
          <? } ?>
        </button>
      </div>
    </div>
  <? } ?>

  </div>
  <script>
    function go_lucky_deal_info(){
        $(".lucky_beal_info").toggle();
    }
  </script>


<Script>
  function go_beal_info_close(){
    $(".lucky_beal_info").css("display","none");
  }
</Script>
  <div class="lucky_beal_info " style="display:none;" onclick="go_beal_info_close()" >
    <? $내등급 = $member['grade'] + 1; ?>
    <div style="background-color: #eee; padding: 10px;">
      <div>
        <ul class="lucky_level my_grade">
          <h4>나의 럭키등급 L<?=$member['grade']?></h4>
          <li style="font-size: 1rem; color: cornflowerblue; font-weight: bold;"><?=$my_buy_grade[$member['grade']]?></li>
        </ul>
      </div>
      <div >
          <h3>이벤트 기간동안 공통혜택</h3>
          <p>하루 한번! 게임당 <?=number_format($_amount)?>캐시에 구매가능한 혜택이 적용 됩니다.</p>
      </div>
    </div>

    <ul class="lucky_level <?=$member['grade']==1 ? "acvite":"" ?>">
      <h4>럭키등급 L1 (기본)</h4>
      <li><?=$my_buy_grade[1]?></li>
    </ul>

    <ul class="lucky_level <?=$내등급==2 ? "acvite":"" ?>">
      <h4>럭키등급 L2 달성시 (누적충전 50만⬆️)</h4>
      <li><?=$my_buy_grade[2]?></li>
    </ul>

    <ul class="lucky_level <?=$내등급==3 ? "acvite":"" ?>">
      <h4>럭키등급 L3 달성시 (누적충전 100만⬆️)</h4>
      <li><?=$my_buy_grade[3]?></li>
    </ul>

    <ul class="lucky_level <?=$내등급==4 ? "acvite":"" ?>">
      <h4>럭키등급 L4 달성시 (누적충전 150만⬆️)</h4>
      <li><?=$my_buy_grade[4]?></li>
    </ul>

    <ul class="lucky_level <?=$member['grade']==5 ? "acvite":"" ?>">
      <h4>럭키등급 L5 달성시 (누적충전 300만⬆️)</h4>
      <li><?=$my_buy_grade[5]?></li>
    </ul>
  </div>
</div>
