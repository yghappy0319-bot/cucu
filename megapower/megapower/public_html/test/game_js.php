<script>
$(function(){
  var lnb = $("#lnb").offset().top;
  $(window).scroll(function() {
    var scrollTop = $(this).scrollTop(); // window 객체의 scrollTop 속성을 사용하여 스크롤 위치를 가져옵니다.

    if(lnb <= scrollTop) {
      $("#lnb").addClass("back_result_box");
    } else {
      $("#lnb").removeClass("back_result_box");
    }
  })
});
var lucky_deal_event_cnt = "<?=$_grade?>";

function go_info_show(){
  $(".info_hide").toggle();

  if(getCookie("all_view")){
      setCookie("all_view", "", -99);
  }else{
      setCookie("all_view", "1", 1);
  }
}

function generateRandomNumbers(min, max, count) {
  const uniqueNumbers = new Set();
  while (uniqueNumbers.size < count) {
    const randomNum = Math.floor(Math.random() * (max - min + 1)) + min;
    uniqueNumbers.add(randomNum.toString().padStart(2, '0'));
  }
  return Array.from(uniqueNumbers);
}


function go_coupon(){
  $('#popup_coupon_box').bPopup({
       modalClose : true
   });
}

function applyDiscount(originalPrice, discountPercentage) {
      var discountAmount = originalPrice * (discountPercentage / 100);
      var discountedPrice = originalPrice - discountAmount;
      return discountedPrice;
  }

function go_set_coupon(obj,idx){
  go_lucky_reset();
  var originalPrice = parseFloat($("input[name='org_total_amount']").val());
  var discountPercentage = obj;
  var discountedPrice = applyDiscount(originalPrice, discountPercentage);
  var freeval = originalPrice - discountedPrice;
  var coupon_amount = discountedPrice.toLocaleString('ko-KR');
  $("input[name='coupon_idx']").val(idx)
  $(".view_total_amount").html(coupon_amount);
  $("input[name='total_amount']").val(discountedPrice)
  alert(freeval+"P가 할인 적용 되었습니다.");
  $(".coupon_set").html("(-"+freeval+"P 적용)");
  $('#popup_coupon_box').bPopup().close();
}

function _amount(obj,obj2="lucky_point",ck){
  var sell_lucky_point = $("#sell_"+obj2).val();
  var change_amount = (default_amount * obj) - sell_lucky_point;
  $("input[name='total_amount']").val(change_amount);
  var formattedAmount = change_amount.toLocaleString('ko-KR');

  $(".view_total_amount").html(formattedAmount);
  if(!ck){
  $("input[name='org_total_amount']").val(change_amount);
  }
  //console.log(change_amount);
}

function go_all_clear(){
  //초기화
  $(".step2_btn").removeClass("order_btn_active");
  $(".btn-2").addClass("order_btn_active");
  $(".gball").addClass("border-dashed");

  var all = $(".order_game_cnt").val();
  var glist = 0;
  for(i=1;i<=all;i++){

    $(".game_list li").eq(glist).attr("data-game", glist);

    const randum_number = [];
    const generatedNumbers = generateRandomNumbers(1, white_ball_count, 5);
    const megaNumber = generateRandomNumbers(1, last_ball_count, 1)[0];
    randum_number.push(...generatedNumbers);
    randum_number.push(megaNumber);
    var ballarr = randum_number.length;
    for(ba=0;ba<ballarr;ba++){
        $(".view_number"+i+"_"+ba).html("");
    }
    $(".select_number_"+i).val(""); //추가
    glist++;
  }
  go_reset();
}
function go_manual_reset(element){
  $(".setp03btn").removeClass("on");
  // 선택된 요소의 부모에 'on' 클래스 추가
   const parent = element.closest('.setp03btn');
   if (parent) {
     parent.classList.add('on');
   }
}

function go_number_re_choise(num){ //재선택
  const randum_number = [];
  const generatedNumbers = generateRandomNumbers(1, white_ball_count, 5);
  const megaNumber = generateRandomNumbers(1, last_ball_count, 1)[0];
  randum_number.push(...generatedNumbers);
  randum_number.push(megaNumber);

  var ballarr = randum_number.length;
  for(ba=0;ba<ballarr;ba++){
      $(".view_number"+num+"_"+ba).html(randum_number[ba]);
  }
  $(".select_number_"+num).val(randum_number);
  _amount(real_order_cnt());
}

