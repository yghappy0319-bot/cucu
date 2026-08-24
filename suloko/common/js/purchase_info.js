$(function() {
  makeContents();

  let gubun = $('#gubun').val();
  let data = null;
  let title = null;

  $.post('/mode.php', {
    mode: 'purchaseInfo'
  }, function(e) {
    if (!e.error) {
      if (gubun == 'MM') {
        data = e.MM
        title = '메가밀리언';
      } else if (gubun == 'PB') {
        data = e.PB
        title = '파워볼';
      }

      $('.gubun').text(title);
      $('.mega-price').text(data.price);
      $('.mega-price-text').text(data.priceKRW);
      $('.mega-priceDollar').text(data.priceUSD);
      $('.mega-s-time').text(data.s_date);
      $('.mega-e-time').text(data.e_date);
      $('.mega-es-time').text(data.es_date);
      $('.mega-drawnum').text(data.gameNo);
      $('.mega-drawnum-prev').text(data.gameNo_prev);
      $('.mega-price-est').text(data.price_est);
      $('.mega-price-est-text').text(data.priceKRW_est);
      $('.mega-price-fst').text(data.price_fst);
      $('.mega-price-fst-text').text(data.priceKRW_fst);
      $('.mega-draw-today-time').text(data.draw_now_date);
      $('.mega-nomal-style').css('display', data.nomal_style);
      $('.mega-deadline-style').css('display', data.deadline_style);

      updateDueTime(new Number(data.remain), new Date());
    }
  }, 'json');
});

function updateDueTime(remainTime, startDate) {
  update();
  setInterval(() => {update()}, 1000);

  function update() {
    let currentDate = new Date();
    let timePast    = (currentDate.getTime() - startDate.getTime()) / 1000;
    let timeRemain  = remainTime - timePast;

    let days    = Math.floor(timeRemain / 86400);
    timeRemain  = timeRemain % 86400;
    let hours   = String(Math.floor(timeRemain / 3600)).padStart(2, '0');
    timeRemain  = timeRemain % 3600;
    let minutes = String(Math.floor(timeRemain / 60)).padStart(2, '0');
    timeRemain  = timeRemain % 60;
    let seconds = String(Math.floor(timeRemain)).padStart(2, '0');

    let html = `${days}일 ${hours}시 ${minutes}분 ${seconds}초`;
    $('#lottery-dDay').text(html);
  }
};

function makeContents() {
  let contents = `
    <ul class="flex align_center">
      <li class="text-center">
        <div class="mega-nomal-style">
          <p class="title"><span class="gubun"></span><strong> 1등 당첨금</strong></p>
          <strong class="txt_type1 ls25 color_000">￦
            <span class="mega-price">0</span>
            <span class="color_red"> (<span class="mega-price-text">0</span>원)</span>
          </strong>
          <p><span>$ <span class="mega-priceDollar">0</span></span></p>
        </div>
        <div class="mega-deadline-style" style="display: none;">
          <p class="fs17">지금은
            <span class="color_red">
              <span class="mega-drawnum">0</span>회차
            </span>
            주문 접수 중입니다.</br>1등 당첨금은 아래 내용을 확인하여 주십시오.
          </p>
          <p style="border-bottom: 1px solid #d2d2d2; width: 90%; margin: 1rem;"></p>
          <p class="fs17" style="text-align: left; padding-left: 1rem;">
            <span>Case 1.
              <span class="mega-drawnum-prev">0</span>
              회차 1등 당첨자 미 발생시 예상 금액</br>
              <span class="txt_type1 color_red">₩
                <span class="mega-price-est">0</span> (<span class="mega-price-est-text">0</span>원)
              </span></br></br>
            </span>
          </p>
          <p class="fs17" style="text-align: left; padding-left: 1rem;">
            <span>Case 2.
              <span class="mega-drawnum-prev">0</span>
              회차 1등 당첨자 발생시 시작 금액</br>
              <span class="txt_type1 color_000">₩
                <span class="mega-price-fst">0</span> (<span class="mega-price-fst-text">0</span>원)</span></br>
              </span>
          </p>
        </div>
      </li>
      <li>
        <div>
          <p class="title add_after ico_date">
            <span class="va_mid">추첨일시</span>
            <span class="date">
              <strong class="mega-drawnum">0</strong> 회차
            </span>
          </p>
          <ul class="nation">
            <li class="ko add_before ico_ko"><span class="va_mid mega-e-time"></span></li>
            <li class="us add_before ico_us"><span class="va_mid mega-s-time"></span></li>
          </ul>
        </div>
      </li>
      <li>
        <div>
          <p class="title add_after ico_time_ko"><span class="va_mid">주문마감일시</span></p>
          <span class="mega-es-time"></span>
          <p class="text-center"><strong class="color_red txt_type1 ls20" id="lottery-dDay"></strong></p>
        </div>
        <div class="mega-deadline-style" style="display: none;">
          <p class="fs15 pt30" style="color: #FF0000;">※
            <span class="mega-drawnum-prev">0</span>
            회차 1등 당첨자 확정 공시
            <span class="mega-draw-today-time"></span>(변동가능)
          </p>
        </div>
      </li>
    </ul>
  `;
  $('.sec_info .purchase_info').append(contents);
};


function scrollToSmoothly(targetPosition, duration) {
  let startPosition = window.scrollY || window.pageYOffset;
  let distance = targetPosition - startPosition;
  let startTime = null;

  function animation(currentTime) {
    if (startTime === null) startTime = currentTime;
    let elapsedTime = currentTime - startTime;
    let scrollAmount = easeInOut(elapsedTime, startPosition, distance, duration);
    window.scrollTo(0, scrollAmount);
    if (elapsedTime < duration) {
      requestAnimationFrame(animation);
    }
  }

  function easeInOut(t, b, c, d) {
    t /= d / 2;
    if (t < 1) return c / 2 * t * t + b;
    t--;
    return -c / 2 * (t * (t - 2) - 1) + b;
  }

  requestAnimationFrame(animation);
}
