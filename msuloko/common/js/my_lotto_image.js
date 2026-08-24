$(function() {
  let page = 1;
  list_read(page);

  // 티켓 숨김 처리
  $(document).on('click', '.del_btn', function() {
    let no = $(this).data('no');

    if (no == '') {
      alert('숨김처리 실패하였습니다.');
      return false;
    }

    if (confirm('숨김처리하겠습니까?')) {
      $.post('/contents/other.php', {
        mode: 'my_lotto_hide',
        no: no
      }, function(e) {
        $('#box' + no).remove();
      }, 'json');
    }
  });

  // 더보기
  $(document).on('click', '.type_btn_more', function() {
    page++;
    list_read(page);
  });

  // 조회
  $(document).on('change', '#gubun, #win_yn, #order', function() {
    page = 1;
    $('#list_box').html('');
    $('.type_btn_more').show();
    list_read(page);
  });

  function list_read(page) {
    $.post('./mode.php', {
      mode: 'my_lotto_list_image',
      page: page,
      gubun: $('#gubun').val(),
      win_yn: $('#win_yn').val(),
      order: $('#order').val()
    }, function(e) {
      $('#list_box').append(e.html);

      if (e.html == '') {
        $('.type_btn_more').hide();
      }

      if ($('#list_box').find('tbody').length <= 0 && page == 1) {
        let html = `
          <tbody id='box'>
            <tr>
              <td class='txt_center'>주문하신 내역이 없네요!</td>
            </tr>
          </tbody>`;
        $('#list_box').html(html);
      }
    }, 'json');
  }
});