function go_number_re_update(num){ //재선택
  const randum_number = [];
  const generatedNumbers = generateRandomNumbers(1, white_ball_count, 5);
  const megaNumber = generateRandomNumbers(1, last_ball_count, 1)[0];
  randum_number.push(...generatedNumbers);
  randum_number.push(megaNumber);

  var ballarr = randum_number.length;
  for(ba=0;ba<ballarr;ba++){
      $(".view_number"+num+"_"+ba).html("");
  }
  $(".select_number_"+num).val("");

  $(".sct_text_center").show();
  _amount(real_order_cnt());
}


function go_all_reset(element){
  //자동선택
  go_lucky_reset();
  $(".setp03btn").removeClass("on");
  // 선택된 요소의 부모에 'on' 클래스 추가
   const parent = element.closest('.setp03btn');
   if (parent) {
     parent.classList.add('on');
   }

  var glist = 0;
  var all = $(".order_game_cnt").val();
  buy_val = all +1;
  for(i=1;i<=all;i++){
    $(".game_list li").eq(glist).removeAttr("data-game");
    const randum_number = [];
    const generatedNumbers = generateRandomNumbers(1, white_ball_count, 5);
    const megaNumber = generateRandomNumbers(1, last_ball_count, 1)[0];
    randum_number.push(...generatedNumbers);
    randum_number.push(megaNumber);

    var ballarr = randum_number.length;
    for(ba=0;ba<ballarr;ba++){
        $(".view_number"+i+"_"+ba).html(randum_number[ba]);
    }
    $(".select_number_"+i).val(randum_number);
    glist++;
  }
  $(".sct_text_center").show();
  _amount(all);

  $('html, body').animate({
      scrollTop: $('#order_result').offset().top
  }, 500);  // 500ms 동안 부드럽게 스크롤
}




function go_my_choise_number(obj){
  var arr = jQuery.parseJSON(obj);
  var lastNumbers = []; // 마지막 요소를 담을 배열
   var newArr = []; // 나머지 요소를 담을 배열

   $.each(arr, function(index, item) {
       for (var key in item) {
           if (item.hasOwnProperty(key)) {
               var numbers = item[key].split(","); // 문자열을 쉼표로 분할하여 배열로 만듦
               lastNumbers.push(numbers.pop()); // 배열에서 마지막 요소 추출하여 새로운 배열에 추가
               newArr.push(numbers); // 나머지 요소들을 다시 문자열로 합쳐서 새로운 배열에 추가
           }
       }
   });
   var all = newArr.length;
   go_game(all);
   var glist = 0;
   for(i=1;i<=all;i++){
     $(".game_list li").eq(glist).attr("data-game", glist);
     const randum_number = [];
     const generatedNumbers = newArr[glist];
     const megaNumber = lastNumbers[glist];
     randum_number.push(...generatedNumbers);
     randum_number.push(megaNumber);
     var ballarr = randum_number.length;
     for(ba=0;ba<ballarr;ba++){
         $(".view_number"+i+"_"+ba).html(randum_number[ba]);
     }
     $(".select_number_"+i).val(randum_number);
     glist++;
   }
   _amount(all);
   $('#my_number_list').bPopup().close();
}


function real_order_cnt(){
  const lottoNumberInputs = document.querySelectorAll('.lotto_number');
  let nonEmptyCount = 0;
  lottoNumberInputs.forEach(inputElement => {
    const inputValue = inputElement.value;
    if (inputValue.trim() !== '') {
      nonEmptyCount++;
    }
  });
  return nonEmptyCount;
}

