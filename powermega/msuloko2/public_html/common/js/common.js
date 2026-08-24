
$(function() {
  // window.addEventListener("contextmenu", e => e.preventDefault());
  // window.addEventListener("selectstart", e => e.preventDefault());
  // window.addEventListener("dragstart", e => e.preventDefault());

  // 메뉴
  const siteMap = $('.sitemap');
  const gnbListItem = $('.gnb_total li > a');

  $('.btn_menu').on('click', function() {
    siteMap.toggleClass('active');
  });

  gnbListItem.on('click', function(e) {
    if ($(this).next().length > 0) {
      e.preventDefault();
      $(this).toggleClass('active');
      $(this).parent().siblings().children('a').removeClass('active')
    }
  });

  // select 페이지 이동
  $('.sel_link').on('change', function() {
    let url = $(this).val();
    location.href = url
  });

  // popup close
  const closePopBtn = document.querySelectorAll('.popup .btn_close');
  for (let i = 0; i < closePopBtn.length; i++) {
    closePopBtn[i].addEventListener('click', function() {
      this.closest('.popup').classList.remove('active');
      $('body').removeAttr('style');
    });
  }

  // popup close - new version
  const newClosePopBtn = document.querySelectorAll('.popup .close');
  for (let i = 0; i < newClosePopBtn.length; i++) {
    newClosePopBtn[i].addEventListener('click', function() {
      this.closest('.popup').classList.remove('active');
      $('body').removeAttr('style');
    });
  }

  // popup open - 팝업띄울 버튼 class에 btn_popup 추가, id에 btn_* 넣기, 띄울 팝업 클래스명에 *추가
  const popupBtn = document.querySelectorAll('.btn_popup');
  for (let i = 0; i < popupBtn.length; i++) {
    popupBtn[i].addEventListener('click', function(e) {
      e.preventDefault();
      let btnName = this.getAttribute('id'); // id값 추출
      btnName = btnName.substr(4, btnName.length); // 재할당
      setTimeout(() => {
        showPopup(btnName);
      }, 100);

    });
  }

  // popup 외부 영역 클릭 시 close
  $(document).mouseup(function (e) {
    if ($('.popup').is(e.target)) {
      $(e.target).removeClass('active');
      $('body').removeAttr('style');
    }
  });

  // popup bottom close button
  $('.btn_close_bottom').click(function() {
    $(this).parents('div').removeClass('active');
    $('body').removeAttr('style');
  });


  $.datepicker.setDefaults({
    dateFormat: 'yy-mm-dd', // Input Display Format 변경
    showOtherMonths: false, // 빈 공간에 현재 월의 앞뒤 월의 날짜를 표시
    showMonthAfterYear: true, // 년도 먼저 나오고, 뒤에 월 표시
    changeYear: false, // 콤보박스에서 년 선택 가능
    changeMonth: false, // 콤보박스에서 월 선택 가능
    showOn: "both", // button: 버튼을 표시하고, 버튼을 눌러야만 달력 표시 ^ both:버튼을 표시하고, 버튼을 누르거나 input을 클릭하면 달력 표시
    buttonImage: '/common/images/calendar.svg', // 버튼 이미지 경로
    buttonImageOnly: true, // 기본 버튼의 회색 부분을 없애고, 이미지만 보이게 함
    buttonText: "선택", // 버튼에 마우스 갖다 댔을 때 표시되는 텍스트
    yearSuffix: "년", // 달력의 년도 부분 뒤에 붙는 텍스트
    monthNamesShort: ['1','2','3','4','5','6','7','8','9','10','11','12'], // 달력의 월 부분 텍스트
    monthNames: ['1월','2월','3월','4월','5월','6월','7월','8월','9월','10월','11월','12월'], // 달력의 월 부분 Tooltip 텍스트
    dayNamesMin: ['일','월','화','수','목','금','토'], // 달력의 요일 부분 텍스트
    dayNames: ['일요일','월요일','화요일','수요일','목요일','금요일','토요일'], // 달력의 요일 부분 Tooltip 텍스트
  });

  if ($('#sdate').length > 0) {
    $('#sdate').datepicker();
  }

  if ($("#edate").length > 0) {
    $("#edate").datepicker();
  }
}); //jquery function end

