$(function() {
  let page = 1;
  list_read(page);

  $('.tab-menu li').click(function() {
    let tabNumber = $(this).data('tab');
    $('.tab-menu li').removeClass('on');
    $('.tab-cont').removeClass('on');
    $(this).addClass('on');
    $('.tab-cont[data-tab="' + tabNumber + '"]').addClass('on');
  });

  $('.type_btn_more').click(function() {
    page++;
    list_read(page);
  });

  $(document).on('click', '.del_btn', function() {
    let no = $(this).data('no');

    if (no == '') {
      alert('구매내역이 선택되지 않았습니다.');
      return false;
    }

    if (confirm('주문건 취소 및 환불이 아닙니다. \n구매내역을 숨김 처리하시겠습니까?')) {
      $.post('./mode.php', {
        mode: 'my_lotto_hide',
        no: no
      }, function(e) {
        if (e.error) {
          alert(e.msg);
        } else {
          $('#box' + no).remove();
        }
      }, 'json');
    }
  });

  $(document).on('click', '.restore_btn', function() {
    let no = $(this).data('no');

    if (no == '') {
      alert('구매내역이 선택되지 않았습니다.');
      return false;
    }

    if (confirm('숨김내역을 복구하시겠습니까?')) {
      $.post('./mode.php', {
        mode: 'my_lotto_restore',
        no: no
      }, function(e) {
        if (e.error) {
          alert(e.msg);
        } else {
          $('#box' + no).remove();
        }
      }, 'json');
    }
  });

  $(document).on('click', '.search_btn', function() {
    page = 1;
    list_read(page);
  });

  $(document).on('click', '.winchk', function() {
    page = 1;
    $('.winchk_check').find('li').removeClass('on');
    $(this).parent().addClass('on');

    list_read(page);
  });

  $(document).on('click', '.gubun', function() {
    page = 1;
    $('#list_box').html('');
    $('.type_btn_more').show();
    $('.gubun_check').find('li').removeClass('on');
    $('.winchk_check').find('li').removeClass('on');
    $(this).parent().addClass('on');
    $('#sdate').val('');
    $('#edate').val('');

    list_read(page);
  });

  $(document).on('change', '#order', function() {
    page = 1;

    $('#list_box').html('');
    $('.type_btn_more').show();

    list_read(page);
  });

  $(document).on('click', '.scan_read', function() {
    let url = $(this).data('url');
    let no = $(this).data('no');

    let fileInfo = parsePath(url);
    let path = fileInfo.path;
    let backImg = "";

    if (fileInfo.filename.slice(-1) == "a") {
      backImg = `${fileInfo.filename.slice(0,-1)}b.${fileInfo.extension}`;
    } else {
      let backIntName = BigInt(fileInfo.filename) + 1n;
      let backName = backIntName.toString();
      backImg = `${BigInt(backName.replace('n', ''))}.${fileInfo.extension}`;
    }
    let ticketBackUrl = `${path}/back/${backImg}`;

    isImageURLValid(ticketBackUrl).then(isValid => {
      if (isValid) {
        $('.scan').find('.back').attr('src', ticketBackUrl);
        $('.scan .tab-menu').removeAttr('style');
      } else {
        $('.scan').find('.back').attr('src', '');
        $('.scan .tab-menu').css('display', 'none');
      }
    })

    // Set tab
    $('.tab-menu li').removeClass('on');
    $('.tab-cont').removeClass('on');
    $('.tab-menu li:first').addClass('on');
    $('.tab-cont:first').addClass('on');

    $('.scan').find('.front').attr('src', url);

    // 스캔본 팝업 하단에 구매·당첨내역 정보 표시
    let $historyRows = $('#box' + no).find('tr').clone();
    $historyRows = $historyRows.filter(function() {
      let th = $(this).find('th').text().trim();
      // 스캔본 확인 행·숨김 버튼 행은 팝업에서 제외
      return th !== '복권스캔본' && th !== '구매 숨김' && th !== '숨김 복구';
    });
    $('.popup.scan .scan-history-body').html($historyRows);
    $('.popup.scan .cont').scrollTop(0);

    // show popup
    let btnName = $(this).attr('id');
    btnName = btnName.substr(4, btnName.length);
    showPopup(btnName);
  });

  $(document).on('click', '.date_gubun', function() {
    page = 1;

    $('.datechk_check').find('li').removeClass('on');
    $(this).parent().addClass('on');

    $.post('/other.php', {
      mode: 'search_date',
      schdate: $(this).data('tp')
    }, function(e) {
      if (e.errNo > 0) {
      } else {
        $('#sdate').val(e.sdate);
        $('#edate').val(e.edate);
        list_read(page);
      }
    }, 'json');
  });

  $('.btn_hide').click(function() {
    page = 1;
    $('#hide').val($(this).val());
    $('.hide_title').show();
    $('.order_title').hide();
    $('.btn_order').show();
    $('.btn_hide').hide();
    list_read(page);
  });

  $('.btn_order').click(function() {
    page = 1;
    $('#hide').val($(this).val());
    $('.hide_title').hide();
    $('.order_title').show();
    $('.btn_order').hide();
    $('.btn_hide').show();
    list_read(page);
  });
});

// 파일 정보 추출
function parsePath(path) {
  let filename = path.split('/').pop();
  let extension = filename.split('.').pop();

  let pathLastIndex = path.lastIndexOf('/');
  if (pathLastIndex !== -1) {
    path = path.substring(0, pathLastIndex);
  }

  let fileLastIndex = filename.lastIndexOf('.');
  if (fileLastIndex !== -1) {
    filename = filename.substring(0, fileLastIndex);
  }

  return {
    path: path,
    filename: filename,
    extension: extension
  };
}

// check image
function isImageURLValid(url) {
  return new Promise(resolve => {
    const img = new Image();
    img.onload = () => resolve(true);
    img.onerror = () => resolve(false);
    img.src = url;
  });
}

function list_read(page) {
  $.post('./mode.php', {
    mode: 'my_lotto_list',
    page: page,
    gubun: $('.gubun_check').find('.on').find('a').data('tp'),
    win_yn: $('.winchk_check').find('.on').find('a').data('tp'),
    hide_yn: $('#hide').val(),
    sdate: $('#sdate').val(),
    edate: $('#edate').val(),
    order: $('#order').val()
  }, function(e) {
    if (page > 1) {
      $('#list_box').append(e.html);
    } else {
      $('#list_box').html(e.html);
    }

    if (e.html == '') {
      $('.type_btn_more').hide();
    }

    if ($('#list_box').find('tbody').length <= 0 && page == 1) {
      let html = `
        <tbody id="box">
          <tr>
            <td class="txt_center">내역이 없습니다.</td>
          </tr>
        </tbody>`;

      $('#list_box').html(html);
    }
  }, 'json');
}