function go_reset(obj) {
  const randum_number = [];
  const generatedNumbers = generateRandomNumbers(1, 70, 5);
  const megaNumber = generateRandomNumbers(1, 25, 1)[0]; // 1개 숫자만 선택
  randum_number.push(...generatedNumbers); // 스프레드 연산자를 사용하여 각 요소를 배열에 추가
  randum_number.push(megaNumber); // 마지막 숫자 추가

  var ballarr = randum_number.length;
  for(ba=0;ba<ballarr;ba++){ // game list ball add
      $(".view_number"+obj+"_"+ba).html(randum_number[ba]);
  }
  $(".select_number_"+obj).val(randum_number); //추가
  $(".order_game_cnt").val(real_order_cnt());
  _amount(real_order_cnt());
}

  function view_cnt(obj){
    $(".gamecnt").removeClass("on");
    $(".g"+obj).addClass("on");
    $(".game_cnt").html(obj);
    $(".order_game_cnt").val(obj);
  }
  function go_lucky_reset(){
    $(".coupon_set").html("");
    var org_total_amount = $("input[name='org_total_amount']").val(); //원금
    $("input[name='total_amount']").val(org_total_amount); //최종결제금액 초기화
    $(".amount_input").val(0); //포인트 초기화
    $("input[name='coupon_idx']").val(""); //쿠폰도 초기화
    $(".view_total_amount").html(addCommas(org_total_amount));
  }
  function go_game(obj){

    go_lucky_reset();
    $("input[name='coupon_idx']").val("");
    $(".step2_btn").removeClass("order_btn_active");
    $(".lotto_number").val("");
    buy_val = 1;

    view_cnt(obj);

    var html = "";
    b = 1;
    for(var a=0;a<obj;a++){

      html+= "<li class='list" + b + "' >";
      html+= "    <div class='wrap'>";
      html+= "        <div class='tit'>"+b+"게임</div>";
      html+= "        <div class='ball'>";
      html+= "            <p class='view_number"+b+"_0'></p>";
      html+= "            <p class='view_number"+b+"_1'></p>";
      html+= "            <p class='view_number"+b+"_2'></p>";
      html+= "            <p class='view_number"+b+"_3'></p>";
      html+= "            <p class='view_number"+b+"_4'></p>";
      html+= "            <p class='view_number"+b+"_5'></p>";
      html+= "        </div>";
      html+= "        <div class='btn'>";
      html+= "            <a href='javascript:go_number_re_choise("+b+")' >재선택</a>";
      html+= "            <a href='javascript:go_number_re_update("+b+")'>수정</a>";
      html+= "        </div>";
      html+= "    </div>";
      html+= "</li>";

      $(".game_list").html(html);
      b++;
    }
    _amount(obj);
  }

  function getSelectedMegaNumber() {
    const selectedMegaBall = document.querySelector('.lotto-last-ball.on');

    if (selectedMegaBall) {
      const megaNumber = parseInt(selectedMegaBall.textContent).toString().padStart(2, '0');
      return megaNumber;
    } else {
      return null; // 선택된 메가 번호가 없는 경우
    }
  }

  function findEmptyIndexes(maxNumber) {
      const emptyIndexes = []; // 비어있는 인덱스를 저장할 배열

      for (let i = 1; i <= maxNumber; i++) {
          const selector = `.select_number_${i}`; // 클래스명 생성
          const value = $(selector).val(); // 각 요소의 value 가져오기

          if (!value || value.trim() === '') { // 값이 없거나 공백인 경우
              emptyIndexes.push(i); // 비어있는 인덱스 추가
          }
      }

      return emptyIndexes; // 비어있는 인덱스 배열 반환
  }

