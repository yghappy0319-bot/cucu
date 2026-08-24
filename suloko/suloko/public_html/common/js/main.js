'use strict';

$(function() {
  main_data();

  $.post('/other.php', {
    mode: 'type_count'
  }, function(e) {
    // 주문 마감 시간이 12시간 이하로 남았을 경우 효과
    let deadline = 43200000;
    if (e.MM.timediff <= deadline) {
      $('#mega_defaultTime').addClass('hide');
      $('#mega_nearTime').removeClass('hide');
    } else if (e.PB.timediff <= deadline) {
      $('#power_defaultTime').addClass('hide');
      $('#power_nearTime').removeClass('hide');
    }
    $('.mmCountdown').jCountdown(setCountDown(e.MM.eo_date));
    $('.pbCountdown').jCountdown(setCountDown(e.PB.eo_date));
  }, 'json');

  // 당첨 티켓 및 정보
  $.post('/other.php', {
    mode: 'getWinners'
  }, function(e) {
    $('.winner_list').append(e.html);
  }, 'json');
});

setTimeout(() => {
  const $wrap = $('.flow_banner');
  const $list = $('.flow_banner .list');
  let listWidth = $list.width();
  const speed = 92; // 1초에 몇 픽셀 이동하는지 설정
  let isPaused = false; // 마우스 호버 시 일시 정지 상태를 나타내는 변수 추가

  // 리스트 복제
  let $clone = $list.clone();
  $wrap.append($clone);

  // 배너 실행 함수
  function flowBannerAct() {
    // 마우스 호버 시 일시 정지 및 재개 이벤트 추가
    $wrap.on('mouseenter', function () {
      isPaused = true;
      $wrap.find('.list').css({
        'animation-play-state': 'paused'
      });
    }).on('mouseleave', function () {
      isPaused = false;
      $wrap.find('.list').css({
        'animation-play-state': 'running'
      });
    });

    // 초기 배너 상태 설정
    if (!isPaused) {
      $wrap.find('.list').css({
        'animation': `${listWidth / speed}s linear infinite flowRolling`
      });
    }
  }

  flowBannerAct();
}, 3000);

// 누적 당첨자 수 & 누적 당첨금
const counter = ($counter, max, type) => {
  let now = max;

  const handle = setInterval(() => {
    let result = Math.ceil(max - now).toLocaleString('ko-KR');
    if (type) {
      $counter.innerHTML = result + ' <span class="unit">원</span>';
      $('.sumMoney').html(max.toLocaleString('ko-KR'));
    } else {
      $counter.innerHTML = result + ' <span class="unit">명</span>';
      $('.cntWinner').html(max.toLocaleString('ko-KR'));
    }

    // 목표수치에 도달하면 정지
    if (now < 1) {
      clearInterval(handle);
    }

    // 증가되는 값이 계속하여 작아짐
    const step = now / 50;

    // 값을 적용시키면서 다음 차례에 영향을 끼침
    now -= step;
  }, 10);
}

$('.winner_last_detail_btn').click(function() {
  let no = $(this).data('no');
  let PB_dollar = $('.power-priceDollar').html();
  let MM_dollar = $('.mega-priceDollar').html();

  $.post('/other.php', {
    mode: 'winner_detail',
    no: no,
    PB_dollar: PB_dollar,
    MM_dollar: MM_dollar
  }, function(e) {
    if (e.cla == 'power') {
      $('#wininfo_balls_img').attr('src', '/common/images/logo_powerball_202x56.png');
    } else {
      $('#wininfo_balls_img').attr('src', '/common/images/logo_mega_163x76.png');
    }
    $('#last_wininfo_price_no_cnt').html(e.html_no_cnt);

    if (e.PRIZ1 == '20000000' || e.PRIZ1 == '50000000') {
      $('#nomal_info_div').hide();
      $('#winner_info_div').show();
    } else {
      $('#nomal_info_div').show();
      $('#winner_info_div').hide();
    }

    if (e.cla == "mega" && e.DRAWNUM >= 2065) {
      $('.mega_multiplier_msg').show();
    } else {
      $('.mega_multiplier_msg').hide();
    }
  }, 'json');
});

