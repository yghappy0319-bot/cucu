
<script>
    // $(".new_header .bottom .fl .menu ul li:eq(2)").addClass("on")
    // $(".new_header_m .menu:eq(0) ul li:eq(2)").addClass("on")
    // $(".new_all_menu .menu_fb > ul > li:eq(2)").addClass("on")
    $(document).ready(function(){
      go_game(5);
    });
</script>

<div class="w_max">
    <div class="w_min">
      <div class="modle_guide">
          <div class="modle_sub_title"><?=$title?> 구매</div>
          <ul>
              <li><i class="xi-home"></i></li>
              <li><i class="xi-angle-right"></i></li>
              <li><p><?=$title?> 구매</p></li>
          </ul>
      </div>

      <div class="new_buy_ball_head">
          <div class="wrap price">
            <? if (메가밀리언_수요일_토요일($주문회차, $서머타임['주문마감시간'])) { //추첨일날 예상당첨금 나오는 부분 ?>
            <div>
              <div class="ft">
                  <span class="mg"><?=$주문회차-1?>회차</span><font class="blink" color="#CC1F3B">오늘 추첨!</font>
              </div>
                <div class="fb">
                    <div class="number_kr">
                        <span>₩</span>
                        <p class="mg"><?=당첨금원화단위변경($mega1, "서브")?></p>
                        <span>억원</span>
                    </div>
                    <div class="number_us">
                        <span>예상 당첨금</span>
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
                        <p class="next_total"><?=당첨금원화단위변경($totalAmount, "서브")?></p>
                    </div>
                </div>
            </div>
            <? }else{ ?>
              <div class="ft"><span style="color: #0051c7;"><?=$title?></span> 1등 당첨금</div>
              <div class="fb">
                  <div class="number_kr">
                      <span>₩</span>
                      <p><?=당첨금원화단위변경($mega1, "서브")?></p>
                      <span>억원</span>
                  </div>
                  <div class="number_us">
                      <span>$</span>
                      <p><?=number_format($mega_ball['prize'])?></p>
                  </div>
              </div>
            <? } ?>
          </div>
          <div class="wrap date">
              <div class="ft"><span class="mg">제<?=$주문회차;?>회</span>추첨일시</div>
              <div class="fb">
                <input type="hidden" id="drawing_day" value="<?= date("Y년 m월 d일", strtotime($_한국추첨일)) ?> <?=$_한국요일?>요일 <?=$서머타임['추첨시간']?>:00" />
                  <p><span style="background: #0051c7;">KOR</span> <?=$_kor추첨일?> <?=$_한국요일?>요일 <?=$서머타임['추첨시간']?>:00</p>
                  <p><span style="background: #F00;">USA</span> <?=$_usa추첨일?> <?=$_미국요일?>요일 20:00</p>
              </div>
          </div>
          <div class="wrap time">
              <div class="ft">마감일시</div>
              <div class="fb">
                  <p >
                      <i class="xi-spinner-4 xi-spin" class="mg"></i>
                      <span id="power_countdown">0일 00시간 0분 0초</span>
                  </p>

                  <p>
                  <?
                    $추첨마감요일 = str_replace("요일", "", 요일($_메가밀리언추첨));
                    $추첨마감 = date("m월 d일", strtotime($_메가밀리언추첨));
                  ?>
                  <?= $추첨마감 ?> (<?=$추첨마감요일?>) <?=$서머타임['주문마감시간']?>:00 주문마감 됩니다.</p>
              </div>
          </div>
      </div>