function go_set(){
  buy_val = real_order_cnt()+1;
  // 사용 예시



  if(buy_val > lot_cnt){
    alert(lot_cnt+"게임 이상은 구입하실 수 없습니다.");
    return false;
  }

  const selectedLottoBalls = document.querySelectorAll('.lotto-ball.on');
  const selectedNumbers = Array.from(selectedLottoBalls).map(ball => parseInt(ball.textContent).toString().padStart(2, '0'));
  const arrayLength = selectedNumbers.length;


  if(arrayLength!=5){
    alert("화이트볼 5개를 선택해주세요.");
    return false;
  }

  const selectedMegaNumber = getSelectedMegaNumber();
  if (selectedMegaNumber !== null) { // last ball
    selectedNumbers.push(selectedMegaNumber);

    if(!$(".select_number_"+buy_val).val()){ //값이 없으면
      $(".select_number_"+buy_val).val(selectedNumbers); //추가

      const list1Exists = document.querySelector('.game_list .list'+buy_val) !== null;
      if (!list1Exists) { // game list dom add
        b = buy_val;
        var html = "";

        html+= "<li class='list" + b + "' >";
        html+= "    <div class='wrap'>";
        html+= "        <div class='tit'>"+b+"게임</div>";
        html+= "        <div class='ball'>";
        html+= "            <p class='view_number"+b+"_0'></p>";
        html+= "            <p class='view_number"+b+"_1'></p>";
        html+= "            <p class='view_number"+b+"_2'></p>";
        html+= "            <p class='view_number"+b+"_3'></p>";
        html+= "            <p class='view_number"+b+"_4'></p>";
        html+= "            <p class='view_number"+b+"_5'></p>";
        html+= "        </div>";
        html+= "        <div class='btn'>";
        html+= "            <a>재선택</a>";
        html+= "            <a>수정</a>";
        html+= "        </div>";
        html+= "    </div>";
        html+= "</li>";


        $(".game_list").append(html);
        view_cnt(b);
      }

      var ballarr = selectedNumbers.length;
      for(ba=0;ba<ballarr;ba++){ // game list ball add
          $(".view_number"+buy_val+"_"+ba).html(selectedNumbers[ba]);
      }
      $(".order_game_cnt").val(buy_val);
      _amount(buy_val);
      buy_val++;
    }else{

      const emptyIndexes = findEmptyIndexes(real_order_cnt());
      if(emptyIndexes){
        var ballarr = selectedNumbers.length;
        for(ba=0;ba<ballarr;ba++){ // game list ball add
            $(".view_number"+emptyIndexes+"_"+ba).html(selectedNumbers[ba]);
        }
          $(".select_number_"+emptyIndexes).val(selectedNumbers);
      }
      _amount(real_order_cnt());


      var cnt = real_order_cnt() +1; //현재 있는 개수 +1
      //console.log(real_order_cnt());
    }
    $('.game_step3').bPopup().close();


    for (const number of selectedNumbers) {
      selectedNewNumbers.push(number);
    }
    $(".sct_text_center").show();
    $(".lotto-ball").removeClass("on");
    $(".lotto-last-ball").removeClass("on");
    selectedNumbers.length = 0;
    $(".white_ball").html(5);

  } else {
    alert("마지막 "+last_ball_name+" 1개를 선택해주세요.");
  }
}


function go_reset(){
  $(".lotto-ball").removeClass("on");
  $(".lotto-last-ball").removeClass("on");
  selectedNumbers.length = 0;
}

const container = document.getElementById("lotto-container");
function toggleSelect(ball, number) { //white ball select
  var d_ball = 5;
  const isSelected = ball.classList.toggle("on");

  if(selectedNewNumbers.length>5){
    selectedNewNumbers.length = 0;
    selectedNumbers.length = 0;
  }
  if (isSelected) {
    if (selectedNumbers.length < 5) {

      selectedNumbers.push(number);
      $(".white_ball").html(d_ball-selectedNumbers.length);
    } else {
      // selectedNew.length
      ball.classList.remove("on");
      alert("최대 5개까지 선택할 수 있습니다.");
    }
  } else {
    const index = selectedNumbers.indexOf(number);
    if (index !== -1) {
      selectedNumbers.splice(index, 1);
    }
    $(".white_ball").html(d_ball-selectedNumbers.length);
  }
}

// <li><div class="wrap"><p>2</p></div></li>

function go_lotto(obj){
  for (let number = 1; number <= white_ball_count; number++) {
    const ball = document.createElement("li"); // <li> 생성
    ball.classList.add("lotto-ball"); // li에 클래스 추가

    const wrap = document.createElement("div"); // <div class="wrap"> 생성
    wrap.classList.add("wrap");

    const paragraph = document.createElement("p"); // <p> 생성
    paragraph.textContent = number; // <p> 안에 숫자 추가

    // 구조 조합
    wrap.appendChild(paragraph); // <div class="wrap"> 안에 <p> 추가
    ball.appendChild(wrap); // <li> 안에 <div> 추가

    // 클릭 이벤트 추가
    ball.addEventListener("click", () => toggleSelect(ball, number));

    // .ball-box에 추가
    $(".ball-box").append(ball);
  }
  //$(".lotto-ball").first().addClass("on");

  const lottoContainer = document.getElementById("lotto-last-container");

  for (let i = 1; i <= last_ball_count; i++) {
    // <li> 생성
    const lottoBall = document.createElement("li");
    lottoBall.classList.add("lotto-last-ball"); // li에 클래스 추가

    // <div class="wrap"> 생성
    const wrap = document.createElement("div");
    wrap.classList.add("wrap");

    // <p> 생성
    const paragraph = document.createElement("p");
    paragraph.textContent = i; // 숫자 추가

    // 구조 조합
    wrap.appendChild(paragraph); // <div> 안에 <p> 추가
    lottoBall.appendChild(wrap); // <li> 안에 <div> 추가

    // 클릭 이벤트 추가
    lottoBall.addEventListener("click", () => handleLottoBallClick(lottoBall));

    // <li>를 lottoContainer에 추가
    lottoContainer.appendChild(lottoBall);
  }
  //$(".lotto-last-ball").first().addClass("on");



}

