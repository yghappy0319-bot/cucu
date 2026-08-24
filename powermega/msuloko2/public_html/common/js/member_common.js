'use strict';

/**
 * 회원 가입 및 회원 정보 수정
 */
let koreanRegexp  = /^[가-힣]{2,10}$/;
let englishRegexp = /^[a-zA-Z\s]{5,30}$/;
let numberRegexp  = /^[0-9]+$/;
let idRegexp      = /^[a-z]+[a-z0-9]{4,14}$/;
let pwRegexp      = /^(?!.*[&'<>])(?=.*[a-zA-Z])(?=.*[0-9]).{8,20}$/;
let birthRegexp   = /^(19[0-9][0-9]|20\d{2})(0[0-9]|1[0-2])(0[1-9]|[1-2][0-9]|3[0-1])$/;
let emailRegexp   = /^[\w-]+(\.[\w-]+)*@([\w-]+\.)+[a-zA-Z]+$/;

let koreanRegexp_check  = /[a-z0-9]|[ \[\]{}()<>?|`~!@#$%^&*-_+=,.;:\"'\\]/g;
let englishRegexp_check = /[ㄱ-힣0-9~!@#$%^&*()_+|<>?:{}=]/g;

function validId(id) {
  if (id === '') {
    alert('아이디를 입력해주세요.');
    return false;
  } else if (!idRegexp.test(id)) {
    alert('아이디를 정확히 입력해주세요.');
    return false;
  }
  return true;
}

function validEmail(email) {
  if (email === '') {
    alert('이메일을 입력해주세요.');
    return false;
  } else if (!emailRegexp.test(email)) {
    alert('정확한 이메일 주소를 입력해주세요.');
    return false;
  }
  return true;
}

function validCountry(country) {
  if (!country) {
    alert('국적을 선택해주세요.');
    return false;
  }
  return true;
}

function validName(name) {
  if (name === '') {
    alert('휴대폰 명의 이름을 입력해주세요.');
    return false;
  } else if (!koreanRegexp.test(name)) {
    alert('이름은 2~10자 사이 공백없이 한글로 입력해주세요.');
    return false;
  }
  return true;
}

function validEname(ename) {
  if (ename === '') {
    alert('영문 이름을 입력해주세요.');
    return false;
  } else if (!englishRegexp.test(ename)) {
    alert('외국인은 영문 이름을 입력해주세요. (최소 5자 이상)');
    return false;
  }
  return true;
}

function validBirth(birth) {
  if (birth === '') {
    alert('생년월일을 입력해주세요.');
    return false;
  } else if (birth.length !== 8) {
    alert('생년월일을 정확히 입력해주세요.');
    return false;
  } else if (!numberRegexp.test(birth)) {
    alert('생년월일을 정확히 입력해주세요.');
    return false;
  } else if (!birthRegexp.test(birth)) {
    alert('생년월일을 정확히 입력해주세요.');
    return false;
  } else if (isChild(birth)) {
    alert('19세 미만은 회원가입을 할 수 없습니다.');
    return false;
  }
  return true;
}

function validGender(gender) {
  if (!gender) {
    alert('성별을 선택해주세요.');
    return false;
  }
  return true;
}

function validPhone(tel) {
  if (tel === '') {
    alert('핸드폰 번호를 입력해주세요.');
    return false;
  }
  return true;
}

function validAuthNum(authNum) {
  if (authNum === '') {
    alert('인증번호를 입력해주세요.');
    return false;
  }
  return true;
}

function validPwd(pwd, cPwd) {
  if (pwd === '' || cPwd === '' || !pwd || !cPwd) {
    alert('비밀번호를 입력해주세요.');
    return false;
  } else if (pwd !== cPwd) {
    alert('입력하신 비밀번호가 동일하지 않습니다.');
    return false;
  } else if (!pwRegexp.test(cPwd)) {
    alert('비밀번호는 8~20자의 영문과 숫자를 섞어주세요.');
    return false;
  }
  return true;
}

// 휴대폰 번호 하이픈 자동 입력
function autoHypenTel(phoneNum) {
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

  return result.filter((val) => val).join('-');
}

// 19세 미만 확인
function isChild(birthDate) {
  let today = new Date();
  let yyyy = today.getFullYear();
  let mm = today.getMonth() < 9 ? '0' + (today.getMonth() + 1) : (today.getMonth() + 1);
  let dd  = today.getDate() < 10 ? '0' + today.getDate() : today.getDate();

  if (parseInt(birthDate) < 10000000) {
    return true;
  }

  return parseInt(yyyy.toString() + mm.toString() + dd.toString()) - parseInt(birthDate) - 190000 < 0;
}

// 인증번호 입력 시간 카운트다운
function authNumCountDown() {
  $('.count_down').plug_in_time({
    minute : 3,
    second : 0,
    msec : 0
  });
}
