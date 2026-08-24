$(document).ready(function() {
  let auth_phone = false; // 휴대폰 인증 여부

  // 뒤로 가기
  $('.btn_prev').click(function() {
    history.go(-1);
  });

  // 휴대폰 번호에 Hypen 추가
  $('#user_phone').keyup(function(event) {
    event = event || window.event;
    let _val = this.value.trim();
    this.value = autoHypenTel(_val);

    // 인증 후 휴대폰 번호를 변경하였을 때
    auth_phone = false;
    $('.auth_btn').show();
  });


  // 인증 번호 요청
  $('.auth_btn').click(function() {
    let id = $('#reg_id').val();
    let name = $('#reg_name').val();
    let phone = $('#user_phone').val();

    if (!validId(id) || !validPhone(phone)) {
      return false;
    }

    $.post('/other.php', {
      mode: 'find_tel_chk',
      name: name,
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
    let phone = $('#user_phone').val();
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
      mode: 'join_auth_chk',
      tel: phone,
      auth: authNum
    }, function(e) {
      if (e.error) {
        $('.auth_btn').hide();
        $('.auth_chk').hide();
        alert('인증에 성공하였습니다.');
        auth_phone = true;
      } else {
        alert(e.msg);
      }
    }, 'json');
  });

  // 비밀번호 찾기
  $('.ok_btn').click(function() {
    let id = $('#reg_id').val();
    let phone = $('#user_phone').val();

    if (!validId(id) || !validPhone(phone)) {
      return false;
    }

    if (!auth_phone) {
      alert('인증 번호를 확인해 주세요.');
      return false;
    }

    $.post('/other.php', {
      mode: 'id_check',
      hp: phone
    }, function(e) {
      if (e.error) {
        $('#find_id').val(e.user_id);
        $('.find_pw').hide();
        $('.sec_find').show();
      } else {
        alert(e.msg);
      }
    }, 'json');
  });

  // 비밀번호 변경
  $('.ok_btn_new').click(function() {
    let id = $('#find_id').val();
    let phone = $('#user_phone').val();
    let new_pwd = $('#new_pwd').val();
    let new_pwd_chk = $('#new_pwd_chk').val();

    if (!validId(id) || !validPwd(new_pwd, new_pwd_chk)) {
      return false;
    }

    $.post('./other.php', {
      mode: "pwd_update",
      tel: phone,
      pwd: new_pwd_chk
    }, function(e) {
      if (e.error) {
        // 성공 시 메인페이지로 이동
        alert(e.msg, '/');
      } else {
        alert(e.msg);
      }
    }, 'json');
  });
});