let selectedBall = null;
function handleLottoBallClick(ball) {
  if (selectedBall) {
    selectedBall.classList.remove("on");
  }
  if (selectedBall !== ball) {
    selectedBall = ball;
    selectedBall.classList.add("on");
    const selectedNumber = parseInt(selectedBall.textContent);
  } else {
    selectedBall = null;
  }
}

function go_countEmptyValues(maxNumber) {
    let emptyCount = 0; // 비어있는 요소 개수 초기화

    for (let i = 1; i <= maxNumber; i++) {
        const selector = `.select_number_${i}`; // 클래스명 생성
        const value = $(selector).val(); // 각 요소의 value 가져오기

        if (!value || value.trim() === '') { // 값이 없거나 공백인 경우
            emptyCount++; // 비어있는 요소 개수 증가
        }
    }

    return emptyCount; // 비어있는 요소 개수 반환
}

function go_buy(){
  //alert("teset");
  if(real_order_cnt()==0){
    alert('번호를 선택하여 담기 버튼을 눌러주세요.');
    return false;
  }

  var prosess = $("#prosess").val();
  if(prosess==1){
    alert('구매중 진행중 입니다. 잠시만 기다려주세요!');
    return false;
  }

  var params = jQuery("#buyFrm").serialize();
  $.ajax({
    type : "POST",
    url: "_ajax_buy.php",
    async: false,
    data: params,
    success: function(result) {
    var r = result.trim();
    console.log(r);
      if(r!=1){
        alert(r);
        return false;
      }else{
        $("#prosess").val(1);
        // alert("정상적으로 구매되었습니다.");
        var game = $("#order_game_name").val();
        var round = $("#order_round").val();

        if(game=="pb"){
<?=$config['script_power']?>



          game = "파워볼";
          $(".buy_confim_btn").attr("href","/member/buy.html?game=pb");
        }else{
<?=$config['script_mega']?>



          game = "메가밀리언";
          $(".buy_confim_btn").attr("href","/member/buy.html?game=mm");
        }
        $(".sgame").html(game);
        $(".sround").html(round);



        $("#completed .date").html($("#drawing_day").val());
        $('#completed').bPopup({
             modalClose : true,
             onClose: function() {
               location.reload();
              }
         });
      }
    },
    error:function(e) {
      alert(e.responseText);
    }
  });
}

<? if($status=="pc"){?>
  go_lotto(<?=$default_select_game?>);
  go_game(<?=$default_select_game?>);
<? }else{ ?>
  go_lotto(<?=$default_select_game?>);
  go_game(<?=$default_select_game?>);
<? } ?>

function go_speed_game(){

  go_all_reset();
  go_next_step('mobile_game',1,3);
  window.scrollBy(0,-120);
}
function go_game_add(){
  $('.game_step3').bPopup({
       modalClose : true
   });
}

function go_max_luckypoint(obj,obj2){
  $(".amount_input").val("");
  var org_total_amount = $("input[name='org_total_amount']").val(); //원금
  var order_game_cnt = $(".order_game_cnt").val(); //게임수
  var sell_point = $("#sell_"+obj2).val(); //포인트타입
  if(sell_point>0){
    return false;
  }

  $.ajax({
      type : "POST",
      url: "_ajax_max_lucky_point.php",
      async: false,
      data: {
        idx : obj,
        point_type : obj2,
        total_amount : org_total_amount
      },
      success: function(result) {
        console.log(result);
        var r = jQuery.parseJSON(result);
         if(r.status=="idx_null"){
           alert("회원만 이용가능한 서비스 입니다.");
           _go_login();
           return false;
         }else if(r.status=="lucky_point_null"){
           alert("보유 럭키포인트가 없습니다.");
           return false;
         }else if(r.status=="max_point"){
           $("#"+r.point_type).val(r.sell_point);
           $("#sell_"+obj2).val(r.sell_point);
           _amount(order_game_cnt,obj2,1);
         }else if(r.status=="ok_point"){
            console.log(r.total_amount);
            $("#"+r.point_type).val(r.sell_point);
            $("#sell_"+obj2).val(r.sell_point);
            _amount(order_game_cnt,obj2,1);
            // $("#total_amount").val(r.total_amount);
         }else{
           //$("#lucky_point").val(r);
         }
      },
      error:function(e) {
         alert(e.responseText);
      }
   });
}

