
function setCookie(name, value, days) {
    var expires = "";
    if (days) {
        var date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        expires = "; expires=" + date.toUTCString();
    }
    document.cookie = name + "=" + value + expires + "; path=/";
}
function getCookie(cookieName) {
  const name = cookieName + "=";
  const decodedCookie = decodeURIComponent(document.cookie);
  const cookieArray = decodedCookie.split(';');

  for (let i = 0; i < cookieArray.length; i++) {
    let cookie = cookieArray[i].trim();
    if (cookie.indexOf(name) === 0) {
      return cookie.substring(name.length, cookie.length);
    }
  }

  return null;
}

function CountDownTimer(dt, id) {
  var end = new Date(dt); // dt는 한국 시간 기준 문자열 (예: '2025-03-01T15:00:00+09:00')

     var timer;
    function showRemaining() {
        var now = moment();
        var distance = end - now;
        if (distance < 0) {
            clearInterval(timer);
            document.getElementById(id).innerHTML = 'EXPIRED!';
            return;
        }

        var seconds = Math.floor(distance / 1000);
        var minutes = Math.floor(seconds / 60);
        var hours = Math.floor(minutes / 60);

        hours %= 24;
        minutes %= 60;
        seconds %= 60;

        var days = Math.floor(distance / (1000 * 60 * 60 * 24));
        var totalHours = days * 24 + hours;
        var remainingTime = totalHours + '시간 ' + minutes + '분 ' + seconds + '초';
        if(id == "power_countdown"){
          if(totalHours < 20){
            $(".pb_end_time").addClass("blinking4");
          }
        }else{
          if(totalHours < 20){
            $(".mm_end_time").addClass("blinking4");
          }
        }

        document.getElementById(id).innerHTML = remainingTime;
    }

    showRemaining(); // 초기 호출
    timer = setInterval(showRemaining, 1000);
}

function CountDownTimer_new(dt, id) {
  var end = new Date(dt); // dt는 한국 시간 기준 문자열 (예: '2025-03-01T15:00:00+09:00')
     var timer;
    function showRemaining() {
        var now = moment();
        var distance = end - now;
        if (distance < 0) {
            clearInterval(timer);
            document.getElementById(id).innerHTML = 'EXPIRED!';
            return;
        }

        var seconds = Math.floor(distance / 1000);
        var minutes = Math.floor(seconds / 60);
        var hours = Math.floor(minutes / 60);

        hours %= 24;
        minutes %= 60;
        seconds %= 60;

        var days = Math.floor(distance / (1000 * 60 * 60 * 24));
        var totalHours = days * 24 + hours;
        var remainingTime = totalHours + '시간 ' + minutes + '분 ' + seconds + '초';
        if(id == "power_countdown"){
          if(totalHours < 20){
            $(".power_alert").addClass("on");
          }
        }else{
          if(totalHours < 20){
            $(".mega_alert").addClass("on");
          }
        }

        document.getElementById(id).innerHTML = remainingTime;
    }

    showRemaining(); // 초기 호출
    timer = setInterval(showRemaining, 1000);
}

function Day_CountDownTimer(dt, id) {
    var end = new Date(dt); // dt는 한국 시간 기준 문자열 (예: '2025-03-01T15:00:00+09:00')

    var timer = setInterval(function() {
        var now = new Date(); // 현재 시간 (브라우저 기본 시간)
        var distance = end - now;

        if (distance < 0) {
            clearInterval(timer);
            $('#' + id).html('EXPIRED!');
            return;
        }

        var seconds = Math.floor(distance / 1000);
        var minutes = Math.floor(seconds / 60);
        var hours = Math.floor(minutes / 60);
        var days = Math.floor(hours / 24);

        hours %= 24;
        minutes %= 60;
        seconds %= 60;

        var remainingTime;
        if (id === "power_countdown_new" || id === "mega_countdown_new") {
            remainingTime = days + '일 ' + hours + '시 ' + minutes + '분 ' + seconds + '초';
        } else {
            remainingTime = "남은시간 " + days + '일 <b>' + hours + '시 ' + minutes + '분 ' + seconds + '초</b>';
        }

        // 마감임박 표시 처리
        if (days < 1 && hours < 20) {
            if (id === "power_countdown_new") {
                $("#power_alert i").addClass("add_time");
                $(".pb_sold_text").css({"color": "#e13b2c", "font-weight": "bold"});
                $(".pb_sold_text").html("마감임박");
            } else {
                $("#mega_alert i").addClass("add_time");
                $(".mm_sold_text").css({"color": "#e13b2c", "font-weight": "bold"});
                $(".mm_sold_text").html("마감임박");
            }
        }

        $('#' + id).html(remainingTime);

    }, 1000); // 1초마다 갱신
}



