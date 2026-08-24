$(function() {
  // Initialize Firebase
  !firebase.apps.length ? firebase.initializeApp(FIREBASE_CONFIG) : firebase.app();

  try {
    getFirebaseMessaging().then(messaging => {
      return messaging.getToken();
    }).then(token => {
      if (token) {
        // 토큰이 조회되었을 경우
        sendTokenToServer(token);
      }
    }).catch(e => {
      // blocked
    });
  } catch(e) {
    // unsupported-browser
  }

});
