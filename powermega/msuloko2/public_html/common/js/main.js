'use strict';

// PWA 설치 관련 전역 변수
let deferredPwaPrompt = null;
let canShowPwaInstallPrompt = false;


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

  // 당첨 flow 배너: 자동 롤링 + 터치로 좌우 스와이프 가능 (스크롤 방식)
  window.flowBannerInited = false;
  function initFlowBannerHorizontal() {
    const $wrap = $('.flow_banner');
    const $list = $('.flow_banner .list').first();
    if (!$list.length || !$list.children().length) return;
    if ($wrap.find('.list').length > 1) return;
    let listWidth = $list.width();
    if (listWidth <= 0) return;
    const speed = 92; // px/s
    let isPaused = false;
    let userScrolling = false;
    let rafId = null;
    let lastTime = 0;
    const cycleTime = (listWidth / speed) * 1000;
    const pauseTime = 2000;
    let pauseUntil = 0;

    let $clone = $list.clone();
    $wrap.append($clone);
    window.flowBannerInited = true;

    // 호버가 있는 기기에서만 터치 시 롤링 멈춤 방지: 마우스 올렸을 때만 일시정지
    if (window.matchMedia('(hover: hover)').matches) {
      $wrap.on('mouseenter', function () { isPaused = true; });
      $wrap.on('mouseleave', function () { isPaused = false; });
    }

    $wrap[0].scrollLeft = 0;

    function autoScroll(now) {
      if (!rafId) return;
      const el = $wrap[0];
      if (isPaused || userScrolling) {
        lastTime = now;
        rafId = requestAnimationFrame(autoScroll);
        return;
      }
      if (now < pauseUntil) {
        lastTime = now;
        rafId = requestAnimationFrame(autoScroll);
        return;
      }
      const dt = (now - lastTime) / 1000;
      lastTime = now;
      el.scrollLeft += speed * dt;
      if (el.scrollLeft >= listWidth) {
        el.scrollLeft -= listWidth;
        pauseUntil = now + pauseTime;
      }
      rafId = requestAnimationFrame(autoScroll);
    }

    function startAutoScroll() {
      lastTime = performance.now();
      rafId = requestAnimationFrame(autoScroll);
    }

    $wrap.on('touchstart', function () {
      userScrolling = true;
    });
    $wrap.on('touchend touchcancel', function () {
      setTimeout(function () { userScrolling = false; }, 50);
      var el = $wrap[0];
      while (el.scrollLeft >= listWidth) el.scrollLeft -= listWidth;
      while (el.scrollLeft < 0) el.scrollLeft += listWidth;
    });

    startAutoScroll();
  }

  // 당첨 티켓 및 정보
  let winnerSwiper = null;
  $.post('/other.php', {
    mode: 'getWinners'
  }, function(e) {
    $('.winner_list').append(e.html);
    // 모바일: 스와이프 슬라이드 초기화
    if (typeof Swiper !== 'undefined' && window.matchMedia('(max-width: 768px)').matches) {
      winnerSwiper = new Swiper('.winner_swiper.swiper-container', {
        slidesPerView: 1.15,
        spaceBetween: 12,
        centeredSlides: true,
        loop: true,
        touchRatio: 1,
        grabCursor: true
      });
    }
    // 당첨 flow 배너 자동 롤링: 모바일·데스크톱 공통, 목록 로드 후 초기화
    requestAnimationFrame(function() {
      initFlowBannerHorizontal();
    });
  }, 'json');

  // PWA (serviceworker & pushmanager)
  if ('serviceWorker' in navigator && 'PushManager' in window) {
    navigator.serviceWorker.register('/service-worker.js')
      .then((reg) => {
      }).catch(error => {
        console.error('Service Worker Error', error);
      });
  } else {
    console.warn('Push messaging is not supported');
  }

});

let onScrollActionExecuted = false;

function onScrollAction() {
  if (onScrollActionExecuted) {
    return;
  }
  // 모바일에서는 스와이프 슬라이드 사용, 롤링 배너는 getWinners 후 자동 초기화됨
  if (window.matchMedia('(max-width: 768px)').matches) {
    return;
  }
  // 이미 당첨 목록 로드 후 flow 배너가 초기화된 경우 스킵 (중복 클론 방지)
  if (window.flowBannerInited) {
    return;
  }

  onScrollActionExecuted = true;

  setTimeout(() => {
    const $wrap = $('.flow_banner');
    const $list = $('.flow_banner .list').first();
    if (!$list.length || !$list.children().length || $wrap.find('.list').length > 1) return;
    let listWidth = $list.width();
    if (listWidth <= 0) return;
    const speed = 92;

    let $clone = $list.clone();
    $wrap.append($clone);
    window.flowBannerInited = true;

    const duration = listWidth / speed;
    $wrap.find('.list').css({
      'animation': `${duration}s linear infinite flowRolling`,
      'animation-play-state': 'running'
    });
  }, 1000);
}

