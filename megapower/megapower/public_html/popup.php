<style>
  ._lbtitle {opacity: 0; position: absolute;}
</style>

<? if(!$_COOKIE['popup_main']){ ?>
<div class="new_main_popup_m">
    <div class="banner">
        <div class="rolling">
            <div class="swiper-wrapper">
              <?php
              $currentPath = $_SERVER['PHP_SELF'];
              if($currentPath=="/index.html"){
                $_popup = db_query("select * from lr_popup where popup_yn = 1 ");
                foreach($_popup as $pop){ ?>
                  <div class="swiper-slide"><a href="<?=$pop['popup_url']?>">
                    <img src="https://img.megapower.world/data/<?=$pop['popup_file']?>"></a>
                  </div>
                  <? } ?>
                <? } ?>
            </div>
            <div class="new_main_popup_m_pagination"></div>
        </div>

        <div class="fb">
            <div class="fl"><a href="javascript:go_one_day_close(1)">오늘하루 보지않기</a></div>
            <div class="fr close"><a href="javascript:go_popup_close()">닫기</a></div>
        </div>
    </div>
</div>
<style>
.vertical-text {
  writing-mode: vertical-rl;
  text-orientation: mixed;
}
</style>
<div class="new_main_popup <?=$_COOKIE['main_pop'] == 1 ? "":"on" ?> ">
    <div class="btn">
        <p>POPUP</p>
        <i class="xi-angle-right"></i>
    </div>
    <div class="banner">
      <div class="wrap">
        <div class="rolling">
          <div class="swiper-wrapper">
          <?php
          $currentPath = $_SERVER['PHP_SELF'];
          if($currentPath=="/index.html"){
            $_popup = db_query("select * from lr_popup where popup_yn = 1 ");
            foreach($_popup as $pop){
          ?>
              <div class="swiper-slide">
                <a href="<?=$pop['popup_url']?>" >
                <img src="https://img.megapower.world/data/<?=$pop['popup_file']?>">
                </a>
              </div>
            <? } ?>
          <? } ?>
          </div>
        <div class="banner_btn btn_prev"><i class="xi-angle-left"></i></div>
        <div class="banner_btn btn_next"><i class="xi-angle-right"></i></div>
        </div>
      </div>
    </div>
  <div class="bg"></div>
</div>
<? } ?>

<script>

const popup = document.querySelector('.new_main_popup');
const icon = document.querySelector('.new_main_popup .btn i');

if (popup && popup.classList.contains('on')) {
  console.log('팝업on');
   if (icon) {
     icon.className = 'xi-close vertical-text'; // 기존 클래스 제거 후 xi-close로 설정
     icon.textContent = '팝업닫기'; // 텍스트 설정
   }
} else {
  $('.new_main_popup .btn i').removeClass('vertical-text');
  icon.textContent = ''; // 텍스트 설정
  console.log('팝업off');
}


var login_status = "<?=$member['idx']?>";

$('.new_main_popup .btn').on('click', function() { //버튼을 눌렀을때

  const icon = document.querySelector('.new_main_popup .btn i');
    $('.new_main_popup .btn i').removeClass('vertical-text');
    setCookie("main_pop", 1, 2);


});

  function go_popup_close(){
    $(".new_main_popup_m").css("display","none");
  }
  function go_one_day_close(obj){
    setCookie("popup_main", 1, 1);
    $(".new_main_popup_m").css("display","none");

  }
</script>
