<?php
/* ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
    ┃ 데이터베이스 관련 함수                                                                                                                           ┃
    ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛*/
$er_conn = mysqli_connect("49.247.46.254","root","Gkstlr59!","errorlist");
mysqli_query($er_conn, "set names utf8mb4");

//SQL 쿼리 실행 함수
function er_db_query($sql){
    global $er_conn;

    $rs = mysqli_query($er_conn, $sql);
    return $rs;
}

//개별데이터
function er_db_select($sql){
    $rs = er_db_query($sql);
    return @er_db_fetch($rs);
}

//데이터를 배열로 가져오기
function er_db_fetch($rs){
    return @mysqli_fetch_array($rs);
}

function er_db_result($sql){
    $rs = er_db_query($sql);
    $row = mysqli_fetch_array($rs);
    if($row ) return @$row[0];
}

//농협
function 농협_입금_원사이금액($message) {
    // "입금"과 "원" 사이의 금액을 정규식을 사용하여 추출
    $pattern = '/입금([0-9,]+)원/';
    preg_match($pattern, $message, $matches);

    if (isset($matches[1])) {
        // 추출된 금액에서 쉼표(,) 제거 후 정수로 변환
        $amount = intval(str_replace(',', '', $matches[1]));
        return $amount;
    } else {
        // 일치하는 패턴이 없는 경우 또는 금액 추출에 실패한 경우
        return null;
    }
}
function 농협_입금자명추출($message, $delimiter) {
  // 주어진 구분자를 기준으로 문자열을 분리
  $parts = explode($delimiter, $message);
  $입금자명 = explode(" ",$parts[1]);
  // 배열의 첫 번째 요소 반환
  return $입금자명[1];
}


//기업
function 기업은행_문자_입금자명($inputString) {
    // 정규 표현식을 사용하여 "원"과 "017" 사이의 문자열을 추출합니다.
    preg_match('/원(.*?)017/', $inputString, $matches);

    if (!empty($matches[1])) {
        $extractedString = trim($matches[1]); // 공백 제거
        return $extractedString;
    } else {
        return "일치하는 문자열을 찾을 수 없습니다.";
    }
}

function 기업은행_입금_이후문자열($input) {
  // 정규식 패턴
   $pattern = '/입금\s+(.+)/u';

   // 정규식을 사용하여 매칭된 부분을 찾음
   preg_match($pattern, $input, $matches);

   // 매칭된 부분이 있다면 반환, 없다면 빈 문자열 반환
   return isset($matches[1]) ? trim($matches[1]) : '';
}
function 기업은행_입금금액부분_추출($input) {
  // 첫 번째 '원' 이전의 문자열을 찾음
  $position = strpos($input, '원');

  if ($position !== false) {
      // '원' 이전의 문자열을 제거하고 나머지를 반환
      $result = substr($input, $position + 3);
      return $result;
  } else {
      // '원'이 없으면 원래 문자열을 그대로 반환
      return $input;
  }
}


function 기업은행_입금자명_추출($input, $identifier) {
    // 정규식 패턴
    $pattern = '/(.+)' . preg_quote($identifier, '/') . '/u';

    // 정규식을 사용하여 매칭된 부분을 찾음
    preg_match($pattern, $input, $matches);

    // 매칭된 부분이 있다면 반환, 없다면 빈 문자열 반환
    return isset($matches[1]) ? trim($matches[1]) : '';
}

function 기업은행_잔액부분_추출($message) {
    preg_match('/잔액\s*([\d,]+)원/u', $message, $balanceMatch);

    $balance = isset($balanceMatch[1]) ? $balanceMatch[1] . '원' : null;
    $rest = preg_replace('/잔액\s*[\d,]+원/u', '', $message);

    return [
        'balance' => $balance,
        'rest' => trim($rest)
    ];
}

function 기업은행_문자_입금금액($inputString) {
    $pattern = '/(?:입금이|입금)\s*(\d+)/u';
    preg_match_all($pattern, $inputString, $matches);

    if (empty($matches[1])) {
        return "";
    }

    $extractedText = implode("", $matches[1]);
    return $extractedText;
}

//신한
function 신한_콤마전부제거($string) {
    return str_replace(',', '', $string);
}

