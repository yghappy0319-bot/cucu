async function pushToTopic(topic, subject, content) {
  // 'Authorization': 'key=' + FCM_SERVER_KEY

  if (!topic || !subject || !content) {
    alert('필수 입력 값이 누락되었습니다.');
    return false;
  }

  const iconUrl = 'https://m.surokolink.com/common/images/cropped-favicon-32x32.png';
  const actionUrl = 'https://m.surokolink.com';

  const url = 'https://fcm.googleapis.com/fcm/send';
  const options = {
    method: 'POST',
    headers: new Headers({
      'Authorization': 'key=' + FCM_SERVER_KEY,
      'Content-Type': 'application/json'
    }),
    body: JSON.stringify({
      'notification': {
        'title': subject,
        'body': content,
        'icon': iconUrl,
        'action': actionUrl
      },
      'data': {
        'title': subject,
        'body': content,
        'action': actionUrl
      },
      'priority': 'high',
      'to': '/topics/' + topic,
      'direct_boot_ok': true
    })
  };

  let response;
  try {
    response = await fetch(url, options);
    const data = await response.json();
    alert('알림 발송에 성공했습니다.');
    save_push_log(topic, subject, content, actionUrl, iconUrl, 'Y');
  } catch(error) {
    alert('알림 발송에 "실패"했습니다.');
    is_success = false;
    save_push_log(topic, subject, content, actionUrl, iconUrl, 'N', response.status, error);
  }
  location.reload();
}

function save_push_log() {
  $.post('/default.php', {
    mode: 'save_push_sending_log',
    type: 'M',
    topic: arguments[0],
    subject: arguments[1],
    content: arguments[2],
    actionUrl: arguments[3],
    iconUrl: arguments[4],
    is_success: arguments[5],
    error_code: arguments[6],
    messase: arguments[7]
  }, function(e) {
    if (e.error) {
      // 로그 저장 실패 시 알림
      alert(e)
    }
  });
}
