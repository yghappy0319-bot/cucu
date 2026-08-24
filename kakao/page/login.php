<?php include_once "../adm/head.php";?>
    <div class="app-content content container-fluid">
      <div class="content-wrapper">
        <div class="content-header row">
        </div>
        <div class="content-body">
          <section class="flexbox-container">
            <div class="col-md-4 offset-md-4 col-xs-10 offset-xs-1  box-shadow-2 p-0">
                <div class="card border-grey border-lighten-3 m-0">
                    <div class="card-header no-border">
                        <div class="card-title text-xs-center">
                            <div class="p-1"><img src="../../app-assets/images/logo/logo.png" alt="branding logo"></div>
                        </div>
                        <p style="text-align: center;" >
                          SMM마케팅패널 제작 | 무통장입금 자동화 | 웹사이트 유지보수
                        </p>
                        <h6 class="card-subtitle line-on-side text-muted text-xs-center font-small-3 pt-2"><span>로그인</span></h6>
                    </div>
                    <div class="card-body collapse in">
                        <div class="card-block">
                            <form class="form-horizontal form-simple" id="loginFrm" >
                                <fieldset class="form-group position-relative has-icon-left mb-0">
                                    <input type="text" name="mb_id" class="form-control form-control-lg input-lg" id="user-name" placeholder="아이디" value="<?=$_COOKIE['log_id']?>">
                                    <div class="form-control-position">
                                        <i class="icon-head"></i>
                                    </div>
                                </fieldset>
                                <fieldset class="form-group position-relative has-icon-left">
                                    <input type="password" name="mb_password" class="form-control form-control-lg input-lg" id="user-password" placeholder="비밀번호" value="<?=$_COOKIE['log_pw']?>">
                                    <div class="form-control-position">
                                        <i class="icon-key3"></i>
                                    </div>
                                </fieldset>
                                <fieldset class="form-group row">
                                    <div class="col-md-6 col-xs-12 text-xs-center text-md-left">
                                        <fieldset>
                                            <input type="checkbox" id="remember-me" class="chk-remember" onclick="go_login_save_info()">
                                            <label for="remember-me"> 로그인 정보 저장하기</label>
                                        </fieldset>
                                    </div>
                                    <div class="col-md-6 col-xs-12 text-xs-center text-md-right"><a href="pwfind.php" class="card-link">비밀번호 찾기</a></div>
                                </fieldset>
                                <button type="button" class="btn btn-primary btn-lg btn-block" onclick='go_login()'>
                                  <i class="icon-unlock2"></i> 로그인
                                </button>

                                <button type="button" class="btn btn-success btn-lg btn-block" onclick='go_test_login()'>
                                  둘러보기
                                </button>

                                <button type="button" class="btn btn-info btn-lg btn-block" onclick='go_kakao()'>
                                  카카오톡으로 문의하기
                                </button>

                                <button type="button" class="btn btn-info btn-lg btn-block" onclick='go_telegram()'>
                                  텔레그램으로 문의하기
                                </button>


                            </form>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="">
                            <p class="float-sm-left text-xs-center m-0"><a href="pwfind.php" class="card-link">비밀번호 찾기</a></p>
                            <p class="float-sm-right text-xs-center m-0"><a href="register.php" class="card-link">회원가입</a></p>
                        </div>
                    </div>
                </div>
            </div>
          </section>
        </div>
      </div>
    </div>
<script>
  function go_test_login(){
    $("#user-name").val("testuser");
    $("#user-password").val("testuser");
    go_login();
  }

  function go_login(){

    var userid = $("#user-name").val();
    if(userid==""){
      alert("아이디를 입력해주세요.");
      return false;
    }

  var params = jQuery("#loginFrm").serialize();
  $.ajax({
    type : "POST",
    url: "/ajax/login.chk.php",
    async: false,
    data: params,
    dataType: "json",
    success: function(r) {
      console.log(r);
      if(r.msg=="login_ok"){
        top.location.href=r.url;
      }else{
        alert("로그인 정보를 확인해주세요.");
      }
      console.log(r);
      },
      error:function(e) {
        alert(e.responseText);
      }
  });
  }

  function go_login_save_info(){
       var checked = $("#remember-me").is(":checked");
       alert("아이디와 비밀번호가 현재 브라우저에 저장됩니다. 공공장소에서의 사용을 주의하세요!");
       console.log(checked);
       if(checked){
         if($("#user-name").val()){
           setCookie('log_id', $("#user-name").val(), 14);
         }
         if($("#user-password").val()){
           setCookie('log_pw', $("#user-password").val(), 14);
         }
       }
  }

  function go_kakao(){
    var kakao_link = '<?=$카카오링크?>';
    window.open(kakao_link);
  }
  
  function go_telegram(){
    var telegram_link = '<?=$텔레그램링크?>';
    window.open(telegram_link);
  }

</script>

<?php include_once "../adm/footer.php";?>