<div class="mega_banner"><img src="/images/m-new-img-banner.jpg" alt="새로워진 메가밀리언"></div>
      <div class="new_buy_ball_body">
          <div class="fl">
              <div class="step">

                  <div class="title">
                      <p><span>STEP 01</span>게임수량 선택</p>

                      <? if($gnt>20){ ?>
                        <select onchange="go_game(this.value)">
                          <option value="">최대100게임</option>
                          <?
                          for($g=21;$g<=$gnt;$g++){?>
                          <option value="<?=$g?>"><?=$g?>게임</option>
                          <? } ?>
                        </select>
                      <? } ?>

                  </div>
                  <div class="quantity">
                      <ul>

                        <?
                        if($buy>0){
                          $game_choise = $member['grade'];
                        }else{
                          $game_choise = 1;
                        }
                        for($g=$game_choise;$g<=20;$g++){?>
                        <li class="gamecnt g<?=$g?>" ><div class="wrap " onclick="go_game(<?=$g?>)" ><p><?=$g?>게임</p></div></li>
                        <? } ?>

                          <!-- <li class="on"><div class="wrap"><p>1게임</p></div></li> -->

                      </ul>
                  </div>



              </div>

              <div class="step">
                  <div class="title"><p><span>STEP 02</span>게임방법 선택</p></div>
                  <div class="menu">
                      <ul>
                          <li class="setp03btn on"><div class="wrap" onclick="go_manual_reset(this)"><p>수동선택</p></div></li>
                          <li class="setp03btn "><div class="wrap" onclick="go_all_reset(this)" ><p>전체 자동선택</p></div></li>
                          <li class="setp03btn ">
                            <div class="wrap new_popup_my_save_ball_btn"onclick="<?= $member['idx']>0 ? "go_my_number_list('mm')":"_go_login()" ?>"  ><p>내 번호 불러오기</p></div>
                          </li>
                      </ul>
                  </div>

                  <div class="ball">
                      <div class="list">
                          <div class="title"><p><span>STEP 03</span>화이트볼 <b>5개</b> 선택</p></div>
                          <ul class="ball-box">
                          </ul>
                      </div>

                      <div class="list">
                          <div class="title"><p><span>STEP 04</span>메가볼 <b>1개</b> 선택</p></div>

                          <ul id="lotto-last-container">
                          </ul>
                          <div class="button">
                              <a href="javascript:go_reset()" >
                                <i class="xi-rotate-right"></i>
								                <p>리셋</p>
                              </a>
                              <a href="javascript:go_set()">
                                  <i class="xi-cart-add"></i>
                                  <p>번호 담기</p>
                              </a>
                          </div>
                      </div>
                  </div>
              </div>

          </div>
          <div class="fr">

              <div class="pay" id="order_result">
                  <div class="head">
                      <p><?=$title?> 게임</p>
                      <span>1게임 <b><?=$_amount?></b>캐시</span>
                  </div>
                  <div class="select">
                      <div class="list">
                          <ul class="game_list" >
                          </ul>
                      </div>
                      <div class="btn" onclick="go_my_number_save()"><i class="xi-valign-bottom"></i>&nbsp;내 번호로 저장하기</div>
                  </div>
                  <div class="info">
                      <ul>
                        <? if(!$buy){ ?>
                          <li>
                              <div class="tit">
                                  <p>보유캐시</p>
                                  <span><?= number_format($member['point']) ?> 캐시</span>
                              </div>
                              <div class="txt">
                                  <a href="/buy/point.html">캐시충전</a>
                              </div>
                          </li>
                          <li>
                            <?
                            $data = date("Y-m-d H:i:s");
                            $coupon_cnt = db_select("select count(*) as cnt from lr_coupon_log where midx = {$member['idx']} and status = 0 and usedate1 > '{$data}' ");
                            ?>
                              <div class="tit">
                                  <p>쿠폰</p>
                                  <span><?= number_format($coupon_cnt['cnt']) ?>개</span>
                              </div>
                              <div class="txt">
                                  <a href="javascript:go_coupon()">쿠폰선택</a>
                              </div>
                          </li>

                          <li>
                              <div class="tit">
                                  <p>당첨금</p>
                                  <span>
                                    <a style="color:#fff;" href="<?=$member['prize_money'] > 0 ? "javascript:go_max_luckypoint(".$member['idx'].",'prize_money')":"javascript:_go_login();"; ?>">
                                    <?= number_format($member['prize_money']) ?>원 <font>중</font>
                                    </a>
                                  </span>
                              </div>
                              <div class="txt" style="position: relative;">
                                  <input type="text" class="amount_input _point" id="prize_money" data-point="prize_money" id="prize_money" value="0원">
                                  <span style="position: absolute; right: 15px;">원</span>
                              </div>
                          </li>
                          <li>
                              <div class="tit">
                                  <p>메가파워월드 포인트</p>
                                  <span>
                                    <a style="color:#fff;" href="<?=$member['lucky_point'] > 0 ? "javascript:go_max_luckypoint(".$member['idx'].",'lucky_point')":"javascript:_go_login();"; ?>">
                                    <?= number_format($member['lucky_point']) ?> P <font>중</font>
                                    </a>
                                  </span>
                              </div>
                              <div class="txt" style="position: relative;">
                                <input type="text" class="amount_input _point" id="lucky_point" data-point="lucky_point" value="0P" />
                                <span style="position: absolute; right: 15px;">P</span>
                              </div>
                          </li>


                          <? } ?>




                      </ul>
                  </div>
                  <div class="all">
                      <p>주문금액</p>
                      <span class="view_total_amount">6,000 캐시</span>
                  </div>


                  <div class="button">
                      <a href="<?=$_SESSION['midx'] !='' ? 'javascript:go_buy();':'javascript:_go_login();' ?>" >구매하기</a>
                      <a href="/member/buy.html?game=mm" >내가 구매한 번호 확인</a>
                  </div>
              </div>

          </div>
          <div class="fb">
              <div class="text">
                  <div class="tit"><i class="xi-alarm-off xi-x"></i> 구매대행 마감시간</div>
                  <div class="bg">
                      <div class="txt">당회차 주문 마감시간 <span>AM <?=$서머타임['주문마감시간']?>:00</span></div>
                      <div class="tip">※ 추첨이 있는 날 AM <?=$서머타임['주문마감시간']?>:00 이후 주문건은 차회차로 구매 됩니다.</div>
                  </div>
              </div>
              <div class="scan">
                  <div class="tit"><i class="xi-alarm-clock-o xi-x"></i> 스캔본 확인 가능시간</div>
                  <div class="list">
                      <div class="wrap">
                          <i class="xi-time-o "></i>
                          <p>00:00 ~ <?=$서머타임['주문마감시간']?>:00 > 당일 12:00 이전</p>
                      </div>
                      <div class="wrap">
                          <i class="xi-time-o"></i>
                          <p><?=$서머타임['주문마감시간']?>:00 ~ 24:00 > 익일 07:00 이후</p>
                      </div>
                  </div>
              </div>
          </div>
      </div>



    </div>
</div>
