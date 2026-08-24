<aside class="quickArea" aria-label="사이드퀵(오른쪽)" style="bottom: 6.4rem;">
  <div class="quickMenu">
    <ul id="quick-fluid-list" class="List fluidList" aria-label="접히는영역">
          <!-- 브랜드  -->
    <!-- <li class="link-kakao">
      <a class="link" href="https://www.facebook.com/ineeclinicseoul" target="_blank" >
        <div class="iconBox"><img src="/img/rbtn4.png" alt="facebook" style="width: 24px;"></div>
        <div class="lable">facebook</div>
      </a>
    </li> -->

        <li class="link-instagram">
      <a class="link" href="https://www.instagram.com/toxnfill_sinnonhyeon" target="_blank" >
        <div class="iconBox"><img src="/img/rbtn2.png" alt="instagram" style="width: 24px;" ></div>
        <div class="lable">instagram</div>
      </a>
    </li>

        <li class="link-kakao">
      <a class="link" href="https://maps.app.goo.gl/7NLGbKXNb2Uie8eP7" target="_blank" >
        <div class="iconBox"><img src="/img/rbtn1.png" alt="Google" style="width: 24px;" ></div>
        <div class="lable">Google</div>
      </a>
    </li>

  <li class="link-kakao">
      <a class="link" href="https://api.whatsapp.com/send/?phone=821049414842" target="_blank" >
        <div class="iconBox"><img src="/img/rbtn3.png" alt="whats app" style="width: 24px;" ></div>
        <div class="lable">whats app</div>
      </a>
    </li>


    </ul>
    <ul class="List staticList" aria-label="기본보기">
      <li class="link-toggle">
        <button type="button" class="link" aria-label="사이드 메뉴 접기" aria-expanded="true" aria-controls="quick-fluid-list">
          <div class="iconBox"><img src="https://www.doctors365.co.kr/assets/images/icon-x-close.svg" alt=""></div>
        </button>
      </li>
      <li class="link-top" aria-label="최상단으로">
        <a class="link " href="#">
          <div class="iconBox"><img src="https://www.doctors365.co.kr/assets/images/icon-chervon-up.svg" alt="chervon-up"></div>
        </a>
      </li>
    </ul>
  </div>
</aside>
<script>
(function () {
  var menu = document.querySelector('.quickMenu');
  var toggle = document.querySelector('.quickMenu .link-toggle .link');
  var fluid = document.getElementById('quick-fluid-list');
  if (!menu || !toggle || !fluid) return;

  toggle.addEventListener('click', function () {
    var collapsed = menu.classList.toggle('is-collapsed');
    toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    toggle.setAttribute('aria-label', collapsed ? '사이드 메뉴 펼치기' : '사이드 메뉴 접기');
    fluid.setAttribute('aria-hidden', collapsed ? 'true' : 'false');
  });
})();
</script>