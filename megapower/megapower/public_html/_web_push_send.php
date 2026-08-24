<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Web Push Example</title>
    <!-- Firebase JavaScript SDK -->
    <script src="https://www.gstatic.com/firebasejs/10.4.0/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/10.4.0/firebase-firestore-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/10.4.0/firebase-auth-compat.js"></script>

    <script type="module">
      const firebaseConfig = {
        apiKey: "AIzaSyC-wYvSoTSbxhxYMC42SapSTb1fk1jo5ck",
        authDomain: "luckyball-4188f.firebaseapp.com",
        projectId: "luckyball-4188f",
        storageBucket: "luckyball-4188f.appspot.com",
        messagingSenderId: "140349102341",
        appId: "1:140349102341:web:8566a139ea5b06ede1ff6c"
      };

      // Initialize Firebase

        const firebaseApp = firebase.initializeApp(firebaseConfig);
        const db = firebaseApp.firestore();
        const auth = firebaseApp.auth();

    </script>

</head>
<body>

    <script>
          // FCM으로 푸시 알림 보내기
      function sendPushNotification(token, title, body) {
        const notification = {
          title: title,
          body: body,
        };

        const message = {
          token: token,
          notification: {
            title: 'Hello',
            body: 'This is a test notification.'
          }
        };
        // FCM 서버 API 호출
        fetch('https://fcm.googleapis.com/fcm/send', {
          method: 'POST',
          headers: {
            'Authorization': 'key=AAAAIK11WQU:APA91bEt6XdJdxl-PESTbi18IErIaE4nYDuOLizdRgiJ2q_nJRFR99QzSyJGJnXlF1ePKSUhkYnzcbJZBWgToaCr5kYqVcN3--_oj_XD3gGG0_Tf8uiYM0Zhvm3uNAGqMxUJzJKuYV66',
            'Content-Type': 'application/json',
          },
          body: JSON.stringify(message),
        })
        .then((response) => {
          if (!response.ok) {
            throw new Error(`Network response was not ok, status: ${response.status}, ${response.statusText}`);
          }
          return response.json();
        })
        .then((data) => {
          console.log('FCM Response:', data);
        })
        .catch((error) => {
          console.error('Error sending FCM request:', error);
        });
      }

      // 사용 예시
      const userFcmToken = 'ceEwmseY_w0:APA91bFDZR2mFkqQp-D1aY1skaMXTDO_DTqm4tJ7Nssq9Xt0xn_g_p3EJaXMwXwbj-ZDKkqgUOrQZJW9M6EbyaB0RHzMa7XI_QHF4qHljfJRu-yUOkwyjNVW6piDEhssLoVAvweLBNyi';
      sendPushNotification(userFcmToken, 'Hello', 'This is a test notification.');

    </script>
</body>
</html>
