<style>
  .youtube_title{font-size: 22px; margin-bottom: 10px; font-weight: bold;}
  .youtube_title span {color:#537db2;}
  .draw_modal {
    visibility: hidden;
    opacity: 0;
    position: fixed;
    inset: 0;
    z-index: 10000;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 20px;
    transition: opacity .2s;
    pointer-events: none;
  }
  .draw_modal.is-open {
    visibility: visible;
    opacity: 1;
    pointer-events: auto;
  }
  .draw_modal_bg {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, .7);
    cursor: pointer;
  }
  .draw_modal_box {
    position: relative;
    z-index: 1;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    padding: 40px;
    border-radius: 20px;
    background: #fff;
  }
  .draw_modal_box.size-md { max-width: 720px; }
  .draw_modal_box.size-lg { max-width: 850px; }
  .draw_modal_close {
    position: absolute;
    top: 27px;
    right: 26px;
    width: 40px;
    height: 40px;
    display: flex;
    justify-content: center;
    align-items: center;
    border: none;
    border-radius: 50%;
    background: transparent;
    cursor: pointer;
    transition: transform .2s;
  }
  .draw_modal_close:hover { transform: scale(.95) rotate(180deg); }
  .draw_modal_close i { font-size: 22px; color: #000; }
  @media (max-width: 768px) {
    .draw_modal_box { padding: 30px 20px; }
    .draw_modal_close { top: 15px; right: 15px; }
  }
</style>

<div class="draw_modal" id="youtube_layer">
  <div class="draw_modal_bg" data-close="youtube_layer"></div>
  <div class="draw_modal_box size-md">
    <button type="button" class="draw_modal_close" data-close="youtube_layer"><i class="xi-close"></i></button>
    <div class="youtube_title"></div>
    <iframe style="width:100%;" height="315" id="youtube_frame" src="" title="YouTube video player" frameborder="0"
      allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
      referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
  </div>
</div>

<div class="draw_modal" id="result_layer">
  <div class="draw_modal_bg" data-close="result_layer"></div>
  <div class="draw_modal_box size-lg">
    <button type="button" class="draw_modal_close" data-close="result_layer"><i class="xi-close"></i></button>
    <div class="result_content"></div>
  </div>
</div>

<script>
  function openDrawModal(id) {
    document.getElementById(id).classList.add('is-open');
    document.body.style.overflow = 'hidden';
  }

  function closeDrawModal(id) {
    var el = document.getElementById(id);
    if (!el) return;
    el.classList.remove('is-open');
    if (!document.querySelector('.draw_modal.is-open')) {
      document.body.style.overflow = '';
    }
    if (id === 'youtube_layer') {
      document.getElementById('youtube_frame').setAttribute('src', '');
    }
  }

  document.addEventListener('click', function(e) {
    var closeEl = e.target.closest('[data-close]');
    if (closeEl) {
      closeDrawModal(closeEl.getAttribute('data-close'));
    }
  });

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      ['youtube_layer', 'result_layer'].forEach(function(id) {
        closeDrawModal(id);
      });
    }
  });

  function go_youtube(title, url) {
    var videoId = url.replace('https://youtu.be/', '').replace('https://www.youtube.com/watch?v=', '').split('&')[0];
    document.querySelector('#youtube_layer .youtube_title').innerHTML = title;
    document.getElementById('youtube_frame').setAttribute('src', 'https://www.youtube.com/embed/' + videoId);
    openDrawModal('youtube_layer');
  }

  function go_result(game, round) {
    $.getJSON('/buy/ajax_prize_result.php', { game: game, round: round }, function(data) {
      if (data.error) {
        alert('당첨 결과를 불러올 수 없습니다.');
        return;
      }
      if (data.alert) {
        alert(data.alert);
      }
      document.querySelector('#result_layer .result_content').innerHTML = data.html;
      openDrawModal('result_layer');
    });
  }
</script>