//숫자와 하이픈 표시
function isNumberOrHyphen(obj){
    var exp = /[^0-9-]/g;
    if ( exp.test(obj.value) ) {
        alert("숫자와 '-'만 입력가능합니다.");
        obj.value = "";
        obj.focus();
    }
}

// 전화번호에 하이픈 찍어주기
function cvtPhoneNumber(obj){
    var exp = /-/g;
    var number = obj.value.replace(exp,"");
    var revNumber = reverse(number);
    if ( obj.value.length > 2 ) {
        if ( number.substring(0,2) == "02" ){
            obj.value = number.substring(0,2)+"-"+insertHyphen(number.substring(2));
        } else if ( obj.value.length > 3 && number.substring(0,2) != "02" && number.substring(0,1) == "0" ) {
            obj.value = number.substring(0,3)+"-"+insertHyphen(number.substring(3));
        } else if (obj.value.length > 4 && number.substring(0,1) != "0") {
            obj.value = number.substring(0,4)+"-"+insertHyphen(number.substring(4));
        }
    }
}

function reverse(s) {
    var rev = "";

    for(var i = s.length-1; i >= 0 ; i--) {
        rev += s.charAt(i);
    }
    return rev;
}

function insertHyphen(target){
    var rev = reverse(target);
    var cnt = 0;
    if ( target.length%4 != 0 ) {
        cnt = Math.floor(target.length/4);
    } else {
        cnt = Math.floor(target.length/4)-1;
    }
    var result = "";
    if ( cnt > 0 ) {
        var token = new Array();
        for ( var i=0; i<=cnt; i++ ) {
            token[i] =  reverse(rev.substring(0,4));
            rev = rev.substring(4);
        }
        for ( var i=cnt; i>0; i-- ){
            result = result + token[i] + "-";
        }
        result += token[0];
        return result;

    } else {
        return target;
    }
}

function isPasswordValid() {
  var passwordInput = document.getElementById("password");
  var password = passwordInput.value;

  // 비밀번호의 길이가 6자리 이상 20자 이하인지 확인
  if (password.length >= 6 && password.length <= 20) {
    return true; // 유효한 비밀번호
  } else {
    return false; // 유효하지 않은 비밀번호
  }
}

function find_pass_password_valid() {
  var passwordInput = document.getElementById("newpass");
  var password = passwordInput.value;

  // 비밀번호의 길이가 6자리 이상 20자 이하인지 확인
  if (password.length >= 6 && password.length <= 20) {
    return true; // 유효한 비밀번호
  } else {
    return false; // 유효하지 않은 비밀번호
  }
}
function isValidPhoneNumber(phoneNumber) {
  // 휴대폰 번호의 패턴을 정규 표현식으로 정의합니다.
  // 여기서는 숫자로 시작하고, 3자리 숫자-4자리 숫자-4자리 숫자 형식을 가정합니다.
  var pattern = /^\d{3}-\d{4}-\d{4}$/;

  // 정규 표현식을 사용하여 패턴을 확인합니다.
  return pattern.test(phoneNumber);
}

function 콤마추가(number) {
  return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}


function 콤마제거(number) {
    return number.replace(/,/g, "");
}
