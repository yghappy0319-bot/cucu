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

function isValidid(userid) {
  // 최소 8자 이상
  if (userid.length < 8) {
      return false;
  }
  // 영문자 또는 숫자 포함 확인
  const hasLetterOrNumber = /[a-zA-Z0-9]/.test(userid);

  return hasLetterOrNumber;
}

function isValidpass(pass) {
  // 최소 8자 이상
  if (pass.length < 6) {
      return false;
  }

  return true;
}
function setCookie(name, value, days) {
    var expires = "";
    if (days) {
        var date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        expires = "; expires=" + date.toUTCString();
    }
    document.cookie = name + "=" + value + expires + "; path=/";
}
