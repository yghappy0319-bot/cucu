$(document).ready(function() {
  let auth_phone = false; // 휴대폰 인증 여부
  let passwd_chk = false; // 비밀번호 변경 선택 여부

  // 국적 선택
  $(document).on("click", ".country", function() {
    $(".country").parent().removeClass("on");
    $(".country").removeClass("button_on");
    $(this).parent().addClass("on");
    $(this).addClass("button_on");
    $("#reg_country").val($(this).data("value"));
    // 기존 회원들의 국적선택시 재입력 방지를 위해 처리 안함.
    // $('#reg_name').val("");
    // $('#reg_name').focus();
  });

  // 성별 선택
  $(document).on("click", ".gender", function() {
    $(".gender").parent().removeClass("on");
    $(".gender").removeClass("button_on");
    $(this).parent().addClass("on");
    $(this).addClass("button_on");
    $("#reg_gender").val($(this).data("value"));
  });

  // 이름 입력값 검증
  $('#reg_name').keyup(function() {
    let country = $("#reg_country").val();
    let name    = $('#reg_name').val();

    if (country == "") {
      alert("국적을 먼저 선택해 주세요.");
      $('#reg_name').val("");
      return false;
    }

    if (country == "kr") {
      $(this).val(name.replace(koreanRegexp_check, ''));
    } else {
      $(this).val(name.replace(englishRegexp_check, ''));
    }
  });

  // 휴대폰 번호에 Hypen 추가
  $('#user_phone').keyup(function(event) {
    event = event || window.event;
    let _val = this.value.trim();
    this.value = autoHypenTel(_val);

    // 인증 후 휴대폰 번호를 변경하였을 때
    auth_phone = false;
    $('#auth_phone').val("false");
    $('.auth_btn').show();
  });

  // 비밀번호 변경 선택
  $('#pwd_btn').click(function() {
    passwd_chk = true;
    alert('비밀번호 변경을 선택했습니다.');
    $(this).hide();
  })

  // 인증 번호 요청
  $('.auth_btn').click(function() {
    let phone = $('#user_phone').val();

    if (!validPhone(phone)) {
      return false;
    }

    $.post('/other.php', {
      mode: 'mupdate_tel_chk',
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
  });

  // 인증 번호 확인
  $('.auth_chk_btn').click(function() {
    let phone   = $('#user_phone').val();
    let authNum = $('#auth_num').val();
    let timeout = time(); // 입력 유효 시간 확인

    if (!validAuthNum(authNum)) {
      return false;
    }

    if (timeout == false) {
      alert('입력 시간을 초과했습니다. 인증 번호를 다시 요청해 주세요.');
      return false;
    }

    $.post('/other.php', {
      mode: 'mupdate_auth_chk',
      tel: phone,
      auth: authNum
    }, function(e) {
      if (e.error) {
        $('.auth_btn').hide();
        $('.auth_chk').hide();
        alert('인증에 성공하였습니다.');
        auth_phone = true;
        $('#auth_phone').val("true");
      } else {
        alert(e.msg);
      }
    }, 'json');
  });

  // 회원 정보 수정
  $('.ok_btn').click(function() {
    let gender = $('#reg_gender').val();
    let county = $('#reg_country').val();
    let name   = $('#reg_name').val();
    // let ename  = $('#ename').val();
    let birth  = $('#birth').val();
    let email  = $('#email').val();
    let phone  = $('#user_phone').val();
    let pwd    = $('#pwd').val();
    let cPwd   = $('#pwd_chk').val();

    if (!validCountry(county) ||
        (county == "kr" && !validName(name)) ||
        (county == "os" && !validEname(name)) ||
        !validBirth(birth) || !validGender(gender) ||
        !validEmail(email) || !validPhone(phone)) {
      return false;
    }

    if (passwd_chk) {
      if (!validPwd(pwd, cPwd)) {
        return false;
      }
    } else {
      $('#pwd').val('');
      $('#pwd_chk').val('');
    }

    if (!auth_phone) {
      alert('인증 번호를 확인해 주세요.');
      return false;
    }

    $('#w_frm').submit();
  });
});
