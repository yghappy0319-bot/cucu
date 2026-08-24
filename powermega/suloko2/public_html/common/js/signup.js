$(document).ready(function() {
  // 휴대폰 인증 여부
  let auth_phone = false;

  // 국적 선택
  $('.country').click(function() {
    $('.country').parent().removeClass('on');
    $('.country').removeClass('button_on');
    $(this).parent().addClass('on');
    $(this).addClass('button_on');
    $('#reg_country').val($(this).data('value'));
    $('#reg_name').val('');
    $('#reg_name').focus();
  })

  // 성별 선택
  $('.gender').click(function() {
    $('.gender').parent().removeClass('on');
    $('.gender').removeClass('button_on');
    $(this).parent().addClass('on');
    $(this).addClass('button_on');
    $('#reg_gender').val($(this).data('value'));
  })

  // 이름 입력값 검증
  $('#reg_name').keyup(function() {
    let country = $('#reg_country').val();
    let name    = $('#reg_name').val();

    if (country == '') {
      alert('국적을 먼저 선택해 주세요.');
      $('#reg_name').val('');
      return false;
    }

    if (country == 'kr') {
      $(this).val(name.replace(koreanRegexp_check, ''));
    } else {
      $(this).val(name.replace(englishRegexp_check, ''));
    }
  });

  // 약관 전체 동의
  $('#chk_all').click(function() {
    let tp = $(this).prop('checked');
    $('.agree').find('input[type="checkbox"]').prop('checked', tp);
  })

  // 뒤로 가기
  $('.btn_prev').click(function() {
    history.go(-1);
  });

  // 휴대폰 번호에 Hypen 추가
  $('#reg_phone').keyup(function(event) {
    event = event || window.event;
    let _val = this.value.trim();
    this.value = autoHypenTel(_val);

    // 인증 후 휴대폰 번호를 변경하였을 때
    auth_phone = false;
    $('#auth_phone').val('false');
    $('.auth_btn').show();
  });

  // ID 소문자로 강제 변환
  $('#reg_id').on('propertychange input', function() {
    $(this).val($(this).val().toLowerCase());
  })

  // ID 중복 검사
  $('.id_check_btn').click(function() {
    let reg_id = $('#reg_id').val();

    if (!validId(reg_id)) {
      return false;
    }

    $.post('./signup.php', {
      mode: 'idDuplicateCheck',
      id: reg_id
    }, function(e) {
      alert(e.msg);
    }, 'json');
  })

  // 인증 번호 요청
  $('.auth_btn').click(function() {
    let reg_name = $('#reg_name').val();
    let reg_cuty = $('#reg_country').val();
    let phone    = $('#reg_phone').val();
    let birth    = $('#reg_birth').val();

    if (!validCountry(reg_cuty) ||
        (reg_cuty == 'kr' && !validName(reg_name)) ||
        (reg_cuty == 'os' && !validEname(reg_name)) ||
        !validBirth(birth) || !validPhone(phone)) {
      return false;
    }

    $.post('./other.php', {
      mode: 'join_tel_chk',
      tel: phone
    }, function(e) {
      if (e.error) {
        $('.auth_btn').hide();
        $('.auth_chk').show();
        authNumCountDown();
      } else {
        alert(e.msg);
      }
    }, 'json');
  })

  // 인증 번호 확인
  $('.auth_chk_btn').click(function() {
    let phone   = $('#reg_phone').val();
    let authNum = $('#auth_num').val();
    let timeout = time(); // 입력 유효 시간 확인

    if (!validAuthNum(authNum)) {
      return false;
    }

    if (timeout == false) {
      alert('입력 시간을 초과했습니다. 인증 번호를 다시 요청해 주세요.');
      return false;
    }

    $.post('./other.php', {
      mode: 'join_auth_chk',
      tel: phone,
      auth: authNum
    }, function(e) {
      if (e.error) {
        $('.auth_btn').hide();
        $('.auth_chk').hide();
        alert('인증에 성공하였습니다.');
        auth_phone = true;
        $('#auth_phone').val('true');
      } else {
        alert(e.msg);
      }
    }, 'json');
  })

  // 회원 가입
  $('.ok_btn').click(function() {
    let id     = $('#reg_id').val();
    let email  = $('#reg_email').val();
    let county = $('#reg_country').val();
    let name   = $('#reg_name').val();
    // let ename  = $('#reg_ename').val();
    let birth  = $('#reg_birth').val();
    let gender = $('#reg_gender').val();
    let phone  = $('#reg_phone').val();
    let pwd    = $('#reg_pwd').val();
    let cPwd   = $('#reg_pwd_chk').val();
    let pop1   = $('.agree').find('input[type="checkbox"]').eq(1).prop('checked');
    let pop2   = $('.agree').find('input[type="checkbox"]').eq(2).prop('checked');
    let pop4   = $('.agree').find('input[type="checkbox"]').eq(4).prop('checked');

    if (!validId(id) || !validEmail(email) || !validCountry(county) ||
        (county == 'kr' && !validName(name)) || (county == 'os' && !validEname(name)) ||
        !validBirth(birth) || !validGender(gender) || !validPhone(phone) || !validPwd(pwd, cPwd)) {
      return false;
    }

    if (!auth_phone) {
      alert('인증 번호를 확인해 주세요.');
      return false;
    }

    if (!pop1 || !pop2 || !pop4) {
      alert('필수 약관에 동의하여 주세요.');
      return false;
    }

    $.post('./signup.php', $('#w_frm').serialize(), function(e) {
      console.log(e);
      if (e.error) {
        alert(e.msg);
      } else {
        console.log("회원가입");
        alert("회원가입이 완료되었습니다.");
        _tfa.push({notify: 'event', name: 'lead', id: 2024947});
        // 회원가입 성공 시 로그인 처리
        login(id, pwd);
      }
    }, 'json');
  })

  function login(id, pwd) {
    $.post('./login.php', {
      user_id: id,
      passwd: pwd,
      rememberme: '',
      type: 'signup'
    }, function(e) {
      
      if (!e.error) {
        location.href = e.qs;
      } else {
        alert(e.msg);
      }
    }, 'json');
  }
});
