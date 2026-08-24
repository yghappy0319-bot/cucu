<?php
include_once "../header.html";

// 현재 연도와 월 계산
$year = date("Y");
$month = date("n");

// 현재 달의 첫 날과 마지막 날 계산
$firstDay = mktime(0, 0, 0, $month, 1, $year);
$lastDay = mktime(0, 0, 0, $month + 1, 0, $year);

// 현재 달의 일 수 계산
$daysInMonth = date("t", $firstDay);

// 현재 달의 첫 날의 요일 계산 (0: 일요일, 1: 월요일, ..., 6: 토요일)
$firstDayOfWeek = date("w", $firstDay);
?>
<!DOCTYPE html>
<div lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0px;
            padding: 0px;
        }
        h2,p {margin: 0px;}
        ul { margin: 0px;padding: 0px; list-style: none;}
        .calendar {
            width: 100%;
            max-width: 620px;
            margin: auto;
        }

        .month {
            background-color: #f2f2f2;
            padding-top: 10px;
            text-align: center;
        }
        .month .rols ul{margin: 0px; padding: 0px;list-style: none;}
        .rols { padding: 10px 5px; color: #666; text-align: left;}

        .days {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 5px;
            padding: 20px;
        }

        .day {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: center;
            background-color: #fff;
        }

        /* 현재 날짜에 대한 스타일 */
        .today {
            background-color: #8ac4d0;
        }

        /* 토요일에 대한 스타일 */
        .saturday {
            color: blue;
        }

        /* 일요일에 대한 스타일 */
        .sunday {
            color: red;
        }

        @media (max-width: 600px) {

          .rols {font-size: 14px;}
          .days { grid-template-columns: repeat(7, 1fr);padding: 5px;}
          .day{padding: 5px;}
          .mo_under {display: block;}
          .my_cal_chk {}
          .my_cal_chk_sub {font-size: 16px; font-weight: bold;}
          .cal_bottom {}
          .calender_info {}
          .cal_list{font-size:14px;}
          .mo_display_none {display: none;}
          .cal_comment {width: unset !important;}
        }
        .my_cal_chk {text-align: center; font-size: 1.3rem; background-color: #eee; padding: 10px;}
        .my_cal_chk_sub {display:flex;justify-content: space-evenly;}
        .title2 {text-align: center;    margin: 10px 0px;}
        .cal_btn { background-color: #333; color: #fff; border: 0px; font-size: 1.3rem; padding: 10px;}
        .cal_comment {width: 50%; margin-right: 10px; font-size: 1.3rem; text-align: center;}
        .cal_write {    margin: 10px 0px; border-bottom: 1px solid #ccc; padding-bottom: 20px;}
        .ranker { background-color: #CC1F3B; color: #fff; border-radius: 10px; padding: 2px; font-size: 14px;}
        .ranker_none { background-color: #ccc; color: #333; border-radius: 10px; padding: 2px; font-size: 14px;}

        .cal_list {   margin: 0px;padding: 0px;}
        .cal_list li {    padding: 4px 0px; display: flex;justify-content: space-between;}
        .calender_info{background-color: #f2f2f2; padding: 10px; border: 1px solid;margin-bottom: 40px;}
        .ellipsis { width: 100px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;  display: inline-block;}
        .add_point {background-color: #333; color: #FFF; font-size: 13px; padding: 2px; border-radius: 6px;margin: 0px 3px;}
        .top_title{    color: #333; text-decoration: none;}
    </style>

		<script>
      $(".new_header .bottom .fl .menu ul li:eq(5)").addClass("on")
      $(".new_header_m .menu:eq(1) ul li:eq(1)").addClass("on")
      $(".new_all_menu .menu_fb > ul > li:eq(5)").addClass("on")
	</script>


    <title><?=$config['사이트명2']?> 볼 출석체크</title>
</head>
<body>
<script>
    $(".new_header .bottom .fr .menu ul li:eq(0)").addClass("on")
    $(".new_header_m .menu:eq(1) ul li:eq(2)").addClass("on")
    $(".new_all_menu .menu_fb > ul:eq(1) > li:eq(0)").addClass("on")
</script>
<div class="w_max">
    <div class="w_min">
        <div class="modle_guide" style="position:relative;">
            <div class="modle_sub_title">이벤트</div>
            <ul>
                <li><i class="xi-home"></i></li>
                <li><i class="xi-angle-right"></i></li>
                <li><p>이벤트</p></li>
            </ul>
            <div class="guide_box" >
              <div>
                <a href="/member/view.html?gubun=notice&idx=146" target="_blank" class="coupon_guide" ><p>쿠폰&포인트 사용방법</p></a>
              </div>
            </div>            
        </div>

        <div class="modle_menu_02">
            <a href="/event/scratch.html"><p>스크래치</p></a>
            <a href="/member/coupon.html"><p>쿠폰발급</p></a>
            <a class="on" href="/event/calendar.php"><p>출석체크</p></a>
        </div>

        <div class="new_event_calendar calendar">
            <div class="month">
                <p class="tit"><?php echo date("Y년 m월 d일"); ?></p>
                <div class="txt">
                    <ul>
                        <li>기본 <b>30P ~ 100P</b> 랜덤 <?=$config['사이트명2']?> 포인트 지급</li>
                        <li>1/2/3등으로 출석자 <b>100P ~ 200P</b> 랜덤 <?=$config['사이트명2']?> 포인트 지급</li>
                    </ul>
                </div>
            </div>
            <!-- <div class="banner"><img src="/images/event_banner_03.png"></div> -->
            <div class="days">
                <?php
                $daysOfWeek = array('일', '월', '화', '수', '목', '금', '토');
                foreach ($daysOfWeek as $day) {
                  echo '<div class="day">' . $day . '</div>';
                }
                ?>

                <!-- 날짜 출력 -->
                <?php
                for ($i = 0; $i < $firstDayOfWeek; $i++) {
                  echo '<div class="day"></div>';
                }

                for ($day = 1; $day <= $daysInMonth; $day++) {
                  $currentDay = mktime(0, 0, 0, $month, $day, $year);
                  $date = date("Y-m-d", strtotime($year."-".$month."-".$day));
                  $dayClass = '';

                  $chk = db_select("select * from event_attendance where midx = {$member['idx']} and date = '{$date}' ");

                  // 현재 날짜에 대한 클래스 추가
                  if($chk['date']==$date){
                    $dayClass .= ' today';
                  }


                  // 토요일에 대한 클래스 추가
                  if (date('w', $currentDay) == 6) {
                      $dayClass .= ' saturday';
                  }

                  // 일요일에 대한 클래스 추가
                  if (date('w', $currentDay) == 0) {
                      $dayClass .= ' sunday';
                  }


                  echo '<div class="day' . $dayClass . '">' . $day .'</div>';
                }
                ?>
            </div>




            <div class="cal_bottom">


                <div class="cal_write">
                    <?
                        $my = db_select("select * from event_attendance where date = '".date("Y-m-d")."' and midx = {$member['idx']}");
                    ?>
                    <form id="orderFrm">
                        <input type="hidden" name="midx" value="<?=$member['idx']?>" />
                        <input type="hidden" name="date" value="<?=date("Y-m-d")?>" />
                        <? if($my['idx']){
                            $출석완료 = "완료!";
                          }else{
                            $출석완료 = "하기";
                          }
                        ?>

                        <div class="check_ok">
                            <?php echo date("Y년 m월 d일"); ?> 출석<?=$출석완료?>
                        </div>
                        <? if($my['idx']){?>
                            <div class="my_cal_chk">
                                <div class="tit">출석 보상! <span class="mo_under"><?=$config['사이트명2']?>  포인트 <font color="#194A9C" style="font-weight: bold;" ><?=$my['point']?>P 지급 되었습니다!</font></span></div>
                                <div class="my_cal_chk_sub">
                                    <div><?=$my['title']?></div>
                                    <div ><?= date("y/m/d H:i", strtotime($my['regdate'])) ?></div>
                                </div>
                            </div>
                        <? } ?>
                        <?
                        $titles = array("오늘도 힘찬 하루 되세요~",
                        "1등 당첨자는 나야나~",
                        "감사합니다.",
                        "출석체크 합니다!",
                        "모두 화이팅 하세요!",
                        "파워볼 기다려라 내가간다~","메가밀리언 기다려라 내가간다~");
                        $randomIndex = array_rand($titles);
                        $randomTitle = $titles[$randomIndex];
                        ?>
                        <? if(!$my['idx']){?>
                        <div class="check_btn">
                            <input type="text" name="title" value="<?=$randomTitle?>" class="cal_comment" />
                            <button type="button" class="cal_btn" onclick="<?=$_SESSION['midx'] !='' ? 'go_submit()':'_go_login()' ?>" >출석체크!</button>
                        </div>
                        <? } ?>
                    </form>
                </div>

                <div class="todat_check">

                    <div>나의 <?=$config['사이트명2']?> 포인트 : <b><?=$member['lucky_point']?></b>P</div>
                </div>

                <div class="check_list">
                    <div class="tit" >출석인사</div>
                    <ul class="cal_list">
                        <?
                        $list = db_query("select * from event_attendance where date = '".date("Y-m-d")."' order by rank asc ");
                        $cnts = mysqli_num_rows($list);
                        foreach($list as $data){
                            if($data['rank']<=3){
                              $styls ="ranker";
                            }else{
                              $styls ="ranker_none";
                            }
                            $회원 = 로그인정보($data['midx']);
                        ?>
                        <li>
                            <div class="row">
                                <div class="fl">
                                    <span class="<?=$styls?>"><?=$data['rank']?>등</span>
                                    <span class="add_point" >+<?=$data['point']?>P</span>
                                    <span class="ellipsis"><?= 이름가운데별표처리($회원['id'])?></span>
                                </div>
                                <div class="fr">
                                    <div class="tit"><?=$data['title']?></div>
                                    <div class="date"><?= date("y/m/d H:i", strtotime($data['regdate'])) ?></div>
                                </div>
                            </div>
                        </li>
                        <? } ?>
                        <? if($cnts==0){ ?>
                        <li class="none">출석자가 없습니다.</li>
                        <? } ?>
                    </ul>
                </div>


                <div class="calender_info" style="display: none;" >
                    <div>
                        <h2 class="title2" >하루도 빠짐없이 <span class="mo_under">출석체크만 해도</span> 할인쿠폰을 드려요!</h2>
                        <ul>
                            <li>7일 출석시 10%할인쿠폰 지급!</li>
                            <li>14일 출석시 20%할인쿠폰 지급!</li>
                            <li>21일 출석시 30%할인쿠폰 지급!</li>
                        </ul>
                    </div>
                    <div>
                        <ul>
                            <li>이벤트 종료일(4월30일) 까지는 구매이력이 없어도 <?=$config['사이트명2']?> 포인트를 적립 받을 수 있습니다.</li>
                            <li><?=$config['사이트명2']?> 포인트는 티켓 구매시 할인 적용하여 제한 없이 사용가능 합니다.</li>
                            <li>적립 내역은 마이페이지에서 확인 하실 수 있습니다.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <script src='//code.jquery.com/jquery-3.6.1.min.js' type='text/javascript'></script>
        <script src="/assets/js/bpopup.js"></script>
        <script>
        function go_submit() {
          var commentInput = $('.cal_comment');
           var inputText = commentInput.val();

           if (inputText.length < 5) {
             alert("출석인사말은 5자 이상 입력해야 합니다.");
             commentInput.focus(); // 댓글 입력 필드로 포커스 이동
             return false; // 폼 제출 중단
           }

          var params = jQuery("#orderFrm").serialize();
          $.ajax({
            type: "POST",
            url: "ajax_calendar_set.php",
            async: false,
            data: params,
            success: function(result) {
              console.log(result);
              var obj = jQuery.parseJSON(result);
              if (obj.status == 1) {
                alert(obj.rank + "등으로 출석완료! [<?= $config['사이트명2'] ?> 포인트 " + obj.point + "P ]획득!");
                location.reload();
              } else {
                alert(obj.msg);
                location.reload();
              }
            },
            error: function(e) {
              alert(e.responseText);
            }
          });
        }

        </script>

    </div>
</div>
<?php
//include_once "./lib/channer_talk.php";
include "../footer_new.html";
?>
</body>
</html>