$('._point').keyup(function (){
  var _value = this.value;
  console.log(_value);
  if(!_value){
    console.log("값이 비어");
    this.value=0;
  }
  if (_value.length > 1) {
   this.value = _value.replace(/^0+/, '');
  }
  var _point = $(this).data('point');
  var total_amount =  $("#total_amount").val();
  var order_game_cnt = $(".order_game_cnt").val();
  var org_total_amount = $("input[name='org_total_amount']").val();
  $("input[name='coupon_idx']").val("");
  var potitle = "";
  var luckypoint = this.value;
  $.ajax({
      type : "POST",
      url: "_ajax_lucky_point_chk.php",
      async: false,
      data: {
        _point : _point,
        lucky_point : luckypoint
      },
      success: function(result) {
         var r = result.trim();
        if(_point=="prize_money"){
          potitle = "당첨금이";
        }else if(_point=="lucky_point"){
          potitle = "럭키포인트가";
        }

         if(r=="point_over"){
           alert("사용가능한 "+potitle+" 부족합니다.");
           $("#"+_point).val("");
           $("#sell_"+_point).val("");
          _amount(order_game_cnt);
           return false;
         }else{
            var price = parseInt(org_total_amount) - parseInt(luckypoint);
            if(price >= 0){
              $("#total_amount").val(price)
              $(".view_total_amount").html(price.toLocaleString('ko-KR'));
              $("#sell_"+_point).val(luckypoint);
            }else{
              $("#"+_point).val("");
              $("#sell_"+_point).val("");
              _amount(order_game_cnt);
            }
         }
      },
      error:function(e) {
         alert(e.responseText);
      }
   });
});

$('#lucky_point').click(function (){
  var coupon_use = $("input[name='coupon_idx']").val() // 쿠폰 사용여부
  if(coupon_use>0){
    alert("주문 한 건당 중복 사용이 안됩니다.\n쿠폰/당첨금/메가파워월드포인트 중 한개만 적용 해주세요.");
    return false;
  }

  go_lucky_reset();
  if(this.value==0){
    $("#lucky_point").val("");
  }else{
    var order_game_cnt = $(".order_game_cnt").val();
    _amount(order_game_cnt);
  }
});
function addCommas(number) {
    // 숫자를 문자열로 변환 후 천 단위 콤마 삽입
    return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}
$('#prize_money').click(function (){
  var coupon_use = $("input[name='coupon_idx']").val() // 쿠폰 사용여부
  if(coupon_use>0){
    alert("주문 한 건당 중복 사용이 안됩니다.\n쿠폰/당첨금/메가파워월드포인트 중 한개만 적용 해주세요.");
    return false;
  }
  go_lucky_reset();
  if(this.value==0){
    $("#prize_money").val("");
  }else{
    var order_game_cnt = $(".order_game_cnt").val();
    _amount(order_game_cnt);
  }
});

function go_my_number_save(){
  if(real_order_cnt()==0){
    alert('내 번호로 저장할 번호를 선택해주세요.');
    return false;
  }

  var params = jQuery("#buyFrm").serialize();
  $.ajax({
    type : "POST",
    url: "_ajax_my_number_save.php",
    async: false,
    data: params,
  success: function(result) {
  var r = result.trim();

    if(r==1){
      alert("내 번호로 저장되었습니다.");
      return false;
    }else{
      alert(r);
      return false;
    }

    },
    error:function(e) {
      alert(e.responseText);
    }
  });
}


function go_my_number_list(game){
  $(".new_popup_my_save_ball").addClass("on")

   $.ajax({
       type : "POST",
       url: "_ajax_my_number_list.php",
       async: false,
       data: {
         gubun : game,
         lucky_deal_cnt : lucky_deal_event_cnt
       },
       success: function(result) {
          var r = result.trim();
          $(".new_popup_my_save_ball").html(r);
       },
       error:function(e) {
          alert(e.responseText);
       }
    });

}



</script>
