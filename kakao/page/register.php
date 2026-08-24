<?php include_once "../adm/head.php";?>
<div class="app-content content container-fluid">
      <div class="content-wrapper">
        <div class="content-header row">
        </div>
        <div class="content-body">
          <section class="flexbox-container">
            <div class="col-md-4 offset-md-4 col-xs-10 offset-xs-1 box-shadow-2 p-0">
          		<div class="card border-grey border-lighten-3 px-2 py-2 m-0">
          			<div class="card-header no-border">
          				<div class="card-title text-xs-center">
          					<img src="../../app-assets/images/logo/logo.png" alt="branding logo">
          				</div>
          				<h6 class="card-subtitle line-on-side text-muted text-xs-center font-small-3 pt-2"><span>회원가입</span></h6>
          			</div>
          			<div class="card-body collapse in">

          				<div class="card-block">
          					<form class="form-horizontal form-simple" id="regfrm" >

          						<fieldset class="form-group position-relative has-icon-left mb-1">
          							<input type="text" class="form-control form-control-lg input-lg" autocomplete="off" name="mb_id" id="user-name" placeholder="아이디">
          							<div class="form-control-position">
          							    <i class="icon-head"></i>
          							</div>
          						</fieldset>

          						<fieldset class="form-group position-relative has-icon-left">
          							<input type="password" class="form-control form-control-lg input-lg" autocomplete="new-password" name="mb_password" id="user-password" placeholder="비밀번호" >
          							<div class="form-control-position">
          							    <i class="icon-key3"></i>
          							</div>
          						</fieldset>

                      <fieldset class="form-group position-relative has-icon-left mb-1">
                        <input type="text" class="form-control form-control-lg input-lg" name="mb_name" id="mb_name" placeholder="이름" required maxlength="40" >
                        <div class="form-control-position">
                            <i class="icon-user4"></i>
                        </div>
                      </fieldset>

                      <fieldset class="form-group position-relative has-icon-left mb-1">
                        <div style="display:flex;">
                          <div class="position-relative" style="flex:1;">
                            <input type="text" class="form-control form-control-lg input-lg" name="mb_hp" id="user-phone" placeholder="연락처" maxlength="13" onkeyup="isNumberOrHyphen(this);cvtPhoneNumber(this);onPhoneChange();" >
                            <div class="form-control-position">
                                <i class="icon-android-phone-portrait"></i>
                            </div>
                          </div>
                          <button type="button" class="btn btn-secondary ml-1" id="btn_send_code" onclick="go_send_code()" style="white-space:nowrap;">인증번호 발송</button>
                        </div>
                      </fieldset>

                      <fieldset class="form-group position-relative has-icon-left mb-1" id="auth_code_form" style="display:none;">
                        <div style="display:flex;">
                          <div class="position-relative" style="flex:1;">
                            <input type="text" class="form-control form-control-lg input-lg" id="auth_code" placeholder="인증번호 6자리" maxlength="6" inputmode="numeric" >
                            <div class="form-control-position">
                                <i class="icon-key3"></i>
                            </div>
                          </div>
                          <button type="button" class="btn btn-info ml-1" id="btn_check_code" onclick="go_auth_code_chk()" style="white-space:nowrap;">인증확인</button>
                        </div>
                        <small class="text-muted" id="auth_msg"></small>
                      </fieldset>
                      <input type="hidden" id="auth_status" value="0" />


                      <div >
      									<div class="input-group">
      										<label class="display-inline-block custom-control custom-checkbox">
      											<input type="checkbox" name="agree1" id="agree1" value="1" class="custom-control-input">
      											<span class="custom-control-indicator"></span>
      											<span class="custom-control-description ml-0">서비스이용약관</span>
                            <a href="/page/terms.html" target="_blank">확인</a>
      										</label>
      									</div>
      								</div>

                      <div class="form-group">
      									<div class="input-group">
      										<label class="display-inline-block custom-control custom-checkbox">
      											<input type="checkbox" name="agree2" id="agree2" value="1" class="custom-control-input">
      											<span class="custom-control-indicator"></span>
      											<span class="custom-control-description ml-0">개인정보처리방침</span>
                            <a href="/page/privacy.html" target="_blank">확인</a>
      										</label>
      									</div>
      								</div>

          						<button type="button" class="btn btn-primary btn-lg btn-block" onclick="go_register()"><i class="icon-unlock2"></i> 회원가입</button>
          					</form>
          				</div>
          				<p class="text-xs-center">이미 가입된 계정이 있으신가요 ? <a href="login.php" class="card-link">로그인</a></p>
          			</div>
          		</div>
          	</div>
          </section>
        </div>
      </div>
    </div>
   <p id="result"></p>