// 앱 2.0 설치 이벤트 popup close
$('.btn_close_eventpopup').click(function() {
  $('.webapp_event_popup').hide();
})

// 구매 안내 video
$('.show_video').click(function() {
  let youtube = $(this).data('youtube');
  let draw    = $(this).data('draw');
  let tp      = $(this).data('tp');
  youtube	=	youtube.split('v=');

  $('#youtube_draw').html(draw + '회차');

  if (tp === 'PB') {
    $('#youtube_draw_title').html('파워볼');
  } else if (tp === 'MM') {
    $('#youtube_draw_title').html('메가밀리언');
  }

  $('#youtube_ifrm').attr('src','https://www.youtube.com/embed/' + youtube[1]);
});

// 추천 영상 video
$('.suggest_video').click(function() {
  let youtube	=	$(this).data('youtube');
  let title	=	$(this).data('title');

  $('#hot_youtube_title').html(title);
  $('#hot_youtube_ifrm').attr('src', youtube);
});

function main_data() {
  $.post('/other.php', {
    mode: 'mainnew'
  }, function(e) {
    let mm = e.MM;
    let pb = e.PB;

    $('.power-drawnum').html(pb.gameNo);
    $('.mega-drawnum').html(mm.gameNo);
    $('.power-price').html('₩ ' + pb.allprice);
    $('.mega-price').html('₩ ' + mm.allprice);
    $('.power-price-text').html(pb.price);
    $('.mega-price-text').html(mm.price);
    $('.power-priceDollar').html('$' + pb.priceDollar);
    $('.mega-priceDollar').html('$' + mm.priceDollar);
    $('.power-k-time').html(pb.play_k_date);
    $('.mega-k-time').html(mm.play_k_date);

    $('.mega-2nd-min-prize').html(mm.secondMinPrize);
    $('.mega-2nd-max-prize').html(mm.secondMaxPrize);
    $('.power-2nd-prize').html(pb.secondPrize);
    $('.power-3rd-prize').html(pb.thirdPrize);

    $('.power-drawnum-prev').html(pb.gameNo_prev);
    $('.power-price-est').html(pb.allprice_est);
    $('.power-price-est-text').html(pb.price_est);
    $('.power-price-fst').html(pb.allprice_fst);
    $('.power-price-fst-text').html(pb.price_fst);
    $('.power-draw-today-time').html(pb.draw_now_date);
    $('.power-nomal-style').css('display', pb.nomal_style);
    $('.power-deadline-style').css('display', pb.deadline_style);

    $('.mega-drawnum-prev').html(mm.gameNo_prev);
    $('.mega-price-est').html(mm.allprice_est);
    $('.mega-price-est-text').html(mm.price_est);
    $('.mega-price-fst').html(mm.allprice_fst);
    $('.mega-price-fst-text').html(mm.price_fst);
    $('.mega-draw-today-time').html(mm.draw_now_date);
    $('.mega-nomal-style').css('display', mm.nomal_style);
    $('.mega-deadline-style').css('display', mm.deadline_style);
  }, 'json');
}

// 주문 마감 시간 countdown 설정
function setCountDown(date) {
  let b = 9;
  let i = date;
  let config = {
    timeText: i,           // 마감 시간
    timeZone: b,           // 현재 시간
    style: 'flip',         // 샘플 종류 (flip, slide, metal, crystal)
    color: 'black',        // 색상 (white, black)
    width: 230,            // 넓이
    textGroupSpace: 30,    // 일자, 시, 분, 초 사이의 간격
    textSpace: 0,          // 숫자 간의 간격
    reflection: 0,         // 음영 처리 여부
    reflectionOpacity: 10, // 음영 부분 투명도
    reflectionBlur: 0,     // 음영 부분 흐릿함
    dayTextNumber: 2,      // 마감 시간 일자 개수
    displayDay: !0,        // 일자 출력 여부
    displayHour: !0,       // 시간 출력 여부
    displayMinute: !0,     // 분 출력 여부
    displaySecond: !0,     // 초 출력 여부
    displayLabel: !0,      // 하단 label 출력 여부
    onFinish: function() {}
  };
  return config;
}

