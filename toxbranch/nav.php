<div class="site-nav">
  <!-- <div class="top_img">
    <img src="/img/top1.jpg" alt="">
  </div> -->
  <div class="site-nav-bar">
    <button type="button" class="nav-toggle" aria-expanded="false" aria-controls="site-nav-drawer" aria-label="메뉴 열기">
      <span class="nav-toggle-bar" aria-hidden="true"></span>
      <span class="nav-toggle-bar" aria-hidden="true"></span>
      <span class="nav-toggle-bar" aria-hidden="true"></span>
    </button>
    <div class="img-row nav-desktop">
      <a href="/">INEE PREMIUM CLINIC</a>
      <a href="/promotions.html">MONTHLY PROMOTIONS</a>
      <a href="/package.html">VIP PACKAGE & CONCIERGE</a>
    </div>
  </div>
</div>


<div class="nav-drawer-backdrop" aria-hidden="true"></div>
<aside id="site-nav-drawer" class="nav-drawer" aria-hidden="true" aria-label="메인 메뉴">
  <div class="nav-drawer-header">
    <span class="nav-drawer-title">MENU</span>
    <button type="button" class="nav-drawer-close" aria-label="메뉴 닫기">&times;</button>
  </div>
  <nav class="nav-drawer-inner">
    <a href="/">INEE PREMIUM CLINIC</a>
    <a href="/promotions.html">MONTHLY PROMOTIONS</a>
    <a href="/package.html">VIP PACKAGE & CONCIERGE</a>
  </nav>
  
</aside>
<script>
(function () {
  var toggle = document.querySelector('.nav-toggle');
  var drawer = document.getElementById('site-nav-drawer');
  var backdrop = document.querySelector('.nav-drawer-backdrop');
  var closeBtn = document.querySelector('.nav-drawer-close');
  if (!toggle || !drawer || !backdrop) return;

  function openNav() {
    document.body.classList.add('nav-drawer-open');
    toggle.setAttribute('aria-expanded', 'true');
    drawer.setAttribute('aria-hidden', 'false');
    backdrop.setAttribute('aria-hidden', 'false');
  }

  function closeNav() {
    document.body.classList.remove('nav-drawer-open');
    toggle.setAttribute('aria-expanded', 'false');
    drawer.setAttribute('aria-hidden', 'true');
    backdrop.setAttribute('aria-hidden', 'true');
  }

  toggle.addEventListener('click', function () {
    if (document.body.classList.contains('nav-drawer-open')) closeNav();
    else openNav();
  });
  closeBtn && closeBtn.addEventListener('click', closeNav);
  backdrop.addEventListener('click', closeNav);
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeNav();
  });
  drawer.querySelectorAll('a').forEach(function (a) {
    a.addEventListener('click', closeNav);
  });
})();
</script>
