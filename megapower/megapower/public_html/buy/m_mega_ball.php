<script>
$(document).on("click", ".select_list", function(){
    if($(this).data('game')>=0){
      $('.game_step3').bPopup({
           modalClose : true
       });
    }
});
</script>
<style>
.game_round_info {position: relative;}
.footer_menu_form, .business_footer {display:none;}
.info_hide {<?= $_COOKIE['all_view'] ? "display:none;":"" ?>}
.view_controll{position: absolute; right: 5px; top: 0;}
footer .inner_box{padding: 0px;}

.back_result_box {position: fixed; top: 80px;    z-index: 9;}
.back_result { background-color: #ccc; align-items: center; padding: 6px; }
.back_result p {    font-size: 16px; font-weight: bold;}
.ball_list {}
.ball_list div {margin: 0px; margin-right: 3px;}
.ball_list div span {padding: 3px 0px; width: 28px; height: 28px; font-size: 18px;}
.back_result .back_title {font-size: 18px; font-weight: 300; color: #666; margin-left: 4px;}
#my_number_list {top: 200px !important;}
</style>
<?
$직전회차 = db_select("select * from lr_result where game = 'mm' order by round desc limit 1 ");
?>

<main>
    <section>
      <div class="flex back_result" id="lnb" >
        <div>
          <div>
            <p>직전회차 당첨번호</p>
          </div>
          <div >
              <?=$직전회차['round']?>회차 (<?=date("y-m-d", strtotime($직전회차['ko_date']))?>)
          </div>
        </div>

        <div class=" ">
          <div class="flex  ball_list">
            <div><span><?=$직전회차['ball1']?></span></div>
            <div><span><?=$직전회차['ball2']?></span></div>
            <div><span><?=$직전회차['ball3']?></span></div>
            <div><span><?=$직전회차['ball4']?></span></div>
            <div><span><?=$직전회차['ball5']?></span></div>
            <div><span><?=$직전회차['ball6']?></span></div>
          </div>
        </div>
      </div>

      <?
        //include_once "event_lucky_deal.php";
      ?>

      <div class="game_status" style="margin-bottom: 40px;" >
        <div class="game_mm_round" ><?= 게임명($_game_name) ?> 주문하기</div>
        <div class="game_round_info">

          <div class="flex" >
            <? if (메가밀리언_수요일_토요일($주문회차, $서머타임['주문마감시간'])) { ?>
              <div >
                <div class="priz">
                  <p class="issue" ><?=$주문회차-1?>회차 1등
                    <font class="blink" color="#CC1F3B">오늘 추첨!</font></p>
                  <p><?=당첨금원화단위변경($mega1)?></p>
                </div>

                <div class="priz2">
                  <p class="next_issue" ><?=$주문회차?>회차 예상 당첨금</p>
                  <?
                  $_예상당첨금 = $mega1;
                  $additionalPercentage = 7;
                  $additionalAmount3 = $_예상당첨금 * ($additionalPercentage / 100);
                  $totalAmount = $_예상당첨금 + $additionalAmount3;

                  $_예상당첨금us = $mega_ball['prize'];
                  $_당첨금비율 = 10;
                  $additionalAmount4 = $_예상당첨금us * ($_당첨금비율 / 100);
                  $totalUsAmount = $_예상당첨금us + $additionalAmount4;
                  ?>
                  <p class="next_total"><?=당첨금원화단위변경($totalAmount)?></p>
                </div>
              </div>
            <? }else{ ?>
              <div class="sub_result_box priz" >
                <div class="sub_result_sub_box" >
                  <div class="sub_title info_hide" >1등 당첨금</div>
                  <div class="sub_priz_money"><?=당첨금원화단위변경($mega1)?></div>
                  <div class="sub_us_money info_hide">$<?=number_format($mega_ball['prize'])?></div>
                </div>

              </div>
            <? } ?>
            <div class="view_controll">
              <img src="/assets/icon/eye-thin.png" style="width: 24px;" onclick="go_info_show();" />
            </div>
            <div class="sub_result_box priz">
              <div class="sub_result_sub_box"  >
                <div class="sub_title info_hide"><?=$주문회차?>회차 주문마감일시</div>
                <div class="sub_result_buy_time" >
                  <span id="power_countdown">0일 00시간 0분 0초</span>
                </div>
                <div>
                  <?= date("Y/m/d", strtotime($_메가밀리언추첨)) ?> 오전 <?=$서머타임['주문마감시간']?>:00
                </div>
              </div>
            </div>

            <div class="sub_result_box priz">
              <div class="sub_result_sub_box">
                <div class="sub_title info_hide">추첨 일시</div>
                <div class="korea_result_date ko_dday">
                  한국 <?=$_한국추첨일?> <?=$_한국요일?>요일 <?=$서머타임['추첨시간']?>시
                </div>
                <div class="info_hide us_dday">
                  미국 <?=$_미국추첨일?> <?=$_미국요일?>요일 20시
                </div>
              </div>
            </div>
          </div>
          <? if($config['메가볼구매제한']>0){?>
          <div class="lotto_limit" >
            <p><?=date("m월 d일")?> 메가밀리언 주문가능수량 : <?=$config['메가밀리언구매제한']?>장</p>
          </div>
          <? } ?>
        </div>
      </div>

      <div class="mobile_game1" id="top_menu" >
        <div calss="text-center">
          <img src="/assets/image/step1.png" style="width:100%;" />
        </div>

        <div class="game_step2 flex" >
          <p class="title" >1. 게임수량 선택</p>
          <div class="game_buy_count sub_box_sha" >
            <div class="game_status_var">
              <p class="status mm_red" ><?= 게임명($_game_name) ?>
                <span class="game_cnt" ></span>게임
              </p>
              <p class="amount">
                게임수량을 선택해주세요.
              </p>
            </div>

            <div class="game_rules_sub game_select" >
              <?
              if($buy>0){
                $game_choise = $member['grade'];
              }else{
                $game_choise = 1;
              }

              for($g=$game_choise;$g<=$gnt;$g++){?>
              <button type="button" onclick="go_game(<?=$g?>)" class="gamebtn g<?=$g?>" >
                <div><?=$g?>게임</div>
              </button>
              <? } ?>
            </div>

            <? if(!$buy){?>
            <div style="background-color: #F4F4F4;" >
              <div class="game_100_form" >
                <select class="go_max_game" onchange="go_max_game(this.value)">
                  <option value="" >최대 100게임 까지 선택하세요.</option>
                  <?
                    for($g=11;$g<=100;$g++){
                  ?>
                  <option value="<?=$g?>" ><?=$g?>게임</option>
                  <? } ?>
                </select>
              </div>

              <div class="m_game_count_box" style="display:none;" >
                <button class="game_100_select_button" onclick="go_game_cancel()" >
                  <span class="gct">0</span> 게임
                  <img src="/assets/image/circle.png" />
                </button>
              </div>
            </div>
            <? } ?>

            <script>
              function go_max_game(obj){
                $(".m_game_count_box").show();
                go_game(obj);
                $(".gct").html(obj);
              }
              function go_game_cancel(){
                $(".m_game_count_box").css("display","none");
                $(".go_max_game option:eq(0)").prop("selected", true);
                go_game(1);
              }
            </script>


            <div class="game_step1_sbox" >
              <div>
                  <button type="button" onclick="go_speed_game();" class="reset_btn" >빠른게임</button>
              </div>
              <div>
                  <button type="button" onclick="go_next_step('mobile_game',1,2)" class="cart_btn" >선택완료</button>
              </div>
            </div>
          </div>
        </div>
      </div>


<script>
  function go_next_step(obj1,obj2,obj3){
    $("."+obj1+obj2).css("display","none");
    $("."+obj1+obj3).show();
    $(".game_status").css("display","none");

  }
</script>

      <div class="game_step2 mobile_game2" style="display:none;" >
        <div calss="text-center">
          <img src="/assets/image/step2.png" style="width:100%;" />
        </div>
        <p class="title" >2. 게임방법 선택</p>
        <div class="game_buy_count sub_box_sha" >
          <div class="game_status_var" >
            <p class="status mm_red" ><?= 게임명($_game_name) ?>
              <span class="game_cnt" ></span>게임
            </p>
            <p class="amount">
              게임방법을 선택해주세요.
            </p>
          </div>

          <div class="game_rules" >

            <div class="game_rules_sub" >
              <div><button type="button" class="step2_btn btn-1" onclick="go_all_reset()">전체 자동 선택</button></div>
              <div><button type="button" class="step2_btn btn-2" onclick="go_all_clear()">수동 선택</button></div>
              <div><button type="button" class="step2_btn btn-3" onclick="<?= $member['idx']>0 ? "go_my_number_list('mm')":"_go_login()" ?>">내 번호 불러오기</button></div>
            </div>
              <div>
                <ul class="game_list" ></ul>
              </div>
              <? if(!$buy){?>
              <div class="game_add_btn">
                <button type="button" onclick="go_game_add()" >게임 추가하기</button>
              </div>
              <? } ?>

              <div class="game_step1_sbox" >
                <div>
                    <button type="button" onclick="go_next_step('mobile_game',2,1)" class="back_btn" >뒤로가기</button>
                </div>
                <div>
                    <button type="button" onclick="go_next_step('mobile_game',2,3)" class="cart_btn" >선택완료</button>
                </div>
              </div>

          </div>
        </div>
      </div>

      <div class="game_step2 mobile_game3" style="display:none;" >
        <div calss="text-center">
          <img src="/assets/image/step3.png" style="width:100%;" />
        </div>

          <div>
            <p class="title" >3. 주문하기</p>
          </div>
          <div class="game_buy_count game_buy_point sub_box_sha" style="width: 100%; height: 100%;" >
            <div  class="game_status_var"  >
              <p class="status mm_red" ><?= 게임명($_game_name) ?>
                <span class="game_cnt" ></span>게임
              </p>
              <p class="amount">
                1게임 <?=number_format($_amount)?>캐시
              </p>
              <div class="text-center" >
                <button class="ball_number_chk_btn" type="button" onclick="go_next_step('mobile_game',3,2)" >번호 확인하기</button>
              </div>
            </div>

            <div class="order_info" >
              <div>
                <div class="subject" >보유 캐시</div>
                <div class="flex_between" >
                  <div class="lh2">
                    <span style="font-weight: 800;" ><?= number_format($member['point']) ?>P</span>
                  </div>
                  <div><button type="button" class="point_btn" onclick="show_cash_charge_notice();" >캐시 충전</button></div>
                </div>
              </div>

              <? if(!$buy){?>
              <div class="order_info_sub" >
                <div class="subject">쿠폰</div>
                <div class="flex_between">
                  <div class="lh2">
                    <?
                    $data = date("Y-m-d H:i:s");
                    $coupon_cnt = db_select("select count(*) as cnt from lr_coupon_log where midx = {$member['idx']} and status = 0 and bdate > '{$data}' ");
                    ?>
                    <span style="font-weight: 800;"><?= number_format($coupon_cnt['cnt']) ?>개</span>
                  </div>
                  <div class="po-l" >
                    <button type="button" class="point_btn" onclick="go_coupon()" >쿠폰선택</button>
                  </div>
                </div>
              </div>


                <div class="order_info_sub"  >
                  <div class="subject">당첨금</div>
                  <div class="flex_between">
                    <div class="lh2">
                      <a href="<?=$member['prize_money'] > 0 ? "javascript:go_max_luckypoint(".$member['idx'].",'prize_money')":"javascript:_go_login();"; ?>">
                      <span style="font-weight: 800; <?=$member['prize_money'] > 0 ? 'text-decoration: underline':''; ?>">
                        <?= number_format($member['prize_money']) ?>원</span></a> 중
                    </div>
                    <div class="po-l" >
                      <input type="text" class="amount_input _point" id="prize_money" data-point="prize_money" value="0" /><span class="won" >원</span>
                    </div>
                  </div>
                </div>



                <div class="order_info_sub"  >
                  <div class="subject">럭키포인트</div>
                  <div class="flex_between">
                    <div class="lh2">
                      <a href="<?=$member['lucky_point'] > 0 ? "javascript:go_max_luckypoint(".$member['idx'].",'lucky_point')":"javascript:_go_login();"; ?>">
                      <span style="font-weight: 800; <?=$member['lucky_point'] > 0 ? 'text-decoration: underline':''; ?>">
                        <?= number_format($member['lucky_point']) ?>LP</span></a> 중
                    </div>
                    <div class="po-l" >
                      <input type="text" class="amount_input _point" id="lucky_point" data-point="lucky_point" value="0" /><span class="lp">LP</span>
                    </div>
                  </div>
                </div>

              <? } ?>

              <div class="order_info_total"  >
                <div  class="flex_between order_result" >
                  <div>
                    주문금액
                  </div>
                  <div>
                    <span class="view_total_amount" >0</span>P
                  </div>
                </div>
              </div>
            </div>

            <div class="game_step1_sbox" >
              <div>
                  <button type="button" onclick="go_next_step('mobile_game',3,2)" class="back_btn" >뒤로가기</button>
              </div>
              <div>
                  <button type="button" onclick="go_buy()" class="cart_btn" >구매하기</button>
              </div>
            </div>

          </div>
      </div>

      <div class="game_step3 " style="display:none;" >
        <div id="lotto-container">
          <div style="    background-color: #fff; padding: 20px; text-align: center; font-size: 18px; font-weight: 600;border-radius: 10px 10px 0px 0px;" >
            수동 선택
          </div>
          <div class="white_ball_box" >
            화이트볼 <span class="white_ball" >5</span>개 선택
          </div>
          <div class="ball-box" ></div>
          <div class="white_ball_box" ><?= 게임명($_game_name) ?> 1개 선택</div>

          <div id="lotto-last-container" ></div>

          <div class="game_step1_sbox" >
            <div>
                <button type="button" onclick="go_reset();" class="back_btn" >리셋</button>
            </div>
            <div>
              <button type="button" onclick="go_set()" class="cart_btn" >번호 담기</button>
            </div>
          </div>
        </div>
      </div>

    </section>
</main>
