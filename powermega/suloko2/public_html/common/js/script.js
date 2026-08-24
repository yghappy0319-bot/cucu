'use strict';

$(function() {
  // window.addEventListener("contextmenu", e => e.preventDefault());
  // window.addEventListener("selectstart", e => e.preventDefault());
  // window.addEventListener("dragstart", e => e.preventDefault());


  $('input[type="file"]').on('change', function() {
    let fileName = $(this).val();
    $(this).next('.fileName').val(fileName)
  });

  $(document).on('click', '.add_before', function() {
    if ($('#main_popup').prop('checked')) {
      $('#main_popup').prop('checked', false);
    } else {
      $('#main_popup').prop('checked', true);
    }
  });
});

const noLink = document.querySelectorAll('.nolink');
for (let i = 0; i < noLink.length; i++) {
  noLink[i].addEventListener('click', function(e) {
    e.preventDefault()
  });
}

// open popup - 팝업 띄울 버튼 class에 btn_popup 추가, id 값에 btn_* 넣기, 띄울 팝업 클래스명에 *추가
const popupBtn = document.querySelectorAll('.btn_popup');
for (let i = 0; i < popupBtn.length; i++) {
  popupBtn[i].addEventListener('click', function(e) {
    e.preventDefault();
    let btnName = this.getAttribute('id'); // id값 추출
    btnName = btnName.substr(4, btnName.length); // 재할당
    $('body').css('overflow', 'hidden');
    $('body').css('height', '100%');
    showPopup(btnName);
  });
}

// close popup
const closePopBtn = document.querySelectorAll('.popup .btn_close');
for (let i = 0; i < closePopBtn.length; i++) {
  closePopBtn[i].addEventListener('click', function(event) {
    try {
      this.closest('.popup').classList.remove('active');
      $('#youtube_ifrm').attr('src', '');
      $('#hot_youtube_ifrm').attr('src', '');

      $('body').removeAttr('style');
    } catch(e) {
      alert('지원하지 않는 기능입니다. 새로고침(F5) 후 이용해 주세요');
    }
  });
}

// popup 외부 영역 클릭 시 close
$(document).mouseup(function(e) {
  if ($('.popup').is(e.target)) {
    $(e.target).removeClass('active');
    $('body').removeAttr('style');
  }
});

// 오늘 하루 열지 않기
$('#top_banner_nottoday').click(function(e) {
  e.stopPropagation();
  $('.banner').hide();
  setCookie('c_banner', 'banner_nottoday', 1);
});

$('#top_banner_close').click(function(e) {
  e.stopPropagation();
  $('.banner').hide();
});

$('.btn_top').click(function() {
  $('html, body').animate({
    scrollTop: 0
  }, 1000);
});


$.datepicker.setDefaults({
  dateFormat: 'yy-mm-dd', // Input Display Format 변경
  showMonthAfterYear: true, // 년도 먼저 나오고, 뒤에 월 표시
  showOn: "both", // button: 버튼을 표시하고, 버튼을 눌러야만 달력 표시 ^ both:버튼을 표시하고, 버튼을 누르거나 input을 클릭하면 달력 표시
  buttonImage: '/common/images/calendar.svg', // 버튼 이미지 경로
  buttonImageOnly: true, // 기본 버튼의 회색 부분을 없애고, 이미지만 보이게 함
  buttonText: "선택", // 버튼에 마우스 갖다 댔을 때 표시되는 텍스트
  monthNamesShort: ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'], // 달력의 월 부분 텍스트
  monthNames: ['1월', '2월', '3월', '4월', '5월', '6월', '7월', '8월', '9월', '10월', '11월', '12월'], // 달력의 월 부분 Tooltip 텍스트
  dayNamesMin: ['일', '월', '화', '수', '목', '금', '토'], // 달력의 요일 부분 텍스트
  dayNames: ['일요일', '월요일', '화요일', '수요일', '목요일', '금요일', '토요일'], // 달력의 요일 부분 Tooltip 텍스트
});


if ($('#sdate').length > 0) {
  $('#sdate').datepicker();
}

if ($('#edate').length > 0) {
  $('#edate').datepicker();
}

function showPopup(param) {
  const popup = document.querySelector('.popup.' + param);
  if (param === 'login') {
    setBannerImg();
  }
  popup.classList.add('active');
}

function setCookie(cName, cValue, cDay) {
  let now = new Date();
  now.setTime(now.getTime() + cDay * 3600 * 1000 * 24);
  let cookies = cName + '=' + escape(cValue) + '; path=/; expires=' + now + ';';
  document.cookie = cookies;
}

function getCookie(name) {
  let value = document.cookie.match('(^|;) ?' + name + '=([^;]*)(;|$)');
  return value ? value[2] : null;
}

function top_banner_click(url) {
  setTimeout(function() {
    let isCookie = getCookie('c_banner');
    if (!isCookie) {
      location.href = url;
    }
  }, 300)
}

// 로그인 랜덤 이미지 변경
function setBannerImg() {
  $.post('/other.php', {
    mode: 'getLoginImgCnt'
  }, function(e) {
    const imgCount = e.fileCount;
    // let randomImg = Math.floor(Math.random() * imgCount) + 1;
    let randomImg = 3;
    let src = `/images/web/login-banner${randomImg}.png`;
    $('#loginImg').attr('src', src);
  }, 'json');
}