function 신한_입금뒤문자열만추출($input) {
  // '입금' 다음의 문자열을 찾음
   $depositPattern = '/입금\s*(.+)$/u';
   preg_match($depositPattern, $input, $textMatches);

   // 결과 반환
   $result = !empty($textMatches) ? trim($textMatches[1]) : '';

   return $result;
}

function 신한_금액_입금자명분리($input) {
  // 앞에 있는 숫자 형식을 찾음
    $numberPattern = '/^\d+/';
    preg_match($numberPattern, $input, $numberMatches);

    // 뒤에 있는 숫자가 아닌 형식을 찾음
    $nonNumericPattern = '/\D+$/u';
    preg_match($nonNumericPattern, $input, $nonNumericMatches);

    // 결과 반환
    $result = [
        'number' => !empty($numberMatches) ? $numberMatches[0] : '',
        'nonNumeric' => !empty($nonNumericMatches) ? trim($nonNumericMatches[0]) : ''
    ];

    return $result;
}


function 국민_계좌번호_제거후_문자내용($inputString, $pattern) {
    $outputString = preg_replace('/' . preg_quote($pattern, '/') . '/', '', $inputString);
    return $outputString;
}



function 국민_입금자명_옆_입금상태제거($inputString) {
    // 함수 내부에 키워드 정의
    $keywordsToRemove = array("전자금융", "간편이체", "오픈뱅킹");

    // 키워드를 순회하면서 해당 부분 제거
    foreach ($keywordsToRemove as $keyword) {
        $inputString = str_ireplace($keyword, '', $inputString);
    }

    return $inputString;
}


function 국민_문자열맨끝_숫자형식만추출($inputString) {
    // 정규식을 사용하여 문자열의 맨 끝에 있는 숫자형식 데이터 추출 (콤마 포함)
    preg_match('/[\d,]+$/', $inputString, $matches);

    // 추출된 숫자를 정리하여 반환, 없을 경우 기본값인 0 반환
    if (isset($matches[0])) {
        $numberString = str_replace(',', '', $matches[0]); // 콤마 제거
        return (int)$numberString;
    } else {
        return 0;
    }
}

function 국민_입금자명만추출($inputString) {
    // 정규식을 사용하여 맨 앞의 2자리 숫자 제거
    $outputString = preg_replace('/^\d{2}/', '', $inputString);

    return $outputString;
}


function 문자열중간에소괄호체크($inputString) {
    // '(주)'를 일단 임시로 치환해서 판단 대상에서 제외
    $modifiedString = str_replace('(주)', '', $inputString);

    $posParenthesis = strpos($modifiedString, '(');  // 나머지 괄호 위치 찾기
    $posFBS = strpos($modifiedString, 'FBS');        // FBS 위치 찾기

    // 기준에 따라 자르기
    if ($posParenthesis !== false && ($posFBS === false || $posParenthesis < $posFBS)) {
        return substr($modifiedString, 0, $posParenthesis);
    } elseif ($posFBS !== false && ($posParenthesis === false || $posFBS < $posParenthesis)) {
        return substr($modifiedString, 0, $posFBS);
    }

    // 둘 다 없으면 원래 문자열 반환 (단, (주) 제거한 modifiedString이 아니라 원본)
    return $inputString;
}


function 특정_단어_제거($inputString) {
    $wordsToRemove = array("스마트폰", "FBS", "리브");

    // 단어를 찾아서 제거
    $result = str_replace($wordsToRemove, '', $inputString);

    return $result;
}

function 하나_계좌번호부분_제외한문자열1($message,$계좌) {
    // 카드 번호 부분 찾기
    $cardNumberPosition = strpos($message, $계좌);
    // 카드 번호를 찾은 경우
    if ($cardNumberPosition !== false) {
        // 문자열에서 카드 번호 제외
        $result = substr_replace($message, '', $cardNumberPosition, strlen($계좌));
    } else {
        // 카드 번호를 찾지 못한 경우 원래 문자열 반환
        $result = $message;
    }
    return $result;
}

function 하나_문자열_금액_입금자추출1($text) {
    // 정규식을 사용하여 금액과 원 뒤의 텍스트를 추출
    preg_match('/입금(\d+(?:,\d{3})*)(원)(.*)/', $text, $matches);
    // $matches 배열에서 추출된 값 확인
    $amount = isset($matches[1]) ? $matches[1] : null;
    $unit = isset($matches[2]) ? $matches[2] : null;
    $restOfText = isset($matches[3]) ? trim($matches[3]) : null;

    // 반환할 연관 배열 생성
    $result = array(
        'amount' => $amount,
        'name' => $restOfText
    );
    return $result;
}