// mobile alert
window.alert = function(msg, callback) {
  let div = document.createElement('div');

  div.innerHTML = `
    <style type="text/css">
      .nbaMask { position: fixed; z-index: 1000; top: 0; right: 0; left: 0; bottom: 0; background: rgba(0, 0, 0, 0.5); }
      .nbaMaskTransparent { position: fixed; z-index: 1000; top: 0; right: 0; left: 0; bottom: 0; }
      .nbaDialog { position: fixed; z-index: 5000; width: 80%; max-width: 300px; top: 50%; left: 50%; -webkit-transform: translate(-50%, -50%); transform: translate(-50%, -50%); background-color: #fff; text-align: center; border-radius: 8px; overflow: hidden; opacity: 1; color: white; }
      .nbaDialog .nbaDialogHd { padding: .2rem .27rem .08rem .27rem; }
      .nbaDialog .nbaDialogHd .nbaDialogTitle { font-size: 17px; font-weight: 400; }
      .nbaDialog .nbaDialogBd { padding: 1rem .27rem; font-size: 15px; line-height: 1.3; word-wrap: break-word; word-break: break-all; color: #000000; }
      .nbaDialog .nbaDialogFt { position: relative; line-height: 48px; font-size: 17px; display: -webkit-box; display: -webkit-flex; display: flex; }
      .nbaDialog .nbaDialogFt:after { content: " "; position: absolute; left: 0; top: 0; right: 0; height: 1px; border-top: 1px solid #e6e6e6; color: #e6e6e6; -webkit-transform-origin: 0 0; transform-origin: 0 0; -webkit-transform: scaleY(0.5); transform: scaleY(0.5); }
      .nbaDialog .nbaDialogBtn { display: block; -webkit-box-flex: 1; -webkit-flex: 1; flex: 1; color: #09BB07; text-decoration: none; -webkit-tap-highlight-color: transparent; position: relative; margin-bottom: 0; }
      .nbaDialog .nbaDialogBtn:after { content: " "; position: absolute; left: 0; top: 0; width: 1px; bottom: 0; border-left: 1px solid #e6e6e6; color: #e6e6e6; -webkit-transform-origin: 0 0; transform-origin: 0 0; -webkit-transform: scaleX(0.5); transform: scaleX(0.5); }
      .nbaDialog a { text-decoration: none; -webkit-tap-highlight-color: transparent; }
    </style>
    <div id="dialogs" style="display: none;">
      <div class="nbaMask"></div>
      <div class="nbaDialog">
        <div class="nbaDialogHd">
          <strong class="nbaDialogTitle"></strong>
        </div>
        <div class="nbaDialogBd" id="dialog_msg">&nbsp;</div>
        <div class="nbaDialogHd">
          <strong class="nbaDialogTitle"></strong>
        </div>
        <div class="nbaDialogFt">
          <a class="nbaDialogBtn nbaDialogBtnPrimary" id="dialog_ok">확인</a>
        </div>
      </div>
    </div>
  `;
  document.body.appendChild(div);

  let dialogs = document.getElementById('dialogs');
  dialogs.style.display = 'block';

  let dialog_msg = document.getElementById('dialog_msg');
  dialog_msg.innerHTML = msg;

  let dialog_ok = document.getElementById('dialog_ok');
  dialog_ok.onclick = function() {
    dialogs.style.display = 'none';

    if (typeof callback === 'undefined') {
      // nothing
    } else if (callback === 'back') {
      history.back();
    } else {
      location.href = callback;
    }
  };
};

function showPopup(param) {
  const popup = document.querySelector('.popup.' + param);
  popup.classList.add('active');
  $('body').css('overflow', 'hidden');
  $('body').css('height', '100%');
}

function subscribeTokenToTopic(token, topic) {
  fetch('https://iid.googleapis.com/iid/v1/' + token + '/rel/topics/' + topic, {
    method: 'POST',
    headers: new Headers({
      'Authorization': 'key=' + FCM_SERVER_KEY
    })
  }).then(response => {
    if (response.status < 200 || response.status >= 400) {
      throw 'Error subscribing to topic: '+ response.status + ' - ' + response.text();
    }
    // console.log('Subscribed to "' + topic + '"');
  }).catch(error => {
    console.error(error);
  })

  // $.ajax({
  //   type : "POST",
  //   url: "/access_token.php",
  //   async: false,
  //   data: {
  //     token : token,
  //     topic : topic
  //   },
  //   success: function(result) {
  //     var r = result.trim();
  //     //console.log(r);
  //     //alert(r);
  //   },
  //   error:function(e) {
  //     alert(e.responseText);
  //   }
  // });

}

function unsubscribeTokenToTopic(token, topic) {
  fetch('https://iid.googleapis.com/iid/v1/:batchRemove', {
    method: 'POST',
    headers: new Headers({
      'Content-Type': 'application/json',
      'Authorization': 'key=' + FCM_SERVER_KEY
    }),
    body: JSON.stringify({
      'to': '/topics/' + topic,
      'registration_tokens': [token]
    })
  }).then(response => {
    if (response.status < 200 || response.status >= 400) {
      throw 'Error unsubscribing to topic: '+ response.status + ' - ' + response.text();
    }
    // console.log('UnSubscribed to "' + topic + '"');
  }).catch(error => {
    console.error(error);
  })
}

function setTopic(token, topicName, status) {
  if (status === 'Y') {
    if (!topicName) {
      topicName = 'basic';
    }
    // 구독 설정
    subscribeTokenToTopic(token, topicName);
  } else if (status === 'N') {
    // 구독 해제
    unsubscribeTokenToTopic(token, topicName)
  }
}

function sendTokenToServer(token) {
  $.post('/mode.php', {
    mode: 'sendTokenToServer',
    token: token
  }, function(e) {
    if (e.topics) {
      for (let i = 0; i < e.topics.USER_ID.length; i++) {
        if (e.isNew) {
          setTopic(token, null, 'Y');
        } else if (e.topics.STATUS[i] === 'Y') {
          // fcm에 기존 구독 재설정
          setTopic(token, e.topics.TOPIC_NAME[i], 'Y');
        }
      }
    }
  }, 'json');
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

// 휴대폰 번호 하이픈 자동 입력
function autoHypenTel(phoneNum) {
  phoneNum = phoneNum.replace(/[^0-9]/g, '');
  let result = [];
  let restNumber = '';

  result.push(phoneNum.substr(0, 3));
  restNumber = phoneNum.substring(3);

  if (restNumber.length === 7) {
    result.push(restNumber.substring(0, 3));
    result.push(restNumber.substring(3));
  } else {
    result.push(restNumber.substring(0, 4));
    result.push(restNumber.substring(4));
  }

  return result.filter((val) => val).join('-');
}
