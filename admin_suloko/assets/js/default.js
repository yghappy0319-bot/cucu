function onlyNum (str) {
  str.value = str.value.replace(/\D/g, '');
}

// 휴대폰 하이픈 자동입력
function setHypenPhone(obj) {
  let phoneNum = obj.value;
  phoneNum = phoneNum.replace(/[^0-9]/g, '');

  let result = [];
  let restNumber = '';

  result.push(phoneNum.substr(0, 3));
  restNumber = phoneNum.substring(3);

  if (restNumber.length === 7) {
    result.push(restNumber.substring(0, 3));
    result.push(restNumber.substring(3));
  } else {
    result.push(restNumber.substring(0, 4));
    result.push(restNumber.substring(4));
  }
  obj.value = result.filter((val) => val).join('-');
}

function setComma(obj) {
  obj.value = obj.value.replace(/[^\d*\-]/g, '');
  let str = '' + obj.value.replace(/,/gi, ''); // 콤마 제거
  let regx = new RegExp(/(-?\d+)(\d{3})/);
  let bExists = str.indexOf(".",0);
  let strArr = str.split('.');
  while(regx.test(strArr[0])) {
    strArr[0] = strArr[0].replace(regx, "$1,$2");
  }
  if (bExists > -1) {
    obj.value = strArr[0] + "." + strArr[1];
  } else {
    obj.value = strArr[0];
  }
}

function autoSubmit(str) {
  let result = false;

  if (typeof str != 'undefined') {
    result = str.indexOf('auto-submit') > -1;
  }

  if (result) {
    $('#srForm').submit();
  }
}

$('#chkAll').click(function() {
  let type = $(this).prop('checked');
  $('#table-list').find('[name="noChk[]"]').prop('checked', type);
});

$('#btn-search').click(function() {
  $('#srForm').submit();
});


$('input').keyup(function(key) {
  if (key.keyCode == 13) {
    let str = $(this).attr('class');
    autoSubmit(str);
  }
});

// 날짜조회 - 시작일
$('#sdate').change(function() {
  let eDate = $('#edate').val();
  if (eDate) {
    $('#srForm').submit();
  }
});

// 날짜조회 - 종료일
$('#edate').change(function() {
  let sDate = $('#sdate').val();
  if (sDate) {
    $('#srForm').submit();
  }
});

// 추첨일
$('#pdate, #exclude_regdate, #exclude_lastdate').change(function() {
  $('#srForm').submit();
});

function setDataTable(num) {
  let table = $('#table-list').DataTable({
    lengthChange: false,
    searching: false,
    ordering: true,
    info: false,
    paging: false,
    autoWidth: false,
    order: [[ num, 'desc' ]],
    language: {
      emptyTable: '내역이 없습니다.'
    }
  });
  return table;
}

function setTableOrder(dataTable) {
  // 페이지 로딩 시 URL 파라미터 읽어와서 정렬 설정
  let urlParams = new URLSearchParams(window.location.search);
  let orderIdx = urlParams.get('orderIdx');
  let orderDir = urlParams.get('orderDir');

  if (orderIdx) {
    dataTable.order([[orderIdx, orderDir]]).draw();
  }

  // 정렬 변경 시 로컬 스토리지에 저장
  dataTable.on('order.dt', function() {
    localStorage.setItem('datatableSort', JSON.stringify(dataTable.order()));
  });

  // 페이지 로드 시 저장된 정렬 상태 복원
  let savedSort = localStorage.getItem('datatableSort');
  if (savedSort) {
    let order = JSON.parse(savedSort);
    dataTable.order(order).draw();
  }

  // 이전에 저장한 정렬 상태 제거
  localStorage.removeItem('datatableSort');
}

function setReloadToOrder(orderIdx, orderDir) {
  let url = new URL(window.location.href);
  url.searchParams.set('orderIdx', orderIdx);
  url.searchParams.set('orderDir', orderDir);

  window.location.href = url.pathname + url.search;
}

function setReloadToPagination(currentOrder, href) {
  let columnSort = currentOrder[0][0];
  let columnDir = currentOrder[0][1];

  let additionalText = `&orderIdx=${columnSort}&orderDir=${columnDir}`;

  let newHref = href + additionalText;
  // 수정된 href 속성 값으로 리다이렉트
  window.location.href = newHref;
}

$(function() {
  $('#sdate').attr('autocomplete', 'off');
  $('#edate').attr('autocomplete', 'off');
  $('#pdate').attr('autocomplete', 'off');
  $('#play_date').attr('autocomplete', 'off');

  $('#schdate').change(function(event) {
    let schdate = $(this).val();

    if (!schdate) {
      $('#sdate').val('');
      $('#edate').val('');
      $('#srForm').submit();
    } else {
      $.post('/default.php', {
        mode: 'search_date',
        schdate: schdate
      }, function(e) {
        if (e.errNo > 0) {
        } else {
          $('#sdate').val(e.sdate);
          $('#edate').val(e.edate);
          $('#srForm').submit();
        }
      }, 'json');
    }

  });

  $('select').change(function(e) {
    let str = $(this).attr('class');
    autoSubmit(str);
  })

  // Datepicker
  $('.fc-datepicker').datepicker({
    showOtherMonths: true,
    selectOtherMonths: true
  });
});
