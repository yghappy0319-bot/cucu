$(document).ready(function(){
    //최상단 체크박스 클릭
    $("#all_agree").click(function(){
        //클릭되었으면
        if($("#all_agree").prop("checked")){
            //input태그의 name이 chk인 태그들을 찾아서 checked옵션을 true로 정의
            $(".agree").prop("checked",true);
            //클릭이 안되있으면
        }else{
            //input태그의 name이 chk인 태그들을 찾아서 checked옵션을 false로 정의
            $(".agree").prop("checked",false);
        }
    })
})

function go_code_chke(){
  var phone = $("#phone").val();

  var smscode = $("#smscode").val();
  if(smscode==""){
    alert("인증번호를 입력해주세요.");
    return false;
  }

  $.ajax({
     type : "POST",
     url: "ajax_sms_code_chk.php",
     async: false,
     data: {
        smscode : $("#smscode").val(),
        phone : phone,
        page : "member"
     },
     dataType: "json",
     success: function(result) {
       console.log(result);
        if(result.msg=="success"){
          alert("인증번호가 확인되었습니다.");
          $("#smscode").prop("readonly", true);
          $("#sms_send_status").val(1);
          $(".sms_form").hide(1000);
        }else{
          alert("인증번호를 확인해주세요.");
          return false;
        }

     },
     error:function(e) {
        alert(e.responseText);
     }
  });
}

function go_send_sms(){
  var userid = $("#login_id").val();
  var password = $("#password").val();
  var repassword = $("#repassword").val();
  var username = $("#member_name").val();
  var phone = $("#phone").val();
  var sms_send_chk = $("#sms_send_chk").val();
  if(userid==""){
    alert("아이디를 입력해주세요.");
    return false;
  }
  if(password==""){
    alert("비밀번호를 입력해주세요.");
    return false;
  }
  if(repassword==""){
    alert("비밀번호 재확인을 입력해주세요.");
    return false;
  }
  if(username==""){
    alert("이름을 입력해주세요.");
    return false;
  }

  if(phone==""){
    alert("휴대폰번호를 입력해주세요.");
    return false;
  }

  if(sms_send_chk==1){
    alert("인증번호가 문자로 발송되었습니다.\n인증번호가 도착하지 않았다면 아래 고객센터1:1문의로 요청 주시면 도와드리겠습니다.");
    return false;
  }

  $.ajax({
     type : "POST",
     url: "ajax_send_sms.php",
     async: false,
     data: {
        phone :phone,
        pages : 'member'
     },
     success: function(result) {
       console.log(result);
        if(result==1){
          $(".codechk_form").show();
          $("#phone").prop("readonly", true);
          $("#sms_send_chk").val(1);
          alert("인증번호가 문자로 발송되었습니다.\n인증번호가 도착하지 않았다면 아래 고객센터1:1문의로 요청 주시면 도와드리겠습니다.");
          return false;
        }else if(result=="over"){
          alert("인증문자 발송개수가 초과되었습니다. 관리자에게 문의해주세요.");
          return false;
        }else{
          alert(result);
          return false;
        }
     },
     error:function(e) {
        alert(e.responseText);
     }
  });
}




function go_kakao_regs(){
  var agree1 = $("#agree1").is(":checked");
  var agree2 = $("#agree2").is(":checked");
  var agree3 = $("#agree3").is(":checked");

  if(!agree1){
    alert("이용약관에 동의하여야만 회원가입이 가능합니다.");
    return false;
  }
  if(!agree2){
    alert("개인정보처리방침에 동의하여야만 회원가입이 가능합니다.");
    return false;
  }

  var phone = $("#phone").val();
  if(phone==""){
    alert('연락처를 입력해주세요.');
    return false;
  }

  // 사용 예시
  if (!isValidPhoneNumber(phone)) {
    alert("휴대폰번호가 올바르지 않습니다. 올바른 휴대폰번호를 입력해주세요.");
    return false;
  }

  var params = jQuery("#memberFrm").serialize();
    $.ajax({
      type : "POST",
      url: "ajax_kakao_member_join.php",
      async: false,
      data: params,
      success: function(result) {
        var r = result.trim();
        console.log(r);
        if(r==1){
          alert("회원가입이 완료되었습니다.");
          $("#memberFrm").attr("action","/member/login.html");
          $("#memberFrm").submit();
          $("#memberFrm").attr("action","");
          //가입완료
        }else if(r=="phone_overlap"){
          alert("이미 등록된 휴대폰번호 입니다.");
          $("#reg_msg").html("<font color='red' >이미 등록된 휴대폰번호 입니다.</font>");
          $("#phone").focus();
          return false;
        }else if(r=="recommender_id_null"){
          alert("존재하지 않는 추천인 코드 입니다.\n추천인 코드를 정확히 입력해주세요.");
          return false;
        }else{
          alert(r);
        }

      },
      error:function(e) {
          alert(e.responseText);
      }
    });
}