(function onYouTubeIframeAPIReady() {
  const tag = document.createElement('script');
  tag.src = "https://www.youtube.com/iframe_api";
  const firstScriptTag = document.getElementsByTagName('script')[0];
  firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);

  tag.onload = setupPlayer;
})();

let player = null;

function setupPlayer() {
  window.YT.ready(function() {
    player = new YT.Player('muteYouTubeVideoPlayer', {
      videoId: 'Sg4yAjTn-gM', // YouTube Video ID
      width: 330,             // Player width (in px)
      height: 185.625,        // Player height (in px)
      playerVars: {
        autoplay: 1,          // 자동 재생 여부 설정 (1: 자동, 2: 수동)
        controls: 0,          // 재생 컨트롤 표시 여부 설정 (1: 표시, 2: 숨김)
        showinfo: 0,          // 동영상 정보 표시 여부 설정 (1: 표시, 2: 숨김)
        modestbranding: 1,    // YouTube 로고 크기와 표시 여부 설정 (1: 작은 로고, 2: 기본 로고 크기)
        loop: 1,              // 동영상 반복 재생 여부 설정 (1: 반복 재생, 2: 반복하지 않음)
        fs: 0,                // 전체 화면 버튼 표시 여부 설정 (1: 표시, 0: 숨김)
        cc_load_policy: 0,    // 자막 표시 여부 설정 (1: 표시, 2: 숨김)
        iv_load_policy: 3,    // 인터액션 표시 여부 설정 (1: 표시, 2: 숨김)
      },
      events: {
        onReady: onPlayerReady,
        onStateChange: onPlayerStateChange
      },
    });
  });
}

function onPlayerReady(event) {
  event.target.mute();
  event.target.playVideo();
}

function onPlayerStateChange(event) {
  if (event.data === YT.PlayerState.ENDED) {
    event.target.playVideo();
  }
}

// 긴급공지 롤링
let height = $(".notice").height();
let num = $(".rolling li").length;
let max = height * num;
let move = 0;

let noticeRollingOff = setInterval(noticeRolling, 3000);
$(".rolling").append($(".rolling li").first().clone());

function noticeRolling() {
  move += height;
  $(".rolling").animate({ top: -move }, 600, function () {
    if (move >= max) {
      $(this).css("top", 0);
      move = 0;
    }
  });
}

// 메인팝업배너컨트롤
document.addEventListener("DOMContentLoaded", function () {
  document.querySelectorAll(".modal-main-banner").forEach(modal => {
    const modalId = modal.getAttribute("data-modal-id");
    const closeBtn = modal.querySelector(".close-modal");
    const closeBtnX = modal.querySelector(".close-modal-x");
    const dontShow1Day = modal.querySelector(".dont-show-1-day");
    const dontShow7Days = modal.querySelector(".dont-show-7-days");

    function setCookie(name, value, days) {
      const expireDate = new Date();
      expireDate.setDate(expireDate.getDate() + days);
      document.cookie = `${name}=${value}; expires=${expireDate.toUTCString()}; path=/`;
    }

    function getCookie(name) {
      const cookies = document.cookie.split("; ");
      for (let cookie of cookies) {
        const [key, value] = cookie.split("=");
        if (key === name) return value;
      }
      return null;
    }

    function setModalHidden(days) {
      setCookie(`modalHiddenUntil_${modalId}`, "hidden", days);
      modal.style.display = "none";
    }

    function checkModalVisibility() {
      if (!getCookie(`modalHiddenUntil_${modalId}`)) {
        modal.style.display = "block";
      }
    }

    closeBtn.addEventListener("click", () => modal.style.display = "none");
    closeBtnX.addEventListener("click", () => modal.style.display = "none");
    dontShow1Day.addEventListener("click", () => setModalHidden(1));
    dontShow7Days.addEventListener("click", () => setModalHidden(7));

    modal.addEventListener("click", (event) => {
      if (event.target === modal) {
        modal.style.display = "none";
      }
    });

    checkModalVisibility();
  });
});
