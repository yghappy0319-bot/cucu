<?php include_once "../adm/head.php";?>
<div class="app-content content container-fluid">
      <div class="content-wrapper">
        <div class="content-header row">
        </div>
        <div class="content-body">
          <section class="flexbox-container">
              <div class="col-md-4 offset-md-4 col-xs-10 offset-xs-1 box-shadow-2 p-0">
                  <div class="card border-grey border-lighten-3 px-2 py-2 m-0">
                      <div class="card-header no-border pb-0">
                          <div class="card-title text-xs-center">
                              <img src="../../app-assets/images/logo/logo.png" alt="branding logo">
                          </div>
                          <h6 class="card-subtitle line-on-side text-muted text-xs-center font-small-3 pt-2"><span>비밀번호 찾기</span></h6>
                      </div>
                      <div class="card-body collapse in">
                          <div class="card-block">
                              <form class="form-horizontal" id="findfrm" >
                                  <fieldset class="form-group position-relative has-icon-left">
                                      <input type="text" name="mb_hp" class="form-control form-control-lg input-lg" id="user-phone" placeholder="연락처" maxlength="13" onkeyup="isNumberOrHyphen(this);cvtPhoneNumber(this);">
                                      <div class="form-control-position">
                                          <i class="icon-android-phone-portrait"></i>
                                      </div>
                                  </fieldset>
                                  <button type="button" onclick="go_find()" class="btn btn-primary btn-lg btn-block">
                                    <i class="icon-lock4"></i> 비밀번호 찾기</button>
                              </form>
                          </div>
                      </div>
                      <div class="card-footer no-border">
                          <p class="float-sm-left text-xs-center"><a href="login.php" class="card-link">로그인</a></p>
                          <p class="float-sm-right text-xs-center">아직 회원이 아니신가요 ? <a href="register.php" class="card-link">회원가입</a></p>
                      </div>
                  </div>
              </div>
          </section>
        </div>
      </div>
    </div>
<script>
function go_find(){
  const userphone = $('#user-phone').val();
   if (!userphone) {
       alert("가입된 휴대폰번호를 입력해주세요.");
       return false;
   }

  var params = jQuery("#findfrm").serialize();
  $.ajax({
    type : "POST",
    url: "/ajax/id.pass.find.php",
    async: false,
    data: params,
    dataType: "json",
    success: function(result) {
      console.log(result);
      if(result.status>0){
        alert(result.msg);
        $('#user-phone').val("");
        top.location.href="/";
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