<script>
  function onPhoneChange() {
    $('#auth_status').val('0');
    $('#auth_msg').text('');
    $('#auth_code').val('');
    $('#auth_code_form').hide();
    $('#btn_check_code').prop('disabled', false).text('인증확인');
    $('#btn_send_code').prop('disabled', false).text('인증번호 발송');
    $('#user-phone').prop('readonly', false);
  }

  function go_send_code() {
    var phone = ($('#user-phone').val() || '').trim();
    if (!phone) {
      alert('연락처를 입력해주세요.');
      $('#user-phone').focus();
      return;
    }
    if ($('#auth_status').val() == '1') {
      alert('인증번호가 도착하지 않았다면 새로고침 후 재발송 해주세요.');
      return;
    }

    $('#btn_send_code').prop('disabled', true).text('발송중…');
    $.ajax({
      type: 'POST',
      url: '/ajax/_ajax_register_auth_number.php',
      dataType: 'json',
      data: { phone: phone },
      success: function(result) {
        if (result.status == 1) {
          alert(result.msg);
          $('#auth_code_form').show();
          $('#auth_status').val('1');
          $('#auth_code').val('').focus();
          $('#auth_msg').text('문자로 받은 6자리 인증번호를 입력해주세요.');
        } else {
          alert(result.msg || '발송에 실패했습니다.');
          $('#btn_send_code').prop('disabled', false).text('인증번호 발송');
        }
      },
      error: function(e) {
        alert(e.responseText || '요청 오류');
        $('#btn_send_code').prop('disabled', false).text('인증번호 발송');
      }
    });
  }

  function go_auth_code_chk() {
    var phone = ($('#user-phone').val() || '').trim();
    var code = ($('#auth_code').val() || '').trim();
    if ($('#auth_status').val() != '1' && $('#auth_status').val() != '2') {
      alert('인증번호를 발송해주세요.');
      return;
    }
    if (!code || code.length !== 6) {
      alert('인증번호 6자리를 입력해주세요.');
      $('#auth_code').focus();
      return;
    }

    $.ajax({
      type: 'POST',
      url: '/ajax/_ajax_register_auth_code_chk.php',
      dataType: 'json',
      data: { phone: phone, code: code },
      success: function(result) {
        if (result.status == 1) {
          alert(result.msg);
          $('#auth_status').val('2');
          $('#auth_msg').text('휴대폰 인증이 완료되었습니다.');
          $('#btn_check_code').prop('disabled', true).text('인증완료');
          $('#btn_send_code').prop('disabled', true).text('인증완료');
          $('#user-phone').prop('readonly', true);
        } else {
          alert(result.msg || '인증에 실패했습니다.');
        }
      },
      error: function(e) {
        alert(e.responseText || '요청 오류');
      }
    });
  }

  function go_register(){
    const userid = ($('#user-name').val() || '').trim();
    const password = $('#user-password').val();
    const userphone = ($("#user-phone").val() || '').trim();
    const mb_name = ($("#mb_name").val() || '').trim();
     if (!isValidid(userid)) {
         alert("아이디는 영문자 또는 숫자 포함 8자리 이상 입력해주세요.");
         return false;
     }
     if (!isValidpass(password)) {
         alert("비밀번호는 6자리 이상 입력해주세요.");
         return false;
     }
     if (!mb_name) {
       alert("이름을 입력해주세요.");
       $("#mb_name").focus();
       return false;
     }
     if (!userphone) {
       alert("연락처를 입력해주세요.");
       return false;
     }
     if ($('#auth_status').val() != '2') {
       alert('휴대폰 인증을 완료해주세요.');
       return false;
     }
     if (!$('#agree1').is(':checked')) {
       alert('서비스이용약관에 동의해주세요.');
       return false;
     }
     if (!$('#agree2').is(':checked')) {
       alert('개인정보처리방침에 동의해주세요.');
       return false;
     }
     $("#mb_name").val(mb_name);

     var kakao_link = '<?=$카카오링크?>';
    var params = jQuery("#regfrm").serialize();
    $.ajax({
      type : "POST",
      url: "/ajax/join.php",
      async: false,
      data: params,
      dataType: "json",
      success: function(result) {
        if(result.status>0){
          alert(result.msg);
          window.open(kakao_link, '_blank', 'noopener,noreferrer');
          window.location.href = '/page/login.php';
        }else{
          alert(result.msg);
          return false;
        }
      },
      error:function(e) {
        alert(e.responseText);
      }
    });
  }
</script>

<?php include_once "../adm/footer.php";?>
