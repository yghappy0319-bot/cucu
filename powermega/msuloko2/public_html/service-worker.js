self.addEventListener('install', (e) => {
  // console.log('Service worker Installing!');
});

// service worker가 activate 되었을 때 이벤트 발생
self.addEventListener('activate', (e) => {
  // console.log('Service worker Activate!');
  return self.clients.claim();
});

// fetch event listener
self.addEventListener('fetch', (e) => {
  // console.log('Fetching somthing!', e.request.url);
  e.respondWith(fetch(e.request));
});
