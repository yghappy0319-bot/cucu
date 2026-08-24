$(function() {
  let memberNo = $('input[name=MEMBER_NO]').val();
  let userAgent = navigator.userAgent.toLowerCase();
  let hostname = $(location).attr('hostname');
  let memberToken;
  let topicIdx;
  let topicName;

  // 알림 설정 페이지 예외처리
  if (userAgent.indexOf('naver') > -1) {
    alert('알림 서비스는 네이버앱을 지원하지 않습니다. 크롬앱에서 접속해주세요.', 'back');
  } else if (userAgent.indexOf('android') == -1) {
    alert('현재는 안드로이드 기기에서만 지원되는 기능입니다.', 'back');
  } else if (hostname.indexOf('sulotko.com') > -1) {
    alert('파메코2.0에서만 지원되는 기능입니다.', 'back');
  }

  // Initialize Firebase
  !firebase.apps.length ? firebase.initializeApp(FIREBASE_CONFIG) : firebase.app();

  // messaging
  const messaging = firebase.messaging();

  messaging.getToken().then(token => {
    if (token) {
      // 토큰이 조회되었을 경우
      memberToken = token;
      sendTokenToServer(token);
    }
  }).catch(e => {
    alert('지원되지 않는 환경입니다.</br>앱 설치 안내를 참고해 주세요.', '/webapp_guide.html');
  });

  // 서비스 알림 컨트롤
  $('.topic-basic').on('click', function() {
    if ($(this).is(':checked')) {
      topicIdx = $(this).prevAll('input[name=TOPIC_IDX]').val();
      topicName = $(this).prevAll('input[name=TOPIC_NAME]').val();

      // 서비스 알림 동의
      sendTopicToServer(topicIdx, topicName, 'Y');
    } else {
      alert("기본 알림은 차단할 수 없습니다.");
      $(this).prop('checked', true);
    }
  });

  // 서비스 알림 하위 메뉴 컨트롤
  $('.topic-item').change(function() {
    topicIdx = $(this).prevAll('input[name=TOPIC_IDX]').val();
    topicName = $(this).prevAll('input[name=TOPIC_NAME]').val();

    if ($(this).is(':checked')) {
      // 알림 등록 또는 허용
      sendTopicToServer(topicIdx, topicName, 'Y');
    } else {
      // 알림 차단
      sendTopicToServer(topicIdx, topicName, 'N');
    }
  });

  // 마케팅 동의
  $('.is-marketing').change(function() {
    if ($(this).is(':checked')) {
      updateMarketing(memberNo, 'Y');
    } else {
      updateMarketing(memberNo, 'N');
    }
    showAlertWindow();
  });

  // 알림 등록 또는 수정
  function sendTopicToServer(topicIdx, topicName, status) {
    if (!topicIdx) {
      showAlertWindow('알림 설정에 실패했습니다.');
      return false;
    }

    $.post('/mode.php', {
      mode: 'sendTopicToServer',
      topicIdx: topicIdx,
      status: status
    }, function(e) {
      if (e.error) {
        showAlertWindow(e.msg);
      } else {
        setTopic(memberToken, topicName, status);
        showAlertWindow();
      }
    }, 'json')
  }

  // 마케팅 동의 수정
  function updateMarketing(memberNo, status) {
    $.post('/mode.php', {
      mode: 'updateMarketing',
      MEMBER_NO: memberNo,
      IS_MARKETING: status
    }, function(e) {
      if (e.error) {
        alert(e.msg);
      }
    }, 'json');
  }

  function showAlertWindow(message) {
    if (!message) {
      message = '설정이 변경되었습니다.';
    }

    setTimeout(function() {
      alert(message);
    }, 300);
  }
});