$(window).scroll(function() {
  let windowHeight = $(window).height();
  let scrollMiddle = $(window).scrollTop() + windowHeight / 2;
  let targetDivTop = $('.winner_rolling_wrap').offset().top;

  if (scrollMiddle >= targetDivTop) {
    onScrollAction();
    $(window).off('scroll');
  }
});

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

// 긴급 공지 click
$('.main_notice').click(function() {
  document.location.href = $(this).attr('href');
});

// webapp 이벤트 팝업 close
$('#btn_close_eventpopup').click(function() {
  $('.appeventbanner').hide();
})

// 당첨금 안내 팝업
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

    $('.lastdraw').addClass('active');
  }, 'json');
});

// 추첨 결과 방송 보기 팝업
$('.show_video').click(function() {
  let youtube = $(this).data('youtube');
  let draw    = $(this).data('draw');
  let gubun   = $(this).data('gubun');
  youtube     = youtube.split('v=');

  $('#youtube_draw').html(draw + '회차');
  $('#youtube_ifrm').attr('src', 'https://www.youtube.com/embed/' + youtube[1]);
  $('#video_gubun_name').html(gubun);
});

// 추첨 결과 방송 보기 팝업 close
$('.video_close').click(function() {
  $('#youtube_ifrm').attr('src', '');
});

// Naver App으로 접속 시 홈 바로가기 Config
let userAgent = navigator.userAgent.toLowerCase();
let isBacon = window.location.search;
let currentDomain = new URL(document.URL).hostname;
let domain = currentDomain.match(/(?:[^.]+\.)*([^.]+\.[a-z]+)/)[1];

let url = "https://m." + domain + "/index.html?id=bacon";
let icon = "https://m." + domain + "/common/images/cropped-favicon-32x32.png";

let title = "파메코";
let serviceCode = "suloko";

// Naver 바로가기 설치 하루 한번만 팝업 띄우기
if (getCookie('suloko_bacon') != 'Y') {
  setCookie('suloko_bacon', 'Y', 1);
  home_key();
}

// Naver 바로가기 설치
function home_key() {
  let appUrl = "naversearchapp://addshortcut?url=";
      appUrl += encodeURIComponent(url) + "&icon=";
      appUrl += encodeURIComponent(icon) + "&title=";
      appUrl += title + "&serviceCode=" + serviceCode + "&version=7";

  if (userAgent.indexOf('naver') > -1 &&
      userAgent.match('android') &&
      isBacon.length == 0) {
    window.open(appUrl);
  }
}

// 주문 마감시간 countdown 설정
function setCountDown(date) {
  let b = 9;
  let i = date;
  let config = {
    timeText: i,           // 마감 시간
    timeZone: b,           // 현재 시간
    style: 'flip',         // 샘플 종류 flip, slide, metal, crystal
    color: 'black',        // 색상 white, black
    width: 230,            // 넓이
    textGroupSpace: 30,    // 일자, 시, 분, 초 사이의 간격
    textSpace: 0,          // 숫자 간의 간격
    reflection: 0,         // 음영 처리 여부
    reflectionOpacity: 10, // 음영 부분 투명도
    reflectionBlur: 0,     // 음영 부분 흐릿함
    dayTextNumber: 2,      // 마감시간 일자 개수
    displayDay: !0,        // 일자 출력 여부
    displayHour: !0,       // 시간 출력 여부
    displayMinute: !0,     // 분 출력 여부
    displaySecond: !0,     // 초 출력 여부
    displayLabel: !0,      // 하단 label 출력 여부
    onFinish: function() {}
  };
  return config;
}

function main_data() {
  $.post('/other.php', {
    mode: 'mainnew'
  }, function(e) {
    let mm = e.MM;console.log(mm);
    let pb = e.PB;
    let change_location = false;
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

    if (pb.deadline_style == 'block') {
      change_location = true;
    } else if (mm.deadline_style != 'block' && parseInt(mm.priceDollar.replace(/,/gi,"")) > parseInt(pb.priceDollar.replace(/,/gi,""))) {
      change_location = true;
    }

    if (change_location) $('.powerInfo').insertAfter('.megaInfo');

  }, 'json');
}


(function onYouTubeIframeAPIReady() {
  const tag = document.createElement('script');
  tag.src = "https://www.youtube.com/iframe_api";
  const firstScriptTag = document.getElementsByTagName('script')[0];
  firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);

  tag.onload = setupPlayer;
})();

let player;

