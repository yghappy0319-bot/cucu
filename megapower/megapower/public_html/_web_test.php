<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Web Push Example</title>

    <script type="module">
      // Import the functions you need from the SDKs you need
      import { initializeApp } from "https://www.gstatic.com/firebasejs/10.7.2/firebase-app.js";
      // TODO: Add SDKs for Firebase products that you want to use
      // https://firebase.google.com/docs/web/setup#available-libraries

      // Your web app's Firebase configuration
      const firebaseConfig = {
        apiKey: "AIzaSyC-wYvSoTSbxhxYMC42SapSTb1fk1jo5ck",
        authDomain: "luckyball-4188f.firebaseapp.com",
        projectId: "luckyball-4188f",
        storageBucket: "luckyball-4188f.appspot.com",
        messagingSenderId: "140349102341",
        appId: "1:140349102341:web:8566a139ea5b06ede1ff6c"
      };

      // Initialize Firebase
      const app = initializeApp(firebaseConfig);
    </script>

</head>
<body>
    <h1>Web Push Example</h1>
    <button onclick="subscribeToPush()">Subscribe to Push Notifications</button>

    <script>
        // 서비스 워커 등록
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('service-worker.js')
                .then(registration => {
                    console.log('Service Worker registered with scope:', registration.scope);
                })
                .catch(error => {
                    console.error('Service Worker registration failed:', error);
                });
        }

        // 푸시 알림 구독
        function subscribeToPush() {
            Notification.requestPermission().then(permission => {
                if (permission === 'granted') {
                    return navigator.serviceWorker.ready;
                }
            }).then(registration => {
                return registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array('BOPOP65OUKzTkwBlh01JtgU7KtOwa1Z3aazd-PUMTaX1oKYEexg6x8oVXExhERcZrRGLBj_xA_eknGHmjVl7TVY')
                });
            }).then(subscription => {
                console.log('Subscribed to Push:', JSON.stringify(subscription));
            }).catch(error => {
                console.error('Error subscribing to Push:', error);
            });
        }

        // Base64를 Uint8Array로 변환
        function urlBase64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - base64String.length % 4) % 4);
            const base64 = (base64String + padding)
                .replace(/-/g, '+')
                .replace(/_/g, '/');

            const rawData = window.atob(base64);
            const outputArray = new Uint8Array(rawData.length);

            for (let i = 0; i < rawData.length; ++i) {
                outputArray[i] = rawData.charCodeAt(i);
            }

            return outputArray;
        }
    </script>
</body>
</html>
