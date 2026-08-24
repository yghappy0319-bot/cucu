$(document).ready(function() {
  $(document).on('click', '.login_btn', function() {
    $(this).hide();
    $('.loading').show();

    let i = true;
    let rememberme = '';

    $('.check_on').each(function() {
      if ($.trim($(this).val()) == '') {
        alert($(this).attr('alt'));
        $(this).focus();
        i = false;
        return false;
      }
    });

    if (!i) {
      $(this).show();
      $('.loading').hide();
      return false;
    }

    if ($('#rememberme').is(':checked')) {
      rememberme = 'Y';
    }

    $.post('/login.php', {
      user_id: $('#user_id').val(),
      passwd: $('#user_pwd').val(),
      rememberme: rememberme,
    }, function(e) {
      if (!e.error) {
        let userAgent = navigator.userAgent.toLowerCase();
        let hostname = $(location).attr('hostname');

        // 안드로이드 기기에서 접속 시
        if (userAgent.indexOf('android') > -1) {
          if (userAgent.indexOf('naver') > -1) { // Naver 앱으로 접속 시
            return_page(e);
          } else if (hostname.indexOf('sulotko.com') > -1) { // 파메코 앱으로 접속 시
            return_page(e);
          } else {
            // 브라우저 앱으로 접속
            confirmNotification(e);
          }
        // 안드로이드 이외의 기기에서 접속 시
        } else {
          return_page(e);
        }
      } else {
        $('.login_btn').show();
        $('.loading').hide();
        alert(e.msg);
      }
    }, 'json');

    // 알림 허용 여부 확인
    function confirmNotification(e) {
      // Initialize Firebase
      !firebase.apps.length ? firebase.initializeApp(FIREBASE_CONFIG) : firebase.app();

      // messaging
      try {
        const messaging = firebase.messaging();

        messaging.requestPermission().then(() => {
          return messaging.getToken();
        }).then((token) => {
          // 알림 동의 또는 Token 값이 존재
          sendTokenToServer(token);
          setTimeout(function() {
            return_page(e);
          }, 500);
        }).catch((err) => {
          return_page(e);
        })
      } catch(error) {
        return_page(e);
      }
    }

    function return_page(e) {
      let url = document.referrer;
      if (url) {
        location.href = url + e.qs;
      } else {
        location.href = '/' + e.qs;
      }
    }
  });
});