function 우리_추출_금액과_텍스트($text) {
  // "원"으로 시작하는 부분을 찾습니다.
  preg_match('/(\d{1,3}(,\d{3})*)원/', $text, $amountMatch);
  $amount = trim(str_replace(',', '', $amountMatch[1]));

  // "원" 이후의 문자열을 찾습니다.
  $name = trim(preg_replace('/(\d{1,3}(,\d{3})*)원/', '', $text));

  return array("amount" => $amount, "name" => $name);
}

function 하나은행20241113($message) {
    // "입금"을 기준으로 문자열을 나누기
    $parts = explode("입금", $message);

    // 금액과 이름이 포함된 부분 추출
    if (count($parts) < 2) {
        return ["amount" => null, "name" => null];
    }

    // 입금 이후 부분을 다루기
    $amountAndName = trim($parts[1]);

    // 첫 번째 "원"을 기준으로 나누기
    $splitByWon = explode("원", $amountAndName, 2);
    $amount = trim($splitByWon[0]); // 금액 부분
    $name = isset($splitByWon[1]) ? trim($splitByWon[1]) : null; // 이름 부분

    return ["amount" => $amount, "name" => $name];
}

function SC제일은행_입금자명추출($input){
    // 첫 두 자리 숫자를 제거하기 위한 정규 표현식
    $pattern = '/^\d{2}(.*)/';
    preg_match($pattern, $input, $matches);

    // 매칭된 결과에서 첫 두 자리 숫자 제거한 부분 반환
    return $matches[1];
}

function SC제일은행_입금금액추출($input) {
  // '입금' 뒤의 숫자를 추출 (천 단위 구분 기호 포함)
  $pattern = '/입금([\d,]+)/';
  preg_match($pattern, $input, $matches);

  // 추출한 숫자 문자열에서 콤마를 제거
  $amount = intval(str_replace(',', '', $matches[1]));

  return $amount;
}

function 수협_계좌번호기준으로분리($message, $계좌) {
    // 카드 번호 부분 찾기
    $cardNumberPosition = strpos($message, $계좌);
    // 카드 번호를 찾은 경우
    if ($cardNumberPosition !== false) {
        // 문자열에서 카드 번호 제외
        $result = explode($계좌, $message);
    } else {
        // 카드 번호를 찾지 못한 경우 원래 문자열 반환
        $result = $message;
    }

    return $result[1];
}

function 수협_문자열_금액_입금자추출1($string) {
    // 정규 표현식을 사용하여 "입금"과 "원" 사이의 숫자, 그리고 "원" 뒤의 텍스트를 추출
    if (preg_match('/입금(\d+)원(.*)/', $string, $matches)) {
        $amount = $matches[1]; // "입금"과 "원" 사이의 숫자
        $afterText = trim($matches[2]); // "원" 뒤의 텍스트, 앞뒤 공백 제거

        return [
          'name' => $afterText,
          'amount' => $amount
        ];
    }

    return null; // 매칭되지 않으면 null 반환
}
function 수협_입금자명_옆_입금상태제거($inputString) {
    // 함수 내부에 키워드 정의
    $keywordsToRemove = array("전자금융", "간편이체", "오픈뱅킹");

    // 키워드를 순회하면서 해당 부분 제거
    foreach ($keywordsToRemove as $keyword) {
        $inputString = str_ireplace($keyword, '', $inputString);
    }

    return $inputString;
}

function 케이뱅크_입금금액추출($string) {
    // 정규 표현식을 사용하여 "입금"과 "원" 사이의 숫자를 찾습니다.
    preg_match('/입금\s+(\d+)\s*원/', $string, $matches);

    // 숫자가 발견되면 반환, 아니면 null 반환
    return isset($matches[1]) ? $matches[1] : null;
}
function 케이뱅크_입금자명추출($string) {
    // 정규 표현식을 사용하여 "잔액"과 "원" 뒤의 문자열을 찾습니다.
    preg_match('/잔액\s*.*?원(.*)/', $string, $matches);

    // 결과를 반환
    return isset($matches[1]) ? trim($matches[1]) : null;
}