function setupPlayer() {
  window.YT.ready(function() {
    player = new YT.Player('player', {
      videoId: 'yiiIAGnnnJ4',
      playerVars: {
        'autoplay': 0,       // 자동 재생 여부 설정 (1: 자동, 2: 수동)
        'rel': 0,            // 관련 동영상 표시 여부 설정 (0: 숨김, 1: 표시)
        'modestbranding': 1, // YouTube 로고 크기와 표시 여부 설정 (1: 작은 로고, 2: 기본 로고 크기)
        'playsinline': 1,    // 모바일 장치에서 인라인 재생을 허용할지 여부 설정 (1: 허용, 0: 전체 화면 모드로 열림)
        'controls': 0,       // 재생 컨트롤 표시 여부 설정 (1: 표시, 2: 숨김)
        'color':'white',     // 재생 컨트롤 및 진행바 색상 설정
        'loop': 1,           // 동영상 반복 재생 여부 설정 (1: 반복 재생, 2: 반복하지 않음)
        'mute': 1            // 동영상 음소거 기능 설정 (1: 음소거, 2: 음소거하지 않음)
      },
      events: {
        'onReady': onPlayerReady,
        'onStateChange': onPlayerStateChange
      }
    });
  });
}

function onPlayerReady(event) {
  event.target.playVideo();
  event.target.mute();
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
  const isAndroid = userAgent.indexOf('android') > -1;

  document.querySelectorAll(".modal-main-banner").forEach(modal => {
    const modalId = modal.getAttribute("data-modal-id");
    const isPwaModal = modalId === "pwa-install";
    const closeBtn = modal.querySelector(".close-modal");
    const closeBtnX = modal.querySelector(".close-modal-x");
    const dontShow1Day = modal.querySelector(".dont-show-1-day");
    const dontShow7Days = modal.querySelector(".dont-show-7-days");
    const pwaInstallBtn = modal.querySelector(".pwa-install-btn");
    const pwaSkipBtn = modal.querySelector(".pwa-skip-btn");

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
      // PWA 설치 안내 모달은 안드로이드 + 설치 가능 상태일 때만
      if (isPwaModal) {
        if (!isAndroid) return;
        if (!canShowPwaInstallPrompt) return;
      }

      if (!getCookie(`modalHiddenUntil_${modalId}`)) {
        modal.style.display = "block";
      }
    }

    if (closeBtn) {
      closeBtn.addEventListener("click", () => modal.style.display = "none");
    }
    if (closeBtnX) {
      closeBtnX.addEventListener("click", () => modal.style.display = "none");
    }
    if (dontShow1Day) {
      dontShow1Day.addEventListener("click", () => setModalHidden(1));
    }
    if (dontShow7Days) {
      dontShow7Days.addEventListener("click", () => setModalHidden(7));
    }

    // PWA 설치 모달 전용 버튼 동작
    if (isPwaModal && pwaInstallBtn) {
      pwaInstallBtn.addEventListener("click", async function () {
        if (!deferredPwaPrompt) {
          modal.style.display = "none";
          return;
        }

        deferredPwaPrompt.prompt();
        const choiceResult = await deferredPwaPrompt.userChoice;

        // 설치 여부와 관계없이 모달은 닫고, 당분간 다시 안 뜨게 처리
        if (choiceResult && choiceResult.outcome === 'accepted') {
          setModalHidden(30); // 설치한 경우: 30일간 안 보기
        } else {
          setModalHidden(7);  // 거절한 경우: 7일간 안 보기
        }

        deferredPwaPrompt = null;
      });
    }

    if (isPwaModal && pwaSkipBtn) {
      pwaSkipBtn.addEventListener("click", function () {
        // 웹으로 그냥 보겠다는 의사: 하루 동안만 안 보기
        setModalHidden(1);
      });
    }

    modal.addEventListener("click", (event) => {
      if (event.target === modal) {
        modal.style.display = "none";
      }
    });

    checkModalVisibility();
  });
});

// PWA 설치 가능 시점 감지 (안드로이드 + 아직 설치 안 된 경우)
window.addEventListener('beforeinstallprompt', function (e) {
  const ua = navigator.userAgent.toLowerCase();
  const isAndroid = ua.indexOf('android') > -1;

  if (!isAndroid) {
    return;
  }

  // 기본 브라우저 자동 배너는 막고, 우리가 만든 팝업에서만 제어
  e.preventDefault();
  deferredPwaPrompt = e;
  canShowPwaInstallPrompt = true;

  // DOMContentLoaded 이후에 모달 가시성 체크가 한 번 더 돌도록 트리거
  const pwaModal = document.querySelector('.modal-main-banner[data-modal-id="pwa-install"]');
  if (pwaModal && !pwaModal.style.display) {
    // display 값이 아직 설정되지 않았다면, 강제로 한 번 더 체크
    const event = new Event('DOMContentLoaded');
    document.dispatchEvent(event);
  }
});

