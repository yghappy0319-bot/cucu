self.addEventListener("install", function (e) {
  // console.log("fcm sw install..", e);
  self.skipWaiting();
});

self.addEventListener("activate", function (e) {
  // console.log("fcm sw activate..", e);
});

self.addEventListener("push", function (e) {
  if (!e.data.json()) return;
  const resultData = e.data.json().notification;
  const notificationTitle = resultData.title;
  const notificationOptions = {
    body: resultData.body,
    icon: resultData.icon, // 웹 푸시 이미지는 icon
    image: resultData.image,
    tag: resultData.tag,
    link: resultData.link,
    badge: resultData.badge
  };
  // console.log("push: ", {resultData, notificationTitle, notificationOptions});

  // self.registration.showNotification(notificationTitle, notificationOptions);
  e.waitUntil(self.registration.showNotification(notificationTitle, notificationOptions));
});


self.addEventListener("notificationclick", function (event) {
  event.notification.close();

  event.waitUntil(clients.matchAll({type: 'window'}).then(function(clientList) {
    for (let i = 0; i < clientList.length; i++) {
      let client = clientList[i];
      if (client.url == '/' && 'focus' in client) {
        return client.focus();
      }
    }
    if (clients.openWindow) {
      return clients.openWindow('https://m.sulokolink.com');
    }
  }))
});
